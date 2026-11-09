<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class RawType
{
    /**
     * @param class-string<PrimitiveValueObjectInterface> $rowType
     */
    public function __construct(
        public string $rowType,
        public bool $nullable = false
    ) {
    }
}
