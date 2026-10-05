<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Source;

use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;

/**
 * @api
 */
final class RegistrySourceDefinition
{
    private ?RegistryDataScopeDefinition $dataScope = null;
    private ?string $countSource = null;
    private SelectSourceDefinition $selectSource;

    private function __construct(private readonly string $from)
    {
        if (\trim($from) === '') {
            throw new \InvalidArgumentException('Registry source FROM must not be empty.');
        }

        $this->selectSource = SelectSourceDefinition::fromSql($from);
    }

    public static function relation(string $from): self
    {
        return new self($from);
    }

    public static function from(string $from): self
    {
        return new self($from);
    }

    public function withJoin(string $joinSql): self
    {
        $joinSql = \trim($joinSql);
        if ($joinSql === '') {
            throw new \InvalidArgumentException('Registry source JOIN must not be empty.');
        }

        $clone = clone $this;
        $clone->selectSource = $clone->selectSource->withRawJoin($joinSql);

        return $clone;
    }

    public function withLeftJoin(string $relation, string $on): self
    {
        return $this->withJoin(\sprintf('LEFT JOIN %s ON %s', \trim($relation), \trim($on)));
    }

    public function withConnection(string $connection): self
    {
        $connection = \trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('Registry source connection must not be empty.');
        }

        $clone = clone $this;
        $clone->selectSource = $clone->selectSource->withConnection($connection);

        return $clone;
    }

    public function withCountSource(string $countSource): self
    {
        $countSource = \trim($countSource);
        if ($countSource === '') {
            throw new \InvalidArgumentException('Registry source count source must not be empty.');
        }

        $clone = clone $this;
        $clone->countSource = $countSource;

        return $clone;
    }

    public function withDataScope(RegistryDataScopeDefinition $dataScope): self
    {
        $clone = clone $this;
        $clone->dataScope = $dataScope;

        return $clone;
    }

    public function fromSql(): string
    {
        return $this->from;
    }

    /**
     * @return list<string>
     */
    public function joins(): array
    {
        $joins = [];
        foreach ($this->selectSource->joins() as $join) {
            if ($join->raw() !== null) {
                $joins[] = $join->raw();
            }
        }

        return $joins;
    }

    public function connection(): string
    {
        return $this->selectSource->connection();
    }

    public function dataScope(): ?RegistryDataScopeDefinition
    {
        return $this->dataScope;
    }

    public function countSource(): ?string
    {
        return $this->countSource;
    }
}
