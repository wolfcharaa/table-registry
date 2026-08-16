<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Value;

final class DateTimeValue implements PrimitiveValueObjectInterface
{
    private function __construct(private readonly ?string $value)
    {
    }

    public static function value(mixed $value): static
    {
        if ($value instanceof \DateTimeInterface) {
            return new self($value->format('Y-m-d H:i:s'));
        }

        if ($value === null || (\is_string($value) && \trim($value) === '')) {
            return new self(null);
        }

        return new self((string)$value);
    }

    public function formatLabel(string $dateTimeFormat = 'd.m.Y H:i', string $dateFormat = 'd.m.Y'): string
    {
        if ($this->value === null) {
            return '';
        }

        $dateTime = $this->dateTime();
        if ($dateTime === null) {
            return $this->value;
        }

        return $dateTime->format($this->isDateOnly() ? $dateFormat : $dateTimeFormat);
    }

    public function dateTime(): ?\DateTimeImmutable
    {
        if ($this->value === null) {
            return null;
        }

        $value = \trim($this->value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value ?? '';
    }

    private function isDateOnly(): bool
    {
        return $this->value !== null && \preg_match('/^\d{4}-\d{2}-\d{2}$/', \trim($this->value)) === 1;
    }
}
