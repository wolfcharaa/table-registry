<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Exception;

use Wolfcharaa\TableRegistry\Query\RegistryQueryValidationResult;

/**
 * @api
 */
final class InvalidRegistryQueryException extends \InvalidArgumentException
{
    private function __construct(private readonly RegistryQueryValidationResult $result)
    {
        parent::__construct('Registry query is invalid.');
    }

    public static function fromResult(RegistryQueryValidationResult $result): self
    {
        if ($result->isValid()) {
            throw new \InvalidArgumentException('Cannot create invalid query exception from valid result.');
        }

        return new self($result);
    }

    public function result(): RegistryQueryValidationResult
    {
        return $this->result;
    }
}
