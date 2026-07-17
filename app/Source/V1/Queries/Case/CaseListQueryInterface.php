<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;

interface CaseListQueryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getList(KryteriaWyszukiwaniaSpraw $criteria): array;

    public function getListCount(KryteriaWyszukiwaniaSpraw $criteria): int;
}
