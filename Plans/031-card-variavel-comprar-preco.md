# Plano 031 — Card variável: Comprar agora e preço único

**Status:** Pronto para staging
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
| Sem estoque na opção | Combinação esgotada não adiciona ao carrinho |
| Acessível | Chips com nome do atributo, estado selecionado e foco em combinação incompleta |

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

- [x] Remover faixa de preço nos cards variáveis.
- [x] Trocar “Ver opções” / “Adicionar ao carrinho” do card por **Comprar agora**.
- [x] Default = variação comprável mais barata.

**Gate**

- [x] Card variável na loja e na Home mostra um preço, nunca `R$ A – R$ B`.
- [x] Nenhum card mostra “Ver opções”.

### Sessão 02 — Chips e compra no card

- [x] Renderizar chips dos atributos de variação.
- [x] Atualizar preço (e imagem da variação quando houver).
- [x] **Comprar agora** adiciona a variação selecionada; minicarrinho atualiza.
- [x] Personalizável continua no fluxo 012.

**Gate**

- [x] Escolher outro tamanho no card muda o preço exibido.
- [x] Comprar agora com combinação válida coloca o item certo no carrinho.
- [x] Combinação incompleta ou esgotada não adiciona.
- [x] Browser gate 031 validou Home, Loja, Busca, Relacionados, Store API real, minicarrinho, combinação incompleta com foco, combinação esgotada, produto 012 e preço promocional “de”.

### Sessão 03 — Handoff

- [x] Gates PHP/browser focados; `Plans/STATUS.md`.

**Gate**

- [x] Home, loja, busca e relacionados seguem o mesmo contrato.
- [x] Reprovisionar não devolve “Ver opções” nem a faixa nos gates focados do Ticket 031.

## 8. Evidências de validação

- Finding HIGH do review corrigido: o JavaScript do card respeita `data-initial-variation-id` e mantém a variação inicial escolhida no PHP até o usuário trocar um chip.
- `scripts/validate-031-product-card.mjs` aprovado: cobre variação inicial wildcard/any e payload de add-to-cart após troca de chip.
- Browser gate 031 isolado aprovado com `failures: []`.
- Superfícies validadas no browser/runtime real: Home, Loja, Busca e Relacionados.
- Fluxos validados no browser/runtime real: `initialVariationId`, wildcard/any, troca de chips, Store API real, minicarrinho, combinação incompleta + foco, combinação esgotada, produto personalizável 012 e preço promocional “de”.
- `npm run validate:changed` aprovado.
- `npm run validate:changed:browser` aprovado.
- `node --check` aprovado para os scripts JS/MJS alterados.
- `git diff --check` aprovado.
- `/review-bugbot` final sem findings bloqueantes.
- O full validate browser (`npm run validate -- --browser`) **não concluiu integralmente** por falha externa anterior aos browser gates: `validate-005-session-01.php` falhou com “Entrada ausente no menu: Kits Economicos”. Essa falha pertence ao gate 005 e não é pendência funcional do Ticket 031.

## 9. Riscos

| Risco | Mitigação |
|---|---|
| Muitos atributos estouram o card | Chips compactos, centralizados e com quebra de linha; o botão permanece no fim do card |
| Add to cart no Blocks | Store API, não clonar formulário clássico frágil |
| Pix do concorrente | Fora de escopo; regra de dado real |

## 10. Correção de layout do card

O card variável ficou esticado: o preço usava `margin-top: auto` e descia para o botão, os chips de 44 px em pílula grudavam na esquerda e o rótulo do atributo aumentava a altura. A referência do Moda Bicho organiza o card de cima para baixo, sem copiar Pix, parcelas, estrelas nem a paleta.

Ordem visual obrigatória:

1. Foto.
2. Título.
3. Preço logo abaixo do título, em um bloco de altura fixa e compacto. Promoção mostra o valor riscado na primeira linha e o preço atual em laranja na segunda. Sem promoção, o preço atual ocupa a mesma segunda linha, para o card não crescer.
4. Chips compactos, com cantos retos, na mesma coluna esquerda do título e do preço. Opção sem estoque continua visível, desabilitada e riscada.
5. **Comprar agora** no fim do card, com respiro nas laterais, alinhado entre os cards da mesma fileira.

O espaço entre foto, título, preço, chips e botão fica curto. O tema pai não pode recolocar a margem de 10 px entre os blocos do card.

Fora desta correção: Pix, parcelas, avaliação inventada e redesign da PDP.
