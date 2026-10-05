<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Query;

/**
 * @api
 */
final class SelectQueryValidationIssue implements \JsonSerializable
{
    public function __construct(
        private readonly string $code,
        private readonly string $key,
        private readonly string $message
    ) {
        if (\trim($code) === '' || \trim($key) === '' || \trim($message) === '') {
            throw new \InvalidArgumentException('Select query validation issue fields must not be empty.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return array{code:string,key:string,message:string} */
    public function jsonSerialize(): array
    {
        return ['code' => $this->code, 'key' => $this->key, 'message' => $this->message];
    }
}
