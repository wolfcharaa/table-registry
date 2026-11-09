<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Runtime;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnValueLabelResolverInterface;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

/**
 * @internal
 */
final readonly class LabelFromRowValue implements ColumnValueLabelResolverInterface
{
    public function __construct(
        private string $rowKey,
        private bool $fallbackToValue
    ) {
    }

    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): string {
        $label = $row->get($this->rowKey);
        if ($label === null && $this->fallbackToValue) {
            $label = $value->getValue();
        }

        return self::stringify($label);
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \Stringable || \is_scalar($value)) {
            return (string)$value;
        }

        $encoded = \json_encode($value, JSON_UNESCAPED_UNICODE);

        return \is_string($encoded) ? $encoded : '';
    }
}
