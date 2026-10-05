<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Runtime;

/**
 * @api
 */
interface RegistryResolverInvokerInterface extends \Wolfcharaa\TableRegistry\Select\Runtime\ResolverInvokerInterface
{
    /**
     * @param callable|class-string $resolver
     * @param array<string, mixed> $arguments
     */
    public function invoke(callable|string $resolver, array $arguments): mixed;
}
