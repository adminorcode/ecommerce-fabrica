# Plano 040 — Card mobile compacto e CTA Comprar

**Status:** Pendente
**Data:** 2026-10-01
**Branch sugerida:** `040-card-mobile-comprar`
**Dependências:** [031-card-variavel-comprar-preco.md](./031-card-variavel-comprar-preco.md) (chips, preço único, add to cart no card)
**Origem:** mockup mobile de “Destaques da loja” (2026-10-01). No card estreito a legenda do atributo e o respiro interno ocupam a fileira; o CTA ainda diz “Comprar agora”.
**ClickUp:** pendente de criação nesta sessão (conector ClickUp indisponível). Título previsto: `Plano 040 - Card mobile compacto e CTA Comprar`.
**Alcance:** global em todo card de produto das grades (Home `petshop/product-grid`, loja, categoria, busca, relacionados e lista de desejos). A Home em 390 px é a amostra principal. A loja em 390 px é a amostra fora da Home. O rótulo do botão vale também em 1440 px.

## 1. Objetivo

No celular, o card de produto fica mais baixo: a legenda da variação some da tela e o respiro interno diminui. Em qualquer largura, o botão do card deixa de dizer “Comprar agora” e passa a dizer **Comprar**, com o ícone de carrinho que o card já desenha.

User story: como comprador no celular, quero ver tamanho, preço e compra no mesmo card sem uma linha extra de legenda e sem um botão comprido.

## 2. Baseline

| Superfície | Estado | Problema |
|---|---|---|
| Card variável | `<legend class="petshop-product-card__attribute-label">` visível (ex.: “Tamanho”) acima dos chips | Uma linha a mais em card de duas colunas |
| Card, viewport ≤ 767 px | Título `padding: .5rem .75rem 0`; preço e chips com `.75rem` nas laterais; ações `.4rem .75rem .55rem`; foto `padding: .75rem` | Respiro largo para a largura da coluna |
| CTA do card | Texto “Comprar agora” + SVG `.petshop-product-card__cart-icon` já no markup | Rótulo longo; o ícone já existe e permanece |
| Desktop ≥ 768 px | Mesma legenda visível e o mesmo padding do card | Fora do aperto do mockup; só o rótulo muda |
| Gates 031 | Assertam o texto “Comprar agora” | Vão falhar quando o rótulo mudar |

## 3. Escopo comprometido

- Em **toda** grade de card, viewport **≤ 767 px**, a legenda do atributo (`.petshop-product-card__attribute-label`) não ocupa espaço nem é vista. O `<legend>` continua no HTML, fora da tela, para o leitor de tela nomear o grupo de chips.
- Em viewport **≥ 768 px**, essa legenda continua visível, como hoje.
- Em viewport **≤ 767 px**, o respiro interno do card passa aos valores da tabela abaixo, inclusive na lista de desejos (os overrides de `.petshop-wishlist-page__products` não podem manter o padding maior).
- O botão do card, simples, variável e personalizável, em **qualquer** largura, mostra o texto visível **Comprar** e o ícone de carrinho já existente, à esquerda do texto.
- `aria-label` do botão que adiciona ao carrinho é `Comprar`. No personalizável, o texto visível é `Comprar` e o `aria-label` continua `Personalizar {nome do produto}`.
- Produto indisponível continua com o texto **Indisponível**. Estados “Adicionando…”, erro e “adicionado” do 031 permanecem.
- Trocar um chip não devolve o texto “Comprar agora” (`i18n.buyNow` e o fallback em `product-card.js` passam a `Comprar`).
- Os gates do 031 que comparam o rótulo passam a esperar `Comprar`. Preço único, chips, estoque e add to cart do 031 não mudam de comportamento.
- O botão mantém `min-height: 40px`.

### Padding do card em ≤ 767 px

| Bloco | Hoje | Alvo |
|---|---|---|
| Título | `.5rem .75rem 0` | `.35rem .5rem 0` |
| Preço | `0 .75rem` | `0 .5rem` |
| Variações | padding `0 .75rem`, margin-top `.3rem` | padding `0 .5rem`, margin-top `.15rem` |
| Área do botão | `.4rem .75rem .55rem` | `.25rem .5rem .35rem` |
| Foto do card | `.75rem` | `.35rem` |

### Fora de escopo

- Página de produto (PDP): seletor e botão de adicionar ao carrinho.
- Proporção da foto (o `aspect-ratio: 4 / 5` do mobile permanece).
- Gap da grade, tamanho dos chips, coração, header e busca.
- Legenda visível e padding do card em ≥ 768 px.
- Preço, estoque, imagem da variação e add to cart além de não regressar o 031.
- Tornar “Comprar” editável no Gutenberg ou no Personalizar. É rótulo funcional traduzível, no mesmo lugar de “Comprar agora”.

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Legenda | Some só no mobile (≤ 767 px). No desktop permanece visível. No DOM, sempre, para leitor de tela |
| Padding | Diminui só no mobile, nos valores da tabela. Desktop inalterado |
| CTA | Texto visível `Comprar` + ícone de carrinho já existente, no mobile e no desktop, em todas as grades |
| Personalizável | Texto visível `Comprar`; o nome acessível continua “Personalizar {produto}” |
| Botão | Altura mínima permanece 40 px |
| Amostra do gate | Home 390 px; loja 390 px; Home 1440 px só para o rótulo e para confirmar que legenda e padding do desktop não mudaram |

## 5. Conteúdo administrável e textos funcionais

Exceção documentada: o card é dado WooCommerce mais rótulo funcional. Não há bloco editorial novo.

| Item | Rota | Origem |
|---|---|---|
| Texto visível “Comprar” | Todas as grades de card | `__('Comprar', 'petshop-core')` |
| “Indisponível”, “Adicionando…” | Mesmas grades | Tradução funcional já existente |
| Ícone de carrinho | Mesmas grades | SVG decorativo `aria-hidden` no plugin; não é conteúdo |
| Nome da variação (legenda) | Cards variáveis | Atributo do produto WooCommerce; oculto visualmente só em ≤ 767 px |
| Nome, preço, foto, opções (P, M, G…) | Mesmas grades | Produto WooCommerce |

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Rótulo | `class-storefront-product-card.php` e `assets/js/product-card.js` | Trocar “Comprar agora” por “Comprar” no HTML, no `aria-label` comprável e no `buyNow` |
| Legenda e padding | `petshop-theme/style.css` em `@media (max-width: 767px)` | Ocultar a legenda sem tirá-la do DOM; aplicar a tabela de padding, com especificidade que vença a lista de desejos |
| Gates | `scripts/validate-031-product-card.mjs`, `scripts/validate-031-product-card-browser.mjs` e um gate focado deste plano | 031 espera `Comprar`; o gate 040 mede legenda, padding e rótulo em 390 e 1440 |

Não editar WooCommerce, Blocksy nem o markup dos chips.

## 7. Sessões

### Sessão 01 — Rótulo Comprar

- [ ] Texto visível do card, simples, variável e personalizável: `Comprar`.
- [ ] Ícone de carrinho continua ao lado do texto.
- [ ] `buyNow` e o fallback JS usam `Comprar`.
- [ ] Gates 031 que checam o rótulo passam a esperar `Comprar`.

**Gate**

- [ ] Em 390 px e em 1440 px, o botão da Home mostra `Comprar` e o SVG do carrinho.
- [ ] Depois de trocar um chip, o texto continua `Comprar`.

### Sessão 02 — Legenda e padding no mobile

- [ ] Em ≤ 767 px, a legenda do atributo não é visível e não reserva altura.
- [ ] O `<legend>` permanece no DOM para tecnologia assistiva.
- [ ] Em ≥ 768 px, a legenda continua visível.
- [ ] Padding do título, preço, variações, botão e foto segue a tabela em ≤ 767 px, na Home e na lista de desejos.
- [ ] Em ≥ 768 px, esses paddings permanecem os de hoje.

**Gate**

- [ ] Home em 390 px: legenda com caixa visual nula; foto com padding `0.35rem`; título com padding inline `0.5rem`; botão com padding da área `.25rem .5rem .35rem`.
- [ ] Loja em 390 px: o mesmo contrato da Home (amostra fora do exemplo principal).
- [ ] Home em 1440 px: legenda visível; padding horizontal do título continua `0.75rem`.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Esconder a legenda quebra o nome do grupo | Manter o `<legend>` fora da tela, sem `display: none` |
| Overrides da lista de desejos ignoram o padding novo | Seletor do mobile com especificidade igual ou maior |
| Gate 031 continua exigindo “Comprar agora” | Atualizar a asserção do rótulo neste plano |
