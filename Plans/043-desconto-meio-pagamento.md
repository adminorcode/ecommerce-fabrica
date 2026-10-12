# Plano 043 — Desconto por meio de pagamento no item

**Status:** Pendente  
**Data:** 2026-10-12  
**Branch sugerida:** `043-desconto-meio-pagamento`  
**Dependências:** Checkout Block e plugin oficial Mercado Pago já em uso (013/017). O retorno à loja após Pix aprovado permanece o do [029-retorno-mercado-pago-pix.md](./029-retorno-mercado-pago-pix.md). O preço principal do card variável permanece o da variação selecionada no [031-card-variavel-comprar-preco.md](./031-card-variavel-comprar-preco.md).  
**Origem:** pedido do lojista (2026-10-12): cadastrar, em cada produto e variação, uma porcentagem de desconto ao pagar por um meio configurável. O primeiro meio é o Pix do Mercado Pago.  
**ClickUp:** [86e3p30ww](https://app.clickup.com/t/86e3p30ww) — Open  

## 1. Objetivo

O lojista informa, no cadastro de cada item, a porcentagem que vale quando o comprador escolhe um meio de pagamento da lista global. O comprador vê esse preço no card e na página do produto e, no checkout, o total cai ao selecionar o meio e volta ao preço cheio se trocar para outro.

User story: como lojista, quero definir o desconto por produto e por variação e escolher quais meios o disparam, para que o comprador pague no Pix o valor anunciado e não ganhe esse desconto no cartão.

## 2. Baseline

| Superfície | Estado | Problema |
|---|---|---|
| Cadastro do produto | Preço regular e promocional WooCommerce | Não há porcentagem por meio de pagamento |
| Variação | Preço próprio da variação | Não herda nem substitui um desconto do pai |
| Card e PDP | Preço WooCommerce; o card variável mostra o preço da variação selecionada (031) | Não existe segundo preço real de meio de pagamento. O 031 proíbe Pix fabricado; este plano só exibe desconto gravado no produto |
| Checkout | Checkout Block. Cobrança atual do Mercado Pago usa `woo-mercado-pago-basic` (Checkout Pro), e o comprador ainda escolhe Pix ou cartão na página do Mercado Pago | A loja fecha um único total antes de saber o meio. Desconto por item não pode entrar nesse fluxo |
| Carrinho | Totais oficiais da Store API | Não reagem a um meio de pagamento com desconto por linha |

## 3. Escopo comprometido

- Produto simples: campo de porcentagem no cadastro. Vazio ou 0 = sem desconto.
- Produto variável: campo no pai e em cada variação. Variação com valor maior que 0 usa o próprio número. Variação vazia ou 0 herda o pai. Se o pai tem porcentagem, toda variação tem desconto.
- A porcentagem aceita de 0 a 100, no máximo duas casas decimais. Valor negativo, acima de 100 ou com mais de duas casas não é gravado.
- A base é o preço do produto que o comprador já veria: promocional, se houver promoção válida; senão o regular. Entram nessa base o preço por quantidade que o WooCommerce já tiver aplicado ao item. Ficam de fora personalização e frete.
- Ordem na linha: preço de venda, depois a porcentagem do meio sobre esse preço, depois o cupom do WooCommerce sobre o preço já reduzido. Personalização permanece no valor cheio, fora dessa base.
- Lista global dos meios que disparam o desconto, escolhida entre os gateways WooCommerce habilitados. A mesma porcentagem do item vale para qualquer meio dessa lista.
- Valor inicial da lista: somente o gateway Pix do plugin oficial Mercado Pago (`woo-mercado-pago-pix`). `woo-mercado-pago-basic` não entra nessa lista inicial. Reprovisionar não desfaz a lista que o lojista tiver gravado depois.
- No checkout da loja o Pix aparece como meio próprio. Com ele selecionado, o total cobrado já inclui o desconto por item e a cobrança segue só nesse Pix. Cartão é outro meio: sem esse desconto.
- Rótulo único da vitrine, editável no painel. Valor inicial: `no Pix`. Salvar vazio grava de novo `no Pix`.
- Card e página do produto, em todas as grades (Home, loja, categoria, busca e relacionados), mostram o preço normal e o preço com o rótulo quando a porcentagem efetiva é maior que 0. Porcentagem efetiva 0 oculta o segundo preço.
- Card e PDP de produto variável, antes de uma variação específica ser o preço do desconto: “a partir de” o menor preço com desconto entre as variações compráveis, usando o texto nativo do WooCommerce. Se todas essas variações tiverem o mesmo preço com desconto, mostrar esse valor único, sem “a partir de”. Na PDP, depois da variação escolhida, o segundo preço é o dessa variação.
- O preço normal do card variável continua o da variação selecionada (031). O “a partir de” do desconto não substitui esse preço.
- Carrinho e checkout mostram o desconto somente com um meio da lista selecionado. Trocar para um meio fora da lista remove o desconto e recalcula o cupom. Minicarrinho não tem meio de pagamento e permanece no preço sem esse desconto.
- Visitante e cliente logado seguem a mesma regra.
- O pedido HPOS grava o total cobrado, a porcentagem efetiva e o valor do desconto em cada linha. Reabrir o pedido no painel mostra esses valores.
- Cálculo em unidades monetárias inteiras, com o arredondamento de preço do WooCommerce. O preço anunciado e o total da linha coincidem.
- Textos comerciais próprios ficam na configuração global. Não há imagem nova.

### Fora de escopo

- Isentar uma variação quando o pai tem porcentagem.
- Porcentagem diferente por meio no mesmo item.
- Desconto sobre personalização ou frete.
- Checkout Pro único (`woo-mercado-pago-basic`) em que Pix e cartão compartilham o mesmo total.
- Gateway próprio ou edição do plugin Mercado Pago, do WooCommerce ou do Blocksy.
- Refazer o retorno à loja do Plano 029.
- Redesenhar e-mail, card do 031 ou minicarrinho.
- Cupom que deixe de combinar com promoção ou com este desconto.

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Onde o comprador escolhe | Meios separados no checkout da loja. Pix selecionado cobra o total com desconto e não abre escolha de cartão nesse pagamento |
| Porcentagem | 0 a 100, até duas casas. Simples vazio/0 = sem desconto. Variação vazia/0 herda o pai. Variação maior que 0 substitui o pai |
| Base | Preço de venda do produto, sem personalização e sem frete |
| Empilhamento | Preço de venda → porcentagem do meio → cupom sobre o que restou do produto |
| Meios | Uma lista global. Uma porcentagem por item para todos os meios da lista |
| Lista vazia | Nenhum desconto e nenhum segundo preço |
| Rótulo | Texto global. Inicial `no Pix` |
| Card variável | Preço normal da variação selecionada; segundo preço “a partir de” o menor desconto comprável |
| Quem vê | Visitante e logado |

Exemplo fechado: produto a R$ 100,00 em promoção, 10% no meio, cupom de 10% e personalização de R$ 20,00. A linha do produto fica R$ 90,00 e, com o cupom, R$ 81,00. A personalização continua R$ 20,00. O frete não muda. O mesmo pedido no cartão, com o Pix fora da seleção, não aplica os 10%.

## 5. Conteúdo administrável

| Rota | Item | Origem |
|---|---|---|
| Card (Home, loja, categoria, busca, relacionados) e PDP | Rótulo do segundo preço, inicial `no Pix` | Configuração global da loja no painel. Não é bloco Gutenberg: o texto se repete em várias rotas |
| Card variável e PDP variável | “A partir de” | Texto nativo do WooCommerce |
| Carrinho e checkout | Nome do meio de pagamento | Título do gateway WooCommerce |
| Cadastro do produto e da variação | Porcentagem | Campos no produto WooCommerce |
| Painel | Lista de meios | A mesma tela global do rótulo, entre gateways habilitados |
| Painel do produto | Aviso de porcentagem recusada | Texto funcional traduzível do `petshop-core` |

Sem imagem. Sem rótulo comercial fixo em PHP, CSS ou JavaScript depois do provisionamento inicial.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Porcentagem | Meta do produto e da variação no `petshop-core` | Sanitizar 0–100 com duas casas; herança pai → variação; capability `manage_woocommerce` e nonce |
| Lista e rótulo | Setting global do `petshop-core` | Provisionar Pix oficial e `no Pix` uma vez; não sobrescrever edição posterior |
| Checkout | Gateway Pix oficial no Checkout Block | Meio separado de `woo-mercado-pago-basic` e do cartão |
| Totais | Carrinho WooCommerce / Store API, compatível com HPOS | Ajustar só o preço do produto quando o meio selecionado está na lista; cupom calcula em seguida; personalização intacta |
| Vitrine | `petshop-core` + CSS do `petshop-theme` | Segundo preço no card e na PDP, em todas as grades |
| Pedido | Meta da linha no HPOS | Porcentagem efetiva e valor descontado |
| Gates | PHP e browser | Simples, variável, segundo card fora da PDP, Pix versus cartão, cupom, personalização, persistência |

Não editar WooCommerce, Blocksy nem o plugin Mercado Pago.

## 7. Sessões

### Sessão 01 — Cadastro e configuração

- [ ] Campo de porcentagem no produto simples, no pai variável e em cada variação, com a regra de herança e a recusa de valor inválido.
- [ ] Tela global com a lista de meios e o rótulo. Inicial: `woo-mercado-pago-pix` e `no Pix`, sem sobrescrever edição posterior.

### Sessão 02 — Cobrança

- [ ] Pix como meio próprio no Checkout Block. Selecionar aplica o desconto por linha; trocar para cartão remove.
- [ ] Cupom incide sobre o produto já descontado. Personalização e frete ficam de fora.
- [ ] Pedido HPOS guarda porcentagem e valor do desconto por linha.

### Sessão 03 — Vitrine

- [ ] Card e PDP mostram o segundo preço com o rótulo quando a porcentagem efetiva é maior que 0.
- [ ] Produto variável: “a partir de” o menor desconto comprável; PDP com variação escolhida mostra o preço daquela variação.
- [ ] Preço normal do card variável do 031 permanece o da variação selecionada.

**Gate**

- [ ] Produto simples a R$ 100,00 com 10% e cupom de 10% cobra R$ 81,00 no Pix e R$ 90,00 no cartão com o mesmo cupom.
- [ ] Variação com 4% usa 4%; variação vazia de pai com 10% usa 10%; variação 0 do mesmo pai usa 10%.
- [ ] Personalização de R$ 20,00 no mesmo item continua R$ 20,00 no Pix e no cartão.
- [ ] Card de categoria, não só a PDP, mostra o rótulo e o preço com desconto. Card variável mantém o preço normal da variação selecionada.
- [ ] Trocar o rótulo e a lista no painel sobrevive a reprovisionamento. Lista vazia some com o segundo preço e com o desconto no checkout.
- [ ] WooCommerce, Blocksy e o plugin Mercado Pago não foram editados.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Checkout Pro continua sendo o único botão e o comprador paga cartão com total de Pix | Exigir o gateway Pix oficial separado; o desconto só existe com um meio da lista selecionado |
| “A partir de” anuncia variação mais barata que a selecionada no card | Manter o preço normal do 031 na variação selecionada e limitar o “a partir de” ao segundo preço |
| Cupom calcula antes do desconto do meio | Aplicar a porcentagem no preço do produto antes do cálculo de cupom do WooCommerce |
| Arredondamento diverge entre vitrine e pedido | Uma função só, em centavos, com arredondamento de preço do WooCommerce |
