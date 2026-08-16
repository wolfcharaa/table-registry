<?php

declare(strict_types=1);

namespace TableRegistry\Filter;

final class TextFilterRegistryDefinition extends BaseRegistryFilterDefinition
{
    public static function contains(string $queryKey, string $columnKey, string $title): self
    {
        return new self($queryKey, $title, $columnKey, 'string', ['contains']);
    }

    public static function exact(string $queryKey, string $columnKey, string $title): self
    {
        return new self($queryKey, $title, $columnKey, 'string', ['exact']);
    }
}
