<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\DTO\Request\SortowanieDokumentow;
use App\Source\V1\DTO\Request\TypFiltrDokument;
use App\Source\V1\Enum\TypDokument;
use Illuminate\Support\Facades\DB;

class DocumentListQuery extends AbstractDocumentQuery
{
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
            fn (TypDokument $typDokumentu) => '(' . $this->buildUnionPart($typDokumentu, $criteria) . ')',
            $this->resolveDocumentTypes($criteria->filtry),
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

    private function buildUnionsSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $parts = array_map(
            fn (TypDokument $typDokumentu) => '(' . $this->buildUnionPart($typDokumentu, $criteria) . ')',
            $this->resolveDocumentTypes($criteria->filtry),
        );

        $sql = <<<SQL
            SELECT * FROM (
                {$this->implodeUnions($parts)}
            ) AS documents
        SQL;
        $sql .= "\nORDER BY " . $this->getOrderSql($criteria->sortowanie);
        $sql .= ", id_dokumentu ASC";

        if (!$criteria->filtry->isScopedToTeczka()) {
            $sql .= "\nLIMIT " . $this->getLimitSql($criteria->paginacja->limit);
            $sql .= "\nOFFSET " . $this->getOffsetSql($criteria->paginacja->offset);
        }

        return $sql;
    }

    private function buildUnionPart(TypDokument $typDokumentu, KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        return match ($typDokumentu) {
            TypDokument::DokPrzychodzacy => $this->pismaPrzychodzaceSql($criteria),
            TypDokument::DokWychodzacy => $this->dokumentyWSprawieSql($criteria),
            TypDokument::DokZpo => $this->pismaZwrotSql($criteria),
        };
    }

    private function pismaPrzychodzaceSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $typDokumentu = TypDokument::DokPrzychodzacy->value;
        $where = $this->getWhereSql(
            TypDokument::DokPrzychodzacy,
            $criteria->konfiguracja,
            $criteria->filtry
        );

        return <<<SQL
            SELECT
                $this->idDokumentuSelect
                {$this->commonSelectSql()},
                {$this->pismoSelectSql()},
                '{$typDokumentu}' AS typ_dokumentu
            FROM eurzad_sprawa es
                {$this->pismoInnerJoinsSql()}
                {$this->commonInnerJoinSql()}
                {$this->pismoLeftJoinsSql()}
                {$this->teczkaJoinsSql(TypDokument::DokPrzychodzacy, $criteria->filtry)}
            WHERE
                gp.name NOT IN ('zwrot', 'zwrotka') AND
                {$where}
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function dokumentyWSprawieSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $typDokumentu = TypDokument::DokWychodzacy->value;
        $where = $this->getWhereSql(
            TypDokument::DokWychodzacy,
            $criteria->konfiguracja,
            $criteria->filtry
        );

        return <<<SQL
            SELECT
                $this->idDokumentuSelect
                {$this->commonSelectSql()},
                {$this->dokumentSelectSql()},
                '{$typDokumentu}' AS typ_dokumentu
            FROM eurzad_pismo ep
                {$this->dokumentInnerJoinSql()}
                {$this->commonInnerJoinSql()}
                {$this->dokumentLeftJoinsSql()}
                {$this->teczkaJoinsSql(TypDokument::DokWychodzacy, $criteria->filtry)}
            WHERE
                {$where}
            ORDER BY id_dokumentu ASC, epo.pismo_obieg_id DESC
        SQL;
    }

    private function pismaZwrotSql(KryteriaWyszukiwaniaDokumentow $criteria): string
    {
        $typDokumentu = TypDokument::DokZpo->value;
        $where = $this->getWhereSql(
            TypDokument::DokZpo,
            $criteria->konfiguracja,
            $criteria->filtry,
        );

        return <<<SQL
            SELECT
                $this->idDokumentuSelect
                {$this->commonSelectSql()},
                {$this->pismoSelectSql()},
                '{$typDokumentu}' AS typ_dokumentu
            FROM eurzad_sprawa es
                {$this->pismoInnerJoinsSql()}
                {$this->commonInnerJoinSql()}
                {$this->pismoLeftJoinsSql()}
                {$this->teczkaJoinsSql(TypDokument::DokZpo, $criteria->filtry)}
            WHERE
                gp.name IN ('zwrot', 'zwrotka') AND
                {$where}
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function commonInnerJoinSql(): string
    {
        return <<<SQL
            INNER JOIN users_groups ug_w ON (ug_w.group_id = gi.workstation)
            INNER JOIN users_groups ug_g ON (ug_g.group_id = ug_w.parent_group_id)
            INNER JOIN users_usergroups uug ON (uug.group_id = ug_w.group_id AND uug.status = 'A' AND uug.typ = 'Z')
            INNER JOIN users_users uu ON (uu."userId" = uug."userId")
            INNER JOIN eurzad_form ef ON (gp.normalized_name = ef.form_name)
SQL;

    }

    private function pismoSelectSql(): string
    {
        return <<<SQL
                es.sprawa_uid AS id_dokumentu,
                fd_nr_na_pismie.form_dane_wartosc AS nr_na_pismie,
                NULL AS wersja,
                COALESCE(
                    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
                    esp.sprawa_createdate
                ) AS data_rejestracji,
                es.sprawa_createdate as data_utworzenia,
                fd_pliki.form_dane_wartosc AS zalaczniki,
                (NULLIF(fd_tytul.form_dane_wartosc, '')::jsonb)->>'textarea' AS dokument_tytul,
                fd_tresc_wniosku.form_dane_wartosc AS tresc_wniosku,
                ek.ksiega_numer || '/' || ek.ksiega_rok AS nr_ksiegi,
                EXISTS (
                    SELECT 1
                    FROM eurzad_form_dane fd
                    WHERE fd.sprawa_uid = es.sprawa_uid
                      AND fd.form_dane_pole = 'interesanci'
                      AND NULLIF(TRIM(fd.form_dane_wartosc), '') IS NOT NULL
                ) AS has_pozostali_interesanci
SQL;


    }

    private function dokumentSelectSql(): string
    {
        return <<<SQL
                ep.pismo_uid AS id_dokumentu,
                null AS nr_na_pismie,
                ep.pismo_wersja AS wersja,
                ep.pismo_createdate as data_rejestracji,
                ep.pismo_createdate as data_utworzenia,
                fd_pliki.wartosc AS zalaczniki,
                (NULLIF(fd_tytul.wartosc, '')::jsonb)->>'textarea' AS dokument_tytul,
                null AS tresc_wniosku,
                '' AS nr_ksiegi,
                false AS has_pozostali_interesanci  --dokumenty (decyzja/korespondencja) dołączane do sprawy - nie mają pola interesanci - dlatego ustawiamy na false
                
SQL;

    }

    private function dokumentInnerJoinSql(): string
    {
        return <<<SQL
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
SQL;
    }
    private function commonSelectSql(): string
    {
        return <<<SQL
                gp.name AS nazwa_procesu,
                gp.normalized_name AS nazwa_znormalizowana_procesu,
                gp."pId" AS id_procesu,
                ef.form_typ AS typ_formularza, -- internal | external (eurzad_form.form_typ)
                ess.opis AS status_procesu,
                et.teczka_znak_sprawy as znak_sprawy,
                gi.workstation as wlasciciel_stanowisko_id,
                ug_w."groupName" as wlasciciel_stanowisko_skrot,
                ug_w."groupDesc" as wlasciciel_stanowisko_nazwa,
                ug_g."groupName" as wlasciciel_komorka_skrot,
                ug_g."groupDesc" as wlasciciel_komorka_nazwa,
                uu.surname AS wlasciciel_nazwisko,
                uu.forename AS wlasciciel_imie,
                NULLIF(uu.surname2, '') AS wlasciciel_nazwisko2,
                NULLIF(uu.surname3, '') AS wlasciciel_nazwisko3,
                CONCAT_WS(
                   ' ',
                   uu.forename,
                   uu.surname,
                   NULLIF(uu.surname2, ''),
                   NULLIF(uu.surname3, '')
                )  as wlasciciel_imie_nazwisko,
                ps_petent.view_podmiot as interesant,
                ps_petent.view_adres_korespondencyjny as interesant_adres,
                pd_petent.typ_osoby as interesant_type
                
SQL;

    }

    private function teczkaJoinsSql(TypDokument $typDokumentu, TypFiltrDokument $filtry): string
    {
        return match ($typDokumentu) {
            TypDokument::DokPrzychodzacy => $filtry->isScopedToTeczka()
                ? 'INNER JOIN eurzad_teczka et ON et.teczka_uid = ' . $this->bind($filtry->teczkaUid)
                : <<<SQL
                LEFT JOIN LATERAL (
                    SELECT t.teczka_znak_sprawy, t.teczka_uid
                    FROM eurzad_teczka t
                    WHERE t.sprawa_uid = es.sprawa_uid
                    UNION ALL
                    SELECT t.teczka_znak_sprawy, t.teczka_uid
                    FROM eurzad_teczka_zawartosc z
                    INNER JOIN eurzad_teczka t ON t.teczka_uid = z.teczka_uid
                    WHERE z.teczka_zawartosc_uid = es.sprawa_uid
                    LIMIT 1
                ) et ON true
            SQL,
            TypDokument::DokWychodzacy => <<<SQL
                LEFT JOIN eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = ep.pismo_uid
                LEFT JOIN eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            SQL,
            TypDokument::DokZpo => <<<SQL
                LEFT JOIN eurzad_teczka_zawartosc etz2 ON etz2.teczka_zawartosc_uid = es.sprawa_uid
                LEFT JOIN eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = etz2.teczka_uid
                LEFT JOIN eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            SQL,
        };
    }

    private function pismoInnerJoinsSql(): string
    {
        return <<<SQL
                INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
                INNER JOIN eurzad_sprawa_przedluzanie esp ON esp.sprawa_uid = es.sprawa_uid
                INNER JOIN eurzad_obieg eo ON (eo.sprawa_uid = es.sprawa_uid AND max_status_sprawy_id > 0)
                INNER JOIN eurzad_slownik_status ess ON ess.symbol = eo.status
                INNER JOIN galaxia_instances gi ON gi."instanceId" = eo."instanceId" 
                INNER JOIN eurzad_sprawa_przedluzanie sp ON sp.sprawa_uid = es.sprawa_uid
        SQL;
    }

    private function pismoLeftJoinsSql(): string
    {
        return <<<SQL
                LEFT JOIN eurzad_form_dane fd_tytul
                       ON (fd_tytul.sprawa_uid = es.sprawa_uid AND fd_tytul.form_dane_pole = 'dokument_tytul' AND fd_tytul.form_dane_wartosc != '')
                LEFT JOIN eurzad_form_dane fd_tresc_wniosku
                       ON (fd_tresc_wniosku.sprawa_uid = es.sprawa_uid AND fd_tresc_wniosku.form_dane_pole = 'tresc_wniosku' AND fd_tresc_wniosku.form_dane_wartosc != '')
                LEFT JOIN eurzad_form_dane fd_nr_na_pismie
                       ON (fd_nr_na_pismie.sprawa_uid = es.sprawa_uid AND fd_nr_na_pismie.form_dane_pole = 'nr_na_pismie' AND fd_nr_na_pismie.form_dane_wartosc != '')
                LEFT JOIN eurzad_ksiega_sprawa eks ON (eks.sprawa_uid = es.sprawa_uid)
                LEFT JOIN eurzad_ksiega ek ON (ek.ksiega_uid = eks.ksiega_uid)
                LEFT JOIN eurzad_form_dane fd_data_rej
                       ON (fd_data_rej.sprawa_uid = es.sprawa_uid AND fd_data_rej.form_dane_pole = 'data' AND fd_data_rej.form_dane_wartosc != '')
                LEFT JOIN eurzad_form_dane fd_petent
                       ON (fd_petent.sprawa_uid = es.sprawa_uid AND fd_petent.form_dane_pole = 'petent_uid' AND fd_petent.form_dane_wartosc != '')
                LEFT JOIN eurzad_petent_dane pd_petent ON (
                        pd_petent.main_petent_uid = fd_petent.form_dane_wartosc 
                        AND pd_petent.petent_uid = pd_petent.main_petent_uid
                )
                LEFT JOIN eurzad_petent_search ps_petent ON (ps_petent.main_petent_uid = fd_petent.form_dane_wartosc)
                LEFT JOIN eurzad_form_dane fd_pliki
                       ON (fd_pliki.sprawa_uid = es.sprawa_uid AND fd_pliki.form_dane_pole = 'pliki' AND fd_pliki.form_dane_wartosc != '')
SQL;

    }

    private function dokumentLeftJoinsSql(): string
    {
        return <<<SQL
                LEFT JOIN eurzad_form_pisma_dane fd_tytul
                    ON (fd_tytul.id = ep.id AND fd_tytul.klucz = 'dokument_tytul')
                LEFT JOIN eurzad_form_pisma_dane fpd_petent
                    ON (fpd_petent.id = ep.id AND fpd_petent.klucz = 'petent_uid')
                LEFT JOIN eurzad_petent_dane pd_petent 
                    ON (
                        pd_petent.main_petent_uid = fpd_petent.wartosc 
                        AND pd_petent.petent_uid = pd_petent.main_petent_uid
                    )
                LEFT JOIN eurzad_petent_search ps_petent 
                    ON (ps_petent.main_petent_uid = fpd_petent.wartosc)
                LEFT JOIN eurzad_form_pisma_dane fd_pliki
                    ON (fd_pliki.id = ep.id AND fd_pliki.klucz = 'pliki')
SQL;

    }

    /**
     * @param list<string> $parts
     */
    private function implodeUnions(array $parts): string
    {
        return implode("\nUNION\n", $parts);
    }

    /**
     * @return list<TypDokument>
     */
    private function resolveDocumentTypes(TypFiltrDokument $filtry): array
    {
        if ($filtry->typProcesu === null) {
            return TypDokument::wszystkie();
        }

        return [$filtry->typProcesu];
    }

    private function getOrderSql(SortowanieDokumentow $sortowanie): string
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
}
