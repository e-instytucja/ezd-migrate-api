#!/usr/bin/env bash
# Importuje .sql lub .sql.gz do kontenera PostgreSQL o nazwie pg_import (docker exec).
# Mapowanie portu na hoście (np. 5433) nie jest potrzebne — komunikacja idzie przez Docker.
#
# Użycie:
#   ./scripts/import-to-pg_import.sh [ścieżka_do_pliku]
# Bez argumentu: pierwszy istniejący z
#   ezd_dump_chojnice_sidas.sql, ezd_dump_chojnice_sidas.sql.gz w katalogu projektu.
#
# Zmienne opcjonalne:
#   PG_IMPORT_CONTAINER  — nazwa kontenera (domyślnie: pg_import)
#   IMPORT_DB_USER       — nadpisanie użytkownika psql (domyślnie: POSTGRES_USER z kontenera)
#   IMPORT_DB_NAME       — nadpisanie nazwy bazy (domyślnie: POSTGRES_DB z kontenera)
#   IMPORT_SKIP_EZD_ROLE — jeśli ustawione (np. 1), nie tworzy roli „ezd” przed importem

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

CONTAINER="${PG_IMPORT_CONTAINER:-pg_import}"

resolve_default_dump() {
  local f
  for f in \
    "$PROJECT_ROOT/ezd_dump_chojnice_sidas.sql" \
    "$PROJECT_ROOT/ezd_dump_chojnice_sidas.sql.gz"; do
    if [[ -f "$f" ]]; then
      printf '%s' "$f"
      return 0
    fi
  done
  printf '%s' "$PROJECT_ROOT/ezd_dump_chojnice_sidas.sql"
}

if [[ -n "${1:-}" ]]; then
  DUMP="$1"
else
  DUMP="$(resolve_default_dump)"
fi

if [[ ! -f "$DUMP" ]]; then
  echo "Brak pliku: $DUMP" >&2
  echo "Podaj ścieżkę: $0 ./ezd_dump_chojnice_sidas.sql" >&2
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  echo "Docker nie odpowiada (uruchom Docker Desktop / demona)." >&2
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "Kontener „$CONTAINER” nie jest uruchomiony. Sprawdź: docker ps" >&2
  exit 1
fi

for _ in {1..120}; do
  if docker exec "$CONTAINER" pg_isready >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

if ! docker exec "$CONTAINER" pg_isready >/dev/null 2>&1; then
  echo "PostgreSQL w „$CONTAINER” nie odpowiada (timeout pg_isready)." >&2
  exit 1
fi

U="$(docker exec "$CONTAINER" sh -c 'printf %s "$POSTGRES_USER"' | tr -d '\r')"
D="$(docker exec "$CONTAINER" sh -c 'printf %s "$POSTGRES_DB"' | tr -d '\r')"
POSTGRES_USER="${IMPORT_DB_USER:-$U}"
POSTGRES_DB="${IMPORT_DB_NAME:-$D}"

if [[ -z "$POSTGRES_USER" || -z "$POSTGRES_DB" ]]; then
  echo "Nie udało się ustalić POSTGRES_USER / POSTGRES_DB w kontenerze „$CONTAINER”." >&2
  exit 1
fi

echo "Import: $DUMP -> kontener $CONTAINER, baza $POSTGRES_DB, użytkownik $POSTGRES_USER"

if [[ -z "${IMPORT_SKIP_EZD_ROLE:-}" ]]; then
  echo "Upewnianie się, że istnieje rola „ezd” (wymagana przez zrzuty EZD / OWNER TO ezd)…"
  docker exec -i "$CONTAINER" psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<'EOSQL'
DO $$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'ezd') THEN
    CREATE ROLE ezd WITH LOGIN SUPERUSER;
  END IF;
END
$$;
EOSQL
fi

case "$DUMP" in
  *.gz)
    gunzip -c "$DUMP" | docker exec -i "$CONTAINER" psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"
    ;;
  *.sql)
    docker exec -i "$CONTAINER" psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <"$DUMP"
    ;;
  *)
    echo "Obsługiwane rozszerzenia: .sql, .sql.gz" >&2
    exit 1
    ;;
esac

echo "Import zakończony."
