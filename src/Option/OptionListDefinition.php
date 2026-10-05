<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Option;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;

/**
 * @api
 */
final class OptionListDefinition
{
    /** @var array<string, array{alias:string,type:SelectValueType}> */
    private array $meta = [];
    private ?string $codeAlias = null;
    private ?string $descriptionAlias = null;

    private function __construct(
        private readonly SelectDefinition $select,
        private readonly string $valueAlias,
        private readonly string $labelAlias,
        private readonly SelectValueType $valueType
    ) {
        $this->assertFieldExists($valueAlias);
        $this->assertFieldExists($labelAlias);
        if (!\in_array($valueType, [SelectValueType::String, SelectValueType::Integer], true)) {
            throw InvalidSelectDefinitionException::because('Option value type must be string or integer.');
        }
    }

    public static function from(
        SelectDefinition $select,
        string $valueAlias,
        string $labelAlias,
        SelectValueType $valueType = SelectValueType::String
    ): self {
        return new self($select, $valueAlias, $labelAlias, $valueType);
    }

    public function withCode(string $alias): self
    {
        $this->assertFieldExists($alias);
        $clone = clone $this;
        $clone->codeAlias = $alias;

        return $clone;
    }

    public function withDescription(string $alias): self
    {
        $this->assertFieldExists($alias);
        $clone = clone $this;
        $clone->descriptionAlias = $alias;

        return $clone;
    }

    public function withMeta(string $key, string $alias, SelectValueType $type): self
    {
        Identifier::assertAlias($key, 'Option meta key');
        $this->assertFieldExists($alias);
        if (!\in_array($type, [
            SelectValueType::String,
            SelectValueType::Integer,
            SelectValueType::Float,
            SelectValueType::Boolean,
        ], true)) {
            throw InvalidSelectDefinitionException::because(
                'Option meta type must be string, integer, float or boolean.'
            );
        }

        if (\array_key_exists($key, $this->meta)) {
            throw InvalidSelectDefinitionException::because(\sprintf('Duplicate option meta key "%s".', $key));
        }

        $clone = clone $this;
        $clone->meta[$key] = ['alias' => $alias, 'type' => $type];

        return $clone;
    }

    public function select(): SelectDefinition
    {
        return $this->select;
    }

    public function valueAlias(): string
    {
        return $this->valueAlias;
    }

    public function labelAlias(): string
    {
        return $this->labelAlias;
    }

    public function valueType(): SelectValueType
    {
        return $this->valueType;
    }

    public function codeAlias(): ?string
    {
        return $this->codeAlias;
    }

    public function descriptionAlias(): ?string
    {
        return $this->descriptionAlias;
    }

    /** @return array<string, array{alias:string,type:SelectValueType}> */
    public function meta(): array
    {
        return $this->meta;
    }

    private function assertFieldExists(string $alias): void
    {
        if ($this->select->field($alias) === null) {
            throw InvalidSelectDefinitionException::because(\sprintf(
                'Option mapping alias "%s" is not declared by the select definition.',
                $alias
            ));
        }
    }
}
