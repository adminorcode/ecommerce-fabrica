# Plano 007 — Refatoração arquitetural do petshop-core

**Status:** Pendente  
**Data:** 2026-07-31  
**Dependências:** [006-infraestrutura-ci-e-documentacao.md](./006-infraestrutura-ci-e-documentacao.md); recomendado após baseline do [008-suite-de-testes-automatizados.md](./008-suite-de-testes-automatizados.md) Etapa 1  
**Branch:** `007-refatoracao-petshop-core`  
**Origem:** review — god class ~1.470 linhas, migrações frágeis, PSR-4 não utilizado

## 1. Objetivo

Reduzir acoplamento e risco de regressão em `petshop-core`, dividindo responsabilidades, consolidando migrações versionadas e alinhando ownership de Customizer/conteúdo administrável — **sem alterar comportamento visível** validado pelos planos 004b/005.

## 2. Resultado esperado

- `StorefrontExperience` decomposto em classes coesas sob `Petshop\Core\`;
- autoload Composer ativo; `require_once` manual removido do bootstrap;
- migrador de Home com registry de schemas (7→8→9…) em um único módulo;
- migrações disparadas por activation hook + comando WP-CLI `wp petshop migrate` (além de fallback admin documentado);
- Customizer: registro e consumo na mesma camada; defaults centralizados;
- migrações provisionam **estrutura**, não copy comercial repetível;
- mensagens de erro de migração com código estável + log detalhado;
- `register_deactivation_hook` / `uninstall.php` documentados para options próprias;
- `load_plugin_textdomain` e declare `cart_checkout_blocks` compatibility;
- todos os gates PHP/browser existentes continuam passando.

## 3. Contexto

`class-storefront-experience.php` concentra: migrações Gutenberg, menus, theme mods, renderização de catálogo, shortcodes, SEO, admin de categorias e queries. Hero migration repete blocos lógicos três vezes. Defaults de texto existem em tema, plugin e Customizer.

## 4. Etapas

### Etapa 1 — Bootstrap e autoload

1. `composer install` no plugin; `vendor/autoload.php` em `petshop-core.php`.
2. Remover `require_once` das classes migradas.
3. Registrar bootstrap leve (container ou lista de serviços `::bootstrap()`).

**Gate:** plugin ativa sem fatal; classes carregam via PSR-4.

### Etapa 2 — Extração do catálogo

1. Mover para `Petshop\Core\Storefront\CatalogFilter` (ou equivalente):
   - render sidebar/toolbar;
   - `applyCatalogCategoryFilter`, `canonicalizeCatalogCategoryFilter`;
   - `enqueueCatalogFilterAssets`;
   - estado de layout (eliminar `$catalogLayoutOpen` estático — render único ou buffer).
2. Corrigir guards: alinhar UI (`is_shop() || is_product_taxonomy()`) com query;
   - normalizar `tax_query` com `relation => AND`;
   - canonical SEO com query args sanitizados quando filtros ativos.

**Gate:** `validate-005-catalog-layout-browser.mjs` e filtros OR na URL passam; sem regressão visual.

### Etapa 3 — Migrador de Home

1. Extrair `Petshop\Core\Migration\HomeMigrator` com API:
   - `locateHeroBlock()`, `isManagedHero()`, `replaceHero()`, `currentSchema(): int`.
2. Registry `[7 => callable, 8 => callable, 9 => callable]`.
3. Remover duplicação em `migrateManagedHome`.

**Gate:** `test-005-session-02-migrations.php` passa; persistência editorial intacta.

### Etapa 4 — Provisionamento e menus

1. Extrair `StorefrontProvisioner` (pages, menus, theme mods estruturais).
2. `ensureCommercialMenu` depende explicitamente de versão do catálogo ou falha com código `PETSHOP_MENU_CATEGORIES_MISSING`.
3. Reduzir copy comercial hardcoded; defaults iniciais apenas na primeira criação administrável.

**Gate:** `test-005-session-01-persistence.php` e `test-004b-persistence.php` passam.

### Etapa 5 — Shortcodes, SEO e admin

1. Classes: `Shortcodes`, `SeoMeta`, `CategoryTermMeta` (campos de categoria).
2. Manter hooks públicos equivalentes; deprecar métodos estáticos expostos apenas se necessário com alias temporário.

**Gate:** Home renderiza shortcodes; meta description e campos de categoria funcionam.

### Etapa 6 — Customizer e defaults

1. Mover registro de settings `petshop_*` para plugin (ou módulo `Admin\Customizer`) — tema mantém apenas enqueue/CSS.
2. Array único de defaults versionado (`petshop_defaults()` ou classe `DefaultSettings`).
3. Remover triplicação em `get_theme_mod` fallbacks divergentes.

**Gate:** valores editáveis no Customizer persistem após reprovisionamento; tema não registra settings duplicados.

### Etapa 7 — Ciclo de vida e i18n

1. `register_activation_hook` → schedule migration / flush;
2. `uninstall.php` opcional listando options a remover;
3. `load_plugin_textdomain('petshop-core')`;
4. `FeaturesUtil::declare_compatibility('cart_checkout_blocks', ...)`;
5. Corrigir header `Requires at least` vs PHP 8.3.

**Gate:** ativação/desativação sem fatal; textdomain carregável; sem warnings WC HPOS/blocks.

## 5. Regras transversais

- Comportamento externo idêntico salvo bugs documentados corrigidos na Etapa 2.
- Nenhum texto comercial novo fixo em PHP.
- Commits incrementais por etapa com gates entre elas.
- Não editar WooCommerce, Blocksy ou Core.

## 6. Fora do escopo

- Novas features de storefront (Plano 005 sessões 04–08);
- redesign CSS (Plano 009);
- PHPUnit além do necessário para regressão (Plano 008);
- Abilities API / REST endpoints novos.

## 7. Critérios de aceite

- [ ] Nenhuma classe PHP do plugin > 400 linhas (meta; exceção documentada se inevitável)
- [ ] PSR-4 autoload em produção
- [ ] HomeMigrator com registry único de schemas
- [ ] CatalogFilter sem static layout flag frágil
- [ ] Customizer ownership unificado
- [ ] Activation hook + `wp petshop migrate` documentados
- [ ] uninstall.php ou doc de cleanup de options
- [ ] Todos validators 004b/005 e persistence tests passando
- [ ] `Plans/STATUS.md` atualizado ao concluir

## 8. Validação

Executar sequência completa do Plano 006 `run-gates` + persistence scripts + browser gates após **cada** etapa.

## 9. Evidências obrigatórias

- diagrama ou lista de classes antes/depois;
- diff de linhas por arquivo;
- logs de validators;
- nota de breaking changes (esperado: nenhum para usuário final).
