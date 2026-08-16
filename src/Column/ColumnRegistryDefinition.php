<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Column;

use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Filter\RegistryFilterDefinitionInterface;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Runtime\RegistryResolverInvokerInterface;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;
use Wolfcharaa\TableRegistry\Value\RawValue;

class ColumnRegistryDefinition implements \JsonSerializable
{
    /** @var callable|class-string<ColumnValueLabelResolverInterface>|string|null */
    private mixed $label = null;
    private ?RegistryFilterDefinitionInterface $filter = null;
    private ?RegistrySortDefinition $sort = null;
    private ?ColumnSelectDefinition $select = null;
    private bool $searchable = false;
    private ?string $hint = null;
    private bool $payloadOnly = false;

    /** @var array<string, ColumnSelectDefinition> */
    private array $auxiliarySelects = [];

    /** @var class-string<PrimitiveValueObjectInterface> */
    private string $rowTypeObject = RawValue::class;

    /** @var callable|class-string<ColumnValueHydratorInterface>|null */
    private mixed $hydration = null;

    public function __construct(
        private readonly string $key,
        private readonly string $title
    ) {
        if (\trim($key) === '') {
            throw new \InvalidArgumentException('Column key must not be empty.');
        }

        if (\trim($title) === '') {
            throw new \InvalidArgumentException('Column title must not be empty.');
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @param callable|class-string<ColumnValueLabelResolverInterface>|string|null $label
     */
    public function withLabel(callable|string|null $label): static
    {
        $this->assertLabelResolver($label);

        $clone = clone $this;
        $clone->label = $label;

        return $clone;
    }

    public function withFilter(?RegistryFilterDefinitionInterface $filter): static
    {
        $clone = clone $this;
        $clone->filter = $filter;

        return $clone;
    }

    public function withSort(?RegistrySortDefinition $sort): static
    {
        $clone = clone $this;
        $clone->sort = $sort;

        return $clone;
    }

    public function withSearchable(bool $searchable = true): static
    {
        $clone = clone $this;
        $clone->searchable = $searchable;

        return $clone;
    }

    public function withSelect(?ColumnSelectDefinition $select): static
    {
        $clone = clone $this;
        $clone->select = $select;

        return $clone;
    }

    public function withAuxiliarySelect(ColumnSelectDefinition $select): static
    {
        $clone = clone $this;
        $clone->auxiliarySelects[$select->alias()] = $select;

        return $clone;
    }

    /**
     * @param class-string<PrimitiveValueObjectInterface> $rowTypeObject
     */
    public function withRowTypeObject(string $rowTypeObject): static
    {
        if (!\is_subclass_of($rowTypeObject, PrimitiveValueObjectInterface::class)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Row type object "%s" must implement %s.',
                $rowTypeObject,
                PrimitiveValueObjectInterface::class
            ));
        }

        $clone = clone $this;
        $clone->rowTypeObject = $rowTypeObject;

        return $clone;
    }

    /**
     * @param callable|class-string<ColumnValueHydratorInterface>|null $resolver
     */
    public function withHydration(callable|string|null $resolver): static
    {
        $this->assertHydrationResolver($resolver);

        $clone = clone $this;
        $clone->hydration = $resolver;

        return $clone;
    }

    public function withHint(?string $hint): static
    {
        $clone = clone $this;
        $clone->hint = $hint !== null && \trim($hint) !== '' ? $hint : null;

        return $clone;
    }

    public function withPayloadOnly(bool $payloadOnly = true): static
    {
        $clone = clone $this;
        $clone->payloadOnly = $payloadOnly;

        return $clone;
    }

    public function withActionOnly(bool $actionOnly = true): static
    {
        return $this->withPayloadOnly($actionOnly);
    }

    public function filter(): ?RegistryFilterDefinitionInterface
    {
        return $this->filter;
    }

    public function sort(): ?RegistrySortDefinition
    {
        return $this->sort;
    }

    public function select(): ?ColumnSelectDefinition
    {
        return $this->select;
    }

    /**
     * @return list<ColumnSelectDefinition>
     */
    public function auxiliarySelects(): array
    {
        return \array_values($this->auxiliarySelects);
    }

    public function searchable(): bool
    {
        return $this->searchable;
    }

    public function sortable(): bool
    {
        return $this->sort !== null;
    }

    public function filterable(): bool
    {
        return $this->filter !== null;
    }

    public function isPrimaryKey(): bool
    {
        return false;
    }

    public function payloadOnly(): bool
    {
        return $this->payloadOnly;
    }

    public function exposedInDefinition(): bool
    {
        return !$this->payloadOnly;
    }

    /**
     * @return class-string<PrimitiveValueObjectInterface>
     */
    public function rowTypeObject(): string
    {
        return $this->rowTypeObject;
    }

    public function rawValueObject(mixed $rawValue): PrimitiveValueObjectInterface
    {
        $className = $this->rowTypeObject;

        return $className::value($rawValue);
    }

    public function value(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        RegistryResolverInvokerInterface $invoker
    ): mixed {
        if ($this->hydration === null) {
            return $value->getValue();
        }

        return $invoker->invoke($this->hydration, [
            'value' => $value,
            'row' => $row,
            'column' => $this,
        ]);
    }

    public function label(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        mixed $hydratedValue,
        RegistryResolverInvokerInterface $invoker
    ): string {
        if ($this->label === null) {
            if ($value instanceof DateTimeValue) {
                return (new DateTimeValueLabelResolver())($value, $row, $this);
            }

            return $this->stringify($hydratedValue);
        }

        if (\is_string($this->label) && !\class_exists($this->label)) {
            return $this->label;
        }

        $label = $invoker->invoke($this->label, [
            'value' => $value,
            'row' => $row,
            'column' => $this,
        ]);

        return $this->stringify($label);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'key' => $this->key,
            'title' => $this->title,
            'searchable' => $this->searchable(),
            'sortable' => $this->sortable(),
        ];

        if ($this->filter !== null) {
            $payload['filterable'] = true;
            $payload['filter'] = $this->filter->jsonSerialize();
        } else {
            $payload['filterable'] = false;
        }

        if ($this->hint !== null) {
            $payload['hint'] = $this->hint;
        }

        return $payload;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        if (\is_scalar($value)) {
            return (string)$value;
        }

        $encoded = \json_encode($value, JSON_UNESCAPED_UNICODE);

        return \is_string($encoded) ? $encoded : '';
    }

    private function assertLabelResolver(callable|string|null $label): void
    {
        if (!\is_string($label) || !\class_exists($label)) {
            return;
        }

        if (!\is_subclass_of($label, ColumnValueLabelResolverInterface::class)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Column label resolver "%s" must implement %s.',
                $label,
                ColumnValueLabelResolverInterface::class
            ));
        }
    }

    private function assertHydrationResolver(callable|string|null $resolver): void
    {
        if (!\is_string($resolver) || !\class_exists($resolver)) {
            return;
        }

        if (!\is_subclass_of($resolver, ColumnValueHydratorInterface::class)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Column hydration resolver "%s" must implement %s.',
                $resolver,
                ColumnValueHydratorInterface::class
            ));
        }
    }
}
