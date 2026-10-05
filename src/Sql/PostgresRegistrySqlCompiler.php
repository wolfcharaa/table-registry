<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sql;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\RegistryFilterExpressionInterface;
use Wolfcharaa\TableRegistry\Filter\TimeRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Runtime\NativeRegistryResolverInvoker;
use Wolfcharaa\TableRegistry\Runtime\RegistryResolverInvokerInterface;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

/**
 * @api
 */
final class PostgresRegistrySqlCompiler
{
    public function __construct(
        private readonly RegistryResolverInvokerInterface $resolverInvoker = new NativeRegistryResolverInvoker()
    ) {
    }

    /**
     * @param RegistrySqlQueryInput|array<string, mixed> $query
     */
    public function compileRows(TableRegistryDefinition $definition, RegistrySqlQueryInput|array $query = []): CompiledRegistrySql
    {
        $input = $this->queryInput($definition, $query);

        $params = [];
        $paramIndex = 0;
        $where = $this->whereSql($definition, $input, $params, $paramIndex);
        $orderBy = $this->orderBySql($definition, $input);

        $sql = \sprintf(
            "SELECT\n%s\nFROM %s\n%s%s%s\nLIMIT %d OFFSET %d",
            $this->selectSql($definition),
            $definition->source()->fromSql(),
            $this->joinsSql($definition),
            $where,
            $orderBy,
            $input->perPage(),
            $input->offset()
        );

        return new CompiledRegistrySql($sql, $params);
    }

    /**
     * @param RegistrySqlQueryInput|array<string, mixed> $query
     */
    public function compileExportRows(TableRegistryDefinition $definition, RegistrySqlQueryInput|array $query = []): CompiledRegistrySql
    {
        $input = $this->queryInput($definition, $query);

        $params = [];
        $paramIndex = 0;
        $where = $this->whereSql($definition, $input, $params, $paramIndex);
        $orderBy = $this->orderBySql($definition, $input);

        $sql = \sprintf(
            "SELECT\n%s\nFROM %s\n%s%s%s",
            $this->selectSql($definition),
            $definition->source()->fromSql(),
            $this->joinsSql($definition),
            $where,
            $orderBy
        );

        return new CompiledRegistrySql($sql, $params);
    }

    /**
     * @param RegistrySqlQueryInput|array<string, mixed> $query
     */
    public function compileCount(TableRegistryDefinition $definition, RegistrySqlQueryInput|array $query = []): CompiledRegistrySql
    {
        $input = $this->queryInput($definition, $query);

        $params = [];
        $paramIndex = 0;
        $where = $this->whereSql($definition, $input, $params, $paramIndex);

        $sql = \sprintf(
            "SELECT COUNT(*)::bigint AS \"total\"\nFROM %s\n%s%s",
            $this->countFromSql($definition->source()),
            $this->joinsSql($definition),
            $where
        );

        return new CompiledRegistrySql($sql, $params);
    }

    private function countFromSql(RegistrySourceDefinition $source): string
    {
        $countSource = $source->countSource();
        if ($countSource === null || !$this->isSqlFromSource($countSource)) {
            return $source->fromSql();
        }

        return $countSource;
    }

    private function isSqlFromSource(string $source): bool
    {
        if (\str_contains($source, ':')) {
            return false;
        }

        return true;
    }

    private function selectSql(TableRegistryDefinition $definition): string
    {
        $parts = [];

        foreach ($definition->columns()->all() as $column) {
            if ($column->outputOnly()) {
                continue;
            }

            $parts[] = \sprintf(
                '    %s AS %s',
                $this->columnExpression($column),
                PostgresIdentifier::value($column->key())->quoted()
            );

            foreach ($column->auxiliarySelects() as $select) {
                $parts[] = \sprintf(
                    '    %s AS %s',
                    $select->expression(),
                    PostgresIdentifier::value($select->alias())->quoted()
                );
            }
        }

        return \implode(",\n", $parts);
    }

    private function joinsSql(TableRegistryDefinition $definition): string
    {
        $joins = $definition->source()->joins();
        if ($joins === []) {
            return '';
        }

        return \implode("\n", $joins) . "\n";
    }

    /**
     * @param array<string, mixed> $params
     */
    private function whereSql(
        TableRegistryDefinition $definition,
        RegistrySqlQueryInput $query,
        array &$params,
        int &$paramIndex
    ): string
    {
        $conditions = [];

        $this->applySearch($definition, $query, $conditions, $params, $paramIndex);
        $this->applyFilters($definition, $query, $conditions, $params, $paramIndex);
        $this->applySelectedPrimaryKeys($definition, $query, $conditions, $params, $paramIndex);
        $this->applyRuntimeConstraints($definition, $query, $conditions, $params, $paramIndex);

        if ($conditions === []) {
            return '';
        }

        return 'WHERE ' . \implode("\n  AND ", $conditions) . "\n";
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     */
    private function applySearch(
        TableRegistryDefinition $definition,
        RegistrySqlQueryInput $query,
        array &$conditions,
        array &$params,
        int &$paramIndex
    ): void {
        if (!$definition->search()->isEnabled()) {
            return;
        }

        $search = $query->search();
        if ($search === '') {
            return;
        }

        $searchableColumns = $definition->columns()->searchable();
        if ($searchableColumns === []) {
            return;
        }

        $searchConditions = [];
        foreach ($searchableColumns as $column) {
            $param = $this->nextParam($params, $paramIndex, '%' . $search . '%');
            $searchConditions[] = \sprintf(
                'CAST(%s AS text) ILIKE :%s',
                $this->columnExpression($column),
                $param
            );
        }

        $conditions[] = '(' . \implode(' OR ', $searchConditions) . ')';
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     */
    private function applyFilters(
        TableRegistryDefinition $definition,
        RegistrySqlQueryInput $query,
        array &$conditions,
        array &$params,
        int &$paramIndex
    ): void {
        foreach ($definition->columns()->all() as $column) {
            if ($column->outputOnly()) {
                continue;
            }

            $filter = $column->filter();
            if ($filter === null) {
                continue;
            }

            if ($filter instanceof DateRangeFilterRegistryDefinition) {
                $this->applyDateRangeFilter($column, $filter, $query, $conditions, $params, $paramIndex);
                continue;
            }

            if ($filter instanceof TimeRangeFilterRegistryDefinition) {
                $this->applyTimeRangeFilter($column, $filter, $query, $conditions, $params, $paramIndex);
                continue;
            }

            if (!$query->hasFilterValue($filter->queryKey())) {
                continue;
            }

            $value = $filter->normalize($query->filterValue($filter->queryKey()));
            if ($value === null || $value === []) {
                continue;
            }

            $this->applyFilterExpression($column, $filter->expression($value), $conditions, $params, $paramIndex);
        }
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     */
    private function applyTimeRangeFilter(
        ColumnRegistryDefinition $column,
        TimeRangeFilterRegistryDefinition $filter,
        RegistrySqlQueryInput $query,
        array &$conditions,
        array &$params,
        int &$paramIndex
    ): void {
        $expression = $this->columnExpression($column);
        $from = $this->timeQueryValue($query->filterValue($filter->fromKey()));
        $to = $this->timeQueryValue($query->filterValue($filter->toKey()));

        if ($from !== '') {
            $param = $this->nextParam($params, $paramIndex, $from);
            $conditions[] = \sprintf('%s >= :%s', $expression, $param);
        }

        if ($to !== '') {
            $param = $this->nextParam($params, $paramIndex, $to);
            $conditions[] = \sprintf('%s <= :%s', $expression, $param);
        }
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     */
    private function applyDateRangeFilter(
        ColumnRegistryDefinition $column,
        DateRangeFilterRegistryDefinition $filter,
        RegistrySqlQueryInput $query,
        array &$conditions,
        array &$params,
        int &$paramIndex
    ): void {
        $expression = $this->columnExpression($column);
        $from = $this->stringQueryValue($query->filterValue($filter->fromKey()));
        $to = $this->stringQueryValue($query->filterValue($filter->toKey()));

        if ($from !== '') {
            $param = $this->nextParam($params, $paramIndex, $from . ' 00:00:00');
            $conditions[] = \sprintf('%s >= :%s', $expression, $param);
        }

        if ($to !== '') {
            $toDate = (new \DateTimeImmutable($to . ' 00:00:00'))
                ->modify('+1 day')
                ->format('Y-m-d H:i:s');
            $param = $this->nextParam($params, $paramIndex, $toDate);
            $conditions[] = \sprintf('%s < :%s', $expression, $param);
        }
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     */
    private function applyFilterExpression(
        ColumnRegistryDefinition $column,
        RegistryFilterExpressionInterface $filter,
        array &$conditions,
        array &$params,
        int &$paramIndex
    ): void {
        $expression = $this->columnExpression($column);
        $operator = $filter->operator();
        $value = $filter->value();

        if (\is_array($value)) {
            if ($value === []) {
                return;
            }

            $placeholders = [];
            foreach ($value as $item) {
                $param = $this->nextParam($params, $paramIndex, $item);
                $placeholders[] = ':' . $param;
            }

            $conditions[] = \sprintf('%s IN (%s)', $expression, \implode(', ', $placeholders));
            return;
        }

        if ($operator === 'contains') {
            $param = $this->nextParam($params, $paramIndex, '%' . $this->scalarString($value) . '%');
            $conditions[] = \sprintf('CAST(%s AS text) ILIKE :%s', $expression, $param);
            return;
        }

        if ($operator === 'is_null') {
            $conditions[] = \sprintf('%s IS NULL', $expression);
            return;
        }

        if ($operator === 'is_not_null') {
            $conditions[] = \sprintf('%s IS NOT NULL', $expression);
            return;
        }

        $param = $this->nextParam($params, $paramIndex, $value);
        $conditions[] = \sprintf('%s = :%s', $expression, $param);
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     */
    private function applySelectedPrimaryKeys(
        TableRegistryDefinition $definition,
        RegistrySqlQueryInput $query,
        array &$conditions,
        array &$params,
        int &$paramIndex
    ): void {
        $selectedPrimaryKeys = $query->selectedPrimaryKeys();
        if ($selectedPrimaryKeys === []) {
            return;
        }

        $placeholders = [];
        foreach ($selectedPrimaryKeys as $primaryKey) {
            $placeholders[] = ':' . $this->nextParam($params, $paramIndex, $primaryKey);
        }

        $conditions[] = \sprintf(
            '%s IN (%s)',
            $this->columnExpression($definition->columns()->primaryKey()),
            \implode(', ', $placeholders)
        );
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     */
    private function applyRuntimeConstraints(
        TableRegistryDefinition $definition,
        RegistrySqlQueryInput $query,
        array &$conditions,
        array &$params,
        int &$paramIndex
    ): void {
        $runtimeConstraints = $definition->runtimeConstraints();
        if ($runtimeConstraints === []) {
            return;
        }

        $context = new RegistrySqlRuntimeContext(
            $definition,
            $query,
            function (mixed $value) use (&$params, &$paramIndex): string {
                return $this->nextParam($params, $paramIndex, $value);
            }
        );

        foreach ($runtimeConstraints as $resolver) {
            $condition = $this->resolverInvoker->invoke($resolver, [
                'context' => $context,
                'definition' => $definition,
                'query' => $query,
            ]);

            if ($condition === null) {
                continue;
            }

            if (!$condition instanceof RegistrySqlCondition) {
                throw new \UnexpectedValueException(\sprintf(
                    'Registry runtime constraint must return %s or null.',
                    RegistrySqlCondition::class
                ));
            }

            foreach ($condition->params() as $key => $value) {
                if (\array_key_exists($key, $params)) {
                    throw new \LogicException(\sprintf('Registry SQL parameter "%s" is already defined.', $key));
                }

                $params[$key] = $value;
            }

            $conditions[] = '(' . $condition->sql() . ')';
        }
    }

    private function orderBySql(TableRegistryDefinition $definition, RegistrySqlQueryInput $query): string
    {
        $column = null;
        $direction = \strtoupper($query->sortDirection());
        $sortColumn = $query->sortColumn();

        if ($sortColumn !== '') {
            $candidate = $definition->columns()->get($sortColumn);
            if ($candidate !== null && $candidate->sortable()) {
                $column = $candidate;
            }
        }

        if ($column === null && $definition->defaultSort() !== null) {
            $candidate = $definition->columns()->get($definition->defaultSort()->columnKey());
            if ($candidate !== null && !$candidate->outputOnly()) {
                $column = $candidate;
                $direction = \strtoupper($definition->defaultSort()->direction());
            }
        }

        if ($column === null) {
            $column = $definition->columns()->primaryKey();
            $direction = 'ASC';
        }

        $sortExpression = $column->sort()?->expression() ?? $this->columnExpression($column);

        return \sprintf("ORDER BY %s %s\n", $sortExpression, $direction);
    }

    private function columnExpression(ColumnRegistryDefinition $column): string
    {
        return $column->select()?->expression() ?? PostgresIdentifier::value($column->key())->quoted();
    }

    private function stringQueryValue(mixed $value): string
    {
        return \trim($this->scalarString($value));
    }

    private function timeQueryValue(mixed $value): string
    {
        $value = \trim($this->scalarString($value));
        if ($value === '' || \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $matches) !== 1) {
            return '';
        }

        return \sprintf('%02d:%02d:%02d', (int)$matches[1], (int)$matches[2], (int)($matches[3] ?? 0));
    }

    private function scalarString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function nextParam(array &$params, int &$paramIndex, mixed $value): string
    {
        $key = 'p' . $paramIndex++;
        $params[$key] = $value;

        return $key;
    }

    /**
     * @param RegistrySqlQueryInput|array<string, mixed> $query
     */
    private function queryInput(TableRegistryDefinition $definition, RegistrySqlQueryInput|array $query): RegistrySqlQueryInput
    {
        return $query instanceof RegistrySqlQueryInput
            ? $query
            : RegistrySqlQueryInput::fromArray($definition, $query);
    }
}
