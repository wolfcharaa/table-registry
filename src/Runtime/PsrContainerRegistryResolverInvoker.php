<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Runtime;

use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class PsrContainerRegistryResolverInvoker implements RegistryResolverInvokerInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly RegistryResolverInvokerInterface $fallback = new NativeRegistryResolverInvoker()
    ) {
    }

    public function invoke(callable|string $resolver, array $arguments): mixed
    {
        if (\is_string($resolver) && \class_exists($resolver) && $this->container->has($resolver)) {
            $resolver = $this->container->get($resolver);
        }

        return $this->fallback->invoke($resolver, $arguments);
    }
}
