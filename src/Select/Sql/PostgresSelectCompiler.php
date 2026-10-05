<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Sql;

use Wolfcharaa\TableRegistry\Exception\SelectCompilationException;
use Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier;
use Wolfcharaa\TableRegistry\Internal\Select\Sql\ParameterBinder;
use Wolfcharaa\TableRegistry\Select\Definition\SelectCountDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectCriterionDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectJoinDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectOrderDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSortDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Query\SelectNullability;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryInput;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidator;
use Wolfcharaa\TableRegistry\Select\Query\SelectRange;
use Wolfcharaa\TableRegistry\Select\Query\SelectSortDirection;
use Wolfcharaa\TableRegistry\Select\Runtime\NativeResolverInvoker;
use Wolfcharaa\TableRegistry\Select\Runtime\ResolverInvokerInterface;
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeContext;

/**
 * @api
 */
final class PostgresSelectCompiler implements SelectCompilerInterface
{
    public function __construct(
        private readonly ResolverInvokerInterface $resolverInvoker = new NativeResolverInvoker(),
        private readonly SelectQueryValidator $validator = new SelectQueryValidator()
    ) {
    }

    public function compile(SelectDefinition $definition, ?SelectQueryInput $query = null): CompiledSelect
    {
        $input = $query ?? SelectQueryInput::empty();
        $this->validator->validate($definition, $input)->assertValid();

        $binder = new ParameterBinder();
        $where = $this->whereSql($definition, $input, $binder);
        $order = $this->orderSql($definition, $input);
        $pagination = $this->paginationSql($definition, $input);

        $fields = \array_map(
            fn ($field): string => '    ' . $this->fieldExpression($field)
                . ' AS ' . Identifier::quote($field->alias()),
            $definition->fields()
        );

        $sql = "SELECT\n" . \implode(",\n", $fields)
            . "\nFROM " . $this->sourceSql($definition->source())
            . $this->joinsSql($definition->source())
            . $where
            . $order
            . $pagination;

        return new CompiledSelect($sql, $binder->params());
    }

    public function compileReadPlan(
        SelectDefinition $definition,
        ?SelectQueryInput $query = null
    ): CompiledSelectReadPlan {
        $input = $query ?? SelectQueryInput::empty();
        $rows = $this->compile($definition, $input);
        $count = $definition->count() !== null
            ? $this->compileCount($definition, $input, $definition->count())
            : null;

        return new CompiledSelectReadPlan($rows, $count);
    }

    private function compileCount(
        SelectDefinition $definition,
        SelectQueryInput $input,
        SelectCountDefinition $count
    ): CompiledSelect {
        $this->validator->validate($definition, $input)->assertValid();

        $binder = new ParameterBinder();
        $source = $count->source() ?? $definition->source();
        $where = $this->whereSql($definition, $input, $binder);
        $sql = \sprintf(
            "SELECT %s AS %s\nFROM %s%s%s",
            $count->expression(),
            Identifier::quote($count->alias()),
            $this->sourceSql($source),
            $this->joinsSql($source),
            $where
        );

        return new CompiledSelect($sql, $binder->params());
    }

    private function sourceSql(SelectSourceDefinition $source): string
    {
        if (!$source->isRelation()) {
            return $source->sql();
        }

        $sql = Identifier::quoteQualified($source->sql());
        if ($source->alias() !== null) {
            $sql .= ' AS ' . Identifier::quote($source->alias());
        }

        return $sql;
    }

    private function joinsSql(SelectSourceDefinition $source): string
    {
        if ($source->joins() === []) {
            return '';
        }

        return "\n" . \implode("\n", \array_map(
            fn (SelectJoinDefinition $join): string => $this->joinSql($join),
            $source->joins()
        ));
    }

    private function joinSql(SelectJoinDefinition $join): string
    {
        if ($join->raw() !== null) {
            return $join->raw();
        }

        $relation = $join->relation();
        $on = $join->on();
        if ($relation === null || $on === null) {
            throw SelectCompilationException::because('Structured select JOIN is incomplete.');
        }

        $sql = $join->type() === 'left' ? 'LEFT JOIN ' : 'INNER JOIN ';
        $sql .= Identifier::quoteQualified($relation);
        if ($join->alias() !== null) {
            $sql .= ' AS ' . Identifier::quote($join->alias());
        }

        return $sql . ' ON ' . $on;
    }

    private function fieldExpression(\Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition $field): string
    {
        return $field->isColumn()
            ? Identifier::quoteQualified($field->expression())
            : $field->expression();
    }

    private function whereSql(
        SelectDefinition $definition,
        SelectQueryInput $input,
        ParameterBinder $binder
    ): string {
        $conditions = [];

        foreach ($definition->criteria() as $criterion) {
            if (!$input->hasCriterion($criterion->key())) {
                continue;
            }

            $conditions[] = $this->criterionSql($criterion, $input->criterion($criterion->key()), $binder);
        }

        if ($input->search() !== null && $definition->search() !== null) {
            $search = '%' . $this->escapeLike($input->search()) . '%';
            $searchConditions = [];
            foreach ($definition->search()->expressions() as $expression) {
                $searchConditions[] = \sprintf(
                    "CAST(%s AS text) ILIKE %s ESCAPE E'\\\\'",
                    $expression,
                    $binder->bind($search)
                );
            }
            $conditions[] = '(' . \implode(' OR ', $searchConditions) . ')';
        }

        if ($definition->runtimeConstraints() !== []) {
            $context = new SelectRuntimeContext(
                $definition,
                $input,
                static fn (mixed $value): string => $binder->bind($value)
            );

            foreach ($definition->runtimeConstraints() as $resolver) {
                $condition = $this->resolverInvoker->invoke($resolver, ['context' => $context]);
                if ($condition === null) {
                    continue;
                }

                if (!$condition instanceof SelectCondition) {
                    throw SelectCompilationException::because(\sprintf(
                        'Select runtime constraint must return %s or null.',
                        SelectCondition::class
                    ));
                }

                $conditions[] = '(' . $condition->toSql() . ')';
            }
        }

        return $conditions === [] ? '' : "\nWHERE " . \implode("\n  AND ", $conditions);
    }

    private function criterionSql(
        SelectCriterionDefinition $criterion,
        mixed $value,
        ParameterBinder $binder
    ): string {
        $expression = $criterion->expression();

        return match ($criterion->operator()) {
            SelectCriterionDefinition::EQUAL => $expression . ' = ' . $binder->bind($value),
            SelectCriterionDefinition::ONE_OF => $this->inSql($expression, $value, $binder),
            SelectCriterionDefinition::EQUAL_OR_ONE_OF => \is_array($value)
                ? $this->inSql($expression, $value, $binder)
                : $expression . ' = ' . $binder->bind($value),
            SelectCriterionDefinition::CONTAINS => $this->containsSql($expression, $value, $binder),
            SelectCriterionDefinition::GREATER_THAN => $expression . ' > ' . $binder->bind($value),
            SelectCriterionDefinition::GREATER_THAN_OR_EQUAL => $expression . ' >= ' . $binder->bind($value),
            SelectCriterionDefinition::LESS_THAN => $expression . ' < ' . $binder->bind($value),
            SelectCriterionDefinition::LESS_THAN_OR_EQUAL => $expression . ' <= ' . $binder->bind($value),
            SelectCriterionDefinition::RANGE => $this->rangeSql($expression, $value, $binder),
            SelectCriterionDefinition::NULLABILITY => $this->nullabilitySql($expression, $value),
            default => throw SelectCompilationException::because(\sprintf(
                'Unsupported select criterion operator "%s".',
                $criterion->operator()
            )),
        };
    }

    private function inSql(string $expression, mixed $value, ParameterBinder $binder): string
    {
        if (!\is_array($value)) {
            throw SelectCompilationException::because('Select IN criterion requires a list.');
        }

        return \sprintf(
            '%s IN (%s)',
            $expression,
            \implode(', ', \array_map(
                static fn (mixed $item): string => $binder->bind($item),
                $value
            ))
        );
    }

    private function containsSql(string $expression, mixed $value, ParameterBinder $binder): string
    {
        if (!\is_string($value)) {
            throw SelectCompilationException::because('Select contains criterion requires a string.');
        }

        return \sprintf(
            "CAST(%s AS text) ILIKE %s ESCAPE E'\\\\'",
            $expression,
            $binder->bind('%' . $this->escapeLike($value) . '%')
        );
    }

    private function rangeSql(string $expression, mixed $value, ParameterBinder $binder): string
    {
        if (!$value instanceof SelectRange) {
            throw SelectCompilationException::because('Select range criterion requires SelectRange.');
        }

        $conditions = [];
        if ($value->hasFrom()) {
            $conditions[] = $expression . ' >= ' . $binder->bind($value->fromValue());
        }
        if ($value->hasTo()) {
            $conditions[] = $expression . ' <= ' . $binder->bind($value->toValue());
        }

        return \count($conditions) === 1 ? $conditions[0] : '(' . \implode(' AND ', $conditions) . ')';
    }

    private function nullabilitySql(string $expression, mixed $value): string
    {
        if (!$value instanceof SelectNullability) {
            throw SelectCompilationException::because('Select nullability criterion requires SelectNullability.');
        }

        return $expression . ($value === SelectNullability::IsNull ? ' IS NULL' : ' IS NOT NULL');
    }

    private function orderSql(SelectDefinition $definition, SelectQueryInput $input): string
    {
        $parts = [];
        $expressions = [];

        if ($input->sortKey() !== null) {
            $sort = $definition->sort($input->sortKey());
            $direction = $input->sortDirection();
            if ($sort !== null && $direction !== null) {
                $parts[] = $this->sortSql($sort, $direction);
                $expressions[\trim($sort->expression())] = true;
            }
        }

        foreach ($definition->order() as $order) {
            $expression = \trim($order->expression());
            if (isset($expressions[$expression])) {
                continue;
            }

            $parts[] = $this->orderTermSql($order);
            $expressions[$expression] = true;
        }

        return $parts === [] ? '' : "\nORDER BY " . \implode(', ', $parts);
    }

    private function sortSql(SelectSortDefinition $sort, SelectSortDirection $direction): string
    {
        return $sort->expression() . ' ' . \strtoupper($direction->value) . $this->nullsSql($sort->nulls());
    }

    private function orderTermSql(SelectOrderDefinition $order): string
    {
        return $order->expression() . ' ' . \strtoupper($order->direction()->value)
            . $this->nullsSql($order->nulls());
    }

    private function nullsSql(?string $nulls): string
    {
        return $nulls === null ? '' : ' NULLS ' . \strtoupper($nulls);
    }

    private function paginationSql(SelectDefinition $definition, SelectQueryInput $input): string
    {
        $pagination = $definition->pagination();
        if ($pagination === null) {
            return '';
        }

        return \sprintf(
            "\nLIMIT %d OFFSET %d",
            $input->limit() ?? $pagination->defaultLimit(),
            $input->offset() ?? 0
        );
    }

    private function escapeLike(string $value): string
    {
        return \str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
