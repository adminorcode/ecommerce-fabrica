# Plano 008 — Suite de testes automatizados

**Status:** Em andamento
**Data:** 2026-07-31  
**Dependências:** [006-infraestrutura-ci-e-documentacao.md](./006-infraestrutura-ci-e-documentacao.md)  
**Branch:** `008-suite-de-testes-automatizados`  
**Origem:** review técnica — ausência de PHPUnit, Playwright acoplado ao host Windows

## 1. Objetivo

Introduzir testes automatizados reproduzíveis: **PHPUnit** para lógica do plugin, **Playwright containerizado** para gates visuais, biblioteca compartilhada para scripts browser e integração com CI e `run-gates`.

## 2. Resultado esperado

- `phpunit.xml.dist`, `tests/` e dependências dev no `petshop-core` (ou raiz, documentado);
- `docker compose --profile test run --rm test-runner` executa PHPUnit com código atual;
- Playwright roda via serviço `node` no Compose, sem path hardcoded de Chromium no host;
- helpers `scripts/lib/browser-helpers.mjs` e `scripts/lib/assert.php` eliminam duplicação;
- CI estendido: PHPUnit + opcional workflow manual para browser gates;
- cobertura mínima documentada para: filtros de catálogo, SKU exato, sanitização de query string, persistência de migração;
- gates browser para PDP e minicarrinho (adicionar ao carrinho) além dos existentes.

## 3. Contexto

Hoje existem scripts WP-CLI e `.mjs` valiosos (`validate-005-*`, `test-*-persistence.php`), mas:

- `test-runner.sh` falha intencionalmente sem PHPUnit;
- browser tests usam `createRequire('playwright')` no host e path `C:/Users/lucas/...`;
- perfil `test` pode usar plugin desatualizado em relação ao Watch do dev;
- não há testes para `applyCatalogCategoryFilter`, `filterExactSkuSearch`, `selectedCatalogCategorySlugs`.

## 4. Etapas

### Etapa 1 — PHPUnit bootstrap

1. Adicionar `require-dev`: `phpunit/phpunit`, scaffold WP (Brain Monkey ou WP test suite via Composer — escolher abordagem mínima documentada).
2. Criar `phpunit.xml.dist` e `tests/bootstrap.php`.
3. Testes iniciais (unitários, sem DB quando possível):
   - parsing/sanitização de `petshop_categories` na query string;
   - construção de cláusula SKU em `filterExactSkuSearch`;
   - normalização de slugs em `selectedCatalogCategorySlugs`.
4. Testes de integração opcionais via WP test environment no profile `test`.

**Gate:** `test-runner` exit 0 com ≥ 5 testes passando.

### Etapa 2 — Sincronização do profile `test`

1. Garantir que `petshop-core` e `petshop-theme` no profile `test` reflitam o worktree (mount, rebuild step no CI, ou copy no `test-init`).
2. Documentar em `AI_BOOTSTRAP.md`.

**Gate:** alteração local no plugin visível ao PHPUnit no profile `test` sem rebuild manual obscuro.

### Etapa 3 — Playwright no contêiner

1. Adicionar `playwright` a `devDependencies`; Dockerfile ou step no serviço `node` instala browsers.
2. Remover paths hardcoded dos `.mjs`; usar `PETSHOP_BASE_URL` (default `http://wordpress:80` interno ou `http://host.docker.internal:8888`).
3. Montar `scripts/` no serviço `node`.
4. Documentar: `docker compose --profile tools run --rm node node scripts/validate-005-session-01-browser.mjs`.

**Gate:** três scripts browser existentes passam via contêiner `node` em ambiente limpo.

### Etapa 4 — Bibliotecas compartilhadas

1. Extrair `contrast`/`luminance`/`normalize` para `scripts/lib/browser-helpers.mjs`.
2. Extrair asserts comuns para `scripts/lib/assert.php` ou padronizar saída WP-CLI nos validators.
3. Atualizar imports nos scripts existentes.

**Gate:** nenhuma duplicação de `luminance` entre os três `.mjs`.

### Etapa 5 — Novos gates automatizados

1. `validate-005-pdp-browser.mjs`: preço visível, CTA compra, aviso administrável, HTTP 200.
2. `validate-005-cart-browser.mjs`: adicionar produto, contador minicarrinho incrementa.
3. Integrar flags `--pdp` e `--cart` em `run-gates` (Plano 006).

**Gate:** gates novos documentados em `Plans/008-TESTING.md` e passando no ambiente demo.

### Etapa 6 — CI

1. Estender workflow do Plano 006 com job `test-runner` (PHPUnit).
2. Workflow `browser-gates.yml` manual (`workflow_dispatch`) ou nightly com Playwright no `node`.

**Gate:** PR quebra se PHPUnit falhar; browser workflow documentado para release/storefront.

## 5. Fora do escopo

- Cobertura 100% do `StorefrontExperience`;
- testes E2E de checkout com gateway real;
- testes de carga;
- refatoração estrutural do plugin (Plano 007) — apenas testes compatíveis com código atual ou pequenos hooks de teste.

## 6. Critérios de aceite

- [x] PHPUnit configurado e executável via profile `test`
- [x] ≥ 5 testes unitários/integração para lógica de catálogo/SKU
- [x] Playwright roda no contêiner `node` sem path de host
- [x] Helpers compartilhados extraídos
- [x] Gates PDP e carrinho adicionados
- [x] CI configurada para executar PHPUnit em PR (aguarda primeira execução remota)
- [x] `Plans/008-TESTING.md` com matriz rota × script × comando
- [x] Scripts legados de persistência continuam passando

## 7. Documentação

Criar `Plans/008-TESTING.md` espelhando formato de `005-TESTING.md`: pré-requisitos, comandos Docker, variáveis, interpretação de falhas, screenshots opcionais.

## 8. Evidências obrigatórias

- saída de `test-runner` e `run-gates --browser`;
- link de CI verde;
- lista de arquivos de teste adicionados.
