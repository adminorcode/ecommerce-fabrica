# Evidências de teste — Plano 009

**Plano:** [009-design-system-acessibilidade-e-checkout.md](./009-design-system-acessibilidade-e-checkout.md)  
**Branch:** `009-design-system-acessibilidade-e-checkout`  
**Data:** 2026-08-02

## Ledger de execução

| Step | Evidência | Tentativa 1 | Tentativa 2 | Estado |
| --- | --- | --- | --- | --- |
| Etapa 1 — hex soltos | `rg #7f3008\|#652505\|#747172 style.css` (somente `:root`) | passed | — | concluído |
| Etapa 2 — `:is()` catálogo | inspeção `style.css` | passed | — | concluído |
| Etapa 2 — nav morto | `.petshop-utility-nav` ausente | passed | — | concluído |
| Etapa 3 — busca a11y | label WC + filtro tema | passed | — | concluído |
| Etapa 4 — cart/checkout | `validate-009-cart-checkout-browser.mjs` | falhou (carrinho vazio) | passed | concluído |
| Gate 005-01 browser | `validate-005-session-01-browser.mjs` | passed | — | concluído |
| Gate 005-02 browser | `validate-005-session-02-browser.mjs` | falhou (H1 tablet) | passed | concluído |
| Gates PHP | `run-gates.sh --skip-provision` | passed | — | concluído |
| Etapa 5 — NVDA/VoiceOver | roteiro 004 manual | — | — | pendente (humano) |
| Etapa 6 — template parts | opcional | — | — | adiado |

## Tokens adicionados

| Token | Valor | Uso |
| --- | --- | --- |
| `--color-brand-orange-hover` | `#7f3008` | hover botões |
| `--color-brand-orange-active` | `#652505` | active botões |
| `--color-disabled` | `#747172` | disabled |
| `--color-error` | `#b42318` | erros / estoque |
| `--color-neutral-border` | `#747172` | inputs e separadores |
| `--shadow-soft` | `0 6px 20px rgb(55 52 53 / 8%)` | sombras leves |

Hex fora de `:root`: zero (gate Etapa 1).

## Cart / checkout (Etapa 4)

| Viewport | Rota | HTTP | CTA primário | Contraste | Overflow |
| --- | --- | ---: | --- | --- | ---: |
| 1440 | `/cart/` | 200 | laranja 44px | AA | 0 |
| 390 | `/cart/` | 200 | laranja 44px | AA | 0 |
| 1440 | `/checkout/` | 200 | laranja 45px | AA | 0 |
| 390 | `/checkout/` | 200 | laranja 45px | AA | 0 |

Capturas: `.local/evidence/009/cart-desktop-1440.png`, `cart-mobile-390.png`, `checkout-desktop-1440.png`, `checkout-mobile-390.png`.

## A11y manual (Etapa 5)

Automatizado (004-TESTING): árvore, foco 3px, 44px targets, labels de busca/minicarrinho, regiões live no cart/checkout.

**Pendência formal:** sessão ouvindo NVDA ou VoiceOver conforme `Plans/004-TESTING.md` — não executável neste ambiente CI/local headless. Permanece responsabilidade do Plano 004 até aceite humano.

## Correções aplicadas durante validação

1. H1 do hero em 768px: `text-wrap: balance` + faixa 768–900px (gate 005-02).
2. Filtro `get_product_search_form`: não confundir `aria-label` do botão com label do campo.
3. Gate 009: provisionar carrinho via Store API (`/wp-json/wc/store/v1/cart/add-item`).
