# Warstwa Queries — konwencje

Katalog: `app/Source/V1/Queries/`

## Wzorzec architektoniczny

```
Request DTO → Query → surowe wiersze → Service → DTO odpowiedzi
```

Queries **nie zwracają** DTO — mapowanie w Services.

## Organizacja plików

| Katalog / plik | Odpowiedzialność |
|----------------|------------------|
| `Case/` | CaseListQuery, CaseQuery |
| `Document/` | AbstractDocumentQuery, DocumentListQuery, DocumentQuery; `QueryBuilder.php` — **Q-04** |
| `Registry/` | RegistryAssignmentQuery, RegistryAssignmentRpwQuery — patrz [registry-assignment-queries.md](registry-assignment-queries.md), [registry-assignment-rpw-queries.md](registry-assignment-rpw-queries.md) |
| `Structure/` | WorkstationQuery, WorkstationScopeQuery |
| `Form/`, `Attachment/`, `Suppliant/`, `Dictionary/` | poza Fazą 1 docs |
| `ProcessQuery.php` | root; brak konsumenta w Services — **Q-05** |

## Style zapytań

1. Raw SQL (`DB::select`) — CaseListQuery, DocumentListQuery, część FormQuery
2. Query Builder (`DB::table`) — CaseQuery, DocumentQuery, WorkstationQuery

## Parametryzacja

Metoda `bind()` → `?` + tablica `$bindings`.

**WNIOSEK Z KODU (nie potwierdzone komentarzem autora):** `array_merge($bindings, $bindings)` w UNION (`FormQuery`, `DocumentQuery::getProcessNames`) — dwa identyczne zestawy parametrów dla obu gałęzi UNION.

## Scope stanowisk

`ApiKonfiguracja::madkomWorkstationIds` — w listach globalnych pusta tablica → Exception `[err_10_appendWorkstationScope]`.

### `pokaz_udostepnione` — semantyka w kodzie

Warunek w Query: `$filtry->pokazUdostepnione !== null` przekazywany jako `$includeShared` do `buildWorkstationCondition()`.

| Stan pola w JSON | `$includeShared` | SQL |
|------------------|------------------|-----|
| klucz **nieobecny** (null po parse) | `false` | tylko `gi.workstation IN (...)` |
| klucz **obecny**, wartość `0` lub `1` | `true` | właściciel OR EXISTS `galaxia_instance_users` |

**DO WYJAŚNIENIA (Q-02):** kod nie używa wartości bool — decyduje **obecność klucza**. Czy to zamierzone?

`TypFiltrSpraw` dziedziczy po `TypFiltrDokument` — pole `pokazUdostepnione` wspólne.

## PostgreSQL w projekcie

| Konstrukcja | Gdzie |
|-------------|-------|
| `DISTINCT ON (id_dokumentu)` | DocumentListQuery |
| `LATERAL (... LIMIT 1)` | DocumentListQuery typ 2 |
| `ILIKE` | filtry tekstowe |
| `"instanceId"`, `"pId"`, `"groupName"` | cudzysłowy na camelCase |

## Symetria list vs count

| Query | Różnica |
|-------|---------|
| CaseListQuery | `getList()` zawsze LEFT JOIN petenta; `getListCount()` — tylko gdy filtr `interesant` |
| DocumentListQuery | list i count — te same UNION parts |

## Mapowanie Service → Query

| Service | Queries |
|---------|---------|
| `CaseService` | CaseListQuery, CaseQuery, FormQuery, WorkstationQuery, UugQuery |
| `Case\HistoryService` | CaseQuery |
| `DocumentService` | DocumentListQuery, DocumentQuery, UugQuery |
| `Document\HistoryService` | DocumentQuery |
| `AttachmentService` | AttachmentQuery, CaseQuery, DocumentQuery, FormQuery |
| `FormService` | FormQuery |
| `FormDaneService` | GroupQuery, WorkstationQuery |
| `SupliantService` | SuppliantQuery |
| `DictionaryService` | DoctionaryQuery |
| `WorkstationService` | WorkstationQuery |
| `EmployeeService` | UugQuery |
| `RegistryAssignmentService` | RegistryAssignmentQuery, CaseQuery, WorkstationScopeService |
| `RegistryAssignmentRpwService` | RegistryAssignmentRpwQuery, RegistryAssignmentQuery, CaseQuery, WorkstationScopeService, SupliantService, EmployeeService |

## Dokumentacja per domena

- [case-queries.md](case-queries.md)
- [document-queries.md](document-queries.md)
- [registry-assignment-queries.md](registry-assignment-queries.md)
- [registry-assignment-rpw-queries.md](registry-assignment-rpw-queries.md)

## Otwarte kwestie

[open-questions.md](../open-questions.md)
