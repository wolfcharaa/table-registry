<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnValueLabelResolverInterface;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Runtime\PsrContainerRegistryResolverInvoker;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class StatusLabelResolver implements ColumnValueLabelResolverInterface
{
    public function __invoke(
        PrimitiveValueObjectInterface $value,
        RegistryRowData $row,
        ColumnRegistryDefinition $column
    ): string {
        return match ($value->getValue()) {
            'ready' => 'Готово',
            'failed' => 'Ошибка',
            default => 'Неизвестно',
        };
    }
}

final class ExampleContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $services;

    public function __construct()
    {
        $this->services = [
            StatusLabelResolver::class => new StatusLabelResolver(),
        ];
    }

    public function get($id): object
    {
        return $this->services[$id] ?? throw new RuntimeException('Service not found.');
    }

    public function has($id): bool
    {
        return isset($this->services[$id]);
    }
}

$definition = new TableRegistryDefinition(
    registryKey: 'tasks',
    title: 'Задачи',
    source: RegistrySourceDefinition::from('public.tasks'),
    columns: [
        new PrimaryKeyColumnRegistryDefinition('id'),
        (new ColumnRegistryDefinition('state', 'Статус'))
            ->withLabel(StatusLabelResolver::class),
    ]
);

$projector = new RegistryRowsProjector(
    new PsrContainerRegistryResolverInvoker(new ExampleContainer())
);

$rows = $projector->project($definition, [
    ['id' => 1, 'state' => 'ready'],
]);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

