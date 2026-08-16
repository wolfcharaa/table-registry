<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sql;

final class CompiledRegistrySql implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $params
    ) {
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->params;
    }

    public function sqlWithInlinedParams(): string
    {
        if ($this->params === []) {
            return $this->sql;
        }

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

    /**
     * @return array{sql:string,params:array<string,mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'sql' => $this->sql,
            'params' => $this->params,
        ];
    }
}
