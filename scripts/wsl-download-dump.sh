#!/usr/bin/env bash
# Pobiera zrzut bazy do WSL (uruchamiaj w bashu w WSL).
# Użycie:
#   ./scripts/wsl-download-dump.sh <URL> [ścieżka_docelowa]
# Przykład:
#   ./scripts/wsl-download-dump.sh https://example.com/ezd_dump.sql.gz ./ezd_dump_chojnice_sidas.sql.gz

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

URL="${1:?Podaj URL jako pierwszy argument (np. https://.../dump.sql.gz)}"
OUT="${2:-$PROJECT_ROOT/ezd_dump_chojnice_sidas.sql.gz}"

mkdir -p "$(dirname "$OUT")"

if command -v curl >/dev/null 2>&1; then
  curl -fL --progress-bar -o "$OUT" "$URL"
elif command -v wget >/dev/null 2>&1; then
  wget -O "$OUT" "$URL"
else
  echo "Zainstaluj curl albo wget w WSL (sudo apt install -y curl)." >&2
  exit 1
fi

echo "Zapisano: $OUT ($(du -h "$OUT" | cut -f1))"
