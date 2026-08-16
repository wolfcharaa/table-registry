<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Row;

final class RegistryRowData
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(private readonly array $row)
    {
    }

    public function get(string $key): mixed
    {
        return $this->row[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->row;
    }
}
