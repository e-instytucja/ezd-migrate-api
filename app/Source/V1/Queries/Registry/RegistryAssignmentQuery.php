<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Registry;

use App\Source\V1\DTO\Request\KryteriaPrzypisanRejestrow;
use App\Source\V1\DTO\Scope\WorkstationScopeResult;
use Illuminate\Support\Facades\DB;

class RegistryAssignmentQuery
{
    public const REGISTRY_TYPE_RPW = 'rejestr_pism_wychodzacych';
    public const ASSIGNMENT_TYPE_DOCUMENT = 'rejestr_pismo';

    /** @var array<int, mixed> */
    private array $bindings = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getList(KryteriaPrzypisanRejestrow $kryteria, ?WorkstationScopeResult $scope = null): array
    {
        $this->bindings = [];

        if (!$kryteria->isGlobal && $kryteria->documentIds === []) {
            return [];
        }

        $sql = $this->buildListSql($kryteria, $scope, withPagination: $kryteria->isGlobal);

        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $this->bindings),
        );
    }

    public function getListCount(KryteriaPrzypisanRejestrow $kryteria, ?WorkstationScopeResult $scope = null): int
    {
        $this->bindings = [];

        if (!$kryteria->isGlobal && $kryteria->documentIds === []) {
            return 0;
        }

        $whereSql = $this->buildWhereSql($kryteria, $scope);
        $sql = <<<SQL
            SELECT COUNT(DISTINCT rz.id) AS count
            FROM eurzad_rejestr_zawartosc rz
            INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
            WHERE {$whereSql}
        SQL;

        $result = DB::select($sql, $this->bindings);

        return (int) ($result[0]->count ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $registryAssignmentId): ?array
    {
        $row = DB::selectOne(<<<SQL
            SELECT
                rz.id AS registry_assignment_id,
                rz.rejestr_zawartosc_uid AS registry_assignment_uid,
                rz.rejestr_zawartosc_uid AS document_id,
                rz.rejestr_zawartosc_numeracja AS registry_assignment_number,
                rz.rejestr_zawartosc_typ AS registry_assignment_type,
                rz.rejestr_uid AS registry_uid,
                r.rejestr_typ AS registry_type,
                r.rejestr_opis AS registry_description,
                rz.rejestr_zawartosc_createdate AS created_at
            FROM eurzad_rejestr_zawartosc rz
            INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
            WHERE rz.id = ?
              AND r.rejestr_typ <> ?
        SQL, [$registryAssignmentId, self::REGISTRY_TYPE_RPW]);

        return $row !== null ? (array) $row : null;
    }

    /**
     * @return array<int, array{type: string}>
     */
    public function getRegistryTypes(): array
    {
        $rows = DB::select(<<<SQL
            SELECT DISTINCT r.rejestr_typ AS type
            FROM eurzad_rejestr r
            WHERE r.rejestr_typ <> ?
            ORDER BY r.rejestr_typ
        SQL, [self::REGISTRY_TYPE_RPW]);

        return array_map(
            static fn ($row) => ['type' => (string) $row->type],
            $rows,
        );
    }

    /**
     * Waliduje i zwraca identyfikator dokumentu (`sprawa_uid` lub `pismo_uid`).
     *
     * Kolumny `eurzad_sprawa.sprawa_uid` i `eurzad_pismo.pismo_uid` są typu string (varchar),
     * ale w praktyce przyjmują wartość hex 13 znaków lub numeric zapisany jako string.
     *
     * Parametr `$documentId` (jak `/documents/{documentId}/attachments`):
     * - hex 13 znaków `[a-f0-9]{13}` — lookup bezpośredni w `eurzad_sprawa` / `eurzad_pismo`
     * - numeric (`\d+`) — najpierw lookup po wartości w kolumnie UID, potem mapowanie przez `instance_id`
     *
     * Nie jest to `teczka_uid`; dla sprawy: `/cases/{caseUid}/registry-assignments`.
     */
    public function resolveDocumentUid(string $documentId): ?string
    {
        if (preg_match('/^[a-f0-9]{13}$/', $documentId) === 1) {
            if ($this->documentUidExists($documentId)) {
                return $documentId;
            }

            return null;
        }

        if (ctype_digit($documentId)) {
            if ($this->documentUidExists($documentId)) {
                return $documentId;
            }

            $instanceId = (int) $documentId;

            $pismoUid = DB::table('eurzad_pismo')
                ->where('instance_id', $instanceId)
                ->orderByDesc('pismo_wersja')
                ->value('pismo_uid');

            if ($pismoUid !== null) {
                return (string) $pismoUid;
            }

            $sprawaUid = DB::table('eurzad_obieg')
                ->whereRaw('"instanceId" = ?', [$instanceId])
                ->orderByDesc('max_status_sprawy_id')
                ->value('sprawa_uid');

            return $sprawaUid !== null ? (string) $sprawaUid : null;
        }

        return null;
    }

    public function getProcessNameForPismoUid(string $pismoUid): ?string
    {
        return $this->getProcessNameByPismoUid($pismoUid);
    }

    private function documentUidExists(string $documentUid): bool
    {
        return DB::table('eurzad_sprawa')->where('sprawa_uid', $documentUid)->exists()
            || DB::table('eurzad_pismo')->where('pismo_uid', $documentUid)->exists();
    }

    /**
     * Buduje listę UID dokumentów do filtrowania wpisów rejestru.
     * Uwzględnia powiązania z `eurzad_rejestr_form_zawartosc` oraz kopie z `eurzad_document_copies`
     * (gdy `$withCopies === true`). Wyklucza rejestr pism wychodzących (RPW).
     *
     * @return string[]
     */
    public function resolveAssignmentDocumentIds(string $documentUid, bool $withCopies): array
    {
        $documentIds = [$documentUid];

        foreach ($this->getFormZawartoscUids($documentUid) as $uid) {
            $documentIds[] = $uid;
        }

        if ($withCopies) {
            foreach (array_keys($this->getRegistryUidsByDocumentCopy($documentUid)) as $uid) {
                $documentIds[] = $uid;
            }
        }

        return array_values(array_unique($documentIds));
    }

    public function getLeadCaseUid(string $sprawaUid): string
    {
        $parentUid = DB::table('eurzad_document_copies')
            ->where('sprawa_uid', $sprawaUid)
            ->value('lead_case_uid');

        if ($parentUid === null || $parentUid === '') {
            return $sprawaUid;
        }

        return $this->getLeadCaseUid((string) $parentUid);
    }

    public function getProcessNameByAssignmentType(string $assignmentType, string $documentUid): ?string
    {
        if ($assignmentType === self::ASSIGNMENT_TYPE_DOCUMENT) {
            return $this->getProcessNameByPismoUid($documentUid);
        }

        return $this->getProcessNameBySprawaUid($documentUid);
    }

    private function buildListSql(
        KryteriaPrzypisanRejestrow $kryteria,
        ?WorkstationScopeResult $scope,
        bool $withPagination,
    ): string {
        $whereSql = $this->buildWhereSql($kryteria, $scope);
        $sql = <<<SQL
            SELECT DISTINCT
                rz.id AS registry_assignment_id,
                rz.rejestr_zawartosc_uid AS registry_assignment_uid,
                rz.rejestr_zawartosc_uid AS document_id,
                rz.rejestr_zawartosc_numeracja AS registry_assignment_number,
                rz.rejestr_zawartosc_typ AS registry_assignment_type,
                rz.rejestr_uid AS registry_uid,
                r.rejestr_typ AS registry_type,
                r.rejestr_opis AS registry_description,
                rz.rejestr_zawartosc_createdate AS created_at
            FROM eurzad_rejestr_zawartosc rz
            INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
            WHERE {$whereSql}
            ORDER BY rz.rejestr_zawartosc_createdate DESC, rz.id DESC
        SQL;

        if ($withPagination) {
            $sql .= "\nLIMIT " . $this->bind($kryteria->paginacja->limit);
            $sql .= "\nOFFSET " . $this->bind($kryteria->paginacja->offset);
        }

        return $sql;
    }

    private function buildWhereSql(KryteriaPrzypisanRejestrow $kryteria, ?WorkstationScopeResult $scope): string
    {
        $conditions = [
            'r.rejestr_typ <> ' . $this->bind(self::REGISTRY_TYPE_RPW),
        ];

        if ($kryteria->documentIds !== []) {
            $conditions[] = 'rz.rejestr_zawartosc_uid IN (' . $this->bindList($kryteria->documentIds) . ')';
        } elseif (!$kryteria->isGlobal) {
            $conditions[] = '1 = 0';
        }

        if ($kryteria->isGlobal) {
            $this->appendGlobalScopeConditions($conditions, $scope);
        }

        if ($kryteria->registryUid !== null) {
            $conditions[] = 'rz.rejestr_uid = ' . $this->bind($kryteria->registryUid);
        }

        if ($kryteria->registryTypes !== []) {
            $conditions[] = 'r.rejestr_typ IN (' . $this->bindList($kryteria->registryTypes) . ')';
        }

        if ($kryteria->createdFrom !== null) {
            $conditions[] = 'rz.rejestr_zawartosc_createdate >= ' . $this->bind($kryteria->createdFrom . ' 00:00:00');
        }

        if ($kryteria->createdTo !== null) {
            $conditions[] = 'rz.rejestr_zawartosc_createdate <= ' . $this->bind($kryteria->createdTo . ' 23:59:59');
        }

        if ($kryteria->year !== null) {
            $conditions[] = 'EXTRACT(YEAR FROM rz.rejestr_zawartosc_createdate) = ' . $this->bind($kryteria->year);
        }

        if ($kryteria->numberFrom !== null) {
            $conditions[] = 'rz.rejestr_zawartosc_numeracja >= ' . $this->bind($kryteria->numberFrom);
        }

        if ($kryteria->numberTo !== null) {
            $conditions[] = 'rz.rejestr_zawartosc_numeracja <= ' . $this->bind($kryteria->numberTo);
        }

        return implode(' AND ', $conditions);
    }

    /**
     * @param string[] $conditions
     */
    private function appendGlobalScopeConditions(array &$conditions, ?WorkstationScopeResult $scope): void
    {
        if ($scope === null || $scope->activityIds === []) {
            $conditions[] = '1 = 0';

            return;
        }

        $conditions[] = 'r."galaxia_activities_activityId" IN (' . $this->bindIntList($scope->activityIds) . ')';
    }

    /**
     * @return string[]
     */
    private function getFormZawartoscUids(string $documentUid): array
    {
        return DB::table('eurzad_rejestr_form_zawartosc')
            ->where('wartosc', $documentUid)
            ->pluck('rejestr_zawartosc_uid')
            ->map(static fn ($uid) => (string) $uid)
            ->all();
    }

    /**
     * @return array<string, true>
     */
    private function getRegistryUidsByDocumentCopy(string $documentUid): array
    {
        $leadCaseUid = $this->getLeadCaseUid($documentUid);
        $sprawaUids = $this->getChildrenLeadCaseUids($leadCaseUid);
        $sprawaUids[] = $leadCaseUid;
        $sprawaUids = array_values(array_unique($sprawaUids));

        if ($sprawaUids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($sprawaUids), '?'));
        $rows = DB::select(
            "SELECT rejestr_zawartosc_uid FROM eurzad_rejestr_zawartosc WHERE rejestr_zawartosc_uid IN ({$placeholders})",
            $sprawaUids,
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->rejestr_zawartosc_uid] = true;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function getChildrenLeadCaseUids(string $sprawaUid): array
    {
        $children = DB::table('eurzad_document_copies')
            ->where('lead_case_uid', $sprawaUid)
            ->pluck('sprawa_uid')
            ->map(static fn ($uid) => (string) $uid)
            ->all();

        $result = [];
        foreach ($children as $childUid) {
            $result[] = $childUid;
            $result = array_merge($result, $this->getChildrenLeadCaseUids($childUid));
        }

        return $result;
    }

    private function getProcessNameBySprawaUid(string $sprawaUid): ?string
    {
        $name = DB::table('eurzad_sprawa as s')
            ->join('galaxia_processes as gp', 'gp.normalized_name', '=', 's.form_name')
            ->where('s.sprawa_uid', $sprawaUid)
            ->value('gp.name');

        return $name !== null ? (string) $name : null;
    }

    private function getProcessNameByPismoUid(string $pismoUid): ?string
    {
        $instanceId = DB::table('eurzad_pismo')
            ->where('pismo_uid', $pismoUid)
            ->orderByDesc('instance_id')
            ->value('instance_id');

        if ($instanceId === null) {
            return null;
        }

        $row = DB::selectOne(<<<SQL
            SELECT p.name
            FROM galaxia_instances i
            INNER JOIN galaxia_processes p ON p."pId" = i."pId"
            WHERE i."instanceId" = ?
        SQL, [$instanceId]);

        $name = $row->name ?? null;

        return $name !== null ? (string) $name : null;
    }

    /**
     * @param string[] $values
     */
    private function bindList(array $values): string
    {
        return implode(', ', array_map(fn (string $value) => $this->bind($value), $values));
    }

    /**
     * @param int[] $values
     */
    private function bindIntList(array $values): string
    {
        return implode(', ', array_map(fn (int $value) => $this->bind($value), $values));
    }

    private function bind(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }
}
