<?php

declare(strict_types=1);

namespace TableRegistry\Value;

final class StringValue implements PrimitiveValueObjectInterface
{
    private function __construct(private readonly string $value)
    {
    }

    public static function value(mixed $value): static
    {
        if ($value === null) {
            return new self('');
        }

        if (\is_scalar($value)) {
            return new self((string)$value);
        }

        $encoded = \json_encode($value, JSON_UNESCAPED_UNICODE);

        return new self(\is_string($encoded) ? $encoded : '');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
