<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Query;

/**
 * @api
 */
final class RegistryQueryValidationIssue implements \JsonSerializable
{
    public function __construct(
        private readonly string $code,
        private readonly string $queryKey,
        private readonly string $message
    ) {
        if (\trim($code) === '') {
            throw new \InvalidArgumentException('Validation issue code must not be empty.');
        }

        if (\trim($queryKey) === '') {
            throw new \InvalidArgumentException('Validation issue query key must not be empty.');
        }

        if (\trim($message) === '') {
            throw new \InvalidArgumentException('Validation issue message must not be empty.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function queryKey(): string
    {
        return $this->queryKey;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array{code:string,queryKey:string,message:string}
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'queryKey' => $this->queryKey,
            'message' => $this->message,
        ];
    }
}
