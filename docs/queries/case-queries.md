# Queries — Sprawy (Case)

Pliki: `CaseListQuery.php`, `CaseQuery.php`

Konsumenci (grep): `CaseService`, `Case\HistoryService`, `AttachmentService`

---

## CaseListQuery

### Start zapytania

**FROM `eurzad_teczka et`** — różni się od DocumentListQuery, gdzie gałęzie pism startują od `eurzad_sprawa es`.

### Tabele i JOIN-y

| Alias | Tabela | JOIN |
|-------|--------|------|
| `et` | `eurzad_teczka` | FROM |
| `es` | `eurzad_sprawa` | INNER `es.sprawa_uid = et.sprawa_uid` |
| `gp`, `eo`, `ess`, `gi`, `esp` | proces, obieg, status, instance, przedluzanie | INNER (rdzeń) |
| `ug_w`, `ug_g`, `uug`, `uu` | users_* | INNER |
| `fd_petent`, `pd_petent`, `ps_petent`, `fd_pliki` | form + petent | LEFT — w `getList()` |

Rdzeń obiegu: `eo.max_status_sprawy_id > 0`. Komentarz w kodzie o historycznych duplikatach tej flagi w DB + skrypt naprawczy (operacja poza API).

Aktywny pracownik stanowiska: `uug.status = 'A' AND uug.typ = 'Z'` — znaczenie `typ='Z'` w EZD: **DO WYJAŚNIENIA**.

### Filtry WHERE

| Pole DTO | Klucz JSON | SQL |
|----------|------------|-----|
| — | — | `et.dntas = {0\|1}` |
| — | `konfiguracja.madkomWorkstationIds` | scope stanowisk (wymagane, niepuste) |
| `sprawaUid` | `filtry.sprawa_uid` | `et.teczka_uid = ?` |
| `rok` | `filtry.rok` | `et.teczka_rok_zalozenia = ?` |
| `znak` | `filtry.znak` | `et.teczka_znak_sprawy ILIKE ?` |
| `oznaczenieDntas` | `filtry.oznaczenie_dntas` | `et.oznaczenie_dntas ILIKE ?` |
| `statusProcesu` | `filtry.status_procesu` | `eo.status = ?` |
| `wlascicielStanowisko` | `filtry.wlasciciel_stanowisko` | `buildWorkstationCondition([id], pokazUdostepnione !== null)` |
| `tytulSprawy` | `filtry.tytu_sprawy` | `et.tytul_sprawy ILIKE ?` |
| `interesant` | `filtry.interesant` | ILIKE na `ps_petent` |
| `dataWszczeciaOd/Do` | `filtry.data_wszczecia_od/do` | `et.teczka_createdate` |

### `pokaz_udostepnione`

Patrz [README.md](README.md#pokaz_udostepnione--semantyka-w-kodzie) — decyduje **obecność klucza**, nie wartość bool (Q-02).

### Sortowanie

`SortowanieSpraw::toOrderBySql()` — whitelist; domyślnie `data_wszczecia desc` → `et.teczka_createdate`.

### SELECT — aliasy

| Alias | Źródło |
|-------|--------|
| `id_sprawy` | `et.teczka_uid` |
| `main_document_uid` | `et.sprawa_uid` |
| `has_pozostali_interesanci` | EXISTS `form_dane_pole = 'interesanci'` |
| `zalaczniki` | `fd_pliki.form_dane_wartosc` |

Zakomentowany JOIN `dokument_tytul` — tytuł z formularza **nie** w liście.

### getList vs getListCount

| Metoda | JOIN petenta |
|--------|--------------|
| `getList()` | zawsze LEFT (getLeftJoinSql) |
| `getListCount()` | tylko gdy `TypFiltrSpraw::requiresInteresantJoin()` |

---

## CaseQuery

Query Builder. Metody lookup / historia.

### Metody i tabele

| Metoda | Tabele | Uwagi |
|--------|--------|-------|
| `getTeczkaSyg` | `eurzad_teczka` | Zakomentowany filtr `dntas` (Q-10) |
| `getMainDocumentUidByCaseUid` | `eurzad_teczka` | `sprawa_uid` po `teczka_uid` |
| `getStatus` / `getStatusSymbol` | `eurzad_obieg`, `eurzad_slownik_status` | **Bez** `max_status_sprawy_id`; `getStatusSymbol` bez ORDER BY |
| `getFirstRowFromHistory` | `eurzad_obieg` | Sort `status_sprawy_id`, bez filtra max (Q-01) |
| `getHistory` | `eurzad_obieg` + status | DESC `status_sprawy_id` |
| `getInstanceIdByCaseUid` | via `getFirstRowFromHistory` | |
| `getSprawaUidByTeczkaZawartoscUid` | teczka_zawartosc → teczka → obieg | `status_sprawy_id > 0` (nie max) |
| `getAllFromTeczkaBySprawaUid` | teczka + podteczki | filtr `dntas` |
| `getTitleAndDescription` | `eurzad_teczka` | filtr `dntas` |
| `getStatuses($dntas)` | teczka → obieg | `max_status_sprawy_id > 0` |

### Niespójność wyboru wiersza obiegu (Q-01)

| Miejsce | Warunek |
|---------|---------|
| CaseListQuery | `max_status_sprawy_id > 0` |
| CaseQuery::getFirstRowFromHistory | brak max, sort `status_sprawy_id` |
| CaseQuery::getSprawaUidByTeczkaZawartoscUid | `status_sprawy_id > 0` |
| CaseQuery::getStatusSymbol | pierwszy wiersz bez ORDER BY |

### Pułapki potwierdzone w kodzie

| Problem | Szczegóły |
|---------|-----------|
| `getStatusSymbol` exception | Komunikat używa pustego `$status` zamiast `$uid` |
| Brak `declare(strict_types=1)` | CaseQuery vs CaseListQuery |

---

## Powiązanie z Services

| Output | Service | DTO (mapowanie w Service — nie w Query) |
|--------|---------|----------------------------------------|
| Wiersz listy | CaseService::getList | SprawaDto, SprawaDanePodstawoweDto, … |
| Historia | CaseHistoryService | HistoriaObieguDto |
| getStatuses | CaseService::getStatuses | `{status, opis}` |

---

## Otwarte kwestie

Q-01, Q-02, Q-10 — [open-questions.md](../open-questions.md)

## Powiązana dokumentacja

- [../database.md](../database.md) — łańcuch A vs B/C
- [document-queries.md](document-queries.md)
