<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(
        public string $key,
        public string $title
    ) {
    }
}
