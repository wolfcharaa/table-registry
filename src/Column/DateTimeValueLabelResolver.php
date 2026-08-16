<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Column;

use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

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
