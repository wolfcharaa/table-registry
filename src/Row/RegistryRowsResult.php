<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Row;

use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;

/**
 * @api
 */
final class RegistryRowsResult implements \JsonSerializable
{
    private const SCHEMA_VERSION = 2;

    /**
     * @var list<array{primaryKey:string,values:array<array-key,mixed>}>
     */
    private array $rows;
    private int $total;

    /**
     * @param array<array-key, mixed> $rows
     */
    public function __construct(
        private readonly TableRegistryDefinition $definition,
        private readonly RegistrySqlQueryInput $query,
        int|string $total,
        array $rows
    ) {
        $this->total = $this->normalizeTotal($total);
        $this->rows = $this->normalizeRows($rows);
    }

    public function registryKey(): string
    {
        return $this->definition->registryKey();
    }

    public function total(): int
    {
        return $this->total;
    }

    /**
     * @return list<array{primaryKey:string,values:array<array-key,mixed>}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return array{
     *     schemaVersion:int,
     *     registryKey:string,
     *     rows:list<array{primaryKey:string,values:array<array-key,mixed>}>,
     *     total:int,
     *     query:array{page:int,perPage:int,search:string,sort:array{column:string,direction:string}}
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'registryKey' => $this->definition->registryKey(),
            'rows' => $this->rows,
            'total' => $this->total,
            'query' => [
                'page' => $this->query->page(),
                'perPage' => $this->query->perPage(),
                'search' => $this->query->search(),
                'sort' => [
                    'column' => $this->query->sortColumn(),
                    'direction' => $this->query->sortDirection(),
                ],
            ],
        ];
    }

    private function normalizeTotal(int|string $total): int
    {
        if (\is_int($total)) {
            if ($total < 0) {
                throw new \InvalidArgumentException('Registry rows total must not be negative.');
            }

            return $total;
        }

        if (\preg_match('/^\d+$/', $total) !== 1) {
            throw new \InvalidArgumentException('Registry rows total must be a non-negative integer.');
        }

        return (int)$total;
    }

    /**
     * @param array<array-key, mixed> $rows
     * @return list<array{primaryKey:string,values:array<array-key,mixed>}>
     */
    private function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!\is_array($row)
                || !\array_key_exists('primaryKey', $row)
                || !\is_string($row['primaryKey'])
                || !\array_key_exists('values', $row)
                || !\is_array($row['values'])
            ) {
                throw new \InvalidArgumentException('Registry rows result expects projected rows.');
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
