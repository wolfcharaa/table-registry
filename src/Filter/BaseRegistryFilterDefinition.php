<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Filter;

/**
 * @api
 */
class BaseRegistryFilterDefinition implements RegistryFilterDefinitionInterface
{
    /**
     * @param list<string> $operators
     * @param array<string, mixed> $extraJson
     */
    public function __construct(
        private readonly string $queryKey,
        private readonly string $title,
        private readonly ?string $columnKey,
        private readonly string $valueFormat,
        private readonly array $operators,
        private readonly array $extraJson = []
    ) {
        if (\trim($queryKey) === '') {
            throw new \InvalidArgumentException('Filter query key must not be empty.');
        }

        if (\trim($title) === '') {
            throw new \InvalidArgumentException('Filter title must not be empty.');
        }

        if (\trim($valueFormat) === '') {
            throw new \InvalidArgumentException('Filter value format must not be empty.');
        }

        if ($operators === []) {
            throw new \InvalidArgumentException('Filter operators must not be empty.');
        }
    }

    public function queryKey(): string
    {
        return $this->queryKey;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function columnKey(): ?string
    {
        return $this->columnKey;
    }

    public function valueFormat(): string
    {
        return $this->valueFormat;
    }

    public function operators(): array
    {
        return $this->operators;
    }

    public function normalize(mixed $value): mixed
    {
        if (\is_string($value)) {
            $value = \trim($value);

            return $value !== '' ? $value : null;
        }

        return $value;
    }

    public function expression(mixed $normalizedValue): RegistryFilterExpressionInterface
    {
        return new BasicRegistryFilterExpression(
            $this->columnKey,
            $this->operators[0],
            $normalizedValue
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return \array_replace([
            'queryKey' => $this->queryKey,
            'label' => $this->title,
            'columnKey' => $this->columnKey,
            'valueFormat' => $this->valueFormat,
            'operators' => $this->operators,
        ], $this->extraJson, $this->anyOptions());
    }

    /**
     * @return array<string, mixed>
     */
    protected function anyOptions(): array
    {
        return [];
    }
}
