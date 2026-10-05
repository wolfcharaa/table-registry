<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Option;

/**
 * @api
 */
final class OptionSourceDefinition implements \JsonSerializable
{
    /** @var list<array<string, mixed>> */
    private array $options;

    /** @param array<array-key, mixed> $options */
    private function __construct(
        private readonly string $mode,
        private readonly ?string $route,
        array $options
    ) {
        $normalized = [];
        foreach ($options as $option) {
            if ($option instanceof SelectOption) {
                $normalized[] = $option->jsonSerialize();
                continue;
            }

            if (!\is_array($option)
                || !\array_key_exists('value', $option)
                || (!\is_string($option['value']) && !\is_int($option['value']))
                || !\array_key_exists('label', $option)
                || !\is_string($option['label'])
            ) {
                throw new \InvalidArgumentException(
                    'Static option sources require SelectOption values or arrays containing scalar value and string label.'
                );
            }

            /** @var array<string, mixed> $option */
            $normalized[] = $option;
        }

        $this->options = $normalized;
    }

    /** @param array<array-key, mixed> $options */
    public static function static(array $options = []): self
    {
        return new self('static', null, $options);
    }

    public static function preload(string $route): self
    {
        return new self('preload', self::route($route), []);
    }

    public static function async(string $route): self
    {
        return new self('async', self::route($route), []);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function routePath(): ?string
    {
        return $this->route;
    }

    /** @return list<array<string, mixed>> */
    public function options(): array
    {
        return $this->options;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $payload = ['optionSource' => $this->mode];
        if ($this->route !== null) {
            $payload['route'] = $this->route;
        }
        if ($this->options !== []) {
            $payload['options'] = $this->options;
        }

        return $payload;
    }

    private static function route(string $route): string
    {
        $route = \trim($route);
        if ($route === '') {
            throw new \InvalidArgumentException('Option source route must not be empty.');
        }

        return $route;
    }
}
