<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeConstraintInterface;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class RuntimeConstraint
{
    /**
     * @param class-string<RegistrySqlRuntimeConstraintInterface> $resolver
     */
    public function __construct(
        public string $resolver,
        public ?string $variant = null
    ) {
    }
}
