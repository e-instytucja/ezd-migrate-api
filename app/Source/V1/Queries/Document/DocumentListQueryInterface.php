<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;

interface DocumentListQueryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getList(KryteriaWyszukiwaniaDokumentow $criteria): array;

    public function getListCount(KryteriaWyszukiwaniaDokumentow $criteria): int;
}
