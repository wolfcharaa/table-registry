<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Source;

/**
 * @api
 */
enum RegistrySourceKind: string
{
    case Sql = 'sql';
    case External = 'external';
}
