# Plano 010 — Layout das seções de produto na Home

**Status:** Concluído  
**Data:** 2026-08-02  
**Branch sugerida:** `010-layout-secoes-produto-home`  
**Última validação:** 2026-08-02 — persistência PHP e browser 1280/390 aprovados  
**Dependências:** base funcional dos Planos 004/004b; Sessões 01–02 do Plano 005 concluídas  
**Relacionamento:** especializa o layout comercial das vitrines da Home descrito nas Sessões 04–06 do Plano 005, sem substituir filtros de catálogo, rodapé ou hero.

## 1. Objetivo

Aproximar as seções de produto da Home do layout de referência aprovado pelo cliente: cabeçalho compacto com título e link “Ver todos”, grade densa de quatro cards por linha em desktop, cards com melhor aproveitamento vertical e elementos condicionais derivados exclusivamente de dados reais do WooCommerce.

Escopo limitado à **Home** e aos **shortcodes de vitrine** reutilizados nela. O CSS de card pode beneficiar catálogo e PDP (relacionados), mas gates de aceite deste plano cobrem somente a rota `/` (Home).

## 2. Referência visual

Layout-alvo (fornecido pelo cliente, 2026-08-02):

- cabeçalho da seção em uma linha: título à esquerda, link textual “Ver todos →” à direita (cor institucional teal);
- grade com **4 produtos por linha** em desktop (1280 px), 2 em mobile;
- card compacto: imagem 1:1, título em até 2 linhas, avaliação quando existir, preço “de | por” em linha quando houver promoção, botão laranja “Adicionar ao carrinho” em largura total;
- badge de promoção (“Economize X%”) somente com preço promocional válido;
- badge “Mais pedido” somente com regra documentada de vendas reais;
- seções aplicáveis: **Mais vendidos**, **Economize / Kits**, **Coleção da estação**, **Seleção para banho e tosa**.

**Fora de escopo deste plano**

- ícone de wishlist / lista de desejos (fase futura opcional; depende de plugin ou feature dedicada);
- sidebar e filtragem do catálogo (permanecem no Plano 005, Sessão 04);
- substituição de fotografias (Plano 005, Sessão 03);
- rodapé e header (Plano 005, Sessões 01 e 07).

## 3. Resultado esperado

- todas as vitrines de produto da Home compartilham o mesmo padrão visual de card;
- todas as vitrines administráveis usam cabeçalho unificado (título + “Ver todos”);
- link “Ver todos” aponta para destino real (loja, categoria ou página Gutenberg), editável onde aplicável;
- cards sem promoção, avaliação ou badge não exibem buracos ou placeholders;
- seções sem produtos publicados somem por completo, sem heading órfão;
- alterações editoriais feitas pelo cliente na Home persistem após reprovisionamento;
- paridade editor ↔ loja mantida para blocos Gutenberg afetados.

## 4. Inventário de conteúdo por seção (rota `/`)

| Seção | Textos | Imagens / produtos | Link “Ver todos” | Origem de edição |
| --- | --- | --- | --- | --- |
| Mais vendidos / Destaques | Título dinâmico (`Mais vendidos` ou fallback) | Produtos por popularidade | Loja (`/shop/`) ou URL configurável | Shortcode `[petshop_featured_products]`; fallback de título em **Personalizar → Conteúdo da loja** |
| Economize comprando kits | Título, intro opcional, rótulo do CTA | Categoria **Conjuntos** | Categoria Conjuntos | Shortcode `[petshop_kits_section title="" intro="" cta="" category=""]` na Home |
| Coleção da estação | Título da seção | Categorias com meta **Categoria sazonal** + **Exibir na navegação** | Página Coleções ou primeira categoria sazonal | Bloco **Título** + `[petshop_seasonal_products]` na Home; categorias em **Produtos → Categorias** |
| Seleção para banho e tosa | Título, parágrafo introdutório | Categorias `adesivos`, `gravatas`, `lacos` (editável no shortcode) | Loja filtrada ou categoria principal | Blocos Gutenberg + `[products category="…"]` na Home |
| Card individual | Nome, preço, botão | Imagem destacada do produto | — | **Produtos** WooCommerce |
| Badge promoção | “Economize X%” (calculado) | — | — | Preço regular + preço promocional do produto |
| Badge mais pedido | “Mais pedido” | — | — | `total_sales` acima de limiar documentado na Sessão 04 |

Nenhum texto comercial ou URL de “Ver todos” pode ficar fixo apenas em PHP/CSS/JS sem caminho de edição na Home, Customizer ou WooCommerce.

## 5. Arquivos previstos

| Arquivo | Papel |
| --- | --- |
| `wp-content/themes/petshop-theme/style.css` | Grid, densidade, card, cabeçalho de seção, preço em linha, badges |
| `wp-content/themes/petshop-theme/assets/css/editor-storefront.css` | Overrides mínimos do iframe Gutenberg, se necessário |
| `wp-content/themes/petshop-theme/functions.php` | Bump de versão do tema (cache bust) |
| `wp-content/plugins/petshop-core/includes/class-storefront-experience.php` | Markup unificado das seções, shortcodes, migração versionada da Home |
| `docs/guia-edicao-home.md` | Atualizar instruções de “Ver todos” e novos atributos de shortcode |
| `scripts/validate-010-session-*.php` | Gates PHP (persistência, seções vazias) |
| `scripts/validate-010-session-*-browser.mjs` | Gates visuais/responsivos |

## 6. Regras transversais

- Reutilizar tokens de `style.css` (`--color-brand-teal`, `--color-brand-orange`, `--radius-*`, `--space-*`).
- Não fabricar desconto, avaliação, badge “Mais pedido” ou wishlist.
- Ocultar blocos condicionais sem whitespace residual (> 120 px).
- Migrações em `StorefrontExperience` devem preservar conteúdo customizado da Home (hash/versão).
- Laranja restrito a CTA, preço em destaque e badges de ação; seções não usam fundo laranja integral.
- Validar 390, 768, 1024 e 1280 px antes de fechar cada sessão.

## 7. Protocolo de cada sessão

1. baseline screenshot desktop/mobile da Home;
2. implementar somente o escopo da sessão;
3. validar sintaxe PHP/CSS e ausência de fatal no log;
4. testar persistência editorial e reprovisionamento;
5. capturar evidências após lazy load;
6. marcar sessão concluída somente quando o gate passar.

## 8. Sessões de implementação

### Sessão 01 — Cards compactos (CSS global da vitrine)

**Status da sessão:** [x] Concluída

**Escopo**

- reduzir padding interno e gaps da grade para aproximar densidade da referência;
- manter imagem 1:1 com `object-fit: contain`;
- título com `-webkit-line-clamp: 2` e altura mínima estável;
- avaliação oculta quando vazia (já parcialmente implementado);
- preço promocional: exibir `del` à esquerda e `ins` à direita na mesma linha quando houver sale;
- botão “Adicionar ao carrinho” full-width, altura mínima 44 px;
- alinhar alturas de cards na mesma linha (flex/grid + `margin-top: auto` no preço/CTA);
- garantir 4 colunas em `columns-4` a partir de 1024 px; 2 colunas abaixo de 768 px.

**Arquivos:** `style.css`; revisar `editor-storefront.css` se o canvas distorcer a grade.

**Gate verificável**

- [x] quatro cards alinhados por linha em 1280 px quando houver quatro produtos;
- [x] cards com título de 1 e 2 linhas mantêm CTA alinhado na base;
- [x] produto sem promoção não exibe `del` nem badge;
- [x] produto sem review não deixa espaço de estrelas;
- [x] adição ao carrinho por teclado funciona e atualiza minicarrinho;
- [x] screenshots com card “completo” e card “mínimo”.

### Sessão 02 — Cabeçalho unificado de seção

**Status da sessão:** [x] Concluída

**Escopo**

- criar componente markup `.petshop-section-head` com:
  - `<h2>` (id para `aria-labelledby`);
  - link `.petshop-section-head__link` com texto padrão “Ver todos →”;
- estilizar flex `space-between`, alinhamento vertical, link teal sem sublinhado agressivo;
- substituir CTA centralizado abaixo da grade na seção de kits por cabeçalho unificado;
- remover parágrafo introdutório longo dos kits **somente se** o cliente preferir layout idêntico à referência — caso contrário, manter intro abaixo do cabeçalho em tipografia secundária.

**Arquivos:** `class-storefront-experience.php`, `style.css`.

**Gate verificável**

- [x] título e “Ver todos” na mesma linha em desktop;
- [x] em mobile, link permanece visível e tocável (44 px), sem overflow horizontal;
- [x] foco visível no link (`:focus-visible`);
- [x] rótulo do link editável via atributo de shortcode ou bloco na Home.

### Sessão 03 — Padronização dos shortcodes da Home

**Status da sessão:** [x] Concluída

**Escopo**

- refatorar `renderFeaturedProducts`, `renderKitsSection`, `renderSeasonalProducts` para emitir:
  - `<section class="petshop-section petshop-product-showcase">`;
  - cabeçalho unificado (Sessão 02);
  - grade `[products …]`;
- estender `renderSeasonalProducts` para aceitar `title`, `cta`, `cta_url` (fallback: página Coleções ou termo sazonal);
- criar shortcode `[petshop_product_showcase]` genérico para **Seleção para banho e tosa** (wrapper + cabeçalho + `[products]`), substituindo grupo Gutenberg fragmentado;
- atualizar `managedHomeTail()` com markup migrável e versão incrementada em `petshop_storefront_version`;
- atributos administráveis mínimos:

```text
[petshop_featured_products limit="4" columns="4" cta="Ver todos →" cta_url=""]
[petshop_kits_section limit="4" columns="4" title="" intro="" cta="Ver todos →" category="conjuntos"]
[petshop_seasonal_products limit="4" columns="4" title="" cta="Ver todos →" cta_url=""]
[petshop_product_showcase limit="4" columns="4" title="" intro="" cta="Ver todos →" category="adesivos,gravatas,lacos" orderby="date"]
```

- migração idempotente: se a Home já foi customizada, não sobrescrever blocos editados pelo cliente.

**Gate verificável**

- [x] quatro seções usam o mesmo padrão de cabeçalho + grade;
- [x] “Ver todos” de kits resolve para `/product-category/conjuntos/` (ou slug administrável);
- [x] “Ver todos” de sazonal resolve para destino válido HTTP 200;
- [x] seção banho e tosa editável na Home sem alterar PHP;
- [x] reprovisionamento preserva customização manual da Home;
- [x] seção sem produtos não renderiza HTML (incluindo `<h2>`).

### Sessão 04 — Badges condicionais

**Status da sessão:** [x] Concluída

**Escopo**

- badge de promoção: calcular percentual a partir de regular vs sale price; exibir “Economize X%” no canto superior esquerdo da área de conteúdo do card;
- badge “Mais pedido”: exibir somente se `total_sales >= N`, com **N documentado** (sugestão inicial: top quartil ou limiar fixo ≥ 5 vendas — confirmar na implementação);
- posicionar badges sem sobrepor wishlist (reservar canto superior direito para fase futura);
- usar hook WooCommerce (`woocommerce_before_shop_loop_item_title` ou filtro de loop) no plugin, não no tema;
- ocultar badge quando condição não for atendida.

**Arquivos:** novo arquivo sugerido `class-storefront-product-card.php` em `petshop-core` **ou** métodos em `StorefrontExperience` se mantiver escopo mínimo.

**Gate verificável**

- [x] produto em promoção exibe percentual correto (arredondamento documentado);
- [x] produto sem promoção não exibe badge de economia;
- [x] produto abaixo do limiar de vendas não exibe “Mais pedido”;
- [x] nenhum badge fabricado em ambiente seed sem vendas/promo reais;
- [x] badges não quebram layout com título longo.

### Sessão 05 — Documentação, guia e validação final

**Status da sessão:** [x] Concluída

**Escopo**

- atualizar `docs/guia-edicao-home.md` com cabeçalho “Ver todos”, novos shortcodes e origem dos badges;
- registrar evidências em `Plans/010-TESTING.md`;
- scripts `validate-010-session-03-persistence.php` e `validate-010-session-03-browser.mjs` cobrindo:
  - ordem e presença das seções;
  - ausência de heading órfão;
  - grid 4 colunas desktop;
  - persistência de atributos de shortcode;
- bump de versão do plugin (`StorefrontExperience::VERSION`) e do tema.

**Gate verificável**

- [x] guia reflete interface final;
- [x] scripts de validação passam em Docker local;
- [x] matriz desktop/mobile anexada ao ledger;
- [x] checklist de conteúdo administrável do `Plans/README.md` satisfeito.

### Sessão 06 — Wishlist (opcional / backlog)

**Status da sessão:** [ ] Fora de escopo

**Escopo**

- avaliar plugin existente ou implementação mínima compatível com WooCommerce Blocks;
- ícone coração no canto superior direito do card;
- somente iniciar após Sessões 01–05 concluídas e decisão explícita do cliente.

## 9. Ordem de execução

```
Sessão 01 (cards CSS)
  └── Sessão 02 (cabeçalho)
        └── Sessão 03 (shortcodes + migração Home)
              └── Sessão 04 (badges reais)
                    └── Sessão 05 (docs + gates)
```

Pode executar em paralelo parcial com Plano 009 (tokens/a11y), desde que alterações de CSS não conflitem — preferir reutilizar tokens existentes.

## 10. Critério de conclusão do plano

O Plano 010 só pode ser marcado como **Concluído** quando:

1. Sessões 01–05 estiverem com gates aprovados;
2. nenhum texto comercial da Home depender de edição de código;
3. screenshots 1280 e 390 px da Home aprovados lado a lado com a referência;
4. `Plans/STATUS.md` atualizado;
5. Sessão 06 permanecer explicitamente opcional ou cancelada.

## 11. Riscos e mitigação

| Risco | Mitigação |
| --- | --- |
| Migração sobrescrever Home customizada | hash de conteúdo + flag de divergência, padrão do 005 |
| CSS de card quebrar catálogo | gates incluem amostra em `/shop/`; escopo visual compartilhado é intencional |
| “Mais pedido” sem vendas no seed | ocultar badge; usar produto de teste temporário apenas no gate |
| Paridade Gutenberg incompleta | `editor-storefront.css` + validação no editor conforme regra `gutenberg-editor-parity` |

## 12. Referências internas

- [005-refinamento-comercial-do-storefront.md](./005-refinamento-comercial-do-storefront.md) — Sessões 04–06
- [docs/guia-edicao-home.md](../docs/guia-edicao-home.md)
- `.cursor/rules/ecommerce-ui-ux.mdc`
- `.cursor/rules/gutenberg-editor-parity.mdc`
