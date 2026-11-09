<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
final readonly class RawJoinCondition
{
    public function __construct(public string $sql)
    {
    }
}
