# Plano 032 — Busca: lupa e Enter listam produtos

**Status:** Concluído
**Data:** 2026-08-24  
**Branch sugerida:** `032-busca-lupa-enter-resultados`  
**Dependências:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) (formulário, sugestões Store API, estado vazio, SKU exato)  
**Origem:** o campo de busca do header não devolve a lista de produtos ao clicar na lupa ou apertar Enter. Investigar a query string e corrigir o envio.  
**ClickUp:** [86e2yy549](https://app.clickup.com/t/86e2yy549) — Open  

## 1. Objetivo

Clicar na lupa ou apertar Enter no campo de busca do header abre a **página de resultados** com a grade de produtos que correspondem ao termo, não uma página vazia, a Home nem uma busca de posts.

User story: como comprador, quero digitar o nome de um produto, clicar na lupa ou apertar Enter e ver a lista dos itens disponíveis.

## 2. Baseline atual

| Superfície | Estado | Problema |
|---|---|---|
| Formulário do header | `get_product_search_form()` em `petshop-theme` (`petshop-commercial-header__search`) | WooCommerce monta GET em `home_url('/')` com `s` + `post_type=product`. O gate do 013 **não** clica na lupa nem envia o form |
| Autocomplete | `storefront-search.js` + Store API `wc/store/v1/products` | Usa o parâmetro `search` (contrato REST). Só dispara no evento `input`. Lupa/Enter não montam essa URL |
| Enter com sugestão destacada | `keydown` faz `preventDefault` e vai à PDP | Enter sem destaque deveria enviar o form; se o JS ou o Blocksy interceptam o submit, a lista não abre |
| Canonical do catálogo | `CatalogFilter::canonicalParametersFromRequest()` | Preserva filtros da loja e **descarta** `s` e `post_type`. Se o submit cair em `/loja/`, a busca some no redirect |
| SKU exato (013) | `/?s=SKU&post_type=product` → 302 à PDP | Correto só quando o termo é SKU exato. Busca por nome deve listar produtos |
| Gate 013 | Preenche o input e testa sugestões; GET direto do SKU/vazio | Não prova lupa nem Enter |

Contrato WordPress/WooCommerce da **página de resultados**: `/?s={termo}&post_type=product`.  
Contrato Store API das **sugestões**: `?search={termo}&per_page=5`. São parâmetros diferentes. Trocar um pelo outro na query da página de resultados deixa `s` vazio e a lista não aparece.

A busca do header é superfície global (hook do tema), não bloco Gutenberg.

## 3. Escopo comprometido

- Lupa (botão submit) envia o formulário e abre a página de resultados com a grade WooCommerce dos produtos que batem com o termo.
- Enter no campo, **sem** sugestão destacada, faz o mesmo envio que a lupa.
- Clique numa sugestão continua abrindo a PDP daquele produto. Enter **com** sugestão destacada (setas) também abre essa PDP.
- A URL da página de resultados usa **`s`** (termo) e **`post_type=product`**. O termo vai na query com encoding correto (espaços, acentos). Não usar `search` nessa URL.
- Qualquer redirect (canonical, rotas 013, Blocksy) **preserva** `s` e `post_type`. `CatalogFilter` não pode engolir esses parâmetros.
- Formulário continua GET, `action` na home da loja, input `name="s"`, hidden `post_type=product`, botão `type="submit"`. Sem JavaScript o envio ainda lista produtos (progressive enhancement do 013).
- Autocomplete por digitação permanece na Store API oficial (`search` + `per_page`). Ele não impede o submit da lupa/Enter.
- Estado vazio do 013 permanece quando o termo não encontra produto.
- Redirect de SKU exato do 013 permanece somente quando o termo é SKU exato.

### Fora de escopo

- Novo endpoint REST próprio para busca.
- Busca de páginas, posts ou pedidos.
- Alterar filtros do catálogo (021) além de preservar `s`/`post_type` em redirect.
- Personalizador (012), checkout (026), frete (027), card variável (031).
- Editar WordPress Core, WooCommerce, Blocksy ou plugins de terceiro. Interceptação do Blocksy se existir: desligar por setting/hook ou CSS/JS no `petshop-core`/`petshop-theme`.
- ViaCEP (não há CEP nesta tela).

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Lupa | Sempre página de resultados (`s` + `post_type=product`) |
| Enter | Sem opção destacada → resultados. Com opção destacada → PDP da sugestão |
| Query da página | Só `s` e `post_type=product` (mais paginação WooCommerce se houver). Sem `search=` |
| Query das sugestões | Store API `search` + `per_page=5` |
| Termo vazio | O input continua `required`; não dispara busca vazia |
| Um resultado por nome | Mostra a lista (loop). Não redirecionar à PDP, salvo SKU exato do 013 |

## 5. Conteúdo administrável e textos funcionais

Exceção documentada: busca do header é hook global, não bloco da Home.

| Item | Origem |
|---|---|
| Placeholder / aria-label “Buscar produtos” | Tradução WooCommerce + filtro do `petshop-theme` |
| Sugestões / “Nenhum produto encontrado” no dropdown | `__()` em `SearchExperience` |
| Título “Resultados para …” e estado vazio da página | `SearchExperience` (já no 013) |
| Cards na lista | WooCommerce / tema; preço/CTA do card seguem 010 e 031 quando esse plano entrar |

Nenhum texto comercial novo fica fixo em PHP/CSS/JS.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Form | markup WooCommerce via `get_product_search_form` + tema | `s`, `post_type=product`, submit |
| Query | `petshop-core` (`SearchExperience`, `CatalogFilter`, rotas se preciso) | Garantir `s` + `post_type` na main query e nos redirects |
| Front | `storefront-search.js` | Sugestões no `input`; não `preventDefault` no submit nem no Enter sem destaque |
| Tema | CSS do header | Lupa clicável (alvo 44×44); dropdown não cobre o submit |
| Gates | `scripts/validate-032-*.php` e browser | lupa, Enter, URL, lista, vazio, sugestões |

Não editar Core, WooCommerce ou Blocksy.

## 7. Sessões

### Sessão 01 — Query string e submit

- [x] Inspecionar o HTML real do form (nomes dos campos, `action`, `method`, tipo do botão) na Home, loja e PDP.
- [x] Corrigir montagem da URL para `/?s={termo}&post_type=product`.
- [x] Impedir que JS, canonical ou `CatalogFilter` removam `s`/`post_type` ou troquem por `search`.
- [x] Lupa e Enter (sem destaque) abrem a lista de produtos para um termo conhecido (ex.: Bandana).

**Gate**

- [x] Clique na lupa: a URL contém `s` com o termo e `post_type=product`; `main ul.products li.product` lista os itens correspondentes.
- [x] Enter no campo (sem seta em sugestão): o mesmo resultado.
- [x] Submit sem JS (ou com JS desabilitado no gate PHP) ainda usa `s` + `post_type=product`.

### Sessão 02 — Sugestões e regressão 013

- [x] Digitar ≥2 caracteres continua mostrando sugestões da Store API.
- [x] Clique na sugestão abre a PDP; Enter com destaque também.
- [x] Termo inexistente mostra o estado vazio do 013.
- [x] SKU exato do 013 continua redirecionando à PDP.

**Gate**

- [x] Browser 1440 e 390: lupa, Enter, sugestão e vazio.
- [x] Atualizar `Plans/STATUS.md`.

### Evidência de validação

- `docker compose build wordpress node`; `docker compose up -d --force-recreate --wait wordpress`; cópia de `petshop-core`/`petshop-theme` para o runtime.
- `docker compose --profile test run --rm test-runner`: 36 testes, 80 assertions.
- `npm run validate`: gates PHP completos, incluindo `validate-032-search.php`.
- `npm run validate:changed:browser`: `validate-032-search-browser.mjs` em 1440 e 390, mais regressão `validate-021-catalog-filters-browser.mjs`.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Confundir `search` (Store API) com `s` (WP_Query) | Gate da URL da página de resultados exige `s` e `post_type` e recusa `search` como único termo |
| Blocksy live search intercepta o submit | Hook/setting no nosso código; não patch no tema pai |
| Canonical da loja come a busca | Preservar `s`/`post_type` em `canonicalParametersFromRequest` ou não canonicalizar quando houver `s` |
