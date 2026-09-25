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

## 2. Diagnóstico runtime

| Superfície | Evidência | Conclusão |
|---|---|---|
| `/carrinho` | A quantidade muda imediatamente; o `update-item` ocorre após o debounce do Plano 039; a Store API retorna o estado correto; linha e total convergem automaticamente | O critério funcional está atendido sem refresh |
| Mini-cart | A quantidade muda no drawer nativo; subtotal converge automaticamente; os assets do Plano 039 não carregam nessa superfície | O critério funcional está atendido sem refresh |
| Estado visual intermediário | Durante uma janela assíncrona pode aparecer quantidade nova com valores antigos | Comportamento assíncrono aceito; não haverá cálculo manual nem patch privado |

O master atual já satisfaz o critério funcional do Ticket 037. A entrega deste plano registra a decisão e cria validação automatizada para impedir regressão.

## 3. Relação com o Plano 039

O Plano 039 controla a estabilidade da quantidade no Cart Block após CEP/frete:

- quantidade otimista/imediata;
- `0` `update-item` nos primeiros ~800 ms;
- exatamente `1` `update-item` após o debounce;
- header `X-Petshop-Qty-Flush: 1`;
- Store API convergindo para a quantidade pretendida.

O Ticket 037 não altera esse comportamento. O gate 037 deve provar o aceite funcional e preservar a regressão do 039 no `/carrinho`.

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
- Atualizar `Plans/STATUS.md` somente após os gates passarem.

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

- [ ] Prepara carrinho com item que aceite `+` e `-`.
- [ ] `/carrinho`: testa `+` e `-`, sem reload, com convergência de quantidade, line total e cart total.
- [ ] `/carrinho`: confirma `0` `update-item` nos primeiros ~800 ms.
- [ ] `/carrinho`: confirma exatamente `1` `update-item` após o debounce.
- [ ] `/carrinho`: confirma header `X-Petshop-Qty-Flush: 1`.
- [ ] Mini-cart: confirma ausência dos assets `cart-quantity-*` do Plano 039 na Home.
- [ ] Mini-cart: testa `+` e `-`, sem reload, com convergência de quantidade e subtotal.
- [ ] Não calcula preço esperado a partir de unitário.

### Regressão 039

- [ ] `scripts/validate-039-cart-qty.php`
- [ ] `scripts/validate-039-cart-qty-browser.mjs`

## 7. Evidência de conclusão

Preencher após validação:

- [ ] `git diff --check`
- [ ] `node --check scripts/validate-037-cart-auto-update-browser.mjs`
- [ ] `node scripts/validate-037-cart-auto-update-browser.mjs`
- [ ] `scripts/validate-039-cart-qty.php`
- [ ] `scripts/validate-039-cart-qty-browser.mjs`
- [ ] `npm run validate:changed`
- [ ] `Plans/STATUS.md` atualizado sem alterar o status do 039
