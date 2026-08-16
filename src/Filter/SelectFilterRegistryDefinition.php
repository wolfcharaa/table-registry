<?php

declare(strict_types=1);

namespace TableRegistry\Filter;

final class SelectFilterRegistryDefinition extends BaseRegistryFilterDefinition
{
    /**
     * @param list<array{value:string,label:string}> $options
     */
    private function __construct(
        string $queryKey,
        string $columnKey,
        string $title,
        private readonly string $optionSource,
        private readonly ?string $route = null,
        private readonly array $options = [],
        string $valueFormat = 'string',
        array $operators = ['exact', 'in']
    ) {
        if (
            \in_array($optionSource, ['async', 'preload'], true)
            && ($route === null || \trim($route) === '')
        ) {
            throw new \InvalidArgumentException('Async and preload select filters must define a route.');
        }

        parent::__construct($queryKey, $title, $columnKey, $valueFormat, $operators);
    }

    public static function preload(
        string $queryKey,
        string $columnKey,
        string $title,
        string $route,
        string $valueFormat = 'string'
    ): self {
        return new self($queryKey, $columnKey, $title, 'preload', $route, valueFormat: $valueFormat);
    }

    public static function async(
        string $queryKey,
        string $columnKey,
        string $title,
        string $route,
        string $valueFormat = 'string'
    ): self {
        return new self($queryKey, $columnKey, $title, 'async', $route, valueFormat: $valueFormat);
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
        return new self($queryKey, $columnKey, $title, 'static', options: $options, valueFormat: $valueFormat);
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
        $payload = [
            'optionSource' => $this->optionSource,
        ];

        if ($this->route !== null) {
            $payload['route'] = $this->route;
        }

        if ($this->options !== []) {
            $payload['options'] = $this->options;
        }

        return $payload;
    }
}
