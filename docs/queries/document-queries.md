# Queries — Dokumenty (Document)

Pliki: `AbstractDocumentQuery.php`, `DocumentListQuery.php`, `DocumentQuery.php`

Konsumenci (grep): `DocumentService`, `Document\HistoryService`, `AttachmentService`

---

## AbstractDocumentQuery

Wspólne filtry WHERE dla 3 gałęzi UNION (`TypDokument`).

### Gałęzie UNION (`TypDokument`)

| Enum | Wartość JSON / filtra | FROM w SQL | Dodatkowy filtr procesu |
|------|----------------------|------------|-------------------------|
| `DokPrzychodzacy` | `dok_przychodzacy` | `eurzad_sprawa es` | `gp.name NOT IN ('zwrot','zwrotka')` |
| `DokWychodzacy` | `dok_wychodzacy` | `eurzad_pismo ep` | — |
| `DokZpo` | `dok_zpo` | `eurzad_sprawa es` | `gp.name IN ('zwrot','zwrotka')` |

`DokPrzychodzacy`: jedna gałąź UNION; `znak_sprawy` = `et.teczka_znak_sprawy`. Scoped do teczki: `INNER JOIN eurzad_teczka et` + membership WHERE; globalnie: `LEFT JOIN LATERAL` (wiodąca lub via `teczka_zawartosc`, `LIMIT 1`).

### TypDokument (API — enum string)

Endpoint `GET|POST /api/v1/documents/types` zwraca 3 typy biznesowe (`TypDokument` enum). Filtr `filtry.typ_procesu` przyjmuje te same wartości string.

| Enum | `id` (JSON, filtry) | `name` (JSON, show) | `label` (JSON) |
|------|---------------------|---------------------|----------------|
| `DokWychodzacy` | `dok_wychodzacy` | `dok_wychodzacy` | Dokumenty wychodzące |
| `DokPrzychodzacy` | `dok_przychodzacy` | `dok_przychodzacy` | Dokumenty przychodzące |
| `DokZpo` | `dok_zpo` | `dok_zpo` | Potwierdzenia odbioru |

- `/documents/types` (opcje filtra): `{ "id": "…", "label": "…" }` (`toFilterOption()`)
- `danePodstawowe.values.typDokumentu` / `typFormularza` (show): `{ "name": "…", "label": "…" }` (`toApi()`); `null` gdy brak wartości

- Brak filtra `typ_procesu` → `TypDokument::wszystkie()` (wszystkie gałęzie UNION)
- Poprawny filtr → jedna gałąź (`[$filtry->typProcesu]`)
- Nieprawidłowa wartość filtra → **422** (`InvalidArgumentException` w `parseTypProcesu`)

Kolumna SQL `typ_dokumentu` → show: `typDokumentu` (`?TypDokument` w `DokumentDanePodstawoweWartosciDto`, JSON: `{name, label}` lub `null`). Lista (`DocumentService::getList`, `getDocumentsListByCaseUID`): `typ_dokumentu` / `typ_formularza` jako `{name, label}` lub `null` — mapowanie w serwisie (`tryFromWiersza` + `toApi()`), nie w Query.

### Tryby WHERE

| Tryb | Warunek | Efekt |
|------|---------|-------|
| Scoped to teczka | `teczka_uid != null` | `DokPrzychodzacy`: `INNER JOIN et` + membership `(es.sprawa_uid = et.sprawa_uid OR EXISTS teczka_zawartosc)`; `DokWychodzacy`/`DokZpo`: `et.teczka_uid = ?`; bez LIMIT/OFFSET w DocumentListQuery |
| Globalny | domyślny | pełne filtry + scope stanowisk + paginacja |

### Filtry (`TypFiltrDokument`)

| Pole DTO | Klucz JSON | Uwagi SQL |
|----------|------------|-----------|
| `documentId` | `filtry.documentId` | `DokWychodzacy`: `ep.pismo_uid`; inne: `es.sprawa_uid` (hex UID, nie instanceId) |
| `typProcesu` | `filtry.typ_procesu` | `TypDokument` enum string; brak = wszystkie gałęzie; nieprawidłowa wartość → 422 |
| `typFormularza` | `filtry.typ_formularza` | `ef.form_typ = ?` (`TypFormularza`: `internal` \| `external`; tylko tryb globalny) |
| `trescPisma` | `filtry.tresc_pisma` | **brak WHERE** — Q-03 |
| `pokazUdostepnione` | `filtry.pokaz_udostepnione` | patrz README — obecność klucza (Q-02) |
| pozostałe | patrz case-queries / kod AbstractDocumentQuery | |

Filtr `oznaczenie`: gdy wartość składa się z cyfr (`ctype_digit`), dodawany warunek `gi."instanceId" = ?` — stąd numeric `\d+` w routes ma sens dla wyszukiwania, niekoniecznie jako PK dokumentu.

---

## DocumentListQuery

### Start zapytania — zależy od `TypDokument`

| TypDokument | FROM | Obieg |
|-------------|------|-------|
| `DokPrzychodzacy`, `DokZpo` | `eurzad_sprawa es` | `eurzad_obieg` (`max_status_sprawy_id > 0`) |
| `DokWychodzacy` | `eurzad_pismo ep` | LATERAL `eurzad_pismo_obieg` (ostatni wiersz) |

**Różnica względem CaseListQuery:** brak startu od `eurzad_teczka`; teczka dołączana osobno (scoped `DokPrzychodzacy`: INNER JOIN; globalnie `DokPrzychodzacy`: LATERAL).

### JOIN teczki per typ

| TypDokument | Łańcuch |
|-------------|---------|
| `DokPrzychodzacy` (scoped) | `INNER JOIN eurzad_teczka et ON et.teczka_uid = ?` + membership WHERE |
| `DokPrzychodzacy` (global) | `LEFT JOIN LATERAL (...)` — wiodąca lub via `teczka_zawartosc`, `LIMIT 1`; alias `et` |
| `DokWychodzacy` | `LEFT JOIN etz` (`teczka_zawartosc_uid = ep.pismo_uid`) → `LEFT JOIN et` |
| `DokZpo` | `LEFT JOIN etz2` → `etz` → `et` (self-join zawartości, Q-06) |

Wszystkie typy: `znak_sprawy` = `et.teczka_znak_sprawy` (bez COALESCE `et_w`/`et_z`).

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

### Duplikat JOIN (Q-07)

`pismoInnerJoinsSql`: `eurzad_sprawa_przedluzanie` jako `esp` i `sp` — ten sam warunek JOIN.

### SELECT — różnice es vs ep

Wspólne kolumny z `commonSelectSql()` (wszystkie typy UNION): m.in. `nazwa_procesu`, `id_procesu`, `typ_formularza` (`ef.form_typ` → `TypFormularza`). Każda gałąź dodaje `'…' AS typ_dokumentu` (wartość `TypDokument`).

| Kolumna | es (`DokPrzychodzacy`/`DokZpo`) | ep (`DokWychodzacy`) |
|---------|----------------|------------|
| `id_dokumentu` | `es.sprawa_uid` | `ep.pismo_uid` |
| `typ_formularza` | `ef.form_typ` | `ef.form_typ` |
| `has_pozostali_interesanci` | EXISTS | literal `false` (komentarz w kodzie) |

Join `eurzad_form ef`: `INNER JOIN ef ON (gp.normalized_name = ef.form_name)` w `commonInnerJoinSql()` (wspólny dla wszystkich gałęzi UNION).

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

UNION statusów z obiegu spraw (`max_status_sprawy_id > 0`) i pism (wiersz z MAX `createdate` per pismo). Bez filtrów requestu.

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
