<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Column;

use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;

/**
 * @internal
 */
final class ColumnRegistryDefinitionValidator
{
    /**
     * @param array<array-key, mixed> $columns
     * @phpstan-assert non-empty-array<array-key, ColumnRegistryDefinition> $columns
     */
    public static function assertValid(array $columns): void
    {
        if ($columns === []) {
            throw InvalidRegistryDefinitionException::because('Registry table must contain at least one column.');
        }

        $keys = [];
        $primaryKeys = 0;

        foreach ($columns as $column) {
            if (!$column instanceof ColumnRegistryDefinition) {
                throw InvalidRegistryDefinitionException::because('Registry columns must be ColumnRegistryDefinition instances.');
            }

            if (isset($keys[$column->key()])) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Duplicate registry column key "%s".',
                    $column->key()
                ));
            }

            $keys[$column->key()] = true;

            if ($column->isPrimaryKey()) {
                if ($column->payloadOnly()) {
                    throw InvalidRegistryDefinitionException::because('Registry primary key column cannot be payload-only.');
                }

                if ($column->outputOnly()) {
                    throw InvalidRegistryDefinitionException::because('Registry primary key column cannot be output-only.');
                }

                ++$primaryKeys;
            }
        }

        if ($primaryKeys !== 1) {
            throw InvalidRegistryDefinitionException::because('Registry table must contain exactly one primary key column.');
        }
    }
}
