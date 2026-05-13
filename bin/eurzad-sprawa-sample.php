<?php

declare(strict_types=1);

/**
 * Test połączenia z bazą importu (np. pg_import) i odczyt z public.eurzad_sprawa.
 *
 * Z kontenera app (Docker):
 *   docker compose exec app php bin/eurzad-sprawa-sample.php
 *   docker compose exec app php bin/eurzad-sprawa-sample.php 3
 */

$projectRoot = dirname(__DIR__);
loadEnvFile($projectRoot . '/.env');

$host = getenv('IMPORT_DB_HOST') ?: '';
$port = getenv('IMPORT_DB_PORT') ?: '5433';
$dbname = getenv('IMPORT_DB_DATABASE') ?: '';
$user = getenv('IMPORT_DB_USERNAME') ?: '';
$password = getenv('IMPORT_DB_PASSWORD') ?: '';

if ($host === '' || $dbname === '' || $user === '') {
    fwrite(STDERR, "Brak IMPORT_DB_* w środowisku lub w pliku .env (IMPORT_DB_HOST, IMPORT_DB_PORT, IMPORT_DB_DATABASE, IMPORT_DB_USERNAME, IMPORT_DB_PASSWORD).\n");
    exit(1);
}

$limit = 1;
if (isset($argv[1]) && ctype_digit($argv[1])) {
    $limit = max(1, min(100, (int) $argv[1]));
}

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Połączenie z PostgreSQL nie powiodło się: ' . $e->getMessage() . "\n");
    exit(1);
}

$sql = 'SELECT * FROM public.eurzad_sprawa LIMIT ' . (int) $limit;

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    fwrite(STDERR, 'Zapytanie nie powiodło się: ' . $e->getMessage() . "\n");
    exit(1);
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}
