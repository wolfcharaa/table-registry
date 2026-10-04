<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Query;

use Wolfcharaa\TableRegistry\Exception\InvalidRegistryQueryException;

/**
 * @api
 */
final class RegistryQueryValidationResult implements \JsonSerializable
{
    /** @var list<RegistryQueryValidationIssue> */
    private array $issues;

    /**
     * @param array<array-key, mixed> $issues
     */
    public function __construct(array $issues = [])
    {
        $normalized = [];
        foreach ($issues as $issue) {
            if (!$issue instanceof RegistryQueryValidationIssue) {
                throw new \InvalidArgumentException(\sprintf(
                    'Registry query validation issue must be instance of %s.',
                    RegistryQueryValidationIssue::class
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

    /**
     * @return list<RegistryQueryValidationIssue>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function assertValid(): void
    {
        if (!$this->isValid()) {
            throw InvalidRegistryQueryException::fromResult($this);
        }
    }

    /**
     * @return array{valid:bool,issues:list<array{code:string,queryKey:string,message:string}>}
     */
    public function jsonSerialize(): array
    {
        return [
            'valid' => $this->isValid(),
            'issues' => \array_map(
                static fn (RegistryQueryValidationIssue $issue): array => $issue->jsonSerialize(),
                $this->issues
            ),
        ];
    }
}
