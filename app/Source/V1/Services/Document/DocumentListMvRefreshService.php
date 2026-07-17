<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Document;

use App\Source\V1\Queries\Document\ApiDocumentListMaterializedView;
use App\Source\V1\Support\MaterializedViews\MaterializedViewRegistry;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DocumentListMvRefreshService
{
    public function __construct(
        private readonly ApiDocumentListMaterializedView $definition,
        private readonly MaterializedViewRegistry $materializedViewRegistry,
    ) {
    }

    /**
     * @return array{created: bool, refreshed: bool, row_count: int, elapsed_ms: float}
     */
    public function refresh(bool $drop = false): array
    {
        $startedAt = microtime(true);
        $view = ApiDocumentListMaterializedView::NAME;
        $existed = $this->materializedViewRegistry->exists($view);
        $created = false;
        $refreshed = false;

        if ($drop && $existed) {
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS {$view}");
            $existed = false;
        }

        if (!$existed) {
            $sql = 'CREATE MATERIALIZED VIEW ' . $view . ' AS ' . $this->definition->definitionSql();
            DB::statement($sql);
            $created = true;
        } else {
            try {
                DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY {$view}");
            } catch (Throwable) {
                DB::statement("REFRESH MATERIALIZED VIEW {$view}");
            }
            $refreshed = true;
        }

        foreach ($this->definition->indexStatements() as $indexSql) {
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
