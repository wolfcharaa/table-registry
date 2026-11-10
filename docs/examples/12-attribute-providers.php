<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\RelationSourceProvider;
use Wolfcharaa\TableRegistry\Attribute\SqlExpressionProvider;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Compiler\PsrContainerCompileTimeProviderResolver;
use Wolfcharaa\TableRegistry\Compiler\RelationSourceProviderInterface;
use Wolfcharaa\TableRegistry\Compiler\SqlExpressionProviderInterface;
use Wolfcharaa\TableRegistry\Metadata\RelationSourceMetadata;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final readonly class ProviderExampleConfig
{
    public function __construct(public string $schema)
    {
    }
}

final class UserSourceProvider implements RelationSourceProviderInterface
{
    public function __construct(private readonly ProviderExampleConfig $config)
    {
    }

    public function __invoke(): RelationSourceMetadata
    {
        return RelationSourceMetadata::relation($this->config->schema . '.users');
    }
}

final class DisplayNameExpressionProvider implements SqlExpressionProviderInterface
{
    public function __invoke(): string
    {
        return 'COALESCE("user"."display_name", "user"."email_address")';
    }
}

#[RelationSourceProvider(UserSourceProvider::class)]
final class ProviderUserRelation
{
    #[Field('user_id')]
    protected int $id = 0;

    #[Field('email_address')]
    protected string $email = '';
}

#[Table('provider_users', 'Пользователи')]
#[From(ProviderUserRelation::class, 'user')]
final class ProviderUsersTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id = 0;

    #[Column('Email', 20)]
    #[Project('user')]
    protected string $email = '';

    #[Column('Отображаемое имя', 30)]
    #[SqlExpressionProvider(DisplayNameExpressionProvider::class)]
    protected string $displayName = '';
}

final class ProviderExampleContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $services;

    public function __construct()
    {
        $config = new ProviderExampleConfig('tenant');
        $this->services = [
            UserSourceProvider::class => new UserSourceProvider($config),
            DisplayNameExpressionProvider::class => new DisplayNameExpressionProvider(),
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

$compiler = new AttributeTableRegistryCompiler(
    providers: new PsrContainerCompileTimeProviderResolver(new ProviderExampleContainer())
);
$definition = $compiler->compile(ProviderUsersTable::class);

echo $definition->source()->fromSql() . PHP_EOL;
echo $definition->columns()->get('displayName')?->select()?->expression() . PHP_EOL;
