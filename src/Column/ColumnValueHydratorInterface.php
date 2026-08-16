<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Column;

use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

interface ColumnValueHydratorInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): mixed;
}
