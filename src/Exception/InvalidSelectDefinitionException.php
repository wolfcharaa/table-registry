<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Exception;

/**
 * @api
 */
final class InvalidSelectDefinitionException extends \InvalidArgumentException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
