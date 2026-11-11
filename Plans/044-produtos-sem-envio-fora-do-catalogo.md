# Plano 044 — Produtos sem peso ou dimensão fora do catálogo

**Status:** Pendente  
**Data:** 2026-10-12  
**Branch sugerida:** `044-produtos-sem-envio-fora-do-catalogo`  
**Dependências:** catálogo WooCommerce e campos de envio do produto (peso, comprimento, largura, altura). A cotação de frete continua no [041-integridade-frete-carrinho-checkout.md](./041-integridade-frete-carrinho-checkout.md).  
**Origem:** tarefa ClickUp `44 - Produtos sem peso e medida devem ser ocultos do catálogo`. Confirmado em 2026-10-12: o critério é o envio do WooCommerce. Falta peso ou qualquer uma das três dimensões e o produto sai do catálogo.  
**ClickUp:** [86e31z0xw](https://app.clickup.com/t/86e31z0xw) — Open  

## Por quê

Produto sem peso ou sem dimensão não entra na cotação de frete e hoje continua na vitrine. O comprador deixa de ver e de comprar esse item até o lojista completar o envio.

## Alcance

Global no storefront: loja, categoria, tag de produto, busca, sugestões da busca, grades `petshop/product-grid` e `[products]`, relacionados, upsells, cross-sells, lista de desejos, página do produto, carrinho e checkout.

## Fora de escopo

- Bloquear o salvamento no wp-admin enquanto peso ou dimensão estiverem vazios. O lojista continua gravando o rascunho incompleto e completa o envio depois.
- Preencher peso ou dimensão com valor padrão.
- Alterar a cotação, o pacote ou o checkout de frete do Plano 041.
- Regravar `catalog_visibility`. O filtro é de leitura; a opção escolhida pelo lojista permanece.
- Ocultar o produto na lista de Produtos do wp-admin.
- Exigir peso e dimensão de produto que não precisa de envio (`needs_shipping()` falso: virtual, baixável ou externo).
- ViaCEP. Este plano não monta endereço.
- O campo editorial Medidas (`_petshop_measurements`) e o guia de medidas.

## Critérios de aceite

- Quando um produto simples publicado tem peso, comprimento, largura e altura maiores que zero, a loja, uma categoria, a busca, uma grade da Home e os relacionados exibem o produto.
- Quando falta peso ou qualquer dimensão, com valor vazio ou zero, esse produto some da loja, da categoria, da tag, da busca, das sugestões, das grades, dos relacionados, dos upsells, dos cross-sells e da lista de desejos.
- Quando alguém sem capacidade `edit_post` abre a URL direta desse produto incompleto, a loja responde 404.
- Enquanto o usuário pode editar o produto, a URL direta abre a página.
- Quando o comprador tenta adicionar o produto incompleto ao carrinho, pela página ou pela Store API, a loja recusa e o carrinho não ganha a linha.
- Quando o carrinho já tem a linha e o produto fica incompleto, a leitura seguinte do carrinho e do checkout remove a linha e mostra o aviso «Este produto saiu do carrinho porque o envio está sem peso ou dimensão.»
- Quando um produto variável tem ao menos uma variação comprável com peso e as três dimensões resolvidos maiores que zero, o pai permanece no catálogo e somente essas variações podem ser escolhidas e compradas.
- Quando nenhuma variação comprável resolve os quatro valores, o pai sai do catálogo e a URL pública responde 404.
- Quando a variação está com peso ou dimensão vazios e o pai tem os quatro valores maiores que zero, a variação usa o valor do pai e continua no catálogo.
- Quando a variação grava zero numa dimensão, a loja não herda o valor do pai nessa dimensão e a variação não pode ser comprada.
- Enquanto `needs_shipping()` for falso, o produto permanece no catálogo mesmo sem peso e dimensão.
- O sistema sempre lista o produto incompleto em Produtos no wp-admin e mantém a `catalog_visibility` já gravada.
- O mesmo produto incompleto ausente da loja também está ausente de uma categoria e de uma grade da Home que não seja essa loja.

## Premissas

- Vazio, zero, negativo ou não numérico contam como ausente. Pacote com lado ou peso zero não é cotável.
- Campo de envio vazio na variação usa o valor do pai na leitura do WooCommerce. Zero gravado na variação é valor próprio e não herda.
- Produto agrupado entra no catálogo quando ao menos um filho vendável passa na regra. O pai agrupado não precisa ter peso.

## Validação

- Baseline: suíte PHP do plugin já verde antes da alteração.
- Gate do plano: produto simples completo visível na loja; o mesmo produto com uma dimensão zerada ausente da loja, de outra categoria, da busca, da sugestão Store API `wc/store/v1/products`, de uma grade `petshop/product-grid` e da lista de desejos; URL pública 404; URL de quem edita abre; inclusão no carrinho recusada; linha prévia removida com o aviso; variação herda pai vazio e rejeita zero próprio; produto virtual sem peso continua visível; `catalog_visibility` gravada não muda; lista do wp-admin ainda contém o produto.
- Fechamento: `npm run validate:changed` depois do gate verde.

## Contexto técnico

- Campos WooCommerce: `_weight`, `_length`, `_width`, `_height`. Unidades são as da loja (kg e cm). O plano só exige número maior que zero.
- A decisão de leitura fica no `petshop-core`. Não alterar WooCommerce, Blocksy nem o tema pai.
- Não aplicar o filtro na listagem `edit.php` de produtos.
- Sugestões da busca usam `wc/store/v1/products` em `Storefront/SearchExperience.php`. Grades manuais passam por `ProductGridBlock::isVisibleProduct()`.
- O aviso do carrinho é texto funcional do plugin, domínio `petshop-core`. Não é conteúdo comercial e não entra no Gutenberg nem no Personalizar.
- Não há imagem nova.
