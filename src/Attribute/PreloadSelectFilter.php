<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class PreloadSelectFilter
{
    public function __construct(
        public string $route,
        public ?string $queryKey = null,
        public string $valueFormat = 'string'
    ) {
    }
}
