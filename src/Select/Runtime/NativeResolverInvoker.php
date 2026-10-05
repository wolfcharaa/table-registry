<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Runtime;

/**
 * @api
 */
final class NativeResolverInvoker implements ResolverInvokerInterface
{
    public function invoke(callable|string $resolver, array $arguments): mixed
    {
        if (\is_string($resolver) && \class_exists($resolver)) {
            $resolver = new $resolver();
        }

        if (!\is_callable($resolver)) {
            throw new \InvalidArgumentException('Select resolver must be callable.');
        }

        return $resolver(...\array_values($arguments));
    }
}
