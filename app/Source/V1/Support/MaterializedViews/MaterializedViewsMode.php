<?php

declare(strict_types=1);

namespace App\Source\V1\Support\MaterializedViews;

use InvalidArgumentException;
use RuntimeException;

/**
 * Globalny przełącznik USE_MATERIALIZED_VIEWS — wszystkie listy API albo live SQL, albo MV.
 */
final class MaterializedViewsMode
{
    public const ENV_KEY = 'USE_MATERIALIZED_VIEWS';

    public function __construct(
        private readonly MaterializedViewRegistry $registry,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('app.use_materialized_views', false);
    }

    /**
     * @throws RuntimeException gdy enabled=true a brakuje któregoś widoku
     */
    public function set(bool $enabled): void
    {
        if ($enabled) {
            $missing = $this->registry->missing();
            if ($missing !== []) {
                $lines = array_map(
                    fn (MaterializedViewDefinition $definition) => $definition->name
                        . ' (php artisan ' . $definition->refreshCommand . ')',
                    $missing,
                );

                throw new RuntimeException(
                    'Brakuje materialized view: ' . implode(', ', $lines),
                );
            }
        }

        $value = $enabled ? 'true' : 'false';
        $this->writeEnvValue(self::ENV_KEY, $value);
        putenv(self::ENV_KEY . '=' . $value);
        $_ENV[self::ENV_KEY] = $value;
        $_SERVER[self::ENV_KEY] = $value;
        config(['app.use_materialized_views' => $enabled]);

        $cachedConfig = base_path('bootstrap/cache/config.php');
        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }
    }

    /**
     * @return array{enabled: bool, views: list<array{key: string, name: string, schema: string, exists: bool, row_count: int|null, refresh_command: string}>}
     */
    public function status(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'views' => $this->registry->viewsStatus(),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function parseEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Wymagane pole enabled (true|false).');
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new InvalidArgumentException('Nieprawidłowe enabled — dozwolone: true, false.'),
        };
    }

    private function writeEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!is_file($envPath) || !is_writable($envPath)) {
            throw new RuntimeException('Nie można zapisać pliku .env');
        }

        $contents = file_get_contents($envPath);
        if ($contents === false) {
            throw new RuntimeException('Nie można odczytać pliku .env');
        }

        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            $contents = preg_replace($pattern, $line, $contents, 1);
        } else {
            $contents = rtrim($contents) . "\n\n" . $line . "\n";
        }

        if (file_put_contents($envPath, $contents) === false) {
            throw new RuntimeException('Nie udało się zapisać ' . $key . ' do .env');
        }
    }
}
