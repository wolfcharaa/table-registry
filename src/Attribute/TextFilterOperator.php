<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
enum TextFilterOperator: string
{
    case Exact = 'exact';
    case Contains = 'contains';
}
