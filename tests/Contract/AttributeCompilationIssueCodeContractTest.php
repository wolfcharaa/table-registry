<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Contract;

use Testo\Assert;
use Testo\Test;

#[Test]
final class AttributeCompilationIssueCodeContractTest
{
    public function matchesReviewedStableIssueCodeFixture(): void
    {
        $json = \file_get_contents(__DIR__ . '/fixtures/attribute-compilation-issue-codes.json');
        if (!\is_string($json)) {
            throw new \LogicException('Attribute compilation issue-code fixture must be readable.');
        }

        Assert::same(\json_decode($json, true, flags: JSON_THROW_ON_ERROR), [
            'alias.invalid',
            'attribute.invalid',
            'capability.invalid',
            'catalog.duplicate_key',
            'definition.invalid',
            'field.invalid',
            'inheritance.invalid',
            'join.invalid',
            'position.invalid',
            'projection.invalid',
            'projection.provider',
            'resolver.invalid',
            'role.conflict',
            'source.invalid',
            'source.provider',
            'type.invalid',
            'variant.invalid',
        ]);
    }
}
