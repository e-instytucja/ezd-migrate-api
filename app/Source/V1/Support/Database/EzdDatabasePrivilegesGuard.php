<?php

declare(strict_types=1);

namespace App\Source\V1\Support\Database;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Weryfikacja uprawnień PostgreSQL: read-only na danych EZD, CREATE na api_cache (MV).
 */
final class EzdDatabasePrivilegesGuard
{
    private const IDENTIFIER_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private ?array $cachedAudit = null;

    /**
     * @return array{
     *     compliant: bool,
     *     enforce_enabled: bool,
     *     current_user: string,
     *     probe_table: string,
     *     mv_schema: string,
     *     checks: array{
     *         ezd_insert: bool,
     *         ezd_update: bool,
     *         ezd_delete: bool,
     *         ezd_truncate: bool,
     *         public_create: bool,
     *         api_cache_create: bool
     *     },
     *     violations: list<string>
     * }
     */
    public function audit(bool $fresh = false): array
    {
        if (!$fresh && $this->cachedAudit !== null) {
            return $this->cachedAudit;
        }

        $probeTable = (string) config('app.ezd_privileges_probe_table', 'public.eurzad_teczka');
        $mvSchema = (string) config('app.materialized_views_schema', 'api_cache');
        $this->assertQualifiedTable($probeTable);
        $this->assertIdentifier($mvSchema, 'mv_schema');

        $row = DB::selectOne(
            <<<'SQL'
                SELECT
                    current_user AS current_user,
                    has_table_privilege(current_user, ?, 'INSERT') AS ezd_insert,
                    has_table_privilege(current_user, ?, 'UPDATE') AS ezd_update,
                    has_table_privilege(current_user, ?, 'DELETE') AS ezd_delete,
                    has_table_privilege(current_user, ?, 'TRUNCATE') AS ezd_truncate,
                    has_schema_privilege(current_user, 'public', 'CREATE') AS public_create,
                    has_schema_privilege(current_user, ?, 'CREATE') AS api_cache_create
            SQL,
            [$probeTable, $probeTable, $probeTable, $probeTable, $mvSchema],
        );

        $checks = [
            'ezd_insert' => (bool) $row->ezd_insert,
            'ezd_update' => (bool) $row->ezd_update,
            'ezd_delete' => (bool) $row->ezd_delete,
            'ezd_truncate' => (bool) $row->ezd_truncate,
            'public_create' => (bool) $row->public_create,
            'api_cache_create' => (bool) $row->api_cache_create,
        ];

        $violations = $this->buildViolations($checks, $probeTable);

        $this->cachedAudit = [
            'compliant' => $violations === [],
            'enforce_enabled' => (bool) config('app.enforce_ezd_db_read_only', false),
            'current_user' => (string) $row->current_user,
            'probe_table' => $probeTable,
            'mv_schema' => $mvSchema,
            'checks' => $checks,
            'violations' => $violations,
        ];

        return $this->cachedAudit;
    }

    public function isCompliant(bool $fresh = false): bool
    {
        return $this->audit($fresh)['compliant'];
    }

    public function assertCompliant(): void
    {
        $audit = $this->audit();
        if ($audit['compliant']) {
            return;
        }

        throw new RuntimeException(
            'Nieprawidłowe uprawnienia bazy danych: ' . implode('; ', $audit['violations']),
        );
    }

    public function assertApiCacheCreate(): void
    {
        $audit = $this->audit();
        if ($audit['checks']['api_cache_create']) {
            return;
        }

        throw new RuntimeException(
            'Brak uprawnienia CREATE na schemacie '
            . $audit['mv_schema']
            . ' dla użytkownika '
            . $audit['current_user']
            . '. Uruchom: php artisan migrate lub scripts/setup-ezd-readonly-privileges.sh',
        );
    }

    /**
     * @param array{
     *     ezd_insert: bool,
     *     ezd_update: bool,
     *     ezd_delete: bool,
     *     ezd_truncate: bool,
     *     public_create: bool,
     *     api_cache_create: bool
     * } $checks
     * @return list<string>
     */
    private function buildViolations(array $checks, string $probeTable): array
    {
        $violations = [];

        if ($checks['ezd_insert']) {
            $violations[] = "INSERT na {$probeTable}";
        }
        if ($checks['ezd_update']) {
            $violations[] = "UPDATE na {$probeTable}";
        }
        if ($checks['ezd_delete']) {
            $violations[] = "DELETE na {$probeTable}";
        }
        if ($checks['ezd_truncate']) {
            $violations[] = "TRUNCATE na {$probeTable}";
        }
        if ($checks['public_create']) {
            $violations[] = 'CREATE na schemacie public';
        }

        return $violations;
    }

    private function assertQualifiedTable(string $qualifiedTable): void
    {
        $parts = explode('.', $qualifiedTable, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Nieprawidłowa tabela probe (oczekiwano schema.table): ' . $qualifiedTable);
        }

        $this->assertIdentifier($parts[0], 'probe_schema');
        $this->assertIdentifier($parts[1], 'probe_table');
    }

    private function assertIdentifier(string $name, string $kind): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $name) !== 1) {
            throw new RuntimeException("Invalid {$kind}: {$name}");
        }
    }
}
