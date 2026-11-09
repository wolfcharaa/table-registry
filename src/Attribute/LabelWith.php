<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

use Wolfcharaa\TableRegistry\Column\ColumnValueLabelResolverInterface;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class LabelWith
{
    /**
     * @param class-string<ColumnValueLabelResolverInterface> $resolver
     * @param list<string> $inputs
     */
    public function __construct(
        public string $resolver,
        public array $inputs = []
    ) {
    }
}
