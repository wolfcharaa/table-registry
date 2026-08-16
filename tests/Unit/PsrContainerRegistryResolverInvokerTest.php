<?php

declare(strict_types=1);

namespace TableRegistry\Tests\Unit;

use Psr\Container\ContainerInterface;
use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\ColumnValueLabelResolverInterface;
use TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Row\RegistryRowData;
use TableRegistry\Row\RegistryRowsProjector;
use TableRegistry\Runtime\PsrContainerRegistryResolverInvoker;
use TableRegistry\Source\RegistrySourceDefinition;
use TableRegistry\Value\PrimitiveValueObjectInterface;

final class PsrContainerRegistryResolverInvokerTest extends RegistryTestCase
{
    public function testResolvesInvokableClassFromPsrContainer(): void
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

        self::assertSame('Готово', $rows[0]['values']['state']['label']);
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

