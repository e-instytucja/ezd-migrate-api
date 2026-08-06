<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Document;

use App\Source\V1\Queries\Document\ApiDocumentListMaterializedView;
use App\Source\V1\Support\Database\EzdDatabasePrivilegesGuard;
use App\Source\V1\Support\MaterializedViews\MaterializedViewNaming;
use App\Source\V1\Support\MaterializedViews\MaterializedViewRegistry;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DocumentListMvRefreshService
{
    public function __construct(
        private readonly ApiDocumentListMaterializedView $definition,
        private readonly MaterializedViewRegistry $materializedViewRegistry,
        private readonly EzdDatabasePrivilegesGuard $privilegesGuard,
    ) {
    }

    /**
     * @return array{created: bool, refreshed: bool, row_count: int, elapsed_ms: float}
     */
    public function refresh(bool $drop = false): array
    {
        $this->privilegesGuard->assertApiCacheCreate();
        $startedAt = microtime(true);
        $view = ApiDocumentListMaterializedView::NAME;
        $qualifiedView = MaterializedViewNaming::qualified($view);
        $existed = $this->materializedViewRegistry->exists($view);
        $created = false;
        $refreshed = false;

        if ($drop && $existed) {
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS {$qualifiedView}");
            $existed = false;
        }

        if (!$existed) {
            $sql = 'CREATE MATERIALIZED VIEW ' . $qualifiedView . ' AS ' . $this->definition->definitionSql();
            DB::statement($sql);
            $created = true;
        } else {
            try {
                DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY {$qualifiedView}");
            } catch (Throwable) {
                DB::statement("REFRESH MATERIALIZED VIEW {$qualifiedView}");
            }
            $refreshed = true;
        }

        foreach ($this->definition->indexStatements($qualifiedView) as $indexSql) {
            DB::statement($indexSql);
        }

        $rowCount = $this->materializedViewRegistry->rowCount($view) ?? 0;

        return [
            'created' => $created,
            'refreshed' => $refreshed,
            'row_count' => $rowCount,
            'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }
}
