<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class TimeRangeFilter
{
    public function __construct(
        public ?string $queryKey = null,
        public ?string $fromKey = null,
        public ?string $toKey = null
    ) {
    }
}
