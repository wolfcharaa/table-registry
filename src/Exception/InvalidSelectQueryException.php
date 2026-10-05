<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Exception;

use Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidationResult;

/**
 * @api
 */
final class InvalidSelectQueryException extends \InvalidArgumentException
{
    private function __construct(string $message, private readonly SelectQueryValidationResult $result)
    {
        parent::__construct($message);
    }

    public static function fromResult(SelectQueryValidationResult $result): self
    {
        $messages = \array_map(
            static fn ($issue): string => $issue->message(),
            $result->issues()
        );

        return new self('Invalid select query: ' . \implode('; ', $messages), $result);
    }

    public function result(): SelectQueryValidationResult
    {
        return $this->result;
    }
}
