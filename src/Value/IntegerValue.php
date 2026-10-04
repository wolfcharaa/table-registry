<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Value;

/**
 * @api
 */
final class IntegerValue implements PrimitiveValueObjectInterface
{
    private function __construct(private readonly ?int $value)
    {
    }

    public static function value(mixed $value): static
    {
        if ($value === null || $value === '') {
            return new self(null);
        }

        return new self((int)$value);
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value !== null ? (string)$this->value : '';
    }
}
