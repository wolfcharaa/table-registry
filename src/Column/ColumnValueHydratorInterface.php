<?php

declare(strict_types=1);

namespace TableRegistry\Column;

use TableRegistry\Row\RegistryRowData;
use TableRegistry\Value\PrimitiveValueObjectInterface;

interface ColumnValueHydratorInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): mixed;
}
