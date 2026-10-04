<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sql;

use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;

/**
 * @api
 */
final class RegistrySqlQueryInput
{
    public const SORT_COLUMN_KEY = 'sort_col';
    public const SORT_DIRECTION_KEY = 'sort_dir';

    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        private readonly string $search = '',
        private readonly int $page = 1,
        private readonly int $perPage = 25,
        private readonly string $sortColumn = '',
        private readonly string $sortDirection = 'asc',
        private readonly array $filters = [],
        private readonly array $selectedPrimaryKeys = []
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromArray(TableRegistryDefinition $definition, array $query): self
    {
        $pagination = $definition->pagination();
        $page = self::positiveInt($query[$pagination->pageKey()] ?? null, 1);
        $perPage = self::positiveInt($query[$pagination->perPageKey()] ?? null, $pagination->defaultPerPage());

        return new self(
            search: self::stringValue($query[$definition->search()->queryKey()] ?? null),
            page: $page,
            perPage: \min($pagination->maxPerPage(), $perPage),
            sortColumn: self::stringValue($query[self::SORT_COLUMN_KEY] ?? null),
            sortDirection: self::normalizeSortDirection($query[self::SORT_DIRECTION_KEY] ?? null),
            filters: $query,
            selectedPrimaryKeys: self::stringList($query['selected_primary_keys'] ?? $query['selected_primary_key'] ?? null)
        );
    }

    public static function empty(TableRegistryDefinition $definition): self
    {
        return self::fromArray($definition, []);
    }

    public function search(): string
    {
        return $this->search;
    }

    public function page(): int
    {
        return \max(1, $this->page);
    }

    public function perPage(): int
    {
        return \max(1, $this->perPage);
    }

    public function offset(): int
    {
        return ($this->page() - 1) * $this->perPage();
    }

    public function sortColumn(): string
    {
        return $this->sortColumn;
    }

    public function sortDirection(): string
    {
        return $this->sortDirection;
    }

    public function hasFilterValue(string $queryKey): bool
    {
        return \array_key_exists($queryKey, $this->filters);
    }

    public function filterValue(string $queryKey): mixed
    {
        return $this->filters[$queryKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @return list<string>
     */
    public function selectedPrimaryKeys(): array
    {
        return $this->selectedPrimaryKeys;
    }

    private static function stringValue(mixed $value): string
    {
        if (\is_array($value)) {
            return '';
        }

        return \trim((string)($value ?? ''));
    }

    private static function normalizeSortDirection(mixed $direction): string
    {
        return \strtolower((string)$direction) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $values = \is_array($value) ? $value : [$value];

        return \array_values(\array_filter(
            \array_map(
                static fn ($item): string => \trim((string)$item),
                $values
            ),
            static fn (string $item): bool => $item !== ''
        ));
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        if (\is_int($value)) {
            return \max(1, $value);
        }

        if (\is_string($value) && \preg_match('/^\d+$/', $value) === 1) {
            return \max(1, (int)$value);
        }

        return $default;
    }
}
