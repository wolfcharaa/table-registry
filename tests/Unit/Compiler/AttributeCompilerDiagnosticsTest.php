<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Compiler;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Exception\AttributeCompilationException;

#[Test]
final class AttributeCompilerDiagnosticsTest
{
    public function aggregatesIndependentCompileAllFailuresInInputOrder(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compileAll([
                InvalidAliasAggregate::class,
                MissingPrimaryKeyAggregate::class,
            ]);
        } catch (AttributeCompilationException $exception) {
            Assert::same(
                \array_map(static fn ($issue): string => $issue->code(), $exception->issues()),
                ['alias.invalid', 'capability.invalid']
            );
            Assert::same(
                \array_map(static fn ($issue): string => $issue->consumer(), $exception->issues()),
                [InvalidAliasAggregate::class, MissingPrimaryKeyAggregate::class]
            );

            return;
        }

        throw new \LogicException('compileAll must aggregate independent failures.');
    }

    public function rejectsConflictingAggregateRole(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(ConflictingRoleAggregate::class);
        } catch (AttributeCompilationException $exception) {
            Assert::same($exception->issues()[0]->code(), 'role.conflict');

            return;
        }

        throw new \LogicException('Conflicting aggregate role must be rejected.');
    }
}

#[Relation('public.diagnostic_records')]
final class DiagnosticRelation
{
    #[Field('record_id')]
    protected int $id;
}

#[Table('invalid_alias', 'Invalid alias')]
#[From(DiagnosticRelation::class, 'invalid-alias')]
final class InvalidAliasAggregate
{
    #[Column('ID', 10)]
    #[Project('invalid-alias')]
    protected int $id;
}

#[Table('missing_primary_key', 'Missing primary key')]
#[From(DiagnosticRelation::class, 'record')]
final class MissingPrimaryKeyAggregate
{
    #[Column('ID', 10)]
    #[Project('record')]
    protected int $id;
}

#[Table('conflicting_role', 'Conflicting role')]
#[Relation('public.conflicting_role')]
#[From(DiagnosticRelation::class, 'record')]
final class ConflictingRoleAggregate
{
    #[Column('ID', 10)]
    #[Project('record')]
    protected int $id;
}
