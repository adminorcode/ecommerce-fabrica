---
name: petshop-workflow
description: >-
  Workflow do repositório ecommerce-petshop (WordPress/WooCommerce + Docker).
  Use ao implementar planos, alterar petshop-core ou petshop-theme, validar
  storefront, migrações versionadas, conteúdo administrável ou scripts em
  scripts/. Complementa .cursor/rules/project.mdc e AI_BOOTSTRAP.md.
---

# Petshop Workflow

Skill de projeto para a loja WordPress/WooCommerce do petshop. Aplique **antes e durante** qualquer implementação neste repositório.

## Quando usar

- implementar ou continuar um plano em `Plans/`
- alterar `wp-content/plugins/petshop-core/` ou `wp-content/themes/petshop-theme/`
- criar migrações, shortcodes, hooks WooCommerce ou ajustes de storefront
- validar persistência editorial ou executar scripts de teste
- decidir onde colocar regra de negócio vs apresentação
- preparar pacote HostGator/cPanel (seguir `preparar-deploy`; nunca zipar `vendor/` do worktree)

## Leitura obrigatória (nesta ordem)

1. `.cursor/rules/project.mdc` — regras globais
2. `.cursor/rules/ecommerce-ui-ux.mdc` — UI/UX do storefront (quando tocar interface)
3. `AI_BOOTSTRAP.md` — Docker, WP-CLI, limites do ambiente
4. `Plans/STATUS.md` — plano ativo e bloqueios
5. O arquivo do plano solicitado (inteiro, incluindo gates e sessões)
6. `Plans/README.md` — se o plano tocar interface ou conteúdo editorial
7. `.cursor/CLICKUP_USAGE_RULE.md` e `write-kanban-tickets` — se o trabalho for criar ou revisar ticket

Não marque checkboxes do plano nem declare conclusão sem evidência de validação.

## Arquitetura (não negociável)

| Responsabilidade | Local |
|---|---|
| Regras de negócio, migrações, hooks WC, shortcodes | `wp-content/plugins/petshop-core/` |
| Apresentação, Customizer, CSS, enqueue de assets | `wp-content/themes/petshop-theme/` |
| Conteúdo de páginas | Gutenberg (Stackable importado) |
| Produtos, categorias, preços, estoque | WooCommerce |
| Menus | Aparência → Menus |
| Textos globais de cabeçalho/rodapé | Customizer (`petshop_store_content`, `petshop_footer`) |

**Proibido:** editar WordPress Core, WooCommerce, Blocksy ou plugins de terceiros; colocar regra de negócio no `functions.php` do tema; instalar plugins sem registro em plano; versionar segredos.

## Ambiente e comandos

O host **não** tem PHP/WP-CLI. Subir ou atualizar a stack: skill
`docker-compose-watch-build`. Comando canônico:

```powershell
docker compose up --watch --build
```

```powershell
# WP-CLI
docker compose --profile tools run --rm --no-deps cli wp <comando>

# Validar versões
docker compose --profile tools run --rm --no-deps cli wp core version
docker compose --profile tools run --rm --no-deps cli wp plugin list
```

URLs locais: loja `http://localhost:8888`, admin `http://localhost:8888/wp-admin`.

Nunca use `docker compose down --volumes` sem autorização explícita.

O volume `wordpress_runtime` **não** é bind mount. Sem `--watch --build` (ou o
one-shot da skill), o contêiner fica com código antigo. Não declare validação
concluída se o gate falhar por código ausente no runtime.

## Padrões de código deste repo

- PHP mínimo **8.3**, `declare(strict_types=1)` em arquivos novos
- PSR-4 no plugin: namespace `Petshop\Core\`, pasta `includes/`
- Classes finais com `bootstrap()` estático registrando hooks
- Migrações versionadas: constante `VERSION`, option de controle, lock contra concorrência, preservar alterações do cliente (hash/versão)
- HPOS: manter compatibilidade declarada em `petshop-core.php`
- Preferir hooks WooCommerce e filtros WP em vez de copiar templates Blocksy/WC
- Sanitizar entradas, escapar saídas, verificar capabilities em operações admin

## Conteúdo administrável

Todo texto comercial/institucional e toda imagem de conteúdo exibida em páginas deve ser editável no painel **sem alterar código**.

- provisionar valores iniciais na primeira instalação, salvar no WordPress, **nunca sobrescrever** edições posteriores
- inventariar por rota: texto/imagem → origem de edição (Gutenberg, mídia, produto, categoria, menu, Customizer)
- testes de persistência: alterar no painel → rerodar migração/reprovisionamento → confirmar que a alteração permanece
- ocultar elementos sem dados reais (sem whitespace residual)
- não fabricar avaliações, vendas, descontos, CNPJ, endereço ou redes sociais

## Fluxo por plano

### Antes de codar

1. Confirmar escopo, critérios de aceite e o que está **fora** do escopo
2. Identificar sessão ativa (planos grandes, ex.: 005, têm sessões com gates)
3. Listar arquivos que serão alterados
4. Para plano novo: branch `<numero>-<nome-do-arquivo-sem-.md>` a partir de `master` (worktree limpo)

### Durante

1. Alterar **somente** arquivos necessários ao escopo da sessão
2. Manter textos comerciais fora de PHP/CSS/JS fixos
3. Registrar migrações com bump de versão quando provisionar conteúdo

### Depois (gate da sessão)

1. **Entregar imagens/runtime** com a skill `docker-compose-watch-build` se plugin, tema ou Docker mudaram
2. Verificar ambiente sobe sem erro fatal (`docker compose logs`)
3. Executar validações do plano (scripts abaixo)
4. Testar fluxo funcional afetado (desktop + mobile quando aplicável)
5. Testar persistência editorial se houver conteúdo administrável
6. Executar `/ecommerce-design-review` ou aplicar `.cursor/rules/ecommerce-ui-ux.mdc` em alterações visuais
7. Atualizar checkboxes do plano **somente** após gate passar
8. Atualizar `Plans/STATUS.md` se o status do plano mudou

Se uma verificação falhar: diagnosticar, corrigir, repetir. Parar e pedir decisão do usuário apenas para bloqueios editoriais/destrutivos após segunda tentativa.

## Scripts de validação

Consulte `scripts/README.md`. Padrão Docker para eval PHP:

```powershell
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/<script>.php
```

Scripts relevantes por plano:

| Plano / área | Scripts |
|---|---|
| 004b vitrine | `validate-004b.php`, `validate-storefront.php`, `test-004b-persistence.php` |
| 005 sessão 01 | `validate-005-session-01.php`, `validate-005-session-01-browser.mjs`, `test-005-session-01-persistence.php` |
| 005 sessão 02 | `validate-005-session-02.php`, `validate-005-session-02-browser.mjs`, `validate-005-session-02-editor.mjs`, `test-005-session-02-*.php` |
| 005 catálogo | `validate-005-catalog-layout-browser.mjs` |

Classes centrais do plugin:

- `Petshop\Core\StorefrontExperience` — migrações storefront, catálogo, shortcodes
- `Petshop\Core\StorefrontCatalog` — taxonomia/categorias demonstrativas

## Stack de interface

- Tema pai: **Blocksy** (não editar)
- Child: `petshop-theme` — header comercial via `wp_body_open`, Customizer, CSS
- Blocos WC nativos (ex.: `woocommerce/mini-cart`) via `do_blocks()`
- Gutenberg + Stackable na Home — não remover Stackable sem verificar dependências
- JS próprio: `petshop-core/assets/js/catalog-filter.js`

## Skills complementares

- `docker-compose-watch-build` — subir/atualizar a stack (`up --watch --build`)

Este repo também inclui skills oficiais. Use quando o trabalho exigir profundidade além desta skill:

- `wordpress-router` / `wp-project-triage` — classificar o repo antes de agir
- `wp-plugin-development` — arquitetura de plugin, hooks, segurança (não empacota HostGator)
- `preparar-deploy` — pacote cPanel/HostGator (`npm run prepare:deploy`)
- `wp-wpcli-and-ops` — automação WP-CLI (adaptar para Docker)
- `wp-block-development` — se criar blocos Gutenberg próprios
- `ui-design-brain` — padrões de componente (modal, form, nav); **adaptar** tokens do petshop

Guia completo: `docs/cursor-ai-guide.md`

## Deploy HostGator / cPanel

Publicar a loja: skill `preparar-deploy` (`npm run prepare:deploy`). O script copia o plugin, remove vendor de desenvolvimento e **regenera** o Composer no pacote com `dump-autoload --no-dev --optimize`.

**Proibido:** zipar ou copiar `wp-content/plugins/petshop-core/vendor` do worktree; apagar `myclabs`/`phpunit` sem regenerar o autoload; rodar `composer dump-autoload --no-dev` no plugin do worktree (quebra o PHPUnit local). Pacote inválido se `autoload_*.php` ainda citar `myclabs`, `phpunit/phpunit` ou `deep-copy`.

## O que não fazer

- commit, push ou PR sem solicitação explícita
- marcar plano/sessão concluído sem validação
- validar/smoke com código antigo no volume — **sempre** `docker compose up --watch --build` (skill `docker-compose-watch-build`) quando plugin/tema mudarem
- bind mount do repo inteiro no contêiner (Compose Watch sync só plugin + tema)
- sobrescrever conteúdo editado pelo cliente em migrações
- implementar Elementor ou editar Blocksy/WooCommerce core
- adicionar `abilities-api-implement` ou skills de operação REST da loja (fora do escopo de desenvolvimento)
- publicar `petshop-core` cujo Composer autoload ainda referencia PHPUnit, myclabs ou deep-copy

## Estado atual (consultar `Plans/STATUS.md` para atualização)

- Consulte `Plans/STATUS.md` — não confie em resumos embutidos nesta skill
- Ambiente Docker: após mudanças no repo, skill `docker-compose-watch-build`

Base directory for this skill: .cursor/skills/petshop-workflow
Relative paths in this skill (e.g., scripts/, reference/) are relative to this base directory.
