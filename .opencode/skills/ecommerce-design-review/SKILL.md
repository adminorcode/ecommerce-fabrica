---
name: ecommerce-design-review
description: >-
  Revisa decisões de UI/UX do storefront WooCommerce do petshop antes de concluir
  uma sessão de interface. Use ao implementar ou refinar header, Home, catálogo,
  cards, PDP, filtros, CSS do tema ou gates visuais do Plano 005. Aplica tokens,
  heurísticas de e-commerce, acessibilidade e anti-patterns do projeto.
paths: wp-content/themes/petshop-theme/**, wp-content/plugins/petshop-core/**, Plans/**
---

# E-commerce Design Review

Revisão estruturada de design e usabilidade para o storefront do petshop. Execute **antes** de marcar uma sessão de UI como concluída.

## Quando usar

- após alterar `style.css`, markup de header/hero/catálogo ou hooks que renderizam UI
- antes de fechar gates visuais do Plano 005 (ou planos futuros de interface)
- quando o usuário pedir opinião sobre layout, conversão ou usabilidade

## Entrada necessária

- rota(s) afetada(s): Home, loja, categoria, PDP, checkout
- viewport(s): 390, 768, 1024, 1440 px
- screenshot ou inspeção browser quando possível

## Procedimento

### 1. Alinhamento ao design system

Consultar [.cursor/skills/ecommerce-design-review/references/design-tokens.md](../../../.cursor/skills/ecommerce-design-review/references/design-tokens.md) (certo vs errado) e `wp-content/themes/petshop-theme/style.css`:

- cores via variáveis `:root` (laranja só em CTA/preço/badge)
- raios `--radius-*`, sombra `--shadow-card`, espaçamento `--space-*`
- botões com `min-height: 44px` e `:focus-visible`

Listar qualquer cor/tamanho hardcoded introduzido e sugerir token equivalente.

### 2. Heurísticas de e-commerce (por rota)

| Rota | Perguntas |
|------|-----------|
| Header | Uma busca? Um carrinho? Logo sem duplicata? Menu com 200 OK? |
| Home | Hero institucional > campanha sazonal? CTAs com destino? Benefícios visíveis? |
| Catálogo | Sidebar esquerda desktop / acima mobile? Filtro acessível? Sem overflow? |
| Card | 1:1, preço claro, CTA consistente, badges só com dado real? |
| PDP | Compra acessível cedo no mobile? Aviso informativo sem competir com CTA? |

### 3. Confiança e honestidade comercial

Confirmar que **não** há:

- avaliações, "mais vendido", preço riscado ou Pix sem fonte WooCommerce/config
- imagens genéricas de banco como estado final (Plano 005 Sessão 03)
- texto comercial fixo em código

Elementos sem dado: **ocultar**, não inventar.

### 4. Acessibilidade rápida

- contraste AA em texto e botões primários
- foco visível em controles interativos
- `aria-label` onde rótulo visual falha
- alt específico em imagens de produto/categoria
- navegação por teclado no header e filtros

### 5. Responsividade

Para cada viewport-alvo, verificar:

- sem scroll horizontal involuntário
- toques ≥ 44px
- títulos sem quebra artificial (hero: eixo esquerdo único)
- imagens sem corte de cabeça/produto crítico

### 6. Anti-patterns do projeto

Marcar violações de `.cursor/rules/ecommerce-ui-ux.mdc`:

- redundâncias (busca/carrinho/logo)
- seções vazias ou whitespace morto
- laranja dominando superfícies grandes
- chips no lugar de sidebar quando o plano exige filtro lateral

### 7. Saída da revisão

Produzir relatório curto:

```markdown
## Design review — [rota/sessão]

### Aprovado
- ...

### Ajustes necessários (bloqueiam conclusão)
- [severidade] descrição → arquivo/selector sugerido

### Sugestões (não bloqueiam)
- ...

### Evidência pendente
- screenshot 390/1440, teste teclado, script validate-*
```

Severidades: **crítico** (conversão/a11y/dado falso), **médio** ( inconsistência visual), **baixo** (polish).

## Validação automatizada

Quando existir script para a sessão, executar após ajustes visuais:

```powershell
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/<script>.php
node scripts/validate-005-*-browser.mjs
```

## Referências

- Tokens e exemplos: [.cursor/skills/ecommerce-design-review/references/design-tokens.md](../../../.cursor/skills/ecommerce-design-review/references/design-tokens.md)
- Rule: `.cursor/rules/ecommerce-ui-ux.mdc`
- Plano: `Plans/005-refinamento-comercial-do-storefront.md`
- Workflow: `/petshop-workflow`
- Componentes genéricos (adaptar tokens): `/ui-design-brain`
- Guia do time: `docs/cursor-ai-guide.md`

Base directory for this skill: .cursor/skills/ecommerce-design-review
Relative paths in this skill (e.g., scripts/, reference/) are relative to this base directory.
