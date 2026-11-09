<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

/**
 * @api
 */
interface CompileTimeProviderResolverInterface
{
    /**
     * @param class-string $provider
     */
    public function resolve(string $provider): object;
}
