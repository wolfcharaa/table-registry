<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Runtime;

use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryInput;

/**
 * @api
 */
final class SelectRuntimeContext
{
    /** @param \Closure(mixed): string $paramFactory */
    public function __construct(
        private readonly SelectDefinition $definition,
        private readonly SelectQueryInput $query,
        private readonly \Closure $paramFactory
    ) {
    }

    public function definition(): SelectDefinition
    {
        return $this->definition;
    }

    public function query(): SelectQueryInput
    {
        return $this->query;
    }

    public function param(mixed $value): string
    {
        return ($this->paramFactory)($value);
    }
}
