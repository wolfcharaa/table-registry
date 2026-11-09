<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class DefaultSort
{
    public function __construct(
        public string $column,
        public string $direction = 'asc',
        public ?string $variant = null
    ) {
    }
}
