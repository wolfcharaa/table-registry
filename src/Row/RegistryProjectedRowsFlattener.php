<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Row;

/**
 * @api
 */
final class RegistryProjectedRowsFlattener
{
    private const CELL_VALUE = 'value';
    private const CELL_LABEL = 'label';

    /**
     * @param list<array{primaryKey?:string,values?:array<string,mixed>}> $rows
     * @return list<array<string, mixed>>
     */
    public static function flatten(array $rows, bool $withPrimaryKey = false, string $cellKey = self::CELL_VALUE): array
    {
        return \array_map(
            static fn (array $row): array => self::flattenRow($row, $withPrimaryKey, $cellKey),
            $rows
        );
    }

    /**
     * @param list<array{primaryKey?:string,values?:array<string,mixed>}> $rows
     * @return list<array<string, mixed>>
     */
    public static function flattenLabels(array $rows, bool $withPrimaryKey = false): array
    {
        return self::flatten($rows, $withPrimaryKey, self::CELL_LABEL);
    }

    /**
     * @param array{primaryKey?:string,values?:array<string,mixed>} $row
     * @return array<string, mixed>
     */
    public static function flattenRow(array $row, bool $withPrimaryKey = false, string $cellKey = self::CELL_VALUE): array
    {
        $values = \is_array($row['values'] ?? null) ? $row['values'] : [];
        $flat = $withPrimaryKey ? ['primaryKey' => $row['primaryKey'] ?? ''] : [];

        foreach ($values as $key => $cell) {
            if (\is_array($cell)) {
                $flat[(string)$key] = \array_key_exists($cellKey, $cell)
                    ? $cell[$cellKey]
                    : ($cell[self::CELL_VALUE] ?? null);
            }
        }

        return $flat;
    }
}
