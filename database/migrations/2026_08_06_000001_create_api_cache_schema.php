<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wyjątek od „brak migracji EZD” — tylko schemat warstwy API (MV list).
 * Uruchamiaj po imporcie dumpa: scripts/import-db.sh → php artisan migrate.
 */
return new class extends Migration
{
    private const IDENTIFIER_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function up(): void
    {
        $schema = (string) env('DB_MV_SCHEMA', 'api_cache');
        $user = (string) config('database.connections.pgsql.username', '');

        $this->assertIdentifier($schema, 'DB_MV_SCHEMA');
        $this->assertIdentifier($user, 'DB_USERNAME');

        DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        DB::statement("GRANT USAGE, CREATE ON SCHEMA {$schema} TO {$user}");
    }

    public function down(): void
    {
        // Celowo puste — DROP schematu z MV poza zakresem rollbacku migracji.
    }

    private function assertIdentifier(string $name, string $label): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException("Invalid {$label}: {$name}");
        }
    }
};
