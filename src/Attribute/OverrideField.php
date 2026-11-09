<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class OverrideField
{
    /**
     * @param class-string<PrimitiveValueObjectInterface>|null $rowType
     */
    public function __construct(
        public string $field,
        public ?string $column = null,
        public ?string $rowType = null,
        public ?bool $nullable = null
    ) {
    }
}
