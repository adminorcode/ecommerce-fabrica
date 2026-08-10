# Plano 016 — Vitrine de produtos selecionável no Gutenberg

**Status:** Concluído
**Data:** 2026-08-09  
**Branch sugerida:** `016-vitrine-produtos-gutenberg`  
**Dependências:** Planos 010 e 011 concluídos; infraestrutura de blocos do `petshop-core` disponível.  
**Relacionamento:** substitui os shortcodes de grade das quatro vitrines da Home por instâncias de um único bloco visual, preservando os cabeçalhos editoriais existentes.

## 1. Objetivo

Permitir que o cliente escolha, visualize, ordene e altere os produtos das vitrines da Home diretamente em **Páginas → Home**, sem conhecer IDs, slugs ou sintaxe de shortcode.

Será criado um único bloco dinâmico `petshop/product-grid`, chamado **Vitrine de produtos** no inseridor. As quatro vitrines atuais serão quatro instâncias do mesmo componente, cada uma com atributos próprios. Variações do Gutenberg fornecerão configurações iniciais convenientes, mas não criarão tipos de bloco nem renderizadores duplicados.

## 2. Decisão de produto e escopo

### Bloco único e variações

O bloco oferecerá estes modos de seleção:

| Modo | Uso | Regra |
| --- | --- | --- |
| Produtos específicos | Curadoria manual | Busca produtos por nome ou SKU, salva IDs e respeita a ordem definida pelo cliente. |
| Por categoria | Kits e vitrines temáticas | Permite selecionar uma ou mais categorias e ordenar os resultados. |
| Mais vendidos | Destaques da loja | Consulta produtos visíveis por popularidade. |
| Categorias sazonais | Coleção da estação | Mantém a regra atual de categorias marcadas como sazonais e visíveis no menu. |

O inseridor poderá apresentar as variações **Mais vendidos**, **Por categoria**, **Coleção sazonal** e **Seleção manual**. Todas usarão o nome estável `petshop/product-grid` e o mesmo `render_callback`.

### Configurações do bloco

- modo de seleção;
- produtos específicos, com busca por nome/SKU, remoção e reordenação;
- uma ou mais categorias, escolhidas pelo nome;
- quantidade de produtos, entre 1 e 12;
- número de colunas no desktop, entre 2 e 6;
- ordenação por data, popularidade, título, preço ou ordem manual, conforme o modo;
- direção crescente ou decrescente quando aplicável;
- prévia da grade dentro do editor.

Título, introdução e CTA **não** serão atributos da vitrine. Eles continuarão em blocos nativos `core/heading`, `core/paragraph` e link/botão na Home, preservando edição visual e sem misturar conteúdo editorial com a consulta WooCommerce.

### Fora de escopo

- criar quatro blocos ou quatro renderizadores diferentes;
- painel administrativo separado, CPT, widget, Customizer ou shortcode novo como interface principal;
- editar preço, estoque, imagem, nome ou categoria do produto dentro do bloco;
- paginação, carrossel, carregamento infinito ou personalização individual do card;
- alterar templates internos do WooCommerce, Blocksy pai ou WordPress Core;
- remover imediatamente os shortcodes PHP legados, que permanecerão como compatibilidade para outras páginas.

## 3. Conteúdo administrável por rota

### Rota `/` — Home

| Item | Onde o cliente edita | Regra de exibição |
| --- | --- | --- |
| Título de cada seção | **Páginas → Home**, bloco `Título` | Continua independente da consulta de produtos. |
| Texto introdutório | **Páginas → Home**, bloco `Parágrafo` | Opcional e livremente editável. |
| Rótulo e destino de “Ver todos” | **Páginas → Home**, link ou botão nativo | Não é gerado pelo bloco de produtos. |
| Estratégia de seleção | Bloco `Vitrine de produtos` → painel lateral | Manual, categoria, mais vendidos ou sazonal. |
| Produtos específicos e ordem | Mesmo bloco → busca e lista ordenável | IDs são salvos; nome, imagem, preço e estoque continuam vindo do WooCommerce. |
| Categorias | Mesmo bloco → seletor por nome | IDs de termos são salvos para resistir a mudança de slug. |
| Quantidade, colunas e ordenação | Mesmo bloco → painel lateral | Valores são sanitizados e limitados pelo componente. |
| Dados e imagens dos cards | **Produtos → Todos os produtos** e Biblioteca de mídia | O bloco sempre consulta os dados atuais do WooCommerce. |

Não haverá texto comercial, imagem de produto, URL editorial ou lista de produtos fixos em PHP, CSS ou JavaScript. Textos funcionais do editor serão traduzíveis pelo domínio `petshop-core`.

## 4. Experiência no Gutenberg e no storefront

### Editor

- O bloco será identificado visualmente como **Vitrine de produtos** na categoria Petshop.
- O estado inicial explicará os quatro modos e permitirá escolher um deles sem abrir a visualização de código.
- A seleção manual terá busca incremental por nome ou SKU, lista dos itens escolhidos, remoção e reordenação operáveis por teclado.
- A seleção por categoria mostrará nomes e hierarquia; o cliente não precisará digitar slugs.
- A prévia será renderizada com dados atuais do WooCommerce e mostrará estados de carregamento, vazio e erro sem impedir o salvamento.
- Alterar um controle atualizará a prévia sem recarregar a página inteira.
- Salvar, fechar e reabrir a Home deverá preservar todos os atributos sem aviso de “bloco inválido”.

### Storefront

- A renderização será dinâmica no servidor; o conteúdo salvo guardará somente atributos de seleção e apresentação.
- Os cards continuarão usando o loop oficial do WooCommerce e os hooks atuais do tema, preservando preço, badges, botão de compra e wishlist.
- Produtos não publicados, invisíveis ou removidos não gerarão card quebrado.
- No modo manual, os produtos válidos manterão a ordem definida pelo cliente.
- Uma consulta sem resultados não renderizará wrapper, título duplicado nem espaço residual; a seção externa existente continuará sujeita à ocultação segura já usada na Home.
- O bloco não terá JavaScript próprio no storefront se a grade não exigir interação adicional.

## 5. Arquitetura e arquivos previstos

| Área | Arquivos previstos | Responsabilidade |
| --- | --- | --- |
| Metadados | `wp-content/plugins/petshop-core/blocks/product-grid/block.json` | `apiVersion: 3`, atributos, supports e script do editor. |
| Editor | `wp-content/plugins/petshop-core/blocks/product-grid/` | Registro JS, controles, busca, reordenação, variações e prévia. |
| Build | `wp-content/plugins/petshop-core/package.json` e `blocks/build/` | Incluir a nova entrada no `@wordpress/scripts` e versionar artefatos exigidos pelo runtime. |
| Plugin | `wp-content/plugins/petshop-core/includes/` | Classe pequena para registro, sanitização, consulta e renderização dinâmica. |
| Migração | `wp-content/plugins/petshop-core/includes/Migration/` | Schema seguinte da Home e transformação estrutural segura dos shortcodes conhecidos. |
| Tema/editor | `wp-content/themes/petshop-theme/assets/css/editor-storefront.css` | Paridade mínima da grade e estados editoriais no iframe. |
| Documentação | `docs/guia-edicao-home.md` | Escolha de modo, busca, categorias, ordem, quantidade e manutenção dos produtos. |
| Validação | `scripts/validate-016-*.php` e `scripts/validate-016-*-browser.mjs` | Registro, atributos, consultas, migração, persistência e fluxo visual. |

### Modelo técnico

- Bloco dinâmico: `save: null` e renderização PHP registrada a partir dos metadados.
- Atributos previstos: `selectionMode`, `productIds`, `categoryIds`, `limit`, `columns`, `orderby` e `order`.
- IDs serão normalizados, deduplicados e validados; valores de enumeração terão allowlist.
- A busca do editor usará APIs públicas do WordPress/WooCommerce. Se produtos ou categorias não estiverem disponíveis em `core-data`, será criado endpoint REST próprio somente de leitura, com `permission_callback` exigindo capacidade `edit_products`; não usar APIs internas não documentadas do WooCommerce.
- A prévia poderá usar `ServerSideRender`, com atualização controlada para evitar consultas excessivas.
- A renderização reutilizará o loop e os hooks públicos do WooCommerce, sem copiar templates do plugin.

## 6. Migração das vitrines existentes

A Home atual possui quatro blocos `core/shortcode` de grade. A migração versionada transformará somente shortcodes reconhecidos, preservando os grupos, títulos, introduções e CTAs ao redor:

| Shortcode atual | Configuração resultante |
| --- | --- |
| `petshop_featured_products_grid` | `selectionMode: popular` |
| `petshop_kits_section_grid` | `selectionMode: category`, com a categoria configurada |
| `petshop_seasonal_products_grid` | `selectionMode: seasonal` |
| `petshop_product_showcase_grid` | `selectionMode: category`, preservando categorias, limite, colunas e ordenação |

Regras obrigatórias da migração:

- usar parsing de blocos/shortcodes; não fazer substituição global cega em `post_content`;
- converter apenas atributos suportados e sanitizados;
- deixar inalterado qualquer shortcode desconhecido, incompleto ou usado fora do escopo seguro;
- preservar cópia, links, ordem das seções e customizações editoriais ao redor;
- criar revisão antes da escrita e só avançar o schema após confirmar persistência;
- não reexecutar a transformação em conteúdo já migrado;
- reprovisionamento posterior não poderá alterar atributos escolhidos pelo cliente.

## 7. Sessões de implementação

### Sessão 01 — Contrato, registro e renderização dinâmica

- [x] Criar `petshop/product-grid` com `block.json` em API v3 e atributos tipados.
- [x] Registrar um único bloco e suas quatro variações, sem duplicar renderizadores.
- [x] Implementar sanitização e consulta para os modos manual, categoria, popular e sazonal.
- [x] Reutilizar os cards e hooks oficiais do loop WooCommerce.
- [x] Garantir saída vazia segura e ordem determinística.

**Gate verificável**

- [x] Há exatamente um tipo de bloco `petshop/product-grid` registrado.
- [x] As quatro variações inserem o mesmo bloco com atributos iniciais diferentes.
- [x] Cada modo retorna apenas produtos publicados e visíveis.
- [x] Seleção manual respeita a ordem dos IDs válidos.
- [x] Consulta vazia não produz espaço residual nem erro PHP.

### Sessão 02 — Controles editoriais e prévia

- [x] Criar escolha visual do modo de seleção.
- [x] Implementar busca por nome/SKU, seleção, remoção e reordenação manual.
- [x] Implementar seletor de categorias por nome e hierarquia.
- [x] Adicionar quantidade, colunas e ordenação com opções válidas para cada modo.
- [x] Exibir prévia, carregamento, vazio e erro no canvas.
- [x] Garantir rótulos, instruções, foco e operação por teclado nos controles próprios.

**Gate verificável**

- [x] O cliente configura a vitrine sem visualizar ou editar IDs, slugs ou shortcode.
- [x] Produtos podem ser encontrados por nome e SKU.
- [x] A ordem manual persiste após salvar e recarregar.
- [x] Categorias e demais atributos persistem após salvar e recarregar.
- [x] Não aparece aviso de bloco inválido nem erro no console.

### Sessão 03 — Migração segura da Home

- [x] Adicionar o próximo schema da Home ao registro do migrador.
- [x] Converter os quatro shortcodes reconhecidos em instâncias do bloco único.
- [x] Preservar cabeçalhos, introduções, CTAs, classes e ordem existentes.
- [x] Manter shortcodes legados registrados para conteúdo fora da Home.
- [x] Criar fixtures para Home padrão, Home customizada e conteúdo já migrado.

**Gate verificável**

- [x] A Home atual passa a conter quatro instâncias de `petshop/product-grid`.
- [x] O HTML funcional das quatro vitrines permanece equivalente antes e depois da migração.
- [x] Texto ou link alterado pelo cliente ao redor das grades não é sobrescrito.
- [x] Segunda execução da migração não modifica `post_content`.
- [x] Shortcode desconhecido ou fora do padrão seguro permanece intacto.

### Sessão 04 — Documentação, regressão e aceite

- [x] Atualizar `docs/guia-edicao-home.md` com instruções não técnicas e screenshots quando disponíveis.
- [x] Criar gates PHP para registro, sanitização, consultas e migração.
- [x] Criar gate de navegador para inserção, configuração, salvamento, recarga e preview.
- [x] Validar Home em 390, 768, 1024 e 1440 px, incluindo grades vazias e incompletas.
- [x] Executar build, PHPUnit, `npm run validate` e revisar logs PHP/console.

**Gate verificável**

- [x] Cliente consegue configurar as quatro vitrines inteiramente em **Páginas → Home**.
- [x] Editor e storefront exibem os mesmos produtos e a mesma ordem para a seleção manual.
- [x] Cards mantêm preço, estoque, badge, compra e wishlist existentes.
- [x] Reprovisionamento não desfaz escolhas, ordem, categorias ou conteúdo editorial.
- [x] Guia administrativo descreve os quatro modos e a origem dos dados dos cards.

## 8. Riscos e mitigação

| Risco | Mitigação |
| --- | --- |
| Criar quatro componentes quase idênticos | Um único nome de bloco, um contrato de atributos e um renderizador; variações são apenas presets. |
| Editor consultar produtos em excesso | Busca paginada/debounced, limite de resultados e prévia limitada à quantidade configurada. |
| Produto removido quebrar seleção manual | Filtrar IDs inválidos no render sem alterar silenciosamente os atributos salvos. |
| Migração sobrescrever conteúdo editorial | Transformar somente o bloco de shortcode reconhecido e comparar o conteúdo persistido antes de avançar o schema. |
| Uso de componente interno do WooCommerce quebrar após atualização | Usar `@wordpress/components`, `@wordpress/core-data` e APIs públicas; endpoint próprio restrito somente como fallback documentado. |
| Prévia divergir do storefront | Renderização dinâmica compartilhada e gate comparando IDs e ordem nas duas superfícies. |
| Bloco ficar inválido após evolução | Nome e atributos tratados como API estável; deprecações/migrações obrigatórias antes de mudança incompatível. |

## 9. Critério de conclusão

O Plano 016 só poderá ser marcado como concluído quando existir exatamente um bloco `petshop/product-grid`; quando suas variações permitirem configurar por produtos específicos, categoria, popularidade ou sazonalidade; quando as quatro vitrines atuais estiverem migradas sem perda editorial; e quando o cliente conseguir buscar, selecionar, ordenar, salvar e reabrir produtos em **Páginas → Home** sem editar shortcode, slug, ID ou código.
