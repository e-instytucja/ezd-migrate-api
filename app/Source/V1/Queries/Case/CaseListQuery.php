<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use App\Source\V1\DTO\Request\ApiKonfiguracja;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;
use App\Source\V1\DTO\Request\SortowanieSpraw;
use App\Source\V1\DTO\Request\TypFiltrSpraw;
use Illuminate\Support\Facades\DB;

class CaseListQuery implements CaseListQueryInterface
{
    /** @var array<int, mixed> */
    private array $bindings = [];

    public function getList(KryteriaWyszukiwaniaSpraw $criteria): array
    {
        $this->bindings = [];

        $sql = <<<SQL
                SELECT
                    {$this->getSelectSql()}
                FROM eurzad_teczka et
                    {$this->getListInnerJoinSql()}
                    {$this->getLeftJoinSql($criteria->filtry)}
                WHERE
                    {$this->getWhereSql($criteria->konfiguracja, $criteria->filtry, $criteria->dntas)}
                ORDER BY
                    {$this->getOrderSql($criteria->sortowanie)}
                LIMIT
                    {$this->getLimitSql($criteria->paginacja->limit)}
                OFFSET
                    {$this->getOffsetSql($criteria->paginacja->offset)}
        SQL;

        $rows = DB::select($sql, $this->bindings);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public function getListCount(KryteriaWyszukiwaniaSpraw $criteria): int
    {
        $this->bindings = [];

        $sql = <<<SQL
            SELECT
                COUNT(DISTINCT et.teczka_uid) AS count
            FROM eurzad_teczka et
                {$this->getCountInnerJoinSql()}
                {$this->getFilterJoinSql($criteria->filtry)}
            WHERE
                {$this->getWhereSql($criteria->konfiguracja, $criteria->filtry, $criteria->dntas)}
        SQL;

        $result = DB::select($sql, $this->bindings);

        return (int) $result[0]->count;
    }

    private function getWhereSql(ApiKonfiguracja $konfiguracja, TypFiltrSpraw $filtry, int $dntas): string
    {
        $conditions = ['et.dntas = ' . $dntas];

        $this->appendWorkstationScope($conditions, $konfiguracja, $filtry);

        if ($filtry->sprawaUid !== null) {
            $conditions[] = 'et.teczka_uid = ' . $this->bind($filtry->sprawaUid);
        }

        if ($filtry->rok !== null) {
            $conditions[] = 'et.teczka_rok_zalozenia = ' . $this->bind($filtry->rok);
        }

        if ($filtry->znak !== null) {
            $conditions[] = 'et.teczka_znak_sprawy ILIKE ' . $this->bind('%' . $filtry->znak . '%');
        }

        if ($filtry->oznaczenieDntas !== null) {
            $conditions[] = 'et.oznaczenie_dntas ILIKE ' . $this->bind('%' . $filtry->oznaczenieDntas . '%');
        }

        if ($filtry->statusProcesu !== null) {
            $conditions[] = 'eo.status = ' . $this->bind($filtry->statusProcesu);
        }

        if ($filtry->typFormularza !== null) {
            $conditions[] = 'ef.form_typ = ' . $this->bind($filtry->typFormularza->value);
        }

        if ($filtry->typProcesu !== null) {
            $conditions[] = 'ef.form_typ = ' . $this->bind($filtry->typProcesu->formTyp());
        }

        if ($filtry->nazwaProcesu !== null) {
            $conditions[] = 'gp.normalized_name = ' . $this->bind($filtry->nazwaProcesu);
        }

        if ($filtry->documentId !== null) {
            $conditions[] = 'es.sprawa_uid = ' . $this->bind($filtry->documentId);
        }

        if ($filtry->opisDokumentu !== null) {
            $conditions[] = $this->opisDokumentuCondition($filtry->opisDokumentu);
        }

        if ($filtry->dataRejestracjiOd !== null) {
            $conditions[] = $this->dataRejestracjiFromCondition($filtry->dataRejestracjiOd);
        }

        if ($filtry->dataRejestracjiDo !== null) {
            $conditions[] = $this->dataRejestracjiToCondition($filtry->dataRejestracjiDo);
        }

        if ($filtry->wlascicielStanowisko !== null) {
            $conditions[] = $this->buildWorkstationCondition(
                [$filtry->wlascicielStanowisko],
                $filtry->pokazUdostepnione !== null,
            );
        }

        if ($filtry->tytulSprawy !== null) {
            $conditions[] = 'et.tytul_sprawy ILIKE ' . $this->bind('%' . $filtry->tytulSprawy . '%');
        }

        if ($filtry->interesant !== null) {
            $conditions[] = '(ps_petent.view_podmiot ILIKE ' . $this->bind('%' . $filtry->interesant . '%') .
            ' OR ps_petent.view_adres_korespondencyjny ILIKE ' . $this->bind('%' . $filtry->interesant . '%') . ')';
        }

        if ($filtry->dataWszczeciaOd !== null) {
            $conditions[] = 'et.teczka_createdate >= ' . $this->bind($filtry->dataWszczeciaOd . ' 00:00:00');
        }

        if ($filtry->dataWszczeciaDo !== null) {
            $conditions[] = 'et.teczka_createdate <= ' . $this->bind($filtry->dataWszczeciaDo . ' 23:59:59');
        }

        return implode("\n                    AND ", $conditions);
    }

    /**
     * @param string[] $conditions
     */
    private function appendWorkstationScope(
        array &$conditions,
        ApiKonfiguracja $konfiguracja,
        TypFiltrSpraw $filtry
    ): void
    {
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
            throw new \InvalidArgumentException('Workstation IDs cannot be empty');
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

    private function getFilterJoinSql(TypFiltrSpraw $filtry): string
    {
        $joins = '';

        if ($filtry->requiresInteresantJoin()) {
            $joins .= <<<SQL

                LEFT JOIN eurzad_form_dane fd_petent
                       ON (fd_petent.sprawa_uid = es.sprawa_uid AND fd_petent.form_dane_pole = 'petent_uid' AND fd_petent.form_dane_wartosc != '')
                LEFT JOIN eurzad_petent_search ps_petent ON (ps_petent.main_petent_uid = fd_petent.form_dane_wartosc)
            SQL;
        }

        return $joins . $this->getFormDaneJoinSql($filtry);
    }

    private function getFormDaneJoinSql(TypFiltrSpraw $filtry): string
    {
        $joins = '';

        if ($filtry->requiresDataRejJoin()) {
            $joins .= <<<SQL

                LEFT JOIN eurzad_form_dane fd_data_rej
                       ON (fd_data_rej.sprawa_uid = es.sprawa_uid AND fd_data_rej.form_dane_pole = 'data' AND fd_data_rej.form_dane_wartosc != '')
            SQL;
        }

        if ($filtry->requiresOpisJoin()) {
            $joins .= <<<SQL

                LEFT JOIN eurzad_form_dane fd_tytul
                       ON (fd_tytul.sprawa_uid = es.sprawa_uid AND fd_tytul.form_dane_pole = 'dokument_tytul' AND fd_tytul.form_dane_wartosc != '')
                LEFT JOIN eurzad_form_dane fd_tresc_wniosku
                       ON (fd_tresc_wniosku.sprawa_uid = es.sprawa_uid AND fd_tresc_wniosku.form_dane_pole = 'tresc_wniosku' AND fd_tresc_wniosku.form_dane_wartosc != '')
            SQL;
        }

        return $joins;
    }

    private function opisDokumentuCondition(string $opis): string
    {
        $pattern = '%' . $opis . '%';
        $bindTresc = $this->bind($pattern);
        $bindTytul = $this->bind($pattern);

        return <<<SQL
    (
        fd_tresc_wniosku.form_dane_wartosc ILIKE {$bindTresc}
        OR (fd_tytul.form_dane_wartosc::jsonb)->>'textarea' ILIKE {$bindTytul}
    )
SQL;
    }

    private function dataRejestracjiFromCondition(string $dataOd): string
    {
        return <<<SQL
COALESCE(
    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
    esp.sprawa_createdate
) >=
SQL . $this->bind($dataOd . ' 00:00:00');
    }

    private function dataRejestracjiToCondition(string $dataDo): string
    {
        return <<<SQL
COALESCE(
    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
    esp.sprawa_createdate
) <=
SQL . $this->bind($dataDo . ' 23:59:59');
    }

    private function bind(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }

    private function getOrderSql(SortowanieSpraw $sortowanie): string
    {
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

    private function getSelectSql(): string
    {
        return <<<SQL
                et.teczka_uid                                                 AS id_sprawy,
                et.teczka_znak_sprawy                                         AS znak,
                et.sprawa_uid                                                 AS main_document_uid,
                esp.sprawa_createdate                                         AS data_rejestracji_dokumentu,
                esp.czas_realizacji                                           AS czas_realizacji,
                esp.sprawa_finishdate                                         AS sprawa_finishdate,
                eo.status                                                     AS status,
                es.sprawa_createdate                                          AS data_utworzenia_dokumentu,
                gp.name                                                       AS nazwa_procesu,
                gp.normalized_name                                            AS nazwa_procesu_znormalizowana,
                gp."pId"                                                      AS id_procesu,
                ef.form_typ                                                   AS typ_formularza,
                ess.opis                                                      AS status_procesu,
                et.teczka_createdate                                          AS data_wszczecia,
                et.opis_sprawy,
                et.tytul_sprawy,
                et.oznaczenie_dntas,
                -- COALESCE(fd_tytul.form_dane_wartosc::json ->> 'textarea', '') AS dokument_tytul,
                fd_pliki.form_dane_wartosc                                    AS zalaczniki,
                ps_petent.view_podmiot as interesant,
                ps_petent.view_adres_korespondencyjny as interesant_adres,
                pd_petent.typ_osoby as interesant_type,
                EXISTS (
                    SELECT 1
                    FROM eurzad_form_dane fd
                    WHERE fd.sprawa_uid = es.sprawa_uid
                      AND fd.form_dane_pole = 'interesanci'
                      AND NULLIF(TRIM(fd.form_dane_wartosc), '') IS NOT NULL
                ) AS has_pozostali_interesanci,
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

    private function getCountInnerJoinSql(): string
    {
        return <<<SQL
                INNER JOIN eurzad_sprawa es ON es.sprawa_uid = et.sprawa_uid
                INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
                INNER JOIN eurzad_form ef ON (gp.normalized_name = ef.form_name)
                INNER JOIN eurzad_obieg eo ON (eo.sprawa_uid = es.sprawa_uid AND eo.max_status_sprawy_id > 0)
                INNER JOIN eurzad_slownik_status ess ON ess.symbol = eo.status
                INNER JOIN galaxia_instances gi ON gi."instanceId" = eo."instanceId"
                INNER JOIN eurzad_sprawa_przedluzanie esp ON esp.sprawa_uid = es.sprawa_uid
        SQL;
    }

    private function getListInnerJoinSql(): string
    {
        return $this->getCountInnerJoinSql() . <<<SQL

                INNER JOIN users_groups ug_w ON (ug_w.group_id = gi.workstation)
                INNER JOIN users_groups ug_g ON (ug_g.group_id = ug_w.parent_group_id)
                INNER JOIN users_usergroups uug ON (uug.group_id = ug_w.group_id AND uug.status = 'A' AND uug.typ = 'Z')
                INNER JOIN users_users uu ON (uu."userId" = uug."userId")
        SQL;
    }

    private function getInnerJoinSql(): string
    {
        return $this->getListInnerJoinSql();
    }

    /*
        musiałem wykonać taki sql - żeby "INNER JOIN eurzad_obieg eo ON (eo.sprawa_uid = es.sprawa_uid AND eo.max_status_sprawy_id > 0)"
        zadziałał prawidłowo.
        był błąd w bazie danych (pewnie w kolejnych wersjach jakaś była na to poprawka)
        przez ten błąd - dublowały się wpisy.
WITH ranked AS (
    SELECT
        eo.status_sprawy_id,
        eo.sprawa_uid,
        ROW_NUMBER() OVER (
            PARTITION BY eo.sprawa_uid
            ORDER BY eo.status_sprawy_id DESC
        ) AS rn
    FROM eurzad_obieg eo
    WHERE eo.sprawa_uid IN (
        SELECT sprawa_uid FROM eurzad_obieg
        WHERE max_status_sprawy_id > 0
        GROUP BY sprawa_uid
        HAVING COUNT(*) > 1
    )
)
UPDATE eurzad_obieg eo
SET max_status_sprawy_id = CASE WHEN r.rn = 1 THEN 1 ELSE 0 END
FROM ranked r
WHERE eo.status_sprawy_id = r.status_sprawy_id;
    */

    private function getLeftJoinSql(TypFiltrSpraw $filtry): string
    {
        return <<<SQL
                LEFT JOIN eurzad_form_dane fd_petent
                       ON (fd_petent.sprawa_uid = es.sprawa_uid AND fd_petent.form_dane_pole = 'petent_uid' AND fd_petent.form_dane_wartosc != '')
                LEFT JOIN eurzad_petent_dane pd_petent ON (
                        pd_petent.main_petent_uid = fd_petent.form_dane_wartosc 
                        AND pd_petent.petent_uid = pd_petent.main_petent_uid
                )
                LEFT JOIN eurzad_petent_search ps_petent ON (ps_petent.main_petent_uid = fd_petent.form_dane_wartosc)
                LEFT JOIN eurzad_form_dane fd_pliki
                       ON (fd_pliki.sprawa_uid = es.sprawa_uid AND fd_pliki.form_dane_pole = 'pliki' AND fd_pliki.form_dane_wartosc != '')
        SQL . $this->getFormDaneJoinSql($filtry);
    }
}
