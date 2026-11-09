<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
final readonly class FieldMatch
{
    public function __construct(
        public string $left,
        public string $right
    ) {
    }
}
