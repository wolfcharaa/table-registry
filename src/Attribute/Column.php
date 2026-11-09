<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public string $title,
        public int $position,
        public ?string $key = null,
        public ColumnMode $mode = ColumnMode::Visible,
        public ?bool $exportable = null,
        public ?string $hint = null
    ) {
    }
}
