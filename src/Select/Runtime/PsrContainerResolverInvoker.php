<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Runtime;

use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class PsrContainerResolverInvoker implements ResolverInvokerInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ResolverInvokerInterface $fallback = new NativeResolverInvoker()
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
