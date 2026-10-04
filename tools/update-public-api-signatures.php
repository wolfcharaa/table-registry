<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Tests\Contract\Support\PublicApiInspector;

$rootDirectory = dirname(__DIR__);

require $rootDirectory . '/vendor/autoload.php';

$inspector = new PublicApiInspector($rootDirectory);
$target = $rootDirectory . '/tests/Contract/fixtures/public-api-signatures.json';
$json = json_encode(
    $inspector->snapshot(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);

if (file_put_contents($target, $json . PHP_EOL) === false) {
    throw new RuntimeException(sprintf('Cannot write %s.', $target));
}

fwrite(STDOUT, sprintf("Updated %s\n", $target));
