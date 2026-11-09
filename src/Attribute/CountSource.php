<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class CountSource
{
    public function __construct(
        public string $source,
        public ?string $variant = null
    ) {
    }
}
