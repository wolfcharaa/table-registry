<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class From
{
    /**
     * @param class-string $model
     */
    public function __construct(
        public string $model,
        public string $alias
    ) {
    }
}
