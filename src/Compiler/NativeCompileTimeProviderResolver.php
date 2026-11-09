<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

/**
 * @api
 */
final class NativeCompileTimeProviderResolver implements CompileTimeProviderResolverInterface
{
    public function resolve(string $provider): object
    {
        if (!\class_exists($provider)) {
            throw new \InvalidArgumentException(\sprintf(
                'Compile-time provider "%s" must be an existing class.',
                $provider
            ));
        }

        $reflection = new \ReflectionClass($provider);
        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException(\sprintf(
                'Compile-time provider "%s" must be instantiable.',
                $provider
            ));
        }

        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new \InvalidArgumentException(\sprintf(
                'Compile-time provider "%s" requires dependency resolution.',
                $provider
            ));
        }

        return $reflection->newInstance();
    }
}
