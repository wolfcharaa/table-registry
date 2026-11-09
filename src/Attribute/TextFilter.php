<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class TextFilter
{
    public function __construct(
        public ?string $queryKey = null,
        public TextFilterOperator $operator = TextFilterOperator::Contains
    ) {
    }
}
