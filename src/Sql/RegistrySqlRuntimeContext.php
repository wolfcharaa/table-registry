<?php

declare(strict_types=1);

namespace TableRegistry\Sql;

use TableRegistry\Definition\TableRegistryDefinition;

final class RegistrySqlRuntimeContext
{
    /**
     * @param \Closure(mixed): string $paramFactory
     */
    public function __construct(
        private readonly TableRegistryDefinition $definition,
        private readonly RegistrySqlQueryInput $query,
        private readonly \Closure $paramFactory
    ) {
    }

    public function definition(): TableRegistryDefinition
    {
        return $this->definition;
    }

    public function query(): RegistrySqlQueryInput
    {
        return $this->query;
    }

    public function param(mixed $value): string
    {
        return ($this->paramFactory)($value);
    }
}
