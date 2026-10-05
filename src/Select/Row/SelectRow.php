<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Row;

/**
 * @api
 */
final class SelectRow implements \JsonSerializable
{
    /** @param array<string, mixed> $values */
    private function __construct(private readonly array $values)
    {
    }

    /** @param array<string, mixed> $values */
    public static function from(array $values): self
    {
        return new self($values);
    }

    public function has(string $alias): bool
    {
        return \array_key_exists($alias, $this->values);
    }

    public function get(string $alias): mixed
    {
        return $this->values[$alias] ?? null;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
