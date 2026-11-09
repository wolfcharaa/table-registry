<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class SqlConstraint
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(
        public string $sql,
        public array $params = [],
        public ?string $variant = null
    ) {
    }
}
