<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Compiler;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\RelationSourceProvider;
use Wolfcharaa\TableRegistry\Attribute\SqlExpressionProvider;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Attribute\Variant;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Compiler\RelationSourceProviderInterface;
use Wolfcharaa\TableRegistry\Compiler\SqlExpressionProviderInterface;
use Wolfcharaa\TableRegistry\Exception\AttributeCompilationException;
use Wolfcharaa\TableRegistry\Metadata\RelationSourceMetadata;

#[Test]
final class AttributeCompilerProviderTest
{
    public function invokesProvidersOnceAcrossCompleteVariantCompilation(): void
    {
        VariantSourceProvider::$invocations = 0;
        VariantExpressionProvider::$invocations = 0;

        $family = (new AttributeTableRegistryCompiler())->compileVariants(ProviderVariantTable::class);

        Assert::same(\array_keys($family->all()), ['active', 'archive']);
        Assert::same(VariantSourceProvider::$invocations, 1);
        Assert::same(VariantExpressionProvider::$invocations, 1);
    }

    public function reportsExpressionProviderFailureAtExactProperty(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(FailingExpressionTable::class);
        } catch (AttributeCompilationException $exception) {
            $issue = $exception->issues()[0];
            Assert::same($issue->code(), 'projection.provider');
            Assert::same($issue->property(), 'computed');
            Assert::same($issue->attribute(), SqlExpressionProvider::class);
            Assert::same($issue->provider(), FailingExpressionProvider::class);
            Assert::string($issue->message())->contains('expression provider failed');

            return;
        }

        throw new \LogicException('Expression provider failure must be reported.');
    }

    public function reportsSourceProviderFailureAtSourceAttribute(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(FailingSourceTable::class);
        } catch (AttributeCompilationException $exception) {
            $issue = $exception->issues()[0];
            Assert::same($issue->code(), 'source.provider');
            Assert::same($issue->attribute(), RelationSourceProvider::class);
            Assert::same($issue->provider(), FailingSourceProvider::class);
            Assert::string($issue->message())->contains('source provider failed');

            return;
        }

        throw new \LogicException('Source provider failure must be reported.');
    }
}

#[RelationSourceProvider(VariantSourceProvider::class)]
final class ProviderVariantRelation
{
    #[Field('record_id')]
    protected int $id;
}

#[Variant('active', 'provider_variant_active', 'Active', isDefault: true)]
#[Variant('archive', 'provider_variant_archive', 'Archive')]
#[From(ProviderVariantRelation::class, 'record')]
final class ProviderVariantTable
{
    #[Column('ID', 10)]
    #[Project('record')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Computed', 20)]
    #[SqlExpressionProvider(VariantExpressionProvider::class)]
    protected string $computed;
}

final class VariantSourceProvider implements RelationSourceProviderInterface
{
    public static int $invocations = 0;

    public function __invoke(): RelationSourceMetadata
    {
        ++self::$invocations;

        return RelationSourceMetadata::relation('public.provider_variants');
    }
}

final class VariantExpressionProvider implements SqlExpressionProviderInterface
{
    public static int $invocations = 0;

    public function __invoke(): string
    {
        ++self::$invocations;

        return "'value'";
    }
}

#[Relation('public.provider_failures')]
final class ProviderFailureRelation
{
    #[Field('record_id')]
    protected int $id;
}

#[Table('failing_expression', 'Failing expression')]
#[From(ProviderFailureRelation::class, 'record')]
final class FailingExpressionTable
{
    #[Column('ID', 10)]
    #[Project('record')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Computed', 20)]
    #[SqlExpressionProvider(FailingExpressionProvider::class)]
    protected string $computed;
}

final class FailingExpressionProvider implements SqlExpressionProviderInterface
{
    public function __invoke(): string
    {
        throw new \RuntimeException('expression provider failed');
    }
}

#[RelationSourceProvider(FailingSourceProvider::class)]
final class FailingSourceRelation
{
    #[Field('record_id')]
    protected int $id;
}

#[Table('failing_source', 'Failing source')]
#[From(FailingSourceRelation::class, 'record')]
final class FailingSourceTable
{
    #[Column('ID', 10)]
    #[Project('record')]
    #[PrimaryKey]
    protected int $id;
}

final class FailingSourceProvider implements RelationSourceProviderInterface
{
    public function __invoke(): RelationSourceMetadata
    {
        throw new \RuntimeException('source provider failed');
    }
}
