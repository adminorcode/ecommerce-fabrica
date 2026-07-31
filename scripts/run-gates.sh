#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose --profile tools run --rm --no-deps cli)
SCRIPTS=/var/www/html/scripts
RUN_BROWSER=0

usage() {
  cat <<'EOF'
Uso: scripts/run-gates.sh [--browser] [--skip-provision]

  --browser         Executa gates Playwright (requer Playwright no host; Plano 008)
  --skip-provision  Pula migrações/seed antes dos validators PHP

Executa smoke PHP: validate-storefront, validate-004b, validate-005-session-01/02.
Requer stack Compose up e .env configurado.
EOF
}

SKIP_PROVISION=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --browser) RUN_BROWSER=1; shift ;;
    --skip-provision) SKIP_PROVISION=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; usage; exit 1 ;;
  esac
done

if [[ ! -f .env ]]; then
  echo "Arquivo .env ausente. Copie .env.example para .env." >&2
  exit 1
fi

run_wp() {
  "${COMPOSE[@]}" wp "$@"
}

run_eval_file() {
  local script="$1"
  echo "==> wp eval-file ${script}"
  "${COMPOSE[@]}" wp eval-file "${SCRIPTS}/${script}"
}

if [[ "$SKIP_PROVISION" -eq 0 ]]; then
  echo "==> provisionando taxonomia e storefront"
  run_wp eval 'Petshop\Core\StorefrontCatalog::maybeEnsureCategories(); Petshop\Core\StorefrontExperience::maybeEnsureStorefront();'
  echo "==> seed demonstrativo 004b (idempotente)"
  run_eval_file seed-storefront-placeholders.php
fi

run_eval_file validate-storefront.php
run_eval_file validate-004b.php
run_eval_file validate-005-session-01.php
run_eval_file validate-005-session-02.php

if [[ "$RUN_BROWSER" -eq 1 ]]; then
  echo "==> browser gates (host)"
  node "${ROOT}/scripts/validate-005-session-01-browser.mjs"
  node "${ROOT}/scripts/validate-005-session-02-browser.mjs"
  node "${ROOT}/scripts/validate-005-catalog-layout-browser.mjs"
fi

echo "run-gates: all PHP gates passed"
