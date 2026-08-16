<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Exception;

final class InvalidRegistryDefinitionException extends \LogicException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
