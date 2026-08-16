<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sql;

interface RegistrySqlRuntimeConstraintInterface
{
    public function __invoke(RegistrySqlRuntimeContext $context): ?RegistrySqlCondition;
}
