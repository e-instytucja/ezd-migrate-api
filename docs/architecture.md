# Architektura

## Przepływ requestu

```mermaid
flowchart LR
  Client --> Nginx
  Nginx --> Laravel["public/index.php"]
  Laravel --> Routes["routes/api.php"]
  Routes --> Controller["Controllers/Api/V1"]
  Controller --> Service["Source/V1/Services"]
  Service --> Query["Source/V1/Queries"]
  Query --> PG["PostgreSQL"]
  Service --> FS["FILES_URL"]
  Controller --> Renderer["Http/Response/ApiResponseRenderer"]
  Renderer --> Client
```

## Warstwy

### 1. Routing (`routes/api.php`)

- Prefiks Laravel `/api` + grupa `v1` → `/api/v1/...`
- Większość endpointów: `Route::match(['get', 'post'], ...)` — potwierdzone w routes
- Health: `GET /health` w `routes/web.php`

Identyfikatory w routes:

| Parametr | Regex | Uwagi |
|----------|-------|-------|
| `{caseUid}` | `[a-f0-9]{13}` | |
| `{documentId}` | `(\d+\|[a-f0-9]{13})` | @TODO: docelowo numeric `instanceId` |
| `{attachmentUid}` | `[a-f0-9]{13}` | |
| `{fileId}` | `[a-zA-Z0-9._-]+` | identyfikator ePUAP (`epuap_download_file.file_id`) |

### 2. Kontrolery (`app/Http/Controllers/Api/V1/`)

| Kontroler | Serwis | Uwagi |
|-----------|--------|-------|
| `CasesController` | `CaseService` | `dntas = 0` |
| `DntasController` | `CaseService` | `dntas = 1`, `AbstractCaseController` |
| `DocumentsController` | `DocumentService` | |
| `AttachmentController` | `AttachmentService` | binary stream (`show`, `showEpuap`) |
| `WorkstationsController` | `WorkstationService` | tylko GET |

### 3. Request DTO (`app/Source/V1/DTO/Request/`)

Kontrolery przekazują `$request->all()` do fabryk `fromPayload` / `fromArray`.

| DTO | Rola |
|-----|------|
| `KryteriaWyszukiwaniaSpraw` | `filtry`, `konfiguracja`, `sort`, paginacja, `dntas` |
| `KryteriaWyszukiwaniaDokumentow` | analogicznie dla dokumentów |
| `TypFiltrSpraw` | extends `TypFiltrDokument` + pola specyficzne dla spraw |
| `TypFiltrDokument` | mapowanie snake_case z JSON |
| `ApiKonfiguracja` | `madkomWorkstationIds`; `einstytucjaUserId` — **parsowane, niewykorzystywane** w Services/Queries |
| `Paginacja` | default limit 10, max 200 |
| `SortowanieSpraw` / `SortowanieDokumentow` | whitelist pól → ORDER BY SQL |

Walidacja ręczna w DTO. `SearchRequest` istnieje, **nie jest podpięty** do tras (Q-17).

### 4. Serwisy

Orkiestracja: Queries → mapowanie DTO → wywołania innych serwisów.

Zależności `CaseService` (konstruktor): `CaseListQuery`, `CaseQuery`, `FormQuery`, `WorkstationQuery`, `UugQuery`, `DocumentService`, `FormService`, `AttachmentService`, `SupliantService`, `CaseHistoryService`.

### 5. Queries

Szczegóły: [queries/README.md](queries/README.md).

### 6. Response (`app/Http/Response/`)

Envelope z `AbstractFormatter::normalize()` — pola faktycznie zwracane:

```json
{
  "success": true,
  "status_code": 200,
  "message": "...",
  "meta": { "page": 1, "limit": 10, "count": 42 },
  "data": []
}
```

- `message` — tylko gdy nie-null
- `error` — **tylko przy błędzie** (`success: false`), wartość = `errorCode` z `ApiResponse`
- `meta` — tylko gdy niepusta

Format: `?format=json|xml|html` (domyślnie JSON). Obsługiwane formaty: `FormatterFactory` — json, xml, html.

### 7. Wyjątki

`ApiExceptionRenderer`: `RuntimeException` → 404, `Exception` → 422, `NotFoundHttpException` → 404, inne → 500.

## Middleware

| Middleware | Rola |
|------------|------|
| `ApiAccessLogMiddleware` | logowanie dostępu API (`API_ACCESS`) |

Brak auth middleware w kodzie.

### Diagnostyka czasów SQL (opcjonalna)

Sterowana flagami `.env` — **domyślnie wyłączona** (`LOG_SQL_QUERIES=false`).

| Komponent | Plik | Rola |
|-----------|------|------|
| `QueryTimingCollector` | `app/Shared/QueryTimingCollector.php` | singleton na request — suma czasu i liczba zapytań DB |
| `AppServiceProvider` | `app/Providers/AppServiceProvider.php` | `DB::listen` → kolektor + log `SQL.slow` |
| `ApiAccessLogMiddleware` | `app/Http/Middleware/ApiAccessLogMiddleware.php` | pola `query_count`, `db_total_ms`, `php_overhead_ms` w `API_ACCESS` |
| `CaseService` / `DocumentService` | `getList()` | logi faz `CASE_LIST.phase` / `DOCUMENT_LIST.phase` (`count`, `list`, `hydrate`) |

Klucze logów (gdy włączone):

| Klucz | Poziom | Zawartość |
|-------|--------|-----------|
| `API_ACCESS` | info | jak dotychczas + opcjonalnie statystyki DB per request |
| `SQL.slow` | notice | pojedyncze zapytanie ≥ `LOG_SQL_SLOW_MS` (sql, bindings, time_ms) |
| `CASE_LIST.phase` / `DOCUMENT_LIST.phase` | info | czas wall-clock fazy serwisu (ms) |
| `CASE_LIST.ok` / `DOCUMENT_LIST.ok` | info | pole `phases: {count_ms, list_ms, hydrate_ms}` |

Zmienne środowiskowe:

| Zmienna | Domyślnie | Opis |
|---------|-----------|------|
| `LOG_SQL_QUERIES` | `false` | włącza listener, statystyki w `API_ACCESS`, fazy w serwisach |
| `LOG_SQL_SLOW_MS` | `100` | próg logu `SQL.slow` (ms) |
| `LOG_SQL_QUERIES_DETAIL` | `false` | pełna lista zapytań w `API_ACCESS` (duże logi) |

Interpretacja: `db_total_ms` ≈ czas w PostgreSQL; `php_overhead_ms` = czas requestu minus suma czasów zapytań (PHP, serializacja, Xdebug itd.).

## Console / scheduler

`routes/console.php` — `attachments:test-main-document-attachments-exists` (schedule: codziennie 02:00).

## Konfiguracja

| Plik | Ustawienia |
|------|------------|
| `config/app.php` | locale `pl`, timezone `Europe/Warsaw`, `log_sql_*` (diagnostyka SQL) |
| `config/database.php` | `pgsql` |
| `.env.example` | `DB_*`, `FILES_URL`, `CACHE_STORE=array`, `LOG_SQL_*` |
| `docker-compose.yml` | `FILES` mount `:ro`, postgres:16, port 8080 |

## Poza aplikacją

Import dumpa: `scripts/import-db.sh`, `scripts/import-to-pg_import.sh`.

## Otwarte kwestie

Pełna lista: [open-questions.md](open-questions.md).
