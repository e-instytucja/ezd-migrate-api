# Registry assignment RPW queries

Katalog: `app/Source/V1/Queries/Registry/RegistryAssignmentRpwQuery.php`  
Serwis: `app/Source/V1/Services/Registry/RegistryAssignmentRpwService.php`  
Kontroler: `app/Http/Controllers/Api/V1/RegistryAssignmentsController.php`

Osobny read model od zwykłych rejestrów. Bez scope stanowisk na endpointzie kontekstowym dokumentu.

Zwykłe rejestry: [registry-assignment-queries.md](registry-assignment-queries.md).

## Endpointy

| Endpoint | Odpowiedź | Scope wpisów |
|----------|-----------|----------------|
| `GET\|POST /api/v1/documents/{documentId}/registry-assignments-rpw` | lista | brak |
| `GET\|POST /api/v1/registry-assignments-rpw` | lista | `rz.workstation` lub brak filtra (`pisma_wychodzace`) |
| `GET\|POST /api/v1/registry-assignments-rpw/{registryAssignmentId}` | szczegóły | brak (klucz: `rz.id`, tylko `\d+`) |

Scope globalny: [workstation-scope-queries.md](workstation-scope-queries.md) — profil `RpwEntryList`.

Brak endpointów `/cases/.../registry-assignments-rpw` i `/dntas/...` — filtr `case_uid` tylko na globalnej liście RPW.

## DTO odpowiedzi

### Lista — `RejestrPrzypisanieRpwDto`

`app/Source/V1/DTO/RejestrPrzypisanieRpwDto.php` — klucze JSON po angielsku (jak zwykłe przypisania).

| Klucz JSON | Źródło |
|------------|--------|
| `registry_assignment_id` | `rz.id` |
| `registry_assignment_uid` | `rz.rejestr_zawartosc_uid` (UID **przesyłki**) |
| `document_id` | `rpw.pismo_uid` |
| `parent_shipment_uid` | `rz.rejestr_zawartosc_parent_uid` |
| `registry_assignment_number` | `rz.rejestr_zawartosc_numeracja` |
| `registry_assignment_type` | `rz.rejestr_zawartosc_typ` |
| `registry_uid`, `registry_type`, `registry_description`, `created_at` | jak zwykłe rejestry |
| `process_name` | serwis: `getProcessNameForPismoUid(document_id)` |

### Szczegóły — `RejestrPrzypisanieRpwSzczegolyDto`

`app/Source/V1/DTO/RejestrPrzypisanieRpwSzczegolyDto.php` — **polskie klucze JSON** + zagnieżdżenia:

| Klucz JSON | Typ |
|------------|-----|
| `id_przypisania_rejestru`, `uid_przypisania_rejestru`, … | pola bazowe (mapowane z listy) |
| `wysylka` | `RejestrRpwWysylkaDto` |
| `adresat` | `InteresantDto` |
| `historia_obiegu` | `HistoriaObieguDto[]` |

Zagnieżdżone DTO RPW: `RejestrRpwWysylkaDto`, `RejestrRpwFormaDoreczeniaDto`, `RejestrRpwPrzesylkaElektronicznaDto`.

Request: `KryteriaPrzypisanRejestrowRpw` (`app/Source/V1/DTO/Request/KryteriaPrzypisanRejestrowRpw.php`).

## Model identyfikatorów RPW

Join: `eurzad_rejestr_pism_wych.rejestr_zawartosc_id = eurzad_rejestr_zawartosc.id`

Stały warunek listy: `r.rejestr_typ = 'rejestr_pism_wychodzacych'`.

`rz.rejestr_zawartosc_typ` zwykle `pismo_wychodzace` — **informacyjnie w odpowiedzi**, bez wymuszania w WHERE (w przeciwieństwie do wczesnego planu EZD3).

Filtr kontekstowy dokumentu: `rpw.pismo_uid = :documentUid` (po `resolveDocumentUid`).

## Identyfikator `{documentId}`

Constraint w routes: `(\d+|[a-f0-9]{13})`.

`resolveDocumentUid` (współdzielone z zwykłymi rejestrami):

1. Hex 13 — lookup w `eurzad_sprawa` / `eurzad_pismo`
2. Numeric — lookup po UID, potem `eurzad_pismo.instance_id` / `eurzad_obieg."instanceId"`

Wynik porównywany z `rpw.pismo_uid`. Identyfikacja jako `sprawa_uid` → lista pusta.

## Filtr `case_uid` (globalna lista)

Serwis mapuje `case_uid` → `pismo_uid` przez `eurzad_teczka` + `eurzad_teczka_zawartosc` + `eurzad_pismo` (najwyższy `instance_id` w teczce). Brak pisma → pusta lista.

## Zapytanie listy

```sql
SELECT DISTINCT
    rz.id AS registry_assignment_id,
    rz.rejestr_zawartosc_uid AS registry_assignment_uid,
    rpw.pismo_uid AS document_id,
    rz.rejestr_zawartosc_numeracja AS registry_assignment_number,
    rz.rejestr_zawartosc_typ AS registry_assignment_type,
    rz.rejestr_uid AS registry_uid,
    rz.rejestr_zawartosc_createdate AS created_at,
    rz.rejestr_zawartosc_parent_uid AS parent_shipment_uid,
    r.rejestr_typ AS registry_type,
    r.rejestr_opis AS registry_description
FROM eurzad_rejestr_pism_wych rpw
INNER JOIN eurzad_rejestr_zawartosc rz ON rpw.rejestr_zawartosc_id = rz.id
INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
WHERE r.rejestr_typ = 'rejestr_pism_wychodzacych'
  AND rpw.pismo_uid = ?
ORDER BY rz.rejestr_zawartosc_createdate DESC, rz.id DESC
```

Opcjonalne filtry (`filtry`): `registry_uid`, `registry_types`, `created_from`, `created_to`, `documentId`, `case_uid`.

Globalna lista: `konfiguracja.madkomWorkstationIds`; profil `RpwEntryList`. Paginacja i meta jak `/registry-assignments`.

## Szczegóły — osobne SELECT-y

Nagłówek: `getById` (`rz` + `r` + `rpw`). Rozszerzenia wyłącznie w `getById` serwisu — bez JOIN-ów w liście.

### Rozszerzenie RPW

```sql
SELECT rpw.petent_uid, rpw.forma_doreczenia, rpw.data_wyslania, rpw.nr_nadawczy, rpw.rejestr_zawartosc_id
FROM eurzad_rejestr_pism_wych rpw
INNER JOIN eurzad_rejestr_zawartosc rz ON rpw.rejestr_zawartosc_id = rz.id
INNER JOIN eurzad_rejestr r ON r.rejestr_uid = rz.rejestr_uid
WHERE rz.id = ? AND r.rejestr_typ = 'rejestr_pism_wychodzacych'
```

Pole `rpw.referat` nie jest eksponowane w API.

### Forma doręczenia

```sql
SELECT ep.klucz, ep.nazwa FROM eurzad2_przesylka ep WHERE ep.klucz = ?
```

### Historia obiegu przesyłki

```sql
SELECT ero.rejestr_zawartosc_uid, ero.createdate, ss.opis AS status_opis,
       ero.uugid_from, ero.uugid_to, ero.added_automatically,
       (SELECT p.instance_id FROM eurzad_pismo p WHERE p.pismo_uid = ? ORDER BY p.pismo_wersja DESC LIMIT 1) AS instance_id
FROM eurzad_rejestr_obieg ero
INNER JOIN eurzad_slownik_status ss ON ero.status = ss.symbol
WHERE ero.rejestr_zawartosc_uid = ?
ORDER BY ero.rejestr_obieg_id DESC
```

Mapowanie: `HistoriaObieguDto` + `EmployeeService::getEmployeeFullNameByUugId` (jak `HistoryService`). `dokumentId` w DTO = UID przesyłki.

### Przesyłka elektroniczna (`en_rpw`)

```sql
SELECT en.rpw_shipment_id, en.status, en.data_wyslania
FROM en_rpw en
WHERE en.rpw_shipment_id = ?
ORDER BY en.id DESC LIMIT 1
```

### Adresat

`rpw.petent_uid` → `SupliantService::getSupliantById` + `mapToInteresantDto` (`role: ['Adresat']`).

## Nie zaimplementowane (plan Faza 3 — filtry listy RPW)

Filtry globalnej listy po: status obiegu, forma doręczenia, adresat, nr nadawczy — wymagają osobnych warunków SQL (poza zakresem obecnego kodu).

## Tabele

| Tabela | Rola |
|--------|------|
| `eurzad_rejestr_pism_wych` | powiązanie przesyłki RPW z wpisem rejestru i `pismo_uid` |
| `eurzad_rejestr_zawartosc` | wpis rejestru (UID przesyłki) |
| `eurzad_rejestr` | definicja rejestru RPW |
| `eurzad_pismo` | `pismo_uid`, `instance_id` |
| `eurzad_rejestr_obieg` | historia obiegu przesyłki (`rejestr_zawartosc_uid`) |
| `eurzad2_przesylka` | słownik formy doręczenia (`klucz`, `nazwa`) |
| `en_rpw` | przesyłka elektroniczna (`rpw_shipment_id` = `rejestr_zawartosc_id`) |
| `eurzad_petent_search` | adresat (`petent_uid`) |
| `eurzad_teczka`, `eurzad_teczka_zawartosc` | resolve `case_uid` → `pismo_uid` |

Kolumny tabel szczegółów: [open-questions.md](../open-questions.md) **Q-27**.
