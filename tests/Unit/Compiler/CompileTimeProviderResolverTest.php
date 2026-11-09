<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Compiler;

use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Compiler\NativeCompileTimeProviderResolver;
use Wolfcharaa\TableRegistry\Compiler\PsrContainerCompileTimeProviderResolver;

#[Test]
final class CompileTimeProviderResolverTest
{
    public function nativeResolverConstructsZeroArgumentProvider(): void
    {
        $provider = (new NativeCompileTimeProviderResolver())->resolve(ZeroArgumentProvider::class);

        Assert::same($provider::class, ZeroArgumentProvider::class);
    }

    public function nativeResolverRejectsProviderThatNeedsDependencies(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        (new NativeCompileTimeProviderResolver())->resolve(DependencyProvider::class);
    }

    public function psrResolverUsesContainerAndFallsBackToNative(): void
    {
        $configured = new ConfiguredProvider('container');
        $resolver = new PsrContainerCompileTimeProviderResolver(new ProviderContainer([
            ConfiguredProvider::class => $configured,
        ]));

        Assert::same($resolver->resolve(ConfiguredProvider::class), $configured);
        Assert::same($resolver->resolve(ZeroArgumentProvider::class)::class, ZeroArgumentProvider::class);
    }
}

final class ZeroArgumentProvider
{
}

final class DependencyProvider
{
    public function __construct(public readonly string $value)
    {
    }
}

final class ConfiguredProvider
{
    public function __construct(public readonly string $value)
    {
    }
}

final class ProviderContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $entries
     */
    public function __construct(private readonly array $entries)
    {
    }

    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }
}
