<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
enum JoinType: string
{
    case Inner = 'inner';
    case Left = 'left';
}
