<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\ApiKonfiguracja;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\DTO\Request\Sortowanie;
use App\Source\V1\DTO\Request\TypFiltrDokument;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentListQuery
{
    /** @var array<int, mixed> */
    private array $bindings = [];

    private const PISMA_INICJUJACE_WIODACE = 1;
    private const DOKUMENTY_W_SPRAWIE = 2;
    private const PISMA_INICJUJACE_W_SPRAWIE = 3;
    private const DOKUMENTY_ZWROT = 4;

    /** @var list<int> */
    private const UNION_TYPES = [
        self::PISMA_INICJUJACE_WIODACE,
        self::DOKUMENTY_W_SPRAWIE,
        self::PISMA_INICJUJACE_W_SPRAWIE,
        self::DOKUMENTY_ZWROT,
    ];

    private $idDokumentuSelect = 'DISTINCT ON (id_dokumentu)';

//    private $idDokumentuSelect = 'id_dokumentu';

    public function getList(KryteriaWyszukiwaniaDokumentow $criteria): array
    {
        $this->bindings = [];

        $rows = DB::select($this->buildUnionsSql($criteria), $this->bindings);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public function getListCount(KryteriaWyszukiwaniaDokumentow $criteria): int
    {
        $this->bindings = [];

        $parts = array_map(
            fn (int $type) => '(' . $this->buildUnionPart($type, $criteria) . ')',
            self::UNION_TYPES,
        );

        $sql = <<<SQL
            SELECT COUNT(*) AS count
            FROM (
                {$this->implodeUnions($parts)}
            ) AS documents
        SQL;

        $result = DB::select($sql, $this->bindings);

        return (int) $result[0]->count;
    }

    public function getListByTeczkaUid(string $teczkaUid, int $dntas = 0): array
    {
        return $this->getList(KryteriaWyszukiwaniaDokumentow::forTeczkaUid($teczkaUid, $dntas));
    }

    private function buildUnionsSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $parts = array_map(
            fn (int $type) => '(' . $this->buildUnionPart($type, $criteria) . ')',
            self::UNION_TYPES,
        );

        $sql = $this->implodeUnions($parts);
        $sql .= "\nORDER BY document_group_type ASC";

        if (!$criteria->filtry->isScopedToTeczka()) {
            $sql .= "\nLIMIT " . $this->getLimitSql($criteria->paginacja->limit);
            $sql .= "\nOFFSET " . $this->getOffsetSql($criteria->paginacja->offset);
        }

        return $sql;
    }

    private function buildUnionPart(int $type, KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        return match ($type) {
            self::PISMA_INICJUJACE_WIODACE => $this->pismaInicjujaceWiodaceSql($criteria),
            self::DOKUMENTY_W_SPRAWIE => $this->dokumentyWSprawieSql($criteria),
            self::PISMA_INICJUJACE_W_SPRAWIE => $this->pismaInicjujaceWSprawieSql($criteria),
            self::DOKUMENTY_ZWROT => $this->dokumentyZwrotSql($criteria),
            default => throw new InvalidArgumentException("Nieprawidłowy typ UNION: {$type}"),
        };
    }

    private function pismaInicjujaceWiodaceSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $where = $this->getWhereSql(
            self::PISMA_INICJUJACE_WIODACE,
            $criteria->konfiguracja,
            $criteria->filtry,
            $criteria->dntas,
        );

        return <<<SQL
            SELECT
                $this->idDokumentuSelect
                es.sprawa_uid AS id_dokumentu,
                NULL AS wersja,
                {$this->getCommonSelectSql()},
                {$this->documentGroupNumber(self::PISMA_INICJUJACE_WIODACE)} AS document_group_type
            FROM eurzad_sprawa es
                {$this->pismoObiegJoinsSql()}
                {$this->getCommonJoinSql()}
                {$this->teczkaJoinsSql(self::PISMA_INICJUJACE_WIODACE, $criteria)}
                {$this->getFilterJoinSql(self::PISMA_INICJUJACE_WIODACE, $criteria->filtry)}
            WHERE
                {$where}
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function dokumentyWSprawieSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $where = $this->getWhereSql(
            self::DOKUMENTY_W_SPRAWIE,
            $criteria->konfiguracja,
            $criteria->filtry,
            $criteria->dntas,
        );

        return <<<SQL
            SELECT
                $this->idDokumentuSelect
                ep.pismo_uid AS id_dokumentu,
                ep.pismo_wersja AS wersja,
                {$this->getCommonSelectSql()},
                {$this->documentGroupNumber(self::DOKUMENTY_W_SPRAWIE)} AS document_group_type
            FROM eurzad_pismo ep
                INNER JOIN galaxia_instances gi ON gi."instanceId" = ep.instance_id
                INNER JOIN galaxia_processes gp ON gp."pId" = gi."pId"
                INNER JOIN LATERAL (
                    SELECT epo.*
                    FROM eurzad_pismo_obieg epo
                    WHERE epo.pismo_uid = ep.pismo_uid
                    ORDER BY epo.pismo_obieg_id DESC
                    LIMIT 1
                ) epo ON true
                INNER JOIN eurzad_slownik_status ess ON ess.symbol = epo.status
                {$this->getCommonJoinSql()}
                
                {$this->teczkaJoinsSql(self::DOKUMENTY_W_SPRAWIE, $criteria)}
                {$this->getFilterJoinSql(self::DOKUMENTY_W_SPRAWIE, $criteria->filtry)}
            WHERE
                {$where}
            ORDER BY id_dokumentu ASC, epo.pismo_obieg_id DESC
        SQL;
    }




    private function pismaInicjujaceWSprawieSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $where = $this->getWhereSql(
            self::PISMA_INICJUJACE_W_SPRAWIE,
            $criteria->konfiguracja,
            $criteria->filtry,
            $criteria->dntas,
        );

        return <<<SQL
            SELECT
                $this->idDokumentuSelect
                es.sprawa_uid AS id_dokumentu,
                NULL AS wersja,
                {$this->getCommonSelectSql()},
                {$this->documentGroupNumber(self::PISMA_INICJUJACE_W_SPRAWIE)} AS document_group_type
            FROM eurzad_sprawa es
                {$this->pismoObiegJoinsSql()}
                {$this->getCommonJoinSql()}
                {$this->teczkaJoinsSql(self::PISMA_INICJUJACE_W_SPRAWIE, $criteria)}
                {$this->getFilterJoinSql(self::PISMA_INICJUJACE_W_SPRAWIE, $criteria->filtry)}
            WHERE
                {$where}
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function dokumentyZwrotSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $where = $this->getWhereSql(
            self::DOKUMENTY_ZWROT,
            $criteria->konfiguracja,
            $criteria->filtry,
            $criteria->dntas,
        );

        return <<<SQL
            SELECT
                $this->idDokumentuSelect
                es.sprawa_uid AS id_dokumentu,
                NULL AS wersja,
                {$this->getCommonSelectSql()},
                {$this->documentGroupNumber(self::DOKUMENTY_ZWROT)} AS document_group_type
            FROM eurzad_sprawa es
                {$this->pismoObiegJoinsSql()}
                {$this->getCommonJoinSql()}
                {$this->teczkaJoinsSql(self::DOKUMENTY_ZWROT, $criteria)}
                {$this->getFilterJoinSql(self::DOKUMENTY_ZWROT, $criteria->filtry)}
            WHERE
                {$where}
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function getCommonJoinSql()
    {
        return <<<SQL
            INNER JOIN users_groups ug_w ON (ug_w.group_id = gi.workstation)
            INNER JOIN users_groups ug_g ON (ug_g.group_id = ug_w.parent_group_id)
            INNER JOIN users_usergroups uug ON (uug.group_id = ug_w.group_id AND uug.status = 'A' AND uug.typ = 'Z')
            INNER JOIN users_users uu ON (uu."userId" = uug."userId")
SQL;

    }
    private function getCommonSelectSql()
    {
        return <<<SQL
                gp.name AS nazwa_procesu,
                gp."pId" AS id_procesu,
                ess.opis AS status_procesu,
                gp.typ AS typ,
                et.teczka_znak_sprawy as znak_sprawy,
                gi.workstation as wlasciciel_stanowisko_id,
                ug_w."groupName" as wlasciciel_stanowisko_skrot,
                ug_w."groupDesc" as wlasciciel_stanowisko_nazwa,
                ug_g."groupName" as wlasciciel_komorka_skrot,
                ug_g."groupDesc" as wlasciciel_komorka_nazwa,
                CONCAT_WS(
                   ' ',
                   uu.forename,
                   uu.surname,
                   NULLIF(uu.surname2, ''),
                   NULLIF(uu.surname3, '')
                )  as wlasciciel_imie_nazwisko
SQL;

    }

    private function teczkaJoinsSql(int $type, KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $join = $criteria->filtry->isScopedToTeczka() ? 'INNER JOIN' : 'LEFT JOIN';

        return match ($type) {
            self::PISMA_INICJUJACE_WIODACE => <<<SQL
                {$join} eurzad_teczka et ON es.sprawa_uid = et.sprawa_uid
            SQL,
            self::DOKUMENTY_W_SPRAWIE => <<<SQL
                {$join} eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = ep.pismo_uid
                {$join} eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            SQL,
            self::PISMA_INICJUJACE_W_SPRAWIE => <<<SQL
                {$join} eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = es.sprawa_uid
                {$join} eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            SQL,
            self::DOKUMENTY_ZWROT => <<<SQL
                {$join} eurzad_teczka_zawartosc etz2 ON etz2.teczka_zawartosc_uid = es.sprawa_uid
                {$join} eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = etz2.teczka_uid
                {$join} eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            SQL,
            default => throw new InvalidArgumentException("Nieprawidłowy typ UNION: {$type}"),
        };
    }

    private function pismoObiegJoinsSql(): string
    {
        return <<<SQL
                INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
                INNER JOIN eurzad_obieg eo ON eo.sprawa_uid = es.sprawa_uid
                INNER JOIN eurzad_slownik_status ess ON ess.symbol = eo.status
                INNER JOIN galaxia_instances gi ON gi."instanceId" = eo."instanceId" AND max_status_sprawy_id > 0
                INNER JOIN eurzad_sprawa_przedluzanie sp ON sp.sprawa_uid = es.sprawa_uid
        SQL;
    }

    private function getFilterJoinSql(int $unionType, TypFiltrDokument $filtry): string
    {
        if (!$filtry->requiresOpisJoin()) {
            return '';
        }

        if ($unionType === self::DOKUMENTY_W_SPRAWIE) {
            return '';
        }

        // TODO: filtrowanie po opisie dokumentu
        return <<<SQL
                LEFT JOIN eurzad_form_dane fd_opis
                       ON (fd_opis.sprawa_uid = es.sprawa_uid AND fd_opis.form_dane_pole = 'dokument_tytul')
        SQL;
    }

    private function getWhereSql(
        int $unionType,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry,
        int $dntas,
    ): string {
        if ($filtry->isScopedToTeczka()) {
            return $this->getScopedWhereSql($filtry, $dntas);
        }

        return $this->getGlobalWhereSql($unionType, $konfiguracja, $filtry, $dntas);
    }

    private function getScopedWhereSql(TypFiltrDokument $filtry, int $dntas): string
    {
        $conditions = [
            'et.teczka_uid = ' . $this->bind($filtry->teczkaUid),
            'et.dntas = ' . $dntas,
        ];

        return implode("\n                AND ", $conditions);
    }

    private function getGlobalWhereSql(
        int $unionType,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry,
        int $dntas,
    ): string {
        $conditions = [
            '(et.teczka_uid IS NULL OR et.dntas = ' . $dntas . ')',
        ];

        $this->appendWorkstationScope($conditions, $konfiguracja, $filtry);

        if ($filtry->idProcesu !== null) {
            $conditions[] = 'gp."pId" = ' . $this->bind($filtry->idProcesu);
        }

        if ($filtry->statusProcesu !== null) {
            $conditions[] = match ($unionType) {
                self::DOKUMENTY_W_SPRAWIE => 'epo.status = ' . $this->bind($filtry->statusProcesu),
                default => 'eo.status = ' . $this->bind($filtry->statusProcesu),
            };
        }

        if ($filtry->wlascicielStanowisko !== null) {
            $conditions[] = $this->buildWorkstationCondition(
                [$filtry->wlascicielStanowisko],
                $filtry->pokazUdostepnione !== null,
            );
        }

        if ($filtry->dataOd !== null) {
            $conditions[] = $this->dateFromCondition($unionType, $filtry->dataOd);
        }

        if ($filtry->dataDo !== null) {
            $conditions[] = $this->dateToCondition($unionType, $filtry->dataDo);
        }

        if ($filtry->przesylka !== null) {
            // TODO: mapowanie wartości przesylka → gp.typ
            $conditions[] = 'gp.typ = ' . $this->bind($filtry->przesylka);
        }

        if ($filtry->opisDokumentu !== null) {
            // TODO: filtrowanie po opisie dokumentu
            $conditions[] = 'fd_opis.form_dane_wartosc ILIKE ' . $this->bind('%' . $filtry->opisDokumentu . '%');
        }

        return implode("\n                AND ", $conditions);
    }

    private function dateFromCondition(int $unionType, string $dataOd): string
    {
        return match ($unionType) {
            self::DOKUMENTY_W_SPRAWIE => 'ep.pismo_createdate >= ' . $this->bind($dataOd . ' 00:00:00'),
            default => 'sp.sprawa_createdate >= ' . $this->bind($dataOd . ' 00:00:00'),
        };
    }

    private function dateToCondition(int $unionType, string $dataDo): string
    {
        return match ($unionType) {
            self::DOKUMENTY_W_SPRAWIE => 'ep.pismo_createdate <= ' . $this->bind($dataDo . ' 23:59:59'),
            default => 'sp.sprawa_createdate <= ' . $this->bind($dataDo . ' 23:59:59'),
        };
    }

    /**
     * @param string[] $conditions
     */
    private function appendWorkstationScope(
        array &$conditions,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry,
    ): void {
        if ($konfiguracja->madkomWorkstationIds === []) {
            throw new \Exception('Brak wskazanych wlascicieli [err_10_appendWorkstationScope]');
        }

        $conditions[] = $this->buildWorkstationCondition(
            $konfiguracja->madkomWorkstationIds,
            $filtry->pokazUdostepnione !== null,
        );
    }

    /**
     * @param int[] $workstationIds
     */
    private function buildWorkstationCondition(array $workstationIds, bool $includeShared): string
    {
        if ($workstationIds === []) {
            throw new InvalidArgumentException('Workstation IDs cannot be empty');
        }

        $placeholders = implode(', ', array_map(
            fn (int $id) => $this->bind($id),
            $workstationIds,
        ));
        $ownerCondition = "gi.workstation IN ({$placeholders})";

        if (!$includeShared) {
            return $ownerCondition;
        }

        $sharedPlaceholders = implode(', ', array_map(
            fn (int $id) => $this->bind($id),
            $workstationIds,
        ));

        return <<<SQL
                ( {$ownerCondition} OR
                EXISTS (
                        SELECT 1
                        FROM galaxia_instance_users giu
                        WHERE giu.instance_id = gi."instanceId"
                          AND giu.workstation IN ({$sharedPlaceholders})
                   )
                )
        SQL;
    }

    /**
     * @param list<string> $parts
     */
    private function implodeUnions(array $parts): string
    {
        return implode("\nUNION\n", $parts);
    }

    private function documentGroupNumber(int $type): int
    {
        return $type;
    }

    private function bind(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }

    private function getOrderSql(Sortowanie $sortowanie): string
    {
        // TODO: sortowanie dokumentów — obecne Sortowanie::FIELD_COLUMNS dotyczy spraw
        return $sortowanie->toOrderBySql();
    }

    private function getLimitSql(int $limit): int
    {
        return $limit;
    }

    private function getOffsetSql(int $offset): int
    {
        return $offset;
    }
}
