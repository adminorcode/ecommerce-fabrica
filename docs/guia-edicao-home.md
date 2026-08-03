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

**Onde:** bloco **Shortcode** `[petshop_featured_products …]` na Home.

| Item | Onde editar |
| --- | --- |
| Produtos (imagem, nome, preço) | **Produtos** → editar cada produto. |
| Link “Ver todos” | Atributos `cta` e `cta_url` do shortcode (padrão: loja). |
| Título “Destaques da loja” | **Aparência → Personalizar → Conteúdo da loja → Título da seção de destaques (sem vendas reais)** ou atributo `fallback_title` no shortcode. |
| Título “Mais vendidos” | Aparece automaticamente quando há vendas reais no WooCommerce. |

Exemplo:

```text
[petshop_featured_products limit="4" columns="4" cta="Ver todos →" cta_url=""]
```

---

## 5. Kits e conjuntos

**Onde:** bloco **Shortcode** `[petshop_kits_section …]` na Home.

| Item | Onde editar |
| --- | --- |
| Produtos exibidos | Categoria **Conjuntos** em **Produtos → Categorias** e produtos vinculados a ela. |
| Título, intro, link “Ver todos” | Atributos `title`, `intro`, `cta` e `category` do shortcode. |
| Destino do link | Categoria configurada em `category` (padrão: **Conjuntos**). |

Exemplo:

```text
[petshop_kits_section limit="4" columns="4" title="" intro="" cta="Ver todos →" category="conjuntos"]
```

A seção **some por completo** se não houver produtos publicados na categoria.

---

## 6. Coleção da estação

**Onde:** bloco **Shortcode** `[petshop_seasonal_products …]` na Home.

| Item | Onde editar |
| --- | --- |
| Título e link “Ver todos” | Atributos `title`, `cta` e `cta_url` do shortcode. |
| Produtos | **Produtos → Categorias** → marque **Categoria sazonal** e **Exibir na navegação** nas categorias desejadas. |
| Destino padrão do link | Página **Coleções** ou primeira categoria sazonal (use `cta_url` para sobrescrever). |

Exemplo:

```text
[petshop_seasonal_products limit="4" columns="4" title="Coleção da estação" cta="Ver todos →" cta_url=""]
```

---

## 7. Seleção para banho e tosa

**Onde:** bloco **Shortcode** `[petshop_product_showcase …]` na Home.

| Item | Onde editar |
| --- | --- |
| Título, parágrafo introdutório e link “Ver todos” | Atributos `title`, `intro`, `cta` e `cta_url` do shortcode. |
| Produtos / categorias | Atributo `category` (slugs separados por vírgula) ou produtos nas categorias correspondentes. |

Exemplo:

```text
[petshop_product_showcase limit="4" columns="4" title="Seleção para banho e tosa" intro="" cta="Ver todos →" category="adesivos,gravatas,lacos" orderby="date"]
```

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

## 10. Chamada de atendimento (final)

| Item | Onde editar |
| --- | --- |
| Título, texto e botão | Blocos de **Título**, **Parágrafo** e **Botão** no Gutenberg. |
| Página de destino do botão | URL do botão ou conteúdo da página **Atendimento** em **Páginas**. |

---

## Referência rápida

| Tipo de conteúdo | Painel |
| --- | --- |
| Textos e imagens do hero, benefícios e seções editoriais | **Páginas → Home** (Gutenberg) |
| Título, intro e “Ver todos” das vitrines | Atributos dos shortcodes na Home |
| Imagens e nomes de categorias | **Produtos → Categorias** |
| Imagens, preços e nomes de produtos | **Produtos** |
| Título alternativo de destaques (sem vendas) | **Personalizar → Conteúdo da loja** |
| Logo | **Personalizar → Identidade do site** |
