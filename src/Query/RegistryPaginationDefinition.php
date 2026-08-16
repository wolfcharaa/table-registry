<?php

declare(strict_types=1);

namespace TableRegistry\Query;

final class RegistryPaginationDefinition implements \JsonSerializable
{
    public function __construct(
        private readonly string $pageKey = 'page',
        private readonly string $perPageKey = 'per_page',
        private readonly int $defaultPerPage = 25,
        private readonly int $maxPerPage = 250
    ) {
        if (\trim($pageKey) === '' || \trim($perPageKey) === '') {
            throw new \InvalidArgumentException('Pagination query keys must not be empty.');
        }

        if ($defaultPerPage < 1 || $maxPerPage < 1 || $defaultPerPage > $maxPerPage) {
            throw new \InvalidArgumentException('Invalid pagination limits.');
        }
    }

    public static function page(): self
    {
        return new self();
    }

    public function pageKey(): string
    {
        return $this->pageKey;
    }

    public function perPageKey(): string
    {
        return $this->perPageKey;
    }

    public function defaultPerPage(): int
    {
        return $this->defaultPerPage;
    }

    public function maxPerPage(): int
    {
        return $this->maxPerPage;
    }

    /**
     * @return array{type:string,pageKey:string,perPageKey:string,defaultPerPage:int,maxPerPage:int}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'page',
            'pageKey' => $this->pageKey,
            'perPageKey' => $this->perPageKey,
            'defaultPerPage' => $this->defaultPerPage,
            'maxPerPage' => $this->maxPerPage,
        ];
    }
}
