<?php

declare(strict_types=1);

namespace TableRegistry\Row;

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Runtime\NativeRegistryResolverInvoker;
use TableRegistry\Runtime\RegistryResolverInvokerInterface;

final class RegistryRowsProjector
{
    public function __construct(
        private readonly RegistryResolverInvokerInterface $resolverInvoker = new NativeRegistryResolverInvoker()
    ) {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{primaryKey:string,values:array<string,array{value:mixed,label:string}>}>
     */
    public function project(TableRegistryDefinition $definition, array $rows): array
    {
        $columns = $definition->columns();
        $primaryKey = $columns->primaryKey()->key();
        $projectedRows = [];

        foreach ($rows as $row) {
            $rowData = new RegistryRowData($row);
            $projectedRows[] = [
                'primaryKey' => $this->stringify($row[$primaryKey] ?? null),
                'values' => $this->projectValues($columns->all(), $rowData),
            ];
        }

        return $projectedRows;
    }

    /**
     * @param list<ColumnRegistryDefinition> $columns
     * @return array<string, array{value:mixed,label:string}>
     */
    private function projectValues(array $columns, RegistryRowData $row): array
    {
        $values = [];

        foreach ($columns as $column) {
            $rawValue = $row->get($column->key());
            $valueObject = $column->rawValueObject($rawValue);
            $value = $column->value($valueObject, $row, $this->resolverInvoker);
            $label = $column->label($valueObject, $row, $value, $this->resolverInvoker);

            $values[$column->key()] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $values;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        if (\is_scalar($value)) {
            return (string)$value;
        }

        $encoded = \json_encode($value, JSON_UNESCAPED_UNICODE);

        return \is_string($encoded) ? $encoded : '';
    }
}
