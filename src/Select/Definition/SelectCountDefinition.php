<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;

/**
 * @api
 */
final class SelectCountDefinition
{
    private function __construct(
        private readonly ?SelectSourceDefinition $source,
        private readonly string $expression,
        private readonly string $alias
    ) {
        if (\trim($expression) === '') {
            throw InvalidSelectDefinitionException::because('Select count expression must not be empty.');
        }

        \Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier::assertAlias(
            $alias,
            'Select count alias'
        );
    }

    public static function matchingRows(string $expression = 'COUNT(*)::bigint', string $alias = 'total'): self
    {
        return new self(null, $expression, $alias);
    }

    public static function fromSource(
        SelectSourceDefinition $source,
        string $expression = 'COUNT(*)::bigint',
        string $alias = 'total'
    ): self {
        return new self($source, $expression, $alias);
    }

    public function source(): ?SelectSourceDefinition
    {
        return $this->source;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function alias(): string
    {
        return $this->alias;
    }
}
