<?php

declare(strict_types=1);

$rootDirectory = \dirname(__DIR__);
$examples = \glob($rootDirectory . '/docs/examples/*.php');
if ($examples === false || $examples === []) {
    throw new \RuntimeException('No documentation examples found.');
}

\sort($examples, SORT_STRING);

foreach ($examples as $example) {
    $result = runExample($example, $rootDirectory);
    $failure = $result['exitCode'] === 0 ? null : \sprintf('Exited with code %d.', $result['exitCode']);
    $xlsxPath = null;

    if (\basename($example) === '05-xlsx-export.php') {
        $xlsxPath = exportedXlsxPath($result['stdout']);
        if ($xlsxPath === null) {
            $failure ??= 'The XLSX example did not report its generated file.';
        } elseif (!\is_file($xlsxPath) || \filesize($xlsxPath) === 0) {
            $failure ??= 'The XLSX example did not create a non-empty file.';
        }
    }

    if ($xlsxPath !== null) {
        $cleanupError = removeGeneratedXlsx($xlsxPath);
        $failure ??= $cleanupError;
    }

    if ($failure !== null) {
        \fwrite(STDERR, \sprintf("Example %s failed: %s\n", \basename($example), $failure));
        if ($result['stdout'] !== '') {
            \fwrite(STDERR, "--- stdout ---\n" . $result['stdout']);
        }
        if ($result['stderr'] !== '') {
            \fwrite(STDERR, "--- stderr ---\n" . $result['stderr']);
        }

        exit(1);
    }

    \fwrite(STDOUT, \sprintf("OK %s\n", \basename($example)));
}

/**
 * @return array{exitCode:int,stdout:string,stderr:string}
 */
function runExample(string $example, string $workingDirectory): array
{
    $pipes = [];
    $process = \proc_open(
        [PHP_BINARY, $example],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $workingDirectory
    );

    if (!\is_resource($process)
        || !isset($pipes[0], $pipes[1], $pipes[2])
        || !\is_resource($pipes[0])
        || !\is_resource($pipes[1])
        || !\is_resource($pipes[2])
    ) {
        throw new \RuntimeException(\sprintf('Cannot start example %s.', \basename($example)));
    }

    \fclose($pipes[0]);
    $stdout = \stream_get_contents($pipes[1]);
    $stderr = \stream_get_contents($pipes[2]);
    \fclose($pipes[1]);
    \fclose($pipes[2]);

    return [
        'exitCode' => \proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

function exportedXlsxPath(string $stdout): ?string
{
    if (\preg_match('/^File path: (.+)$/m', $stdout, $matches) !== 1) {
        return null;
    }

    $path = \trim($matches[1]);

    return $path !== '' ? $path : null;
}

function removeGeneratedXlsx(string $filePath): ?string
{
    $temporaryDirectory = \realpath(\sys_get_temp_dir());
    $realPath = \realpath($filePath);
    if ($temporaryDirectory === false
        || $realPath === false
        || !\str_starts_with($realPath, $temporaryDirectory . DIRECTORY_SEPARATOR)
        || !\str_ends_with($realPath, '.xlsx')
    ) {
        return 'Refused to remove an XLSX file outside the system temporary directory.';
    }

    if (!\unlink($realPath)) {
        return \sprintf('Cannot remove generated XLSX file %s.', $realPath);
    }

    return null;
}
