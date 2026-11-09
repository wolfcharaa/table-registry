<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    /**
     * @param class-string<PrimitiveValueObjectInterface>|null $rowType
     */
    public function __construct(
        public ?string $column = null,
        public ?string $rowType = null,
        public ?bool $nullable = null
    ) {
    }
}
