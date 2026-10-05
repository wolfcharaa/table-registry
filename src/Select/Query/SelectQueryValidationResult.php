<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Query;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectQueryException;

/**
 * @api
 */
final class SelectQueryValidationResult implements \JsonSerializable
{
    /** @var list<SelectQueryValidationIssue> */
    private array $issues;

    /** @param array<array-key, mixed> $issues */
    public function __construct(array $issues = [])
    {
        $normalized = [];
        foreach ($issues as $issue) {
            if (!$issue instanceof SelectQueryValidationIssue) {
                throw new \InvalidArgumentException(\sprintf(
                    'Select query validation issues must be instances of %s.',
                    SelectQueryValidationIssue::class
                ));
            }

            $normalized[] = $issue;
        }

        $this->issues = $normalized;
    }

    public function isValid(): bool
    {
        return $this->issues === [];
    }

    /** @return list<SelectQueryValidationIssue> */
    public function issues(): array
    {
        return $this->issues;
    }

    public function assertValid(): void
    {
        if (!$this->isValid()) {
            throw InvalidSelectQueryException::fromResult($this);
        }
    }

    /** @return array{valid:bool,issues:list<array{code:string,key:string,message:string}>} */
    public function jsonSerialize(): array
    {
        return [
            'valid' => $this->isValid(),
            'issues' => \array_map(
                static fn (SelectQueryValidationIssue $issue): array => $issue->jsonSerialize(),
                $this->issues
            ),
        ];
    }
}
