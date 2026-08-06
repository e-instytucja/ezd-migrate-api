# Queries — Sprawy (Case)

Pliki: `CaseListQuery.php`, `CaseListQueryMV.php`, `CaseQuery.php`, `ApiCaseListMaterializedView.php`

Konsumenci (grep): `CaseService`, `Case\HistoryService`, `AttachmentService`

---

## Źródło list (live SQL vs MV)

Globalny przełącznik: **`USE_MATERIALIZED_VIEWS`** (`config('app.use_materialized_views')`, domyślnie `false`). Dotyczy wszystkich list API z MV — szczegóły i status widoków: `GET|POST /api/v1/system/materialized-views` (`{"enabled":true|false}`).

| Element (sprawy) | Wartość |
|---------|---------|
| Factory | `CaseListQueryFactory` → `CaseListQuery` lub `CaseListQueryMV` gdy `USE_MATERIALIZED_VIEWS=true` |
| MV | `api_cache.api_case_list` (1 wiersz / `teczka_uid`, `DISTINCT ON`; schemat: `DB_MV_SCHEMA`, domyślnie `api_cache`) |
| Refresh | `php artisan cases:refresh-list-mv` lub `materialized-views:refresh` (`--drop`) |

Przed `USE_MATERIALIZED_VIEWS=true` wymagane są **wszystkie** zarejestrowane widoki w schemacie `api_cache` (schemat musi istnieć w DB — patrz [database.md](../database.md)). POST `enabled:true` bez MV → 422.

`CaseService` wywołuje factory **per request** (`caseListQuery()`).

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
| `ef` | `eurzad_form` | INNER `gp.normalized_name = ef.form_name` |
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
| `typFormularza` | `filtry.typ_formularza` | `ef.form_typ = ?` (`TypFormularza`: `internal` \| `external`; nieprawidłowa wartość → filtr ignorowany) |
| `wlascicielStanowisko` | `filtry.wlasciciel_stanowisko` | `buildWorkstationCondition([id], pokazUdostepnione !== null)` |
| `tytulSprawy` | `filtry.tytu_sprawy` | `et.tytul_sprawy ILIKE ?` |
| `interesant` | `filtry.interesant` | ILIKE na `ps_petent` |
| `dataWszczeciaOd/Do` | `filtry.data_wszczecia_od/do` | `et.teczka_createdate` |
| `typProcesu` | `filtry.typ_procesu` | `ef.form_typ` (`dok_przychodzacy` → `external`, `dok_wewnetrzny` → `internal`; inne wartości → 422) |
| `nazwaProcesu` | `filtry.nazwa_procesu` | `gp.normalized_name = ?` |
| `documentId` | `filtry.documentId` | `es.sprawa_uid = ?` (wartość jako string, bez lookup `galaxia_instances`) |
| `opisDokumentu` | `filtry.opis_dokumentu` | ILIKE na `fd_tresc_wniosku` i `fd_tytul` (JOIN warunkowy) |
| `dataRejestracjiOd/Do` | `filtry.data_rejestracji_od/do` | `COALESCE(fd_data_rej, esp.sprawa_createdate)` (JOIN warunkowy) |

### JOIN-y warunkowe (filtry dokumentu inicjującego)

| Alias | Tabela | Kiedy |
|-------|--------|-------|
| `fd_data_rej` | `eurzad_form_dane` (`form_dane_pole = 'data'`) | `requiresDataRejJoin()` |
| `fd_tytul`, `fd_tresc_wniosku` | `eurzad_form_dane` | `requiresOpisJoin()` |

### `pokaz_udostepnione`

Patrz [README.md](README.md#pokaz_udostepnione--semantyka-w-kodzie) — decyduje **obecność klucza**, nie wartość bool (Q-02).

### Sortowanie

`SortowanieSpraw::toOrderBySql()` — whitelist; domyślnie `data_wszczecia desc` → `et.teczka_createdate`.

### SELECT — aliasy

| Alias | Źródło |
|-------|--------|
| `id_sprawy` | `et.teczka_uid` |
| `main_document_uid` | `et.sprawa_uid` |
| `typ_formularza` | `ef.form_typ` (`TypFormularza`: `internal` \| `external`) |
| `has_pozostali_interesanci` | EXISTS `form_dane_pole = 'interesanci'` |
| `zalaczniki` | `fd_pliki.form_dane_wartosc` |
| `czas_realizacji` | `esp.czas_realizacji` |
| `sprawa_finishdate` | `esp.sprawa_finishdate` |
| `status` | `eo.status` (symbol; w MV `api_case_list`; w live `CaseListQuery` od 2026-07) |
| `data_wszczecia` | `et.teczka_createdate` |
| `data_rejestracji_dokumentu` | `esp.sprawa_createdate` (pismo wiodące; nie mapowane do show) |
| `data_utworzenia_dokumentu` | `es.sprawa_createdate` (pismo wiodące; nie mapowane do show) |

### `danePodstawowe.values` (endpoint show — `SprawaDanePodstawoweDto`)

| Pole API | Alias SQL | Źródło DB |
|----------|-----------|-----------|
| `dataRejestracji` | `data_wszczecia` | `et.teczka_createdate` |
| `dataUtworzenia` | `data_wszczecia` | `et.teczka_createdate` |

Daty w show dotyczą **teczki/sprawy**, nie pisma wiodącego. Aliasy `data_rejestracji_dokumentu` / `data_utworzenia_dokumentu` pozostają w SELECT listy (filtry, inne ścieżki).

### `terminRealizacji` (endpoint show — `SprawaDanePodstawoweDto`)

Pole API **zawsze** zawiera datę ISO 8601; `null` niedozwolone.

| `czas_realizacji` | Źródło `terminRealizacji` |
|-------------------|---------------------------|
| `>= 0` | `data_wszczecia` (`et.teczka_createdate`) + N dni |
| `-1` lub `-2` | `esp.sprawa_finishdate`, jeśli wypełnione |
| `-1` lub `-2`, brak `sprawa_finishdate` | błąd HTTP 422 (`request_failed`) |

Komunikaty błędu (brak `sprawa_finishdate` przy `czas_realizacji` ujemnym):

| Status sprawy (`eo.status`) | Komunikat |
|-----------------------------|-----------|
| niezakończona (symbol ∉ `Z`, `ZS`, `ZA`, `T`) | `brak czasu realizacji dla sprawy niezakończonej` |
| zakończona (`Z`, `ZS`, `ZA`, `T`) | `brak czasu realizacji dla sprawy zakończonej` |

Wartości `-1` / `-2` w EZD3: „Nieokreślony” / „Zgodnie z przepisami” — bez konkretnej liczby dni; przy braku `sprawa_finishdate` API nie zgaduje terminu.

Zakomentowany JOIN `dokument_tytul` — tytuł z formularza **nie** w liście.

### getList vs getListCount

| Metoda | JOIN petenta / users |
|--------|----------------------|
| `getList()` | pełny rdzeń (`getListInnerJoinSql`) z `users_*` + LEFT JOIN petenta/formularza |
| `getListCount()` | rdzeń bez `users_*` (`getCountInnerJoinSql`) + `COUNT(DISTINCT et.teczka_uid)`; JOIN-y formularza tylko gdy filtr wymaga |

`users_*` w COUNT powodowały mnożenie wierszy (wiele aktywnych użytkowników na stanowisko) i zbędny koszt JOIN-ów.

---

## CaseListQueryMV

`FROM api_cache.api_case_list acl` — bez JOIN-ów `eurzad_*` (poza `EXISTS` na `galaxia_instance_users` przy `pokaz_udostepnione`).

Filtry mapowane na kolumny MV (`dntas`, `rok`, `status`, `typ_formularza`, `data_rejestracji`, `dokument_tytul`, `tresc_wniosku`, …). COUNT = `COUNT(*)` (MV 1:1 z teczką).

Budowa MV: `ApiCaseListMaterializedView` + `CaseListMvRefreshService` / `cases:refresh-list-mv`.

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
| `getStatuses($dntas)` | `eurzad_obieg` + EXISTS `eurzad_teczka` (`dntas`) | `max_status_sprawy_id > 0`; bez pełnego JOIN teczka×obieg |

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

## Paginacja akt sprawy (endpoint show)

Opcjonalny payload `aktaSprawy: { page, limit }` w `POST /api/v1/cases/{caseUid}` (tylko `dntas=0`).

| Pole | Domyślnie | Zakres |
|------|-----------|--------|
| `page` | 1 | min 1 |
| `limit` | 20 | 10–100 |

- **Brak `aktaSprawy`** — pełna lista akt (`limit: 10000`, sort `data_rejestracji desc`).
- **Z `aktaSprawy`** — jedna strona akt + `meta.aktaSprawy`: `count`, `page`, `limit`, `has_prev`, `has_next`.
- **DNTAS** (`dntas=1`) — `aktaSprawy` zawsze `[]`; paginacja ignorowana.

Implementacja: `AktaSprawyPaginacja` → `KryteriaWyszukiwaniaDokumentow::forTeczkaUidPaginated` → `DocumentListQueryFactory` → `getList` + `getListCount` (przy `USE_MATERIALIZED_VIEWS=true`: `api_cache.api_document_list WHERE teczka_uid = ?`).

---

## Otwarte kwestie

Q-01, Q-02, Q-10 — [open-questions.md](../open-questions.md)

## Powiązana dokumentacja

- [../database.md](../database.md) — łańcuch A vs B/C
- [document-queries.md](document-queries.md)
