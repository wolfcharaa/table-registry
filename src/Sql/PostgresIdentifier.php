<?php

declare(strict_types=1);

namespace TableRegistry\Sql;

final class PostgresIdentifier implements \Stringable
{
    private function __construct(private readonly string $value)
    {
        if (\trim($value) === '') {
            throw new \InvalidArgumentException('Postgres identifier must not be empty.');
        }
    }

    public static function value(string $value): self
    {
        return new self($value);
    }

    public function quoted(): string
    {
        return '"' . \str_replace('"', '""', $this->value) . '"';
    }

    public function __toString(): string
    {
        return $this->quoted();
    }
}
