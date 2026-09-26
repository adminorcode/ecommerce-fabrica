# Plano 037 — Atualização automática dos valores do carrinho

**Status:** Concluído
**Data:** 2026-09-21
**Branch sugerida:** `037-atualizacao-automatica-carrinho`
**Dependências:** [039-quantidade-carrinho-apos-frete.md](./039-quantidade-carrinho-apos-frete.md)
**Origem:** Ticket 037 — produtos no carrinho não atualizam automaticamente após alterar a quantidade. ClickUp ID não identificado no repositório/contexto.

## 1. Objetivo

Formalizar a conclusão runtime do Ticket 037 e transformar o aceite em gate de regressão. O carrinho deve atualizar quantidade e valores automaticamente, sem refresh manual, usando os valores oficiais retornados pelo WooCommerce/Store API.

Critério literal do ticket:

> Ao atualizar a quantidade de um item no carrinho, o valor total deve ser atualizado sem que a página precise de refresh.

## 2. Diagnóstico runtime validado

| Superfície | Evidência | Conclusão |
|---|---|---|
| `/carrinho` | Gate 037 isolado aprovado; validações com `woocommerce_tax_display_cart=excl` e `incl` aprovadas; `validate:changed:browser` aprovado | Quantidade, line total e cart total convergiram contra Store API sem refresh |
| Mini-cart | Gate 037 aprovado validando drawer nativo; regressão compartilhada 039 aprovada como visitante | Quantidade e subtotal convergiram contra Store API sem refresh; assets `cart-quantity-*` não carregaram na Home |
| Estado visual intermediário | Durante uma janela assíncrona pode aparecer quantidade nova com valores antigos | Comportamento assíncrono aceito; não haverá cálculo manual nem patch privado |

O Ticket 037 foi concluído sem alteração funcional de produção. A validação específica do ticket passou nos gates pertinentes; a suíte global `npm run validate -- --browser` não foi registrada como aprovada porque parou em falha preexistente/fora do escopo no gate 005 (`validate-005-session-01.php`: "Entrada ausente no menu: Kits Economicos").

## 3. Relação com o Plano 039

O Plano 039 controla a estabilidade da quantidade no Cart Block após CEP/frete:

- quantidade otimista/imediata;
- `0` `update-item` nos primeiros ~800 ms;
- exatamente `1` `update-item` após o debounce;
- header `X-Petshop-Qty-Flush: 1`;
- Store API convergindo para a quantidade pretendida.

O Ticket 037 não altera esse comportamento. O gate 037 prova o aceite funcional e preserva a regressão do 039 no `/carrinho`.

A revisão posterior do Ticket 037 identificou cobertura/regressão compartilhada com o fluxo visitante do gate 039. Por isso, o gate 039 foi exercitado como regressão necessária da revisão do 037. O Ticket 039 não foi reaberto, nenhum código funcional do Ticket 039 foi alterado, e a mudança ficou restrita ao gate de regressão necessário à revisão do 037.

## 4. Escopo comprometido

- Criar documentação formal do Ticket 037.
- Criar gate browser específico para o Ticket 037.
- Validar `/carrinho` com `+` e `-`:
  - quantidade muda sem refresh;
  - Store API converge;
  - line total converge;
  - cart total converge;
  - valores finais exibidos correspondem aos totais oficiais da Store API.
- Validar mini-cart com `+` e `-`:
  - assets do 039 não carregam na Home;
  - quantidade e subtotal convergem automaticamente;
  - não há refresh;
  - valores finais exibidos correspondem à Store API.
- Integrar o gate ao `validate:changed` conforme convenção existente.
- Atualizar `Plans/STATUS.md` após os gates pertinentes passarem.

### Fora de escopo

- Alterar comportamento funcional do carrinho.
- Calcular preço no navegador.
- Calcular `unit_price × quantity`.
- Antecipar `update-item`.
- Duplicar request.
- Monkey patch de `fetch`.
- `MutationObserver`, polling ou hack de DOM.
- Editar WooCommerce, Blocksy ou plugins de terceiros.
- Alterar `cart-quantity-stability.js`, `cart-quantity-guard.js`, `CartQuantityStability.php`, tema ou código produtivo do carrinho.
- Commit, push, PR, merge ou alteração de ClickUp.

## 5. Decisões técnicas

| Tema | Decisão |
|---|---|
| Fonte da verdade | Store API oficial do WooCommerce |
| Valores esperados | Comparar DOM final contra `items[].totals.line_total`, `totals.total_items` e `totals.total_price` |
| Ausência de refresh | Marcador em `window` antes da operação deve persistir após a convergência |
| Produto de teste | Selecionar dinamicamente um produto adicionável que permita quantidade maior que 1 |
| Mini-cart | Validar comportamento nativo; não exigir estado visual de loading |
| `/carrinho` | Validar aceite do 037 e preservar invariantes do 039 |

## 6. Gates

### Gate browser 037

Script: `scripts/validate-037-cart-auto-update-browser.mjs`

- [x] Prepara carrinho com item que aceite `+` e `-`.
- [x] `/carrinho`: testa `+` e `-`, sem reload, com convergência de quantidade, line total e cart total.
- [x] `/carrinho`: confirma `0` `update-item` nos primeiros ~800 ms.
- [x] `/carrinho`: confirma exatamente `1` `update-item` após o debounce.
- [x] `/carrinho`: confirma header `X-Petshop-Qty-Flush: 1`.
- [x] Mini-cart: confirma ausência dos assets `cart-quantity-*` do Plano 039 na Home.
- [x] Mini-cart: testa `+` e `-`, sem reload, com convergência de quantidade e subtotal.
- [x] Não calcula preço esperado a partir de unitário.

### Regressão 039

- [x] `scripts/validate-039-cart-qty.php`
- [x] `scripts/validate-039-cart-qty-browser.mjs` como visitante

## 7. Evidência de conclusão

Validações registradas:

- [x] `git diff --check`
- [x] `node --check scripts/validate-037-cart-auto-update-browser.mjs`
- [x] Gate browser 037 isolado com `PETSHOP_BASE_URL=http://wordpress` e `PETSHOP_CANONICAL_HOST=wordpress`
- [x] Gate browser 037 com `woocommerce_tax_display_cart=excl`
- [x] Gate browser 037 com `woocommerce_tax_display_cart=incl`
- [x] `scripts/validate-039-cart-qty.php` via `validate:changed:browser`
- [x] `scripts/validate-039-cart-qty-browser.mjs` como visitante
- [x] `npm run validate:changed:browser`
- [x] `Plans/STATUS.md` atualizado sem alterar o status do 039

Observação: `npm run validate -- --browser` não foi marcado como aprovado. A execução global parou em falha fora do escopo do Ticket 037 no gate 005 (`validate-005-session-01.php`: "Entrada ausente no menu: Kits Economicos").
