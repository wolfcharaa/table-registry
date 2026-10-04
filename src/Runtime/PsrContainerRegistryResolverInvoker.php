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
            $resolved = $this->container->get($resolver);
            if (!\is_callable($resolved)) {
                throw new \UnexpectedValueException(\sprintf(
                    'Container entry "%s" must be callable.',
                    $resolver
                ));
            }

            $resolver = $resolved;
        }

        return $this->fallback->invoke($resolver, $arguments);
    }
}
