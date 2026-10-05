<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Sql;

use Wolfcharaa\TableRegistry\Exception\SelectCompilationException;
use Wolfcharaa\TableRegistry\Sql\PostgresLiteral;

/**
 * @api
 */
final class CompiledSelect implements \JsonSerializable
{
    /** @param array<string, string|int|float|bool|null> $params */
    public function __construct(private readonly string $sql, private readonly array $params)
    {
        if (\trim($sql) === '') {
            throw SelectCompilationException::because('Compiled select SQL must not be empty.');
        }
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /** @return array<string, string|int|float|bool|null> */
    public function params(): array
    {
        return $this->params;
    }

    public function sqlWithInlinedParams(): string
    {
        $replacements = [];
        foreach ($this->params as $key => $value) {
            $replacements[':' . $key] = PostgresLiteral::value($value)->quoted();
        }

        \uksort(
            $replacements,
            static fn (string $left, string $right): int => \strlen($right) <=> \strlen($left)
        );

        return \strtr($this->sql, $replacements);
    }

    /** @return array{sql:string,params:array<string,string|int|float|bool|null>} */
    public function jsonSerialize(): array
    {
        return ['sql' => $this->sql, 'params' => $this->params];
    }
}
