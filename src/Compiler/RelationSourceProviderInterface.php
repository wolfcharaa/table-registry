<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

use Wolfcharaa\TableRegistry\Metadata\RelationSourceMetadata;

/**
 * @api
 */
interface RelationSourceProviderInterface
{
    public function __invoke(): RelationSourceMetadata;
}
