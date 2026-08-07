# sidas — API integracyjne SIDAS EZD 


---

## Pierwsze uruchomienie

| Środowisko | Instrukcja |
|------------|------------|
| Dev (Docker) | Opcja A poniżej |
| **Produkcja / nowy serwer** | [docs/install-production.md](docs/install-production.md) |

### Opcja A — Docker

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Aplikacja HTTP: [http://localhost:8080](http://localhost:8080)

Jeśli używasz debugowania Xdebug w układzie Windows -> WSL -> Docker, ustaw w `.env`:

```dotenv
XDEBUG_CLIENT_HOST=192.168.0.6
XDEBUG_CLIENT_PORT=9003
XDEBUG_IDEKEY=PHPSTORM
```

Po zmianie wykonaj:

```bash
docker compose up -d --build app
```



---

### Opcja B — Host (bez Dockera)

```bash
git clone https://github.com/e-instytucja/ezd-migrate-api.git .

mkdir -p storage/framework/{views,cache,sessions}
mkdir -p storage/logs
mkdir -p storage/files/{attachments}
mkdir -p bootstrap/cache
mkdir -p resources/views
chmod -R 775 storage bootstrap/cache

composer install
cp .env.example .env
vim .env
```

W pliku `.env` ustaw połączenie z bazą danych:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5433
DB_DATABASE=sidas_ch
DB_USERNAME=ezd
DB_PASSWORD=<nasze_standardowe>
```

oraz link do repozytorium z załącznikami:
```dotenv
FILES_URL="/var/www/files"
```

wykonaj polecenia:
```bash
php artisan key:generate
php artisan migrate

php artisan optimize:clear
```

---

## API V1 – endpointy

Bazowy prefiks: `/api/v1/`

```
GET /api/v1/cases
GET /api/v1/cases/{id}
GET /api/v1/documents
GET /api/v1/documents/{id}
GET /api/v1/attachment/{token}
```

---

## Testy commandline

W projekcie jest dostępna komenda testowa do weryfikacji załączników pism wiodących:

```bash
php artisan attachments:test-main-document-attachments-exists
```

Opcjonalne parametry:

```bash
php artisan attachments:test-main-document-attachments-exists --limit=1000 --offset=0
```

Komenda uruchamia `testMainDocumentAttachmentsExists()` i raportuje:
- liczbę sprawdzonych rekordów,
- brakujące wpisy w `eurzad_zalacznik`,
- brakujące pliki na dysku,
- rekordy niepoprawne.

Przed użyciem produkcyjnym uzupełnij w `CaseService` metody:
- `getMainDocumentAttachmentsAuditCandidates()`,
- `existsInEurzadZalacznik()`.

---

## Architektura

```
app/
├── Http/
│   ├── Controllers/Api/V1/       ← CasesController, DocumentsController, AttachmentController
│   ├── Requests/Api/V1/          ← walidacja wejścia (SearchRequest)
│   └── Response/                 ← ApiResponseRenderer, FormatterFactory, formattery (JSON/XML/HTML)
├── Shared/
│   ├── Contracts/
│   │   └── SourceAdapterInterface.php
│   └── Functions.php
└── Source/V1/
    ├── DTO/                      ← obiekty transferowe (Typ*, Filtr*)
    ├── Enum/                     ← wyliczenia domenowe
    ├── Queries/                  ← odpytywanie bazy (CaseQuery, DocumentQuery, AttachmentQuery, …)
    └── Services/                 ← logika biznesowa (Case, Document, Attachment, Structure, …)
```

##  Logi błędów 

Logi błędów aplikacji Laravel (np. wyjątki 500) zapisywane są w:

```text
storage/logs/laravel.log
```

Przy domyślnej konfiguracji:
- `LOG_CHANNEL=stack`
- `LOG_STACK=single` (domyślnie z `config/logging.php`)

oznacza to zapis do kanału `single`, czyli właśnie do `storage/logs/laravel.log`.

## Przyszłe wersje API (V2/V3)

Aby dodać nową wersję:
1. Skopiuj `app/Source/V1/` → `app/Source/V2/`
2. Skopiuj `app/Http/Controllers/Api/V1/` → `app/Http/Controllers/Api/V2/`
3. Dodaj grupę `Route::prefix('v2')` w `routes/api.php`
4. V1 pozostaje bez zmian
