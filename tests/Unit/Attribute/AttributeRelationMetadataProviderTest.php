<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Attribute;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\ExcludeField;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\MappedFields;
use Wolfcharaa\TableRegistry\Attribute\OverrideField;
use Wolfcharaa\TableRegistry\Attribute\RawRelation;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\RelationSourceProvider;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Metadata\AttributeRelationMetadataProvider;
use Wolfcharaa\TableRegistry\Metadata\RelationFieldMetadata;
use Wolfcharaa\TableRegistry\Metadata\RelationMetadata;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\IntegerValue;
use Wolfcharaa\TableRegistry\Value\JsonListValue;
use Wolfcharaa\TableRegistry\Value\RawValue;
use Wolfcharaa\TableRegistry\Value\StringValue;

#[Test]
final class AttributeRelationMetadataProviderTest
{
    public function readsRelationAndExplicitMappedFieldsWithoutInstantiatingModel(): void
    {
        $metadata = $this->metadata(UserRelation::class);

        Assert::true($metadata->source()->isRelation());
        Assert::same($metadata->source()->relationIdentifier(), 'public.users');
        Assert::same($metadata->source()->connection(), 'reporting');
        Assert::same(\array_map(
            static fn ($field): string => $field->name(),
            $metadata->fields()
        ), ['id', 'createdAt', 'tags', 'email', 'state']);

        $id = $this->field($metadata, 'id');
        $createdAt = $this->field($metadata, 'createdAt');
        $tags = $this->field($metadata, 'tags');
        $state = $this->field($metadata, 'state');

        Assert::same($id->column(), 'user_id');
        Assert::same($id->rowType(), IntegerValue::class);
        Assert::false($id->nullable());
        Assert::same($createdAt->rowType(), DateTimeValue::class);
        Assert::same($tags->rowType(), JsonListValue::class);
        Assert::same($state->rowType(), StringValue::class);
        Assert::same($metadata->field('legacyCode'), null);
    }

    public function keepsRawAndProviderBackedSourcesDistinct(): void
    {
        $provider = new AttributeRelationMetadataProvider();
        $raw = $this->metadata(ArchivedUserRelation::class, $provider);
        $provided = $this->metadata(ProvidedUserRelation::class, $provider);

        Assert::true($raw->source()->isRaw());
        Assert::same($raw->source()->rawSql(), '(SELECT * FROM public.users WHERE deleted_at IS NOT NULL)');
        Assert::same($raw->source()->connection(), 'archive');
        Assert::true($provided->source()->isProvided());
        Assert::same($provided->source()->provider(), ConfiguredUserSource::class);
        Assert::same($provided->source()->connection(), null);
    }

    public function acceptsExplicitRowTypeForAmbiguousProperty(): void
    {
        $metadata = $this->metadata(MixedPayloadRelation::class);
        $payload = $this->field($metadata, 'payload');

        Assert::same($payload->rowType(), RawValue::class);
        Assert::true($payload->nullable());
    }

    public function ignoresFieldsFromUnmarkedParent(): void
    {
        $metadata = $this->metadata(RelationWithUnmarkedParent::class);

        Assert::same(\array_map(
            static fn ($field): string => $field->name(),
            $metadata->fields()
        ), ['id']);
    }

    public function returnsNullForClassWithoutRelationSource(): void
    {
        Assert::same((new AttributeRelationMetadataProvider())->metadata(UnmappedClass::class), null);
    }

    public function rejectsAmbiguousFieldTypeWithoutExplicitMapping(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

        (new AttributeRelationMetadataProvider())->metadata(AmbiguousFieldRelation::class);
    }

    public function rejectsRelationThatIsAlsoMappedFieldSource(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

        (new AttributeRelationMetadataProvider())->metadata(ConflictingRoleRelation::class);
    }

    /**
     * @param class-string $class
     */
    private function metadata(
        string $class,
        ?AttributeRelationMetadataProvider $provider = null
    ): RelationMetadata {
        $metadata = ($provider ?? new AttributeRelationMetadataProvider())->metadata($class);
        if ($metadata === null) {
            throw new \LogicException(\sprintf('%s metadata must be available.', $class));
        }

        return $metadata;
    }

    private function field(RelationMetadata $metadata, string $name): RelationFieldMetadata
    {
        $field = $metadata->field($name);
        if ($field === null) {
            throw new \LogicException(\sprintf('Field "%s" metadata must be available.', $name));
        }

        return $field;
    }
}

#[MappedFields]
abstract class IdentifiedRecord
{
    #[Field('id')]
    protected int $id;

    #[Field('legacy_code')]
    protected ?string $legacyCode;
}

#[MappedFields]
trait TimestampFields
{
    #[Field('created_at')]
    protected \DateTimeImmutable $createdAt;

    /** @var list<string> */
    #[Field('tags')]
    protected array $tags;
}

#[Relation('public.users', connection: 'reporting')]
#[OverrideField('id', column: 'user_id')]
#[ExcludeField('legacyCode')]
final class UserRelation extends IdentifiedRecord
{
    use TimestampFields;

    #[Field('email_address')]
    protected string $email;

    #[Field('state')]
    protected UserState $state;

    public function __construct()
    {
        throw new \LogicException('Metadata provider must not instantiate relation models.');
    }
}

enum UserState: string
{
    case Active = 'active';
}

#[RawRelation('(SELECT * FROM public.users WHERE deleted_at IS NOT NULL)', connection: 'archive')]
final class ArchivedUserRelation
{
    #[Field]
    protected int $id;
}

#[RelationSourceProvider(ConfiguredUserSource::class)]
final class ProvidedUserRelation
{
    #[Field]
    protected int $id;
}

final class ConfiguredUserSource
{
}

#[Relation('public.payloads')]
final class MixedPayloadRelation
{
    #[Field(rowType: RawValue::class, nullable: true)]
    protected mixed $payload;
}

class UnmarkedParent
{
    #[Field]
    protected string $ignored;
}

#[Relation('public.children')]
final class RelationWithUnmarkedParent extends UnmarkedParent
{
    #[Field]
    protected int $id;
}

final class UnmappedClass
{
}

#[Relation('public.invalid')]
final class AmbiguousFieldRelation
{
    #[Field]
    protected int|string $ambiguous;
}

#[Relation('public.conflicting')]
#[MappedFields]
final class ConflictingRoleRelation
{
    #[Field]
    protected int $id;
}
