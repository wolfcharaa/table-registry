<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Search
{
    public function __construct(
        public string $queryKey = 'search',
        public bool $enabled = true
    ) {
    }
}
