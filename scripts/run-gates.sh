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
RUN_CONTENT_AUDIT=0

usage() {
  cat <<'EOF'
Uso: scripts/run-gates.sh [--browser] [--pdp] [--cart] [--content-audit] [--skip-provision]

  --browser         Executa todos os gates Playwright no contêiner node
  --pdp             Executa somente o gate da página de produto
  --cart            Executa somente o gate de adicionar ao carrinho
  --content-audit   Audita o cadastro editorial de produtos (imagem, alt e copy)
  --skip-provision  Pula migrações/seed antes dos validators PHP

Executa smoke PHP e testes de persistência sem depender da completude editorial do catálogo.
Requer stack Compose up e .env configurado.
EOF
}

SKIP_PROVISION=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --browser) RUN_BROWSER=1; shift ;;
    --pdp) RUN_PDP=1; shift ;;
    --cart) RUN_CART=1; shift ;;
    --content-audit) RUN_CONTENT_AUDIT=1; shift ;;
    --skip-provision) SKIP_PROVISION=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; usage; exit 1 ;;
  esac
done

if [[ ! -f .env ]]; then
  echo "Arquivo .env ausente. Copie .env.example para .env." >&2
  exit 1
fi

node scripts/validate-014-docs-and-tokens.mjs

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
  echo "==> fixtures administraveis do Plano 013"
  run_eval_file seed-013-catalog-samples.php
  echo "==> produtos Animal Republik autorizados"
  run_eval_file seed-animal-republik-launches.php
  echo "==> vitrines comerciais com Ver tudo"
  run_eval_file sync-commercial-page-catalog-links.php
fi

run_eval_file validate-storefront.php
run_eval_file validate-005-session-01.php
run_eval_file validate-005-session-02.php
run_eval_file test-004b-persistence.php
run_eval_file test-005-session-01-persistence.php
run_eval_file test-005-session-02-persistence.php
run_eval_file test-013-persistence.php
run_eval_file validate-013-hpos.php
run_eval_file validate-013-security.php
run_eval_file validate-014-identity-campaigns.php
run_eval_file validate-015-support-section.php
run_eval_file validate-016-product-grid.php
run_eval_file validate-018-commercial-pages.php
run_eval_file validate-animal-republik-products.php

if [[ "$RUN_CONTENT_AUDIT" -eq 1 ]]; then
  run_eval_file validate-004b.php
  run_eval_file audit-storefront-content.php
fi

if [[ "$RUN_BROWSER" -eq 1 || "$RUN_PDP" -eq 1 || "$RUN_CART" -eq 1 ]]; then
  original_home="$(run_wp option get home)"
  expected_public_url="$(sed -n 's/^WORDPRESS_URL=//p' .env | head -n 1)"
  expected_public_url="${expected_public_url:-http://localhost:8888}"
  case "$expected_public_url" in
    http://localhost:*|https://localhost:*|http://127.0.0.1:*|https://127.0.0.1:*) ;;
    *) echo "WORDPRESS_URL deve ser loopback para executar os gates browser locais." >&2; exit 1 ;;
  esac
  if [[ "$original_home" == "http://wordpress" ]]; then
    echo "==> recuperando URL publica deixada por gate browser interrompido"
    run_wp option update home "$expected_public_url" >/dev/null
    run_wp option update siteurl "$expected_public_url" >/dev/null
    run_wp cache flush >/dev/null
    original_home="$expected_public_url"
  fi
  case "$original_home" in
    http://localhost:*|https://localhost:*|http://127.0.0.1:*|https://127.0.0.1:*) ;;
    *) echo "Os gates browser que isolam a URL do Compose so podem alterar uma instalacao local." >&2; exit 1 ;;
  esac
  original_siteurl="$(run_wp option get siteurl)"
  restore_urls() {
    run_wp option update home "$original_home" >/dev/null
    run_wp option update siteurl "$original_siteurl" >/dev/null
    run_wp cache flush >/dev/null
  }
  trap restore_urls EXIT

  run_wp option update home http://wordpress >/dev/null
  run_wp option update siteurl http://wordpress >/dev/null
  run_wp cache flush >/dev/null

  if [[ "$RUN_BROWSER" -eq 1 ]]; then
    echo "==> browser gates (container)"
    for script in validate-005-session-01-browser.mjs validate-005-session-02-browser.mjs validate-005-catalog-layout-browser.mjs validate-013-browser.mjs validate-016-product-grid-browser.mjs validate-018-commercial-pages-browser.mjs validate-no-theme-hero-browser.mjs; do
      docker compose --profile tools run --rm node node "/workspace/scripts/$script"
    done
    docker compose --profile tools run --rm node node /workspace/scripts/validate-016-product-grid-editor.mjs
  fi

  if [[ "$RUN_PDP" -eq 1 || "$RUN_BROWSER" -eq 1 ]]; then
    docker compose --profile tools run --rm node node /workspace/scripts/validate-005-pdp-browser.mjs
  fi

  if [[ "$RUN_CART" -eq 1 || "$RUN_BROWSER" -eq 1 ]]; then
    docker compose --profile tools run --rm node node /workspace/scripts/validate-005-cart-browser.mjs
  fi

  restore_urls
  trap - EXIT
fi

echo "run-gates: all PHP gates passed"
