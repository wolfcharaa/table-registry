<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Row;

/**
 * @api
 */
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

    /**
     * @param list<string> $keys
     */
    public function only(array $keys): self
    {
        $selected = [];

        foreach ($keys as $key) {
            if (\trim($key) === '') {
                throw new \InvalidArgumentException('Registry row data key must not be empty.');
            }

            if (\array_key_exists($key, $this->row)) {
                $selected[$key] = $this->row[$key];
            }
        }

        return new self($selected);
    }
}
