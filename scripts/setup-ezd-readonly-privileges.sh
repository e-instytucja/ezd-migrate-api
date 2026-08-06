#!/usr/bin/env bash
# Ustawia read-only na danych EZD (public) i GRANT CREATE na api_cache dla DB_USERNAME z .env.
# Uruchamiaj na prod/staging PO imporcie dumpa i php artisan migrate.
# Lokalnie: tylko gdy świadomie testujesz prod-like (odbierze INSERT na EZD).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SQL_FILE="$SCRIPT_DIR/sql/setup-ezd-readonly-privileges.sql"

ENV_FILE="$PROJECT_ROOT/.env"
APP_USER=""
MV_SCHEMA=""
DATABASE=""
SUPERUSER=""
DRY_RUN=false
ASSUME_YES=false

usage() {
  cat <<'EOF'
Uzycie: setup-ezd-readonly-privileges.sh [opcje]

Konfiguracja domyslnie z .env: DB_USERNAME, DB_MV_SCHEMA, DB_DATABASE.

Opcje:
  --env-file=PATH     Sciezka do .env (domyslnie: .env w katalogu projektu)
  --app-user=USER     Nadpisanie DB_USERNAME
  --mv-schema=SCHEMA  Nadpisanie DB_MV_SCHEMA (domyslnie: api_cache)
  --database=DB       Nadpisanie DB_DATABASE
  --superuser=USER    Uzytkownik psql z uprawnieniami DDL (domyslnie: POSTGRES_USER z kontenera db)
  --dry-run           Wypisz plan bez wykonania SQL
  --yes               Bez pytania o potwierdzenie
  -h, --help          Pomoc

Przyklad (prod):
  ./scripts/setup-ezd-readonly-privileges.sh --yes
EOF
}

read_env_value() {
  local key="$1"
  local file="$2"
  if [[ ! -f "$file" ]]; then
    return 1
  fi
  grep -E "^${key}=" "$file" | tail -n 1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

assert_identifier() {
  local name="$1"
  local label="$2"
  if [[ ! "$name" =~ ^[a-z][a-z0-9_]*$ ]]; then
    echo "Nieprawidlowy identyfikator ${label}: ${name}" >&2
    exit 1
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env-file=*)
      ENV_FILE="${1#*=}"
      shift
      ;;
    --app-user=*)
      APP_USER="${1#*=}"
      shift
      ;;
    --mv-schema=*)
      MV_SCHEMA="${1#*=}"
      shift
      ;;
    --database=*)
      DATABASE="${1#*=}"
      shift
      ;;
    --superuser=*)
      SUPERUSER="${1#*=}"
      shift
      ;;
    --dry-run)
      DRY_RUN=true
      shift
      ;;
    --yes)
      ASSUME_YES=true
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Nieznana opcja: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

cd "$PROJECT_ROOT"

if [[ -z "$APP_USER" ]]; then
  APP_USER="$(read_env_value DB_USERNAME "$ENV_FILE" || true)"
fi
if [[ -z "$MV_SCHEMA" ]]; then
  MV_SCHEMA="$(read_env_value DB_MV_SCHEMA "$ENV_FILE" || true)"
fi
MV_SCHEMA="${MV_SCHEMA:-api_cache}"

if [[ -z "$DATABASE" ]]; then
  DATABASE="$(read_env_value DB_DATABASE "$ENV_FILE" || true)"
fi

if [[ -z "$APP_USER" || -z "$DATABASE" ]]; then
  echo "Brak DB_USERNAME lub DB_DATABASE (plik: ${ENV_FILE})." >&2
  exit 1
fi

assert_identifier "$APP_USER" 'app_user'
assert_identifier "$MV_SCHEMA" 'mv_schema'
assert_identifier "$DATABASE" 'database'

if [[ ! -f "$SQL_FILE" ]]; then
  echo "Brak pliku SQL: ${SQL_FILE}" >&2
  exit 1
fi

USE_DOCKER=false
if command -v docker >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/docker-compose.yml" ]]; then
  if [[ -n "$(docker compose ps -q db 2>/dev/null || true)" ]]; then
    USE_DOCKER=true
  fi
fi

if [[ "$USE_DOCKER" == true ]]; then
  for _ in {1..30}; do
    if docker compose exec -T db pg_isready >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
  if ! docker compose exec -T db pg_isready >/dev/null 2>&1; then
    echo "PostgreSQL w kontenerze db nie odpowiada." >&2
    exit 1
  fi
  if [[ -z "$SUPERUSER" ]]; then
    SUPERUSER="$(docker compose exec -T db sh -c 'printf %s "$POSTGRES_USER"' | tr -d '\r')"
  fi
fi

if [[ -z "$SUPERUSER" ]]; then
  SUPERUSER="${PGUSER:-postgres}"
fi

assert_identifier "$SUPERUSER" 'superuser'

echo "=== setup-ezd-readonly-privileges ==="
echo "  env_file:   ${ENV_FILE}"
echo "  database:   ${DATABASE}"
echo "  app_user:   ${APP_USER}  (DB_USERNAME)"
echo "  mv_schema:  ${MV_SCHEMA}  (DB_MV_SCHEMA)"
echo "  superuser:  ${SUPERUSER}"
echo "  docker:     ${USE_DOCKER}"
echo ""
echo "UWAGA: Odbierze INSERT/UPDATE/DELETE/TRUNCATE na tabelach EZD (eurzad_*, galaxia_*, ...) dla ${APP_USER}."
echo ""

if [[ "$DRY_RUN" == true ]]; then
  echo "[dry-run] SQL: ${SQL_FILE}"
  echo "[dry-run] psql -v app_user=${APP_USER} -v mv_schema=${MV_SCHEMA}"
  exit 0
fi

if [[ "$ASSUME_YES" != true ]]; then
  read -r -p "Kontynuowac? [y/N] " confirm
  if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo "Przerwano."
    exit 0
  fi
fi

run_psql() {
  if [[ "$USE_DOCKER" == true ]]; then
    docker compose exec -T db psql -v ON_ERROR_STOP=1 \
      -U "$SUPERUSER" \
      -d "$DATABASE" \
      -v "app_user=${APP_USER}" \
      -v "mv_schema=${MV_SCHEMA}" \
      <"$SQL_FILE"
  else
    psql -v ON_ERROR_STOP=1 \
      -U "$SUPERUSER" \
      -d "$DATABASE" \
      -v "app_user=${APP_USER}" \
      -v "mv_schema=${MV_SCHEMA}" \
      <"$SQL_FILE"
  fi
}

run_psql

echo ""
echo "Gotowe. Sprawdz: GET /api/v1/system/db-privileges (z tokenem) lub ENFORCE_EZD_DB_READ_ONLY=true."
