<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Compiler;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\CountSource;
use Wolfcharaa\TableRegistry\Attribute\DefaultSort;
use Wolfcharaa\TableRegistry\Attribute\ExcludeFromVariant;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\OnlyInVariant;
use Wolfcharaa\TableRegistry\Attribute\Pagination;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Sort;
use Wolfcharaa\TableRegistry\Attribute\SqlConstraint;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Attribute\Variant;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Exception\AttributeCompilationException;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;

#[Test]
final class AttributeCompilerVariantTest
{
    public function compilesDefaultExplicitAndCompleteVariantFamily(): void
    {
        $compiler = new AttributeTableRegistryCompiler();
        $active = $compiler->compile(VariantCompaniesTable::class);
        $archive = $compiler->compile(VariantCompaniesTable::class, 'archive');
        $family = $compiler->compileVariants(VariantCompaniesTable::class);

        Assert::same($active->registryKey(), 'companies');
        Assert::same($active->title(), 'Companies');
        Assert::same($active->source()->countSource(), 'active_company_counts');
        Assert::same($active->pagination()->defaultPerPage(), 20);
        Assert::same($active->defaultSort()?->columnKey(), 'name');
        Assert::same(
            \array_map(static fn ($column): string => $column->key(), $active->columns()->all()),
            ['id', 'name', 'activeNote']
        );

        Assert::same($archive->registryKey(), 'companies_archive');
        Assert::same($archive->title(), 'Archived companies');
        Assert::same($archive->source()->countSource(), 'archive_company_counts');
        Assert::same($archive->pagination()->defaultPerPage(), 50);
        Assert::same($archive->defaultSort()?->columnKey(), 'deletedAt');
        Assert::same($archive->defaultSort()->direction(), 'desc');
        Assert::same(
            \array_map(static fn ($column): string => $column->key(), $archive->columns()->all()),
            ['id', 'name', 'deletedAt']
        );

        Assert::same(\array_keys($family->all()), ['active', 'archive']);
        Assert::same($family->require('active')->registryKey(), 'companies');
        Assert::same($family->require('archive')->registryKey(), 'companies_archive');

        $sqlCompiler = new PostgresRegistrySqlCompiler();
        Assert::string($sqlCompiler->compileRows($active)->sql())
            ->contains('("company"."deleted_at" IS NULL)');
        $archiveSql = $sqlCompiler->compileRows($archive)->sql();
        Assert::string($archiveSql)->contains('("company"."deleted_at" IS NOT NULL)');
        Assert::false(\str_contains($archiveSql, '("company"."deleted_at" IS NULL)'));
    }

    public function compileAllRegistersOnlyDefaultVariantAndRejectsDuplicateCatalogKey(): void
    {
        $compiler = new AttributeTableRegistryCompiler();
        $catalog = $compiler->compileAll([VariantCompaniesTable::class]);
        Assert::same(\array_keys($catalog->all()), ['companies']);

        try {
            $compiler->compileAll([VariantCompaniesTable::class, DuplicateCompaniesTable::class]);
        } catch (AttributeCompilationException $exception) {
            Assert::same($exception->issues()[0]->code(), 'catalog.duplicate_key');

            return;
        }

        throw new \LogicException('Duplicate default registry keys must be rejected.');
    }

    public function validatesDefaultSelectionAndEveryVariantIndependently(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(MissingDefaultVariantTable::class);
        } catch (AttributeCompilationException $exception) {
            Assert::same($exception->issues()[0]->code(), 'variant.invalid');
            Assert::string($exception->issues()[0]->message())->contains('exactly one default');
        }

        try {
            (new AttributeTableRegistryCompiler())->compile(InvalidArchiveVariantTable::class);
        } catch (AttributeCompilationException $exception) {
            Assert::same($exception->issues()[0]->variant(), 'archive');
            Assert::string($exception->issues()[0]->message())->contains('exactly one primary key');

            return;
        }

        throw new \LogicException('Invalid named variants must be rejected independently.');
    }
}

#[Relation('public.variant_companies')]
final class VariantCompanyRelation
{
    #[Field('company_id')]
    protected int $id;

    #[Field]
    protected string $name;

    #[Field('deleted_at', nullable: true)]
    protected ?string $deletedAt;

    #[Field('active_note')]
    protected string $activeNote;
}

#[Variant('active', 'companies', 'Companies', isDefault: true)]
#[Variant('archive', 'companies_archive', 'Archived companies')]
#[From(VariantCompanyRelation::class, 'company')]
#[CountSource('active_company_counts')]
#[CountSource('archive_company_counts', 'archive')]
#[Pagination(defaultPerPage: 20)]
#[Pagination(defaultPerPage: 50, variant: 'archive')]
#[DefaultSort('name')]
#[DefaultSort('deletedAt', 'desc', 'archive')]
#[SqlConstraint('"company"."deleted_at" IS NULL')]
#[SqlConstraint('"company"."deleted_at" IS NOT NULL', variant: 'archive')]
final class VariantCompaniesTable
{
    #[Column('ID', 10)]
    #[Project('company')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Name', 20)]
    #[Project('company')]
    #[Sort]
    protected string $name;

    #[Column('Deleted at', 30)]
    #[Project('company')]
    #[Sort]
    #[OnlyInVariant('archive')]
    protected ?string $deletedAt;

    #[Column('Active note', 40)]
    #[Project('company')]
    #[ExcludeFromVariant('archive')]
    protected string $activeNote;
}

#[Table('companies', 'Duplicate companies')]
#[From(VariantCompanyRelation::class, 'company')]
final class DuplicateCompaniesTable
{
    #[Column('ID', 10)]
    #[Project('company')]
    #[PrimaryKey]
    protected int $id;
}

#[Variant('active', 'missing_default_active', 'Active')]
#[Variant('archive', 'missing_default_archive', 'Archive')]
#[From(VariantCompanyRelation::class, 'company')]
final class MissingDefaultVariantTable
{
    #[Column('ID', 10)]
    #[Project('company')]
    #[PrimaryKey]
    protected int $id;
}

#[Variant('active', 'valid_active', 'Active', isDefault: true)]
#[Variant('archive', 'invalid_archive', 'Archive')]
#[From(VariantCompanyRelation::class, 'company')]
final class InvalidArchiveVariantTable
{
    #[Column('ID', 10)]
    #[Project('company')]
    #[PrimaryKey]
    #[ExcludeFromVariant('archive')]
    protected int $id;

    #[Column('Name', 20)]
    #[Project('company')]
    protected string $name;
}
