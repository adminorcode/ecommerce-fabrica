# Evidências de teste — Plano 010

**Plano:** [010-layout-secoes-produto-home.md](./010-layout-secoes-produto-home.md)  
**Data de abertura:** 2026-08-02

## Ledger de execução

| Sessão | Evidência | Tentativa 1 | Tentativa 2 | Estado |
| --- | --- | --- | --- | --- |
| 01 — cards compactos | screenshots + carrinho teclado | — | — | pendente |
| 02 — cabeçalho unificado | screenshots desktop/mobile | — | — | pendente |
| 03 — shortcodes Home | `validate-010-session-03-persistence.php` | — | — | pendente |
| 03 — layout responsivo | `validate-010-session-03-browser.mjs` | — | — | pendente |
| 04 — badges condicionais | screenshots promo / sem promo | — | — | pendente |
| 05 — guia + STATUS | revisão manual | — | — | pendente |

## Baseline (preencher na Sessão 01)

| Medida | Desktop 1280 | Mobile 390 |
| --- | ---: | ---: |
| status HTTP Home | — | — |
| colunas visíveis (grade 4) | — | — |
| seções com cabeçalho “Ver todos” | — | — |
| overflow horizontal | — | — |
| erros de página | — | — |

Capturas sugeridas:

- `.local/evidence/010/session-01/baseline-desktop.png`
- `.local/evidence/010/session-01/baseline-mobile.png`

## Matriz de cards (Sessão 01)

| Cenário | Promoção | Review | Badge | Esperado |
| --- | --- | --- | --- | --- |
| Completo | sim | sim | economia | preço de/por + estrelas + badge |
| Mínimo | não | não | nenhum | sem buraco vertical |
| Título longo | — | — | — | 2 linhas + CTA alinhado |

## Persistência editorial (Sessão 03)

1. Alterar título e `cta` de `[petshop_kits_section]` na Home.
2. Salvar e verificar front.
3. Executar reprovisionamento / bump de versão do storefront.
4. Confirmar que alteração manual permanece.

## Seções vazias (Sessão 03)

| Seção | Ação | Esperado |
| --- | --- | --- |
| Kits | zerar produtos em Conjuntos | seção inteira ausente, sem `<h2>` |
| Sazonal | remover flag sazonal | seção ausente |
| Reviews | — | fora do escopo 010 |
