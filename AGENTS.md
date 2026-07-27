# AGENTS.md — przewodnik dla agenta AI

API integracyjne EZD. Dokumentacja techniczna w `docs/`. Pytania otwarte: [docs/open-questions.md](docs/open-questions.md).

**Read-only** = konwencja projektu (Queries to SELECT). Nie zakładaj blokady zapisów w runtime. **Zakaz** `INSERT`/`UPDATE`/`DELETE`/`TRUNCATE` na tabelach danych EZD. **Wyjątek:** DDL widoków zmaterializowanych (`api_case_list`, `api_document_list`) — wyłącznie `php artisan materialized-views:refresh` / `cases:refresh-list-mv` / `documents:refresh-list-mv`; endpoint `/api/v1/system/materialized-views` nie wykonuje `CREATE`/`REFRESH`/`DROP`. Repozytorium **ezd3** nie służy do modyfikacji danych przez site.

## Kolejność czytania

1. [docs/project-overview.md](docs/project-overview.md)
2. [docs/architecture.md](docs/architecture.md)
3. [docs/database.md](docs/database.md)
4. [docs/queries/](docs/queries/)
5. [docs/open-questions.md](docs/open-questions.md)
6. [tests/README.md](tests/README.md) — uruchamianie testów i raport HTML
7. `.cursor/rules/agent-guardrails.mdc`

## Szybkie fakty (potwierdzone w kodzie)

| Temat | Wartość |
|-------|---------|
| Framework | Laravel 11, PHP ^8.2 |
| Baza | PostgreSQL, schemat legacy (brak migracji w repo) |
| API prefix | `/api/v1/` |
| `{caseUid}` | hex 13 znaków `[a-f0-9]{13}` |
| `{documentId}` | numeric `\d+` **lub** hex 13 znaków (@TODO → tylko `instanceId`) |
| Listy spraw/dokumentów | wymagane `konfiguracja.madkomWorkstationIds` (niepusta tablica) |
| `USE_MATERIALIZED_VIEWS` | `false` (domyślnie) \| `true` — wszystkie listy API z MV; refresh: `php artisan materialized-views:refresh`; status: `GET\|POST /api/v1/system/materialized-views`; szczegóły [case-queries.md](docs/queries/case-queries.md), [document-queries.md](docs/queries/document-queries.md) |
| `MADKOM_API_TOKEN` | wymagany shared secret; nagłówek `madkom-api-token`; pusty → 503 |
| Cases vs DNTAS | ta sama logika serwisu, `dntas` 0 vs 1 |
| `einstytucjaUserId` | pole w `ApiKonfiguracja`, **niewykorzystywane** w Services/Queries |
| Testy API | `tests/Feature/Api/` — PHPUnit Feature, **tylko odczyt HTTP**; uruchom: `composer test` / `composer test:report`; raport: `/test-reports/` ([tests/README.md](tests/README.md), [api-tests.mdc](.cursor/rules/api-tests.mdc)) |

## Gdzie szukać kodu

| Zadanie | Ścieżka |
|---------|---------|
| Endpoint | `routes/api.php` → `app/Http/Controllers/Api/V1/` |
| Logika biznesowa | `app/Source/V1/Services/` |
| SQL | `app/Source/V1/Queries/` |
| Request/response | `app/Source/V1/DTO/`, `app/Http/Response/` |
| Testy API | `tests/Feature/Api/`, `tests/Fixtures/`, [tests/README.md](tests/README.md) |

## Czego nie robić

Pełna lista: `.cursor/rules/agent-guardrails.mdc`
