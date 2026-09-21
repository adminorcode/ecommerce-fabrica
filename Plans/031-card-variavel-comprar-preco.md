# Plano 031 — Card variável: Comprar agora e preço único

**Status:** Pendente  
**Data:** 2026-08-22  
**Branch sugerida:** `031-card-variavel-comprar-preco`  
**Dependências:** [010-layout-secoes-produto-home.md](./010-layout-secoes-produto-home.md), [016-vitrine-produtos-gutenberg.md](./016-vitrine-produtos-gutenberg.md); [012-personalizador-produtos-e-fila-producao.md](./012-personalizador-produtos-e-fila-producao.md) para não quebrar produto personalizável  
**Origem:** comparação com concorrente (chips 10/50/100 un + “Comprar agora” + um preço) versus o card atual (“Ver opções” + faixa `R$ 18,90 – R$ 44,90` em produto com variação de tamanho).  
**ClickUp:** [86e2xzn0r](https://app.clickup.com/t/86e2xzn0r) — Open  

## 1. Objetivo

No card de produto variável, o cliente escolhe a variação na vitrine, vê **um** preço e clica **Comprar agora** — sem faixa de preço e sem “Ver opções”.

User story: como comprador, quero escolher o tamanho no card, ver o preço daquela opção e comprar, sem abrir a PDP só para descobrir o valor.

## 2. Baseline

| Superfície | Estado | Problema |
|---|---|---|
| Card variável | WooCommerce: `get_price_html()` em faixa; CTA “Select options” / “Ver opções” | Não parece comprável; preço parece incerto |
| Card simples | CTA de adicionar ao carrinho (010) | Rótulo diferente do “Comprar agora” pedido |
| Concorrente (referência) | Chips da variação + um preço + “Comprar agora” | Referência de comportamento, não de paleta nem de Pix inventado |
| Pix / parcelas no card | Projeto proíbe fabricar Pix | A referência do concorrente com “R$ 7,78 no PIX” **não** será copiada sem regra real de gateway |

## 3. Escopo comprometido

- Em **todas** as grades de card (Home `petshop/product-grid`, loja/categoria, busca, relacionados): produto variável **não** mostra faixa `mín – máx`.
- O card mostra **um** preço: o da variação selecionada. A seleção inicial é a variação comprável em estoque de **menor preço**.
- Preço “de” só se essa variação tiver promoção WooCommerce válida.
- Atributos da variação (tamanho, pacote, etc.) aparecem no card como chips selecionáveis. Trocar o chip atualiza preço e, se a variação tiver imagem própria, a foto do card.
- CTA do card, simples e variável: **Comprar agora**. Texto funcional traduzível (`petshop-core` / tema). Sem “Ver opções”.
- **Comprar agora** com variação completa e em estoque adiciona ao carrinho (Store API / AJAX oficial) e atualiza o minicarrinho. Sem variação completa ou sem estoque: o botão não adiciona e o foco vai ao chip/atributo pendente.
- Produto **personalizável** (012) não adiciona pelo card: **Comprar agora** abre o fluxo do personalizador já definido.
- Pix, “no cartão” e parcelas **não** entram neste plano (sem dado real de gateway no card).
- Tokens do tema: laranja só no CTA e no preço em destaque. Sem copiar o azul/vermelho do concorrente.
- Sem fabricar avaliação, desconto ou “Mais vendido”.

### Fora de escopo

- Recriar o card da PDP; o seletor da página de produto permanece o do WooCommerce.
- Preço Pix/parcelado no card.
- Alterar Mercado Pago, frete (027) ou checkout (026/029).
- Wishlist no card.

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| CTA | Sempre `Comprar agora` no card |
| Preço | Um valor; default = menor preço comprável em estoque |
| Chips | Todos os atributos de variação usados para compra (ex.: tamanho). Mais de um atributo: os dois no card; só adiciona com combinação válida |
| Sem estoque na opção | Chip visível e desabilitado; não é selecionável |
| Acessível | chips com `role="group"`, nome do atributo, alvo ≥ 44×44, teclado |

## 5. Conteúdo administrável

| Item | Origem |
|---|---|
| Nome, imagem, preços, atributos | Produto WooCommerce |
| Rótulo “Comprar agora” | Tradução funcional do `petshop-core` |

Sem copy comercial nova em PHP além desse rótulo traduzível.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Preço/CTA | `petshop-core` (loop / product-grid) | Filtrar `woocommerce_variable_price_html`, texto do botão, dados das variações |
| Chips + add | JS no plugin + Store API | Seleção, preço, add to cart |
| CSS | `petshop-theme` | chips, preço único, CTA, 390–1440 |
| Gates | PHP + browser | sem faixa, CTA, add da variação, personalizável intacto |

Não editar WooCommerce/Blocksy. Não copiar template de loop sem necessidade comprovada (preferir hooks).

## 7. Sessões

### Sessão 01 — Preço único e CTA

- [ ] Remover faixa de preço nos cards variáveis.
- [ ] Trocar “Ver opções” / “Adicionar ao carrinho” do card por **Comprar agora**.
- [ ] Default = variação comprável mais barata.

**Gate**

- [ ] Card variável na loja e na Home mostra um preço, nunca `R$ A – R$ B`.
- [ ] Nenhum card mostra “Ver opções”.

### Sessão 02 — Chips e compra no card

- [ ] Renderizar chips dos atributos de variação.
- [ ] Atualizar preço (e imagem da variação quando houver).
- [ ] **Comprar agora** adiciona a variação selecionada; minicarrinho atualiza.
- [ ] Personalizável continua no fluxo 012.

**Gate**

- [ ] Escolher outro tamanho no card muda o preço exibido.
- [ ] Comprar agora com combinação válida coloca o item certo no carrinho.
- [ ] Combinação incompleta ou esgotada não adiciona.
- [ ] 1440 e 390: chips e botão usáveis, sem overflow.

### Sessão 03 — Handoff

- [ ] Gates PHP/browser; `Plans/STATUS.md`.

**Gate**

- [ ] Home, loja e relacionados seguem o mesmo contrato.
- [ ] Reprovisionar não devolve “Ver opções” nem a faixa.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Muitos atributos estouram o card | Chips em wrap; altura de card estável; alvo 44 px |
| Add to cart no Blocks | Store API, não clonar formulário clássico frágil |
| Pix do concorrente | Fora de escopo; regra de dado real |
