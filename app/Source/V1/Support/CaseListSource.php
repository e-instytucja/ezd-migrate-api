<?php

declare(strict_types=1);

namespace App\Source\V1\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Źródło list spraw: env CASE_LIST_SOURCE (legacy|mv).
 * Przełączenie przez API aktualizuje .env + config w runtime.
 */
final class CaseListSource
{
    public const LEGACY = 'legacy';

    public const MV = 'mv';

    public const VIEW_NAME = 'api_case_list';

    /** @var list<string> */
    public const ALLOWED = [self::LEGACY, self::MV];

    public function get(): string
    {
        $source = strtolower((string) config('app.case_list_source', self::LEGACY));

        return in_array($source, self::ALLOWED, true) ? $source : self::LEGACY;
    }

    public function isMv(): bool
    {
        return $this->get() === self::MV;
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException gdy source=mv a widok nie istnieje
     */
    public function set(string $source): void
    {
        $source = strtolower(trim($source));

        if (!in_array($source, self::ALLOWED, true)) {
            throw new InvalidArgumentException(
                'Nieprawidłowe source — dozwolone: legacy, mv.',
            );
        }

        if ($source === self::MV && !$this->materializedViewExists()) {
            throw new RuntimeException(
                'Materialized view api_case_list nie istnieje. Uruchom: php artisan cases:refresh-list-mv',
            );
        }

        $this->writeEnvValue('CASE_LIST_SOURCE', $source);
        putenv('CASE_LIST_SOURCE=' . $source);
        $_ENV['CASE_LIST_SOURCE'] = $source;
        $_SERVER['CASE_LIST_SOURCE'] = $source;
        config(['app.case_list_source' => $source]);

        $cachedConfig = base_path('bootstrap/cache/config.php');
        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }
    }

    public function materializedViewExists(): bool
    {
        $result = DB::selectOne(
            'SELECT to_regclass(?) AS regclass',
            ['public.' . self::VIEW_NAME],
        );

        return $result !== null && $result->regclass !== null;
    }

    public function materializedViewRowCount(): ?int
    {
        if (!$this->materializedViewExists()) {
            return null;
        }

        $result = DB::selectOne('SELECT COUNT(*) AS count FROM ' . self::VIEW_NAME);

        return (int) $result->count;
    }

    /**
     * @return array{source: string, mv_exists: bool, mv_row_count: int|null}
     */
    public function status(): array
    {
        $mvExists = $this->materializedViewExists();

        return [
            'source' => $this->get(),
            'mv_exists' => $mvExists,
            'mv_row_count' => $mvExists ? $this->materializedViewRowCount() : null,
        ];
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
            throw new RuntimeException('Nie udało się zapisać CASE_LIST_SOURCE do .env');
        }
    }
}
