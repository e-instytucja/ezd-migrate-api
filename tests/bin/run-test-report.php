<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

foreach (['storage/test-reports', 'public/test-reports'] as $relativeDir) {
    $dir = $projectRoot . '/' . $relativeDir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$phpunit = $projectRoot . '/vendor/bin/phpunit';
$junitPath = $projectRoot . '/storage/test-reports/junit.xml';

if (!is_file($phpunit)) {
    fwrite(STDERR, "Brak vendor/bin/phpunit — uruchom composer install.\n");
    exit(1);
}

$args = array_slice($_SERVER['argv'], 1);
$testPath = $args[0] ?? 'tests/Feature/Api';

$cmd = sprintf(
    '%s --log-junit %s %s',
    escapeshellarg($phpunit),
    escapeshellarg($junitPath),
    escapeshellarg($testPath),
);

passthru($cmd, $exitCode);

require __DIR__ . '/generate-html-report.php';
generateTestHtmlReport(
    $projectRoot,
    $junitPath,
    $projectRoot . '/public/test-reports/index.html',
);

exit($exitCode);
