<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Exception;

/**
 * @api
 */
final class SelectCompilationException extends \RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
