<?php

declare(strict_types=1);

namespace TableRegistry\Value;

final class JsonListValue implements PrimitiveValueObjectInterface
{
    /**
     * @param list<string> $value
     */
    private function __construct(private readonly array $value)
    {
    }

    public static function value(mixed $value): static
    {
        if ($value === null || $value === '') {
            return new self([]);
        }

        if (\is_string($value)) {
            $decoded = \json_decode($value, true);

            if (\is_array($decoded)) {
                return new self(self::normalizeList($decoded));
            }

            return new self([$value]);
        }

        if (\is_array($value)) {
            return new self(self::normalizeList($value));
        }

        if (\is_scalar($value)) {
            return new self([(string)$value]);
        }

        return new self([]);
    }

    /**
     * @return list<string>
     */
    public function getValue(): array
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return \implode(', ', $this->value);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    private static function normalizeList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (\is_scalar($value)) {
                $item = \trim((string)$value);
                if ($item !== '') {
                    $normalized[] = $item;
                }
            }
        }

        return $normalized;
    }
}
