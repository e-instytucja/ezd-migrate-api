<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use Illuminate\Support\Facades\DB;

class CaseQuery
{

    public function getList(int $offset = 0, int $limit = 50)
    {
        $sql = <<<SQL
                SELECT
                    {$this->getSelectSql()}
                FROM eurzad_teczka et
                    {$this->getInnerJoinSql()}
                    {$this->getLeftJoinSql()}
                WHERE
                    {$this->getWhereSql()}
                ORDER BY
                    {$this->getOrderSql()}
                LIMIT
                    {$this->getLimitSql($limit)}
                OFFSET
                    {$this->getOffsetSql($offset)}
        SQL;

        $rows = DB::select($sql);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public function getListCount(): int
    {
        $sql = <<<SQL
            SELECT
                COUNT(et.teczka_uid) AS count
            FROM eurzad_teczka et
                {$this->getInnerJoinSql()}
            WHERE
                {$this->getWhereSql()}
        SQL;

        $result = DB::select($sql);

        return (int) $result[0]->count;
    }

    private function getWhereSql(): string
    {
        $where = '1 = 1 ';
        $where .= <<<SQL
AND EXISTS (
    SELECT 1
    FROM eurzad_form_dane fd
    WHERE fd.sprawa_uid = es.sprawa_uid
      AND fd.form_dane_pole = 'interesanci'
      AND NULLIF(TRIM(fd.form_dane_wartosc), '') IS NOT NULL
)
SQL;
        return $where;


    }

    private function getOrderSql(): string
    {
        return 'et.teczka_createdate DESC';
    }

    private function getLimitSql(int $limit): int
    {
        return $limit;
    }

    private function getOffsetSql(int $offset): int
    {
        return $offset;
    }

    private function getSelectSql()
    {
        return <<<SQL
                et.teczka_uid                                                 AS id_sprawy,
                et.teczka_znak_sprawy                                         AS znak,
                et.sprawa_uid                                                 AS main_document_uid,
                gp.name                                                       AS nazwa_procesu,
                gp."pId"                                                      AS id_procesu,
                ess.opis                                                      AS status_procesu,
                et.teczka_createdate                                          AS data_wszczecia,
                et.opis_sprawy,
                et.tytul_sprawy,
                et.oznaczenie_dntas,
                -- COALESCE(fd_tytul.form_dane_wartosc::json ->> 'textarea', '') AS dokument_tytul,
                fd_pliki.form_dane_wartosc                                    AS zalaczniki,
                ps_petent.view_podmiot as interesant,
                ps_petent.view_adres_korespondencyjny as interesant_adres,
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
    private function getInnerJoinSql()
    {
        return <<<SQL
                INNER JOIN eurzad_sprawa es ON es.sprawa_uid = et.sprawa_uid
                INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
                INNER JOIN eurzad_obieg eo ON (eo.sprawa_uid = es.sprawa_uid AND eo.max_status_sprawy_id > 0)
                INNER JOIN eurzad_slownik_status ess ON ess.symbol = eo.status
                INNER JOIN galaxia_instances gi ON gi."instanceId" = eo."instanceId"
                INNER JOIN eurzad_sprawa_przedluzanie sp ON sp.sprawa_uid = es.sprawa_uid
                INNER JOIN users_groups ug_w ON (ug_w.group_id = gi.workstation)
                INNER JOIN users_groups ug_g ON (ug_g.group_id = ug_w.parent_group_id)
                INNER JOIN users_usergroups uug ON (uug.group_id = ug_w.group_id AND uug.status = 'A' AND uug.typ = 'Z')
                INNER JOIN users_users uu ON (uu."userId" = uug."userId")
        SQL;
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

         *
         */
    }
    private function getLeftJoinSql()
    {
        return <<<SQL
                -- LEFT JOIN eurzad_form_dane fd_tytul
                --         ON (fd_tytul.sprawa_uid = es.sprawa_uid AND fd_tytul.form_dane_pole = 'dokument_tytul')
                LEFT JOIN eurzad_form_dane fd_petent
                       ON (fd_petent.sprawa_uid = es.sprawa_uid AND fd_petent.form_dane_pole = 'petent_uid')
                LEFT JOIN eurzad_petent_search ps_petent ON (ps_petent.main_petent_uid = fd_petent.form_dane_wartosc)
                LEFT JOIN eurzad_form_dane fd_pliki
                       ON (fd_pliki.sprawa_uid = es.sprawa_uid AND fd_pliki.form_dane_pole = 'pliki')
        SQL;

    }
    public function getTeczkaSyg($uid, $dntas = 0)
    {
        return DB::table('eurzad_teczka')
            ->where('sprawa_uid', $uid)
            ->where('dntas', $dntas)
            ->value('teczka_znak_sprawy');
    }

    public function getCaseUidByMainDocumentUid($mainDocumentUid)
    {
        return DB::table('eurzad_teczka')
            ->where('sprawa_uid', $mainDocumentUid)
            ->where('dntas', 0)
            ->value('teczka_uid');
    }
    public function getStatus($uid)
    {
        $symbol = $this->getStatusSymbol($uid);

        $status = DB::table('eurzad_slownik_status')
            ->where('symbol', $symbol)
            ->value('opis');
        if(empty($status)) {
            throw new \Exception("Brak danych dla statusu {$symbol}");
        }
        return $status;
    }



    private function getStatusSymbol($uid)
    {
        $status = DB::table('eurzad_obieg')
            ->where('sprawa_uid', $uid)
            ->value('status');

        if(empty($status)) {
            throw new \Exception("Brak danych dla statusu {$status}");
        }
        return $status;
    }

    public function getSprawaPrzedluzanie($mainDocumentUid, $column)
    {
        $createdate = DB::table('eurzad_sprawa_przedluzanie')
            ->where('sprawa_uid', $mainDocumentUid)
            ->value($column);

        if(empty($createdate)) {
            throw new \Exception("Brak danych dla sprawy {$mainDocumentUid}");
        }

        return $createdate;
    }

    public function getTeczkaCreateDateByCaseId($caseId = '')
    {
        $createdate = DB::table('eurzad_sprawa')
            ->where('sprawa_uid', $caseId)
            ->value('sprawa_createdate');
        if(empty($createdate)) {
            throw new \Exception("Brak danych dla sprawy {$caseId}");
        }
        return $createdate;
    }

    public function getFirstRowFromHistory(
        $mainDocumentUid,
        array $statuses = [],
        string $sortDirection = 'ASC'
    ) {
        $sortDirection = strtoupper($sortDirection);

        if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
            $sortDirection = 'ASC';
        }

        $query = DB::table('eurzad_obieg')
            ->where('sprawa_uid', $mainDocumentUid);

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query
            ->orderBy('status_sprawy_id', $sortDirection)
            ->first();
    }

    public function getSprawaUidByTeczkaZawartoscUid(
        $teczkaZawartoscUid,
        string $returnKeys = 'o."instanceId", o.sprawa_uid'
    ) {
        $allowedColumns = [
            'o."instanceId", o.sprawa_uid',
            'o.sprawa_uid',
            'o."instanceId"',
        ];

        if (!in_array($returnKeys, $allowedColumns, true)) {
            throw new \InvalidArgumentException('Invalid return keys');
        }

        return DB::table('eurzad_teczka_zawartosc as tz')
            ->distinct()
            ->selectRaw($returnKeys)
            ->join('eurzad_teczka as t', 't.teczka_uid', '=', 'tz.teczka_uid')
            ->join('eurzad_obieg as o', function ($join) {
                $join->on('o.sprawa_uid', '=', 't.sprawa_uid')
                    ->where('o.status_sprawy_id', '>', 0);
            })
            ->join('galaxia_instances as gi', 'gi.instanceId', '=', 'o.instanceId')
            ->where('tz.teczka_zawartosc_uid', $teczkaZawartoscUid)
            ->first();
    }

    public function getCaseOwnerByInstanceId($instanceId)
    {
        return DB::table('galaxia_instances')
            ->where('instanceId', $instanceId)
            ->value('workstation');
    }

    public function getAllFromTeczkaBySprawaUid(
        $uid
    ): object {
        $ret = DB::table('eurzad_teczka as t')
            ->leftJoin(
                'eurzad_teczka_podteczki as tp',
                't.podteczka_id',
                '=',
                'tp.id'
            )
            ->where('t.sprawa_uid', $uid)
            ->select(
                't.*',
                'tp.opis as opis_zbioru'
            )
            ->first();

        if ($ret) {
            return $ret;
        }

        return (object) [
            'opis_sprawy' => '',
            'opis_zbioru' => '',
        ];
    }

    public function getTitleAndDescription($caseUid): ?object
    {
        return DB::table('eurzad_teczka')
            ->where('sprawa_uid', $caseUid)
            ->first([
                'tytul_sprawy',
                'opis_sprawy',
            ]);
    }

    public function getFormNameByMainDocumentUid(
        $mainDocumentUid
    ): ?string {
        return DB::table('eurzad_sprawa')
            ->where('sprawa_uid', $mainDocumentUid)
            ->value('form_name');
    }

    public function getCaseOwnerByCaseUid($mainDocumentUid)
    {
        return $this->getCaseOwnerByInstanceId(
            $this->getInstanceIdByCaseUid($mainDocumentUid)
        );
    }

    public function getInstanceIdByCaseUid($caseUid): int
    {
        $row = $this->getFirstRowFromHistory($caseUid);
        return $row->instanceId;
    }

    public function getSprawaCreateDate($documentId = 0)
    {
        if (!$documentId) {
            return null;
        }

        $caseData = (array)DB::table('eurzad_sprawa')
            ->where('sprawa_uid', $documentId)
            ->first();

        if (empty($caseData) || $caseData['rodzaj_pisma'] != 'internal') {
            return null;
        }

        $hour = date('H', strtotime($caseData['sprawa_createdate']));
        if ((int)$hour > 0) {
            return $caseData['sprawa_createdate'];
        }

        $createdate = DB::table('eurzad_teczka_zawartosc')
            ->where('teczka_zawartosc_uid', $documentId)
            ->value('createdate');

        return !empty($createdate) ? $createdate : null;
    }

    public function getHistory($caseUid)
    {
        return DB::table('eurzad_obieg as o')
            ->join('eurzad_slownik_status as ss', 'o.status', '=', 'ss.symbol')
            ->where('o.sprawa_uid', $caseUid)
            ->select(
                'o.sprawa_uid',
                'o.createdate',
                'o.instanceId',
                'ss.opis as action',
                'o.uugid_from',
                'o.uugid_to'
            )
            ->orderBy('o.status_sprawy_id', 'DESC')
            ->get();
    }
}
