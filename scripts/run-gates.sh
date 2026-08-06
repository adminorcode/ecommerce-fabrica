#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Git Bash no Windows reescreve /var/... — preservar paths do contêiner.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL="*"

COMPOSE=(docker compose --profile tools run --rm --no-deps cli)
SCRIPTS=//var/www/html/scripts
RUN_BROWSER=0
RUN_PDP=0
RUN_CART=0

usage() {
  cat <<'EOF'
Uso: scripts/run-gates.sh [--browser] [--pdp] [--cart] [--skip-provision]

  --browser         Executa todos os gates Playwright no contêiner node
  --pdp             Executa somente o gate da página de produto
  --cart            Executa somente o gate de adicionar ao carrinho
  --skip-provision  Pula migrações/seed antes dos validators PHP

Executa smoke PHP: validate-storefront, validate-004b, validate-005-session-01/02.
Requer stack Compose up e .env configurado.
EOF
}

SKIP_PROVISION=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --browser) RUN_BROWSER=1; shift ;;
    --pdp) RUN_PDP=1; shift ;;
    --cart) RUN_CART=1; shift ;;
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
  echo "==> provisionando taxonomia"
  run_wp eval 'Petshop\Core\StorefrontCatalog::maybeEnsureCategories();'
  echo "==> seed demonstrativo 004b (idempotente)"
  run_eval_file seed-storefront-placeholders.php
  echo "==> provisionando storefront"
  run_wp eval 'Petshop\Core\StorefrontExperience::maybeEnsureStorefront();'
fi

run_eval_file validate-storefront.php
run_eval_file validate-004b.php
run_eval_file validate-005-session-01.php
run_eval_file validate-005-session-02.php

if [[ "$RUN_BROWSER" -eq 1 ]]; then
  echo "==> browser gates (container)"
  for script in validate-005-session-01-browser.mjs validate-005-session-02-browser.mjs validate-005-catalog-layout-browser.mjs; do
    docker compose --profile tools run --rm node node "/workspace/scripts/$script"
  done
fi

if [[ "$RUN_PDP" -eq 1 || "$RUN_BROWSER" -eq 1 ]]; then
  docker compose --profile tools run --rm node node /workspace/scripts/validate-005-pdp-browser.mjs
fi

if [[ "$RUN_CART" -eq 1 || "$RUN_BROWSER" -eq 1 ]]; then
  docker compose --profile tools run --rm node node /workspace/scripts/validate-005-cart-browser.mjs
fi

echo "run-gates: all PHP gates passed"
