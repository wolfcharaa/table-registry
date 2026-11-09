<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

/**
 * @api
 */
interface SqlExpressionProviderInterface
{
    public function __invoke(): string;
}
