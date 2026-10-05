<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Filter;

use Wolfcharaa\TableRegistry\Option\OptionSourceDefinition;

/**
 * @api
 */
final class SelectFilterRegistryDefinition extends BaseRegistryFilterDefinition
{
    private function __construct(
        string $queryKey,
        string $columnKey,
        string $title,
        private readonly OptionSourceDefinition $optionSource,
        string $valueFormat = 'string',
        array $operators = ['exact', 'in']
    ) {
        parent::__construct($queryKey, $title, $columnKey, $valueFormat, $operators);
    }

    public static function preload(
        string $queryKey,
        string $columnKey,
        string $title,
        string $route,
        string $valueFormat = 'string'
    ): self {
        self::assertRoute($route);

        return new self(
            $queryKey,
            $columnKey,
            $title,
            OptionSourceDefinition::preload($route),
            valueFormat: $valueFormat
        );
    }

    public static function async(
        string $queryKey,
        string $columnKey,
        string $title,
        string $route,
        string $valueFormat = 'string'
    ): self {
        self::assertRoute($route);

        return new self(
            $queryKey,
            $columnKey,
            $title,
            OptionSourceDefinition::async($route),
            valueFormat: $valueFormat
        );
    }

    /**
     * @param list<array{value:string,label:string}> $options
     */
    public static function static(
        string $queryKey,
        string $columnKey,
        string $title,
        array $options = [],
        string $valueFormat = 'string'
    ): self {
        return new self(
            $queryKey,
            $columnKey,
            $title,
            OptionSourceDefinition::static($options),
            valueFormat: $valueFormat
        );
    }

    public function normalize(mixed $value): mixed
    {
        if ($this->valueFormat() !== 'integer') {
            return parent::normalize($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (\is_array($value)) {
            $values = [];
            foreach ($value as $item) {
                if (\is_numeric($item)) {
                    $values[] = (int)$item;
                }
            }

            return $values;
        }

        return \is_numeric($value) ? (int)$value : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function anyOptions(): array
    {
        return $this->optionSource->jsonSerialize();
    }

    private static function assertRoute(string $route): void
    {
        if (\trim($route) === '') {
            throw new \InvalidArgumentException('Async and preload select filters must define a route.');
        }
    }
}
