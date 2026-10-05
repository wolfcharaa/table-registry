<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Query;

/**
 * @api
 */
final class SelectQueryInput
{
    /**
     * @param array<string, mixed> $criteria
     */
    private function __construct(
        private readonly ?string $search,
        private readonly array $criteria,
        private readonly ?string $sortKey,
        private readonly ?SelectSortDirection $sortDirection,
        private readonly ?int $limit,
        private readonly ?int $offset
    ) {
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function from(
        ?string $search = null,
        array $criteria = [],
        ?string $sortKey = null,
        ?SelectSortDirection $sortDirection = null,
        ?int $limit = null,
        ?int $offset = null
    ): self {
        return new self(
            $search,
            $criteria,
            $sortKey,
            $sortKey !== null ? ($sortDirection ?? SelectSortDirection::Ascending) : $sortDirection,
            $limit,
            $offset
        );
    }

    public static function empty(): self
    {
        return new self(null, [], null, null, null, null);
    }

    public function search(): ?string
    {
        return $this->search;
    }

    /** @return array<string, mixed> */
    public function criteria(): array
    {
        return $this->criteria;
    }

    public function hasCriterion(string $key): bool
    {
        return \array_key_exists($key, $this->criteria);
    }

    public function criterion(string $key): mixed
    {
        return $this->criteria[$key] ?? null;
    }

    public function sortKey(): ?string
    {
        return $this->sortKey;
    }

    public function sortDirection(): ?SelectSortDirection
    {
        return $this->sortDirection;
    }

    public function limit(): ?int
    {
        return $this->limit;
    }

    public function offset(): ?int
    {
        return $this->offset;
    }
}
