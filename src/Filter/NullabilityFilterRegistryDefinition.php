<?php

declare(strict_types=1);

namespace TableRegistry\Filter;

final class NullabilityFilterRegistryDefinition extends BaseRegistryFilterDefinition
{
    public static function forColumn(string $queryKey, string $columnKey, string $title): self
    {
        return new self($queryKey, $title, $columnKey, 'nullability', ['is_null', 'is_not_null']);
    }
}
