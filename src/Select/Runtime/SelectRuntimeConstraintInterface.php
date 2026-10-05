<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Runtime;

use Wolfcharaa\TableRegistry\Select\Sql\SelectCondition;

/**
 * @api
 */
interface SelectRuntimeConstraintInterface
{
    public function __invoke(SelectRuntimeContext $context): ?SelectCondition;
}
