<?php
declare(strict_types=1);
namespace App\Source\V1\Services\Case;

use App\Source\V1\DTO\TypHistoriaObiegu;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Services\Structure\EmployeeService;

class HistoryService
{
    public function __construct(
        private CaseQuery $caseQuery,
        private EmployeeService $employeeService
    )
    {}

    public function getHistory($mainDocumentUid): array
    {
        $historyWorkflow = $this->caseQuery->getHistory($mainDocumentUid);
        $historyRows = [];
        foreach ($historyWorkflow as $history) {
            $historyRow = new TypHistoriaObiegu(
                dokumentId: (string)$mainDocumentUid,
                instanceId: $history->instanceId,
                dataUtworzenia: $history->createdate,
                akcja: $history->action,
                stanowiskoOd: $this->employeeService->getEmployeeFullNameByUugId($history->uugid_from),
                stanowiskoDo: $this->employeeService->getEmployeeFullNameByUugId($history->uugid_to)
            );
            $historyRows[] = $historyRow;
        }
        return $historyRows;
    }
}