<?php

namespace App\Source\V1\Services\Document;

use App\Source\V1\DTO\HistoriaObieguDto;
use App\Source\V1\Queries\Document\DocumentQuery;
use App\Source\V1\Services\Structure\EmployeeService;

class HistoryService
{

    public function __construct(
        private readonly DocumentQuery $documentQuery,
        private readonly EmployeeService $employeeService
    )
    {
    }

    public function getHistory($mainDocumentUid): array
    {
        $historyWorkflow = $this->documentQuery->getHistory($mainDocumentUid);
        $historyRows = [];
        foreach ($historyWorkflow as $history) {
            $historyRow = new HistoriaObieguDto(
                dokumentId: (string)$mainDocumentUid,
                instanceId: $history->instance_id,
                dataUtworzenia: $history->createdate,
                statusOpis: $history->status_opis,
                stanowiskoOd: $this->employeeService->getEmployeeFullNameByUugId($history->uugid_from),
                stanowiskoDo: $this->employeeService->getEmployeeFullNameByUugId($history->uugid_to),
                automat: $history->added_automatically
            );
            $historyRows[] = $historyRow;
        }
        return $historyRows;
    }

}