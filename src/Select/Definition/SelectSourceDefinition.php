<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier;

/**
 * @api
 */
final class SelectSourceDefinition
{
    /** @var list<SelectJoinDefinition> */
    private array $joins = [];
    private string $connection = 'default';

    private function __construct(
        private readonly ?string $relation,
        private readonly ?string $alias,
        private readonly ?string $rawSql
    ) {
    }

    public static function relation(string $relation, ?string $alias = null): self
    {
        Identifier::assertQualified($relation, 'Select source relation');
        if ($alias !== null) {
            Identifier::assertAlias($alias, 'Select source alias');
        }

        return new self($relation, $alias, null);
    }

    public static function fromSql(string $sql): self
    {
        $sql = \trim($sql);
        if ($sql === '') {
            throw InvalidSelectDefinitionException::because('Select source SQL must not be empty.');
        }

        return new self(null, null, $sql);
    }

    public function withJoin(SelectJoinDefinition $join): self
    {
        $effectiveAlias = $this->joinAlias($join);
        if ($effectiveAlias !== null) {
            $aliases = [];
            if ($this->relation !== null) {
                $aliases[] = $this->alias ?? Identifier::lastSegment($this->relation);
            }

            foreach ($this->joins as $existing) {
                $existingAlias = $this->joinAlias($existing);
                if ($existingAlias !== null) {
                    $aliases[] = $existingAlias;
                }
            }

            foreach ($aliases as $alias) {
                if (\strcasecmp($alias, $effectiveAlias) === 0) {
                    throw InvalidSelectDefinitionException::because(\sprintf(
                        'Duplicate select source alias "%s".',
                        $effectiveAlias
                    ));
                }
            }
        }

        $clone = clone $this;
        $clone->joins[] = $join;

        return $clone;
    }

    public function withInnerJoin(string $relation, string $on, ?string $alias = null): self
    {
        return $this->withJoin(SelectJoinDefinition::inner($relation, $on, $alias));
    }

    public function withLeftJoin(string $relation, string $on, ?string $alias = null): self
    {
        return $this->withJoin(SelectJoinDefinition::left($relation, $on, $alias));
    }

    public function withRawJoin(string $sql): self
    {
        return $this->withJoin(SelectJoinDefinition::rawSql($sql));
    }

    public function withConnection(string $connection): self
    {
        $connection = \trim($connection);
        if ($connection === '') {
            throw InvalidSelectDefinitionException::because('Select source connection must not be empty.');
        }

        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }

    public function isRelation(): bool
    {
        return $this->relation !== null;
    }

    public function relationName(): ?string
    {
        return $this->relation;
    }

    public function alias(): ?string
    {
        return $this->alias;
    }

    public function sql(): string
    {
        return $this->relation ?? (string)$this->rawSql;
    }

    /** @return list<SelectJoinDefinition> */
    public function joins(): array
    {
        return $this->joins;
    }

    public function connection(): string
    {
        return $this->connection;
    }

    private function joinAlias(SelectJoinDefinition $join): ?string
    {
        if ($join->relation() === null) {
            return null;
        }

        return $join->alias() ?? Identifier::lastSegment($join->relation());
    }
}
