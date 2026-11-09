<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Sql;

/**
 * @internal
 */
final class Identifier
{
    private const SEGMENT_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/D';

    public static function isAlias(string $alias): bool
    {
        return \preg_match(self::SEGMENT_PATTERN, $alias) === 1;
    }

    public static function isQualified(string $identifier): bool
    {
        $segments = \explode('.', $identifier);
        foreach ($segments as $segment) {
            if (!self::isAlias($segment)) {
                return false;
            }
        }

        return true;
    }

    public static function lastSegment(string $identifier): string
    {
        $segments = \explode('.', $identifier);

        return $segments[\count($segments) - 1];
    }

    public static function quote(string $identifier): string
    {
        return '"' . $identifier . '"';
    }

    public static function quoteQualified(string $identifier): string
    {
        return \implode('.', \array_map(self::quote(...), \explode('.', $identifier)));
    }
}
