<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ExcludeFromVariant
{
    /** @var non-empty-list<string> */
    public array $variants;

    public function __construct(string ...$variants)
    {
        if ($variants === []) {
            throw new \InvalidArgumentException('ExcludeFromVariant requires at least one variant.');
        }

        $this->variants = \array_values($variants);
    }
}
