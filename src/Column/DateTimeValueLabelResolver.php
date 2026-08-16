<?php

declare(strict_types=1);

namespace TableRegistry\Column;

use TableRegistry\Row\RegistryRowData;
use TableRegistry\Value\DateTimeValue;
use TableRegistry\Value\PrimitiveValueObjectInterface;

final class DateTimeValueLabelResolver implements ColumnValueLabelResolverInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): string {
        if (!$value instanceof DateTimeValue) {
            return (string)$value;
        }

        return $value->formatLabel();
    }
}
