<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use App\Source\V1\Support\CaseListSource;

final class CaseListQueryFactory
{
    public function __construct(
        private readonly CaseListSource $caseListSource,
        private readonly CaseListQuery $legacyQuery,
        private readonly CaseListQueryMV $mvQuery,
    ) {
    }

    public function make(): CaseListQueryInterface
    {
        if ($this->caseListSource->isMv()) {
            return $this->mvQuery;
        }

        return $this->legacyQuery;
    }
}
