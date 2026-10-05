<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Sql;

use Wolfcharaa\TableRegistry\Exception\SelectCompilationException;

/**
 * @api
 */
final class SelectCondition
{
    private function __construct(private readonly string $sql)
    {
        if (\trim($sql) === '') {
            throw SelectCompilationException::because('Select SQL condition must not be empty.');
        }
    }

    public static function sql(string $sql): self
    {
        return new self($sql);
    }

    /** @param array<array-key, mixed> $conditions */
    public static function allOf(array $conditions): self
    {
        return self::compose($conditions, 'AND');
    }

    /** @param array<array-key, mixed> $conditions */
    public static function anyOf(array $conditions): self
    {
        return self::compose($conditions, 'OR');
    }

    public static function alwaysFalse(): self
    {
        return new self('1 = 0');
    }

    public function toSql(): string
    {
        return $this->sql;
    }

    /** @param array<array-key, mixed> $conditions */
    private static function compose(array $conditions, string $operator): self
    {
        if ($conditions === []) {
            throw SelectCompilationException::because('Select condition composition must not be empty.');
        }

        $parts = [];
        foreach ($conditions as $condition) {
            if (!$condition instanceof self) {
                throw SelectCompilationException::because(\sprintf(
                    'Select conditions must be instances of %s.',
                    self::class
                ));
            }

            $parts[] = '(' . $condition->toSql() . ')';
        }

        return new self(\implode(' ' . $operator . ' ', $parts));
    }
}
