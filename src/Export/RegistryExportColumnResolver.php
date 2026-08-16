<?php

declare(strict_types=1);

namespace TableRegistry\Export;

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;

final class RegistryExportColumnResolver
{
    /**
     * @param list<string> $allowedColumnKeys
     * @param list<string> $requestedColumnKeys
     * @return list<ColumnRegistryDefinition>
     */
    public function resolve(
        TableRegistryDefinition $definition,
        array $allowedColumnKeys,
        array $requestedColumnKeys = []
    ): array {
        $allowed = $this->existingColumns($definition, $allowedColumnKeys);
        if ($allowed === []) {
            return [];
        }

        $allowedByKey = [];
        foreach ($allowed as $column) {
            $allowedByKey[$column->key()] = $column;
        }

        $requested = [];
        foreach ($requestedColumnKeys as $columnKey) {
            if (isset($allowedByKey[$columnKey]) && !isset($requested[$columnKey])) {
                $requested[$columnKey] = $allowedByKey[$columnKey];
            }
        }

        return $requested !== [] ? \array_values($requested) : $allowed;
    }

    /**
     * @param list<string> $columnKeys
     * @return list<ColumnRegistryDefinition>
     */
    private function existingColumns(TableRegistryDefinition $definition, array $columnKeys): array
    {
        $columns = $definition->columns();
        $resolved = [];

        foreach ($columnKeys as $columnKey) {
            $column = $columns->get($columnKey);
            if ($column !== null) {
                $resolved[] = $column;
            }
        }

        return $resolved;
    }
}
