<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Case;

use App\Source\V1\Queries\Case\ApiCaseListMaterializedView;
use App\Source\V1\Support\CaseListSource;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CaseListMvRefreshService
{
    public function __construct(
        private readonly ApiCaseListMaterializedView $definition,
        private readonly CaseListSource $caseListSource,
    ) {
    }

    /**
     * @return array{created: bool, refreshed: bool, row_count: int, elapsed_ms: float}
     */
    public function refresh(bool $drop = false): array
    {
        $startedAt = microtime(true);
        $view = ApiCaseListMaterializedView::NAME;
        $existed = $this->caseListSource->materializedViewExists();
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

        $rowCount = $this->caseListSource->materializedViewRowCount() ?? 0;

        return [
            'created' => $created,
            'refreshed' => $refreshed,
            'row_count' => $rowCount,
            'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }
}
