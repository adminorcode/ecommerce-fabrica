# Plano 014 — Evolução da identidade visual AUTellê

**Status:** Pendente

**Data:** 2026-08-04

**Branch sugerida:** `014-evolucao-identidade-visual-autelle`

**Dependências:** Planos 009 e 011 concluídos; pode ser executado em paralelo ao Plano 013.
**Origem:** revisão do storefront local e do Guia de Identidade Visual AUTellê.

## 1. Objetivo

Evoluir o storefront para uma expressão de marca mais reconhecível, acolhedora e profissional sem perder os ganhos atuais de legibilidade, contraste, responsividade e conteúdo administrável.

O plano introduz uma paleta semântica de verde-petróleo, ajusta tipografia, raios, sombras, footer e estados de componentes, e cria uma variante de campanha com copy editável no Gutenberg. Não recria o catálogo, não altera o tema pai Blocksy nem substitui conteúdo já salvo pelo cliente.

## 2. Decisões de produto e guardrails

- A referência para uso visual passa a ser o guia oficial revisado em `C:\Users\lucas\Downloads\guia-identidade-visual-autelle.md`; os arquivos originais em `idvisual/` continuam sendo a fonte para logo e sua grafia definitiva.
- Antes de publicar, o responsável pela marca deve confirmar a grafia textual que acompanha o logo. Até essa confirmação, nenhuma migração pode reescrever nome, logo, SEO, menus ou copy existente.
- `#004F50`, `#126E70`, `#2B9292`, `#58C2C7`, `#E9530D` e `#F47721` são tokens de marca; não são licença para uso indiscriminado.
- O CTA preenchido com texto branco deve usar `--color-brand-orange-action: #C94B0B` (ou outra variação aprovada que alcance 4,5:1). `#E9530D` e `#F47721` ficam para superfícies, selos e detalhes quando a combinação de contraste for validada.
- O foco permanece um token funcional independente (`#005FCC`), pois tem contraste robusto em múltiplas superfícies. `brand-teal-500` pode compor foco apenas depois de validação em todos os fundos.
- Usar Nunito Sans como família principal, com fallback de sistema. Poppins não será introduzida nesta etapa: uma única família reduz carga, inconsistência e risco de aparência excessivamente promocional.
- A fotografia de produto não será tingida para forçar a paleta; fidelidade de cor do acessório e do produto prevalece. A paleta aparece no ambiente, composição e interface.
- Hero e conteúdo editorial continuam no Gutenberg. Customizer permanece restrito a conteúdo global. Não haverá textos, URLs ou imagens comerciais novos fixos em PHP, CSS ou JavaScript.

## 3. Escopo

### Incluído

- tokens de cor, superfície, tipografia, raio, sombra e foco no child theme;
- aplicação dos tokens a header, navegação, botões, cards, campos, badges, footer, WooCommerce cart/checkout e estados interativos;
- carregamento performático de Nunito Sans, com fallback e `font-display: swap`;
- footer verde-petróleo e banners escuros seguindo contraste aprovado;
- evolução compatível de `petshop/home-campaign` para suportar campanha editorial com eyebrow, título, texto, benefício, rótulo de CTA e destino editáveis no Gutenberg;
- manutenção da campanha de arte final existente, incluindo imagem desktop/mobile, alt e link;
- paridade visual de editor, desktop e mobile; documentação editorial e do sistema visual.

### Fora de escopo

- redesenhar logo, mascote, embalagem ou material impresso;
- criar imagens, fotografias ou campanhas comerciais;
- alterar a identidade de produto (cores reais dos acessórios);
- migrar automaticamente campanhas existentes de arte final para copy em blocos;
- alterar regras de negócio WooCommerce, catálogo, checkout ou o tema pai.

### Padrão de mídia obrigatório

| Uso | Desktop | Mobile | Regra de composição e edição |
| --- | --- | --- | --- |
| Hero institucional | 2400 × 900 px (8:3) | 1080 × 1350 px (4:5) | Copy em blocos Gutenberg à esquerda no desktop; assunto/foto no lado direito. Em mobile, usar imagem própria e preservar o ponto focal fora da área da copy. |
| Banner promocional ou sazonal editorial | 1920 × 640 px (3:1) | 1080 × 1350 px (4:5) | A imagem é apoio: reservar 40% para copy real e não inserir texto, preço, CTA ou logo na arte. |
| Banner de arte final | 1920 × 640 px (3:1) | 1080 × 1350 px (4:5) | Quando houver texto incorporado, as duas artes são obrigatórias; respeitar margem segura de 8% em todos os lados. |
| Imagem principal e galeria de produto | 1600 × 1600 px (1:1) | mesma mídia | Produto ocupa 75–85% do quadro, fundo limpo e cor fiel; sem texto, selo ou marca d’água. |
| Imagem de categoria/coleção | 1600 × 900 px (16:9) | 1080 × 1350 px (4:5), se houver copy sobreposta | Assunto no centro ou na lateral oposta à copy; categoria, imagem e alt são editados no WooCommerce/Biblioteca de mídia. |
| Foto editorial de apoio | 1600 × 1200 px (4:3) | 1080 × 1350 px (4:5), quando necessário | Usar em blocos Gutenberg com alt e ponto focal revisados no editor. |

As imagens devem ser enviadas pela Biblioteca de mídia em WebP como formato preferencial; PNG é reservado a transparência e JPEG é aceitável como fonte/fallback. WordPress deve gerar tamanhos responsivos, sem subir cópias manuais para cada largura. O plano validará composição, `srcset`, peso, alt e ponto focal em 390, 768, 1024 e 1440 px.

## 4. Inventário de conteúdo administrável

| Rota/área | Conteúdo | Origem de edição | Persistência |
| --- | --- | --- | --- |
| `/` — hero | Foto, alt, eyebrow, título, texto, CTAs e observação | **Páginas → Home**, blocos nativos `core/cover`, `core/group`, texto e botões | A migração só altera estrutura sem conteúdo gerenciado; nunca substitui `post_content` editado. |
| `/` — campanhas de arte final | Imagem desktop/mobile, alt, link e rótulo interno | **Páginas → Home**, `petshop/home-campaign` | Atributos atuais permanecem compatíveis. |
| `/` — campanhas editoriais | Imagem de apoio, alt, eyebrow, título, texto, benefício, CTA e destino | **Páginas → Home**, variante visual do bloco de campanha, com todos os campos no canvas/inspector | Novos atributos serializados; versões antigas recebem depreciação e fixture. |
| Header, barra promocional e footer | Logo, texto promocional, atendimento, dados institucionais e links | Logo/menus/Customizer já existentes | Não regravar `theme_mod`, menus ou mídia existentes. |
| Catálogo, PDP, carrinho e checkout | Produtos, preço, mídia, atributos e textos WooCommerce | WooCommerce + Biblioteca de mídia | Apenas estilo; nenhum dado é migrado. |

## 5. Arquitetura e arquivos previstos

| Área | Arquivos previstos | Responsabilidade |
| --- | --- | --- |
| Tema | `wp-content/themes/petshop-theme/style.css` | Tokens semânticos, componentes, tipografia, superfícies e breakpoints. |
| Tema | `wp-content/themes/petshop-theme/functions.php` ou enqueue dedicado no child theme | Registrar/enfileirar a fonte somente se não houver mecanismo existente mais apropriado. |
| Tema | `wp-content/themes/petshop-theme/assets/css/editor-storefront.css` | Paridade do canvas, sem duplicar o sistema de tokens. |
| Plugin | `wp-content/plugins/petshop-core/blocks/home-campaign/` | Atributos, UI do editor, serialização estável, deprecações e preview da variante editorial. |
| Plugin | `wp-content/plugins/petshop-core/includes/class-home-campaign-blocks.php` | Renderização segura de atributos, mídia e CTA, se a implementação continuar dinâmica. |
| Documentação | `docs/guia-edicao-home.md` | Como escolher arte final ou campanha editorial e editar cada item. |
| Documentação | `docs/guia-identidade-visual-autelle.md` | Cópia versionada do guia oficial aprovado, com tokens e regras de uso. |
| Validação | `scripts/validate-014-*.php`, `scripts/validate-014-*-browser.mjs` | Contraste, tokens, serialização, persistência e regressão visual. |

## 6. Sessões de implementação

### Sessão 01 — Baseline e tokens semânticos

- [ ] Registrar screenshots e estilos computados das rotas Home, loja, categoria, PDP, carrinho e checkout em 390, 768, 1024 e 1440 px.
- [ ] Substituir nomes ambíguos de token por papéis semânticos, mantendo aliases temporários quando isso reduzir regressão.
- [ ] Definir `teal-900`, `teal-700`, `teal-500`, `aqua-400`, `orange-600`, `orange-500`, `orange-action`, neutros, cream, superfícies, foco, erro, raios e sombras.
- [ ] Registrar no guia de mídia as dimensões, proporções, áreas seguras, formatos, peso-alvo e ponto focal de hero, campanhas, produto, categoria e fotografia editorial.
- [ ] Ajustar a renderização dos banners para respeitar a proporção 3:1 sem corte destrutivo; usar `<picture>` e fonte mobile quando cadastrada.
- [ ] Aplicar `teal-900` no rodapé e superfícies institucionais; manter laranja como exceção de alta prioridade.
- [ ] Ajustar raios para 10/16/24 px e usar sombra teal suave apenas em cards e campanhas de destaque.

**Gate verificável**

- [ ] Não há hex de marca solto fora da declaração de tokens e exceções documentadas.
- [ ] Header, footer, botões, cards, campos, badges e controles usam tokens semânticos.
- [ ] A proporção visual preserva maioria clara/neutra e laranja abaixo de 10% nas superfícies de interface.
- [ ] Hero, banners e mídia de produto preservam ponto focal e área segura nas quatro larguras de validação.
- [ ] A Home não perde overflow, hierarquia ou comportamento responsivo.

### Sessão 02 — Tipografia e acessibilidade de componentes

- [ ] Carregar Nunito Sans com pesos estritamente necessários e fallback de sistema; medir ausência de bloqueio de renderização.
- [ ] Aplicar pesos e entrelinhas da hierarquia a H1, títulos de seção, copy, microtexto e botões.
- [ ] Atualizar estados normal, hover, ativo, disabled e foco de links, botões, inputs, checkbox, controles do carrossel e componentes WooCommerce.
- [ ] Garantir que ações principais tenham contraste mínimo AA; usar `orange-action`, e não `orange-600`, quando texto branco exigir 4,5:1.
- [ ] Manter o foco azul funcional até que uma alternativa de marca seja comprovadamente equivalente em contraste e visibilidade.

**Gate verificável**

- [ ] Texto normal e CTAs passam em 4,5:1; texto grande e componentes não textuais passam nos limiares aplicáveis da WCAG AA.
- [ ] Nenhuma informação depende exclusivamente de cor.
- [ ] Fontes, fallbacks, layout e foco permanecem legíveis sem JavaScript e durante o carregamento da fonte.

### Sessão 03 — Campanhas Gutenberg com copy administrável

- [ ] Manter a modalidade atual “arte final” para banners com texto incorporado, sem alterar seus atributos ou HTML salvo.
- [ ] Adicionar uma modalidade “campanha editorial” ao `petshop/home-campaign`, com mídia de apoio e campos de copy/CTA visíveis no canvas e editáveis no inspector.
- [ ] Garantir que imagem, alt, textos, link e rótulo de CTA sejam salvos em `post_content`, não em opções opacas ou strings PHP.
- [ ] Para markup/atributos alterados, adicionar entrada `deprecated` com `save` da versão anterior e fixtures de conteúdo legado; não alterar o nome do bloco.
- [ ] Reutilizar `useInnerBlocksProps` apenas se a composição exigir blocos-filho. Caso campos do próprio bloco ofereçam UX mais estável, documentar a decisão e manter preview visual equivalente ao storefront.
- [ ] Exibir no front somente campanhas completas, com HTML semântico, link/CTA único e alt contextual.

**Gate verificável**

- [ ] Uma campanha legada salva/recarrega sem “bloco inválido” e renderiza como antes.
- [ ] O cliente cria uma campanha editorial em **Páginas → Home** sem shortcode, código ou tela paralela.
- [ ] Copy, imagem, alt e CTA persistem após salvar, recarregar e reprovisionar.
- [ ] Desktop e mobile mantêm área segura para copy e não cortam elementos essenciais.

### Sessão 04 — Documentação, regressão e aceite

- [ ] Versionar uma cópia do guia oficial em `docs/`, mantendo o arquivo original fornecido pelo cliente intacto fora do repositório.
- [ ] Atualizar `docs/guia-edicao-home.md` com a escolha entre arte final e campanha editorial, incluindo edição de alt e imagem mobile.
- [ ] Criar/atualizar gates para tokens, contraste, blocos novos/legados, conteúdo salvo e ausência de sobrescrita editorial.
- [ ] Executar `npm run validate`, os gates específicos do plano e a matriz manual desktop/mobile.
- [ ] Verificar logs de PHP/console e atualizar `Plans/STATUS.md` somente após evidência completa.

**Gate verificável**

- [ ] O guia e a documentação de edição descrevem as interfaces finais e a origem de cada conteúdo.
- [ ] Nenhuma atualização de código sobrescreve alteração editorial do cliente.
- [ ] Home, loja, categoria, PDP, carrinho e checkout não têm regressão de contraste, foco, overflow ou layout.

## 7. Riscos e mitigação

| Risco | Mitigação |
| --- | --- |
| Aplicar laranja literal com texto branco reduz acessibilidade | Usar `orange-action` escuro para texto branco e validar cada par de cores. |
| Troca de fonte causa CLS ou piora de desempenho | Pesos mínimos, `font-display: swap`, fallback métrico e medição nas rotas críticas. |
| Evolução do bloco invalida campanhas existentes | Não mudar nome do bloco; adicionar deprecações, fixtures e teste de abrir/salvar conteúdo legado. |
| Copy passa a depender de imagem | Manter arte final como opção, mas disponibilizar variante editorial com texto real para campanhas recorrentes. |
| Guia diverge do logo original | Exigir confirmação de grafia e ativos originais antes de publicar mudanças de nome ou marca. |
| Verde escuro torna a loja pesada | Reservar teal-900 a áreas institucionais/banners e preservar 55–70% de superfícies claras. |

## 8. Critério de conclusão

O Plano 014 só poderá ser concluído quando o storefront apresentar a paleta e a tipografia aprovadas sem regressão de acessibilidade; quando CTAs, foco e estados passarem os gates de contraste; quando footer e banners institucionais expressarem o verde-petróleo; e quando, em **Páginas → Home**, o cliente puder manter uma arte final ou criar uma campanha com imagem, alt, copy, benefício e CTA editáveis, com todas as edições preservadas após atualização e reprovisionamento.
