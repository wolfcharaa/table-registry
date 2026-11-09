<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class StaticSelectFilter
{
    /**
     * @param list<array{value:string, label:string}> $options
     */
    public function __construct(
        public array $options = [],
        public ?string $queryKey = null,
        public string $valueFormat = 'string'
    ) {
    }
}
