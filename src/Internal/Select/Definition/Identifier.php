<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;

/**
 * @internal
 */
final class Identifier
{
    private const SEGMENT_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/D';

    public static function assertAlias(string $alias, string $subject): void
    {
        if (\preg_match(self::SEGMENT_PATTERN, $alias) !== 1) {
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
        $segments = \explode('.', $identifier);

        return $segments[\count($segments) - 1];
    }

    public static function quote(string $identifier): string
    {
        self::assertAlias($identifier, 'SQL identifier');

        return '"' . $identifier . '"';
    }

    public static function quoteQualified(string $identifier): string
    {
        self::assertQualified($identifier, 'SQL identifier');

        return \implode('.', \array_map(
            static fn (string $segment): string => '"' . $segment . '"',
            \explode('.', $identifier)
        ));
    }
}
