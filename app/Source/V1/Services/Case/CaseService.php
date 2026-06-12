<?php

namespace App\Source\V1\Services\Case;

use App\Shared\Functions;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;
use App\Source\V1\DTO\TypOpisSprawy;
use App\Source\V1\DTO\TypPracownik;
use App\Source\V1\DTO\TypZnakSprawy;
use App\Source\V1\Enum\RodzajPracownika;
use App\Source\V1\Enum\TypDokumentu;
use App\Source\V1\Queries\Case\CaseListQuery;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Document\DocumentQuery;
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
        private TypOpisSprawy                   $caseDetails,
        private readonly CaseQuery              $caseQuery,
        private readonly CaseListQuery          $caseListQuery,
        private readonly ProcessQuery           $processQuery,
        private readonly DocumentService        $documentService,
        private readonly WorkstationQuery       $workstationQuery,
        private readonly DocumentQuery          $documentQuery,
        private readonly EmployeeService        $employeeService,
        private readonly FormService            $formService,
        private readonly CaseHistoryService     $caseHistoryService,
        private readonly SupliantService        $supliantService,
        private readonly FormQuery              $formQuery,
        private readonly AttachmentService      $attachmentService
    )
    {

    }

    /**
     * Pobranie opisu sprawy
     *
     * @return TypOpisSprawy
     * @throws Exception
     * @throws \ReflectionException
     */
    public function getCaseDetails($caseUid, int $dntas = 0)
    {
        Log::notice('CASE_DETAILS.start', ['case_uid' => $caseUid, 'dntas' => $dntas]);
        $startedAt = Functions::startTimer();

        $this->caseUid = $caseUid;
        $this->mainDocumentUid = $this->caseQuery->getMainDocumentUidByCaseUid($caseUid);
        $this->caseDetails->znak = $this->caseQuery->getTeczkaSyg($caseUid);
        $this->caseDetails->oznaczenie_dntas = $this->caseQuery->getTeczkaSyg($caseUid, 'oznaczenie_dntas');

        $processId = $this->processQuery->getBySprawaUid($this->mainDocumentUid);
        if (empty($processId)) {
            Log::error('CASE_DETAILS.error', [
                'case_uid' => $caseUid,
                'main_document_uid' => $this->mainDocumentUid,
                'error' => 'missing_process_id',
            ]);
            throw new Exception(
                "Brak identyfikatora procesu dla '{$this->mainDocumentUid}'"
            );
        }
        $this->caseDetails->id_procesu = $processId;

        $processName = $this->processQuery->getProcesNameByPID($processId);
        $normalizedProcessName = $this->processQuery->getNormalizedProcesNameByPID($processId);

        $this->caseDetails->nazwa_procesu = $processName;

        $status = $this->caseQuery->getStatus($this->mainDocumentUid);

        $this->caseDetails->status_procesu = $status;

        $registerDate = $this->caseQuery->getSprawaPrzedluzanie($this->mainDocumentUid, 'sprawa_createdate');
        if (empty($registerDate)) {
            Log::error('CASE_DETAILS.error', [
                'case_uid' => $caseUid,
                'main_document_uid' => $this->mainDocumentUid,
                'error' => 'missing_register_date',
            ]);
            throw new Exception(
                "Brak daty rejestracji dla ID: '{$this->mainDocumentUid}'"
            );
        }

        $registerDateToReturn = $this->caseQuery->getMainDocumentCreateDateByCaseUid($this->mainDocumentUid);
        $this->caseDetails->rejestracja = Functions::convertToISO8601($registerDateToReturn);

        $realizationTime = $this->caseQuery->getSprawaPrzedluzanie($this->mainDocumentUid, 'czas_realizacji');
        if ($realizationTime >= 0) {
            $finishDate = Functions::extendDateByDays($registerDate, $realizationTime);
            if (empty($finishDate)) {
                Log::error('CASE_DETAILS.error', [
                    'case_uid' => $caseUid,
                    'main_document_uid' => $this->mainDocumentUid,
                    'error' => 'missing_finish_date',
                ]);
                throw new Exception(
                    "Brak daty zakończenia dla ID: '{$this->mainDocumentUid}'"
                );
            }
            $finishDate = Functions::convertToISO8601($finishDate);
        } else {
            $finishDate = null;
        }

        $this->caseDetails->termin = $finishDate;

        $this->caseDetails->wlasciciel = $this->employeeService->getEmployee(
            RodzajPracownika::WLASCICIEL,
            $this->mainDocumentUid
        );
        $this->caseDetails->utworzyl = $this->employeeService->getEmployee(
            RodzajPracownika::TWORCA,
            $this->mainDocumentUid
        );

        $this->caseDetails->znak_szczegolowy = $this->getDetailsOfCaseSign($caseUid, $dntas);
        if (empty($this->caseDetails->znak_szczegolowy->komorka_symbol)) {
            $this->caseDetails->znak_szczegolowy->komorka_symbol = $this->caseDetails->wlasciciel->nazwa_stanowiska;
        }

        $caseTitleAndDesc = $this->caseQuery->getTitleAndDescription($caseUid, $dntas);
        $this->caseDetails->opis = $caseTitleAndDesc->opis_sprawy;
        $this->caseDetails->tytul = $caseTitleAndDesc->tytul_sprawy;

        $this->caseDetails->dokumenty = !$dntas
            ? $this->documentService->getDocumentsListByCaseUID($this->caseUid)
            : [];
        $this->caseDetails->dane_formularza = $this->formService->getFormMainDocumentValues($this->mainDocumentUid, $normalizedProcessName);
        $this->caseDetails->historia_obiegu = $this->caseHistoryService->getHistory($this->mainDocumentUid);
//        $this->caseDetails->udostepniona = $employee->getEmployeesWhoSharedCase($mainDocumentUid);
//        $this->caseDetails->strony = $this->getSidesOfCase();


        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] CASE_DETAILS.ok', [
            'case_uid' => $caseUid,
            'main_document_uid' => $this->mainDocumentUid,
            'process_id' => $this->caseDetails->id_procesu,
            'documents_count' => count($this->caseDetails->dokumenty ?? []),
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

    /**
     * Szkielet danych dla audytu załączników pism wiodących.
     * Tu docelowo dodasz zapytanie do DB.
     *
     * Oczekiwane klucze pojedynczego wiersza:
     * - main_document_uid
     * - attachment_uid
     * - filename
     * - attachment_createdate
     * - attachment_foreign_uid
     */
    public function getMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): array
    {
        return $this->formQuery->getAllValuesByKey('pliki', $limit, $offset);
    }

    public function streamMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): \Generator
    {
        yield from $this->formQuery->streamAllValuesByKey('pliki', $limit, $offset);
    }

    public function countMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): int
    {
        return $this->formQuery->countAllValuesByKey('pliki', $limit, $offset);
    }

    /**
     * Pobranie użytkownika SIDAS EZD (pracownika)
     *
     * @param string $employeeType
     * @param string $id
     * @param integer|null $processType
     * @throws Exception
     */
    public function getEmployee($employeeType, $id, $processType = null): TypPracownik
    {
        switch ($employeeType) {
            case RodzajPracownika::TWORCA:
                if ($processType == TypDokumentu::DOKUMENT) {
                    $row = $this->documentQuery->getFirstRowFromHistory($id);
                    if (empty($row->uugid_from)) {
                        Log::error('EMPLOYEE.error', ['document_id' => $id, 'employee_type' => $employeeType, 'error' => 'missing_uugid_pismo']);
                        throw new Exception(
                            "Wpis dla dokumentu nie zawiera informacji o stanowisku (od) dla '{$id}'"
                        );
                    }
                    $uugid = $row->uugid_from;;
                } else { //pismo wiodące, sprawa
                    $row = $this->caseQuery->getFirstRowFromHistory($id);
                    if (empty($row->uugid_from)) {
                        Log::error('EMPLOYEE.error', ['document_id' => $id, 'employee_type' => $employeeType, 'error' => 'missing_uugid_sprawa']);
                        throw new Exception(
                            "Wpis dla sprawy nie zawiera informacji o stanowisku (od) dla '{$id}'"
                        );
                    }
                    $uugid = $row->uugid_from;
                }
                break;
            case RodzajPracownika::WLASCICIEL:
                if ($processType == TypDokumentu::DOKUMENT) {
                    //podmiana pismo_uid na sprawa_uid (zmiana wartości zmiennej $id)
                    // - potrzebne do pobrania właściciela
                    $mainDocument = $this->caseQuery->getSprawaUidByTeczkaZawartoscUid($id, 'o.sprawa_uid');
                    $id = $mainDocument->sprawa_uid;
                }
                $workstationId = $this->caseQuery->getCaseOwnerByCaseUid($id);
                if (empty($workstationId)) {
                    $info = "Dokument numer '{$id}' nie posiada właściciela sprawy dla bieżącej instancji";
                    Log::error('EMPLOYEE.error', ['document_id' => $id, 'employee_type' => $employeeType, 'error' => 'missing_workstation_owner']);
                    throw new Exception($info);
                }
                $uugid = $this->workstationQuery->getUugId($workstationId);

                break;
            case (RodzajPracownika::ZATWIERDZAJACY && $processType == TypDokumentu::DOKUMENT):
                $uugid = $this->documentQuery->getLastRowFromHistory($id);
                if (empty($uugid)) {
                    Log::error('EMPLOYEE.error', ['document_id' => $id, 'employee_type' => $employeeType, 'error' => 'missing_zatwierdzajacy']);
                    throw new Exception(
                        "Wpis nie zawiera informacji o osobie zatwierdzającej dla '{$id}'"
                    );
                }
                break;
            default:
                Log::error('EMPLOYEE.error', ['document_id' => $id, 'employee_type' => $employeeType, 'error' => 'unsupported_employee_type']);
                throw new Exception(
                    "Nieprawidłowy rodzaj pracownika '{$employeeType}' dla '{$id}'"
                );
        }

        return $this->employeeService->getEmployeeInfoByUUgId($uugid);
    }

    private function getDetailsOfCaseSign(string $caseUid, int $dntas = 0): TypZnakSprawy
    {
        $caseData = $this->caseQuery->getAllFromTeczkaBySprawaUid($caseUid, $dntas);

        $typZnakSprawy = new TypZnakSprawy();

        if (empty($caseData->teczka_sygnatura)) {
            throw new Exception(
                "Brak JRWA dla ID: '{$caseUid}'"
            );
        }
        $jrwa = $this->parseSymbolFromSygnatura($caseData->teczka_sygnatura);

        $typZnakSprawy->jrwa_symbol = $jrwa;
        $zbior_nr = str_replace([$jrwa, '-', '.'], '', $caseData->teczka_sygnatura);
        $typZnakSprawy->zbior_nr = $zbior_nr === '' ? null : $zbior_nr;
        $typZnakSprawy->numer = $caseData->teczka_numer === '' ? null : $caseData->teczka_numer;
        $typZnakSprawy->rocznik = $caseData->teczka_rok_zalozenia;
        $typZnakSprawy->zbior_opis = $caseData->opis_zbioru;
        $typZnakSprawy->komorka_symbol = $caseData->teczka_wydzial;

        return $typZnakSprawy;
    }

    private function parseSymbolFromSygnatura($sygnatura)
    {
        $sygnaturaTmp = '';
        $check_if_zbior = [];

        $check_if_zbior[] = explode('-', $sygnatura);
        $check_if_zbior[] = explode('.', $sygnatura);

        foreach ($check_if_zbior as $val) {
            if (is_array($val) && count($val) > 1) {
                $sygnaturaTmp = $val[0];
            }
        }

        return (empty($sygnaturaTmp)) ? $sygnatura : $sygnaturaTmp;
    }


}