<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
loadEnvFile($projectRoot . '/.env');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'GET' && $path === '/health') {
    jsonResponse(200, ['status' => 'ok']);
}

if ($method === 'GET' && $path === '/api/eurzad-sprawa-sample') {
    $limitRaw = $_GET['limit'] ?? '1';
    $limit = ctype_digit((string) $limitRaw) ? (int) $limitRaw : 1;
    $limit = max(1, min(100, $limit));

    try {
        $pdo = createImportDbPdo();
        $statement = $pdo->query('SELECT * FROM public.eurzad_sprawa LIMIT ' . $limit);
        $rows = $statement !== false ? ($statement->fetchAll() ?: []) : [];

        jsonResponse(200, [
            'ok' => true,
            'limit' => $limit,
            'count' => count($rows),
            'rows' => $rows,
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'ok' => false,
            'error' => $exception->getMessage(),
        ]);
    }
}

if ($method === 'GET' && $path === '/api/eurzad-teczka') {
    $znakSprawy = trim((string) ($_GET['teczka_znak_sprawy'] ?? ''));
    if ($znakSprawy === '') {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Query param teczka_znak_sprawy jest wymagany.',
        ]);
    }

    try {
        $pdo = createImportDbPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM public.eurzad_teczka WHERE teczka_znak_sprawy = :teczka_znak_sprawy'
        );
        $stmt->execute(['teczka_znak_sprawy' => $znakSprawy]);
        $rows = $stmt->fetchAll() ?: [];

        jsonResponse(200, [
            'ok' => true,
            'count' => count($rows),
            'rows' => $rows,
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'ok' => false,
            'error' => $exception->getMessage(),
        ]);
    }
}

jsonResponse(404, [
    'ok' => false,
    'error' => 'Not Found',
]);

function createImportDbPdo(): PDO
{
    $host = getenv('IMPORT_DB_HOST') ?: '';
    $port = getenv('IMPORT_DB_PORT') ?: '5433';
    $database = getenv('IMPORT_DB_DATABASE') ?: '';
    $user = getenv('IMPORT_DB_USERNAME') ?: '';
    $password = getenv('IMPORT_DB_PASSWORD') ?: '';

    if ($host === '' || $database === '' || $user === '') {
        throw new RuntimeException(
            'Brak IMPORT_DB_* w środowisku lub w pliku .env (IMPORT_DB_HOST, IMPORT_DB_PORT, IMPORT_DB_DATABASE, IMPORT_DB_USERNAME, IMPORT_DB_PASSWORD).'
        );
    }

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database);

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

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
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
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
