<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Export;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;

/**
 * @api
 */
final class RegistryExportColumnResolver
{
    /**
     * @param list<string> $requestedColumnKeys
     * @return list<ColumnRegistryDefinition>
     */
    public function resolve(
        TableRegistryDefinition $definition,
        array $requestedColumnKeys = []
    ): array {
        $allowed = $definition->columns()->exportable();
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
}
