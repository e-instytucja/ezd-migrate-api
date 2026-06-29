# Queries — Dokumenty (Document)

Pliki: `AbstractDocumentQuery.php`, `DocumentListQuery.php`, `DocumentQuery.php`

Konsumenci (grep): `DocumentService`, `Document\HistoryService`, `AttachmentService`

---

## AbstractDocumentQuery

Wspólne filtry WHERE dla 4 typów UNION.

### Stałe typów

| Stała | Wartość | FROM w SQL | Filtr procesu |
|-------|---------|------------|---------------|
| `TYP_DOK_PRZYCHODZACY_INICJUJACY` | 1 | `eurzad_sprawa es` | `gp.name NOT IN ('zwrot','zwrotka')` |
| `TYP_DOK_WYCHADZACY_W_SPRAWIE` | 2 | `eurzad_pismo ep` | — |
| `TYP_DOK_PRZYCHODZACY_W_SPRAWIE` | 3 | `eurzad_sprawa es` | `NOT IN ('zwrot','zwrotka')` |
| `TYP_DOK_PRZYCHODZACY_ZPO` | 4 | `eurzad_sprawa es` | `IN ('zwrot','zwrotka')` |

Typ 1 vs 3: różny JOIN teczki + **różna kolejność** joinów w SQL (Q-11). Różnica biznesowa poza SQL: DO WYJAŚNIENIA.

### Tryby WHERE

| Tryb | Warunek | Efekt |
|------|---------|-------|
| Scoped to teczka | `teczka_uid != null` | tylko `et.teczka_uid = ?`; bez LIMIT/OFFSET w DocumentListQuery |
| Globalny | domyślny | pełne filtry + scope stanowisk + paginacja |

### Filtry (`TypFiltrDokument`)

| Pole DTO | Klucz JSON | Uwagi SQL |
|----------|------------|-----------|
| `documentId` | `filtry.documentId` | typ 2: `ep.pismo_uid`; inne: `es.sprawa_uid` (hex UID, nie instanceId) |
| `trescPisma` | `filtry.tresc_pisma` | **brak WHERE** — Q-03 |
| `pokazUdostepnione` | `filtry.pokaz_udostepnione` | patrz README — obecność klucza (Q-02) |
| pozostałe | patrz case-queries / kod AbstractDocumentQuery | |

Filtr `oznaczenie`: gdy wartość składa się z cyfr (`ctype_digit`), dodawany warunek `gi."instanceId" = ?` — stąd numeric `\d+` w routes ma sens dla wyszukiwania, niekoniecznie jako PK dokumentu.

---

## DocumentListQuery

### Start zapytania — zależy od typu UNION

| Typ | FROM | Obieg |
|-----|------|-------|
| 1, 3, 4 | `eurzad_sprawa es` | `eurzad_obieg` (`max_status_sprawy_id > 0`) |
| 2 | `eurzad_pismo ep` | LATERAL `eurzad_pismo_obieg` (ostatni wiersz) |

**Różnica względem CaseListQuery:** brak startu od `eurzad_teczka`; teczka opcjonalna (LEFT/INNER w zależności od scope).

### JOIN teczki per typ

| Typ | Łańcuch |
|-----|---------|
| 1 | `et ON es.sprawa_uid = et.sprawa_uid` |
| 2 | `etz.teczka_zawartosc_uid = ep.pismo_uid` → `et` |
| 3 | `etz.teczka_zawartosc_uid = es.sprawa_uid` → `et` |
| 4 | `etz2` → `etz` → `et` (self-join zawartości, Q-06) |

### Potwierdzenia odbioru — zwrotki (typ 4)

W API: `TYP_DOK_PRZYCHODZACY_ZPO` (`document_group_type = 4`), etykieta w `DocumentService::getTypes`: „potwierdzenia odbioru”.

**Zwrotka nie jest `eurzad_pismo`.** To osobne pismo workflow w `eurzad_sprawa` (`es`), rozpoznawane po procesie Galaxii:

```sql
INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
WHERE gp.name IN ('zwrot', 'zwrotka')
```

Dokumenty wystawiane w sprawie (decyzje, korespondencja) to **typ 2** (`eurzad_pismo`); zwrotki idą osobną gałęzią UNION.

#### Powiązanie ze sprawą (teczką)

Łańcuch w `teczkaJoinsSql` (typ 4):

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

Dla porównania — pismo inicjujące w sprawie (typ 3) ma **jeden** hop: `etz.teczka_zawartosc_uid = es.sprawa_uid` → `et`. Zwrotka jest w drzewie `teczka_zawartosc` **o jeden poziom głębiej**.

#### Powiązanie z dokumentem wystawionym w sprawie

W Queries **brak** jawnej kolumny FK (np. `parent_pismo_uid`) łączącej zwrotkę z `eurzad_pismo`. Powiązanie jest **pośrednie** przez drzewo teczki: pośredni węzeł `etz2.teczka_uid` (użyty jako `teczka_zawartosc_uid` w kolejnym joinie) najpewniej wskazuje dokument/pismo, do którego zwrotka się odnosi — **do weryfikacji na danych EZD** (Q-06).

#### Obieg i identyfikatory

| Aspekt | Zwrotka (typ 4) | Dokument w sprawie (typ 2) |
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

| Kolumna | es (typ 1/3/4) | ep (typ 2) |
|---------|----------------|------------|
| `id_dokumentu` | `es.sprawa_uid` | `ep.pismo_uid` |
| `has_pozostali_interesanci` | EXISTS | literal `false` (komentarz w kodzie) |

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

- `historiaObiegu` — typ 2: `DocumentHistoryService`; inne typy: `CaseHistoryService` (`eurzad_obieg`)
- `utworzyl` — **zawsze** `documentQuery->getFirstRowFromHistory` → `eurzad_pismo_obieg`, niezależnie od `document_group_type`
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
| Q-06 | self-join teczka typ 4 — [zwrotki](#potwierdzenia-odbioru--zwrotki-typ-4) |
| Q-07 | duplikat przedluzanie |
| Q-08 | getProcessNames scope |
| Q-11 | typ 1 vs 3 biznesowo |
| Q-12 | DocumentService utworzyl vs historia |

Pełna lista: [open-questions.md](../open-questions.md)

## Powiązana dokumentacja

- [../database.md](../database.md) — łańcuchy A/B/C
- [case-queries.md](case-queries.md)
