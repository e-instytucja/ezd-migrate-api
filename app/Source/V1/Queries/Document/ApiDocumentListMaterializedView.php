<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\Support\MaterializedViews\DocumentListMaterializedView;

/**
 * Definicja SQL materialized view api_document_list (1 wiersz / id_dokumentu).
 */
final class ApiDocumentListMaterializedView
{
    public const NAME = DocumentListMaterializedView::NAME;

    public function definitionSql(): string
    {
        $branches = [
            $this->dokWychodzacyBranchSql(),
            $this->dokNiewychodzacyInicjujacyBranchSql(),
            $this->dokNiewychodzacyWSprawieBranchSql(),
            $this->dokNiewychodzacyBezSprawyBranchSql(),
            $this->dokZpoBranchSql(),
        ];

        $wrapped = array_map(static fn (string $branch): string => "({$branch})", $branches);

        return implode("\nUNION\n", $wrapped);
    }

    /**
     * @return list<string>
     */
    public function indexStatements(): array
    {
        $view = self::NAME;

        return [
            "CREATE UNIQUE INDEX IF NOT EXISTS {$view}_id_dokumentu_uidx ON {$view} (id_dokumentu)",
            "CREATE INDEX IF NOT EXISTS {$view}_ws_idx ON {$view} (wlasciciel_stanowisko_id)",
            "CREATE INDEX IF NOT EXISTS {$view}_data_rej_idx ON {$view} (data_rejestracji DESC)",
            "CREATE INDEX IF NOT EXISTS {$view}_typ_dok_idx ON {$view} (typ_dokumentu)",
            "CREATE INDEX IF NOT EXISTS {$view}_nazwa_proc_idx ON {$view} (nazwa_znormalizowana_procesu)",
            "CREATE INDEX IF NOT EXISTS {$view}_instance_idx ON {$view} (instance_id)",
        ];
    }

    private function dokWychodzacyBranchSql(): string
    {
        return <<<SQL
            SELECT DISTINCT ON (id_dokumentu)
                {$this->commonSelectSql()},
                {$this->dokumentSelectSql()},
                epo.status AS status,
                gi."instanceId" AS instance_id,
                'dok_wychodzacy' AS typ_dokumentu,
                'w_sprawie' AS typ_powiazania_dokumentu
            FROM eurzad_pismo ep
                {$this->dokumentInnerJoinSql()}
                {$this->commonInnerJoinSql()}
                {$this->dokumentLeftJoinsSql()}
                LEFT JOIN eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = ep.pismo_uid
                LEFT JOIN eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            ORDER BY id_dokumentu ASC, epo.pismo_obieg_id DESC
        SQL;
    }

    private function dokNiewychodzacyInicjujacyBranchSql(): string
    {
        return <<<SQL
            SELECT DISTINCT ON (id_dokumentu)
                {$this->commonSelectSql()},
                {$this->pismoSelectSql()},
                eo.status AS status,
                gi."instanceId" AS instance_id,
                CASE ef.form_typ
                    WHEN 'external' THEN 'dok_przychodzacy'
                    WHEN 'internal' THEN 'dok_wewnetrzny'
                END AS typ_dokumentu,
                'inicjujacy_sprawe' AS typ_powiazania_dokumentu
            FROM eurzad_sprawa es
                {$this->pismoInnerJoinsSql()}
                {$this->commonInnerJoinSql()}
                {$this->pismoLeftJoinsSql()}
                LEFT JOIN eurzad_teczka et ON et.sprawa_uid = es.sprawa_uid
            WHERE
                gp.name NOT IN ('zwrot', 'zwrotka') AND
                ef.form_typ IN ('external', 'internal') AND
                EXISTS (
                    SELECT 1
                    FROM eurzad_teczka t_inic
                    WHERE t_inic.sprawa_uid = es.sprawa_uid
                )
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function dokNiewychodzacyWSprawieBranchSql(): string
    {
        return <<<SQL
            SELECT DISTINCT ON (id_dokumentu)
                {$this->commonSelectSql()},
                {$this->pismoSelectSql()},
                eo.status AS status,
                gi."instanceId" AS instance_id,
                CASE ef.form_typ
                    WHEN 'external' THEN 'dok_przychodzacy'
                    WHEN 'internal' THEN 'dok_wewnetrzny'
                END AS typ_dokumentu,
                'w_sprawie' AS typ_powiazania_dokumentu
            FROM eurzad_sprawa es
                {$this->pismoInnerJoinsSql()}
                {$this->commonInnerJoinSql()}
                {$this->pismoLeftJoinsSql()}
                INNER JOIN eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = es.sprawa_uid
                LEFT JOIN eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            WHERE
                gp.name NOT IN ('zwrot', 'zwrotka') AND
                ef.form_typ IN ('external', 'internal') AND
                EXISTS (
                    SELECT 1
                    FROM eurzad_teczka_zawartosc etz_w
                    WHERE etz_w.teczka_zawartosc_uid = es.sprawa_uid
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM eurzad_teczka t_inic
                    WHERE t_inic.sprawa_uid = es.sprawa_uid
                )
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function dokNiewychodzacyBezSprawyBranchSql(): string
    {
        return <<<SQL
            SELECT DISTINCT ON (id_dokumentu)
                {$this->commonSelectSql()},
                {$this->pismoSelectSql()},
                eo.status AS status,
                gi."instanceId" AS instance_id,
                CASE ef.form_typ
                    WHEN 'external' THEN 'dok_przychodzacy'
                    WHEN 'internal' THEN 'dok_wewnetrzny'
                END AS typ_dokumentu,
                'bez_sprawy' AS typ_powiazania_dokumentu
            FROM eurzad_sprawa es
                {$this->pismoInnerJoinsSql()}
                {$this->commonInnerJoinSql()}
                {$this->pismoLeftJoinsSql()}
                LEFT JOIN eurzad_teczka et ON false
            WHERE
                gp.name NOT IN ('zwrot', 'zwrotka') AND
                ef.form_typ IN ('external', 'internal') AND
                NOT EXISTS (
                    SELECT 1
                    FROM eurzad_teczka t_inic
                    WHERE t_inic.sprawa_uid = es.sprawa_uid
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM eurzad_teczka_zawartosc etz_w
                    WHERE etz_w.teczka_zawartosc_uid = es.sprawa_uid
                )
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function dokZpoBranchSql(): string
    {
        return <<<SQL
            SELECT DISTINCT ON (id_dokumentu)
                {$this->commonSelectSql()},
                {$this->pismoSelectSql()},
                eo.status AS status,
                gi."instanceId" AS instance_id,
                'dok_zpo' AS typ_dokumentu,
                'zpo' AS typ_powiazania_dokumentu
            FROM eurzad_sprawa es
                {$this->pismoInnerJoinsSql()}
                {$this->commonInnerJoinSql()}
                {$this->pismoLeftJoinsSql()}
                LEFT JOIN eurzad_teczka_zawartosc etz2 ON etz2.teczka_zawartosc_uid = es.sprawa_uid
                LEFT JOIN eurzad_teczka_zawartosc etz ON etz.teczka_zawartosc_uid = etz2.teczka_uid
                LEFT JOIN eurzad_teczka et ON et.teczka_uid = etz.teczka_uid
            WHERE
                gp.name IN ('zwrot', 'zwrotka')
            ORDER BY id_dokumentu ASC, eo.status_sprawy_id DESC
        SQL;
    }

    private function commonSelectSql(): string
    {
        return <<<SQL
                gp.name AS nazwa_procesu,
                gp.normalized_name AS nazwa_znormalizowana_procesu,
                gp."pId" AS id_procesu,
                ef.form_typ AS typ_formularza,
                ess.opis AS status_procesu,
                et.teczka_znak_sprawy AS znak_sprawy,
                gi.workstation AS wlasciciel_stanowisko_id,
                ug_w."groupName" AS wlasciciel_stanowisko_skrot,
                ug_w."groupDesc" AS wlasciciel_stanowisko_nazwa,
                ug_g."groupName" AS wlasciciel_komorka_skrot,
                ug_g."groupDesc" AS wlasciciel_komorka_nazwa,
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
                ) AS wlasciciel_imie_nazwisko,
                ps_petent.view_podmiot AS interesant,
                ps_petent.view_adres_korespondencyjny AS interesant_adres,
                pd_petent.typ_osoby AS interesant_type
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
                es.sprawa_createdate AS data_utworzenia,
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
                NULL AS nr_na_pismie,
                ep.pismo_wersja AS wersja,
                ep.pismo_createdate AS data_rejestracji,
                ep.pismo_createdate AS data_utworzenia,
                fd_pliki.wartosc AS zalaczniki,
                (NULLIF(fd_tytul.wartosc, '')::jsonb)->>'textarea' AS dokument_tytul,
                NULL AS tresc_wniosku,
                '' AS nr_ksiegi,
                false AS has_pozostali_interesanci
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
}
