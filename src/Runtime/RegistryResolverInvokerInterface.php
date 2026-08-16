<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Runtime;

interface RegistryResolverInvokerInterface
{
    /**
     * @param callable|class-string $resolver
     * @param array<string, mixed> $arguments
     */
    public function invoke(callable|string $resolver, array $arguments): mixed;
}
