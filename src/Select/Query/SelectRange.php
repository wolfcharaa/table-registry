<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Query;

/**
 * @api
 */
final class SelectRange
{
    private function __construct(
        private readonly bool $hasFrom,
        private readonly mixed $from,
        private readonly bool $hasTo,
        private readonly mixed $to
    ) {
    }

    public static function between(mixed $from, mixed $to): self
    {
        return new self(true, $from, true, $to);
    }

    public static function from(mixed $from): self
    {
        return new self(true, $from, false, null);
    }

    public static function to(mixed $to): self
    {
        return new self(false, null, true, $to);
    }

    public function hasFrom(): bool
    {
        return $this->hasFrom;
    }

    public function fromValue(): mixed
    {
        return $this->from;
    }

    public function hasTo(): bool
    {
        return $this->hasTo;
    }

    public function toValue(): mixed
    {
        return $this->to;
    }
}
