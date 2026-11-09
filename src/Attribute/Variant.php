<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Variant
{
    public function __construct(
        public string $name,
        public string $key,
        public string $title,
        public bool $isDefault = false
    ) {
    }
}
