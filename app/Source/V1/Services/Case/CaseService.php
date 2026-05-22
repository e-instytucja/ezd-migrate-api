<?php

namespace App\Source\V1\Services\Case;

use App\Shared\Functions;
use App\Source\V1\DTO\TypOpisSprawy;
use App\Source\V1\DTO\TypPracownik;
use App\Source\V1\DTO\TypZnakSprawy;
use App\Source\V1\Enum\RodzajPracownika;
use App\Source\V1\Enum\TypDokumentu;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\ProcessQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Case\HistoryService as CaseHistoryService;
use App\Source\V1\Services\Document\DocumentService;
use App\Source\V1\Services\Document\HistoryService as DocumentHistoryService;
use App\Source\V1\Services\Form\FormService;
use App\Source\V1\Services\Structure\EmployeeService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Exception;

class CaseService
{

    //idetntfikator sprawy (eurzad_teczka.tekczaUid)
    private $caseUid;
    private $mainDocumentUid;

    public function __construct(
        private TypOpisSprawy                   $caseDetails,
        private readonly CaseQuery              $caseQuery,
        private readonly ProcessQuery           $processQuery,
        private readonly DocumentService        $documentService,
        private readonly WorkstationQuery       $workstationQuery,
        private readonly DocumentHistoryService $documentHistoryService,
        private readonly EmployeeService        $employeeService,
        private readonly FormService            $formService,
        private readonly CaseHistoryService     $caseHistoryService,
        private readonly SupliantService        $supliantService
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
    public function getCaseDetails($caseUid)
    {
        $this->caseUid = $caseUid;
        $this->mainDocumentUid = $this->caseQuery->getMainDocumentUidByCaseUid($caseUid);
        $this->caseDetails->znak = $this->caseQuery->getTeczkaSyg($caseUid);

        $processId = $this->processQuery->getBySprawaUid($this->mainDocumentUid);
        if (empty($processId)) {
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

        $this->caseDetails->znak_szczegolowy = $this->getDetailsOfCaseSign($caseUid);
        if (empty($this->caseDetails->znak_szczegolowy->komorka_symbol)) {
            $this->caseDetails->znak_szczegolowy->komorka_symbol = $this->caseDetails->wlasciciel->nazwa_stanowiska;
        }

        $caseTitleAndDesc = $this->caseQuery->getTitleAndDescription($caseUid);
        $this->caseDetails->opis = $caseTitleAndDesc->opis_sprawy;
        $this->caseDetails->tytul = $caseTitleAndDesc->tytul_sprawy;
        $this->caseDetails->dokumenty = $this->documentService->getDocumentsListByCaseUID($this->caseUid);
        $this->caseDetails->dane_formularza = $this->formService->getFormValues($this->mainDocumentUid, $normalizedProcessName);
        $this->caseDetails->historia_obiegu = $this->caseHistoryService->getHistory($this->mainDocumentUid);
//        $this->caseDetails->udostepniona = $employee->getEmployeesWhoSharedCase($mainDocumentUid);
//        $this->caseDetails->strony = $this->getSidesOfCase();


        return $this->caseDetails;
    }

    public function getList(int $offset = 0, int $limit = 50): array
    {
        $count = $this->caseQuery->getListCount();
        if(empty($count)) {
            return [
                'data' => [],
                'limit' => $limit,
            ];
        }
        $list = $this->caseQuery->getList($offset, $limit);
        foreach ($list as &$row) {
            $url = '/api/v1/cases/' . $row['id_sprawy'] . '?format=html';

            $row['url'] = sprintf(
                '<a href="%s" target="_blank">Podgląd sprawy</a>',
                $url
            );

            $row['interesant'] = Functions::normalizeText($row['interesant']);
            $row['interesant_adres'] = Functions::normalizeText($row['interesant_adres']);
            $row['interesant_meta'] = [
                'interesant_type' => $row['interesant_type'],
            ];

            if ($row['has_pozostali_interesanci'] === true) {

                $row['pozostali_interesanci'] =
                    $this->supliantService->getAdditionalSuppliants(
                        $row['main_document_uid']
                    );
                $row['pozostali_interesanci_tooltip_count'] = count($row['pozostali_interesanci']);
                $pozostaliInteresanciTooltip = [];
                foreach ($row['pozostali_interesanci'] as &$interesant) {
                    $interesant['interesant'] = Functions::normalizeText($interesant['interesant']);
                    $interesant['interesant_adres'] = Functions::normalizeText($interesant['interesant_adres']);

                    $pozostaliInteresanciTooltip[] = $interesant['interesant'];
                }
                unset($interesant);
                $row['pozostali_interesanci_tooltip'] = implode(', ', $pozostaliInteresanciTooltip);
            }

        }
        return [
            'data'  => $list,
            'count' => $count,
        ];
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
                    $row = $this->documentHistoryService->getFirstRowFromHistory($id);
                    if (empty($row->uugid_from)) {
                        throw new Exception(
                            "Wpis dla pisma nie zawiera informacji o stanowisku (od) dla '{$id}'"
                        );
                    }
                    $uugid = $row->uugid_from;;
                } else { //pismo wiodące, sprawa
                    $row = $this->caseQuery->getFirstRowFromHistory($id);
                    if (empty($row->uugid_from)) {
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
                    throw new Exception($info);
                }
                $uugid = $this->workstationQuery->getUugId($workstationId);

                break;
            case (RodzajPracownika::ZATWIERDZAJACY && $processType == TypDokumentu::DOKUMENT):
                $uugid = $this->documentHistoryService->getLastRowFromHistory($id);
                if (empty($uugid)) {
                    throw new Exception(
                        "Wpis nie zawiera informacji o osobie zatwierdzającej dla '{$id}'"
                    );
                }
                break;
            default:
                throw new Exception(
                    "Nieprawidłowy rodzaj pracownika '{$employeeType}' dla '{$id}'"
                );
        }

        return $this->employeeService->getEmployeeInfoByUUgId($uugid);
    }

    private function getDetailsOfCaseSign(string $caseUid): TypZnakSprawy
    {
        $caseData = $this->caseQuery->getAllFromTeczkaBySprawaUid($caseUid);

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