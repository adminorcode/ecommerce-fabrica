# Scripts

Scripts repetíveis de bootstrap, validação, importação de dados de demonstração e automação local.

Scripts devem ser:

- idempotentes quando possível;
- documentados;
- seguros para execução local;
- independentes de segredos versionados.

## Caminho no contêiner

O serviço `cli` monta esta pasta em **`/var/www/html/scripts`** (read-only).

```powershell
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/validate-storefront.php
```

## Orquestrador (smoke PHP)

```powershell
# Bash (Git Bash / Linux / CI)
bash scripts/run-gates.sh

# PowerShell
./scripts/run-gates.ps1

# npm (delega ao script acima)
npm run validate
```

Opções:

- `--browser` — inclui todos os gates Playwright no contêiner `node`
- `--pdp` / `--cart` — executa o gate isolado de PDP ou de adicionar ao carrinho
- `--skip-provision` — pula migrações/seed antes dos validators

Sequência padrão:

1. `StorefrontCatalog::maybeEnsureCategories()`
2. `seed-storefront-placeholders.php`
3. `StorefrontExperience::maybeEnsureStorefront()`
4. validators PHP

**Git Bash (Windows):** `run-gates.sh` exporta `MSYS_NO_PATHCONV=1` para preservar paths `/var/www/html/scripts` no contêiner.

## Catálogo por plano

| Script | Plano | Descrição |
|--------|-------|-----------|
| `seed-storefront-placeholders.php` | 004b | Seed idempotente de produtos demo |
| `validate-storefront.php` | 004 | Taxonomia, Home, menus e blocos |
| `audit-storefront-content.php` | Operacional | Qualidade editorial dos produtos publicados |
| `validate-004b.php` | 004b | Manifesto XLSX/JSON e vitrine |
| `validate-storefront.php` | 004 | Smoke geral |
| `validate-005-session-01.php` | 005 S01 | Header comercial |
| `validate-005-session-02.php` | 005 S02 | Hero e benefícios |
| `validate-005-session-01-browser.mjs` | 005 S01 | Browser: header |
| `validate-005-session-02-browser.mjs` | 005 S02 | Browser: hero |
| `validate-005-catalog-layout-browser.mjs` | 005 | Browser: filtro lateral |
| `validate-005-pdp-browser.mjs` | 008 | Browser: PDP (preço, CTA e aviso) |
| `validate-005-cart-browser.mjs` | 008 | Browser: adicionar ao carrinho/minicarrinho |
| `validate-005-session-02-editor.mjs` | 005 S02 | Editor Gutenberg |
| `validate-009-cart-checkout-browser.mjs` | 009 | Browser: cart/checkout tokens e a11y |
| `test-004b-persistence.php` | 004b | Persistência editorial |
| `test-005-session-01-persistence.php` | 005 S01 | Persistência header |
| `test-005-session-02-persistence.php` | 005 S02 | Persistência hero |
| `test-005-session-02-migrations.php` | 005 S02 | Migrações Home |

## Seed 004b (manual)

Ordem segura: taxonomia → seed → Home → validação.

```powershell
docker compose --profile tools run --rm --no-deps cli wp eval 'Petshop\Core\StorefrontCatalog::maybeEnsureCategories();'
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/seed-storefront-placeholders.php
docker compose --profile tools run --rm --no-deps cli wp eval 'Petshop\Core\StorefrontExperience::maybeEnsureStorefront();'
npm run validate
```

O seed preserva SKUs existentes. Produtos criados recebem `_petshop_placeholder_004b=1`.

## Variáveis de ambiente

| Variável | Uso |
|----------|-----|
| `PETSHOP_EXPECTED_BLOGNAME` | `validate-storefront.php` — assert opcional do nome da loja |
| `PETSHOP_VALIDATE_DEFAULTS` | `1` — valida defaults iniciais de taxonomia/menu |
| `PETSHOP_BASE_URL` | Browser gates (default `http://localhost:8888`) |

## CI

Pull requests executam `bash scripts/run-gates.sh` e PHPUnit via `.github/workflows/validate.yml`. A auditoria editorial não bloqueia esses testes: execute `npm run validate -- --content-audit --skip-provision` para verificar o cadastro atual sem reprovisioná-lo.
O workflow manual `.github/workflows/browser-gates.yml` executa os gates Playwright
no contêiner e publica evidências quando falhar.

## Legado

`bootstrap-wp-env.mjs` antigo (`wp-env`) — use `npm run bootstrap:legacy` apenas durante migração do Plano 003.
