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



## 3. Compre por categoria



| Item | Onde editar |

| --- | --- |

| Título da seção | Bloco **Título** (“Compre por categoria”). |

| Nome das categorias | **Produtos → Categorias** → editar cada categoria. |

| Imagem da categoria | Mesma tela → **Miniatura** → Biblioteca de mídia + texto alternativo. |

| Quais categorias aparecem | **Produtos → Categorias** → marque **Exibir na navegação**. Ordem: campo **Ordem comercial**. |



---



## 4. Destaques / Mais vendidos

**Onde:** seção com classe `petshop-featured-section` na Home (título + link visíveis no editor).

| Item | Onde editar |
| --- | --- |
| Título da seção | Bloco **Título** no topo da seção |
| Link “Ver todos” | Clique no link ao lado do título → painel lateral → URL |
| Produtos (imagem, nome, preço) | **Produtos** → editar cada produto |
| Quantidade na grade | Shortcode interno `[petshop_featured_products_grid …]` (atributos `limit`, `columns`) |

A seção **some por completo** se não houver produtos para exibir.

---

## 5. Kits e conjuntos

**Onde:** seção `petshop-kits-section` na Home.

| Item | Onde editar |
| --- | --- |
| Título, intro e link “Ver todos” | Blocos **Título**, **Parágrafo** e link no cabeçalho da seção |
| Produtos exibidos | Categoria **Conjuntos** e produtos vinculados |
| Categoria da grade | Shortcode `[petshop_kits_section_grid …]` → atributo `category` |

A seção **some por completo** se não houver produtos publicados na categoria.

---

## 6. Coleção da estação

**Onde:** seção `petshop-seasonal-section` na Home.

| Item | Onde editar |
| --- | --- |
| Título e link “Ver todos” | Blocos no cabeçalho da seção (`petshop-section-head`) |
| Produtos | **Produtos → Categorias** → **Categoria sazonal** + **Exibir na navegação** |
| Destino padrão do link | Edite a URL do link no bloco; várias categorias sazonais usam filtro `petshop_categories` |

---

## 7. Seleção para banho e tosa

**Onde:** seção `petshop-professional-section` na Home.

| Item | Onde editar |
| --- | --- |
| Título, parágrafo introdutório e link “Ver todos” | Blocos no cabeçalho e parágrafo introdutório da seção |
| Produtos / categorias | Shortcode `[petshop_product_showcase_grid …]` → atributo `category` |
| Destino padrão do “Ver todos” | URL do link no cabeçalho; várias categorias usam filtro `petshop_categories` |

---

## 8. Badges nos cards (promoção e mais pedido)



| Badge | Origem |

| --- | --- |

| “Economize X%” | Preço regular + preço promocional válidos no produto (**Produtos → Editar produto → Dados do produto**). |

| “Mais pedido” | Vendas reais registradas pelo WooCommerce (`total_sales` ≥ 5). Não é editável manualmente. |



Sem promoção ou vendas suficientes, o badge correspondente **não aparece**.



---



## 9. Avaliações



Exibidas automaticamente a partir de **avaliações aprovadas** nos produtos (**Produtos → Avaliações**). Sem avaliações reais, a seção não aparece.



---



## 10. Banner de atendimento (final)

**Onde:** bloco **Imagem** dentro do grupo `petshop-support-banner`, no final da Home — igual ao hero, editável direto no Gutenberg.

| Item | Como editar |
| --- | --- |
| Imagem | Clique no bloco **Imagem** → **Substituir** → Biblioteca de mídia |
| Link de destino | Com a imagem selecionada → painel lateral → **Link** → informe a URL (ex.: WhatsApp) |
| Texto alternativo | Painel lateral da imagem → **Texto alternativo** |

O banner é a imagem inteira clicável. Não use o shortcode `[petshop_support_banner]` na Home — ele foi substituído por blocos nativos para permitir edição visual.



---



## Referência rápida



| Tipo de conteúdo | Painel |

| --- | --- |

| Textos e imagens do hero, benefícios e seções editoriais | **Páginas → Home** (Gutenberg) |

| Título, intro e “Ver todos” das vitrines | Cabeçalho Gutenberg de cada seção (`petshop-section-head`) |

| Imagens e nomes de categorias | **Produtos → Categorias** |

| Imagens, preços e nomes de produtos | **Produtos** |

| Título alternativo de destaques (sem vendas) | **Personalizar → Conteúdo da loja** |

| Banner de atendimento da Home | **Páginas → Home** (bloco Imagem no grupo `petshop-support-banner`) |

| Logo | **Personalizar → Identidade do site** |

