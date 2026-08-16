<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Runtime;

final class NativeRegistryResolverInvoker implements RegistryResolverInvokerInterface
{
    public function invoke(callable|string $resolver, array $arguments): mixed
    {
        if (\is_string($resolver) && \class_exists($resolver)) {
            $resolver = new $resolver();
        }

        if (!\is_callable($resolver)) {
            throw new \InvalidArgumentException('Registry resolver must be callable.');
        }

        return $resolver(...\array_values($arguments));
    }
}
