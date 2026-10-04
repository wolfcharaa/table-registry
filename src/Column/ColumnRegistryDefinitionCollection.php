<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Column;

/**
 * @api
 */
final class ColumnRegistryDefinitionCollection
{
    /** @var list<ColumnRegistryDefinition> */
    private array $columns;

    /**
     * @param list<ColumnRegistryDefinition> $columns
     */
    public function __construct(array $columns)
    {
        ColumnRegistryDefinitionValidator::assertValid($columns);
        $this->columns = \array_values($columns);
    }

    /**
     * @return list<ColumnRegistryDefinition>
     */
    public function all(): array
    {
        return $this->columns;
    }

    /**
     * @return list<ColumnRegistryDefinition>
     */
    public function exposedInDefinition(): array
    {
        return \array_values(\array_filter(
            $this->columns,
            static fn (ColumnRegistryDefinition $column): bool => $column->exposedInDefinition()
        ));
    }

    /**
     * @return list<ColumnRegistryDefinition>
     */
    public function payload(): array
    {
        return $this->columns;
    }

    /**
     * @return list<ColumnRegistryDefinition>
     */
    public function exportable(): array
    {
        return \array_values(\array_filter(
            $this->columns,
            static fn (ColumnRegistryDefinition $column): bool => $column->exportable()
        ));
    }

    /**
     * @return list<string>
     */
    public function exportableKeys(): array
    {
        return \array_values(\array_map(
            static fn (ColumnRegistryDefinition $column): string => $column->key(),
            $this->exportable()
        ));
    }

    public function primaryKey(): ColumnRegistryDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->isPrimaryKey()) {
                return $column;
            }
        }

        throw new \LogicException('Primary key column was not validated.');
    }

    /**
     * @return list<ColumnRegistryDefinition>
     */
    public function searchable(): array
    {
        return \array_values(\array_filter(
            $this->columns,
            static fn (ColumnRegistryDefinition $column): bool => $column->searchable()
        ));
    }

    /**
     * @return list<string>
     */
    public function sortableKeys(): array
    {
        return \array_values(\array_map(
            static fn (ColumnRegistryDefinition $column): string => $column->key(),
            \array_filter($this->columns, static fn (ColumnRegistryDefinition $column): bool => $column->sortable())
        ));
    }

    public function get(string $key): ?ColumnRegistryDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->key() === $key) {
                return $column;
            }
        }

        return null;
    }
}
