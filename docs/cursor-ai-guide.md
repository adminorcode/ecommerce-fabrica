# Guia Cursor AI — ecommerce-petshop

Como o time (e agentes) aproveitam rules, skills e validações deste repositório.

## Visão geral

| Camada | Local | Quando entra |
|--------|-------|--------------|
| **Rules** | `.cursor/rules/*.mdc` | Contexto persistente (automático por escopo) |
| **Skills do projeto** | `.cursor/skills/` | Workflows e revisões (automático ou `/nome`) |
| **Skills WordPress** | `.agents/skills/` | Padrões WP oficiais (automático ou `/nome`) |
| **Tickets / ClickUp** | `.cursor/CLICKUP_USAGE_RULE.md` | Lista Autelie, 1 tarefa por plano |
| **Planos** | `Plans/` | Escopo, gates e critérios de aceite |
| **Scripts** | `scripts/` | Validação PHP e browser |

## Rules (sempre consultar)

### `project.mdc` — `alwaysApply: true`

Regras globais: arquitetura plugin/tema, conteúdo administrável, Docker, PHP 8.3, o que não editar (WC core, Blocksy).

**Use quando:** qualquer implementação no repo.

### `ecommerce-ui-ux.mdc` — arquivos do storefront

Ativa ao editar `petshop-theme`, `petshop-core` ou `Plans/`.

**Use quando:** header, Home, catálogo, cards, PDP, CSS, filtros, conversão, acessibilidade.

## Skills do projeto (`.cursor/skills/`)

### `/write-kanban-tickets`

Cria ou revisa tickets com motivação, critérios de aceite e contexto técnico. Usar junto com `.cursor/CLICKUP_USAGE_RULE.md`.

**Invoque quando:** criar ticket local + ClickUp, ou enriquecer um pedido solto.

### `/implement-feature-ticket`

Entrega um ticket ou plano de ponta a ponta: entendimento, enriquecimento, branch, código, revisão, testes e handoff.

### `/pr-to-clickup` e `/merge-and-close-ticket`

Vinculam PR ao ticket e fecham o ticket após merge autorizado. Só com pedido explícito.

### `/docker-compose-watch-build`

Sobe e atualiza a stack com `docker compose up --watch --build` (imagens + sync de `petshop-core` e `petshop-theme`).

**Invoque quando:** iniciar o ambiente, após editar plugin/tema/Docker, antes de validar, ou quando o contêiner estiver com código antigo.

### `/petshop-workflow`

Workflow completo do repositório: leitura de planos, Docker/WP-CLI, migrações, persistência editorial, scripts de validação, gates de sessão.

**Invoque quando:** iniciar plano, continuar 005, dúvida “onde colocar este código?”.

### `/ecommerce-design-review`

Checklist de UI/UX antes de fechar sessão visual. Referencia `references/design-tokens.md` (certo vs errado).

**Invoque quando:** terminar alteração de interface; antes de marcar checkbox do plano.

## Skills WordPress (`.agents/skills/`)

Instaladas de [WordPress/agent-skills](https://github.com/WordPress/agent-skills):

| Skill | Para quê neste repo |
|-------|---------------------|
| `/wordpress-router` | Classificar repo antes de agir |
| `/wp-project-triage` | Detectar plugin, tema, tooling |
| `/wp-plugin-development` | `petshop-core`: hooks, segurança, migrações |
| `/wp-wpcli-and-ops` | Automação WP-CLI (via Docker) |
| `/wp-block-development` | Blocos Gutenberg próprios (futuro) |

## Skill genérica de componentes

### `/ui-design-brain`

Padrões de 60+ componentes UI (modal, toast, form, nav). **Adaptar** aos tokens do petshop — não usar paleta SaaS genérica.

**Invoque quando:** criar novo componente de interface, empty state, drawer de filtro, etc.

## Fluxos recomendados

### Criar ticket (local + ClickUp)

1. `/write-kanban-tickets` + `.cursor/CLICKUP_USAGE_RULE.md`
2. Enriquecer `Plans/{numero}-{slug}.md` (escopo, aceite, fora de escopo)
3. Criar **uma** tarefa na lista Autelie; reutilizar se o plano já existir
4. Gravar ID/URL ClickUp no plano

### Implementar feature do plano

1. Ler `Plans/STATUS.md` + plano da branch
2. `/implement-feature-ticket` e `/petshop-workflow`
3. Codar no escopo (plugin vs tema)
4. Validar com scripts do plano
5. Se UI: `/ecommerce-design-review`
6. Atualizar checkboxes e `STATUS.md` só com evidência

### Ajuste visual rápido (CSS/header)

1. Rule `ecommerce-ui-ux` entra automaticamente
2. Usar tokens de `style.css` — ver `references/design-tokens.md`
3. Screenshots 390 + 1440
4. `node scripts/validate-005-*-browser.mjs` se existir para a sessão

### Novo hook WooCommerce no plugin

1. `/wp-plugin-development` + rule `project.mdc`
2. Classe em `Petshop\Core\`, `bootstrap()`, sanitização/escape
3. `docker compose ... wp eval-file scripts/...`

### Dúvida de arquitetura

1. `/wordpress-router` → `/wp-project-triage`
2. `/petshop-workflow` para limites deste repo

## Comandos úteis

```powershell
# Desenvolvimento (imagens + sync contínuo)
docker compose up --watch --build

# WP-CLI
docker compose --profile tools run --rm --no-deps cli wp plugin list

# Smoke PHP (provisiona + validators)
npm run validate

# Validação individual
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/validate-005-session-01.php

# Validação browser (contêiner Node)
docker compose --profile tools run --rm node node /workspace/scripts/validate-005-session-01-browser.mjs
```

## Invocação manual no Cursor

- Digite `/` no Agent chat + nome da skill (`/petshop-workflow`)
- Ou `@` para anexar skill como contexto

Skills com campo `paths` só aparecem quando arquivos correspondentes estão em foco.

## O que não instalar

- **navarroido/Woocommerce-skill** — operação da loja via REST, não desenvolvimento
- **woocommerce/agent-skills** — Abilities API; fora do escopo atual

## Manutenção

- Novas convenções de UI → atualizar `ecommerce-ui-ux.mdc` e `design-tokens.md`
- Novo plano com gates visuais → referenciar scripts em `petshop-workflow`
- Atualizar skills WP: `npx skills add WordPress/agent-skills --skill <nome>`

## Arquivos de lock

`skills-lock.json` registra versões instaladas via `npx skills add` — commitar junto com `.agents/` e `.cursor/skills/`.
