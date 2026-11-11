# Design tokens e padrões visuais — Petshop Storefront

Referência rápida para decisões de UI no e-commerce. Tokens canônicos estão em `wp-content/themes/petshop-theme/style.css`.

## Paleta e papéis

| Token | Hex (referência) | Papel |
|-------|------------------|-------|
| `--color-brand-teal-dark` | `#17676a` | Marca, links, títulos institucionais |
| `--color-brand-teal` | `#5bc1c3` | Destaques suaves, hover secundário |
| `--color-brand-orange-dark` | `#9f3e0a` | **CTA primário**, botão “Adicionar ao carrinho” |
| `--color-brand-orange` | `#f37d35` | Preço em destaque, badge promocional |
| `--color-ink` | `#373435` | Texto principal |
| `--color-muted` | `#625f60` | Meta, apoio, labels |
| `--color-surface` | `#ffffff` | Fundo de página e cards |
| `--color-surface-soft` | `#e6e7e9` | Faixas, separadores leves |
| `--color-border` | `#d1d3d4` | Bordas de card e inputs |
| `--color-focus` | `#005fcc` | Anel de foco (`:focus-visible`) |

**Regra de ouro:** laranja = ação ou preço; teal = marca e navegação; neutros = superfície. Não inverter (ex.: header inteiro laranja).

## Escala de espaçamento

| Token | Valor | Uso típico |
|-------|-------|------------|
| `--space-1` | 8px | Gap interno compacto |
| `--space-2` | 12px | Itens de nav, chips |
| `--space-3` | 16px | Padding de card |
| `--space-4` | 24px | Blocos internos |
| `--space-5` | 36px | Subseções |
| `--space-6` | 56px | Respiro entre seções (mobile) |

Desktop entre seções principais da Home: ~**72px** (Plano 005).

## Raios e elevação

| Token | Valor | Uso |
|-------|-------|-----|
| `--radius-sm` | 8px | Inputs, badges pequenos |
| `--radius-md` | 16px | Cards de produto, painéis |
| `--radius-pill` | 999px | Botões primários |
| `--shadow-card` | `0 8px 24px rgb(55 52 53 / 12%)` | Cards — **não** combinar com borda pesada |

## Hierarquia de botões

### Certo

```
[ Comprar mais vendidos ]     ← primário (laranja, pill, min-height 44px)
[ Conhecer kits econômicos ]  ← secundário (outline teal ou link sublinhado)
```

- Um primário por bloco (hero, card, modal).
- Verbo + objeto (`Adicionar ao carrinho`, `Ver destaques da loja`).
- Secundário visualmente mais leve (borda teal, fundo transparente ou texto link).

### Errado

```
[ Saiba mais ]  [ Comprar ]  [ Ver ]   ← três primários competindo
[ CLIQUE AQUI ]                        ← rótulo genérico
[ Submit ]                             ← inglês / sem contexto
Botão laranja + badge laranja + preço laranja no mesmo card sem hierarquia
```

## Card de produto

### Certo

```
┌─────────────────────┐
│   imagem 1:1        │
│   (contain)         │
├─────────────────────┤
│ Título max 2 linhas │
│ R$ 49,90            │  ← preço atual sempre visível
│ [ Ver produto ]     │  ← um CTA
└─────────────────────┘
```

- Proporção **1:1**; `object-fit: contain` se o produto for alto.
- Preço “de” só se `_sale_price` válido no WooCommerce.
- Estrelas / “Mais vendido” **omitidos** quando não houver dado.

### Errado

```
★★★★★ (0 reviews)     ← review fabricada
De R$ 99  Por R$ 49   ← “de” sem promoção real
-30% OFF              ← badge sem regra WC
Mesma foto em 3 categorias diferentes
CTA ausente ou link minúsculo (< 44px)
```

## Header

### Certo (desktop)

```
[ promo fina opcional ]
[ Logo ]  [──── busca produtos ────]  [ Atendimento ] [ Conta ] [ 🛒 2 ]
[ Laços | Bandanas | Adesivos | … ]
```

### Errado

```
[ Logo + nome repetido ]
[ busca ] … [ busca icon ]     ← duplicata
[ Carrinho ] [ mini-cart ]     ← duplicata
Menu com 12 itens de topo sem agrupamento
```

## Hero Home

### Certo

- Ratio desktop **2,4:1 – 3,3:1** full-bleed.
- Eyebrow + H1 + parágrafo + **grupo de 2 CTAs** alinhados à esquerda.
- Campanha sazonal em banner **menor**, abaixo ou lateral.

### Errado

- H1 = “Dia dos Pais” quando o hero deveria ser institucional.
- Fundo laranja/marrom em 100% da viewport.
- CTAs centralizados desalinhados do título.
- Corte da cabeça do pet ou acessório ilegível.

## Catálogo / filtro

### Certo (desktop)

```
┌──────────┬────────────────────────────┐
│ Filtrar  │  Ordenar | 12 produtos      │
│ [search] │  ┌────┐ ┌────┐ ┌────┐       │
│ □ Laços  │  │card│ │card│ │card│       │
│ □ Band.  │  └────┘ └────┘ └────┘       │
└──────────┴────────────────────────────┘
```

### Errado

- Chips horizontais scroll infinito no lugar da sidebar (quando plano exige lateral).
- Checkbox sem `<label>` ou contagem falsa.
- Sidebar que empurra grade para scroll horizontal em 390px.

## Tipografia

- **Uma família** no storefront (herda Blocksy + overrides do child theme).
- H1 hero > H2 seção > título de card — peso e escala decrescentes.
- Corpo mínimo **16px** equivalente; labels **14px** no mínimo.
- Título de produto: `-webkit-line-clamp: 2` ou equivalente, sem cortar SKU crítico.

## Acessibilidade mínima

```css
:where(a, button, input):focus-visible {
  outline: 3px solid var(--color-focus);
  outline-offset: 3px;
}
```

- Contraste texto/fundo ≥ 4,5:1 (corpo), 3:1 (texto grande).
- `aria-label` em busca, minicarrinho, nav quando ícone-only.
- Ordem Tab: promo → logo → busca → utilidades → menu → main.

## Mapeamento token ↔ componente WC

| Componente | Classes / seletores | Tokens |
|------------|---------------------|--------|
| Botão primário WC | `.woocommerce a.button`, `.wc-block-components-button` | orange-dark, radius-pill |
| Card produto | `.woocommerce ul.products li.product` | radius-md, shadow-card, border |
| Link nav | `.petshop-commercial-menu a` | teal-dark, min-height 44px |
| Promo bar | `.petshop-promo-bar` | surface-soft ou teal-dark |

## Quando consultar outras fontes

- **Regras de negócio e conteúdo administrável:** `.cursor/rules/project.mdc`
- **Gates por sessão:** `Plans/005-refinamento-comercial-do-storefront.md`
- **Padrões genéricos de componente (modal, toast, table):** skill `/ui-design-brain` — adaptar tokens deste arquivo, nunca copiar paleta SaaS genérica
