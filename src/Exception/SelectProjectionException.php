<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Exception;

/**
 * @api
 */
final class SelectProjectionException extends \RuntimeException
{
    public static function missingAlias(string $alias, int $rowIndex): self
    {
        return new self(\sprintf(
            'Select row %d does not contain declared alias "%s".',
            $rowIndex,
            $alias
        ));
    }
}
