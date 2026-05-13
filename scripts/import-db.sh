#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

DUMP_GZ="ezd_dump_chojnice_sidas.sql.gz"
DUMP_SQL="ezd_dump_chojnice_sidas.sql"

resolve_dump() {
  if [[ -f "$DUMP_GZ" ]]; then
    printf '%s' "$DUMP_GZ"
  elif [[ -f "$DUMP_SQL" ]]; then
    printf '%s' "$DUMP_SQL"
  else
    echo "Brak pliku dumpa. Oczekiwano: ${DUMP_GZ} lub ${DUMP_SQL}" >&2
    exit 1
  fi
}

DUMP_FILE="$(resolve_dump)"

if [[ -z "$(docker compose ps -q --status running db 2>/dev/null || true)" ]]; then
  docker compose up -d
fi

if [[ -z "$(docker compose ps -q db 2>/dev/null || true)" ]]; then
  echo "Brak kontenera dla serwisu db (docker compose ps -q db)." >&2
  exit 1
fi

for _ in {1..60}; do
  if docker compose exec -T db pg_isready >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

if ! docker compose exec -T db pg_isready >/dev/null 2>&1; then
  echo "PostgreSQL w kontenerze db nie odpowiada (timeout)." >&2
  exit 1
fi

POSTGRES_USER="$(docker compose exec -T db sh -c 'printf %s "$POSTGRES_USER"' | tr -d '\r')"
POSTGRES_DB="$(docker compose exec -T db sh -c 'printf %s "$POSTGRES_DB"' | tr -d '\r')"

if [[ -z "$POSTGRES_USER" || -z "$POSTGRES_DB" ]]; then
  echo "Nie udało się odczytać POSTGRES_USER / POSTGRES_DB z kontenera db." >&2
  exit 1
fi

echo "Import: ${DUMP_FILE} -> baza ${POSTGRES_DB} (użytkownik ${POSTGRES_USER}, kontener: $(docker compose ps -q db))"

case "$DUMP_FILE" in
  *.gz)
    gunzip -c "$DUMP_FILE" | docker compose exec -T db psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"
    ;;
  *.sql)
    docker compose exec -T db psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <"$DUMP_FILE"
    ;;
  *)
    echo "Nieobsługiwane rozszerzenie (oczekiwano .sql lub .sql.gz)." >&2
    exit 1
    ;;
esac

echo "Import zakończony."
