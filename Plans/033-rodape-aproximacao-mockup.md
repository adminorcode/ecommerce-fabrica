# Plano 033 — Aproximação visual do rodapé ao mockup

**Status:** Pendente  
**Data:** 2026-08-24  
**Branch sugerida:** `033-rodape-aproximacao-mockup`  
**Dependências:** [023-rodape-institucional-editavel.md](./023-rodape-institucional-editavel.md) (Customizer, menus, markup); [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md) (tokens teal/Nunito)  
**Origem:** print de 2026-08-24 do rodapé de referência (AUTeliê): 4 colunas, faixa de selos com filetes e divisórias, barra legal. O 023 entregou campos e estrutura; o storefront ainda não reproduz essa hierarquia visual.  
**ClickUp:** [86e2yy6mc](https://app.clickup.com/t/86e2yy6mc) — Open  

## 1. Objetivo

Aproximar o rodapé atual da loja ao mockup: três faixas (colunas, selos, legal), títulos teal com sublinhado, selos no mesmo fundo escuro com divisórias, legal compacto. Conteúdo continua no Customizer e nos menus do 023.

User story: como visitante, quero reconhecer no rodapé a mesma organização e o mesmo peso visual da referência (marca e redes, atendimento com ícones, categorias, institucional, selos, dados legais).

## 2. Baseline atual

Markup e settings: `inc/institutional-footer.php` + Customizer `petshop_footer` (023). CSS: `.petshop-institutional-footer` em `petshop-theme/style.css`.

| Bloco | Mockup (2026-08-24) | Loja hoje |
|---|---|---|
| Fundo das colunas | Carvão quase preto | Cinza `#373435` (exceção do 014) |
| Títulos ATENDIMENTO / CATEGORIAS / INSTITUCIONAL | Teal, caixa alta, **sublinhado teal** | Teal, caixa alta, **sem** sublinhado |
| Siga-nos | Teal, menor, sem sublinhado de coluna | `h2` igual aos outros títulos |
| Faixa de selos | Mesmo fundo escuro; filete teal em cima e embaixo; **divisórias verticais**; títulos **brancos**; ícones teal | Faixa preenchida `brand-teal-900`; títulos teal; sem divisórias |
| Legal | Faixa inferior; ícone de loja teal; **duas linhas** (copyright/CNPJ e endereço) | Ícone + texto, sem faixa visualmente separada |
| Colunas desktop | 4: marca+redes, atendimento, categorias, institucional | Já existem (023) |
| Chevrons e círculos de atendimento | Teal | Já existem (023) |

Exceção documentada: rodapé é hook global. Edição permanece **Personalizar → Rodapé da loja** e **Aparência → Menus**. Sem Gutenberg no rodapé.

## 3. Escopo comprometido

- Três faixas horizontais no desktop: (1) grade de 4 colunas, (2) selos, (3) legal.
- Coluna 1: logo (Identidade do site) + tagline + “Siga-nos” + ícones sociais em círculo outline.
- Coluna 2: ATENDIMENTO com sublinhado teal; 5 linhas ícone em círculo teal + título + apoio (quando preenchidos no Customizer).
- Colunas 3 e 4: CATEGORIAS e INSTITUCIONAL com sublinhado teal e chevron teal nos links (menus `petshop-primary` e `petshop-footer`).
- Faixa de selos: **não** usa fundo `brand-teal-900`. Usa o mesmo carvão do rodapé, filete teal no topo e na base, quatro itens com divisória vertical teal no desktop. Título do selo em branco/creme; descrição em branco mais suave; pictograma teal (escudo, medalha, cadeado, caminhão — ordem do 023).
- Faixa legal: visualmente separada; ícone de loja teal à esquerda; duas linhas (copyright/razão/CNPJ na primeira; endereço na segunda). Texto de pagamento do 023, se preenchido, permanece nesta faixa.
- Fundo do rodapé: carvão quase preto alinhado ao mockup (exceção local do rodapé, não troca a paleta do site). Teal dos acentos = tokens já existentes (`--color-brand-teal` / equivalentes). Laranja não entra no cromado do rodapé (o laranja do arquivo de logo permanece).
- Desktop 1440: 4 colunas e 4 selos em linha, sem overflow. 1024: 2×2 colunas e 2×2 selos. 390: 1 coluna; selos empilhados; alvos 44×44 nos links e ícones sociais.
- Nenhum campo novo no Customizer. Textos, URLs, selos, CNPJ e endereço continuam onde o 023 definiu. Vazio continua ocultando o trecho.
- Atualizar `docs/guia-edicao-rodape.md` só se a composição descrita mudar a orientação visual; a origem de edição não muda.

### Fora de escopo

- Reabrir o inventário de campos do 023 ou migrar o rodapé para Gutenberg.
- Header, busca (032), cards (031), checkout.
- Upload de SVG por rede/selo; Elementor; editar Blocksy.
- Paleta nova no restante da loja; Nunito Sans permanece.
- Pixel-perfect de tamanho de fonte contra o PNG. CNPJ, endereço e copy comerciais são conteúdo do Customizer, não deste CSS.
- ViaCEP (não há CEP nesta tela).

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Referência | Print de 2026-08-24 (três faixas). O 023 cobre campos; este plano cobre CSS/composição |
| Fundo | Carvão quase preto só no rodapé |
| Selos | Filetes + divisórias no fundo do rodapé; títulos brancos |
| Títulos de coluna | Caixa alta teal com sublinhado teal |
| Siga-nos | Teal, sem o sublinhado das colunas |
| Conteúdo | Customizer + menus do 023; zero campo novo |

## 5. Conteúdo administrável

Nada de texto comercial novo no código. Inventário permanece o do 023 (`docs/guia-edicao-rodape.md`).

| Item | Origem |
|---|---|
| Logo | Identidade do site |
| Tagline, redes, atendimento, selos, legal | Personalizar → Rodapé da loja |
| Categorias / Institucional | Menus |
| Títulos ATENDIMENTO, CATEGORIAS, INSTITUCIONAL, Siga-nos | Tradução/`__()` do tema (funcionais) |

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| CSS | `petshop-theme/style.css` | Fundos, filetes, sublinhado, divisórias, tipografia da faixa de selos e legal |
| Markup | `inc/institutional-footer.php` | Só se o CSS não der as três faixas (classes de faixa). Sem campos novos |
| Plugin | sem setting novo | 023 permanece a origem dos `theme_mod` |
| Gates | `scripts/validate-033-footer-browser.mjs` (+ PHP se markup mudar) | 1440/1024/390; ausência de `brand-teal-900` na faixa de selos; sublinhado nos `h2` de coluna; 4 colunas no desktop |

Não editar Core, WooCommerce ou Blocksy.

## 7. Sessões

### Sessão 01 — Três faixas e títulos

- [ ] Fundo carvão do mockup nas colunas.
- [ ] Sublinhado teal em ATENDIMENTO, CATEGORIAS e INSTITUCIONAL.
- [ ] “Siga-nos” distinto (sem o mesmo sublinhado de coluna).

**Gate**

- [ ] Em 1440 px o rodapé tem 4 colunas e os três `h2` de coluna têm borda/sublinhado teal.

### Sessão 02 — Selos e legal

- [ ] Remover o preenchimento `brand-teal-900` da faixa de selos.
- [ ] Filetes teal superior/inferior e divisórias verticais no desktop.
- [ ] Títulos dos selos brancos; ícones teal.
- [ ] Legal em faixa própria, ícone + duas linhas.

**Gate**

- [ ] Em 1440 a faixa de selos não é um bloco teal sólido; há 4 itens com separador.
- [ ] Em 390 as faixas empilham sem overflow nem corte de texto.
- [ ] Persistência 023: alterar um selo/CNPJ no Customizer continua refletindo no rodapé.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Contraste no carvão | Texto branco/creme e teal dos tokens; foco visível |
| Gate 023 que exigia faixa teal-900 | Atualizar asserção visual no 033; não reabrir critérios de campo do 023 |
