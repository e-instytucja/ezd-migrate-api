<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Registry;

use App\Source\V1\DTO\Request\KryteriaPrzypisanRejestrowRpw;
use App\Source\V1\DTO\Scope\WorkstationScopeResult;
use Illuminate\Support\Facades\DB;

class RegistryAssignmentRpwQuery
{
    public const REGISTRY_TYPE_RPW = 'rejestr_pism_wychodzacych';

    /** @var array<int, mixed> */
    private array $bindings = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getList(KryteriaPrzypisanRejestrowRpw $kryteria, ?WorkstationScopeResult $scope = null): array
    {
        $this->bindings = [];

        if (!$kryteria->isGlobal && ($kryteria->pismoUid === null || $kryteria->pismoUid === '')) {
            return [];
        }

        $sql = $this->buildListSql($kryteria, $scope, withPagination: $kryteria->isGlobal);

        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $this->bindings),
        );
    }

    public function getListCount(KryteriaPrzypisanRejestrowRpw $kryteria, ?WorkstationScopeResult $scope = null): int
    {
        $this->bindings = [];

        if (!$kryteria->isGlobal && ($kryteria->pismoUid === null || $kryteria->pismoUid === '')) {
            return 0;
        }

        $whereSql = $this->buildWhereSql($kryteria, $scope);
        $sql = <<<SQL
            SELECT COUNT(DISTINCT rz.id) AS count
            FROM eurzad_rejestr_pism_wych rpw
            INNER JOIN eurzad_rejestr_zawartosc rz ON rpw.rejestr_zawartosc_id = rz.id
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
        $this->bindings = [];
        $whereSql = 'rz.id = ' . $this->bind($registryAssignmentId)
            . ' AND r.rejestr_typ = ' . $this->bind(self::REGISTRY_TYPE_RPW);

        $row = DB::selectOne(
            $this->buildSelectFromSql(distinct: false) . "\nWHERE {$whereSql}",
            $this->bindings,
        );

        return $row !== null ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRpwExtensionByAssignmentId(int $registryAssignmentId): ?array
    {
        $row = DB::selectOne(<<<SQL
            SELECT
                rpw.petent_uid,
                rpw.forma_doreczenia,
                rpw.data_wyslania,
                rpw.nr_nadawczy,
                rpw.rejestr_zawartosc_id
            FROM eurzad_rejestr_pism_wych rpw
            INNER JOIN eurzad_rejestr_zawartosc rz ON rpw.rejestr_zawartosc_id = rz.id
            INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
            WHERE rz.id = ?
              AND r.rejestr_typ = ?
        SQL, [$registryAssignmentId, self::REGISTRY_TYPE_RPW]);

        return $row !== null ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFormaDoreczeniaByKlucz(string $klucz): ?array
    {
        $row = DB::selectOne(<<<SQL
            SELECT
                ep.klucz,
                ep.nazwa
            FROM eurzad2_przesylka ep
            WHERE ep.klucz = ?
        SQL, [$klucz]);

        return $row !== null ? (array) $row : null;
    }

    /**
     * @return array<int, object>
     */
    public function getHistoriaObieguByUidPrzesylki(string $uidPrzesylki, string $pismoUid): array
    {
        return DB::select(<<<SQL
            SELECT
                ero.rejestr_zawartosc_uid,
                ero.createdate,
                (
                    SELECT p.instance_id
                    FROM eurzad_pismo p
                    WHERE p.pismo_uid = ?
                    ORDER BY p.pismo_wersja DESC
                    LIMIT 1
                ) AS instance_id,
                ss.opis AS status_opis,
                ero.uugid_from,
                ero.uugid_to,
                ero.added_automatically
            FROM eurzad_rejestr_obieg ero
            INNER JOIN eurzad_slownik_status ss ON ero.status = ss.symbol
            WHERE ero.rejestr_zawartosc_uid = ?
            ORDER BY ero.rejestr_obieg_id DESC
        SQL, [$pismoUid, $uidPrzesylki]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPrzesylkaElektronicznaByZawartoscId(int $rejestrZawartoscId): ?array
    {
        $row = DB::selectOne(<<<SQL
            SELECT
                en.rpw_shipment_id,
                en.status,
                en.data_wyslania
            FROM en_rpw en
            WHERE en.rpw_shipment_id = ?
            ORDER BY en.id DESC
            LIMIT 1
        SQL, [$rejestrZawartoscId]);

        return $row !== null ? (array) $row : null;
    }

    private function buildListSql(
        KryteriaPrzypisanRejestrowRpw $kryteria,
        ?WorkstationScopeResult $scope,
        bool $withPagination,
    ): string {
        $whereSql = $this->buildWhereSql($kryteria, $scope);
        $sql = $this->buildSelectFromSql(distinct: true)
            . "\nWHERE {$whereSql}"
            . "\nORDER BY rz.rejestr_zawartosc_createdate DESC, rz.id DESC";

        if ($withPagination) {
            $sql .= "\nLIMIT " . $this->bind($kryteria->paginacja->limit);
            $sql .= "\nOFFSET " . $this->bind($kryteria->paginacja->offset);
        }

        return $sql;
    }

    private function buildSelectFromSql(bool $distinct): string
    {
        $distinctKeyword = $distinct ? 'DISTINCT ' : '';

        return <<<SQL
            SELECT {$distinctKeyword}
                rz.id AS registry_assignment_id,
                rz.rejestr_zawartosc_uid AS registry_assignment_uid,
                rpw.pismo_uid AS document_id,
                rz.rejestr_zawartosc_numeracja AS registry_assignment_number,
                rz.rejestr_zawartosc_typ AS registry_assignment_type,
                rz.rejestr_uid AS registry_uid,
                r.rejestr_typ AS registry_type,
                r.rejestr_opis AS registry_description,
                rz.rejestr_zawartosc_createdate AS created_at,
                rz.rejestr_zawartosc_parent_uid AS parent_shipment_uid
            FROM eurzad_rejestr_pism_wych rpw
            INNER JOIN eurzad_rejestr_zawartosc rz ON rpw.rejestr_zawartosc_id = rz.id
            INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
        SQL;
    }

    private function buildWhereSql(KryteriaPrzypisanRejestrowRpw $kryteria, ?WorkstationScopeResult $scope): string
    {
        $conditions = [
            'r.rejestr_typ = ' . $this->bind(self::REGISTRY_TYPE_RPW),
        ];

        if ($kryteria->pismoUid !== null && $kryteria->pismoUid !== '') {
            $conditions[] = 'rpw.pismo_uid = ' . $this->bind($kryteria->pismoUid);
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

        return implode(' AND ', $conditions);
    }

    /**
     * @param string[] $conditions
     */
    private function appendGlobalScopeConditions(array &$conditions, ?WorkstationScopeResult $scope): void
    {
        if ($scope === null || $scope->isUnrestricted) {
            return;
        }

        if ($scope->expandedWorkstationIds === []) {
            $conditions[] = '1 = 0';

            return;
        }

        $conditions[] = 'rz.workstation IN (' . $this->bindIntList($scope->expandedWorkstationIds) . ')';
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
