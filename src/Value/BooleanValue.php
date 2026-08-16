<?php

declare(strict_types=1);

namespace TableRegistry\Value;

final class BooleanValue implements PrimitiveValueObjectInterface
{
    private function __construct(private readonly ?bool $value)
    {
    }

    public static function value(mixed $value): static
    {
        if ($value === null || $value === '') {
            return new self(null);
        }

        return new self(\filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
    }

    public function getValue(): ?bool
    {
        return $this->value;
    }

    public function __toString(): string
    {
        if ($this->value === null) {
            return '';
        }

        return $this->value ? '1' : '0';
    }
}
