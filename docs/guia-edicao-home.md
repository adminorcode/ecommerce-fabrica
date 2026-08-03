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

| Item | Onde editar |
| --- | --- |
| Produtos (imagem, nome, preço) | **Produtos** → editar cada produto. |
| Título “Destaques da loja” | **Aparência → Personalizar → Conteúdo da loja → Título da seção de destaques (sem vendas reais)**. |
| Título “Mais vendidos” | Aparece automaticamente quando há vendas reais no WooCommerce. |

---

## 5. Kits e conjuntos

| Item | Onde editar |
| --- | --- |
| Produtos exibidos | Categoria **Conjuntos** em **Produtos → Categorias** e produtos vinculados a ela. |
| Título, texto introdutório e botão | Bloco **Shortcode** `[petshop_kits_section …]` — edite os atributos `title`, `intro` e `cta`. |

---

## 6. Coleção da estação

| Item | Onde editar |
| --- | --- |
| Título da seção | Bloco **Título** (“Coleção da estação”). |
| Produtos | **Produtos → Categorias** → marque **Categoria sazonal** e **Exibir na navegação** nas categorias desejadas. A seção some se não houver produtos. |

---

## 7. Seleção para banho e tosa

| Item | Onde editar |
| --- | --- |
| Título e parágrafo introdutório | Blocos de texto no Gutenberg. |
| Produtos | **Produtos** nas categorias Adesivos, Gravatas e Laços (ou altere o shortcode `[products … category="…"]`). |

---

## 8. Avaliações

Exibidas automaticamente a partir de **avaliações aprovadas** nos produtos (**Produtos → Avaliações**). Sem avaliações reais, a seção não aparece.

---

## 9. Chamada de atendimento (final)

| Item | Onde editar |
| --- | --- |
| Título, texto e botão | Blocos de **Título**, **Parágrafo** e **Botão** no Gutenberg. |
| Página de destino do botão | URL do botão ou conteúdo da página **Atendimento** em **Páginas**. |

---

## Referência rápida

| Tipo de conteúdo | Painel |
| --- | --- |
| Textos e imagens do hero, benefícios e seções editoriais | **Páginas → Home** (Gutenberg) |
| Imagens e nomes de categorias | **Produtos → Categorias** |
| Imagens, preços e nomes de produtos | **Produtos** |
| Título alternativo de destaques (sem vendas) | **Personalizar → Conteúdo da loja** |
| Logo | **Personalizar → Identidade do site** |
