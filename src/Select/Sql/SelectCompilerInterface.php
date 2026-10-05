<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Sql;

use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryInput;

/**
 * @api
 */
interface SelectCompilerInterface
{
    public function compile(SelectDefinition $definition, ?SelectQueryInput $query = null): CompiledSelect;

    public function compileReadPlan(
        SelectDefinition $definition,
        ?SelectQueryInput $query = null
    ): CompiledSelectReadPlan;
}
