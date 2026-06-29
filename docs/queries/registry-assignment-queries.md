# Registry assignment queries (zwykłe rejestry)

Katalog: `app/Source/V1/Queries/Registry/RegistryAssignmentQuery.php`  
Serwis: `app/Source/V1/Services/Registry/RegistryAssignmentService.php`  
Kontroler: `app/Http/Controllers/Api/V1/RegistryAssignmentsController.php`

Read model zwykłych rejestrów EZD (`rejestr_typ <> 'rejestr_pism_wychodzacych'`). RPW: [registry-assignment-rpw-queries.md](registry-assignment-rpw-queries.md).

## Endpointy

| Endpoint | Scope wpisów |
|----------|----------------|
| `GET\|POST /api/v1/documents/{documentId}/registry-assignments` | brak |
| `GET\|POST /api/v1/cases/{caseUid}/registry-assignments` | brak |
| `GET\|POST /api/v1/dntas/{caseUid}/registry-assignments` | brak |
| `GET\|POST /api/v1/registries/types` | brak |
| `GET\|POST /api/v1/registry-assignments` | activity na rejestrze (`madkomWorkstationIds` wymagane) |
| `GET\|POST /api/v1/registry-assignments/{registryAssignmentId}` | brak (klucz: `rz.id`, tylko `\d+`) |

Scope globalny: [workstation-scope-queries.md](workstation-scope-queries.md) — profil `RegistryBrowse`.

## DTO odpowiedzi

Wzorzec jak `InteresanciDto`: `sectionLabel`, `labels` (etykiety PL pól), `values`.

### Lista — `RejestrPrzypisaniaDto`

`app/Source/V1/DTO/RejestrPrzypisaniaDto.php` — `data` na listach to **obiekt sekcji**, nie tablica wpisów.

| Klucz JSON (sekcja) | Zawartość |
|---------------------|-----------|
| `sectionLabel` | domyślnie `'Rejestry'` |
| `labels` | mapa kluczy EN → etykieta PL (`defaultLabels()`) |
| `values` | `RejestrPrzypisanieWartosciDto[]` |

### Show — `RejestrPrzypisanieDto`

`app/Source/V1/DTO/RejestrPrzypisanieDto.php` — ten sam kształt sekcji; `values` to pojedynczy obiekt.

Pola w `values` (klucze EN):

| Klucz JSON | Źródło SQL / serwis |
|------------|---------------------|
| `registry_assignment_id` | `rz.id` |
| `registry_assignment_uid` | `rz.rejestr_zawartosc_uid` |
| `document_id` | `rz.rejestr_zawartosc_uid` (alias w SELECT) |
| `registry_assignment_number` | `rz.rejestr_zawartosc_numeracja` |
| `registry_assignment_type` | `rz.rejestr_zawartosc_typ` |
| `registry_uid` | `rz.rejestr_uid` |
| `registry_type` | `r.rejestr_typ` |
| `registry_description` | `r.rejestr_opis` |
| `created_at` | `rz.rejestr_zawartosc_createdate` |
| `lead_case_uid` | serwis: `getLeadCaseUid(document_id)` |
| `process_name` | serwis: `getProcessNameByAssignmentType(typ, document_id)` |

Request: `KryteriaPrzypisanRejestrow` (`app/Source/V1/DTO/Request/KryteriaPrzypisanRejestrow.php`).

## Model identyfikatorów

`eurzad_rejestr_zawartosc.rejestr_zawartosc_uid` wskazuje **bezpośrednio** na dokument (`sprawa_uid` lub `pismo_uid`), nigdy na `teczka_uid`.

| `rejestr_zawartosc_typ` | `rejestr_zawartosc_uid` = |
|-------------------------|---------------------------|
| `rejestr` (domyślny) | `eurzad_sprawa.sprawa_uid` |
| `rejestr_pismo` | `eurzad_pismo.pismo_uid` |
| inne | zwykle `eurzad_sprawa.sprawa_uid` |

W odpowiedzi API: `document_id` = `registry_assignment_uid` = `rejestr_zawartosc_uid`.

## Rozwiązywanie `documentId` (serwis)

Constraint w routes: `(\d+|[a-f0-9]{13})` — jak `/documents/{documentId}/attachments`.

1. **Hex 13** — lookup w `eurzad_sprawa` / `eurzad_pismo` (`resolveDocumentUid`)
2. **Numeric** — najpierw lookup po wartości w kolumnie UID, potem `instance_id` w `eurzad_pismo`, potem `"instanceId"` w `eurzad_obieg`
3. **`caseUid`** (endpoint lub filtr globalny) → `CaseQuery::getMainDocumentUidByCaseUid()` → `sprawa_uid`

Query dostaje gotową tablicę `documentIds[]` — nie zna `caseUid`.

## `resolveAssignmentDocumentIds`

1. Start: `[$documentUid]`
2. `eurzad_rejestr_form_zawartosc` WHERE `wartosc = :documentUid` → dodaj `rejestr_zawartosc_uid`
3. Gdy `withCopies` (domyślnie `true`): kopie z `eurzad_document_copies` (lead case + dzieci) → wpisy istniejące w `eurzad_rejestr_zawartosc`
4. `array_unique`

Wykluczenie RPW: brak lookupu w `eurzad_rejestr_pism_wych`; dodatkowo `r.rejestr_typ <> 'rejestr_pism_wychodzacych'`.

Filtr listy: `rz.rejestr_zawartosc_uid IN (...)`.

## Główne zapytanie listy

```sql
SELECT DISTINCT
    rz.id AS registry_assignment_id,
    rz.rejestr_zawartosc_uid AS registry_assignment_uid,
    rz.rejestr_zawartosc_uid AS document_id,
    rz.rejestr_zawartosc_numeracja AS registry_assignment_number,
    rz.rejestr_zawartosc_typ AS registry_assignment_type,
    rz.rejestr_uid AS registry_uid,
    rz.rejestr_zawartosc_createdate AS created_at,
    r.rejestr_typ AS registry_type,
    r.rejestr_opis AS registry_description
FROM eurzad_rejestr_zawartosc rz
INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
WHERE r.rejestr_typ <> 'rejestr_pism_wychodzacych'
  AND rz.rejestr_zawartosc_uid IN (...)
ORDER BY rz.rejestr_zawartosc_createdate DESC, rz.id DESC
```

Opcjonalne filtry (`filtry`):

| Filtr | SQL / zachowanie |
|-------|------------------|
| `registry_uid` | `rz.rejestr_uid = ?` |
| `registry_types` / `typy_rejestrow` | `r.rejestr_typ IN (...)` |
| `with_copies` / `withCopies` | wpływa na `resolveAssignmentDocumentIds`, nie na JOIN |
| `documentId` / `document_id` | tylko globalna lista → resolve → `documentIds[]` |
| `caseUid` / `case_uid` | tylko globalna lista → `sprawa_uid` → `documentIds[]` |
| `created_from` / `created_to` | `rz.rejestr_zawartosc_createdate` |
| `year` / `rok` | `EXTRACT(YEAR FROM rz.rejestr_zawartosc_createdate)` |
| `number_from` / `number_to` | `rz.rejestr_zawartosc_numeracja` |

Globalna lista: wymaga `konfiguracja.madkomWorkstationIds`; filtr `r."galaxia_activities_activityId" IN (...)` z profilu `RegistryBrowse`. Paginacja: `page`, `limit`; meta: `page`, `limit`, `count`, `has_prev`, `has_next`.

Szczegóły (`/registry-assignments/{registryAssignmentId}`): `WHERE rz.id = ? AND r.rejestr_typ <> 'rejestr_pism_wychodzacych'` — ten sam DTO co lista (bez osobnego read modelu szczegółów).

Pusta `documentIds` na liście kontekstowej → brak zapytania, `data: []`, `meta.count = 0`.

## `process_name` i `lead_case_uid`

| Pole | Źródło |
|------|--------|
| `lead_case_uid` | rekurencja po `eurzad_document_copies.lead_case_uid` |
| `process_name` | `rejestr_pismo` → `galaxia_processes` przez `eurzad_pismo.instance_id`; inaczej → `eurzad_sprawa` + `galaxia_processes` |

## Typy rejestrów (`/registries/types`)

```sql
SELECT DISTINCT r.rejestr_typ
FROM eurzad_rejestr r
WHERE r.rejestr_typ <> 'rejestr_pism_wychodzacych'
ORDER BY r.rejestr_typ
```

Typy dynamiczne z bazy — bez hardcodowania w kodzie.

## Tabele

| Tabela | Rola |
|--------|------|
| `eurzad_rejestr_zawartosc` | wpisy w rejestrze |
| `eurzad_rejestr` | definicja rejestru |
| `eurzad_rejestr_form_zawartosc` | powiązanie przez pole formularza |
| `eurzad_document_copies` | kopie dokumentów / egzemplarze |
| `eurzad_sprawa`, `eurzad_pismo` | `process_name`, resolve UID |
| `galaxia_processes`, `galaxia_instances` | nazwy procesów |
