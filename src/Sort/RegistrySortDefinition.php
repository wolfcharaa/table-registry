<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sort;

/**
 * @api
 */
final class RegistrySortDefinition implements \JsonSerializable
{
    private function __construct(
        private readonly string $columnKey,
        private readonly ?string $expression = null
    ) {
        if (\trim($columnKey) === '') {
            throw new \InvalidArgumentException('Sort column key must not be empty.');
        }
    }

    public static function byColumn(string $columnKey): self
    {
        return new self($columnKey);
    }

    public static function byExpression(string $columnKey, string $expression): self
    {
        $expression = \trim($expression);
        if ($expression === '') {
            throw new \InvalidArgumentException('Sort expression must not be empty.');
        }

        return new self($columnKey, $expression);
    }

    public function columnKey(): string
    {
        return $this->columnKey;
    }

    public function expression(): ?string
    {
        return $this->expression;
    }

    /**
     * @return array{column:string}
     */
    public function jsonSerialize(): array
    {
        return ['column' => $this->columnKey];
    }
}
