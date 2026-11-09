<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Sql\Identifier as SqlIdentifier;

/**
 * @internal
 */
final class Identifier
{
    public static function assertAlias(string $alias, string $subject): void
    {
        if (!SqlIdentifier::isAlias($alias)) {
            throw InvalidSelectDefinitionException::because(\sprintf(
                '%s "%s" must match [A-Za-z_][A-Za-z0-9_]*.',
                $subject,
                $alias
            ));
        }
    }

    public static function assertQualified(string $identifier, string $subject): void
    {
        $segments = \explode('.', $identifier);
        foreach ($segments as $segment) {
            self::assertAlias($segment, $subject . ' segment');
        }
    }

    public static function lastSegment(string $identifier): string
    {
        return SqlIdentifier::lastSegment($identifier);
    }

    public static function quote(string $identifier): string
    {
        self::assertAlias($identifier, 'SQL identifier');

        return SqlIdentifier::quote($identifier);
    }

    public static function quoteQualified(string $identifier): string
    {
        self::assertQualified($identifier, 'SQL identifier');

        return SqlIdentifier::quoteQualified($identifier);
    }
}
