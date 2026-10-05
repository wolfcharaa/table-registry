<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Query;

/**
 * @api
 */
enum SelectSortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}
