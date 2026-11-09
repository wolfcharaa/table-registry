<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
enum ColumnMode: string
{
    case Visible = 'visible';
    case Payload = 'payload';
    case Output = 'output';
}
