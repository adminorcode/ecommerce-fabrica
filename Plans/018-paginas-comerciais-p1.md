# Plano 018 - Paginas comerciais P1

**Status:** Concluido

**Data:** 2026-08-11

**Branch sugerida:** `018-paginas-comerciais-p1`

**Dependencias:** [016-vitrine-produtos-gutenberg.md](./016-vitrine-produtos-gutenberg.md) concluido.

**Relacao com o Plano 017:** o Plano 018 nao depende da conclusao integral do [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) para criar, revisar ou publicar paginas comerciais com produtos reais, desde que o escopo nao prometa go-live completo da loja. O Plano 017 continua sendo gate para publicacao operacional completa quando houver compra real ponta a ponta com pagamento, frete, emails, politicas, monitoramento, backup e acessibilidade manual validados.

**Origem:** `Orcode_Requisitos_Website_Loja_Pet_v2.pdf`, secoes 3, 5, 12, 14.1, 14.2, 16.4, 17.2, 18.1, 19 e 20; alinhamento de produto em 2026-08-12.

## 1. Objetivo

Criar as primeiras paginas comerciais P1 para compradores, com foco em **Animal Republik** e **Produtos premium**.

Essas paginas nao serao fluxos paralelos nem landing pages isoladas. Elas combinam conteudo editorial editavel no Gutenberg com vitrines de produtos reais usando o bloco `petshop/product-grid` ja utilizado na Home. O cliente final edita textos, imagens, CTAs e selecao de produtos no painel, mas nao precisa criar novas paginas desse tipo sem suporte tecnico.

## 2. Decisoes fechadas

- O corte inicial do plano cobre **Animal Republik** e **Premium**.
- **Por Raca fica fora deste plano** para evitar recomendacao indevida de tamanho e reduzir escopo.
- Eventos Pet, Bandanas/Adesivos e Capas de Chuva permanecem como evolucoes posteriores ou rascunhos, caso haja material aprovado, sem bloquear a entrega inicial.
- As paginas serao uma mistura de conteudo Gutenberg e vitrine de produtos.
- A vitrine de produtos deve reutilizar `petshop/product-grid`, exibindo ate 20 itens por categoria e delegando filtros/paginacao para a loja via link "Ver tudo".
- Animal Republik e fornecedor oficial; a pagina deve ter tratamento editorial proprio e hero editavel.
- Premium tambem deve receber pagina propria, com curadoria visual e produtos selecionados.
- Links dessas paginas devem entrar na navegacao principal ou em uma evolucao da navbar, conforme a solucao de menu definida na implementacao.
- Nenhum texto, imagem, CTA ou relacao de produto deve ficar hardcoded em PHP, CSS ou JavaScript.
- O cliente final edita paginas existentes; criacao de novas paginas comerciais fica fora do escopo.

## 3. Modelo editorial

Cada pagina publicada deve conter, no minimo:

| Area | Regra |
| --- | --- |
| Hero | Gutenberg editavel; imagem substituivel pela Biblioteca de midia; alt editavel. |
| Intro | Titulo, texto curto e CTA editaveis no canvas. |
| Vitrine | Bloco `petshop/product-grid`, com produtos escolhidos manualmente pelo cliente. |
| Prova/contexto | Texto ou bloco editorial explicando a colecao sem promessa nao validada. |
| Navegacao | Link na navbar, menu agrupador ou entrada contextual, sem pagina orfa. |

O layout deve favorecer compra: conteudo editorial suficiente para orientar, mas preco, produto e acesso a PDP devem continuar visiveis rapidamente.

## 4. Referencia visual e midia inicial

### Animal Republik

Pesquisa rapida em 2026-08-12 identificou a presenca publica da Animal Republik como marca de **cosmeticos pet** com foco em cuidados profissionais e diarios. Referencias encontradas:

- Instagram Animal Republik: `https://www.instagram.com/animalrepublik/`
- Facebook Animal Republik: `https://www.facebook.com/animalrepublik/`
- Exemplo de produto em marketplace: `https://www.mercadolivre.com.br/shampoo-de-nutricao-e-reconstrucao-pet-animal-republik-1l/up/MLBU2649551065`

Enquanto o fornecedor/cliente nao entregar banner oficial, usar imagem temporaria coerente com banho, tosa, cosmeticos pet ou cuidado profissional. A imagem deve ser importada para a Biblioteca de midia, sem depender de hotlink externo, e marcada como substituivel pelo cliente.

Fontes aceitaveis para placeholder com licenca de uso livre/comercial, a validar no momento da implementacao:

- Unsplash - dog shampoo: `https://unsplash.com/s/photos/dog-shampoo`
- Unsplash - pet grooming: `https://unsplash.com/s/photos/pet-grooming`
- Pexels - dog grooming: `https://www.pexels.com/search/dog%20grooming/`
- Pexels - dog shampoo: `https://www.pexels.com/search/dog%20shampoo/`

Nao usar logo, slogan, banner oficial ou fotografia de produto do fornecedor sem aprovacao. Placeholder nao pode sugerir parceria visual oficial alem do texto editavel informado pelo cliente.

### Premium

Premium deve usar imagem de produto, acabamento, embalagem, detalhe de material ou composicao de catalogo. Evitar imagem generica escura ou aspiracional que nao mostre produto.

## 5. Inventario de conteudo administravel

| Rota | Conteudo e midia | Origem de edicao | Estado inicial |
| --- | --- | --- | --- |
| `/animal-republik/` | hero, titulo, intro, imagem, CTA, texto sobre fornecedor oficial, produtos selecionados | Pagina Gutenberg; Biblioteca de midia; bloco `petshop/product-grid` | Publicavel com placeholder apenas se texto deixar claro que imagem e temporaria/editavel e produtos reais estiverem selecionados. |
| `/premium/` ou `/produtos-premium/` | hero/intro, criterios de curadoria, imagens, CTA e produtos premium | Pagina Gutenberg; Biblioteca de midia; bloco `petshop/product-grid` | Publicavel quando houver produtos reais escolhidos. |
| Navbar | links para Animal Republik e Premium, ou agrupador comercial | Aparencia -> Menus/editor equivalente | Implementar sem menu fixo em PHP. |
| Home | entrada opcional para paginas publicadas | Blocos Gutenberg/`petshop/home-campaigns` | Somente se nao degradar foco de compra da Home. |

## 6. Arquitetura

- Reutilizar o bloco `petshop/product-grid` para todas as vitrines.
- Produtos relacionados serao definidos pelas categorias WooCommerce **Animal Republik** e **Premium**; o cliente edita a relacao no cadastro do produto/categoria e a pagina mostra ate 20 itens.
- O link "Ver tudo" de cada pagina deve levar para a loja filtrada pela categoria correspondente, preservando filtros e paginacao do catalogo.
- Provisionamento pode criar paginas e markup inicial, mas deve preservar qualquer edicao posterior.
- Slugs, titulos e links de menu devem ser reconciliados sem sobrescrever labels alterados pelo cliente.
- Validacao deve falhar se uma pagina publicada nao tiver caminho de navegacao, CTA ou produtos ativos quando a proposta for venda.
- Paginas nao publicadas podem existir como rascunho, mas nao devem aparecer na navbar.

## 7. Sessoes de implementacao

### Sessao 01 - Base Gutenberg e navegacao

- [x] Definir slugs finais de Animal Republik e Premium.
- [x] Criar padrao Gutenberg comum para paginas comerciais com hero, intro, CTA e vitrine.
- [x] Reutilizar `petshop/product-grid` com categoria WooCommerce e limite de ate 20 produtos.
- [x] Evoluir a navbar para incluir Animal Republik e Premium, diretamente ou em agrupador comercial.
- [x] Garantir que paginas em rascunho nao entrem no menu.

**Gate verificavel**

- [x] Cliente edita texto, imagem, alt, CTA e produtos relacionados no painel.
- [x] Reprovisionamento nao sobrescreve conteudo editado.
- [x] Nenhuma pagina publicada fica orfa.

### Sessao 02 - Animal Republik

- [x] Criar pagina `/animal-republik/` com hero editavel.
- [x] Registrar Animal Republik como fornecedor oficial em texto editavel.
- [x] Usar imagem temporaria coerente com cosmeticos pet/cuidados profissionais quando nao houver banner oficial.
- [x] Selecionar produtos reais via `petshop/product-grid`.
- [x] Preparar substituicao posterior de logo/banner/fotos pelo cliente, sem codigo.

**Gate verificavel**

- [x] A pagina nao usa material de marca nao aprovado como se fosse oficial.
- [x] Produtos Animal Republik levam para PDPs WooCommerce comuns.
- [x] Hero, imagem, alt e copy sao editaveis em Gutenberg.

### Sessao 03 - Premium

- [x] Criar pagina `/premium/` ou `/produtos-premium/`, conforme slug aprovado.
- [x] Definir criterio editorial de premium sem alegacoes sensiveis nao comprovadas.
- [x] Selecionar produtos premium pela categoria **Premium**, com vitrine curta e lista completa no catalogo filtrado.
- [x] Exibir CTA para compra sem esconder preco, variacoes ou acesso a PDP.

**Gate verificavel**

- [x] Pagina Premium tem conteudo proprio alem da grade de produto.
- [x] Produtos selecionados estao ativos e compraveis.
- [x] Nenhuma alegacao como exclusividade, resistencia ou conforto e publicada sem fonte aprovada.

### Sessao 04 - Regressao, SEO e documentacao

- [x] Validar mobile, teclado, foco, contraste, overflow e console.
- [x] Validar breadcrumbs, canonical, titulos e sitemap das paginas publicadas.
- [x] Atualizar guia administrativo com como editar hero, imagens, CTA e vitrine.
- [x] Registrar o fluxo para substituir o banner placeholder por material aprovado.

**Gate verificavel**

- [x] Animal Republik e Premium aparecem na navegacao definida.
- [x] Paginas publicadas possuem metadados, conteudo proprio e produtos reais.
- [x] Home nao vira sequencia excessiva de banners.

## 8. Fora de escopo

- Por Raca.
- Criacao pelo cliente de novas paginas comerciais semelhantes.
- Implementar editor visual, uploads, arquivos privados e fila de producao, cobertos pelo Plano 012.
- Implementar pagamentos, frete real, politicas e backup de publicacao, cobertos pelo Plano 017.
- Publicar materiais oficiais Animal Republik sem aprovacao do cliente/fornecedor.
- Criar fluxo de checkout, carrinho ou PDP paralelo.

## 9. Criterios de aceite globais

- [x] Animal Republik e Premium possuem paginas comerciais editaveis no Gutenberg.
- [x] Cada pagina publicada possui hero/intro, CTA, caminho de navegacao, metadados e vitrine com produtos ativos.
- [x] Textos, imagens, alt, CTAs e produtos relacionados sao administraveis no WordPress.
- [x] Navbar ou menu equivalente leva as paginas publicadas sem hardcode.
- [x] Nenhuma alegacao sensivel e publicada sem evidencia ou aprovacao comercial registrada.
- [x] Produtos usam catalogo, PDP, carrinho e checkout comuns.
- [x] Mobile, teclado, foco, contraste e ausencia de overflow foram verificados.
- [x] Reprovisionamento nao sobrescreve edicoes editoriais.

## 11. Evidencias de conclusao

- `npm run validate` aprovado em 2026-08-15, incluindo `validate-018-commercial-pages.php`.
- `npm run test` aprovado em 2026-08-15 com 15 testes e 26 assertions.
- Browser gate `validate-018-commercial-pages-browser.mjs` aprovado em mobile 390, tablet 768 e desktop 1440.
- Screenshots salvos em `.local/evidence/018/`.
- SEO local verificado: `/animal-republik/` e `/premium/` possuem title, canonical, breadcrumb unico e aparecem em `/wp-sitemap-posts-page-1.xml`.

## 10. Criterio de conclusao

O Plano 018 so podera ser concluido quando Animal Republik e Premium estiverem publicadas ou formalmente bloqueadas como rascunho, com conteudo editavel no Gutenberg, vitrines alimentadas por produtos reais selecionados pelo cliente, navegacao coerente e nenhum texto, imagem, CTA, relacao de produto ou promessa comercial dependendo de alteracao de codigo.
