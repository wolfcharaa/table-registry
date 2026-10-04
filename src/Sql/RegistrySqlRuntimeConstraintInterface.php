<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sql;

/**
 * @api
 */
interface RegistrySqlRuntimeConstraintInterface
{
    public function __invoke(RegistrySqlRuntimeContext $context): ?RegistrySqlCondition;
}
