<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Pagination
{
    public function __construct(
        public string $pageKey = 'page',
        public string $perPageKey = 'per_page',
        public int $defaultPerPage = 25,
        public int $maxPerPage = 250,
        public ?string $variant = null
    ) {
    }
}
