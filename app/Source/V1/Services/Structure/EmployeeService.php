<?php

namespace App\Source\V1\Services\Structure;

use App\Source\V1\DTO\TypPracownik;
use App\Source\V1\Enum\RodzajPracownika;
use App\Source\V1\Enum\TypDokumentu;
use App\Source\V1\Queries\CaseQuery;
use App\Source\V1\Queries\Structure\UugQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Document\HistoryService;
use Exception;

class EmployeeService
{

    public function __construct(
        private CaseQuery $caseQuery,
        private WorkstationQuery $workstationQuery,
        private UugQuery $uugQuery,
        private HistoryService $documentHistoryService
    )
    {

    }
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

        return $this->getEmployeeInfoByUUgId($uugid);
    }

    public function getEmployeeFullNameByUugId($uugid): string
    {
        $employeeInfo = $this->getEmployeeInfoByUUgId($uugid);

        return sprintf(
            '%s %s [%s] {%s} (%s)',
            $employeeInfo->imie,
            $employeeInfo->nazwisko,
            $employeeInfo->nazwa_stanowiska,
            $employeeInfo->skrot_komorki,
            $employeeInfo->login
        );

    }
    public function getEmployeeInfoByUUgId($uugid): TypPracownik
    {
        $uugInfo = $this->uugQuery->getInfo($uugid);
        if (empty($uugInfo)) {
            throw new Exception(
                "Brak informacji o pracowniku na podstawie identyfikatora powiązania '{$uugid}'"
            );
        }

        $employee = new TypPracownik();
        $employee->id_uzytkownika = $uugInfo->user_id;
        $employee->imie = $uugInfo->forename;
        $employee->nazwisko = $this->getFullSurnameString($uugInfo);
        $employee->id_stanowiska = $uugInfo->workstation_id;
        $employee->nazwa_stanowiska = $uugInfo->workstation_description;
        $employee->login = $uugInfo->login;
        $employee->skrot_komorki = $uugInfo->departament_name;
        $employee->nazwa_komorki = $uugInfo->departament_description;

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

}