# Przegląd projektu

API integracyjne do odczytu danych ze starego systemu EZD (SIDAS).

## Cel

Udostępnienie REST API (JSON/XML/HTML) dla systemów zewnętrznych: listy spraw, dokumentów, załączników, stanowisk pracy, historii obiegu (historia przez endpointy szczegółów, nie osobny zasób).

Opis w `composer.json`: *„API integracyjne EZD – odczyt danych starego systemu"*.

## Stack techniczny

| Warstwa | Technologia | Źródło |
|---------|-------------|--------|
| Język | PHP ^8.2 | `composer.json` |
| Framework | Laravel 11 | `composer.json` |
| Baza | PostgreSQL (`pgsql`) | `config/database.php` |
| Cache | Array driver (domyślnie) | `.env.example` |
| Konteneryzacja | Docker Compose (nginx:8080, php-fpm, postgres:16) | `docker-compose.yml` |
| Załączniki | Pliki na dysku — env `FILES_URL` | `AttachmentService` |

## Read-only — konwencja, nie gwarancja

- **ZAŁOŻENIE projektu:** API służy do odczytu danych EZD; warstwa `Queries` zawiera wyłącznie SELECT-y (brak INSERT/UPDATE/DELETE w `app/Source/V1/Queries/`).
- Kod **nie implementuje** globalnej blokady zapisów do PostgreSQL ani zapisu plików.
- `FILES_URL` jest read-only **tylko** gdy mount Docker ma flagę `:ro` (`docker-compose.yml`: `../../chojnice:/var/www/files:ro`). Na hoście bez `:ro` — brak gwarancji.

## Ograniczenia repozytorium

- Brak migracji w repo — schemat DB z dumpa EZD (`scripts/import-*.sh`)
- Brak Eloquent — dostęp przez `Queries` + raw SQL / Query Builder
- Brak testów automatycznych — katalog `tests/` nie istnieje; jest komenda audytowa załączników
- Brak middleware autoryzacji — tylko `ApiAccessLogMiddleware` (DO WYJAŚNIENIA: Q-18)

## Struktura repozytorium

```
app/
├── Http/Controllers/Api/V1/
├── Http/Response/
├── Source/V1/
│   ├── DTO/
│   ├── Enum/          (25 plików — potwierdzone)
│   ├── Queries/
│   └── Services/
routes/api.php
scripts/
docs/
```

## Wersjonowanie API

Aktywna wersja: **V1** (`/api/v1/`). Plan V2/V3: kopia katalogów (opis w README).

## Uruchomienie

Setup: [README.md](../README.md). Import DB: `scripts/` (poza Laravel).

**DO WYJAŚNIENIA (Q-23):** README wspomina `php artisan migrate`, plików migracji w repo nie ma.

## Powiązana dokumentacja

- [architecture.md](architecture.md)
- [database.md](database.md)
- [queries/](queries/)
- [open-questions.md](open-questions.md)
- [AGENTS.md](../AGENTS.md)
