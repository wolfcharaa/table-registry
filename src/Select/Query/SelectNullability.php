<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Query;

/**
 * @api
 */
enum SelectNullability: string
{
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';
}
