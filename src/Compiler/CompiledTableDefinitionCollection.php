<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;

/**
 * @api
 * @implements \IteratorAggregate<string, TableRegistryDefinition>
 */
final class CompiledTableDefinitionCollection implements \Countable, \IteratorAggregate
{
    /** @var array<string, TableRegistryDefinition> */
    private array $definitions;

    /**
     * @param iterable<string, TableRegistryDefinition> $definitions
     */
    public function __construct(iterable $definitions = [])
    {
        $normalized = [];

        foreach ($definitions as $name => $definition) {
            if (\trim($name) === '') {
                throw new \InvalidArgumentException('Compiled definition name must be a non-empty string.');
            }

            if (isset($normalized[$name])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Duplicate compiled definition name "%s".',
                    $name
                ));
            }

            $normalized[$name] = $definition;
        }

        $this->definitions = $normalized;
    }

    /** @return array<string, TableRegistryDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $name): ?TableRegistryDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    public function require(string $name): TableRegistryDefinition
    {
        return $this->definitions[$name] ?? throw new \OutOfBoundsException(\sprintf(
            'Compiled table definition "%s" does not exist.',
            $name
        ));
    }

    public function count(): int
    {
        return \count($this->definitions);
    }

    /** @return \Traversable<string, TableRegistryDefinition> */
    public function getIterator(): \Traversable
    {
        yield from $this->definitions;
    }
}
