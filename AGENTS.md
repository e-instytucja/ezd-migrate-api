# AGENTS.md — przewodnik dla agenta AI

API integracyjne EZD. Dokumentacja techniczna w `docs/`. Pytania otwarte: [docs/open-questions.md](docs/open-questions.md).

**Read-only** = konwencja projektu (Queries to SELECT). Nie zakładaj blokady zapisów w runtime.

## Kolejność czytania

1. [docs/project-overview.md](docs/project-overview.md)
2. [docs/architecture.md](docs/architecture.md)
3. [docs/database.md](docs/database.md)
4. [docs/queries/](docs/queries/)
5. [docs/open-questions.md](docs/open-questions.md)
6. `.cursor/rules/agent-guardrails.mdc`

## Szybkie fakty (potwierdzone w kodzie)

| Temat | Wartość |
|-------|---------|
| Framework | Laravel 11, PHP ^8.2 |
| Baza | PostgreSQL, schemat legacy (brak migracji w repo) |
| API prefix | `/api/v1/` |
| `{caseUid}` | hex 13 znaków `[a-f0-9]{13}` |
| `{documentId}` | numeric `\d+` **lub** hex 13 znaków (@TODO → tylko `instanceId`) |
| Listy spraw/dokumentów | wymagane `konfiguracja.madkomWorkstationIds` (niepusta tablica) |
| Cases vs DNTAS | ta sama logika serwisu, `dntas` 0 vs 1 |
| `einstytucjaUserId` | pole w `ApiKonfiguracja`, **niewykorzystywane** w Services/Queries |

## Gdzie szukać kodu

| Zadanie | Ścieżka |
|---------|---------|
| Endpoint | `routes/api.php` → `app/Http/Controllers/Api/V1/` |
| Logika biznesowa | `app/Source/V1/Services/` |
| SQL | `app/Source/V1/Queries/` |
| Request/response | `app/Source/V1/DTO/`, `app/Http/Response/` |

## Czego nie robić

Pełna lista: `.cursor/rules/agent-guardrails.mdc`
