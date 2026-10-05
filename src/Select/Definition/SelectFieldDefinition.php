<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier;

/**
 * @api
 */
final class SelectFieldDefinition
{
    private function __construct(
        private readonly string $alias,
        private readonly ?string $column,
        private readonly ?string $sqlExpression
    ) {
        Identifier::assertAlias($alias, 'Select field alias');
    }

    public static function column(string $column, ?string $alias = null): self
    {
        Identifier::assertQualified($column, 'Select field column');

        return new self($alias ?? Identifier::lastSegment($column), $column, null);
    }

    public static function sqlExpression(string $alias, string $expression): self
    {
        $expression = \trim($expression);
        if ($expression === '') {
            throw InvalidSelectDefinitionException::because('Select field SQL expression must not be empty.');
        }

        return new self($alias, null, $expression);
    }

    public function alias(): string
    {
        return $this->alias;
    }

    public function isColumn(): bool
    {
        return $this->column !== null;
    }

    public function columnName(): ?string
    {
        return $this->column;
    }

    public function expression(): string
    {
        return $this->column ?? (string)$this->sqlExpression;
    }
}
