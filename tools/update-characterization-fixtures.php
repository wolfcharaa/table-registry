<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Row\RegistryRowsResult;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Tests\Support\LegacyRegistryFixture;

$rootDirectory = dirname(__DIR__);

require $rootDirectory . '/vendor/autoload.php';

$definition = LegacyRegistryFixture::definition();
$query = LegacyRegistryFixture::query();
$compiler = new PostgresRegistrySqlCompiler();
$rows = $compiler->compileRows($definition, $query);
$count = $compiler->compileCount($definition, $query);
$export = $compiler->compileExportRows($definition, $query);
$projectedRows = (new RegistryRowsProjector())->project($definition, LegacyRegistryFixture::databaseRows());
$rowsResult = new RegistryRowsResult($definition, $query, '123', $projectedRows);

$fixtures = [
    'table-definition.json' => LegacyRegistryFixture::json($definition),
    'rows.sql' => rtrim($rows->sql()) . PHP_EOL,
    'rows.params.json' => LegacyRegistryFixture::json($rows->params()),
    'count.sql' => rtrim($count->sql()) . PHP_EOL,
    'count.params.json' => LegacyRegistryFixture::json($count->params()),
    'export.sql' => rtrim($export->sql()) . PHP_EOL,
    'export.params.json' => LegacyRegistryFixture::json($export->params()),
    'projected-rows.json' => LegacyRegistryFixture::json($projectedRows),
    'rows-result.json' => LegacyRegistryFixture::json($rowsResult),
];

$fixtureDirectory = $rootDirectory . '/tests/Characterization/fixtures';
foreach ($fixtures as $name => $contents) {
    $path = $fixtureDirectory . '/' . $name;
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException(sprintf('Cannot write %s.', $path));
    }
    fwrite(STDOUT, sprintf("Updated %s\n", $path));
}
