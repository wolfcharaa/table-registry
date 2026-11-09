<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class PsrContainerCompileTimeProviderResolver implements CompileTimeProviderResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly CompileTimeProviderResolverInterface $fallback = new NativeCompileTimeProviderResolver()
    ) {
    }

    public function resolve(string $provider): object
    {
        if (!$this->container->has($provider)) {
            return $this->fallback->resolve($provider);
        }

        $resolved = $this->container->get($provider);
        if (!\is_object($resolved)) {
            throw new \UnexpectedValueException(\sprintf(
                'Compile-time provider container entry "%s" must be an object.',
                $provider
            ));
        }

        return $resolved;
    }
}
