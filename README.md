# sidas — aplikacja (`site/`)

Ten katalog to **korzeń projektu** (Docker, PHP, skrypty). Komendy uruchamiaj stąd (albo ustaw workspace w edytorze na `site`).

**Tylko na tym komputerze (nie commituj):** utwórz `local/` w tym katalogu — jest w `.gitignore` (duże dumpy `.sql`, kopie `.env`).

## Docker (development)

Będąc w **`…/sidas/site`** (ten katalog):

```bash
docker compose up -d --build
```

Po starcie kontenerów (w katalogu projektu Laravel):

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Aplikacja HTTP: [http://localhost:8080](http://localhost:8080).

### REST API (prosty endpoint)

Po `docker compose up -d`:

```bash
curl http://localhost:8080/health
curl "http://localhost:8080/api/eurzad-sprawa-sample?limit=1"
curl "http://localhost:8080/api/eurzad-teczka?teczka_znak_sprawy=BM.7021.2.9.2024"
```

### Baza danych

- Z poziomu Laravela (sieć Dockera): host `db`, port `5432` — jak w `.env.example`.
- Z hosta: domyślnie port Postgresa **nie jest** wystawiany (uniknięcie konfliktu z lokalnym PostgreSQL). Dostęp z maszyny: `docker compose exec db psql …` albo dopisz w `docker-compose.yml` sekcję `ports` z wolnym portem hosta (np. `5434:5432`).
- Serwis Compose dla Postgresa nazywa się **`db`**.

### Baza importu (`pg_import` na hoście)

Zaimportowana baza działa w kontenerze **`pg_import`**, na hoście pod portem **5433** (`5433→5432` w `docker ps`). Inny Postgres na tym komputerze (np. **`chi_db`**) często ma **5432** — to **inne** instancje; aplikacja w Dockerze łączy się z importem przez **`host.docker.internal:5433`**.

Kontener **`app`** ma `extra_hosts: host.docker.internal` i zmienne **`IMPORT_DB_*`** (patrz `docker-compose.yml` oraz `.env.example`). Domyślnie: host `host.docker.internal`, port **5433**, baza `importdb`, użytkownik `postgres` — dopasuj do `docker exec pg_import env | grep POSTGRES`.

**Weryfikacja połączenia** (najpierw `cd` do katalogu `site/`):

```bash
docker compose up -d
docker compose exec app php bin/eurzad-sprawa-sample.php
```

Jeśli pojawi się **`password authentication failed`**, a `docker exec pg_import env` pokazuje `POSTGRES_PASSWORD=postgres`, często przyczyną jest **stary wolumen danych** (hasło ustawione przy pierwszym `init` inne niż w obecnym `docker run`). Wtedy z hosta lub z innego kontenera TCP nie przejdzie, a `docker exec pg_import psql -U postgres …` dalej działa (socket / trust). Naprawa przykładowa (jednorazowo w kontenerze):

```bash
docker exec pg_import psql -U postgres -d postgres -c "ALTER USER postgres WITH PASSWORD 'postgres';"
```

(dostosuj hasło do tego, co masz w `IMPORT_DB_PASSWORD` / `.env`).

### Import dumpa (`ezd_dump_chojnice_sidas`)

W katalogu głównym projektu umieść `ezd_dump_chojnice_sidas.sql.gz` albo `ezd_dump_chojnice_sidas.sql`, potem:

```bash
bash scripts/import-db.sh
```

Skrypt podniesie stack (`docker compose up -d`), jeśli kontener `db` nie działa, odczyta `POSTGRES_USER` / `POSTGRES_DB` z kontenera i zaimportuje plik przez `psql`.

### Sprawdzenie tabel w `psql`

W kontenerze (użytkownik i baza zgodne z `docker-compose.yml`: `laravel` / `laravel_api`):

```bash
docker compose exec db psql -U laravel -d laravel_api -c '\dt'
```

Interaktywna sesja:

```bash
docker compose exec db psql -U laravel -d laravel_api
```
