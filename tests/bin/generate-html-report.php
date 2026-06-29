<?php

declare(strict_types=1);

/**
 * Buduje public/test-reports/index.html z pliku JUnit wygenerowanego przez PHPUnit.
 */
function generateTestHtmlReport(string $projectRoot, string $junitPath, string $htmlPath): void
{
    if (!is_file($junitPath)) {
        fwrite(STDERR, "Brak pliku JUnit: {$junitPath}\n");
        fwrite(STDERR, "Uruchom najpierw: composer test:report\n");
        exit(1);
    }

    $xml = @simplexml_load_file($junitPath);
    if ($xml === false) {
        fwrite(STDERR, "Nie można odczytać JUnit XML: {$junitPath}\n");
        exit(1);
    }

    $suites = [];
    $totals = ['tests' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0, 'time' => 0.0];

    foreach ($xml->xpath('//testsuite[@name]') as $suiteNode) {
        $name = (string) $suiteNode['name'];
        if ($name === '') {
            continue;
        }

        // Pomiń zagnieżdżone testsuite w agregacie — bierzemy liście (klasy testowe).
        $hasChildSuite = count($suiteNode->testsuite) > 0;
        if ($hasChildSuite) {
            continue;
        }

        $cases = [];
        foreach ($suiteNode->testcase as $case) {
            $status = 'passed';
            $detail = '';

            if (isset($case->failure)) {
                $status = 'failed';
                $detail = trim((string) $case->failure);
            } elseif (isset($case->error)) {
                $status = 'error';
                $detail = trim((string) $case->error);
            } elseif (isset($case->skipped)) {
                $status = 'skipped';
                $detail = trim((string) $case->skipped);
            }

            $cases[] = [
                'name' => (string) $case['name'],
                'class' => (string) ($case['classname'] ?? $case['class'] ?? ''),
                'time' => (float) ($case['time'] ?? 0),
                'status' => $status,
                'detail' => $detail,
            ];
        }

        if ($cases === []) {
            continue;
        }

        $suites[] = [
            'name' => $name,
            'cases' => $cases,
        ];

        $totals['tests'] += count($cases);
        foreach ($cases as $c) {
            match ($c['status']) {
                'failed' => $totals['failures']++,
                'error' => $totals['errors']++,
                'skipped' => $totals['skipped']++,
                default => null,
            };
            $totals['time'] += $c['time'];
        }
    }

    $passed = $totals['tests'] - $totals['failures'] - $totals['errors'] - $totals['skipped'];
    $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')))->format('Y-m-d H:i:s T');
    $coverageIndex = $projectRoot . '/public/test-reports/coverage/index.html';
    $hasCoverage = is_file($coverageIndex);

    $html = buildReportHtml($suites, $totals, $passed, $generatedAt, $hasCoverage);

    $dir = dirname($htmlPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($htmlPath, $html);
    fwrite(STDOUT, "Raport HTML: public/test-reports/index.html\n");
    fwrite(STDOUT, "Podgląd: http://localhost:8080/test-reports/\n");
}

/**
 * @param list<array{name: string, cases: list<array{name: string, class: string, time: float, status: string, detail: string}>}> $suites
 * @param array{tests: int, failures: int, errors: int, skipped: int, time: float} $totals
 */
function buildReportHtml(array $suites, array $totals, int $passed, string $generatedAt, bool $hasCoverage): string
{
    $statusLabel = static function (string $status): string {
        return match ($status) {
            'passed' => 'OK',
            'failed' => 'FAIL',
            'error' => 'ERROR',
            'skipped' => 'SKIP',
            default => strtoupper($status),
        };
    };

    $statusClass = static function (string $status): string {
        return match ($status) {
            'passed' => 'ok',
            'failed', 'error' => 'bad',
            'skipped' => 'skip',
            default => '',
        };
    };

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHPUnit — raport API</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { margin: 0; padding: 1.5rem; line-height: 1.45; }
        h1 { margin-top: 0; font-size: 1.35rem; }
        .meta { color: #666; margin-bottom: 1rem; }
        .summary { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem; }
        .pill { padding: .35rem .7rem; border-radius: 999px; background: #eee; font-size: .9rem; }
        .pill.ok { background: #d4edda; }
        .pill.bad { background: #f8d7da; }
        .pill.skip { background: #fff3cd; }
        details { border: 1px solid #ccc; border-radius: 6px; margin-bottom: .75rem; }
        summary { cursor: pointer; padding: .6rem .8rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: .45rem .8rem; border-top: 1px solid #ddd; vertical-align: top; }
        .st-ok { color: #198754; }
        .st-bad { color: #dc3545; font-weight: 600; }
        .st-skip { color: #997404; }
        pre { margin: .25rem 0 0; white-space: pre-wrap; font-size: .8rem; background: #f6f6f6; padding: .5rem; border-radius: 4px; }
        a { color: #0d6efd; }
    </style>
</head>
<body>
    <h1>Testy API (PHPUnit)</h1>
    <p class="meta">Wygenerowano: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="summary">
        <span class="pill">testy: <?= $totals['tests'] ?></span>
        <span class="pill ok">OK: <?= $passed ?></span>
        <?php if ($totals['failures'] > 0): ?>
            <span class="pill bad">FAIL: <?= $totals['failures'] ?></span>
        <?php endif; ?>
        <?php if ($totals['errors'] > 0): ?>
            <span class="pill bad">ERROR: <?= $totals['errors'] ?></span>
        <?php endif; ?>
        <?php if ($totals['skipped'] > 0): ?>
            <span class="pill skip">SKIP: <?= $totals['skipped'] ?></span>
        <?php endif; ?>
        <span class="pill">czas: <?= number_format($totals['time'], 2) ?> s</span>
    </div>
    <?php if ($hasCoverage): ?>
        <p><a href="coverage/index.html">Pokrycie kodu (coverage)</a></p>
    <?php endif; ?>
    <?php foreach ($suites as $suite): ?>
        <details open>
            <summary><?= htmlspecialchars($suite['name'], ENT_QUOTES, 'UTF-8') ?> (<?= count($suite['cases']) ?>)</summary>
            <table>
                <thead>
                    <tr><th>Test</th><th>Status</th><th>Czas</th></tr>
                </thead>
                <tbody>
                <?php foreach ($suite['cases'] as $case): ?>
                    <tr>
                        <td>
                            <div><?= htmlspecialchars($case['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php if ($case['class'] !== ''): ?>
                                <small><?= htmlspecialchars($case['class'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                            <?php if ($case['detail'] !== ''): ?>
                                <pre><?= htmlspecialchars($case['detail'], ENT_QUOTES, 'UTF-8') ?></pre>
                            <?php endif; ?>
                        </td>
                        <td class="st-<?= $statusClass($case['status']) ?>"><?= $statusLabel($case['status']) ?></td>
                        <td><?= number_format($case['time'], 3) ?> s</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    <?php endforeach; ?>
</body>
</html>
    <?php
    return (string) ob_get_clean();
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename((string) ($_SERVER['argv'][0] ?? ''))) {
    $projectRoot = dirname(__DIR__, 2);
    generateTestHtmlReport(
        $projectRoot,
        $projectRoot . '/storage/test-reports/junit.xml',
        $projectRoot . '/public/test-reports/index.html',
    );
}
