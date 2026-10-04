<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Search;

/**
 * @api
 */
final class SearchBySearchableColumnsDefinition implements RegistrySearchDefinitionInterface
{
    public function __construct(
        private readonly string $queryKey = 'search',
        private readonly bool $enabled = true
    ) {
        if (\trim($queryKey) === '') {
            throw new \InvalidArgumentException('Search query key must not be empty.');
        }
    }

    public static function default(): self
    {
        return new self();
    }

    public function queryKey(): string
    {
        return $this->queryKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array{enabled:bool,queryKey:string}
     */
    public function jsonSerialize(): array
    {
        return [
            'enabled' => $this->enabled,
            'queryKey' => $this->queryKey,
        ];
    }
}
