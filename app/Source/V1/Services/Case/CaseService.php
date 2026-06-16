<?php

namespace App\Source\V1\Services\Case;

use App\Shared\Functions;
use App\Source\V1\DTO\PracownikDto;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;
use App\Source\V1\DTO\SprawaDto;
use App\Source\V1\DTO\znakSprawyDto;
use App\Source\V1\Queries\Case\CaseListQuery;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Form\FormQuery;
use App\Source\V1\Queries\ProcessQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Case\HistoryService as CaseHistoryService;
use App\Source\V1\Services\Document\DocumentService;
use App\Source\V1\Services\Form\FormService;
use App\Source\V1\Services\Structure\EmployeeService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Exception;
use Illuminate\Support\Facades\Log;

class CaseService
{

    //idetntfikator sprawy (eurzad_teczka.tekczaUid)
    private $caseUid;
    private $mainDocumentUid;

    public function __construct(
        private SprawaDto                   $caseDetails,
        private readonly CaseQuery          $caseQuery,
        private readonly CaseListQuery      $caseListQuery,
        private readonly ProcessQuery       $processQuery,
        private readonly DocumentService    $documentService,
        private readonly EmployeeService    $employeeService,
        private readonly FormService        $formService,
        private readonly CaseHistoryService $caseHistoryService,
        private readonly SupliantService    $supliantService,
        private readonly FormQuery              $formQuery,
        private readonly AttachmentService      $attachmentService,
        private readonly WorkstationQuery   $workstationQuery
    )
    {

    }

    /**
     * Pobranie opisu sprawy
     *
     * @return SprawaDto|null
     * @throws Exception
     * @throws \ReflectionException
     */
    public function getCaseDetails(KryteriaWyszukiwaniaSpraw $kryteriaWyszukiwania, int $dntas = 0): ?SprawaDto
    {
        Log::notice('CASE_DETAILS.start', ['kryteriaWyszukiwania' => json_encode($kryteriaWyszukiwania), 'dntas' => $dntas]);
        $startedAt = Functions::startTimer();

        $caseUid = $kryteriaWyszukiwania->filtry->sprawaUid;

        $caseDetails = $this->caseListQuery->getList($kryteriaWyszukiwania)[0];
        $this->caseUid = $caseUid;
        $this->mainDocumentUid = $caseDetails['main_document_uid'];
        $this->caseDetails->znakSprawy = $caseDetails['znak'];
        $this->caseDetails->oznaczenieDntas =  $caseDetails['oznaczenie_dntas'];
        $this->caseDetails->idProcesu = $caseDetails['id_procesu'];
        $this->caseDetails->nazwaProcesu = $caseDetails['nazwa_procesu'];
        $normalizedProcessName = $caseDetails['nazwa_procesu_znormalizowana'];
        $this->caseDetails->statusPismaWiodacego = $caseDetails['status_procesu'];
        $registerDate = $caseDetails['data_rejestracji_dokumentu'];

        $this->caseDetails->dataUtworzenia = Functions::convertToISO8601($caseDetails['data_utworzenia_dokumentu']);;
        $this->caseDetails->dataRejestracji = Functions::convertToISO8601($registerDate);

        $realizationTime = $caseDetails['czas_realizacji'];
        if ($realizationTime >= 0) {
            $finishDate = Functions::extendDateByDays($registerDate, $realizationTime);
            $finishDate = Functions::convertToISO8601($finishDate);
        } else {
            $finishDate = null;
        }

        $this->caseDetails->terminRealizacji = $finishDate;
        $wlascicielInfo = $this->workstationQuery->getWorkstationInfo($caseDetails['wlasciciel_stanowisko_id']);
        $this->caseDetails->wlasciciel = PracownikDto::fromWorkstationRow($wlascicielInfo);
        $row = $this->caseQuery->getFirstRowFromHistory($this->mainDocumentUid);
        $utworzylInfo = $this->workstationQuery->getWorkstationInfo($row->uugid_from);
        $this->caseDetails->utworzyl = PracownikDto::fromWorkstationRow($utworzylInfo);





        $this->caseDetails->znakSprawySzczegoly = znakSprawyDto::fromTeczkaRow(
            $this->caseQuery->getAllFromTeczkaBySprawaUid($caseUid, $dntas),
            $caseUid,
        );
        if (empty($this->caseDetails->znakSprawySzczegoly->symbolKomorki)) {
            $this->caseDetails->znakSprawySzczegoly->symbolKomorki = $this->caseDetails->wlasciciel->stanowiskoNazwa;
        }

        $caseTitleAndDesc = $this->caseQuery->getTitleAndDescription($caseUid, $dntas);
        $this->caseDetails->opisSprawy = $caseTitleAndDesc->opis_sprawy;
        $this->caseDetails->tytulSprawy = $caseTitleAndDesc->tytul_sprawy;

        $this->caseDetails->aktaSprawy = !$dntas
            ? $this->documentService->getDocumentsListByCaseUID($this->caseUid)
            : [];
        $this->caseDetails->daneFormularza = $this->formService->getFormMainDocumentValues($this->mainDocumentUid, $normalizedProcessName);
        $this->caseDetails->historiaObiegu = $this->caseHistoryService->getHistory($this->mainDocumentUid);
//        $this->caseDetails->udostepniona = $employee->getEmployeesWhoSharedCase($mainDocumentUid);
//        $this->caseDetails->strony = $this->getSidesOfCase();


        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] CASE_DETAILS.ok', [
            'case_uid' => $caseUid,
            'main_document_uid' => $this->mainDocumentUid,
            'process_id' => $this->caseDetails->idProcesu,
            'documents_count' => count($this->caseDetails->aktaSprawy ?? []),
        ]);

        return $this->caseDetails;
    }

    public function getList(KryteriaWyszukiwaniaSpraw $kryteriaWyszukiwania): array
    {
        Log::notice('CASE_LIST.start', [
            'offset' => $kryteriaWyszukiwania->paginacja->offset,
            'limit' => $kryteriaWyszukiwania->paginacja->limit,
            'page' => $kryteriaWyszukiwania->paginacja->page,
            'sort_field' => $kryteriaWyszukiwania->sortowanie->field,
            'sort_direction' => $kryteriaWyszukiwania->sortowanie->direction,
            'dntas' => $kryteriaWyszukiwania->dntas,
        ]);
        $startedAt = Functions::startTimer();

        $count = $this->caseListQuery->getListCount($kryteriaWyszukiwania);
        if (empty($count)) {
            Log::info('CASE_LIST.empty', [
                'offset' => $kryteriaWyszukiwania->paginacja->offset,
                'limit' => $kryteriaWyszukiwania->paginacja->limit,
            ]);
            return [
                'data' => [],
                'count' => $count,
            ];
        }
        $list = $this->caseListQuery->getList($kryteriaWyszukiwania);
        foreach ($list as &$row) {
            $row['zalaczniki_details'] = !empty($row['zalaczniki'])
                ? $this->attachmentService->getAttachmentsDetails($row['zalaczniki'])
                : [];

            $this->supliantService->hydrateSuppliantData($row, $row['main_document_uid']);

        }
        unset($row);
        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] CASE_LIST.ok', [
            'total_count' => $count,
            'returned_count' => count($list),
            'offset' => $kryteriaWyszukiwania->paginacja->offset,
            'limit' => $kryteriaWyszukiwania->paginacja->limit,
        ]);

        return [
            'data' => $list,
            'count' => $count,
        ];
    }

    /**
     * @return array<int, array{status: string, opis: string}>
     */
    public function getStatuses(int $dntas = 0): array
    {
        return $this->caseQuery->getStatuses($dntas);
    }

    public function streamMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): \Generator
    {
        yield from $this->formQuery->streamAllValuesByKey('pliki', $limit, $offset);
    }

    public function countMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): int
    {
        return $this->formQuery->countAllValuesByKey('pliki', $limit, $offset);
    }

}