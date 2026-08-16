# Guia de edição — Home



Acesso: **Painel WordPress → Páginas → Home** (página definida como inicial em *Configurações → Leitura*).



Salve e visualize a loja após cada alteração. O conteúdo editado no painel **não é sobrescrito** por atualizações do sistema.



---



## 1. Hero (topo da página)



**Onde:** bloco **Capa** (`petshop-hero`), primeiro bloco da Home.



| Item | Como editar |

| --- | --- |

| Imagem de fundo | Selecione o bloco → **Substituir** → Biblioteca de mídia. Prefira formato horizontal (proporção ~2,4:1 a 3,3:1). |

| Texto alternativo da imagem | Painel lateral do bloco → **Texto alternativo**. |

| Eyebrow, título (H1), parágrafo | Edite os blocos de texto dentro da capa. |

| Botões e links | Bloco **Botões** → edite rótulo e URL de cada botão. |



---



## 2. Faixa de benefícios



**Onde:** grupo `petshop-benefits`, logo abaixo do hero.



Três colunas com ícone, título e texto de apoio. Edite cada bloco interno (`petshop-benefits__item`) na Home:



| Item | Título (editável) | Texto de apoio (editável) |

| --- | --- | --- |

| Entrega | Pronta entrega | Produtos à pronta entrega |

| Volume | Condições para volume | Preços especiais para pet shops |

| Frete | Frete para todo o Brasil | Envio rápido e seguro |



**Ícones:** cada item inclui um bloco **Imagem** (`petshop-benefits__icon`). Clique nele → **Substituir** ou **Biblioteca de mídia** para enviar um SVG/PNG próprio. Enquanto nenhuma imagem estiver selecionada, o tema exibe o ícone padrão (cor teal).



Títulos e textos de apoio são blocos **Parágrafo** dentro do grupo `petshop-benefits__copy` de cada item.



---



## 3. Banners de campanha



**Onde:** bloco **Banners de campanha** (`petshop/home-campaigns`), entre a faixa de benefícios e a seção **Compre por categoria**.



| Item | Como editar |
| --- | --- |
| Inserir a faixa | No editor da Home → **+** → categoria **Petshop** → **Banners de campanha** |
| Adicionar campanha | Dentro do bloco → **Adicionar banner** |
| Tipo de campanha | Bloco-filho **Banner de campanha** → painel lateral → **Tipo de campanha** |
| Imagem desktop | Bloco-filho **Banner de campanha** → painel lateral → **Imagem desktop** |
| Imagem mobile | Mesmo bloco → **Imagem mobile** (opcional; se vazia, usa a desktop) |
| Texto alternativo | Painel lateral → **Texto alternativo contextual** |
| Copy editorial | Modo **Campanha editorial** → campos de eyebrow, título, texto, benefício e CTA no canvas ou painel lateral |
| Link de destino | Painel lateral → campo de URL (produto, categoria, página ou link externo). Em campanha editorial, é o destino do CTA |
| Ordem | Arraste os blocos-filho no editor |
| Remover campanha | Selecione o bloco-filho → **Opções** → **Remover** |
| Rótulo interno | Painel lateral → **Rótulo interno** (somente para identificar no editor) |



Use **Arte final** quando a peça já vier fechada com texto incorporado. Nesse caso, cadastre imagem desktop, imagem mobile quando a arte horizontal não servir no celular, texto alternativo contextual e link.

Use **Campanha editorial** para campanhas recorrentes. A imagem vira apoio visual e a oferta fica em texto real no Gutenberg: eyebrow, título, texto, benefício, rótulo do CTA e destino. Essa modalidade é a recomendada quando a equipe precisa atualizar copy, SEO, acessibilidade ou CTA sem criar nova arte.

Com **uma** campanha completa, a loja exibe um banner estático. Com **duas ou mais**, aparecem setas e indicadores para navegação manual — sem troca automática.



Campanhas incompletas não aparecem na loja. Se nenhuma campanha estiver válida, a faixa inteira **some** (sem espaço vazio).



---



## 4. Compre por categoria



| Item | Onde editar |

| --- | --- |

| Título da seção | Bloco **Título** (“Compre por categoria”). |

| Nome das categorias | **Produtos → Categorias** → editar cada categoria. |

| Ícone da vitrine (Home) | Mesma tela → **Ícone da vitrine** → escolha na galeria outline (teal). |

| Imagem da categoria (página da categoria) | Mesma tela → **Miniatura** → Biblioteca de mídia + texto alternativo. |

| Quais categorias aparecem | **Produtos → Categorias** → marque **Exibir na navegação**. Ordem: campo **Ordem comercial**. |



A Home usa ícones compactos da galeria do projeto. No desktop, ao passar o mouse (ou focar com teclado) em uma categoria com produtos, aparece uma prévia com até 3 itens — sem overlay. A **Miniatura** WooCommerce continua valendo para a página da própria categoria.



---



## 5. Vitrines de produtos

**Onde:** cada seção de produtos da Home mantém título, texto e link em blocos nativos. A grade é o bloco **Vitrine de produtos** (`petshop/product-grid`) dentro da mesma seção.

| Item | Onde editar |
| --- | --- |
| Título, texto introdutório e link “Ver todos” | Blocos **Título**, **Parágrafo** e link/botão no cabeçalho da seção |
| Modo da grade | Selecione **Vitrine de produtos** → painel lateral → **Modo de seleção** |
| Produtos específicos | Modo **Seleção manual** → busque por nome ou SKU, adicione, remova e reordene |
| Categorias | Modo **Por categoria** → busque categorias por nome |
| Mais vendidos | Modo **Mais vendidos** usa vendas reais do WooCommerce |
| Coleção sazonal | Modo **Coleção sazonal** usa categorias marcadas como **Categoria sazonal** e **Exibir na navegação** |
| Quantidade e colunas | Painel lateral do bloco **Vitrine de produtos** |
| Imagem, nome, preço, estoque e categoria dos cards | **Produtos** e **Produtos → Categorias** |

Uma vitrine sem produtos válidos não aparece na loja. A seção externa também pode sumir quando não houver nenhum card, sem deixar espaço vazio.

---

## 6. Kits e conjuntos

**Onde:** seção `petshop-kits-section` na Home.

| Item | Onde editar |
| --- | --- |
| Título, intro e link “Ver todos” | Blocos **Título**, **Parágrafo** e link no cabeçalho da seção |
| Produtos exibidos | Categoria **Conjuntos** e produtos vinculados |
| Categoria da grade | Bloco **Vitrine de produtos** em modo **Por categoria** |

A seção **some por completo** se não houver produtos publicados na categoria.

---

## 7. Coleção da estação

**Onde:** seção `petshop-seasonal-section` na Home.

| Item | Onde editar |
| --- | --- |
| Título e link “Ver todos” | Blocos no cabeçalho da seção (`petshop-section-head`) |
| Produtos | **Produtos → Categorias** → **Categoria sazonal** + **Exibir na navegação** |
| Destino padrão do link | Edite a URL do link no bloco; várias categorias sazonais usam filtro `petshop_categories` |

---

## 8. Seleção para banho e tosa

**Onde:** seção `petshop-professional-section` na Home.

| Item | Onde editar |
| --- | --- |
| Título, parágrafo introdutório e link “Ver todos” | Blocos no cabeçalho e parágrafo introdutório da seção |
| Produtos / categorias | Bloco **Vitrine de produtos** em modo **Seleção manual** ou **Por categoria** |
| Destino padrão do “Ver todos” | URL do link no cabeçalho; várias categorias usam filtro `petshop_categories` |

---

## 9. Páginas comerciais P1

**Onde:** **Páginas → Animal Republik** e **Páginas → Produtos premium**.

| Item | Onde editar |
| --- | --- |
| Hero, título, texto, CTA e contexto editorial | Blocos nativos da própria página, no Gutenberg |
| Imagem do hero | Selecione o bloco **Capa** → **Substituir** → Biblioteca de mídia |
| Texto alternativo da imagem | Biblioteca de mídia ou painel lateral do bloco de imagem/capa |
| Produtos exibidos em Animal Republik | Produtos publicados na categoria **Animal Republik**; os lançamentos importados também recebem a tag **Lançamentos Animal Republik** |
| Produtos exibidos em Premium | Produtos publicados na categoria **Premium** |
| Ordem, adição e remoção de produtos | Cadastro/categoria do produto em **Produtos**; as vitrines das páginas exibem até 20 itens |
| Link "Ver tudo" | Cabeçalho da vitrine; aponta para **Loja** filtrada por **Animal Republik** ou **Premium**, com filtros e paginação do catálogo |
| Preço, estoque, imagem e compra | Cadastro de cada item em **Produtos** |
| Link na navbar | **Aparência → Menus** → menu ligado a **Navegação principal** |

As imagens iniciais dessas páginas são placeholders gerados para o projeto. Substitua por material aprovado do cliente ou fornecedor antes de usar uma campanha oficial. O sistema não sobrescreve alterações editoriais salvas nessas páginas.

---

## 10. Badges nos cards (promoção e mais pedido)



| Badge | Origem |

| --- | --- |

| “Economize X%” | Preço regular + preço promocional válidos no produto (**Produtos → Editar produto → Dados do produto**). |

| “Mais pedido” | Vendas reais registradas pelo WooCommerce (`total_sales` ≥ 5). Não é editável manualmente. |



Sem promoção ou vendas suficientes, o badge correspondente **não aparece**.



---



## 10. Avaliações



Exibidas automaticamente a partir de **avaliações aprovadas** nos produtos (**Produtos → Avaliações**). Sem avaliações reais, a seção não aparece.



---



## 11. Secao de atendimento (final)

**Onde:** grupo `petshop-support-banner`, no final da Home, editavel direto no Gutenberg.

| Item | Como editar |
| --- | --- |
| Eyebrow, titulo, texto e beneficio | Edite os blocos **Paragrafo** e **Titulo** dentro do grupo. |
| CTA | Bloco **Botoes** -> **Botao**. Edite o rotulo e a URL. Use preferencialmente `https://wa.me/<numero>` com DDI e DDD, sem espacos. |
| Imagem desktop | Bloco **Imagem** com classe `petshop-support-banner__image--desktop` -> **Substituir** -> Biblioteca de midia. Tamanho utilizado/sugerido: 1920 x 640 px. |
| Imagem mobile | Bloco **Imagem** com classe `petshop-support-banner__image--mobile` -> **Substituir** -> Biblioteca de midia. Tamanho utilizado/sugerido: 1080 x 1350 px. |
| Texto alternativo | Painel lateral de cada imagem -> **Texto alternativo**. |

A imagem e apenas apoio visual; nao coloque texto, telefone, preco, CTA ou logo do WhatsApp dentro da arte. O conteudo deve continuar legivel sem a foto.

Alturas esperadas no storefront:

| Breakpoint | Midia exibida | Proporcao renderizada |
| --- | --- | --- |
| Desktop amplo | Desktop | 3:1 dentro do painel de midia. |
| Tablet | Desktop | 3:1, com a secao empilhada para evitar painel estreito. |
| Mobile (< 768 px) | Mobile | 4:5. |

Nao use imagem desktop com area vazia reservada para texto. A copy fica nos blocos da coluna de conteudo; a imagem deve preencher o quadro.

O provisionamento inicial usa WhatsApp global valido em formato `https://wa.me/<numero>` quando existir. Sem URL valida, o botao aponta para a pagina de atendimento como fallback editavel; depois de configurar o canal oficial, edite o botao na Home para usar o link de WhatsApp.

Se uma atualizacao preservar o banner antigo como imagem unica, substitua manualmente pelo padrao novo: adicione um **Grupo** com classe `petshop-support-banner`, inclua os textos, um unico botao e as duas imagens acima. Nao use o shortcode `[petshop_support_banner]` na Home.



---



## Referência rápida



| Tipo de conteúdo | Painel |

| --- | --- |

| Textos e imagens do hero, benefícios e seções editoriais | **Páginas → Home** (Gutenberg) |

| Título, intro e “Ver todos” das vitrines | Cabeçalho Gutenberg de cada seção (`petshop-section-head`) |

| Seleção, ordem, categoria, quantidade e colunas das vitrines | Bloco **Vitrine de produtos** em cada seção |

| Imagens e nomes de categorias | **Produtos → Categorias** |

| Imagens, preços e nomes de produtos | **Produtos** |

| Título alternativo de destaques (sem vendas) | **Personalizar → Conteúdo da loja** |

| Secao de atendimento da Home | **Páginas → Home** (grupo `petshop-support-banner`, textos, botao e imagens nativas) |

| Logo | **Personalizar → Identidade do site** |
