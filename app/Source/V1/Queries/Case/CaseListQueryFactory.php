<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use App\Source\V1\Support\MaterializedViews\MaterializedViewsMode;

final class CaseListQueryFactory
{
    public function __construct(
        private readonly MaterializedViewsMode $materializedViewsMode,
        private readonly CaseListQuery $legacyQuery,
        private readonly CaseListQueryMV $mvQuery,
    ) {
    }

    public function make(): CaseListQueryInterface
    {
        if ($this->materializedViewsMode->isEnabled()) {
            return $this->mvQuery;
        }

        return $this->legacyQuery;
    }
}
