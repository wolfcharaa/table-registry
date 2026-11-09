<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\OnlyInVariant;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Variant;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

#[Relation('public.variant_orders')]
final class VariantExampleOrder
{
    #[Field('order_id')]
    protected int $id;

    #[Field('deleted_at', nullable: true)]
    protected ?string $deletedAt;
}

#[Variant('active', 'orders', 'Заказы', isDefault: true)]
#[Variant('archive', 'orders_archive', 'Архив заказов')]
#[From(VariantExampleOrder::class, 'order')]
final class VariantExampleOrdersTable
{
    #[Column('ID', 10)]
    #[Project('order')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Удалён', 20)]
    #[Project('order')]
    #[OnlyInVariant('archive')]
    protected ?string $deletedAt;
}

$compiler = new AttributeTableRegistryCompiler();
$default = $compiler->compile(VariantExampleOrdersTable::class);
$family = $compiler->compileVariants(VariantExampleOrdersTable::class);

if ($default->registryKey() !== 'orders' || count($family) !== 2) {
    throw new RuntimeException('Unexpected Attribute variant family.');
}

echo sprintf(
    "default=%s variants=%s archive_columns=%d\n",
    $default->registryKey(),
    implode(',', array_keys($family->all())),
    count($family->require('archive')->columns()->all())
);
