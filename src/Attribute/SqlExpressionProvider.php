<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class SqlExpressionProvider
{
    /**
     * @param class-string $provider
     */
    public function __construct(public string $provider)
    {
    }
}
