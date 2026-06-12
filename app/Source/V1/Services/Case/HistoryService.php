<?php
declare(strict_types=1);
namespace App\Source\V1\Services\Case;

use App\Source\V1\DTO\HistoriaObieguDto;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Services\Structure\EmployeeService;

class HistoryService
{
    public function __construct(
        private CaseQuery $caseQuery,
        private EmployeeService $employeeService
    )
    {
    }

    public function getHistory($mainDocumentUid): array
    {
        $historyWorkflow = $this->caseQuery->getHistory($mainDocumentUid);
        $historyRows = [];
        foreach ($historyWorkflow as $history) {
            $historyRow = new HistoriaObieguDto(
                dokumentId: (string)$mainDocumentUid,
                instanceId: $history->instanceId,
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