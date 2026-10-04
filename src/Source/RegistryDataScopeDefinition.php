<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Source;

/**
 * @api
 */
final class RegistryDataScopeDefinition
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $name,
        private readonly array $options = []
    ) {
        if (\trim($name) === '') {
            throw new \InvalidArgumentException('Data scope name must not be empty.');
        }
    }

    public static function named(string $name, mixed ...$options): self
    {
        return new self($name, $options);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }
}
