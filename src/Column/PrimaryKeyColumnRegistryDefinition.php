<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Column;

final class PrimaryKeyColumnRegistryDefinition extends ColumnRegistryDefinition
{
    public function __construct(string $key, string $title = 'ID')
    {
        parent::__construct($key, $title);
    }

    public function isPrimaryKey(): bool
    {
        return true;
    }
}
