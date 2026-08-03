#!/usr/bin/env bash
set -euo pipefail

# Corrige permissões de uploads após WP-CLI com --allow-root criar arquivos como root.
# Sintoma: upload de mídia no Gutenberg retorna 500 (rest_upload_sideload_error).

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! docker compose ps --status running --services 2>/dev/null | grep -qx 'wordpress'; then
  echo "Container wordpress não está rodando. Execute: docker compose up -d" >&2
  exit 1
fi

MSYS_NO_PATHCONV=1 docker compose exec -T wordpress \
  chown -R www-data:www-data /var/www/html/wp-content/uploads

echo "Permissões de uploads corrigidas (www-data:www-data)."
