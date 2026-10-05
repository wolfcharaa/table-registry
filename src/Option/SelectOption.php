<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Option;

/**
 * @api
 */
final class SelectOption implements \JsonSerializable
{
    /**
     * @param array<string, string|int|float|bool|null> $meta
     */
    private function __construct(
        private readonly string|int $value,
        private readonly string $label,
        private readonly bool $hasCode,
        private readonly ?string $code,
        private readonly bool $hasDescription,
        private readonly ?string $description,
        private readonly bool $hasMeta,
        private readonly array $meta
    ) {
    }

    /**
     * @param array<string, string|int|float|bool|null> $meta
     */
    public static function from(
        string|int $value,
        string $label,
        ?string $code = null,
        ?string $description = null,
        array $meta = []
    ): self {
        return new self(
            $value,
            $label,
            $code !== null,
            $code,
            $description !== null,
            $description,
            $meta !== [],
            $meta
        );
    }

    public function withCode(?string $code): self
    {
        return new self(
            $this->value,
            $this->label,
            true,
            $code,
            $this->hasDescription,
            $this->description,
            $this->hasMeta,
            $this->meta
        );
    }

    public function withDescription(?string $description): self
    {
        return new self(
            $this->value,
            $this->label,
            $this->hasCode,
            $this->code,
            true,
            $description,
            $this->hasMeta,
            $this->meta
        );
    }

    /** @param array<string, string|int|float|bool|null> $meta */
    public function withMeta(array $meta): self
    {
        return new self(
            $this->value,
            $this->label,
            $this->hasCode,
            $this->code,
            $this->hasDescription,
            $this->description,
            true,
            $meta
        );
    }

    public function value(): string|int
    {
        return $this->value;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /** @return array<string, string|int|float|bool|null> */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @return array<string, string|int|float|bool|null|array<string, string|int|float|bool|null>>
     */
    public function jsonSerialize(): array
    {
        $payload = ['value' => $this->value, 'label' => $this->label];
        if ($this->hasCode) {
            $payload['code'] = $this->code;
        }
        if ($this->hasDescription) {
            $payload['description'] = $this->description;
        }
        if ($this->hasMeta) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }
}
