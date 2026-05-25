# sidas — API integracyjne SIDAS EZD 


---

## Pierwsze uruchomienie

### Opcja A — Docker

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Aplikacja HTTP: [http://localhost:8080](http://localhost:8080)

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


## Przyszłe wersje API (V2/V3)

Aby dodać nową wersję:
1. Skopiuj `app/Source/V1/` → `app/Source/V2/`
2. Skopiuj `app/Http/Controllers/Api/V1/` → `app/Http/Controllers/Api/V2/`
3. Dodaj grupę `Route::prefix('v2')` w `routes/api.php`
4. V1 pozostaje bez zmian
