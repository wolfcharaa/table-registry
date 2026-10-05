<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;

/**
 * @api
 */
final class SelectSearchDefinition
{
    /** @var non-empty-list<string> */
    private array $expressions;

    /**
     * @param array<array-key, mixed> $expressions
     */
    private function __construct(array $expressions, private readonly int $minLength, private readonly ?int $maxLength)
    {
        if ($minLength < 1) {
            throw InvalidSelectDefinitionException::because('Select search minimum length must be positive.');
        }

        if ($maxLength !== null && $maxLength < $minLength) {
            throw InvalidSelectDefinitionException::because(
                'Select search maximum length must not be smaller than its minimum length.'
            );
        }

        $normalized = [];
        foreach ($expressions as $expression) {
            if (!\is_string($expression) || \trim($expression) === '') {
                throw InvalidSelectDefinitionException::because(
                    'Select search expressions must be non-empty strings.'
                );
            }

            $normalized[] = \trim($expression);
        }

        if ($normalized === []) {
            throw InvalidSelectDefinitionException::because('Select search must contain at least one expression.');
        }

        $this->expressions = $normalized;
    }

    /**
     * @param array<array-key, mixed> $expressions
     */
    public static function contains(array $expressions, int $minLength = 1, ?int $maxLength = null): self
    {
        return new self($expressions, $minLength, $maxLength);
    }

    /** @return non-empty-list<string> */
    public function expressions(): array
    {
        return $this->expressions;
    }

    public function minLength(): int
    {
        return $this->minLength;
    }

    public function maxLength(): ?int
    {
        return $this->maxLength;
    }
}
