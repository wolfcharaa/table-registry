<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Runtime;

use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeConstraintInterface;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;

/**
 * @internal
 */
final readonly class StaticRegistrySqlConstraint implements RegistrySqlRuntimeConstraintInterface
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(
        private string $sql,
        private array $params
    ) {
        if (\trim($sql) === '') {
            throw new \InvalidArgumentException('Static SQL constraint must not be empty.');
        }

        foreach ($params as $name => $_value) {
            if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
                throw new \InvalidArgumentException(\sprintf(
                    'Static SQL constraint parameter "%s" is not a valid name.',
                    $name
                ));
            }
        }
    }

    public function __invoke(RegistrySqlRuntimeContext $context): RegistrySqlCondition
    {
        $sql = \preg_replace_callback(
            '/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/',
            function (array $match) use ($context): string {
                $name = $match[1];
                if (!\array_key_exists($name, $this->params)) {
                    throw new \InvalidArgumentException(\sprintf(
                        'Static SQL constraint does not declare parameter "%s".',
                        $name
                    ));
                }

                return ':' . $context->param($this->params[$name]);
            },
            $this->sql
        );

        if (!\is_string($sql)) {
            throw new \LogicException('Static SQL constraint parameter rewriting failed.');
        }

        return RegistrySqlCondition::raw($sql);
    }
}
