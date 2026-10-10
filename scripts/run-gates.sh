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
  echo "==> fixtures personalizáveis do Plano 012"
  run_eval_file seed-012-personalizable-products.php
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
run_eval_file validate-025-account-registration.php
run_eval_file validate-014-identity-campaigns.php
run_eval_file validate-015-support-section.php
run_eval_file validate-016-product-grid.php
run_eval_file validate-018-commercial-pages.php
run_eval_file validate-animal-republik-products.php
run_eval_file validate-012-personalization.php
run_eval_file validate-023-footer.php
run_eval_file validate-024-home-campaigns-carousel.php
run_eval_file validate-030.php
run_eval_file validate-032-search.php
run_eval_file validate-039-cart-qty.php
run_eval_file validate-shipping-quote-destination.php
run_eval_file validate-041-shipping-destination.php
run_eval_file validate-041-shipping-preview.php
"${COMPOSE[@]}" wp eval 'require "/var/www/html/scripts/validate-041-shipping-selection-cache.php";'
run_eval_file validate-041-block-persistence.php
run_eval_file validate-041-native-commerce.php
docker compose --profile tools run --rm node node /workspace/scripts/validate-041-quote-preference.mjs
docker compose --profile tools run --rm node node /workspace/scripts/validate-041-cart-operations.mjs
docker compose --profile tools run --rm node node /workspace/scripts/validate-041-cart-request-coordinator.mjs
docker compose --profile tools run --rm node node /workspace/scripts/validate-041-cart-estimate.mjs
docker compose --profile tools run --rm node node /workspace/scripts/validate-041-runner-cleanup.mjs
run_eval_file validate-034-emails.php
run_eval_file validate-035-menu-dropdown.php
run_eval_file smoke-012-order-flow.php

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
    local restore_status=0
    run_wp option update home "$original_home" >/dev/null || restore_status=$?
    run_wp option update siteurl "$original_siteurl" >/dev/null || restore_status=$?
    run_wp cache flush >/dev/null || restore_status=$?
    return "$restore_status"
  }
  finish_browser_gate() {
    local original_status=$?
    local cleanup_status=0
    local restore_status=0
    trap - EXIT
    if [[ "$#" -gt 0 ]]; then "$1" || cleanup_status=$?; fi
    restore_urls || restore_status=$?
    if [[ "$cleanup_status" -ne 0 ]]; then echo "Browser fixture cleanup failed: $cleanup_status" >&2; fi
    if [[ "$restore_status" -ne 0 ]]; then echo "Browser URL restore failed: $restore_status" >&2; fi
    if [[ "$original_status" -ne 0 ]]; then exit "$original_status"; fi
    if [[ "$cleanup_status" -ne 0 ]]; then exit "$cleanup_status"; fi
    exit "$restore_status"
  }
  trap finish_browser_gate EXIT

  run_wp option update home http://wordpress >/dev/null
  run_wp option update siteurl http://wordpress >/dev/null
  run_wp cache flush >/dev/null

  if [[ "$RUN_BROWSER" -eq 1 ]]; then
    echo "==> browser gates (container)"
    cleanup_plan041_fixture() {
      local cleanup_status=0
      run_eval_file cleanup-041-browser-customer.php || cleanup_status=$?
      rm -f .local/041-browser-fixture.json || cleanup_status=$?
      run_eval_file cleanup-041-editor-fixture.php || cleanup_status=$?
      rm -f .local/041-editor-fixture.json || cleanup_status=$?
      return "$cleanup_status"
    }
    trap 'finish_browser_gate cleanup_plan041_fixture' EXIT
    mkdir -p .local
    run_wp eval-file /var/www/html/scripts/setup-041-browser-customer.php > .local/041-browser-fixture.json
    run_wp eval-file /var/www/html/scripts/setup-041-editor-fixture.php > .local/041-editor-fixture.json
    for script041 in validate-041-product-quote-browser.mjs validate-041-address-races-browser.mjs validate-041-editor-browser.mjs validate-041-checkout-address-browser.mjs validate-041-cart-delivery-browser.mjs validate-041-cart-concurrency-browser.mjs validate-041-cart-performance-browser.mjs validate-041-cart-failures-browser.mjs validate-041-delivery-performance-browser.mjs validate-041-cart-consistency-browser.mjs; do
      docker compose --profile tools run --rm -e PETSHOP_BASE_URL=http://wordpress -e PETSHOP_CANONICAL_HOST=wordpress node node "/workspace/scripts/$script041"
    done
    cleanup_plan041_fixture
    trap finish_browser_gate EXIT
    for script in validate-005-session-01-browser.mjs validate-005-session-02-browser.mjs validate-005-catalog-layout-browser.mjs validate-013-browser.mjs validate-016-product-grid-browser.mjs validate-018-commercial-pages-browser.mjs validate-012-personalizer-browser.mjs validate-023-footer-browser.mjs validate-024-home-campaigns-carousel-browser.mjs validate-025-account-registration-browser.mjs validate-030-order-received-browser.mjs validate-032-search-browser.mjs validate-037-cart-auto-update-browser.mjs validate-039-cart-qty-browser.mjs validate-035-menu-dropdown-browser.mjs validate-no-theme-hero-browser.mjs; do
      docker compose --profile tools run --rm -e PETSHOP_CANONICAL_HOST=wordpress node node "/workspace/scripts/$script"
    done
    plan031_fixture_ready=0
    cleanup_plan031_fixture() {
      if [[ "$plan031_fixture_ready" -eq 1 ]]; then
        run_eval_file cleanup-031-product-card-fixture.php
        plan031_fixture_ready=0
      fi
    }

    run_eval_file setup-031-product-card-fixture.php
    plan031_fixture_ready=1
    trap 'finish_browser_gate cleanup_plan031_fixture' EXIT
    docker compose --profile tools run --rm -e PETSHOP_BASE_URL=http://wordpress -e PETSHOP_CANONICAL_HOST=localhost:8888 node node /workspace/scripts/validate-031-product-card-browser.mjs
    cleanup_plan031_fixture
    trap finish_browser_gate EXIT

    docker compose --profile tools run --rm -e PETSHOP_CANONICAL_HOST=wordpress node node /workspace/scripts/validate-016-product-grid-editor.mjs
  fi

  if [[ "$RUN_PDP" -eq 1 || "$RUN_BROWSER" -eq 1 ]]; then
    docker compose --profile tools run --rm -e PETSHOP_CANONICAL_HOST=wordpress node node /workspace/scripts/validate-005-pdp-browser.mjs
  fi

  if [[ "$RUN_CART" -eq 1 || "$RUN_BROWSER" -eq 1 ]]; then
    docker compose --profile tools run --rm -e PETSHOP_CANONICAL_HOST=wordpress node node /workspace/scripts/validate-005-cart-browser.mjs
  fi

  restore_urls
  trap - EXIT
fi

echo "run-gates: all PHP gates passed"
