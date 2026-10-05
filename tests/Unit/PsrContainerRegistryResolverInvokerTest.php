<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnValueLabelResolverInterface;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Runtime\PsrContainerRegistryResolverInvoker;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Runtime\PsrContainerResolverInvoker;
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeConstraintInterface;
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeContext;
use Wolfcharaa\TableRegistry\Select\Sql\PostgresSelectCompiler;
use Wolfcharaa\TableRegistry\Select\Sql\SelectCondition;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

#[Test]
final class PsrContainerRegistryResolverInvokerTest extends RegistryTestCase
{
    public function resolvesInvokableClassFromPsrContainer(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'tasks',
            title: 'Задачи',
            source: RegistrySourceDefinition::from('public.tasks'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                (new ColumnRegistryDefinition('state', 'Статус'))
                    ->withLabel(ContainerStateLabelResolver::class),
            ]
        );

        $projector = new RegistryRowsProjector(new PsrContainerRegistryResolverInvoker(
            new ArrayContainer([
                ContainerStateLabelResolver::class => new ContainerStateLabelResolver(),
            ])
        ));

        $rows = $projector->project($definition, [
            ['id' => 1, 'state' => 'ready'],
        ]);

        Assert::same($rows[0]['values']['state']['label'], 'Готово');
    }

    public function resolvesStandaloneSelectConstraintFromPsrContainer(): void
    {
        $definition = SelectDefinition::from(
            SelectSourceDefinition::relation('document', 'd'),
            [SelectFieldDefinition::column('d.id', 'id')]
        )->withRuntimeConstraint(ContainerAreaConstraint::class);

        $compiler = new PostgresSelectCompiler(new PsrContainerResolverInvoker(new ArrayContainer([
            ContainerAreaConstraint::class => new ContainerAreaConstraint(77),
        ])));
        $compiled = $compiler->compile($definition);

        Assert::string($compiled->sql())->contains('(d.area_id = :p0)');
        Assert::same($compiled->params(), ['p0' => 77]);
    }
}

final class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(private readonly array $services)
    {
    }

    public function get($id): object
    {
        return $this->services[$id] ?? throw new \RuntimeException('Service not found.');
    }

    public function has($id): bool
    {
        return isset($this->services[$id]);
    }
}

final class ContainerStateLabelResolver implements ColumnValueLabelResolverInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): string {
        return $value->getValue() === 'ready' ? 'Готово' : 'Неизвестно';
    }
}

final class ContainerAreaConstraint implements SelectRuntimeConstraintInterface
{
    public function __construct(private readonly int $areaId)
    {
    }

    public function __invoke(SelectRuntimeContext $context): SelectCondition
    {
        return SelectCondition::sql('d.area_id = ' . $context->param($this->areaId));
    }
}
