# Plano 021 — Filtros de catálogo funcionais

## 1. Status

Concluído.

## 2. Problema

O filtro lateral atual do catálogo ocupa mais altura que a viewport e exige múltiplas rolagens para o usuário entender e aplicar todos os filtros. Em desktop, ele funciona como um formulário longo sempre aberto; em mobile, vira drawer, mas ainda depende de rolagem extensa dentro do painel.

O problema observado nas capturas:

- O usuário precisa rolar a página para ver categorias, preço, cor, tamanho e disponibilidade.
- A ação `Aplicar filtros` fica distante do ponto onde a seleção é feita.
- O filtro compete com a grade de produtos em vez de funcionar como uma ferramenta de refinamento rápida.
- As facetas menos usadas têm o mesmo peso visual das mais usadas.

## 3. Pesquisa e inspiração

Fontes consultadas em 2026-08-15:

- Petlove — https://www.petlove.com.br/ e categoria de rações https://www.petlove.com.br/cachorro/racoes
- Petz — https://www.petz.com.br/
- Cobasi — https://www.cobasi.com.br/
- Chewy — https://www.chewy.com/b/food-332
- Amazon Pet Shop — https://www.amazon.com.br/b?ie=UTF8&node=21217754011

Leituras aplicáveis:

- Petz prioriza navegação por categoria no topo antes de refinamento profundo. A primeira escolha do cliente é o tipo de produto, não um painel longo de filtros.
- Petlove e Chewy mantêm a atenção na listagem e nos cards, com ordenação/refinamento como apoio. Chewy mostra muitos produtos, avaliações, preço, entrega e CTA; filtro não pode roubar a tela da decisão de compra.
- Cobasi e Petlove trabalham com benefícios e categorias como atalhos comerciais. Para a Autellê, categorias principais e chips aplicados devem aparecer rápido, sem forçar leitura de todas as facetas.
- Amazon/Chewy usam filtros por facetas especializadas e agrupadas. O padrão correto para listas longas é disclosure/accordion, busca dentro de grupos e ações persistentes.

Conclusão de UX: o catálogo deve trocar o painel lateral alto por uma combinação de `toolbar + chips + painel compacto`, com disclosure progressivo. Em desktop, o filtro pode continuar lateral, mas precisa caber no uso real: altura limitada, scroll interno único, grupos recolhíveis e botão de aplicar sempre visível. Em tablet/mobile, o filtro deve ser um drawer/bottom sheet com header e footer fixos.

## 4. Objetivo

Redesenhar os filtros de catálogo para que o usuário consiga:

- entender quais filtros estão ativos sem abrir o painel;
- abrir filtros sem perder o contexto da grade;
- alterar categorias, preço, cor, tamanho e estoque com no máximo uma área de rolagem por vez;
- aplicar ou limpar filtros sem procurar botões no fim do painel;
- usar o filtro em desktop, tablet e mobile sem sobreposição, overflow ou painel maior que a tela.

## 5. Escopo

Rotas afetadas:

- `/loja/`
- `/product-category/*`
- `/product-tag/*`
- buscas de produto quando usarem o mesmo fluxo de listagem/filtros, se aplicável ao hook atual.

Componentes afetados:

- filtro de catálogo renderizado por `Petshop\Core\Storefront\CatalogFilter`;
- toolbar de catálogo;
- chips de filtros aplicados;
- drawer/painel de filtros;
- CSS do catálogo no `petshop-theme`;
- JS de abertura, fechamento e busca dentro de categorias;
- gates browser do catálogo.

Fora do escopo:

- trocar motor de busca ou indexação;
- AJAX de produtos sem reload;
- novos atributos comerciais ainda não cadastrados no WooCommerce;
- mudança visual dos cards de produto, exceto ajustes mínimos de grid afetados pela largura do filtro.

## 6. Direção de interface

### Desktop amplo, >= 1180 px

- Layout em duas colunas, mas com sidebar mais funcional:
  - largura alvo entre `256px` e `280px`;
  - sidebar `position: sticky` abaixo do header/breadcrumb;
  - `max-height: calc(100dvh - offset)` com scroll interno único;
  - header do painel fixo no topo do card;
  - footer fixo no fim do card com `Aplicar filtros` e `Limpar`.
- Grupos em accordion/disclosure:
  - `Categorias` aberto por padrão;
  - `Preço`, `Cor`, `Tamanho`, `Disponibilidade` recolhidos por padrão quando não ativos;
  - qualquer grupo com filtro ativo abre por padrão.
- Categorias:
  - busca no topo;
  - mostrar primeiras categorias prioritárias e opção `Ver mais` quando houver lista longa;
  - categoria atual aparece também como chip aplicado.
- Toolbar:
  - contador de resultados;
  - ordenação;
  - botão secundário `Filtros` com contador de ativos mesmo em desktop, para consistência e fallback.

### Tablet, 768–1179 px

- Remover sidebar fixa.
- Usar toolbar compacta acima da grade:
  - botão `Filtros (n)`;
  - chips aplicados em linha horizontal rolável;
  - ordenação ao lado ou em linha seguinte, sem quebrar grid.
- Abrir filtros em drawer lateral ou bottom sheet:
  - largura `min(420px, 100vw)`;
  - header fixo;
  - conteúdo com scroll interno único;
  - footer fixo com aplicar/limpar.

### Mobile, < 768 px

- Usar bottom sheet/drawer full-screen:
  - fundo da página travado enquanto o painel estiver aberto;
  - header com título e fechar;
  - chips aplicados no topo do painel;
  - accordions para todos os grupos;
  - footer sempre visível com botão primário `Aplicar filtros` e link `Limpar`.
- A página não pode exigir rolagem simultânea do body e do painel para chegar ao botão de aplicar.

## 7. Conteúdo administrável e dados

Não há texto editorial, comercial ou imagem nova neste plano.

Textos funcionais:

- `Filtros`
- `Filtrar produtos`
- `Categorias`
- `Filtrar categorias`
- `Preço`
- `Cor`
- `Tamanho`
- `Disponibilidade`
- `Aplicar filtros`
- `Limpar`
- `Ver mais`
- `Ver menos`

Origem: strings funcionais traduzíveis via APIs de tradução WordPress no `petshop-core`.

Dados dinâmicos:

- categorias: taxonomia WooCommerce `product_cat`;
- cor e tamanho: atributos WooCommerce `pa_color` e `pa_size`;
- estoque: `_stock_status`;
- preço: parâmetros WooCommerce `min_price` e `max_price`;
- ordenação: WooCommerce `orderby`.

Não criar imagens. Não criar conteúdo Gutenberg. Não salvar textos comerciais em PHP/CSS/JS.

## 8. Implementação proposta

1. Refatorar markup do filtro em `CatalogFilter.php`:
   - cada faceta vira um disclosure com botão `aria-expanded`;
   - adicionar wrappers para corpo rolável e footer fixo;
   - chips aplicados ficam disponíveis tanto na toolbar quanto dentro do painel;
   - preservar nomes de parâmetros atuais para não quebrar URLs.

2. Atualizar `catalog-filter.js`:
   - controlar accordions;
   - abrir grupos ativos por padrão;
   - manter foco e Escape no drawer;
   - bloquear scroll do body somente enquanto drawer estiver aberto em tablet/mobile;
   - implementar `Ver mais/Ver menos` para listas longas;
   - manter a busca de categoria acessível com `aria-live`.

3. Atualizar CSS do `petshop-theme`:
   - sidebar sticky com altura limitada em desktop;
   - card mais compacto, com padding menor e divisões claras;
   - footer sticky interno;
   - toolbar responsiva com chips;
   - drawer/bottom sheet com header/footer fixos;
   - garantir touch targets de 44 px.

4. Preservar comportamento funcional:
   - filtros continuam submetendo para `/loja/`;
   - canonicalização de parâmetros em páginas de taxonomia continua funcionando;
   - seleção múltipla de categorias continua com operador `IN`;
   - atributos continuam combinando com relação `AND`;
   - estoque e preço continuam preservados na query.

## 9. Critérios de aceite

- Em desktop `1440x1000`, o painel de filtros não ultrapassa a viewport; o botão `Aplicar filtros` fica visível sem rolar a página inteira.
- Em desktop `1024x900`, não há sidebar lateral fixa; filtro abre por drawer ou painel compacto sem cobrir permanentemente a grade.
- Em mobile `390x844`, abrir filtros trava o body e permite ver header, conteúdo e footer do painel com uma única rolagem interna.
- Nenhum cenário exige rolagem independente da página e do filtro ao mesmo tempo para aplicar uma seleção.
- Grupos recolhíveis têm `aria-expanded`, são acionáveis por teclado e têm foco visível.
- Grupos com filtro ativo aparecem abertos por padrão.
- Chips aplicados aparecem fora do painel e permitem remover filtros individualmente.
- `Limpar` remove todos os filtros e preserva rota canônica `/loja/`.
- A grade de produtos não tem overflow horizontal em `390`, `768`, `1024` e `1440`.
- O banner `.hero-section` padrão do Blocksy continua ausente em loja/categoria/PDP.

## 10. Validação

Atualizar ou criar gate browser para validar:

- `/loja/`
- `/product-category/bandanas/`
- `/product-category/conjuntos/`
- `/loja/?product_cat%5B%5D=bandanas&filter_pa_color=azul&filter_pa_size=p&stock_status=instock`

Métricas obrigatórias:

- `visibleThemeHeroes === 0`;
- sem overflow horizontal;
- botão de filtro visível nos breakpoints tablet/mobile;
- drawer abre, fecha por botão, fecha por Escape e devolve foco;
- body não rola quando drawer está aberto;
- footer do painel visível;
- número de rolagens internas do painel é no máximo 1;
- chips aplicados aparecem e removem filtros;
- ordenação continua presente;
- produtos continuam renderizando.

Executar:

- lint PHP em `CatalogFilter.php`;
- `npm run test`;
- `npm run validate`;
- gate browser específico do Plano 021;
- screenshots desktop/tablet/mobile em `.local/evidence/021/`.

## 11. Riscos

- Drawer em tablet pode esconder contexto demais se for full-screen; preferir lateral quando houver largura suficiente.
- Sidebar sticky precisa respeitar header sticky atual e admin bar.
- Accordions não podem esconder filtros ativos sem feedback visual.
- `Ver mais` em categorias precisa continuar compatível com busca textual.
- Alterar markup pode quebrar testes existentes de labels/checkboxes; atualizar gates mantendo acessibilidade.

## 12. Entrega manual esperada

Após implementação, testar manualmente:

1. Abrir `/product-category/bandanas/` em desktop.
2. Confirmar que o filtro não passa da tela e que aplicar/limpar fica sempre acessível.
3. Selecionar outra categoria, cor, tamanho e estoque.
4. Aplicar e confirmar URL/resultados/chips.
5. Remover um chip individual.
6. Repetir em largura mobile, abrindo e fechando o drawer.
7. Confirmar que o hero Gutenberg de `/animal-republik/` continua intacto e que o `.hero-section` padrão não voltou.

## 13. Evidência de entrega

Implementado em `codex/021-filtros-catalogo-funcionais`.

- `CatalogFilter.php`: toolbar com botão de filtros e chips aplicados fora do painel, painel com header/body/footer, facetas em disclosure com `aria-expanded`, grupos ativos abertos por padrão e chip para categoria atual em rotas de categoria.
- `catalog-filter.js`: controle de drawer em tablet/mobile, Escape, retorno de foco, trava de scroll do body, busca de categorias com `aria-live`, navegação por setas entre facetas e `Ver mais/Ver menos`.
- `style.css`: sidebar sticky com altura limitada em desktop amplo, drawer lateral em tablet, bottom sheet em mobile, footer fixo, chips roláveis e contenção de overflow horizontal.
- `validate-021-catalog-filters-browser.mjs`: gate browser para `/loja/`, `/product-category/bandanas/`, `/product-category/conjuntos/` e query combinada, em `1440`, `1024`, `768` e `390`.

Validação executada:

- `docker compose --profile tools run --rm --no-deps cli php -l /var/www/html/wp-content/plugins/petshop-core/includes/Storefront/CatalogFilter.php` — passou.
- `npm run validate:changed:browser` — passou; inclui `validate-021-catalog-filters-browser.mjs` e `validate-no-theme-hero-browser.mjs`.
- `npm run test` — passou: 15 testes, 26 assertions.
- `npm run validate` — passou: todos os gates PHP.

Screenshots gerados em `.local/evidence/021/`: `desktop-1440.png`, `desktop-1024.png`, `mobile-390.png`.
