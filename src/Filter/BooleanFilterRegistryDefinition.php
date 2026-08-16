<?php

declare(strict_types=1);

namespace TableRegistry\Filter;

final class BooleanFilterRegistryDefinition extends BaseRegistryFilterDefinition
{
    public static function exact(string $queryKey, string $columnKey, string $title): self
    {
        return new self($queryKey, $title, $columnKey, 'boolean', ['exact']);
    }

    public function normalize(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_bool($value)) {
            return $value;
        }

        return \filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
