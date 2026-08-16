<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sql;

final class RegistrySqlCondition
{
    /**
     * @param array<string, mixed> $params
     */
    private function __construct(
        private readonly string $sql,
        private readonly array $params = []
    ) {
        if (\trim($sql) === '') {
            throw new \InvalidArgumentException('Registry SQL condition must not be empty.');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function raw(string $sql, array $params = []): self
    {
        return new self($sql, $params);
    }

    public static function impossible(): self
    {
        return new self('1 = 0');
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
}
