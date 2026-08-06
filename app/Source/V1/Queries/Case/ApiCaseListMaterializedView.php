<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use App\Source\V1\Support\MaterializedViews\CaseListMaterializedView;

/**
 * Definicja SQL materialized view api_case_list (1 wiersz / teczka_uid).
 */
final class ApiCaseListMaterializedView
{
    public const NAME = CaseListMaterializedView::NAME;

    public function definitionSql(): string
    {
        return <<<SQL
            SELECT DISTINCT ON (et.teczka_uid)
                et.teczka_uid                                                 AS id_sprawy,
                et.teczka_znak_sprawy                                         AS znak,
                et.sprawa_uid                                                 AS main_document_uid,
                esp.sprawa_createdate                                         AS data_rejestracji_dokumentu,
                esp.czas_realizacji                                           AS czas_realizacji,
                esp.sprawa_finishdate                                         AS sprawa_finishdate,
                es.sprawa_createdate                                          AS data_utworzenia_dokumentu,
                gp.name                                                       AS nazwa_procesu,
                gp.normalized_name                                            AS nazwa_procesu_znormalizowana,
                gp."pId"                                                      AS id_procesu,
                ef.form_typ                                                   AS typ_formularza,
                ess.opis                                                      AS status_procesu,
                eo.status                                                     AS status,
                et.teczka_createdate                                          AS data_wszczecia,
                et.opis_sprawy,
                et.tytul_sprawy,
                et.oznaczenie_dntas,
                et.dntas,
                et.teczka_rok_zalozenia                                       AS rok,
                fd_pliki.form_dane_wartosc                                    AS zalaczniki,
                ps_petent.view_podmiot                                        AS interesant,
                ps_petent.view_adres_korespondencyjny                         AS interesant_adres,
                pd_petent.typ_osoby                                           AS interesant_type,
                EXISTS (
                    SELECT 1
                    FROM eurzad_form_dane fd
                    WHERE fd.sprawa_uid = es.sprawa_uid
                      AND fd.form_dane_pole = 'interesanci'
                      AND NULLIF(TRIM(fd.form_dane_wartosc), '') IS NOT NULL
                ) AS has_pozostali_interesanci,
                gi.workstation                                                AS wlasciciel_stanowisko_id,
                gi."instanceId"                                               AS instance_id,
                ug_w."groupName"                                              AS wlasciciel_stanowisko_skrot,
                ug_w."groupDesc"                                              AS wlasciciel_stanowisko_nazwa,
                ug_g."groupName"                                              AS wlasciciel_komorka_skrot,
                ug_g."groupDesc"                                              AS wlasciciel_komorka_nazwa,
                uu.surname                                                    AS wlasciciel_nazwisko,
                uu.forename                                                   AS wlasciciel_imie,
                NULLIF(uu.surname2, '')                                       AS wlasciciel_nazwisko2,
                NULLIF(uu.surname3, '')                                       AS wlasciciel_nazwisko3,
                CONCAT_WS(
                   ' ',
                   uu.forename,
                   uu.surname,
                   NULLIF(uu.surname2, ''),
                   NULLIF(uu.surname3, '')
                )                                                             AS wlasciciel_imie_nazwisko,
                COALESCE(
                    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
                    esp.sprawa_createdate
                )                                                             AS data_rejestracji,
                (NULLIF(fd_tytul.form_dane_wartosc, '')::jsonb)->>'textarea'  AS dokument_tytul,
                fd_tresc_wniosku.form_dane_wartosc                            AS tresc_wniosku
            FROM eurzad_teczka et
                INNER JOIN eurzad_sprawa es ON es.sprawa_uid = et.sprawa_uid
                INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
                INNER JOIN eurzad_form ef ON (gp.normalized_name = ef.form_name)
                INNER JOIN eurzad_obieg eo ON (eo.sprawa_uid = es.sprawa_uid AND eo.max_status_sprawy_id > 0)
                INNER JOIN eurzad_slownik_status ess ON ess.symbol = eo.status
                INNER JOIN galaxia_instances gi ON gi."instanceId" = eo."instanceId"
                INNER JOIN eurzad_sprawa_przedluzanie esp ON esp.sprawa_uid = es.sprawa_uid
                INNER JOIN users_groups ug_w ON (ug_w.group_id = gi.workstation)
                INNER JOIN users_groups ug_g ON (ug_g.group_id = ug_w.parent_group_id)
                INNER JOIN users_usergroups uug ON (uug.group_id = ug_w.group_id AND uug.status = 'A' AND uug.typ = 'Z')
                INNER JOIN users_users uu ON (uu."userId" = uug."userId")
                LEFT JOIN eurzad_form_dane fd_petent
                       ON (fd_petent.sprawa_uid = es.sprawa_uid AND fd_petent.form_dane_pole = 'petent_uid' AND fd_petent.form_dane_wartosc != '')
                LEFT JOIN eurzad_petent_dane pd_petent ON (
                        pd_petent.main_petent_uid = fd_petent.form_dane_wartosc
                        AND pd_petent.petent_uid = pd_petent.main_petent_uid
                )
                LEFT JOIN eurzad_petent_search ps_petent ON (ps_petent.main_petent_uid = fd_petent.form_dane_wartosc)
                LEFT JOIN eurzad_form_dane fd_pliki
                       ON (fd_pliki.sprawa_uid = es.sprawa_uid AND fd_pliki.form_dane_pole = 'pliki' AND fd_pliki.form_dane_wartosc != '')
                LEFT JOIN eurzad_form_dane fd_data_rej
                       ON (fd_data_rej.sprawa_uid = es.sprawa_uid AND fd_data_rej.form_dane_pole = 'data' AND fd_data_rej.form_dane_wartosc != '')
                LEFT JOIN eurzad_form_dane fd_tytul
                       ON (fd_tytul.sprawa_uid = es.sprawa_uid AND fd_tytul.form_dane_pole = 'dokument_tytul' AND fd_tytul.form_dane_wartosc != '')
                LEFT JOIN eurzad_form_dane fd_tresc_wniosku
                       ON (fd_tresc_wniosku.sprawa_uid = es.sprawa_uid AND fd_tresc_wniosku.form_dane_pole = 'tresc_wniosku' AND fd_tresc_wniosku.form_dane_wartosc != '')
            ORDER BY et.teczka_uid, uug."userId" ASC
        SQL;
    }

    /**
     * @return list<string>
     */
    public function indexStatements(string $qualifiedView): array
    {
        $view = self::NAME;

        return [
            "CREATE UNIQUE INDEX IF NOT EXISTS {$view}_id_sprawy_uidx ON {$qualifiedView} (id_sprawy)",
            "CREATE INDEX IF NOT EXISTS {$view}_dntas_ws_idx ON {$qualifiedView} (dntas, wlasciciel_stanowisko_id)",
            "CREATE INDEX IF NOT EXISTS {$view}_dntas_data_idx ON {$qualifiedView} (dntas, data_wszczecia DESC)",
            "CREATE INDEX IF NOT EXISTS {$view}_rok_idx ON {$qualifiedView} (dntas, rok)",
            "CREATE INDEX IF NOT EXISTS {$view}_main_doc_idx ON {$qualifiedView} (main_document_uid)",
            "CREATE INDEX IF NOT EXISTS {$view}_instance_idx ON {$qualifiedView} (instance_id)",
        ];
    }
}
