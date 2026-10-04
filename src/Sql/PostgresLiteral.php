<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sql;

/**
 * @api
 */
final class PostgresLiteral
{
    private function __construct(private readonly mixed $value)
    {
    }

    public static function value(mixed $value): self
    {
        return new self($value);
    }

    public function quoted(): string
    {
        if ($this->value === null) {
            return 'NULL';
        }

        if (\is_bool($this->value)) {
            return $this->value ? 'TRUE' : 'FALSE';
        }

        if (\is_int($this->value)) {
            return (string)$this->value;
        }

        if (\is_float($this->value)) {
            if (!\is_finite($this->value)) {
                throw new \InvalidArgumentException('Non-finite float cannot be converted to PostgreSQL literal.');
            }

            return (string)$this->value;
        }

        if ($this->value instanceof \DateTimeInterface) {
            return self::quoteString($this->value->format('Y-m-d H:i:sP'));
        }

        if (\is_scalar($this->value) || $this->value instanceof \Stringable) {
            return self::quoteString((string)$this->value);
        }

        throw new \InvalidArgumentException(\sprintf(
            'Value of type "%s" cannot be converted to PostgreSQL literal.',
            \get_debug_type($this->value)
        ));
    }

    private static function quoteString(string $value): string
    {
        return "'" . \str_replace("'", "''", $value) . "'";
    }
}
