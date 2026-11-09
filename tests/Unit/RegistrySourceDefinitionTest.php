<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Exception\UnsupportedRegistrySourceException;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceKind;

#[Test]
final class RegistrySourceDefinitionTest
{
    public function describesExternalSourceWithoutPretendingItIsSql(): void
    {
        $source = RegistrySourceDefinition::external('object_history');

        Assert::same($source->kind(), RegistrySourceKind::External);
        Assert::true($source->isExternal());
        Assert::false($source->isSql());
        Assert::same($source->externalKey(), 'object_history');
        Assert::same($source->joins(), []);
        Assert::same($source->countSource(), null);
    }

    public function keepsSqlSourceBehavior(): void
    {
        $source = RegistrySourceDefinition::from(' public.users u ')
            ->withConnection('reporting')
            ->withLeftJoin('public.department d', 'd.id = u.department_id')
            ->withCountSource('public.users u');

        Assert::same($source->kind(), RegistrySourceKind::Sql);
        Assert::true($source->isSql());
        Assert::false($source->isExternal());
        Assert::same($source->externalKey(), null);
        Assert::same($source->fromSql(), ' public.users u ');
        Assert::same($source->connection(), 'reporting');
        Assert::same($source->joins(), ['LEFT JOIN public.department d ON d.id = u.department_id']);
        Assert::same($source->countSource(), 'public.users u');
    }

    public function rejectsSqlReadFromExternalSource(): void
    {
        Expect::exception(UnsupportedRegistrySourceException::class);

        RegistrySourceDefinition::external('object_history')->fromSql();
    }

    public function rejectsSqlConfigurationOnExternalSource(): void
    {
        Expect::exception(UnsupportedRegistrySourceException::class);

        RegistrySourceDefinition::external('object_history')->withJoin('LEFT JOIN public.users u ON true');
    }
}
