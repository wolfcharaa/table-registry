<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier;

/**
 * @api
 */
final class SelectJoinDefinition
{
    private function __construct(
        private readonly string $type,
        private readonly ?string $relation,
        private readonly ?string $alias,
        private readonly ?string $on,
        private readonly ?string $rawSql
    ) {
    }

    public static function inner(string $relation, string $on, ?string $alias = null): self
    {
        return self::structured('inner', $relation, $on, $alias);
    }

    public static function left(string $relation, string $on, ?string $alias = null): self
    {
        return self::structured('left', $relation, $on, $alias);
    }

    public static function rawSql(string $sql): self
    {
        $sql = \trim($sql);
        if ($sql === '') {
            throw InvalidSelectDefinitionException::because('Select raw JOIN SQL must not be empty.');
        }

        return new self('raw', null, null, null, $sql);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function relation(): ?string
    {
        return $this->relation;
    }

    public function alias(): ?string
    {
        return $this->alias;
    }

    public function on(): ?string
    {
        return $this->on;
    }

    public function raw(): ?string
    {
        return $this->rawSql;
    }

    private static function structured(string $type, string $relation, string $on, ?string $alias): self
    {
        Identifier::assertQualified($relation, 'Select JOIN relation');
        if ($alias !== null) {
            Identifier::assertAlias($alias, 'Select JOIN alias');
        }

        $on = \trim($on);
        if ($on === '') {
            throw InvalidSelectDefinitionException::because('Select JOIN ON expression must not be empty.');
        }

        return new self($type, $relation, $alias, $on, null);
    }
}
