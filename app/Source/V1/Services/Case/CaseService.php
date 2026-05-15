<?php
namespace App\Source\V1\Services\Case;

use App\Shared\Functions;
use App\Source\V1\DTO\TypOpisSprawy;
use App\Source\V1\DTO\TypPracownik;
use App\Source\V1\DTO\TypZnakSprawy;
use App\Source\V1\Enum\RodzajPracownika;
use App\Source\V1\Enum\TypDokumentu;
use App\Source\V1\Enum\TypZapytania;
use App\Source\V1\Queries\CaseQuery;
use App\Source\V1\Queries\ProcessQuery;
use App\Source\V1\Queries\Structure\UugQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Document\DocumentService;
use Exception;
use Illuminate\Support\Facades\DB;

class CaseService {

    //idetntfikator sprawy (eurzad_teczka.tekczaUid)
    private $caseUid;

    public function __construct(
        private TypOpisSprawy $caseDetails,
        private readonly CaseQuery $caseQuery,
        private readonly ProcessQuery $processQuery,
        private readonly DocumentService $documentService,
        private readonly WorkstationQuery $workstationQuery,
        private readonly UugQuery $UugQuery,
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
    public function getCaseDetails($mainDocumentUid)
    {
        $this->caseUid = $this->caseQuery->getCaseUidByMainDocumentUid($mainDocumentUid);
        $this->caseDetails->znak = $this->caseQuery->getTeczkaSyg($mainDocumentUid);

        $processId = $this->processQuery->getBySprawaUid($mainDocumentUid);
        if (empty($processId)) {
            throw new Exception(
                "Brak identyfikatora procesu dla '{$mainDocumentUid}'"
            );
        }
        $this->caseDetails->id_procesu = $processId;

        $processName = $this->processQuery->getProcesNameByPID($processId);

        $this->caseDetails->nazwa_procesu = $processName;

        $status = $this->caseQuery->getStatus($mainDocumentUid);

        $this->caseDetails->status_procesu = $status;

        $registerDate = $this->caseQuery->getSprawaPrzedluzanie($mainDocumentUid, 'sprawa_createdate');
        if (empty($registerDate)) {
            throw new Exception(
                "Brak daty rejestracji dla ID: '{$mainDocumentUid}'"
            );
        }

        $registerDateToReturn = $this->caseQuery->getTeczkaCreateDateByCaseId($mainDocumentUid);
        $this->caseDetails->rejestracja = Functions::convertToISO8601($registerDateToReturn);

        $realizationTime = $this->caseQuery->getSprawaPrzedluzanie($mainDocumentUid, 'czas_realizacji');
        if ($realizationTime >= 0) {
            $finishDate = Functions::extendDateByDays($registerDate, $realizationTime);
            if (empty($finishDate)) {
                throw new Exception(
                    "Brak daty zakończenia dla ID: '{$mainDocumentUid}'"
                );
            }
            $finishDate = Functions::convertToISO8601($finishDate);
        } else {
            $finishDate = null;
        }

        $this->caseDetails->termin = $finishDate;

        $this->caseDetails->wlasciciel = $this->getEmployee(
            RodzajPracownika::WLASCICIEL,
            $mainDocumentUid
        );
        $this->caseDetails->utworzyl = $this->getEmployee(
            RodzajPracownika::TWORCA,
            $mainDocumentUid
        );

        $this->caseDetails->znak_szczegolowy = $this->getDetailsOfCaseSign($mainDocumentUid);
        if (empty($this->caseDetails->znak_szczegolowy->komorka_symbol)) {
            $this->caseDetails->znak_szczegolowy->komorka_symbol = $this->caseDetails->wlasciciel->nazwa_stanowiska;
        }

        $caseTitleAndDesc = $this->caseQuery->getTitleAndDescription($mainDocumentUid);
        $this->caseDetails->opis = $caseTitleAndDesc->opis_sprawy;
        $this->caseDetails->tytul = $caseTitleAndDesc->tytul_sprawy;
        $this->caseDetails->dokumenty = $this->documentService->getDocumentsListByCaseUID($this->caseUid);
        $this->caseDetails->udostepniona = $employee->getEmployeesWhoSharedCase($mainDocumentUid);
        $this->caseDetails->strony = $this->getSidesOfCase();


        $esbServiceQueue = new ESBServiceQueue();
        $this->caseDetails->blad = $esbServiceQueue->checkDocumentLog($this->caseUid, TypZapytania::CASE_REQUEST);

        return $opisSprawy;
    }

    public function getList(): array
    {
        $rows = DB::select(<<<SQL
            SELECT
            DISTINCT ON (id_sprawy)
            et.teczka_uid          AS id_sprawy,
            et.teczka_znak_sprawy  AS znak,
            et.sprawa_uid          AS main_document_uid,
            gp.name                AS nazwa_procesu,
            gp."pId"               AS id_procesu,
            ess.opis               AS status_procesu,
            et.teczka_createdate   AS rejestracja
            FROM eurzad_teczka et
            INNER JOIN eurzad_sprawa          es  ON es.sprawa_uid        = et.sprawa_uid
            INNER JOIN galaxia_processes      gp  ON gp.normalized_name   = es.form_name
            INNER JOIN eurzad_obieg           eo  ON eo.sprawa_uid        = es.sprawa_uid
            INNER JOIN eurzad_slownik_status  ess ON ess.symbol           = eo.status
            INNER JOIN galaxia_instances      gi  ON gi."instanceId"      = eo."instanceId"
                                                AND max_status_sprawy_id > 0
            INNER JOIN eurzad_sprawa_przedluzanie sp ON sp.sprawa_uid     = es.sprawa_uid
            ORDER BY id_sprawy ASC, eo.status_sprawy_id DESC
        SQL);

        return array_map(fn ($r) => (array) $r, $rows);
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
                    $row = $this->documentService->getFirstRowFromHistory($id);
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
                $workstationId = $this->getCaseOwnerByCaseUid($id);
                if (empty($workstationId)) {
                    $info = "Dokument numer '{$id}' nie posiada właściciela sprawy dla bieżącej instancji";
                    throw new Exception($info);
                }
                $uugid = $this->workstationQuery->getUugId($workstationId);

                break;
            case (RodzajPracownika::ZATWIERDZAJACY && $processType == TypDokumentu::DOKUMENT):
                $uugid = $this->documentService->getLastRowFromHistory($id);
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

        return $this->getEmployeeInfoByUUgId($uugid);
    }

    public function getInstanceIdByCaseUid($caseUid): int
    {
        $row = $this->caseQuery->getFirstRowFromHistory($caseUid);
        return $row->instanceId;
    }

    public function getCaseOwnerByCaseUid($mainDocumentUid)
    {
        return $this->caseQuery->getCaseOwnerByInstanceId(
            $this->getInstanceIdByCaseUid($mainDocumentUid)
        );
    }

    public function getEmployeeInfoByUUgId($uugid)
    {
        $uugInfo = $this->UugQuery->getInfo($uugid);
        if (empty($uugInfo)) {
            throw new Exception(
                "Brak informacji o pracowniku na podstawie identyfikatora powiązania '{$uugid}'"
            );
        }

        $employee = new TypPracownik();
        $employee->id_uzytkownika = $uugInfo->login;
        $employee->imie = $uugInfo->forename;
        $employee->nazwisko = $this->getFullSurnameString($uugInfo);
        $employee->id_stanowiska = $uugInfo->workstation_id;
        $employee->nazwa_stanowiska = $uugInfo->workstation_description;

        return $employee;
    }

    private function getFullSurnameString($user)
    {
        $surname = '';
        if (!empty($user->surname)) {
            $surname .= $user->surname;
        }
        if (!empty($user->surname2)) {
            if (!empty($surname)) {
                $surname .= '-';
            }
            $surname .= $user->surname2;
        }
        if (!empty($user->surname3)) {
            if (!empty($surname)) {
                $surname .= '-';
            }
            $surname .= $user->surname3;
        }

        return $surname;
    }

    private function getDetailsOfCaseSign($mainDocumentUid): TypZnakSprawy
    {
        $caseData = $this->caseQuery->getAllFromTeczkaBySprawaUid($mainDocumentUid);

        $typZnakSprawy = new TypZnakSprawy();

        if (empty($caseData->teczka_sygnatura)) {
            throw new Exception(
                "Brak JRWA dla ID: '{$mainDocumentUid}'"
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