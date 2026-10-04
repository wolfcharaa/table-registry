<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Query;

use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\RegistryFilterDefinitionInterface;
use Wolfcharaa\TableRegistry\Filter\TimeRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;

/**
 * @api
 */
final class RegistryQueryValidator
{
    /**
     * @param array<array-key, mixed> $query
     */
    public function validate(TableRegistryDefinition $definition, array $query): RegistryQueryValidationResult
    {
        $issues = [];

        $this->validateKnownQueryKeys($definition, $query, $issues);
        $this->validateSort($definition, $query, $issues);
        $this->validatePagination($definition, $query, $issues);
        $this->validateSearch($definition, $query, $issues);
        $this->validateFilters($definition, $query, $issues);

        return new RegistryQueryValidationResult($issues);
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validateKnownQueryKeys(
        TableRegistryDefinition $definition,
        array $query,
        array &$issues
    ): void {
        $knownKeys = \array_fill_keys($this->knownQueryKeys($definition), true);

        foreach (\array_keys($query) as $queryKey) {
            if (!\is_string($queryKey)) {
                continue;
            }

            if (!\array_key_exists($queryKey, $knownKeys) && !$this->isEmptyQueryValue($query[$queryKey])) {
                $issues[] = $this->issue(
                    'unknown_query_key',
                    $queryKey,
                    \sprintf('Registry query key "%s" is not defined.', $queryKey)
                );
            }
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validateSort(TableRegistryDefinition $definition, array $query, array &$issues): void
    {
        $sortColumn = $this->stringValue($query[RegistrySqlQueryInput::SORT_COLUMN_KEY] ?? null);
        if ($sortColumn !== '') {
            $column = $definition->columns()->get($sortColumn);
            if ($column === null) {
                $issues[] = $this->issue(
                    'unknown_sort_column',
                    RegistrySqlQueryInput::SORT_COLUMN_KEY,
                    \sprintf('Registry sort column "%s" is not defined.', $sortColumn)
                );
            } elseif (!$column->sortable()) {
                $issues[] = $this->issue(
                    'not_sortable_column',
                    RegistrySqlQueryInput::SORT_COLUMN_KEY,
                    \sprintf('Registry column "%s" is not sortable.', $sortColumn)
                );
            }
        }

        $sortDirection = $this->stringValue($query[RegistrySqlQueryInput::SORT_DIRECTION_KEY] ?? null);
        if ($sortDirection !== '' && !\in_array(\strtolower($sortDirection), ['asc', 'desc'], true)) {
            $issues[] = $this->issue(
                'invalid_sort_direction',
                RegistrySqlQueryInput::SORT_DIRECTION_KEY,
                'Registry sort direction must be "asc" or "desc".'
            );
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validatePagination(TableRegistryDefinition $definition, array $query, array &$issues): void
    {
        $pagination = $definition->pagination();

        if (\array_key_exists($pagination->pageKey(), $query)
            && !$this->isEmptyQueryValue($query[$pagination->pageKey()])
            && $this->positiveIntValue($query[$pagination->pageKey()]) === null
        ) {
            $issues[] = $this->issue(
                'invalid_page',
                $pagination->pageKey(),
                'Registry page must be a positive integer.'
            );
        }

        if (!\array_key_exists($pagination->perPageKey(), $query)
            || $this->isEmptyQueryValue($query[$pagination->perPageKey()])
        ) {
            return;
        }

        $perPage = $this->positiveIntValue($query[$pagination->perPageKey()]);
        if ($perPage === null) {
            $issues[] = $this->issue(
                'invalid_per_page',
                $pagination->perPageKey(),
                'Registry per-page value must be a positive integer.'
            );
            return;
        }

        if ($perPage > $pagination->maxPerPage()) {
            $issues[] = $this->issue(
                'per_page_exceeds_max',
                $pagination->perPageKey(),
                \sprintf('Registry per-page value must not be greater than %d.', $pagination->maxPerPage())
            );
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validateSearch(TableRegistryDefinition $definition, array $query, array &$issues): void
    {
        $search = $definition->search();
        if (!\array_key_exists($search->queryKey(), $query) || $this->isEmptyQueryValue($query[$search->queryKey()])) {
            return;
        }

        if (!$search->isEnabled()) {
            $issues[] = $this->issue(
                'search_disabled',
                $search->queryKey(),
                'Registry search is disabled.'
            );
            return;
        }

        if ($definition->columns()->searchable() === []) {
            $issues[] = $this->issue(
                'search_has_no_columns',
                $search->queryKey(),
                'Registry search has no searchable columns.'
            );
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validateFilters(TableRegistryDefinition $definition, array $query, array &$issues): void
    {
        foreach ($definition->columns()->all() as $column) {
            if ($column->outputOnly()) {
                continue;
            }

            $filter = $column->filter();
            if ($filter === null) {
                continue;
            }

            if ($filter instanceof DateRangeFilterRegistryDefinition) {
                $this->validateDateRangeFilter($filter, $query, $issues);
                continue;
            }

            if ($filter instanceof TimeRangeFilterRegistryDefinition) {
                $this->validateTimeRangeFilter($filter, $query, $issues);
                continue;
            }

            $this->validateFilterValue($filter, $query, $issues);
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validateFilterValue(
        RegistryFilterDefinitionInterface $filter,
        array $query,
        array &$issues
    ): void {
        if (!\array_key_exists($filter->queryKey(), $query) || $this->isEmptyQueryValue($query[$filter->queryKey()])) {
            return;
        }

        $normalized = $filter->normalize($query[$filter->queryKey()]);
        if ($normalized === null || $normalized === []) {
            $issues[] = $this->issue(
                'invalid_filter_value',
                $filter->queryKey(),
                \sprintf('Registry filter "%s" has invalid value.', $filter->queryKey())
            );
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validateDateRangeFilter(
        DateRangeFilterRegistryDefinition $filter,
        array $query,
        array &$issues
    ): void {
        foreach ([$filter->fromKey(), $filter->toKey()] as $queryKey) {
            if (\array_key_exists($queryKey, $query)
                && !$this->isEmptyQueryValue($query[$queryKey])
                && !$this->isDateValue($query[$queryKey])
            ) {
                $issues[] = $this->issue(
                    'invalid_date_filter_value',
                    $queryKey,
                    \sprintf('Registry date filter "%s" must use Y-m-d format.', $queryKey)
                );
            }
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<RegistryQueryValidationIssue> $issues
     */
    private function validateTimeRangeFilter(
        TimeRangeFilterRegistryDefinition $filter,
        array $query,
        array &$issues
    ): void {
        foreach ([$filter->fromKey(), $filter->toKey()] as $queryKey) {
            if (\array_key_exists($queryKey, $query)
                && !$this->isEmptyQueryValue($query[$queryKey])
                && !$this->isTimeValue($query[$queryKey])
            ) {
                $issues[] = $this->issue(
                    'invalid_time_filter_value',
                    $queryKey,
                    \sprintf('Registry time filter "%s" must use HH:MM or HH:MM:SS format.', $queryKey)
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function knownQueryKeys(TableRegistryDefinition $definition): array
    {
        $keys = [
            $definition->search()->queryKey(),
            $definition->pagination()->pageKey(),
            $definition->pagination()->perPageKey(),
            RegistrySqlQueryInput::SORT_COLUMN_KEY,
            RegistrySqlQueryInput::SORT_DIRECTION_KEY,
            'selected_primary_key',
            'selected_primary_keys',
        ];

        foreach ($definition->columns()->all() as $column) {
            if ($column->outputOnly()) {
                continue;
            }

            $filter = $column->filter();
            if ($filter === null) {
                continue;
            }

            $keys[] = $filter->queryKey();

            if ($filter instanceof DateRangeFilterRegistryDefinition
                || $filter instanceof TimeRangeFilterRegistryDefinition
            ) {
                $keys[] = $filter->fromKey();
                $keys[] = $filter->toKey();
            }
        }

        return \array_values(\array_unique($keys));
    }

    private function issue(string $code, string $queryKey, string $message): RegistryQueryValidationIssue
    {
        return new RegistryQueryValidationIssue($code, $queryKey, $message);
    }

    private function isEmptyQueryValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (\is_string($value)) {
            return \trim($value) === '';
        }

        return $value === [];
    }

    private function positiveIntValue(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (\is_string($value) && \preg_match('/^[1-9]\d*$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }

    private function isDateValue(mixed $value): bool
    {
        $value = $this->scalarString($value);
        if ($value === null) {
            return false;
        }

        $value = \trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    private function isTimeValue(mixed $value): bool
    {
        $value = $this->scalarString($value);
        if ($value === null) {
            return false;
        }

        return \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', \trim($value)) === 1;
    }

    private function stringValue(mixed $value): string
    {
        return \trim($this->scalarString($value) ?? '');
    }

    private function scalarString(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }

        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        return null;
    }
}
