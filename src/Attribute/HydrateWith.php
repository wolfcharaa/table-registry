<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

use Wolfcharaa\TableRegistry\Column\ColumnValueHydratorInterface;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class HydrateWith
{
    /**
     * @param class-string<ColumnValueHydratorInterface> $resolver
     * @param list<string> $inputs
     */
    public function __construct(
        public string $resolver,
        public array $inputs = [],
        public bool $unchecked = false
    ) {
    }
}
