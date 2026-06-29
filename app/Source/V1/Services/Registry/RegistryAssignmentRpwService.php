<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Registry;

use App\Shared\Functions;
use App\Source\V1\DTO\HistoriaObieguDto;
use App\Source\V1\DTO\InteresantDto;
use App\Source\V1\DTO\RejestrRpwPrzypisanieWartosciDto;
use App\Source\V1\DTO\RejestrRpwPrzypisaniaDto;
use App\Source\V1\DTO\RejestrRpwPrzypisanieSzczegolyDto;
use App\Source\V1\DTO\Request\KryteriaPrzypisanRejestrowRpw;
use App\Source\V1\DTO\RejestrRpwFormaDoreczeniaDto;
use App\Source\V1\DTO\RejestrRpwPrzesylkaElektronicznaDto;
use App\Source\V1\DTO\RejestrRpwWysylkaDto;
use App\Source\V1\Enum\WorkstationScopeProfile;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Registry\RegistryAssignmentQuery;
use App\Source\V1\Queries\Registry\RegistryAssignmentRpwQuery;
use App\Source\V1\Services\Structure\EmployeeService;
use App\Source\V1\Services\Structure\WorkstationScopeService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistryAssignmentRpwService
{
    public function __construct(
        private readonly RegistryAssignmentRpwQuery $registryAssignmentRpwQuery,
        private readonly RegistryAssignmentQuery $registryAssignmentQuery,
        private readonly CaseQuery $caseQuery,
        private readonly WorkstationScopeService $workstationScopeService,
        private readonly SupliantService $supliantService,
        private readonly EmployeeService $employeeService,
    ) {
    }

    public function getByDocumentId(string $documentId, array $payload = []): RejestrRpwPrzypisaniaDto
    {
        $documentUid = $this->registryAssignmentQuery->resolveDocumentUid($documentId);

        if ($documentUid === null) {
            throw new Exception('Nieprawidłowy identyfikator dokumentu: ' . $documentId);
        }

        return $this->getList(
            KryteriaPrzypisanRejestrowRpw::forPismoUid($documentUid, $payload),
        );
    }

    /**
     * @return array{data: RejestrRpwPrzypisaniaDto, count: int}
     */
    public function getGlobalList(array $payload = []): array
    {
        $kryteria = $this->resolveGlobalKryteria($payload);
        $scope = $this->workstationScopeService->resolve(
            $kryteria->konfiguracja,
            WorkstationScopeProfile::RpwEntryList,
        );

        return $this->getPaginatedList($kryteria, $scope);
    }

    public function getById(int $registryAssignmentId): ?RejestrRpwPrzypisanieSzczegolyDto
    {
        $row = $this->registryAssignmentRpwQuery->getById($registryAssignmentId);

        if ($row === null) {
            return null;
        }

        $podstawa = $this->mapRow($row);
        $extension = $this->registryAssignmentRpwQuery->getRpwExtensionByAssignmentId($registryAssignmentId);

        return RejestrRpwPrzypisanieSzczegolyDto::fromPodstawa(
            podstawa: $podstawa,
            wysylka: $extension !== null ? $this->mapWysylka($extension) : null,
            adresat: $this->mapAdresat(
                isset($extension['petent_uid']) ? (string) $extension['petent_uid'] : null,
            ),
            historiaObiegu: $this->mapHistoriaObiegu(
                uidPrzesylki: $podstawa->registryAssignmentUid,
                idDokumentu: $podstawa->documentId,
            ),
        );
    }

    private function getList(KryteriaPrzypisanRejestrowRpw $kryteria): RejestrRpwPrzypisaniaDto
    {
        Log::notice('REGISTRY_ASSIGNMENTS_RPW.start', [
            'pismo_uid' => $kryteria->pismoUid,
            'global' => $kryteria->isGlobal,
        ]);
        $startedAt = Functions::startTimer();

        $rows = $this->registryAssignmentRpwQuery->getList($kryteria);
        $values = array_map(
            fn (array $row) => $this->mapRow($row),
            $rows,
        );

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] REGISTRY_ASSIGNMENTS_RPW.ok', [
            'count' => count($values),
        ]);

        return RejestrRpwPrzypisaniaDto::fromValues($values);
    }

    /**
     * @return array{data: RejestrRpwPrzypisaniaDto, count: int}
     */
    private function getPaginatedList(KryteriaPrzypisanRejestrowRpw $kryteria, mixed $scope): array
    {
        Log::notice('REGISTRY_ASSIGNMENTS_RPW_GLOBAL.start', [
            'page' => $kryteria->paginacja->page,
            'limit' => $kryteria->paginacja->limit,
        ]);
        $startedAt = Functions::startTimer();

        $count = $this->registryAssignmentRpwQuery->getListCount($kryteria, $scope);
        $rows = $this->registryAssignmentRpwQuery->getList($kryteria, $scope);
        $values = array_map(
            fn (array $row) => $this->mapRow($row),
            $rows,
        );

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] REGISTRY_ASSIGNMENTS_RPW_GLOBAL.ok', [
            'count' => $count,
            'returned' => count($values),
        ]);

        return [
            'data' => RejestrRpwPrzypisaniaDto::fromValues($values),
            'count' => $count,
        ];
    }

    private function resolveGlobalKryteria(array $payload): KryteriaPrzypisanRejestrowRpw
    {
        $kryteria = KryteriaPrzypisanRejestrowRpw::fromGlobalPayload($payload);

        if ($kryteria->documentId !== null) {
            $documentUid = $this->registryAssignmentQuery->resolveDocumentUid($kryteria->documentId);

            return $kryteria->withPismoUid($documentUid);
        }

        if ($kryteria->caseUid !== null) {
            $pismoUid = $this->resolvePismoUidByCaseUid($kryteria->caseUid);

            return $kryteria->withPismoUid($pismoUid);
        }

        return $kryteria;
    }

    private function resolvePismoUidByCaseUid(string $caseUid): ?string
    {
        $sprawaUid = $this->caseQuery->getMainDocumentUidByCaseUid($caseUid);

        if ($sprawaUid === null || $sprawaUid === '') {
            return null;
        }

        $pismoUid = DB::table('eurzad_teczka_zawartosc as etz')
            ->join('eurzad_pismo as ep', 'ep.pismo_uid', '=', 'etz.teczka_zawartosc_uid')
            ->join('eurzad_teczka as et', 'et.teczka_uid', '=', 'etz.teczka_uid')
            ->where('et.teczka_uid', $caseUid)
            ->orderByDesc('ep.instance_id')
            ->value('ep.pismo_uid');

        return $pismoUid !== null ? (string) $pismoUid : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): RejestrRpwPrzypisanieWartosciDto
    {
        $row['process_name'] = $this->registryAssignmentQuery->getProcessNameForPismoUid(
            (string) $row['document_id'],
        );

        return RejestrRpwPrzypisanieWartosciDto::fromRow($row);
    }

    /**
     * @param array<string, mixed> $extension
     */
    private function mapWysylka(array $extension): RejestrRpwWysylkaDto
    {
        $formaDoreczenia = null;
        $formaKlucz = $extension['forma_doreczenia'] ?? null;

        if ($formaKlucz !== null && $formaKlucz !== '') {
            $formaRow = $this->registryAssignmentRpwQuery->getFormaDoreczeniaByKlucz((string) $formaKlucz);

            if ($formaRow !== null) {
                $formaDoreczenia = RejestrRpwFormaDoreczeniaDto::fromRow($formaRow);
            } else {
                $formaDoreczenia = new RejestrRpwFormaDoreczeniaDto(klucz: (string) $formaKlucz, nazwa: null);
            }
        }

        $przesylkaElektroniczna = null;
        $zawartoscId = $extension['rejestr_zawartosc_id'] ?? null;

        if ($zawartoscId !== null) {
            $enRow = $this->registryAssignmentRpwQuery->getPrzesylkaElektronicznaByZawartoscId((int) $zawartoscId);

            if ($enRow !== null) {
                $przesylkaElektroniczna = RejestrRpwPrzesylkaElektronicznaDto::fromRow($enRow);
            }
        }

        return new RejestrRpwWysylkaDto(
            dataWyslania: isset($extension['data_wyslania']) ? (string) $extension['data_wyslania'] : null,
            nrNadawczy: isset($extension['nr_nadawczy']) ? (string) $extension['nr_nadawczy'] : null,
            formaDoreczenia: $formaDoreczenia,
            przesylkaElektroniczna: $przesylkaElektroniczna,
        );
    }

    private function mapAdresat(?string $petentUid): ?InteresantDto
    {
        if ($petentUid === null || $petentUid === '') {
            return null;
        }

        $row = $this->supliantService->getSupliantById($petentUid);

        if ($row === []) {
            return null;
        }

        return $this->supliantService->mapToInteresantDto(
            row: $row,
            uid: $petentUid,
            glowny: false,
            role: ['Adresat'],
        );
    }

    /**
     * @return HistoriaObieguDto[]
     */
    private function mapHistoriaObiegu(string $uidPrzesylki, string $idDokumentu): array
    {
        $rows = $this->registryAssignmentRpwQuery->getHistoriaObieguByUidPrzesylki($uidPrzesylki, $idDokumentu);
        $historia = [];

        foreach ($rows as $row) {
            $historia[] = new HistoriaObieguDto(
                dokumentId: $uidPrzesylki,
                instanceId: (int) ($row->instance_id ?? 0),
                dataUtworzenia: (string) $row->createdate,
                statusOpis: (string) $row->status_opis,
                stanowiskoOd: $this->employeeService->getEmployeeFullNameByUugId($row->uugid_from),
                stanowiskoDo: $this->employeeService->getEmployeeFullNameByUugId($row->uugid_to),
                automat: (bool) $row->added_automatically,
            );
        }

        return $historia;
    }
}
