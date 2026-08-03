# Evidências de teste — Plano 010

**Plano:** [010-layout-secoes-produto-home.md](./010-layout-secoes-produto-home.md)  
**Data de abertura:** 2026-08-02  
**Última execução:** 2026-08-02

## Ledger de execução

| Sessão | Evidência | Tentativa 1 | Tentativa 2 | Estado |
| --- | --- | --- | --- | --- |
| 01 — cards compactos | screenshots + carrinho teclado | passed | — | concluído |
| 02 — cabeçalho unificado | screenshots desktop/mobile | passed | — | concluído |
| 03 — shortcodes Home | `validate-010-session-03-persistence.php` | failed (migração) | passed | concluído |
| 03 — layout responsivo | `validate-010-session-03-browser.mjs` | blocked (Playwright) | passed | concluído |
| 04 — badges condicionais | lógica condicional + CSS | passed | — | concluído |
| 05 — guia + STATUS | revisão manual | passed | — | concluído |

## Baseline

| Medida | Desktop 1280 | Mobile 390 |
| --- | ---: | ---: |
| status HTTP Home | 200 | 200 |
| colunas visíveis (grade 4) | 4 | 2 |
| seções com cabeçalho “Ver todos” | ≥ 2 vitrines | ≥ 2 vitrines |
| overflow horizontal | 0 | 0 |
| erros de página | 0 | 0 |

Capturas:

- `.local/evidence/010/session-03/desktop-1280.png`
- `.local/evidence/010/session-03/mobile-390.png`

## Validação executada

| Check | Comando | Resultado |
| --- | --- | --- |
| Persistência shortcodes | `docker compose --profile tools run --rm cli wp eval-file scripts/validate-010-session-03-persistence.php` | passed |
| Layout responsivo + carrinho | `PETSHOP_CHROME=… node scripts/validate-010-session-03-browser.mjs` | passed |

**Nota:** browser no host exige `PETSHOP_CHROME` apontando para Chromium do Playwright (mesmo padrão dos gates 005).

## Persistência editorial (Sessão 03)

1. Alterar título e `cta` de `[petshop_kits_section]` na Home — validado via script sentinela.
2. Reprovisionamento (`maybeEnsureStorefront`) preservou alteração manual.
3. Schema Home atualizado para 17 com `[petshop_product_showcase]`.

## Seções vazias (Sessão 03)

| Seção | Ação | Esperado | Resultado |
| --- | --- | --- | --- |
| Kits | categoria inexistente no shortcode | retorno vazio | passed |

## Badges (Sessão 04)

- Limiar **Mais pedido:** `total_sales >= 5` (`StorefrontProductCard::BEST_SELLER_MIN_SALES`).
- **Economize X%:** arredondamento `round()` a partir de preço regular vs promocional.
