# sidas — API integracyjne EZD (`site/`)

Ten katalog to **korzeń projektu** (Docker, Laravel, skrypty). Komendy uruchamiaj stąd (albo ustaw workspace w edytorze na `site`).

**Tylko na tym komputerze (nie commituj):** utwórz `local/` w tym katalogu — jest w `.gitignore` (duże dumpy `.sql`, kopie `.env`).

---

## Pierwsze uruchomienie

Będąc w **`…/sidas/site`** (ten katalog):

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Aplikacja HTTP: [http://localhost:8080](http://localhost:8080)

---

## API V1 – endpointy

Bazowy prefiks: `/api/v1/`

### Health check

```
GET /health
```

### Wyszukiwanie

```
GET /api/v1/search?type=<typ>&q=<fraza>&limit=20&offset=0
```

| Parametr | Wymagany | Opis                                               |
|----------|----------|----------------------------------------------------|
| `type`   | tak      | `case` \| `document` \| `registry` \| `shipment`  |
| `q`      | nie      | Fraza tekstowa (ILIKE)                             |
| `limit`  | nie      | Liczba wyników (1–100, domyślnie 20)               |
| `offset` | nie      | Przesunięcie (domyślnie 0)                         |

**Przykłady:**

```bash
curl "http://localhost:8080/api/v1/search?type=case&q=BM.7021&limit=5"
curl "http://localhost:8080/api/v1/search?type=document&q=umowa"
curl "http://localhost:8080/api/v1/search?type=shipment&limit=10&offset=20"
```

### Pobieranie rekordu po ID

```
GET /api/v1/cases/{id}
GET /api/v1/documents/{id}
GET /api/v1/registries/{id}
GET /api/v1/shipments/{id}
```

**Przykłady:**

```bash
curl "http://localhost:8080/api/v1/cases/42"
curl "http://localhost:8080/api/v1/documents/17"
```

### Format odpowiedzi

**Pojedynczy rekord:**
```json
{
    "data": { … }
}
```

**Lista (search):**
```json
{
    "data": [ … ],
    "meta": {
        "type": "case",
        "total": 137,
        "limit": 20,
        "offset": 0
    }
}
```

**Błąd 404:**
```json
{
    "error": "not_found",
    "message": "Case #42 not found."
}
```

**Błąd walidacji 422 (/search):**
```json
{
    "message": "…",
    "errors": { "type": ["…"] }
}
```

---

## Architektura

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/          ← cienkie controllery (delegują do adaptera)
│   └── Requests/
│       └── Api/V1/          ← walidacja wejścia
├── Shared/
│   └── Contracts/
│       └── SourceAdapterInterface.php   ← wspólny kontrakt V1/V2/…
└── Source/
    └── V1/
        ├── Queries/          ← odpytywanie bazy importu (connection: import)
        ├── Mappers/          ← mapowanie wierszy DB → reprezentacja API
        ├── CaseAdapter.php
        ├── DocumentAdapter.php
        ├── RegistryAdapter.php
        └── ShipmentAdapter.php
```

**Połączenia DB:**
- `pgsql` — baza Laravel (migracje, cache); serwis `db` w Docker Compose
- `import` — baza starego EZD (tylko odczyt); `host.docker.internal:5433`

---

## Baza danych

### Baza aplikacji Laravel (serwis `db`)

Z poziomu Laravela (sieć Dockera): host `db`, port `5432` — jak w `.env.example`.

Z hosta: domyślnie port Postgresa **nie jest** wystawiany. Dostęp: `docker compose exec db psql …` albo dopisz w `docker-compose.yml` sekcję `ports` z wolnym portem hosta (np. `5434:5432`).

```bash
docker compose exec db psql -U laravel -d laravel_api -c '\dt'
```

### Baza importu (`pg_import` na hoście)

Kontener **`pg_import`** działa na hoście pod portem **5433**. Aplikacja łączy się przez `host.docker.internal:5433`.

**Weryfikacja połączenia:**

```bash
docker compose up -d
docker compose exec app php bin/eurzad-sprawa-sample.php
```

Jeśli pojawi się `password authentication failed`, przyczyną może być stary wolumen:

```bash
docker exec pg_import psql -U postgres -d postgres -c "ALTER USER postgres WITH PASSWORD 'postgres';"
```

### Import dumpa

```bash
bash scripts/import-db.sh
```

---

## Dostosowanie schematów bazy importu

Nazwy tabel i kolumn w `app/Source/V1/Queries/` są **zgadywane** na podstawie konwencji `eurzad_*`.  
Po podłączeniu do rzeczywistej bazy importu zweryfikuj je i zaktualizuj:

| Plik | Tabela (stała `TABLE`) | Klucz główny (`PK_COL`) |
|------|------------------------|--------------------------|
| `CaseQuery.php`     | `eurzad_sprawa`   | `sprawa_id`   |
| `DocumentQuery.php` | `eurzad_dokument` | `dokument_id` |
| `RegistryQuery.php` | `eurzad_rejestr`  | `rejestr_id`  |
| `ShipmentQuery.php` | `eurzad_wysylka`  | `wysylka_id`  |

Następnie uzupełnij mappery w `app/Source/V1/Mappers/` o jawne mapowanie pól.

---

## Przyszłe wersje API (V2/V3)

Aby dodać nową wersję:
1. Skopiuj `app/Source/V1/` → `app/Source/V2/`
2. Skopiuj `app/Http/Controllers/Api/V1/` → `app/Http/Controllers/Api/V2/`
3. Dodaj grupę `Route::prefix('v2')` w `routes/api.php`
4. V1 pozostaje bez zmian
