<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Row;

use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;

/**
 * @api
 */
final class RegistryRowInputDefinition
{
    public function __construct(
        private readonly string $key,
        private readonly ?ColumnSelectDefinition $select = null
    ) {
        if (\trim($key) === '') {
            throw new \InvalidArgumentException('Registry row input key must not be empty.');
        }

        if ($select !== null && $select->alias() !== $key) {
            throw new \InvalidArgumentException('Registry row input key must match its select alias.');
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    public function select(): ?ColumnSelectDefinition
    {
        return $this->select;
    }
}
