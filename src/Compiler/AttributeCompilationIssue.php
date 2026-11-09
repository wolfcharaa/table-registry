<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

/**
 * @api
 */
final readonly class AttributeCompilationIssue
{
    /**
     * @param class-string $consumer
     * @param class-string|null $attribute
     * @param class-string|null $provider
     */
    public function __construct(
        private string $code,
        private string $message,
        private string $consumer,
        private ?string $variant = null,
        private ?string $property = null,
        private ?string $attribute = null,
        private ?string $provider = null
    ) {
        if (\trim($code) === '' || \trim($message) === '' || \trim($consumer) === '') {
            throw new \InvalidArgumentException('Compilation issue code, message, and consumer must not be empty.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return class-string */
    public function consumer(): string
    {
        return $this->consumer;
    }

    public function variant(): ?string
    {
        return $this->variant;
    }

    public function property(): ?string
    {
        return $this->property;
    }

    /** @return class-string|null */
    public function attribute(): ?string
    {
        return $this->attribute;
    }

    /** @return class-string|null */
    public function provider(): ?string
    {
        return $this->provider;
    }
}
