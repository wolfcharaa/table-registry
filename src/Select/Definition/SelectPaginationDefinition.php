<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;

/**
 * @api
 */
final class SelectPaginationDefinition
{
    private function __construct(private readonly int $defaultLimit, private readonly int $maxLimit)
    {
        if ($defaultLimit < 1 || $maxLimit < 1) {
            throw InvalidSelectDefinitionException::because('Select pagination limits must be positive.');
        }

        if ($defaultLimit > $maxLimit) {
            throw InvalidSelectDefinitionException::because(
                'Select pagination default limit must not exceed its maximum limit.'
            );
        }
    }

    public static function limit(int $defaultLimit = 25, int $maxLimit = 100): self
    {
        return new self($defaultLimit, $maxLimit);
    }

    public function defaultLimit(): int
    {
        return $this->defaultLimit;
    }

    public function maxLimit(): int
    {
        return $this->maxLimit;
    }
}
