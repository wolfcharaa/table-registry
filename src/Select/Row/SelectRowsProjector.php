<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Row;

use Wolfcharaa\TableRegistry\Exception\SelectProjectionException;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;

/**
 * @api
 */
final class SelectRowsProjector
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<SelectRow>
     */
    public function project(SelectDefinition $definition, array $rows): array
    {
        $projected = [];
        foreach ($rows as $rowIndex => $row) {
            $values = [];
            foreach ($definition->fields() as $field) {
                $alias = $field->alias();
                if (!\array_key_exists($alias, $row)) {
                    throw SelectProjectionException::missingAlias($alias, $rowIndex);
                }

                $values[$alias] = $row[$alias];
            }

            $projected[] = SelectRow::from($values);
        }

        return $projected;
    }
}
