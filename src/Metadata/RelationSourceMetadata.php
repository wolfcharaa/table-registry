<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Metadata;

use Wolfcharaa\TableRegistry\Internal\Sql\Identifier;

/**
 * @api
 */
final class RelationSourceMetadata
{
    /**
     * @param class-string|null $provider
     */
    private function __construct(
        private readonly ?string $relation,
        private readonly ?string $rawSql,
        private readonly ?string $provider,
        private readonly ?string $connection
    ) {
    }

    public static function relation(string $identifier, string $connection = 'default'): self
    {
        self::assertNotEmpty($identifier, 'Relation identifier');
        self::assertNotEmpty($connection, 'Relation connection');
        if (!Identifier::isQualified($identifier)) {
            throw new \InvalidArgumentException('Relation identifier must contain qualified SQL identifiers.');
        }

        return new self($identifier, null, null, $connection);
    }

    public static function raw(string $sql, string $connection = 'default'): self
    {
        self::assertNotEmpty($sql, 'Raw relation SQL');
        self::assertNotEmpty($connection, 'Relation connection');

        return new self(null, $sql, null, $connection);
    }

    /**
     * @param class-string $provider
     */
    public static function providedBy(string $provider): self
    {
        self::assertNotEmpty($provider, 'Relation source provider');

        return new self(null, null, $provider, null);
    }

    public function isRelation(): bool
    {
        return $this->relation !== null;
    }

    public function isRaw(): bool
    {
        return $this->rawSql !== null;
    }

    public function isProvided(): bool
    {
        return $this->provider !== null;
    }

    public function relationIdentifier(): ?string
    {
        return $this->relation;
    }

    public function rawSql(): ?string
    {
        return $this->rawSql;
    }

    /** @return class-string|null */
    public function provider(): ?string
    {
        return $this->provider;
    }

    public function connection(): ?string
    {
        return $this->connection;
    }

    private static function assertNotEmpty(string $value, string $subject): void
    {
        if (\trim($value) === '') {
            throw new \InvalidArgumentException($subject . ' must not be empty.');
        }
    }
}
