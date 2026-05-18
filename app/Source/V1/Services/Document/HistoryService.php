<?php

namespace App\Source\V1\Services\Document;

use App\Source\V1\Queries\Document\DocumentQuery;

class HistoryService
{

    public function __construct(
        private readonly DocumentQuery $documentQuery,
    )
    {
    }

    public function getLastRowFromHistory(
        $documentUid,
        array $statuses = [],
        string $sortDirection = 'DESC'
    ): \stdClass
    {
        return $this->documentQuery->getRowFromHistory(
            $documentUid,
            $statuses,
            'DESC'
        );
    }

    public function getFirstRowFromHistory(
        $documentUid,
        array $statuses = []
    ): \stdClass
    {
        return $this->documentQuery->getRowFromHistory(
            $documentUid,
            $statuses,
            'ASC'
        );
    }

}