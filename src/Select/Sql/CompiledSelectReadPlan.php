<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Sql;

/**
 * @api
 */
final class CompiledSelectReadPlan
{
    public function __construct(
        private readonly CompiledSelect $rows,
        private readonly ?CompiledSelect $count = null
    ) {
    }

    public function rows(): CompiledSelect
    {
        return $this->rows;
    }

    public function count(): ?CompiledSelect
    {
        return $this->count;
    }
}
