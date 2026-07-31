# Plano 004 — Identidade visual e navegabilidade da loja

**Status:** Em andamento — implementação concluída; validação humana com leitor de tela pendente
**Data:** 2026-07-31  
**Objetivo:** transformar a loja WooCommerce em um catálogo de acessórios pet com a identidade visual e as categorias reais do contratante, navegação comercial clara e experiência mobile-first. A Moda Bicho é referência secundária de merchandising; textos, imagens, marca e interface devem ser originais.

## 1. Decisões de produto e arquitetura

- Manter WordPress + WooCommerce + Blocksy e o child theme `petshop-theme` como storefront.
- Não criar agora uma aplicação React/Next separada. Usar os blocos nativos do WooCommerce (que já utilizam JavaScript moderno) e adicionar JavaScript somente para interações pontuais e justificadas.
- Manter WooCommerce como fonte de verdade para produto, estoque, carrinho, checkout, pedido, pagamento e cupons.
- Todo texto editorial/comercial e toda foto ou imagem de conteúdo devem ser editáveis pelo cliente no WordPress; o código pode apenas provisionar valores iniciais sem sobrescrever alterações posteriores.
- Reservar **Personalize** como futura área independente de navegação. Configurador de acessórios, prévia e regras de preço ficam fora deste plano e não podem bloquear o catálogo inicial.
- Não copiar marca, imagens, avaliações, preços, campanhas ou código da referência. Exceção: a copy provisória do hero fornecida diretamente pelo usuário pode ser usada como conteúdo inicial editável.

### Critérios para reavaliar React headless

Reavaliar somente se, após a loja base, houver um configurador visual que não possa ser entregue como extensão isolada do WordPress, uma necessidade comprovada de experiência altamente interativa em múltiplas rotas, ou metas mensuráveis de desempenho que o tema não atinja. A decisão exigirá um plano próprio para sessões de carrinho, checkout, SEO, pré-visualização editorial e compatibilidade de pagamentos.

## 2. Referência estrutural

### Hierarquia de referência

1. **Catálogo atual do contratante — Auteliê Moda Pet:** fonte de verdade para logo, paleta, identidade, tom de marca, categorias, nomenclatura e ordem comercial.
2. **Moda Bicho:** referência secundária para padrões de navegação e merchandising do segmento, sem reproduzir sua interface ou conteúdo.

### Identidade e catálogo do contratante

- Fonte: https://www.auteliemodapet.com.br/pt
- Usar o logo original disponibilizado pelo contratante; não redesenhá-lo nem extrair uma versão de baixa resolução do site.
- Adotar a paleta da pasta `idvisual/` como fonte oficial dos tokens. Ela supera qualquer variação observada no catálogo atual e deve ser aplicada após validação de contraste.
- Preservar a nomenclatura e a ordem das categorias existentes, salvo aprovação explícita: Promoções, Adesivos, Babador, Bandanas, Colarinhos, Conjuntos, Copa, Festa Junina, Gargantilhas, Gravatas, Inverno, Laços e Penteados. `Laços Adesivos` deve ser tratado como subcategoria de Laços.
- Categorias sazonais, como Copa, Festa Junina, Inverno e Dia dos Pais, devem poder ser exibidas ou ocultadas sem alterar a estrutura principal.

### Ativos visuais fornecidos

A pasta versionada `idvisual/` é a biblioteca-fonte da marca. Os originais não devem ser sobrescritos; derivados otimizados para a web devem ser criados em local próprio do tema quando a implementação começar.

| Ativo | Uso aprovado | Observação |
| --- | --- | --- |
| `idvisual/LOGO PNG.png` | Logo principal no cabeçalho | PNG transparente horizontal, 458 × 140 px; usar em tamanho de exibição compatível com a resolução. |
| `idvisual/logo1.png` | Marca vertical em mobile, rodapé ou peças institucionais | PNG transparente, 230 × 254 px. Não substitui o logo horizontal no desktop. |
| `idvisual/logopn.png` e `idvisual/PNG 1.png` | Avatar, destaque institucional ou redes sociais | Variações circulares com mascote sobre fundo cinza. Não usar como logo padrão de navegação. |
| `idvisual/logo.jpg` | Referência/uso editorial ou impresso após conversão | JPEG CMYK sem transparência; não usar diretamente na web. |
| `idvisual/PALETA DE CORES.jpg` | Fonte oficial dos tokens de cor | Confirma `#5BC1C3` (turquesa), `#F37D35` (laranja) e `#E6E7E9` (cinza-claro). |

Tokens iniciais a validar no child theme:

```css
--color-brand-teal: #5BC1C3;
--color-brand-orange: #F37D35;
--color-surface-soft: #E6E7E9;
--color-ink: #373435;
```

O turquesa e o laranja não devem ser assumidos como cores de texto sobre fundo branco: cada combinação de texto, botão, foco e ícone deve passar na verificação de contraste.

Lacunas a solicitar ou produzir antes de publicar:

- logo vetorial (SVG, AI ou PDF) e versão horizontal de maior resolução;
- favicon e ícones para tela inicial;
- logos branco/monocromático para fundos escuros ou coloridos;
- definição da tipografia licenciada, se houver, e guia de fotografia/uso do mascote.

### Referência secundária de merchandising

A Moda Bicho orienta a organização comercial, não a reprodução visual:

1. aviso de benefício comercial/frete e canal de contato;
2. cabeçalho simples com busca, conta e carrinho;
3. categorias principais visíveis sem depender de busca;
4. vitrine inicial por coleções e intenção de compra;
5. blocos de prova social e chamada de recompra;
6. páginas de categoria que ajudam a escolher, não apenas listam produtos;
7. página de produto com contexto de uso, composição do kit e itens relacionados.

Fontes secundárias de referência:

- https://www.modabicho.com.br/
- https://www.modabicho.com.br/adesivo-pet
- https://www.modabicho.com.br/gravata-pet

## 3. Escopo

### Incluído

- Sistema visual original: cores, tipografia, espaçamento, ícones, botões, cards, badges e estados interativos.
- Cabeçalho, navegação desktop/mobile, busca, carrinho e breadcrumbs.
- Página inicial, arquivos de categoria, busca, página de produto, carrinho e checkout usando recursos compatíveis do WooCommerce.
- Taxonomia e vitrines baseadas primeiro no catálogo atual do contratante; a planilha de importação deve ser normalizada para essa taxonomia antes da carga.
- Blocos editoriais para mais vendidos, kits econômicos, seleção para groomers, novidades/sazonais, prova social e CTA de atendimento.
- Hero da Home em banner comercial full-bleed, seguindo a composição da referência (copy à esquerda e fotografia à direita), com imagem, legenda, título, texto, observação, CTA e destino integralmente editáveis no Gutenberg; o link deve aceitar página, coleção/categoria ou produto cadastrado.
- Acessibilidade, responsividade, SEO técnico básico e validação de desempenho.

### Excluído nesta fase

- Aplicação React/Next desacoplada.
- Configurador/personalização, cálculo de preço dinâmico ou prévia visual.
- Integração com ERP, meios de pagamento, frete ou CRM novos.
- Migração efetiva do catálogo, produção de fotos e redação definitiva das descrições; estes são pré-requisitos de conteúdo e devem ocorrer em plano próprio.
- Cópia de qualquer ativo ou conteúdo da Moda Bicho.

## 4. Implementação por etapas

### Etapa 1 — Diagnóstico e arquitetura de informação

1. Registrar um snapshot do ambiente e do conteúdo atual antes de alterar páginas, menus ou produtos.
2. Inventariar páginas, menus, widgets, templates Blocksy e blocos importados do Petsy que ainda estejam ativos.
3. Registrar no WooCommerce a taxonomia canônica do catálogo atual do contratante, incluindo categorias principais, subcategoria `Laços Adesivos`, ordem e regras de visibilidade sazonal.
4. Mapear cada categoria da planilha para a taxonomia canônica; exceções e produtos sem categoria não podem ser importados sem destino aprovado.
5. Definir o mapa de navegação:
   - topo: benefícios, atendimento e acesso rápido;
   - principal: categorias de compra, coleções e futura entrada `Personalize`;
   - rodapé: atendimento, políticas, envio e redes sociais;
   - rotas de apoio: busca, conta, carrinho e checkout.
6. Produzir um inventário de conteúdo necessário por rota: banner, título, texto, imagem, CTA, produtos, regra de seleção e origem administrativa de edição.

**Aceite:** a árvore de categorias e o mapa de navegação são aprovados, cada coleção tem uma regra de composição documentada, nenhuma rota depende de conteúdo da referência e todo texto ou imagem de conteúdo possui uma origem de edição no WordPress.

### Etapa 2 — Sistema visual original

1. Criar tokens no child theme a partir dos ativos em `idvisual/`: cores, tipografia, escala de espaçamento, sombras, bordas e breakpoints.
2. Definir direção visual própria adequada a acessórios pet: calor humano, acabamento artesanal/profissional, leitura clara de kits e foco em fotos de produto, sem perder reconhecimento da identidade atual.
3. Implementar componentes reutilizáveis para botões, links, badges de promoção/estoque, cards, títulos de seção, inputs e avisos.
4. Gerar cópias web otimizadas e acessíveis do logo aprovado, mantendo os originais em `idvisual/`; configurar favicon quando a versão for disponibilizada.
5. Configurar tipografia, cores globais e componentes do Blocksy sem editar o tema pai ou o WooCommerce.
5. Garantir contraste, foco visível, estados de erro e comportamento com teclado.

**Aceite:** todos os componentes têm estados normal, hover, foco, ativo e indisponível; não há contraste insuficiente ou informação transmitida somente por cor.

### Etapa 3 — Navegação e páginas comerciais

1. Implementar o cabeçalho e menu responsivo, incluindo busca, conta e minicarrinho conforme a capacidade nativa do tema/WooCommerce.
2. Configurar breadcrumbs e filtros de catálogo coerentes com a taxonomia aprovada.
3. Construir a home com blocos independentes e editáveis, incluindo textos, links e imagens:
   - hero original;
   - principais categorias;
   - kits e economia por quantidade;
   - mais vendidos;
   - seleção profissional para groomers;
   - coleção sazonal;
   - prova social e CTA de WhatsApp/atendimento, se aprovado.
4. Personalizar arquivos de categoria e busca para evidenciar foto, nome, preço, kit/quantidade, estoque e CTA de compra.
5. Personalizar a página de produto para explicar material, conteúdo do pacote, aplicação, variações relevantes, cuidados e produtos complementares.
6. Manter carrinho e checkout nos blocos oficiais do WooCommerce; validar extensões antes de qualquer customização.

**Aceite:** uma pessoa encontra uma categoria, filtra ou busca um produto, entende o que recebe no pacote e chega ao checkout em desktop e mobile sem rotas quebradas; o cliente altera textos e imagens de conteúdo pelo painel, sem editar código.

### Etapa 4 — Conteúdo e catálogo preparados para a interface

1. Concluir o saneamento de importação: SKU, categorias ausentes, preços zero, visibilidade, estoque, imagens e descrições.
2. Criar as categorias e coleções aprovadas antes de importar produtos.
3. Garantir imagem principal, texto alternativo e ao menos as informações comerciais mínimas para cada produto publicado.
4. Criar produtos relacionados por categoria, coleção e regra editorial, sem dependência de IDs fixos.
5. Configurar páginas de coleção para que um administrador possa trocar produtos, textos e imagens, inclusive texto alternativo, sem editar código.

**Aceite:** não há produto publicado sem imagem principal, preço válido, categoria, SKU e descrição comercial mínima; vitrines não mostram produtos ocultos ou sem estoque, salvo regra editorial explicitamente aprovada; reprovisionamentos preservam todos os textos e imagens salvos pelo cliente.

### Etapa 5 — Qualidade e lançamento local

1. Testar navegação e compra em Chrome, Firefox e Safari/iOS ou em emulação equivalente.
2. Validar teclado, leitor de tela em fluxos críticos, zoom de 200%, contraste e alvos de toque.
3. Medir homepage, categoria e produto com cache aquecido; corrigir imagens excessivas, fontes bloqueantes e recursos desnecessários.
4. Testar produto simples, produto sem estoque, cupom, alteração de quantidade, carrinho, checkout e conta.
5. Verificar títulos, meta descriptions, URLs, dados estruturados fornecidos pelo WooCommerce/tema, sitemap e páginas de política.
6. Registrar capturas, métricas e problemas conhecidos antes da aprovação.

**Aceite:** não há erro fatal, erro de console relevante, layout quebrado em mobile, bloqueio de compra ou regressão de carrinho/checkout; fluxos e métricas estão documentados.

## 5. Arquivos e responsabilidades previstos

| Área | Local previsto | Responsabilidade |
| --- | --- | --- |
| Originais de marca | `idvisual/` | fonte preservada de logo, paleta e mascote; não carregar diretamente sem otimização |
| Estilos e templates próprios | `wp-content/themes/petshop-theme/` | identidade, layout e comportamento visual, incluindo derivados web dos ativos aprovados |
| Regras WooCommerce próprias | `wp-content/plugins/petshop-core/` | somente regras de negócio ou integrações que não pertençam ao tema |
| Conteúdo editorial | WordPress/Gutenberg | banners, vitrines, textos e chamadas administráveis |
| Dados de catálogo | plano de importação separado | saneamento, mapeamento e importação repetível |

## 6. Riscos e dependências

- A planilha atual não contém imagens e possui categorias/descrições ausentes; a interface não deve mascarar essa lacuna nem substituir a taxonomia do catálogo atual por categorias inferidas.
- O tema Petsy importado pode ter componentes ou páginas que precisem ser substituídos de forma gradual; não remover plugins sem confirmar dependências.
- Ajustes de checkout devem respeitar a compatibilidade dos blocos e dos futuros gateways de pagamento.
- A personalização futura deve receber requisitos próprios antes de influenciar preços, estoque ou pedidos.
- Há uma modificação local não relacionada em `docker/scripts/init-wordpress.sh`; ela deve ser preservada e não pertence a este plano.

## 7. Evidências de conclusão

- mapa final de navegação e taxonomia aprovados;
- inventário de componentes e páginas implementadas;
- lista de conteúdo faltante e sua resolução;
- resultados de testes de compra e responsividade;
- medições de desempenho antes/depois;
- lista de pendências explicitamente transferidas para o plano de personalização.

## 8. Registro de execução

- 2026-07-31: iniciada a fundação técnica no child theme e no `petshop-core`: tokens visuais, estilos acessíveis de WooCommerce, localizações de menu e taxonomia canônica idempotente.
- 2026-07-31: documentada a operação de menus, categorias sazonais e requisitos de conteúdo em `004-OPERATIONS.md`.
- 2026-07-31: páginas Gutenberg, menus, taxonomia editável, identidade, busca, minicarrinho, vitrines, avaliações reais, SEO e blocos oficiais de carrinho/checkout implementados.
- 2026-07-31: runtime WordPress/WooCommerce validado nos volumes persistentes por contêiner de recuperação; rotas e fluxos críticos aprovados em Chromium, Firefox e WebKit. Evidências em `004-TESTING.md`.
- 2026-07-31: duas rodadas de revisão crítica concluídas; provisionamento movido ao contexto administrativo com capacidade e lock, e customizações do cliente preservadas em upgrades.
- 2026-07-31: correção 004b concluída na mesma branch: hero full-bleed baseado na referência, 26 produtos demonstrativos de 14 categorias, nove imagens rastreáveis, descrições de categoria e conteúdo integralmente administrável no WordPress.
- Pendência para encerramento: sessão manual com NVDA ou VoiceOver, ou aceite explícito para transferir essa validação humana. A auditoria automatizada de nome, papel, estado, foco e regiões ao vivo foi aprovada.
- Dependência externa: o `docker compose up --build -d` padrão ainda encontra CRLF em `docker/scripts/init-wordpress.sh`, pertencente ao Plano 003. A dependência não impediu os testes funcionais deste plano e não foi alterada fora de escopo.
