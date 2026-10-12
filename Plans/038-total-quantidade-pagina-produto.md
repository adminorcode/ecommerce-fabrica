# Plano 038 — Total por quantidade na página de produto

**Status:** Concluído
**Data:** 2026-09-27
**Branch sugerida:** `038-total-quantidade-pagina-produto`
**Dependências:** Ticket 037 / PR #10 para cobertura da parte de carrinho; [039-quantidade-carrinho-apos-frete.md](./039-quantidade-carrinho-apos-frete.md) para preservar estabilidade de quantidade após CEP.
**Origem:** Ticket 038 — Na página de produto, alterar a quantidade do item não exibe o valor total. Descrição disponível: dentro da página do produto, ao multiplicar a quantidade, um campo de valor total deve ser adicionado sendo calculado como valor do item × quantidade do item. O mesmo ocorre no carrinho.

## 1. Objetivo

Na página individual do produto, exibir um valor total reativo correspondente ao preço atual oficial do WooCommerce multiplicado pela quantidade selecionada.

O escopo deste plano é a PDP. A parte de carrinho não recebe novo campo nem novo cálculo neste ticket porque o Cart Block já exibe subtotal/total oficiais, quantidade, line total e cart total convergem pela Store API, e o Ticket 037 validou atualização sem refresh.

## 2. Escopo obrigatório

- PDP de produto simples.
- PDP de produto variável.
- Preço promocional.
- Preço da variação atualmente selecionada.
- Quantidade nativa do formulário WooCommerce.
- Respeito a min/max/step nativos do input de quantidade.
- Formatação monetária compatível com WooCommerce.
- Atualização imediata quando a quantidade mudar.
- Atualização quando `found_variation` fornecer nova variação/preço.
- Ocultar ou não calcular total antes de uma variação válida.
- Implementação progressiva sem copiar template WooCommerce.

## 3. Fonte de verdade

- O preço atual vem do WooCommerce.
- O preço de produto simples, produto variável e promoção deve usar dados oficiais disponibilizados pelo WooCommerce, não texto visual parseado como fonte primária.
- O Ticket 038 não inventa cálculo de preço do carrinho.

## 4. Carrinho

O Ticket 038 não criará novo campo, subtotal customizado ou cálculo paralelo no Cart Block.

O comportamento citado no carrinho já é fornecido pelo Cart Block/Store API e foi validado no Ticket 037:

- quantidade do item;
- line total;
- cart total;
- atualização sem refresh.

A implementação da PDP deve preservar esse comportamento e não alterar os contratos dos Tickets 037 e 039.

## 5. Fora de escopo

- Cart Block customizado.
- Mini-cart customizado.
- Alteração do Ticket 037.
- Alteração do Ticket 039.
- Cálculo manual `unit price × quantity` no carrinho.
- Mudança em WooCommerce, Blocksy ou plugins de terceiros.
- Alterações em cards/listagem do Ticket 031.
- Copiar templates do WooCommerce.

## 6. Critérios de aceite

- [x] Produto simples mostra valor total inicial correto na PDP.
- [x] Alterar quantidade atualiza imediatamente o valor total.
- [x] Produto variável só apresenta total válido após seleção de variação válida.
- [x] Trocar a variação atualiza o total com o preço daquela variação.
- [x] Sale price utiliza o preço atual efetivo do WooCommerce.
- [x] Quantidade respeita min/max/step do input nativo.
- [x] Valor é apresentado com formatação monetária correta.
- [x] Alteração não exige refresh.
- [x] Nenhum template WooCommerce é copiado.
- [x] Nenhum cálculo paralelo é criado no carrinho.
- [x] Cart Block/Store API permanecem sem regressão.

## 7. Arquivos e componentes prováveis

Estes componentes foram identificados no diagnóstico, mas a implementação deve alterar somente o que se demonstrar necessário:

- `wp-content/plugins/petshop-core/includes/WooCommerce/ProductDetails.php`;
- `wp-content/plugins/petshop-core/assets/js/product-experience.js`;
- `wp-content/themes/petshop-theme/style.css`;
- gate focado do Ticket 038 em `scripts/`.

## 8. Testes mínimos planejados

- Validação focada de produto simples na PDP.
- Validação focada de produto variável na PDP, incluindo troca de variação.
- Validação de preço promocional usando preço efetivo do WooCommerce.
- Validação de min/max/step do input nativo de quantidade.
- Validação de formatação monetária.
- Regressão mínima de carrinho quando a implementação tocar qualquer asset global que possa afetar Cart Block, mini-cart, Ticket 037 ou Ticket 039.

## 9. Riscos

| Risco | Mitigação |
|---|---|
| Calcular preço a partir de texto visual | Usar dados oficiais do WooCommerce expostos para a PDP |
| Exibir total incorreto antes da variação | Ocultar ou manter estado neutro até `found_variation` válido |
| Quebrar min/max/step nativos | Ler e respeitar o input de quantidade do WooCommerce |
| Criar conflito com 037/039 | Não alterar Cart Block, mini-cart, scripts de quantidade do carrinho ou Store API |
| Criar conflito com 031 | Não alterar cards/listagem neste plano |

## 10. Critério de conclusão

O plano só pode ser concluído quando a PDP exibir um total por quantidade correto e reativo para produtos simples, variáveis e promocionais, sem copiar templates WooCommerce e sem criar qualquer cálculo paralelo no carrinho.

## 11. Evidências desta implementação

### Arquivos alterados

- `wp-content/plugins/petshop-core/includes/WooCommerce/ProductDetails.php`: adiciona o campo de total por quantidade na PDP, usando `wc_get_price_to_display()` para produtos não variáveis e `variation.display_price` via evento `found_variation` para produtos variáveis; expõe configuração monetária oficial do WooCommerce ao JS.
- `wp-content/plugins/petshop-core/assets/js/product-experience.js`: recalcula o total da PDP ao mudar quantidade, selecionar variação ou resetar variação, sem refresh e sem parse manual de texto de preço.
- `wp-content/themes/petshop-theme/style.css`: adiciona apresentação visual do total por quantidade na PDP.
- `scripts/setup-038-pdp-total-fixtures.php`: cria fixtures focadas para produto simples, promocional e variável.
- `scripts/validate-038-pdp-quantity-total-browser.mjs`: adiciona gate focado de browser para PDP e regressão leve do carrinho via Store API/Cart Block.

### Validação executada

- `node --check scripts/validate-038-pdp-quantity-total-browser.mjs`: passou.
- `node --check wp-content/plugins/petshop-core/assets/js/product-experience.js`: passou.
- `git diff --check`: passou, com avisos de normalização LF/CRLF nos arquivos existentes de produção.
- PHP lint de `wp-content/plugins/petshop-core/includes/WooCommerce/ProductDetails.php`: passou.
- PHP lint de `scripts/setup-038-pdp-total-fixtures.php`: passou.
- Carregamento do WordPress pelo PHP CLI: passou.
- Fixtures determinísticas do Ticket 038: passaram.
- Gate browser `scripts/validate-038-pdp-quantity-total-browser.mjs`: passou com exit 0.

### Cobertura runtime comprovada

- PDP de produto simples validada com quantidade 1 e quantidade maior que 1.
- Atualização do total por quantidade validada sem refresh.
- Produto promocional validado usando sale price/preço efetivo do WooCommerce.
- Produto variável validado antes da seleção, após seleção, com troca de variação e reset de variação.
- `min`, `max` e `step` do input nativo de quantidade preservados.
- Formatação monetária validada segundo a configuração real do WooCommerce.
- Regressão leve do carrinho validada via Store API/Cart Block.

Resultado final do gate:

```json
{
  "ok": true,
  "pdp": "total por quantidade validado para produto simples, promocional e variável",
  "cart": "regressao leve Store API/Cart Block validada sem calculo paralelo"
}
```

### Relação com Tickets 037 e 039

- O Ticket 037 continua responsável pelo comportamento de atualização do carrinho.
- O Ticket 039 continua responsável pela estabilidade de quantidade após CEP.
- O Ticket 038 não criou cálculo paralelo para o carrinho.
