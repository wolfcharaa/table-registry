<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Exception;

use Wolfcharaa\TableRegistry\Compiler\AttributeCompilationIssue;

/**
 * @api
 */
final class AttributeCompilationException extends \LogicException
{
    /** @var non-empty-list<AttributeCompilationIssue> */
    private array $issues;

    /**
     * @param iterable<AttributeCompilationIssue> $issues
     */
    public function __construct(iterable $issues)
    {
        $normalized = \array_values(\is_array($issues) ? $issues : \iterator_to_array($issues, false));
        if ($normalized === []) {
            throw new \InvalidArgumentException('Attribute compilation exception requires at least one issue.');
        }

        $this->issues = $normalized;

        parent::__construct(\implode("\n", \array_map(
            static fn (AttributeCompilationIssue $issue): string => \sprintf(
                '[%s] %s: %s',
                $issue->code(),
                $issue->consumer(),
                $issue->message()
            ),
            $normalized
        )));
    }

    /** @return non-empty-list<AttributeCompilationIssue> */
    public function issues(): array
    {
        return $this->issues;
    }
}
