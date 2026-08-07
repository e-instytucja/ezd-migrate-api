# Instalacja produkcyjna (Linux, bez Dockera)

Świeża instalacja API integracyjnego EZD na nowym serwerze.

Powiązane: [database.md](database.md)

## Wymagania

- PHP ^8.2 + php-fpm, Composer 2.x, PostgreSQL, Apache 2.4 (`mod_rewrite`, `proxy_fcgi`)
- `psql`, `gunzip`, sudo (import DB, vhost)
- dump EZD (`.sql.gz`) na serwerze
- dostęp do repo (HTTPS lub SSH)

## Zmienne

Ustaw na początku sesji:

```bash
APP_DIR=/opt/ezd-migrate-api
DB_NAME=ezd_prod
DB_USER=ezd              # DB_USERNAME w .env
DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)"
DB_HOST=127.0.0.1
DB_PORT=5432
SERVER_NAME=api.example.local
VHOST_NAME=ezd-migrate-api
```

## Model uprawnień (po kroku 5)

| Obiekt | Owner | Rola `ezd` (`DB_USERNAME`) |
|--------|-------|----------------------------|
| baza, tabele EZD w `public` | `postgres` | `SELECT` tylko; bez DML, bez `CREATE` na `public` |
| schemat `api_cache` | `ezd` | `USAGE` + `CREATE` (MV list API) |

Kolejność: import (tymczasowo owner `ezd`) → `migrate` → krok 5 (ownership → `postgres`).

`ENFORCE_EZD_DB_READ_ONLY=true` — blokada HTTP przy złych GRANTach; diagnostyka: `GET /api/v1/system/db-privileges`.

---

## 1. Clone

```bash
git clone https://github.com/e-instytucja/ezd-migrate-api.git "$APP_DIR"
cd "$APP_DIR"
```

## 2. Import bazy EZD

Rola `ezd` musi istnieć (dump: `OWNER TO ezd`). **Bez superusera.**

```bash
DUMP=/tmp/ezd_dump.sql.gz

sudo -u postgres createuser ezd 2>/dev/null || true
sudo -u postgres dropdb --if-exists "$DB_NAME"
sudo -u postgres createdb -O ezd "$DB_NAME"

# długi import — screen/tmux
gunzip -c "$DUMP" | sudo -u postgres psql -v ON_ERROR_STOP=1 -d "$DB_NAME"

sudo -u postgres psql -d "$DB_NAME" -c "SELECT count(*) FROM eurzad_teczka;"
```

Hasło i `NOSUPERUSER` (Laravel łączy się TCP):

```bash
sudo -u postgres psql -v ON_ERROR_STOP=1 <<EOF
ALTER ROLE ${DB_USER} WITH LOGIN NOSUPERUSER PASSWORD '${DB_PASS}';
EOF
```

Test TCP (zawsze `-h` i `-p`):

```bash
PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" \
  -c "SELECT current_user, current_database();"
```

Jeśli TCP nie działa — `pg_hba.conf` dla `127.0.0.1` (scram-sha-256) + `reload postgresql`.

| Rola | Użycie |
|------|--------|
| `postgres` | import, krok 5 |
| `ezd` | `DB_USERNAME` — migrate przed krokiem 5, potem SELECT + `api_cache` |

## 3. Laravel + `.env`

```bash
composer install --no-dev --optimize-autoloader
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
cp .env.example .env
php artisan key:generate
```

Przykład `.env` (produkcja):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://api.example.local

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ezd_prod
DB_USERNAME=ezd
DB_PASSWORD=...

DB_MV_SCHEMA=api_cache

MADKOM_API_TOKEN=<openssl rand -hex 32>
USE_MATERIALIZED_VIEWS=false
ENFORCE_EZD_DB_READ_ONLY=true

SESSION_DRIVER=file
CACHE_STORE=array
FILES_URL=
```

```bash
chmod 600 .env
php artisan config:cache
php artisan route:cache
php artisan db:show
```

## 4. Migracja `api_cache`

**Przed krokiem 5** — `ezd` jest jeszcze właścicielem bazy.

```bash
php artisan migrate
```

Jedyna migracja w repo: schemat `api_cache` + GRANT dla `DB_USERNAME`. Nie zastępuje importu dumpa.

## 5. Read-only EZD (tylko jako `postgres`)

`REVOKE` na ownerze nie działa — najpierw `ALTER OWNER TO postgres`.

```bash
sudo -u postgres psql -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -v ON_ERROR_STOP=1 \
  -c "ALTER DATABASE \"${DB_NAME}\" OWNER TO postgres;"

sudo -u postgres psql -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -v ON_ERROR_STOP=1 <<'EOSQL'
ALTER ROLE ezd WITH LOGIN NOSUPERUSER;

ALTER SCHEMA public OWNER TO postgres;

DO $o$
DECLARE r record;
BEGIN
    FOR r IN
        SELECT tablename
        FROM pg_tables
        WHERE schemaname = 'public'
          AND tablename ~ '^(eurzad_|galaxia_|users_|front_office_)'
    LOOP
        EXECUTE format('ALTER TABLE public.%I OWNER TO postgres', r.tablename);
    END LOOP;
END
$o$;

REVOKE ALL ON SCHEMA public FROM ezd;
GRANT USAGE ON SCHEMA public TO ezd;
REVOKE CREATE ON SCHEMA public FROM ezd;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO ezd;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO ezd;

DO $r$
DECLARE r record;
BEGIN
    FOR r IN
        SELECT tablename
        FROM pg_tables
        WHERE schemaname = 'public'
          AND tablename ~ '^(eurzad_|galaxia_|users_|front_office_)'
    LOOP
        EXECUTE format(
            'REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON TABLE public.%I FROM ezd',
            r.tablename
        );
    END LOOP;
END
$r$;

CREATE SCHEMA IF NOT EXISTS api_cache AUTHORIZATION ezd;
GRANT USAGE, CREATE ON SCHEMA api_cache TO ezd;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    GRANT SELECT ON TABLES TO ezd;

SELECT
    has_table_privilege('ezd', 'public.eurzad_teczka', 'INSERT')   AS ins,
    has_table_privilege('ezd', 'public.eurzad_teczka', 'UPDATE')   AS upd,
    has_table_privilege('ezd', 'public.eurzad_teczka', 'DELETE')   AS del,
    has_table_privilege('ezd', 'public.eurzad_teczka', 'TRUNCATE') AS trunc,
    has_schema_privilege('ezd', 'public', 'CREATE')              AS pub_create,
    has_schema_privilege('ezd', 'api_cache', 'CREATE')             AS cache_create;
EOSQL
```

Oczekiwane: `f / f / f / f / f / t`.

Test:

```bash
PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U ezd -d "$DB_NAME" \
  -c "INSERT INTO public.eurzad_teczka DEFAULT VALUES;"
# ERROR: permission denied
```

Opcjonalnie MV list API:

```bash
php artisan materialized-views:refresh
# USE_MATERIALIZED_VIEWS=true w .env
```

## 6. Apache + php-fpm

```bash
sudo a2enmod rewrite proxy proxy_fcgi setenvif headers
sudo a2enconf php8.5-fpm   # wersja PHP dostosować

sudo tee "/etc/apache2/sites-available/${VHOST_NAME}.conf" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}

    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=\$1

    ErrorLog \${APACHE_LOG_DIR}/${VHOST_NAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${VHOST_NAME}-access.log combined
</VirtualHost>
EOF

sudo a2ensite "${VHOST_NAME}.conf"
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Uprawnienia plików:

```bash
sudo chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
sudo chmod -R ug+rwx "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
```

## 7. Weryfikacja

```bash
curl -fsS -H "Host: $SERVER_NAME" http://127.0.0.1/health
# {"status":"ok"}

TOKEN=$(grep '^MADKOM_API_TOKEN=' .env | cut -d= -f2-)

curl -fsS -H "Host: $SERVER_NAME" -H "madkom-api-token: $TOKEN" \
  http://127.0.0.1/api/v1/system/db-privileges
# "compliant": true

curl -fsS -H "Host: $SERVER_NAME" -H "madkom-api-token: $TOKEN" \
  http://127.0.0.1/api/v1/workstations
# HTTP 200, "success": true
```

## Uwagi

- Długie zadania: `screen` / `tmux`
- Logi: `storage/logs/`, `/var/log/apache2/${VHOST_NAME}-*.log`
- `FILES_URL` — mount załączników EZD (osobny krok)
- Dev lokalny (Docker): import → `migrate`; bez kroku 5, `ENFORCE=false`
- Krok 5 **nie** uruchamiaj jako `ezd`

---

## Wyjątek: dump Chojnice — `eurzad_obieg.max_status_sprawy_id`

Niektóre dumpy (m.in. Chojnice) mają błąd danych: wiele wierszy `eurzad_obieg` z `max_status_sprawy_id > 0` dla tej samej `sprawa_uid`. Powoduje to duplikaty w listach spraw (`CaseListQuery` — JOIN `eo.max_status_sprawy_id > 0`).

**Kiedy:** po imporcie (krok 2), **przed** krokiem 5 (wymaga `UPDATE`).

**Kto:** `postgres`.

Sprawdzenie, czy dotyczy:

```sql
SELECT sprawa_uid, COUNT(*)
FROM eurzad_obieg
WHERE max_status_sprawy_id > 0
GROUP BY sprawa_uid
HAVING COUNT(*) > 1
LIMIT 10;
```

Korekta (jednorazowo):

```sql
WITH ranked AS (
    SELECT
        eo.status_sprawy_id,
        eo.sprawa_uid,
        ROW_NUMBER() OVER (
            PARTITION BY eo.sprawa_uid
            ORDER BY eo.status_sprawy_id DESC
        ) AS rn
    FROM eurzad_obieg eo
    WHERE eo.sprawa_uid IN (
        SELECT sprawa_uid
        FROM eurzad_obieg
        WHERE max_status_sprawy_id > 0
        GROUP BY sprawa_uid
        HAVING COUNT(*) > 1
    )
)
UPDATE eurzad_obieg eo
SET max_status_sprawy_id = CASE WHEN r.rn = 1 THEN 1 ELSE 0 END
FROM ranked r
WHERE eo.status_sprawy_id = r.status_sprawy_id
  AND eo.sprawa_uid = r.sprawa_uid;
```

Weryfikacja — zapytanie kontrolne powinno zwrócić 0 wierszy:

```sql
SELECT sprawa_uid, COUNT(*)
FROM eurzad_obieg
WHERE max_status_sprawy_id > 0
GROUP BY sprawa_uid
HAVING COUNT(*) > 1;
```

Źródło: komentarz w `app/Source/V1/Queries/Case/CaseListQuery.php` (poprawka MADKOM na żywej bazie).
