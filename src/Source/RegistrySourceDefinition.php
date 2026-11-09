<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Source;

use Wolfcharaa\TableRegistry\Exception\UnsupportedRegistrySourceException;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;

/**
 * @api
 */
final class RegistrySourceDefinition
{
    private ?RegistryDataScopeDefinition $dataScope = null;
    private ?string $countSource = null;
    private ?SelectSourceDefinition $selectSource;

    private function __construct(
        private readonly RegistrySourceKind $kind,
        private readonly ?string $from,
        private readonly ?string $externalKey
    ) {
        $this->selectSource = $from !== null ? SelectSourceDefinition::fromSql($from) : null;
    }

    public static function relation(string $from): self
    {
        return self::from($from);
    }

    public static function from(string $from): self
    {
        if (\trim($from) === '') {
            throw new \InvalidArgumentException('Registry source FROM must not be empty.');
        }

        return new self(RegistrySourceKind::Sql, $from, null);
    }

    public static function external(string $key): self
    {
        if (\trim($key) === '') {
            throw new \InvalidArgumentException('External registry source key must not be empty.');
        }

        return new self(RegistrySourceKind::External, null, $key);
    }

    public function withJoin(string $joinSql): self
    {
        $joinSql = \trim($joinSql);
        if ($joinSql === '') {
            throw new \InvalidArgumentException('Registry source JOIN must not be empty.');
        }

        $clone = clone $this;
        $clone->selectSource = $this->sqlSource('add a JOIN')->withRawJoin($joinSql);

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
        $clone->selectSource = $this->sqlSource('select a connection')->withConnection($connection);

        return $clone;
    }

    public function withCountSource(string $countSource): self
    {
        $this->sqlSource('select a count source');

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
        $this->sqlSource('read SQL');

        return (string)$this->from;
    }

    /**
     * @return list<string>
     */
    public function joins(): array
    {
        if ($this->selectSource === null) {
            return [];
        }

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
        return $this->sqlSource('read a connection')->connection();
    }

    public function dataScope(): ?RegistryDataScopeDefinition
    {
        return $this->dataScope;
    }

    public function countSource(): ?string
    {
        return $this->countSource;
    }

    public function kind(): RegistrySourceKind
    {
        return $this->kind;
    }

    public function isSql(): bool
    {
        return $this->kind === RegistrySourceKind::Sql;
    }

    public function isExternal(): bool
    {
        return $this->kind === RegistrySourceKind::External;
    }

    public function externalKey(): ?string
    {
        return $this->externalKey;
    }

    private function sqlSource(string $operation): SelectSourceDefinition
    {
        if ($this->selectSource !== null) {
            return $this->selectSource;
        }

        throw new UnsupportedRegistrySourceException(\sprintf(
            'External registry source "%s" cannot %s.',
            $this->externalKey,
            $operation
        ));
    }
}
