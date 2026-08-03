# Plano 011 — Banners gerenciáveis da Home

**Status:** Pendente  
**Data:** 2026-08-03  
**Branch sugerida:** `codex/011-banners-gerenciaveis-home`  
**Dependências:** Plano 009 concluído; Home e editor Gutenberg entregues pelos Planos 004b e 010  
**Relacionamento:** adiciona campanhas secundárias à Home sem alterar o hero institucional, os cards de produto ou as vitrines do Plano 010.

## 1. Objetivo

Permitir que o cliente crie, edite, ordene e remova banners de campanha diretamente em **Páginas → Home**, com uma experiência visual no Gutenberg equivalente à faixa exibida na loja.

O recurso deve funcionar como banner estático quando houver uma campanha e como carrossel de navegação manual quando houver duas ou mais. Não haverá shortcode, tipo de post, tela administrativa separada, imagem, texto comercial ou URL de campanha dependente de código.

## 2. Decisão de produto e escopo

Será criado um bloco contêiner `petshop/home-campaigns`, disponível para a Home, com blocos-filho `petshop/home-campaign`.

- O contêiner organiza a faixa e aceita somente blocos-filho de campanha.
- Cada bloco-filho representa uma campanha e é editado visualmente no canvas do Gutenberg.
- A ordem no editor é a ordem de exibição; o cliente poderá arrastar, duplicar ou remover uma campanha usando os controles nativos de blocos.
- A faixa será inserida na Home entre a faixa de benefícios e a seção de categorias. Ela é secundária ao hero institucional.
- Sem blocos-filho válidos, o contêiner não renderiza saída nem espaço no storefront.
- Um bloco-filho válido renderiza banner estático clicável; dois ou mais habilitam controles manuais de anterior/próximo e indicadores.
- O carrossel não terá autoplay, rolagem automática, parallax ou animação essencial para compreensão da oferta.

### Fora de escopo

- painel separado de campanhas, CPT, taxonomias ou shortcode;
- agendamento de publicação por banner nesta primeira versão;
- geração de artes, descontos, preços ou alegações comerciais;
- alterar hero, header, vitrines de produto, WooCommerce, Blocksy pai ou WordPress Core.

## 3. Conteúdo administrável por rota

### Rota `/` — Home

| Item | Onde o cliente edita | Regra de exibição |
| --- | --- | --- |
| Posição da faixa | **Páginas → Home**, bloco `Banners de campanha` | Após `petshop-benefits` e antes de “Compre por categoria”. |
| Imagem desktop | Bloco-filho `Banner de campanha` → seletor da Biblioteca de mídia | Obrigatória para publicar o banner. |
| Imagem mobile | Mesmo bloco → seletor da Biblioteca de mídia | Opcional; quando ausente, usa a imagem desktop. |
| Texto alternativo contextual | Mesmo bloco → painel lateral do bloco | Obrigatório e salvo no conteúdo da Home. |
| Link de destino | Mesmo bloco → campo de URL com seletor de link WordPress | Obrigatório; aceita produto, categoria, página ou URL válida. |
| Rótulo interno do editor | Mesmo bloco → painel lateral | Opcional, visível somente no editor para identificar a campanha. |
| Ordem, duplicação e remoção | Controles nativos do Gutenberg | Seguem a ordem dos blocos-filho salvos. |
| Setas e indicadores | Gerados pelo bloco, com textos funcionais traduzíveis | Só aparecem com duas ou mais campanhas válidas. |

O nome interno não é copy comercial e não aparece na loja. Qualquer texto presente na própria arte continua parte da imagem administrável fornecida pelo cliente.

## 4. Experiência no editor e no storefront

### Gutenberg

- Ao inserir `Banners de campanha`, o editor mostra um estado vazio claro com a ação **Adicionar banner**.
- Cada banner mostra a prévia da imagem desktop no canvas, o destino e um aviso objetivo se faltar imagem, alt ou link.
- Os seletores de mídia usam a Biblioteca de mídia do WordPress; o cliente pode enviar, trocar ou reutilizar imagens sem editar código.
- A prévia mobile será verificável no painel lateral e na visualização responsiva do editor.
- O bloco usará `InnerBlocks`, restringindo os filhos ao bloco de campanha e preservando a edição, revisão e histórico nativos da página.

### Storefront

- Largura máxima e espaçamentos reutilizam tokens existentes do tema; a faixa não ocupa a viewport inteira nem substitui o H1.
- A imagem é o único CTA visual de cada slide: todo o banner é um link sem botão concorrente.
- Desktop usa arte horizontal; em telas mobile, quando cadastrada, usa a arte mobile por meio de `<picture>`.
- Os controles têm nome acessível, foco visível e área mínima de toque de 44 × 44 px.
- Teclado: Tab alcança link, setas e indicadores; Enter/Espaço acionam controles. A troca respeita `prefers-reduced-motion`.
- A mudança de slide anuncia o item ativo de forma não intrusiva e não move o foco do usuário.

## 5. Arquitetura e arquivos previstos

| Área | Arquivos previstos | Responsabilidade |
| --- | --- | --- |
| Plugin | `wp-content/plugins/petshop-core/blocks/home-campaigns/` | Metadados `block.json`, registro dos blocos, componentes de edição, serialização estática, estilos e script de interação quando necessário. |
| Plugin | `wp-content/plugins/petshop-core/petshop-core.php` ou bootstrap próprio | Registrar blocos via `register_block_type()` a partir dos metadados, sem regras no tema. |
| Tema | `wp-content/themes/petshop-theme/style.css` | Tokens, layout final, foco, breakpoints e estilos compartilhados do storefront. |
| Tema | `wp-content/themes/petshop-theme/assets/css/editor-storefront.css` | Somente ajustes necessários ao canvas Gutenberg; não duplicar tokens ou estilos extensos. |
| Documentação | `docs/guia-edicao-home.md` | Instruções de inserção, edição, imagem mobile, alt, link, ordem e remoção. |
| Validação | `scripts/validate-011-*.php` e `scripts/validate-011-*-browser.mjs` | Gates de serialização, persistência editorial, acessibilidade e responsividade. |

Os atributos dos blocos serão serializados em `post_content` da página Home. A migração existente da Home não poderá inserir, atualizar ou substituir campanhas salvas pelo cliente; em páginas já personalizadas, não realizará alteração alguma nessa seção.

## 6. Sessões de implementação

### Sessão 01 — Base dos blocos Gutenberg

**Status:** [ ] Pendente

- confirmar a versão WordPress e o tooling de blocos já disponível no repositório;
- criar os metadados e o registro de `petshop/home-campaigns` e `petshop/home-campaign`;
- implementar `InnerBlocks` com filho permitido e controles editoriais nativos;
- criar atributos serializados para mídia desktop/mobile, alt contextual, URL e rótulo interno;
- produzir estado vazio útil e validação editorial sem impedir salvamento de rascunhos;
- garantir inserção, salvamento, recarga e edição sem “bloco inválido”.

**Gate verificável**

- [ ] ambos os blocos aparecem no inseridor adequado;
- [ ] o bloco-pai aceita apenas campanhas-filhas;
- [ ] imagem, alt, URL e ordem persistem após salvar e recarregar a Home;
- [ ] duplicar e remover uma campanha preserva a integridade das demais;
- [ ] não há shortcode no conteúdo nem tela administrativa paralela.

### Sessão 02 — Renderização e interação acessível

**Status:** [ ] Pendente

- renderizar semanticamente a campanha estática e o carrossel manual;
- usar `<picture>` quando existir arte mobile e `alt` salvo pelo cliente;
- mostrar apenas campanhas completas (imagem desktop, alt e link);
- criar controles de anterior/próximo e indicadores somente com dois ou mais banners;
- implementar navegação sem autoplay e com movimento reduzido;
- impedir faixa vazia e whitespace residual.

**Gate verificável**

- [ ] uma campanha gera exatamente um banner clicável, sem controles de carrossel;
- [ ] duas ou mais exibem controles operáveis por mouse, toque e teclado;
- [ ] não há troca automática de slide;
- [ ] imagem mobile é escolhida em 390 px e desktop em 1440 px quando ambas existem;
- [ ] campanha incompleta não gera link quebrado, imagem sem alt ou espaço vazio;
- [ ] foco e contraste atendem WCAG AA.

### Sessão 03 — Layout, paridade e posicionamento na Home

**Status:** [ ] Pendente

- aplicar tokens existentes de cor, raio, espaço e foco, sem hex solto;
- posicionar o bloco após benefícios e antes das categorias na Home, sem tocar no hero;
- assegurar paridade suficiente entre a prévia Gutenberg e storefront;
- validar em 390, 768, 1024 e 1440 px após carregamento de imagens;
- revisar que a faixa não cria overflow, corte indevido ou competição com o conteúdo principal.

**Gate verificável**

- [ ] desktop e mobile não apresentam overflow horizontal;
- [ ] imagem, foco e controles permanecem legíveis sobre qualquer arte cadastrada;
- [ ] editor e loja preservam hierarquia e proporção equivalentes;
- [ ] a Home sem banners válidos não contém heading, controles ou espaço residual;
- [ ] hero institucional continua sendo a primeira mensagem comercial da página.

### Sessão 04 — Documentação, persistência e regressão

**Status:** [ ] Pendente

- atualizar o guia de edição da Home com instruções não técnicas;
- testar criação de campanha, troca de imagens, mudança de alt/link e reordenação;
- executar reprovisionamento/migração de storefront com campanha salva;
- registrar evidências visuais e resultados dos scripts de validação;
- revisar Home, editor e ausência de erros fatais no runtime.

**Gate verificável**

- [ ] o cliente pode concluir todo o fluxo pelo Gutenberg sem alterar código;
- [ ] mudanças editoriais persistem após atualização e reprovisionamento;
- [ ] scripts PHP e browser do plano passam;
- [ ] `docs/guia-edicao-home.md` descreve a interface final;
- [ ] não há regressão nas seções existentes da Home.

## 7. Riscos e mitigação

| Risco | Mitigação |
| --- | --- |
| Bloco salvo torna-se inválido após mudança de markup | Definir serialização estável e adicionar deprecações/migração antes de qualquer alteração incompatível. |
| Arte contém texto e perde legibilidade em mobile | Permitir imagem mobile própria e validar em 390 px. |
| Carrossel reduz descoberta de ofertas | Sem autoplay; indicadores e setas visíveis; primeira campanha sempre presente. |
| Atualização sobrescreve conteúdo da Home | Não provisionar campanhas; preservar `post_content` e validar reprovisionamento. |
| Editor diverge visualmente da loja | Reutilizar `style.css`, limitar overrides de iframe e validar ambas as superfícies. |

## 8. Critério de conclusão

O Plano 011 só poderá ser marcado como concluído quando todos os gates estiverem aprovados e for demonstrado que um cliente consegue, em **Páginas → Home**, inserir, editar, substituir, ordenar e remover banners com imagens, textos alternativos e links sem shortcode, sem painel paralelo e sem alteração de código.
