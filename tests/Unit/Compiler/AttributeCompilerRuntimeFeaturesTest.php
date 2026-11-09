<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Compiler;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\ExternalField;
use Wolfcharaa\TableRegistry\Attribute\ExternalRows;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\HydrateWith;
use Wolfcharaa\TableRegistry\Attribute\Internal;
use Wolfcharaa\TableRegistry\Attribute\LabelFrom;
use Wolfcharaa\TableRegistry\Attribute\LabelWith;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\RawType;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\RelationSourceProvider;
use Wolfcharaa\TableRegistry\Attribute\SqlConstraint;
use Wolfcharaa\TableRegistry\Attribute\SqlExpression;
use Wolfcharaa\TableRegistry\Attribute\SqlExpressionProvider;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnValueHydratorInterface;
use Wolfcharaa\TableRegistry\Column\ColumnValueLabelResolverInterface;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Compiler\RelationSourceProviderInterface;
use Wolfcharaa\TableRegistry\Compiler\SqlExpressionProviderInterface;
use Wolfcharaa\TableRegistry\Exception\AttributeCompilationException;
use Wolfcharaa\TableRegistry\Exception\UnsupportedRegistrySourceException;
use Wolfcharaa\TableRegistry\Metadata\RelationSourceMetadata;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;
use Wolfcharaa\TableRegistry\Value\StringValue;

#[Test]
final class AttributeCompilerRuntimeFeaturesTest
{
    public function compilesInternalInputsScopedResolversHydrationAndStaticConstraint(): void
    {
        $definition = (new AttributeTableRegistryCompiler())->compile(DecoratedUsersTable::class);

        Assert::same(
            \array_map(static fn ($input): string => $input->key(), $definition->rowInputs()),
            ['prefix']
        );
        Assert::same(\array_keys($definition->jsonSerialize()['columns'][0]), [
            'key',
            'title',
            'searchable',
            'sortable',
            'filterable',
        ]);

        $compiled = (new PostgresRegistrySqlCompiler())->compileRows($definition);
        Assert::string($compiled->sql())->contains("'#' AS \"prefix\"");
        Assert::string($compiled->sql())->contains('("user"."tenant_id" = :p0)');
        Assert::same($compiled->params(), ['p0' => 42]);

        $rows = (new RegistryRowsProjector())->project($definition, [[
            'id' => 7,
            'prefix' => '#',
        ]]);
        Assert::same($rows[0]['values']['id'], [
            'value' => '#7',
            'label' => '#:7',
        ]);
        Assert::false(isset($rows[0]['values']['prefix']));
    }

    public function mapsExternalProviderKeysAndKeepsInternalValuesPrivate(): void
    {
        $definition = (new AttributeTableRegistryCompiler())->compile(ExternalHistoryTable::class);

        Assert::true($definition->source()->isExternal());
        Assert::same($definition->source()->externalKey(), 'object_history');
        $id = $definition->columns()->get('id');
        if ($id === null) {
            throw new \LogicException('External history ID column must exist.');
        }
        Assert::same($id->rawRowKey(), 'object_id');
        Assert::same($definition->rowInputs()[0]->key(), 'actor_name');

        $rows = (new RegistryRowsProjector())->project($definition, [[
            'object_id' => 15,
            'actor_id' => 9,
            'actor_name' => 'Ada',
        ]]);
        Assert::same($rows[0]['primaryKey'], '15');
        Assert::same($rows[0]['values']['id']['value'], 15);
        Assert::same($rows[0]['values']['actorId']['label'], 'Ada');
        Assert::false(isset($rows[0]['values']['actor_name']));

        try {
            (new PostgresRegistrySqlCompiler())->compileRows($definition);
        } catch (UnsupportedRegistrySourceException) {
            return;
        }

        throw new \LogicException('External Attribute aggregate must not compile as SQL.');
    }

    public function invokesCompileTimeProviderOutputOncePerCompilationSession(): void
    {
        CountingRelationSourceProvider::$invocations = 0;
        CountingSqlExpressionProvider::$invocations = 0;

        $definition = (new AttributeTableRegistryCompiler())->compile(ProviderBackedTable::class);
        $compiled = (new PostgresRegistrySqlCompiler())->compileRows($definition);

        Assert::same(CountingRelationSourceProvider::$invocations, 1);
        Assert::same(CountingSqlExpressionProvider::$invocations, 1);
        Assert::string($compiled->sql())->contains('FROM "tenant"."provider_users" AS "user"');
        Assert::string($compiled->sql())->contains("'provided' AS \"first\"");
        Assert::string($compiled->sql())->contains("'provided' AS \"second\"");
    }

    public function requiresRawTypeForHydratedComputedInput(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(MissingHydrationRawTypeTable::class);
        } catch (AttributeCompilationException $exception) {
            Assert::string($exception->issues()[0]->message())->contains('must declare RawType');

            return;
        }

        throw new \LogicException('Hydrated computed input without RawType must be rejected.');
    }

    public function rejectsConcreteIncompatibleHydratorEvenWhenUnchecked(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(IncompatibleUncheckedHydrationTable::class);
        } catch (AttributeCompilationException $exception) {
            Assert::string($exception->issues()[0]->message())->contains('return type is not assignable');

            return;
        }

        throw new \LogicException('Concrete incompatible hydrator return type must be rejected.');
    }

    public function requiresUncheckedForMixedHydratorReturnType(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(CheckedMixedHydrationTable::class);
        } catch (AttributeCompilationException $exception) {
            Assert::string($exception->issues()[0]->message())->contains('set unchecked');
            $definition = (new AttributeTableRegistryCompiler())->compile(UncheckedMixedHydrationTable::class);
            Assert::same(\count($definition->columns()->all()), 2);

            return;
        }

        throw new \LogicException('Mixed hydrator return type without unchecked must be rejected.');
    }
}

#[Relation('public.decorated_users')]
final class DecoratedUserRelation
{
    #[Field('user_id')]
    protected int $id;
}

#[Table('decorated_users', 'Decorated users')]
#[From(DecoratedUserRelation::class, 'user')]
#[SqlConstraint('"user"."tenant_id" = :tenant', ['tenant' => 42])]
final class DecoratedUsersTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    #[HydrateWith(PrefixedIdHydrator::class, ['prefix'])]
    #[LabelWith(PrefixedIdLabel::class, ['prefix'])]
    protected string $id;

    #[Internal]
    #[SqlExpression("'#'")]
    protected string $prefix;
}

final class PrefixedIdHydrator implements ColumnValueHydratorInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): string {
        $prefix = $row->get('prefix');
        $identifier = $value->getValue();
        if (!\is_string($prefix) || !\is_int($identifier)) {
            throw new \LogicException('Prefixed ID hydration expects a string prefix and an integer identifier.');
        }

        return $prefix . $identifier;
    }
}

final class PrefixedIdLabel implements ColumnValueLabelResolverInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): string {
        $prefix = $row->get('prefix');
        $identifier = $value->getValue();
        if (!\is_string($prefix) || !\is_int($identifier)) {
            throw new \LogicException('Prefixed ID label expects a string prefix and an integer identifier.');
        }

        return $prefix . ':' . $identifier;
    }
}

#[Table('external_history', 'History')]
#[ExternalRows('object_history')]
final class ExternalHistoryTable
{
    #[Column('ID', 10)]
    #[ExternalField('object_id')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Actor', 20)]
    #[ExternalField('actor_id')]
    #[LabelFrom('actorName')]
    protected int $actorId;

    #[Internal]
    #[ExternalField('actor_name')]
    protected string $actorName;
}

#[RelationSourceProvider(CountingRelationSourceProvider::class)]
final class ProviderBackedRelation
{
    #[Field('user_id')]
    protected int $id;
}

#[Table('provider_backed', 'Provider backed')]
#[From(ProviderBackedRelation::class, 'user')]
final class ProviderBackedTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('First', 20)]
    #[SqlExpressionProvider(CountingSqlExpressionProvider::class)]
    protected string $first;

    #[Column('Second', 30)]
    #[SqlExpressionProvider(CountingSqlExpressionProvider::class)]
    protected string $second;
}

final class CountingRelationSourceProvider implements RelationSourceProviderInterface
{
    public static int $invocations = 0;

    public function __invoke(): RelationSourceMetadata
    {
        ++self::$invocations;

        return RelationSourceMetadata::relation('tenant.provider_users');
    }
}

final class CountingSqlExpressionProvider implements SqlExpressionProviderInterface
{
    public static int $invocations = 0;

    public function __invoke(): string
    {
        ++self::$invocations;

        return "'provided'";
    }
}

#[Table('missing_hydration_raw_type', 'Missing hydration raw type')]
#[From(DecoratedUserRelation::class, 'user')]
final class MissingHydrationRawTypeTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Computed', 20)]
    #[SqlExpression("'value'")]
    #[HydrateWith(StringHydrator::class)]
    protected string $computed;
}

#[Table('incompatible_unchecked_hydration', 'Incompatible unchecked hydration')]
#[From(DecoratedUserRelation::class, 'user')]
final class IncompatibleUncheckedHydrationTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Computed', 20)]
    #[SqlExpression("'value'")]
    #[RawType(StringValue::class)]
    #[HydrateWith(StringHydrator::class, unchecked: true)]
    protected int $computed;
}

#[Table('checked_mixed_hydration', 'Checked mixed hydration')]
#[From(DecoratedUserRelation::class, 'user')]
final class CheckedMixedHydrationTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Computed', 20)]
    #[SqlExpression("'value'")]
    #[RawType(StringValue::class)]
    #[HydrateWith(MixedHydrator::class)]
    protected string $computed;
}

#[Table('unchecked_mixed_hydration', 'Unchecked mixed hydration')]
#[From(DecoratedUserRelation::class, 'user')]
final class UncheckedMixedHydrationTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Computed', 20)]
    #[SqlExpression("'value'")]
    #[RawType(StringValue::class)]
    #[HydrateWith(MixedHydrator::class, unchecked: true)]
    protected string $computed;
}

final class StringHydrator implements ColumnValueHydratorInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): string {
        return 'hydrated';
    }
}

final class MixedHydrator implements ColumnValueHydratorInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): mixed {
        return $value->getValue();
    }
}
