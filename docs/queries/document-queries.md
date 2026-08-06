# Queries — Dokumenty (Document)

Pliki: `AbstractDocumentQuery.php`, `DocumentListQuery.php`, `DocumentListQueryMV.php`, `ApiDocumentListMaterializedView.php`, `DocumentQuery.php`

Konsumenci (grep): `DocumentService`, `Document\HistoryService`, `AttachmentService`

---

## Źródło list (live SQL vs MV)

Globalny przełącznik: **`USE_MATERIALIZED_VIEWS`** — patrz [case-queries.md](case-queries.md#źródło-list-live-sql-vs-mv) (wspólny dla wszystkich list API).

| Element (dokumenty) | Wartość |
|---------|---------|
| Factory | `DocumentListQueryFactory::make(TypFiltrDokument $filtry)` |
| MV | `api_cache.api_document_list` (1 wiersz / `id_dokumentu`, UNION 5 gałęzi + `DISTINCT ON`; schemat: `DB_MV_SCHEMA`) |
| Refresh | `php artisan documents:refresh-list-mv` lub `materialized-views:refresh` (`--drop`) |

`DocumentService::getList()` i lista akt w sprawie (`getDocumentsListByCaseUID`, `getDocumentsListByCaseUIDPaginated`) wywołują factory **per request** — przy `USE_MATERIALIZED_VIEWS=true` także scoped (`teczka_uid`). Show (`getDocumentDetails`) nadal używa legacy `DocumentQuery`.

**Scoped (`teczka_uid`):** `DocumentListQueryMV` filtruje `adl.teczka_uid = ?` (bez workstation scope). Kolumna `teczka_uid` w MV pochodzi z `et.teczka_uid` (gałąź `bez_sprawy` → `NULL`). LIMIT/OFFSET stosowane także dla scoped (pełna lista akt: `limit: 10000`; paginacja show sprawy: `aktaSprawy.page/limit` — patrz [case-queries.md](case-queries.md#paginacja-akt-sprawy-endpoint-show)).

Po dodaniu kolumny do istniejącego widoku wymagany `php artisan documents:refresh-list-mv --drop`.

Po zmianie SELECT/JOIN w `DocumentListQuery` (gdy prod ma `USE_MATERIALIZED_VIEWS=true`):
1. `ApiDocumentListMaterializedView` (kolumny widoku)
2. `DocumentListQueryMV` (WHERE; sort: `SortowanieDokumentow::toOrderBySql()` na kolumnach MV)
3. `docs/queries/document-queries.md`

---

## AbstractDocumentQuery

Wspólne filtry WHERE dla 5 gałęzi UNION (`TypUnionDokumentu` — wewnętrzny enum w `Queries/Document/`).

### Gałęzie UNION (`TypUnionDokumentu` — SQL)

| Gałąź SQL | FROM | `typ_powiazania_dokumentu` | `typ_dokumentu` | Dodatkowy filtr |
|-----------|------|---------------------------|-----------------|-----------------|
| `dok_wychodzacy_w_sprawie` | `eurzad_pismo ep` | `w_sprawie` | `dok_wychodzacy` | — |
| `dok_niewychodzacy_inicjujacy_sprawe` | `eurzad_sprawa es` | `inicjujacy_sprawe` | `dok_przychodzacy` / `dok_wewnetrzny` (z `ef.form_typ`) | `gp.name NOT IN ('zwrot','zwrotka')`, klasyfikacja EXISTS `teczka.sprawa_uid` |
| `dok_niewychodzacy_w_sprawie` | `eurzad_sprawa es` | `w_sprawie` | jw. | jw. + via `teczka_zawartosc`, bez wiodącej |
| `dok_niewychodzacy_bez_sprawy` | `eurzad_sprawa es` | `bez_sprawy` | jw. | jw. + brak powiązania ze sprawą |
| `dok_zpo` | `eurzad_sprawa es` | `zpo` | `dok_zpo` | `gp.name IN ('zwrot','zwrotka')` |

Mapowanie filtra `filtry.typ_procesu` (`TypDokument`) → gałęzie UNION:

| `TypDokument` | Gałęzie UNION |
|---------------|---------------|
| brak filtra | wszystkie 5 |
| `DokWychodzacy` | `dok_wychodzacy_w_sprawie` |
| `DokZpo` | `dok_zpo` |
| `DokPrzychodzacy` | 3 gałęzie niewychodzące + `ef.form_typ = 'external'` |
| `DokWewnetrzny` | 3 gałęzie niewychodzące + `ef.form_typ = 'internal'` |

### TypDokument (API — enum string)

Endpoint `GET|POST /api/v1/documents/types` zwraca 4 typy biznesowe (`TypDokument` enum). Filtr `filtry.typ_procesu` przyjmuje te same wartości string.

| Enum | `id` (JSON, filtry) | `label` (JSON) |
|------|---------------------|----------------|
| `DokPrzychodzacy` | `dok_przychodzacy` | Dokumenty przychodzące |
| `DokWewnetrzny` | `dok_wewnetrzny` | Dokumenty wewnętrzne |
| `DokWychodzacy` | `dok_wychodzacy` | Dokumenty wychodzące |
| `DokZpo` | `dok_zpo` | Potwierdzenia odbioru |

`DokPrzychodzacy` vs `DokWewnetrzny`: rozróżnienie po `ef.form_typ` (`external` / `internal`) w gałęziach niewychodzących.

### TypPowiazaniaDokumentu (API — enum string)

Eksponowany w liście i show dokumentu (`typPowiazaniaDokumentu`). Wartości: `inicjujacy_sprawe`, `w_sprawie`, `bez_sprawy`, `zpo`.

### TypFormularza (bez zmian)

`typFormularza` (`internal` / `external`) nadal zwracany w API dokumentów i spraw. Dla dokumentów: semantycznie istotny przy klasyfikacji niewychodzących (`DokPrzychodzacy` / `DokWewnetrzny`); dla wychodzących i ZPO — informacyjny.

- `/documents/types` (opcje filtra): `{ "id": "…", "label": "…" }` (`toFilterOption()`)
- `danePodstawowe.values`: `typDokumentu`, `typFormularza`, `typPowiazaniaDokumentu` jako `{ "name": "…", "label": "…" }` (`toApi()`)

- Brak filtra `typ_procesu` → 5 gałęzi UNION
- Poprawny filtr `typ_procesu` → podzbiór gałęzi (patrz tabela wyżej)
- Nieprawidłowa wartość filtra → **422**

Kolumny SQL `typ_dokumentu`, `typ_powiazania_dokumentu` → mapowanie w `DocumentService` (`tryFromWiersza` + `toApi()`), nie w Query.

### Tryby WHERE

| Tryb | Warunek | Efekt |
|------|---------|-------|
| Scoped to teczka | `teczka_uid != null` | per gałąź: inicjujący → `INNER JOIN et` + `et.sprawa_uid = es.sprawa_uid`; w sprawie → `INNER JOIN et` + `etz`; bez sprawy → wykluczony (`1=0`); wychodzący/ZPO → `et.teczka_uid = ?`; LIMIT/OFFSET z `paginacja` |
| Globalny | domyślny | pełne filtry + scope stanowisk + paginacja |

### Filtry (`TypFiltrDokument`)

| Pole DTO | Klucz JSON | Uwagi SQL |
|----------|------------|-----------|
| `documentId` | `filtry.documentId` | `DokWychodzacy`: `ep.pismo_uid`; inne: `es.sprawa_uid` (hex UID, nie instanceId) |
| `typProcesu` | `filtry.typ_procesu` | `TypDokument` enum string; brak = wszystkie gałęzie; nieprawidłowa wartość → 422 |
| `typFormularza` | `filtry.typ_formularza` | `ef.form_typ = ?` (`TypFormularza`: `internal` \| `external`; tylko tryb globalny) |
| `trescPisma` | `filtry.tresc_pisma` | **brak WHERE** — Q-03 |
| `pokazUdostepnione` | `filtry.pokaz_udostepnione` | patrz README — obecność klucza (Q-02) |
| `rok` | `filtry.rok` | zakres dat: `>= '{rok}-01-01'` i `< '{rok+1}-01-01'` (wychodzące: `ep.pismo_createdate`; niewychodzące: `COALESCE(fd_data_rej, esp.sprawa_createdate)`) |
| pozostałe | patrz case-queries / kod AbstractDocumentQuery | |

Filtr `oznaczenie`: gdy wartość składa się z cyfr (`ctype_digit`), dodawany warunek `gi."instanceId" = ?` — stąd numeric `\d+` w routes ma sens dla wyszukiwania, niekoniecznie jako PK dokumentu.

---

## DocumentListQuery

### Start zapytania — zależy od gałęzi UNION

| Gałąź | FROM | Obieg |
|-------|------|-------|
| niewychodzące (3 gałęzie), `DokZpo` | `eurzad_sprawa es` | `eurzad_obieg` (`max_status_sprawy_id > 0`) |
| `dok_wychodzacy_w_sprawie` | `eurzad_pismo ep` | LATERAL `eurzad_pismo_obieg` (ostatni wiersz) |

### JOIN teczki per gałąź (`TypUnionDokumentu`)

| Gałąź | Globalnie | Scoped (`teczka_uid`) |
|-------|-----------|------------------------|
| `dok_wychodzacy_w_sprawie` | `etz` (`teczka_zawartosc_uid = ep.pismo_uid`) → `et` | `INNER JOIN et ON et.teczka_uid = ?` |
| `dok_niewychodzacy_inicjujacy_sprawe` | `LEFT JOIN et ON et.sprawa_uid = es.sprawa_uid` | `INNER JOIN et ON et.teczka_uid = ?` + WHERE `et.sprawa_uid = es.sprawa_uid` |
| `dok_niewychodzacy_w_sprawie` | `INNER JOIN etz` (`teczka_zawartosc_uid = es.sprawa_uid`) → `LEFT JOIN et` | `INNER JOIN et` + `INNER JOIN etz` membership |
| `dok_niewychodzacy_bez_sprawy` | `LEFT JOIN et ON false` (`znak_sprawy` = NULL) | wykluczony (`1=0`) |
| `dok_zpo` | `etz2` → `etz` → `et` (Q-06) | `INNER JOIN et ON et.teczka_uid = ?` |

Klasyfikacja niewychodzących (wzajemnie rozłączna, WHERE): EXISTS `teczka.sprawa_uid`; EXISTS `teczka_zawartosc` bez wiodącej; brak obu.

### Potwierdzenia odbioru — zwrotki (`DokZpo`)

W API: `TypDokument::DokZpo` (`dok_zpo`), etykieta w `DocumentService::getTypes`: „potwierdzenia odbioru”.

**Zwrotka nie jest `eurzad_pismo`.** To osobne pismo workflow w `eurzad_sprawa` (`es`), rozpoznawane po procesie Galaxii:

```sql
INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
WHERE gp.name IN ('zwrot', 'zwrotka')
```

Dokumenty wystawiane w sprawie (decyzje, korespondencja) to **`DokWychodzacy`** (`eurzad_pismo`); zwrotki idą osobną gałęzią UNION.

#### Powiązanie ze sprawą (teczką)

Łańcuch w `teczkaJoinsSql` (`DokZpo`):

```sql
JOIN eurzad_teczka_zawartosc etz2 ON etz2.teczka_zawartosc_uid = es.sprawa_uid
JOIN eurzad_teczka_zawartosc etz  ON etz.teczka_zawartosc_uid = etz2.teczka_uid
JOIN eurzad_teczka et             ON et.teczka_uid = etz.teczka_uid
```

| Krok | Wniosek z SQL (nie z DDL) |
|------|---------------------------|
| `etz2` | Zwrotka (`es.sprawa_uid`) jest **zawartością** kontenera `etz2.teczka_uid`. |
| `etz` | Ten kontener (`etz2.teczka_uid`) jest **zawartością** wyższego poziomu. |
| `et` | Docelowa **teczka sprawy** (`teczka_uid`, znak sprawy itd.). |

Dla porównania — pismo przychodzące w sprawie (`DokPrzychodzacy`, globalnie) ma teczkę z LATERAL: wiodąca (`t.sprawa_uid = es.sprawa_uid`) lub via `teczka_zawartosc`. Zwrotka jest w drzewie `teczka_zawartosc` **o jeden poziom głębiej**.

#### Powiązanie z dokumentem wystawionym w sprawie

W Queries **brak** jawnej kolumny FK (np. `parent_pismo_uid`) łączącej zwrotkę z `eurzad_pismo`. Powiązanie jest **pośrednie** przez drzewo teczki: pośredni węzeł `etz2.teczka_uid` (użyty jako `teczka_zawartosc_uid` w kolejnym joinie) najpewniej wskazuje dokument/pismo, do którego zwrotka się odnosi — **do weryfikacji na danych EZD** (Q-06).

#### Obieg i identyfikatory

| Aspekt | Zwrotka (`DokZpo`) | Dokument w sprawie (`DokWychodzacy`) |
|--------|-----------------|----------------------------|
| Tabela główna | `eurzad_sprawa` | `eurzad_pismo` |
| `id_dokumentu` w API | `es.sprawa_uid` | `ep.pismo_uid` |
| Obieg | `eurzad_obieg` (`max_status_sprawy_id > 0`) | `eurzad_pismo_obieg` (LATERAL, ostatni wiersz) |
| Formularz | `eurzad_form_dane` | `eurzad_form_pisma_dane` |

### UNION + DISTINCT ON

`DISTINCT ON (id_dokumentu)` w każdej gałęzi; dedup przed zewnętrznym ORDER BY.

Każda gałęź z własnym `ORDER BY` musi być w nawiasach przed `UNION` (PostgreSQL) — `DocumentListQuery::buildUnionParts()` i `ApiDocumentListMaterializedView::definitionSql()`.

`getList()` i `getListCount()` używają tego samego `buildUnionBranchSql()` — COUNT owija pełne gałęzie UNION w podzapytanie.

### Duplikat JOIN (Q-07)

`pismoInnerJoinsSql`: `eurzad_sprawa_przedluzanie` jako `esp` i `sp` — ten sam warunek JOIN.

### SELECT — różnice es vs ep

Wspólne kolumny z `commonSelectSql()` (wszystkie gałęzie UNION): m.in. `nazwa_procesu`, `id_procesu`, `typ_formularza` (`ef.form_typ`). Każda gałąź dodaje `typ_dokumentu` i `typ_powiazania_dokumentu`.

| Kolumna | es (`DokPrzychodzacy`/`DokZpo`) | ep (`DokWychodzacy`) |
|---------|----------------|------------|
| `id_dokumentu` | `es.sprawa_uid` | `ep.pismo_uid` |
| `typ_formularza` | `ef.form_typ` | `ef.form_typ` |
| `has_pozostali_interesanci` | EXISTS | literal `false` (komentarz w kodzie) |

Join `eurzad_form ef`: `INNER JOIN ef ON (gp.normalized_name = ef.form_name)` w `commonInnerJoinSql()` (wspólny dla wszystkich gałęzi UNION).

---

## DocumentListQueryMV

`FROM api_cache.api_document_list adl` — bez JOIN-ów `eurzad_*` (poza `EXISTS` na `galaxia_instance_users` przy `pokaz_udostepnione`).

Filtry mapowane na kolumny MV (`typ_dokumentu`, `status`, `data_rejestracji`, `dokument_tytul`, `tresc_wniosku`, `instance_id`, …). `filtry.typ_procesu` → `adl.typ_dokumentu = ?`. COUNT = `COUNT(*)` na MV.

Sortowanie: `SortowanieDokumentow::toOrderBySql()` + `id_dokumentu ASC` (tiebreaker).

Budowa MV: `ApiDocumentListMaterializedView` + `DocumentListMvRefreshService` / `documents:refresh-list-mv`.

Kolumny MV tylko pod filtry (nie w SELECT API): `status` (symbol `eo`/`epo`), `instance_id`, `teczka_uid` (scoped akt sprawy).

Indeks scoped: `api_document_list_teczka_data_rej_idx (teczka_uid, data_rejestracji DESC)`.

---

## DocumentQuery

### Stałe typu — nazewnictwo w kodzie

```php
DOCUMENT_TYPE_PISMO = 'pismo'      // EXISTS w eurzad_sprawa
DOCUMENT_TYPE_DOKUMENT = 'dokument' // EXISTS w eurzad_pismo
```

### Historia obiegu (Query)

Metody operują na `eurzad_pismo_obieg` (`pismo_obieg_id`).

### DocumentService — niespójność poza Query (Q-12, potwierdzone)

W `mapToDokumentDto` (tylko `GET|POST /api/v1/documents/{documentId}`):

- `historiaObiegu` — `DokWychodzacy`: `DocumentHistoryService`; inne: `CaseHistoryService` (`eurzad_obieg`)
- `utworzyl` — **zawsze** `documentQuery->getFirstRowFromHistory` → `eurzad_pismo_obieg`, niezależnie od `typ_dokumentu` (Q-12)
- `rejestry` — `RejestrPrzypisaniaDto` (sekcja: `sectionLabel`, `labels`, `values[]`); docs: [registry-assignment-queries.md](registry-assignment-queries.md)
- `wysylki` — `RejestrRpwPrzypisaniaDto` (sekcja jak wyżej); docs: [registry-assignment-rpw-queries.md](registry-assignment-rpw-queries.md)

To bug lub świadoma decyzja (dot. `utworzyl`) — DO WYJAŚNIENIA.

### getProcessNames (Q-08)

UNION `eurzad_sprawa` + `eurzad_pismo`. W obu gałęziach JOIN `galaxia_instance_users giu` — **inna** logika scope niż `buildWorkstationCondition` (brak EXISTS alternatywy).

### getStatuses

UNION statusów z obiegu spraw (`eurzad_obieg`, `max_status_sprawy_id > 0`) i pism (`DISTINCT ON (pismo_uid)` po `pismo_obieg_id DESC` — bez skorelowanego `MAX(createdate)` per wiersz). Bez filtrów requestu.

---

## QueryBuilder.php (Q-04)

Indeks/glob repozytorium może wskazywać `Document/QueryBuilder.php`; odczyt pliku z dysku w środowisku dev zwracał „not found". Brak referencji w kodzie PHP (grep). DO WYJAŚNIENIA.

---

## ProcessQuery (Q-05)

Plik `Queries/ProcessQuery.php` — brak importu w Services. Metody: `getBySprawaUid`, `getProcesNameByPID`, …

---

## Pułapki — indeks

| ID | Temat |
|----|-------|
| Q-02 | `pokaz_udostepnione` — obecność vs wartość |
| Q-03 | `tresc_pisma` bez WHERE |
| Q-06 | self-join teczka `DokZpo` — [zwrotki](#potwierdzenia-odbioru--zwrotki-dokzpo) |
| Q-07 | duplikat przedluzanie |
| Q-08 | getProcessNames scope |
| Q-12 | DocumentService utworzyl vs historia |

Pełna lista: [open-questions.md](../open-questions.md)

## Powiązana dokumentacja

- [../database.md](../database.md) — łańcuchy A/B/C
- [case-queries.md](case-queries.md)
