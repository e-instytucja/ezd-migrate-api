<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\TypFiltrDokument;
use App\Source\V1\Support\MaterializedViews\MaterializedViewsMode;

final class DocumentListQueryFactory
{
    public function __construct(
        private readonly MaterializedViewsMode $materializedViewsMode,
        private readonly DocumentListQuery $legacyQuery,
        private readonly DocumentListQueryMV $mvQuery,
    ) {
    }

    public function make(TypFiltrDokument $filtry): DocumentListQueryInterface
    {
        if ($filtry->isScopedToTeczka() || !$this->materializedViewsMode->isEnabled()) {
            return $this->legacyQuery;
        }

        return $this->mvQuery;
    }
}
