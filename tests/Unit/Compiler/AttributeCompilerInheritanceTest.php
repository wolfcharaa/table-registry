<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Compiler;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\ExcludeField;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\MappedFields;
use Wolfcharaa\TableRegistry\Attribute\OverrideField;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Exception\AttributeCompilationException;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;

#[Test]
final class AttributeCompilerInheritanceTest
{
    public function importsOnlyExplicitMappedSourcesAndAppliesDirectives(): void
    {
        $definition = (new AttributeTableRegistryCompiler())->compile(InheritedRecordsTable::class);
        $sql = (new PostgresRegistrySqlCompiler())->compileRows($definition)->sql();

        Assert::string($sql)->contains('"record"."record_id" AS "id"');
        Assert::string($sql)->contains('"record"."created_at" AS "createdAt"');
        Assert::false(\str_contains($sql, 'legacy_code'));
    }

    public function ignoresUnmarkedParentFields(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(UnmarkedInheritedTable::class);
        } catch (AttributeCompilationException $exception) {
            Assert::same($exception->issues()[0]->code(), 'field.invalid');
            Assert::string($exception->issues()[0]->message())->contains('unknown field');

            return;
        }

        throw new \LogicException('Unmarked parent fields must not be imported.');
    }

    public function rejectsConflictingExplicitMappedSources(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(ConflictingInheritedTable::class);
        } catch (AttributeCompilationException $exception) {
            Assert::same($exception->issues()[0]->code(), 'inheritance.invalid');
            Assert::string($exception->issues()[0]->message())->contains('Duplicate mapped relation field');

            return;
        }

        throw new \LogicException('Conflicting mapped-field sources must be rejected.');
    }
}

#[MappedFields]
abstract class InheritedRecordFields
{
    #[Field('id')]
    protected int $id;

    #[Field('legacy_code')]
    protected string $legacyCode;
}

#[MappedFields]
trait InheritedTimestampFields
{
    #[Field('created_at')]
    protected string $createdAt;
}

#[Relation('public.inherited_records')]
#[OverrideField('id', column: 'record_id')]
#[ExcludeField('legacyCode')]
final class InheritedRecordRelation extends InheritedRecordFields
{
    use InheritedTimestampFields;
}

#[Table('inherited_records', 'Inherited records')]
#[From(InheritedRecordRelation::class, 'record')]
final class InheritedRecordsTable
{
    #[Column('ID', 10)]
    #[Project('record')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Created at', 20)]
    #[Project('record')]
    protected string $createdAt;
}

abstract class UnmarkedRelationParent
{
    #[Field('hidden_id')]
    protected int $hiddenId;
}

#[Relation('public.unmarked_inheritance')]
final class UnmarkedInheritedRelation extends UnmarkedRelationParent
{
    #[Field('record_id')]
    protected int $id;
}

#[Table('unmarked_inheritance', 'Unmarked inheritance')]
#[From(UnmarkedInheritedRelation::class, 'record')]
final class UnmarkedInheritedTable
{
    #[Column('ID', 10)]
    #[Project('record', 'hiddenId')]
    #[PrimaryKey]
    protected int $id;
}

#[MappedFields]
abstract class ConflictingMappedParent
{
    #[Field('parent_id')]
    protected int $id;
}

#[MappedFields]
trait ConflictingMappedTrait
{
    #[Field('trait_id')]
    protected int $id;
}

#[Relation('public.conflicting_inheritance')]
final class ConflictingInheritedRelation extends ConflictingMappedParent
{
    use ConflictingMappedTrait;
}

#[Table('conflicting_inheritance', 'Conflicting inheritance')]
#[From(ConflictingInheritedRelation::class, 'record')]
final class ConflictingInheritedTable
{
    #[Column('ID', 10)]
    #[Project('record')]
    #[PrimaryKey]
    protected int $id;
}
