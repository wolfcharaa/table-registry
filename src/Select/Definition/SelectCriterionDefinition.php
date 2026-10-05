<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier;

/**
 * @api
 */
final class SelectCriterionDefinition
{
    public const EQUAL = 'equal';
    public const ONE_OF = 'one_of';
    public const EQUAL_OR_ONE_OF = 'equal_or_one_of';
    public const CONTAINS = 'contains';
    public const GREATER_THAN = 'greater_than';
    public const GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    public const LESS_THAN = 'less_than';
    public const LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    public const RANGE = 'range';
    public const NULLABILITY = 'nullability';

    private function __construct(
        private readonly string $key,
        private readonly string $expression,
        private readonly string $operator,
        private readonly ?SelectValueType $valueType
    ) {
        Identifier::assertAlias($key, 'Select criterion key');
        if (\trim($expression) === '') {
            throw InvalidSelectDefinitionException::because('Select criterion expression must not be empty.');
        }
    }

    public static function equal(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::EQUAL, $valueType);
    }

    public static function oneOf(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::ONE_OF, $valueType);
    }

    public static function equalOrOneOf(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::EQUAL_OR_ONE_OF, $valueType);
    }

    public static function contains(string $key, string $expression): self
    {
        return new self($key, $expression, self::CONTAINS, SelectValueType::String);
    }

    public static function greaterThan(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::GREATER_THAN, $valueType);
    }

    public static function greaterThanOrEqual(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::GREATER_THAN_OR_EQUAL, $valueType);
    }

    public static function lessThan(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::LESS_THAN, $valueType);
    }

    public static function lessThanOrEqual(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::LESS_THAN_OR_EQUAL, $valueType);
    }

    public static function range(string $key, string $expression, SelectValueType $valueType): self
    {
        return new self($key, $expression, self::RANGE, $valueType);
    }

    public static function nullability(string $key, string $expression): self
    {
        return new self($key, $expression, self::NULLABILITY, null);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function operator(): string
    {
        return $this->operator;
    }

    public function valueType(): ?SelectValueType
    {
        return $this->valueType;
    }
}
