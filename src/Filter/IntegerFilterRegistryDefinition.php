<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Filter;

/**
 * @api
 */
final class IntegerFilterRegistryDefinition extends BaseRegistryFilterDefinition
{
    public static function exact(string $queryKey, string $columnKey, string $title): self
    {
        return new self($queryKey, $title, $columnKey, 'integer', ['exact']);
    }

    public function normalize(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \is_numeric($value) ? (int)$value : null;
    }
}
