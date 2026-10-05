<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

/**
 * @api
 */
enum SelectValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime';
}
