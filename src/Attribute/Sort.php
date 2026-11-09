<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Sort
{
    public function __construct(public ?string $expression = null)
    {
    }
}
