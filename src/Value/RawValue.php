<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Value;

final class RawValue implements PrimitiveValueObjectInterface
{
    private function __construct(private readonly mixed $value)
    {
    }

    public static function value(mixed $value): static
    {
        return new self($value);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        if ($this->value === null) {
            return '';
        }

        if (\is_scalar($this->value)) {
            return (string)$this->value;
        }

        $encoded = \json_encode($this->value, JSON_UNESCAPED_UNICODE);

        return \is_string($encoded) ? $encoded : '';
    }
}
