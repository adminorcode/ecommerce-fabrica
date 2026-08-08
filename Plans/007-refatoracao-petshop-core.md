# Plano 007 — Refatoração arquitetural do petshop-core

**Status:** Concluído
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

- [x] Nenhuma classe PHP do plugin > 400 linhas (meta; exceção documentada se inevitável)
- [x] PSR-4 autoload em produção
- [x] HomeMigrator com registry único de schemas
- [x] CatalogFilter sem static layout flag frágil
- [x] Customizer ownership unificado
- [x] Activation hook + `wp petshop migrate` documentados
- [x] uninstall.php ou doc de cleanup de options
- [x] Todos validators 004b/005 e persistence tests passando
- [x] `Plans/STATUS.md` atualizado ao concluir

## 8. Validação

Executar sequência completa do Plano 006 `run-gates` + persistence scripts + browser gates após **cada** etapa.

## 9. Evidências obrigatórias

- diagrama ou lista de classes antes/depois;
- diff de linhas por arquivo;
- logs de validators;
- nota de breaking changes (esperado: nenhum para usuário final).

## 10. Execução e evidências — 2026-08-08

### Arquitetura antes/depois

- Antes: `class-storefront-experience.php` concentrava 3.315 linhas e responsabilidades de migração, provisionamento, catálogo, shortcodes, SEO e administração.
- Depois: a fachada compatível tem 133 linhas; os domínios foram distribuídos entre `Migration\HomeMigrator`, `Provisioning\StorefrontProvisioner`, `Storefront\CatalogFilter`, shortcodes/views, `Admin\Customizer`, `Admin\CategoryTermMeta`, `Settings\DefaultSettings`, `Lifecycle`, `Plugin` e `Cli\MigrateCommand`.
- Nenhuma classe PHP resultante ultrapassa 400 linhas. Traits de implementação foram usados para manter os módulos legados extensos separados sem ampliar a API pública.
- O mapa completo de ownership e o inventário de conteúdo administrável estão em [`docs/arquitetura-petshop-core.md`](../docs/arquitetura-petshop-core.md).

### Diff e compatibilidade

- `class-storefront-experience.php`: 3.315 → 133 linhas (redução aproximada de 96%).
- Bootstrap: autoload Composer substituiu os `require_once` manuais; bridges PSR-4 mantêm os nomes públicos legados.
- Customizer: 145 linhas de registro/defaults foram removidas do tema e centralizadas no plugin.
- Breaking changes para usuário final: **nenhum**. Conteúdo Gutenberg, mídia, menus e `theme_mods` continuam administráveis e são preservados em reprovisionamento/uninstall.
- Correções comportamentais deliberadas: filtro de catálogo aplicado também em taxonomias, `tax_query` com relação `AND`, canonicalização sanitizada, persistência integral/monotônica de schemas e menu gerenciado tolerante a itens extras do cliente.

### Validação final

- `npm run validate -- --browser`: aprovado; validators PHP 004b/005, persistência, Home, catálogo (1440/1024/390), PDP e carrinho.
- `npm test`: **12 testes, 17 assertions**, aprovado em PHP 8.3.32.
- `composer validate --strict`: aprovado.
- lint PHP do plugin e tema: aprovado.
- `wp petshop migrate`: aprovado.
- ativação/desativação do plugin: aprovadas sem fatal; URLs públicas restauradas após os gates browser.
- revisão técnica dedicada: dois ciclos; todos os P0/P1 corrigidos e nenhum achado bloqueante remanescente.

### Nota operacional

Os gates browser alteram `home`/`siteurl` apenas durante execução local e serial para resolver o WordPress pela rede interna do Compose. O runner recusa instalações não-loopback, restaura as URLs em `finally`/`trap` e recupera automaticamente uma execução local interrompida na chamada seguinte.
