<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class OnlyInVariant
{
    /** @var non-empty-list<string> */
    public array $variants;

    public function __construct(string ...$variants)
    {
        if ($variants === []) {
            throw new \InvalidArgumentException('OnlyInVariant requires at least one variant.');
        }

        $this->variants = \array_values($variants);
    }
}
