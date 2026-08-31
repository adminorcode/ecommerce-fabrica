# Plano 035 — Dropdown de subcategorias no menu comercial

**Status:** Pendente  
**Data:** 2026-08-30  
**Branch sugerida:** `035-dropdown-subcategorias-menu-comercial`  
**Dependências:** menu `petshop-primary` / “Navegação comercial” (005/018); tokens e tipografia [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md); drawer mobile do header comercial  
**Origem:** print de 2026-08-30 da [Moda Bicho](https://www.modabicho.com.br/bandana-pet) (hover em **Bandana** abre lista vertical: Kits econômicos, Escolha a estampa, Data festiva, Renda e luxo). A referência manda o **padrão do dropdown**, não a cópia da marca, cores ou copy.  
**Referência visual:** [docs/referencias/035-dropdown-subcategorias-moda-bicho.png](../docs/referencias/035-dropdown-subcategorias-moda-bicho.png)  
**ClickUp:** [86e31cb6z](https://app.clickup.com/t/86e31cb6z) — Open  

## 1. Objetivo

No menu de navegação comercial do header, as subcategorias deixam de aparecer soltas na faixa (sempre visíveis, na mesma linha ou empilhadas sob o pai) e passam a um dropdown vertical no padrão da referência: caixa branca, borda fina, sombra, lista de nomes alinhada à esquerda do item pai.

User story: como visitante, ao passar o mouse (ou focar) em uma categoria do menu comercial que tenha filhas, quero ver só as subcategorias daquela categoria e clicar para ir à listagem correspondente.

## 2. Baseline atual

| Superfície | Estado | Problema |
|---|---|---|
| Header comercial | `wp_nav_menu` em `petshop-theme/functions.php`, location `petshop-primary`, menu **Navegação comercial**, `container` `nav` (classe WP `menu-navegacao-comercial-container`), `menu_class` `petshop-commercial-menu`, `depth` => 2 | Filhos saem em `.sub-menu`, mas **não há CSS/JS de dropdown** |
| Desktop (≥768px) | `.petshop-commercial-menu` é flex em linha com `flex-wrap` | Filhos vazam na mesma faixa ou esticam a barra; não há painel sob o pai |
| Mobile (<768px) | Drawer `#petshop-commercial-menu-panel` | Filhos entram na lista do drawer sem hierarquia; clique em qualquer `<a>` fecha o drawer |
| Rodapé | Mesmo menu, `depth` => 1, classe `petshop-institutional-footer__menu` | Fora deste plano; continua só o primeiro nível |
| Grade “Compre por categoria” da Home | `.petshop-category-grid` com prévia de produtos | Fora deste plano |
| Origem dos itens | **Aparência → Menus** | Nomes, ordem, URL e aninhamento já são do cliente |

Exceção documentada: o menu é hook global do header, não bloco Gutenberg da Home. Edição permanece **Aparência → Menus**.

## 3. Escopo comprometido

Alcance: **global** — o padrão vale para **todo** item de primeiro nível do menu comercial que tenha filhos, em qualquer rota que renderize o header comercial (Home, loja, categoria, PDP, institucional). Bandana na Moda Bicho é o exemplo; o gate testa pelo menos **dois** pais com filhos.

- Desktop (viewport ≥768px e `(hover: hover) and (pointer: fine)`): hover ou foco no item pai abre um painel sob o pai, alinhado à borda esquerda do item, com lista **vertical** de subcategorias (só o título do item). Um dropdown aberto por vez.
- Clique no nome do pai continua indo à URL do pai (categoria, página ou link do menu).
- Item sem filhos não abre caixa vazia e não ganha chevron.
- Mobile e ponteiro grosso: o drawer atual permanece. Filhos começam recolhidos. O nome do pai é o link da categoria. Um botão chevron (alvo 44×44px) expande/recolhe a lista. Expandir o chevron **não** navega e **não** fecha o drawer. Clicar numa filha navega e fecha o drawer.
- Teclado: Tab entra nas filhas enquanto o painel está aberto; Escape fecha o dropdown aberto no desktop; no drawer, Escape continua fechando o drawer. `:focus-visible` visível. `prefers-reduced-motion: reduce` respeitado.
- Composição visual alinhada à referência da Moda Bicho, com **tokens da loja** (014), não as cores do site de referência: fundo `--color-surface`, texto `--color-ink`, hover/current no laranja já usado no menu, borda `--color-border`, sombra `--shadow-card`, Nunito Sans. Sem imagens, preços, badges ou prévia de produto no dropdown. Sem copiar logo, textos ou ativos da Moda Bicho.
- Profundidade máxima continua 2. Não há terceiro nível.
- Fonte dos itens: somente os filhos já aninhados em **Aparência → Menus → Navegação comercial**. Não injetar filhos da taxonomia `product_cat` automaticamente. Reprovisionamento **não** desfaz aninhamento nem rótulos editados pelo cliente.
- Atualizar o guia operacional do menu (seção em `docs/guia-edicao-home.md` e linha em `docs/arquitetura-petshop-core.md`) para dizer: subcategoria no dropdown = item filho no menu comercial; item de primeiro nível sem filhos = só o link.

### Fora de escopo

- Grade `[petshop_categories]` / prévia de produtos da Home.
- Mega menu com fotos, preços ou vitrine.
- Alterar o catálogo WooCommerce, criar categorias ou mudar slugs.
- Injetar filhos de `product_cat` que não estejam no menu.
- Rodapé (023/033), busca (032), checkout (020), cards (031).
- Editar WordPress Core, WooCommerce, Blocksy ou plugins de terceiro.
- ViaCEP (não há CEP nesta tela).

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Superfície | Só o nav comercial do header (`menu-navegacao-comercial-container` / `.petshop-commercial-menu`) |
| Origem | Filhos do menu WordPress; o cliente aninha em **Aparência → Menus** |
| Desktop | Hover/foco abre; clique no rótulo do pai navega |
| Mobile / touch | Chevron abre a lista; rótulo do pai navega |
| Conteúdo do painel | Somente nomes das filhas, um por linha |
| Cores | Tokens Autelie; o vermelho da referência não entra |
| Rodapé | `depth` 1 inalterado |

## 5. Conteúdo administrável

Nenhum texto comercial novo no código.

| Item | Origem |
|---|---|
| Rótulo e URL de cada categoria / subcategoria | **Aparência → Menus** → Navegação comercial (location `petshop-primary`) |
| Nome canônico da categoria WooCommerce | **Produtos → Categorias** (o menu pode usar título próprio) |
| Quais filhas aparecem e em que ordem | Aninhamento e ordem no mesmo menu |
| “Categorias” do drawer, aria-labels do header | Tradução/`__()` do tema (funcionais) |
| Chevron / “Abrir subcategorias de {nome}” | `__()` no tema, com o nome vindo do item do menu |

Persistência: editar título, URL ou aninhamento no menu reflete no storefront sem código. Migração/reprovisionamento não reescreve itens já salvos.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Markup | `petshop-theme/functions.php` (`wp_nav_menu`) | Manter `depth` 2. Walker ou filtro só se o markup padrão do WP não der `aria-expanded`, `aria-haspopup` e botão chevron no item com filhos |
| CSS | `petshop-theme/style.css` | Esconder `.sub-menu` até aberto; painel absoluto no desktop; accordion no drawer; tokens 014; overflow do header/painel **não** corta o dropdown |
| JS | Script do header (hoje inline no `wp_footer`) ou arquivo enfileirado no tema | Hover/foco desktop; um aberto por vez; chevron no drawer; Escape; não fechar o drawer ao clicar no chevron |
| Plugin | sem setting novo | Menu e categorias continuam no WordPress/WooCommerce |
| Docs | `docs/guia-edicao-home.md`, `docs/arquitetura-petshop-core.md` | Origem de edição do dropdown |
| Gates | `scripts/validate-035-menu-dropdown.php` e `scripts/validate-035-menu-dropdown-browser.mjs` | Markup + hover em 1440; accordion em 390; segundo pai com filhos |

Não editar Core, WooCommerce ou Blocksy.

O listener atual do drawer (`click` em qualquer `<a>` fecha o painel) precisa deixar de tratar o controle do submenu como navegação.

## 7. Sessões

### Sessão 01 — Dropdown desktop

- [ ] `.sub-menu` oculto até hover/foco/`aria-expanded="true"` no pai.
- [ ] Painel vertical, alinhado à esquerda do pai, borda + sombra, nomes em lista.
- [ ] Clique no pai navega; item sem filhos não abre painel.
- [ ] Header não corta o painel; um dropdown por vez.

**Gate**

- [ ] Em 1440px, hover em um pai com filhos mostra só as filhas daquele item; o outro pai com filhos permanece fechado.
- [ ] Clique na filha abre a URL da filha (HTTP 200).
- [ ] Item de primeiro nível sem filhos não renderiza caixa vazia.

### Sessão 02 — Drawer, teclado e persistência

- [ ] Em 390px o drawer lista só o primeiro nível; chevron 44×44px revela as filhas.
- [ ] Teclado: foco abre no desktop; Escape fecha o dropdown; foco visível.
- [ ] Documentação do menu atualizada.
- [ ] Alterar título ou aninhamento em **Aparência → Menus** reflete no dropdown sem código.

**Gate**

- [ ] Em 390px, abrir o chevron de um pai não navega e não fecha o drawer; a filha navega.
- [ ] Amostra fora do exemplo Bandana da Moda Bicho: um segundo pai com filhos segue o mesmo padrão.
- [ ] Persistência: item filho renomeado no menu aparece com o novo rótulo após reload.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Overflow do header/painel corta o dropdown | `overflow: visible` no eixo do menu desktop; gate visual em 1440 |
| Clique no chevron fecha o drawer | O handler de âncora ignora o controle do submenu |
| CSS vaza para o rodapé | Seletores só em `.petshop-commercial-menu` / `#petshop-commercial-menu-panel` |
| Reprovisionamento achata o menu | Não reescrever filhos já salvos; gate de persistência |
