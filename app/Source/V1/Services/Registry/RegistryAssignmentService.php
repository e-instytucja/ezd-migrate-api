<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Registry;

use App\Shared\Functions;
use App\Source\V1\DTO\RejestrPrzypisanieDto;
use App\Source\V1\DTO\RejestrPrzypisanieWartosciDto;
use App\Source\V1\DTO\RejestrPrzypisaniaDto;
use App\Source\V1\DTO\Request\KryteriaPrzypisanRejestrow;
use App\Source\V1\Enum\WorkstationScopeProfile;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Registry\RegistryAssignmentQuery;
use App\Source\V1\Services\Structure\WorkstationScopeService;
use Exception;
use Illuminate\Support\Facades\Log;

class RegistryAssignmentService
{
    public function __construct(
        private readonly RegistryAssignmentQuery $registryAssignmentQuery,
        private readonly CaseQuery $caseQuery,
        private readonly WorkstationScopeService $workstationScopeService,
    ) {
    }

    public function getByDocumentId(string $documentId, array $payload = []): RejestrPrzypisaniaDto
    {
        $kryteria = KryteriaPrzypisanRejestrow::forDocumentId($documentId, $payload);
        $documentUid = $this->registryAssignmentQuery->resolveDocumentUid($documentId);

        if ($documentUid === null) {
            throw new Exception('Nieprawidłowy identyfikator dokumentu: ' . $documentId);
        }

        return $this->getList(
            $kryteria->withDocumentIds(
                $this->registryAssignmentQuery->resolveAssignmentDocumentIds(
                    $documentUid,
                    $kryteria->withCopies,
                ),
            ),
        );
    }

    public function getByCaseUid(string $caseUid, array $payload = []): RejestrPrzypisaniaDto
    {
        $kryteria = KryteriaPrzypisanRejestrow::forCaseUid($caseUid, $payload);
        $mainDocumentUid = $this->caseQuery->getMainDocumentUidByCaseUid($caseUid);

        if ($mainDocumentUid === null || $mainDocumentUid === '') {
            return RejestrPrzypisaniaDto::empty();
        }

        return $this->getList(
            $kryteria->withDocumentIds(
                $this->registryAssignmentQuery->resolveAssignmentDocumentIds(
                    (string) $mainDocumentUid,
                    $kryteria->withCopies,
                ),
            ),
        );
    }

    /**
     * @return array{data: RejestrPrzypisaniaDto, count: int}
     */
    public function getGlobalList(array $payload = []): array
    {
        $kryteria = $this->resolveGlobalKryteria($payload);
        $scope = $this->workstationScopeService->resolve(
            $kryteria->konfiguracja,
            WorkstationScopeProfile::RegistryBrowse,
        );

        return $this->getPaginatedList($kryteria, $scope);
    }

    public function getById(int $registryAssignmentId): ?RejestrPrzypisanieDto
    {
        $row = $this->registryAssignmentQuery->getById($registryAssignmentId);

        if ($row === null) {
            return null;
        }

        return RejestrPrzypisanieDto::fromValues($this->mapRow($row));
    }

    /**
     * @return array{type: string}[]
     */
    public function getRegistryTypes(): array
    {
        return $this->registryAssignmentQuery->getRegistryTypes();
    }

    private function getList(KryteriaPrzypisanRejestrow $kryteria): RejestrPrzypisaniaDto
    {
        Log::notice('REGISTRY_ASSIGNMENTS.start', [
            'document_ids_count' => count($kryteria->documentIds),
            'with_copies' => $kryteria->withCopies,
            'global' => $kryteria->isGlobal,
        ]);
        $startedAt = Functions::startTimer();

        $rows = $this->registryAssignmentQuery->getList($kryteria);
        $values = array_map(
            fn (array $row) => $this->mapRow($row),
            $rows,
        );

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] REGISTRY_ASSIGNMENTS.ok', [
            'count' => count($values),
        ]);

        return RejestrPrzypisaniaDto::fromValues($values);
    }

    /**
     * @return array{data: RejestrPrzypisaniaDto, count: int}
     */
    private function getPaginatedList(KryteriaPrzypisanRejestrow $kryteria, mixed $scope): array
    {
        Log::notice('REGISTRY_ASSIGNMENTS_GLOBAL.start', [
            'page' => $kryteria->paginacja->page,
            'limit' => $kryteria->paginacja->limit,
        ]);
        $startedAt = Functions::startTimer();

        $count = $this->registryAssignmentQuery->getListCount($kryteria, $scope);
        $rows = $this->registryAssignmentQuery->getList($kryteria, $scope);
        $values = array_map(
            fn (array $row) => $this->mapRow($row),
            $rows,
        );

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] REGISTRY_ASSIGNMENTS_GLOBAL.ok', [
            'count' => $count,
            'returned' => count($values),
        ]);

        return [
            'data' => RejestrPrzypisaniaDto::fromValues($values),
            'count' => $count,
        ];
    }

    private function resolveGlobalKryteria(array $payload): KryteriaPrzypisanRejestrow
    {
        $kryteria = KryteriaPrzypisanRejestrow::fromGlobalPayload($payload);

        if ($kryteria->documentId !== null) {
            $documentUid = $this->registryAssignmentQuery->resolveDocumentUid($kryteria->documentId);

            if ($documentUid === null) {
                return $kryteria->withDocumentIds([]);
            }

            return $kryteria->withDocumentIds(
                $this->registryAssignmentQuery->resolveAssignmentDocumentIds(
                    $documentUid,
                    $kryteria->withCopies,
                ),
            );
        }

        if ($kryteria->caseUid !== null) {
            $mainDocumentUid = $this->caseQuery->getMainDocumentUidByCaseUid($kryteria->caseUid);

            if ($mainDocumentUid === null || $mainDocumentUid === '') {
                return $kryteria->withDocumentIds([]);
            }

            return $kryteria->withDocumentIds(
                $this->registryAssignmentQuery->resolveAssignmentDocumentIds(
                    (string) $mainDocumentUid,
                    $kryteria->withCopies,
                ),
            );
        }

        return $kryteria;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): RejestrPrzypisanieWartosciDto
    {
        $documentUid = (string) $row['document_id'];
        $assignmentType = (string) ($row['registry_assignment_type'] ?? '');

        $row['lead_case_uid'] = $this->registryAssignmentQuery->getLeadCaseUid($documentUid);
        $row['process_name'] = $this->registryAssignmentQuery->getProcessNameByAssignmentType(
            $assignmentType,
            $documentUid,
        );

        return RejestrPrzypisanieWartosciDto::fromRow($row);
    }
}
