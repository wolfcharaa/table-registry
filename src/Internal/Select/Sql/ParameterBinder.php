<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Select\Sql;

use Wolfcharaa\TableRegistry\Exception\SelectCompilationException;

/**
 * @internal
 */
final class ParameterBinder
{
    /** @var array<string, string|int|float|bool|null> */
    private array $params = [];

    public function bind(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value) && !\is_bool($value) && $value !== null) {
            throw SelectCompilationException::because(
                'Select SQL parameters must be scalar values or null.'
            );
        }

        $key = 'p' . \count($this->params);
        $this->params[$key] = $value;

        return ':' . $key;
    }

    /** @return array<string, string|int|float|bool|null> */
    public function params(): array
    {
        return $this->params;
    }
}
