# Plano 013 — Alinhamento de usabilidade e páginas WooCommerce

**Status:** Em andamento — implementação e gates automatizados verdes; aceite externo pendente
**Data:** 2026-08-03
**Branch:** `codex/013-alinhamento-usabilidade-paginas-woocommerce`
**Dependências:** implementar após a base PSR-4/ciclo de vida do [007](./007-refatoracao-petshop-core.md), antes do [012](./012-personalizador-produtos-e-fila-producao.md); suíte PHPUnit e gates Playwright já disponíveis
**Origem:** `Orcode_Requisitos_Website_Loja_Pet_v2 (1).docx`, revisão estrutural integral; renderização visual do DOCX indisponível no ambiente por ausência de LibreOffice/Microsoft Word

## 1. Objetivo

Alinhar os requisitos P0 e P1 do documento da Orcode ao estado real da loja, priorizando usabilidade, catálogo, busca, produto, carrinho, Checkout Block, confirmação, pedidos e Minha Conta.

Este plano complementa o visual entregue pelo Plano 009, assume os requisitos WooCommerce pendentes do Plano 005 e prepara Cart/Checkout Blocks e HPOS para o personalizador do Plano 012.

## 2. Baseline confirmado em 2026-08-03

| Superfície | Estado atual | Principal lacuna |
| --- | --- | --- |
| Loja/categorias | Cards, ordenação, paginação e filtro por categoria funcionam | Hero vazio excessivo, filtro mobile ocupa a página, faltam preço/cor/tamanho/estoque, chips e restauração |
| Busca | Busca por nome/SKU disponível | Estado vazio sem alternativas, título inadequado, sem sugestões ou tolerância básica |
| Produto | Preço, estoque, descrição, quantidade e relacionados | Sem CEP/frete, prazo de produção, guia de medidas e validação representativa de variações |
| Carrinho | Cart Block, quantidade, remoção, cupom e total | Sem cálculo de entrega por CEP e sem ação clara para continuar comprando |
| Checkout | Checkout Block e compra como visitante habilitados | Nenhum pagamento ou frete ativo; termos não atribuídos e privacidade ainda em rascunho |
| Minha Conta | Login, recuperação e pedidos do WooCommerce | Cadastro desabilitado e ausência de conversão segura da compra visitante em conta |

## 3. Decisões aprovadas

- Entrega conjunta dos requisitos P0 e P1 das páginas WooCommerce.
- Mercado Pago será o gateway principal, com Pix e cartão em sandbox; Stripe permanece desabilitado em produção e coberto por compatibilidade.
- Virtuaria Correios será a integração de frete escolhida para validação com Correios. A publicação continua condicionada a configurar origem, serviços, contrato/credenciais quando aplicável, zonas, embalagens e contingência de falha sem versionar segredos.
- Rotas em português com redirecionamento 301 das rotas antigas.
- Cadastro em Minha Conta e após a compra; checkout continua aceitando visitante.
- Saneamento de uma amostra representativa mais checklist operacional, sem reescrita automática de todo o catálogo.
- Editor visual e fila de produção permanecem no Plano 012.

## 4. Sessões de implementação

### Sessão 00 — Pré-requisitos, baseline e arquitetura

- [x] PHPUnit executável, testes de catálogo/SKU e Playwright containerizado disponíveis.
- [x] Plano 007 fornece bootstrap por Composer, módulos PSR-4 e migrador versionado.
- [x] Registrar baseline desktop/mobile das rotas críticas.
- [x] Confirmar ausência de regressões antes de alterar comportamento.

**Baseline de execução (2026-08-08):** PHPUnit verde com 12 testes e 17 asserções. O gate de catálogo passa em 390/1024/1440 sem overflow, mas o filtro mobile ocupa 762,7 px antes dos produtos. O gate Cart/Checkout confirma ausência de overflow, mas falha no carrinho vazio por CTA primário ausente. `test-005-session-02-persistence.php` também já falha para a imagem do hero após reprovisionamento. Essas duas falhas são baseline e devem ser corrigidas pelo loop deste plano antes do aceite.

### Sessão 01 — Rotas, títulos e navegação

- [x] Migrar páginas centrais para `/loja`, `/carrinho`, `/finalizar-compra` e `/minha-conta`, preservando IDs WooCommerce quando possível.
- [x] Redirecionar `/shop`, `/cart`, `/checkout` e `/my-account` por 301, preservando query string e impedindo loops.
- [x] Atualizar títulos, breadcrumbs, canonical, menus e metadados para português.
- [x] Manter somente um skip link e um breadcrumb por página.
- [x] Substituir o hero vazio dos arquivos WooCommerce por cabeçalho compacto com descrição administrável da categoria.

### Sessão 02 — Catálogo e busca

- [x] Implementar filtros GET canônicos para categoria, faixa de preço, cor, tamanho, estoque e ordenação.
- [x] Usar OR dentro do mesmo atributo e AND entre grupos; mostrar filtros aplicados e limpeza individual/global.
- [x] Ocultar opções zeradas, salvo quando selecionadas.
- [x] Implementar painel mobile acessível com contador, aplicar, limpar e retorno correto de foco.
- [x] Preservar URL, filtros, paginação e posição ao retornar da PDP.
- [x] Manter paginação e envio de busca funcionais sem JavaScript.
- [x] Implementar sugestões com debounce pela Store API oficial, sem endpoint REST próprio.
- [x] Criar estado sem resultados com consulta visível, categorias e produtos alternativos ativos.

### Sessão 03 — Página de produto

- [x] Consolidar galeria, preço, promoção, estoque, SKU, quantidade e CTA.
- [x] Garantir atualização de imagem, preço, estoque, SKU e prazo em produtos variáveis.
- [x] Exibir cor com amostra/nome e tamanho com rótulo/guia administrável.
- [x] Bloquear CTA quando escolha obrigatória estiver ausente e anunciar o campo pendente.
- [x] Adicionar cálculo de entrega por CEP com taxas WooCommerce.
- [x] Separar prazo de produção do prazo de transporte.
- [x] Estruturar materiais, dimensões, conteúdo, cuidados e medidas a partir do produto.
- [x] Exibir avaliações somente com moderação e atendimento habilitados.
- [x] Reservar extensão junto ao CTA para o Plano 012 sem reorganização posterior.

### Sessão 04 — Carrinho, checkout, frete e pagamento

- [x] Preservar Cart/Checkout Blocks e extensões pela Store API.
- [x] Exibir no carrinho imagem, variação, personalização futura, quantidade, preços, economia real, CEP, frete e total.
- [x] Adicionar “Continuar comprando” e estados de carregamento/erro junto à ação afetada.
- [x] Validar contrato de frete com zona brasileira e método local de teste.
- [x] Instalar/configurar Virtuaria Correios em ambiente de validação, sem versionar credenciais, e validar cálculo real por CEP.
- [ ] Configurar Mercado Pago Pix/cartão em sandbox sem versionar credenciais.
- [x] Manter checkout visitante, dados mínimos e proteção contra envio duplicado.
- [x] Aplicar checkout sem distrações, mantendo logo, suporte e retorno ao carrinho.
- [x] Criar páginas Gutenberg separadas para entrega, trocas, personalização, privacidade e termos.
- [x] Atribuir páginas aplicáveis ao WooCommerce e bloquear publicação enquanto o conteúdo jurídico estiver pendente.

### Sessão 05 — Confirmação, pedidos, rastreamento e conta

- [x] Exibir número, status, itens, endereço, pagamento, entrega e próximos passos na confirmação.
- [x] Criar rastreamento HPOS com transportadora, código e URL, editável no pedido e condicional em conta/e-mails.
- [x] Habilitar cadastro em Minha Conta sem exigir autenticação no checkout.
- [x] Oferecer criação segura de senha após compra visitante e vincular pedidos por APIs oficiais.
- [x] Preservar pedidos, endereços, dados, recuperação, logout e wishlist.
- [x] Preparar detalhe do pedido para prévia/estado do Plano 012.
- [x] Adicionar atendimento contextual sem dados pessoais ou número em URL pública.

### Sessão 06 — Conteúdo, amostra do catálogo e analytics

- [x] Garantir origem administrativa por rota conforme o inventário da seção 5.
- [x] Sanear um produto simples, um variável e um preparado para personalização.
- [x] Entregar checklist e relatório de inconsistências para o restante do catálogo.
- [x] Emitir eventos locais compatíveis com GA4 para busca, visualização, variação, carrinho, checkout e compra.
- [x] Não carregar fornecedor externo de analytics antes de consentimento.

### Sessão 07 — Testes, persistência e aceite

- [ ] Executar toda a matriz da seção 7.
- [x] Validar persistência editorial após reprovisionamento.
- [x] Atualizar guia administrativo, Planos 005/009/012 e `Plans/STATUS.md`.
- [x] Registrar fornecedor de frete, credenciais de produção e políticas como bloqueios externos quando ainda ausentes.

## 5. Inventário de conteúdo administrável

| Rota/área | Conteúdo e mídia | Origem administrativa |
| --- | --- | --- |
| `/loja/` | título funcional, resultados, filtros e produtos | WooCommerce; labels funcionais traduzíveis |
| categoria | nome, descrição, imagem e alt | Produtos → Categorias + Biblioteca de mídia |
| busca | consulta e resultados dinâmicos; alternativas | WooCommerce; categorias/produtos ativos |
| produto | nome, galeria, alt, descrições, atributos, medidas, logística, prazo e relacionados | Produto WooCommerce + Biblioteca de mídia |
| `/carrinho/` | itens e totais dinâmicos; orientação editorial opcional | WooCommerce Blocks + Gutenberg da página |
| `/finalizar-compra/` | campos, métodos e totais dinâmicos; links jurídicos | WooCommerce Blocks + páginas Gutenberg |
| confirmação/pedido | dados dinâmicos, rastreamento e próximos passos | Pedido HPOS + configuração global administrável |
| `/minha-conta/` | endpoints e pedidos dinâmicos; orientação editorial opcional | WooCommerce + Gutenberg da página |
| políticas | textos, headings e links | Páginas Gutenberg separadas |

Migrações podem provisionar estrutura inicial somente em conteúdo ainda gerenciado. Nenhum reprovisionamento poderá sobrescrever alteração do cliente.

## 6. Interfaces e contratos

- Parâmetros públicos: `product_cat[]`, `min_price`, `max_price`, `filter_pa_color`, `filter_pa_size`, `stock_status`, `orderby` e `paged`.
- Parâmetros desconhecidos ou inválidos serão sanitizados/canonicalizados sem SQL direto.
- Sugestões usarão endpoints públicos da Store API.
- Pedidos usarão CRUD WooCommerce compatível com HPOS, sem presumir `wp_posts`.
- Metadados de rastreamento terão namespace `petshop`, escaping tardio e nenhuma exposição na Store API.
- Eventos de funil ficarão em camada local compatível com GA4 e condicionada ao consentimento.

### 6.1 Arquitetura executável

- `Petshop\Core\WooCommerce\Routes`: migra IDs existentes para slugs/títulos portugueses, registra 301 somente para os quatro caminhos legados, preserva query string, evita loops e reconcilia menus sem substituir rótulos editados pelo cliente.
- `Petshop\Core\Storefront\CatalogFilter`: torna os parâmetros da seção 6 canônicos, usa `tax_query`/`meta_query` e APIs de consulta WooCommerce, com OR dentro de atributo e AND entre grupos; nunca usa SQL direto.
- `Petshop\Core\Storefront\SearchExperience`: renderiza estado vazio e carrega sugestões exclusivamente de `/wp-json/wc/store/v1/products`, com debounce de 250 ms, limite de resultados e navegação por teclado.
- `Petshop\Core\WooCommerce\ProductDetails`: persiste prazo de produção, materiais, cuidados, medidas e guia no produto/variação; calcula entrega com o pacote e as taxas do WooCommerce, sem fabricar prazo ou preço.
- `Petshop\Core\WooCommerce\CartCheckout`: preserva os blocos nativos, adiciona somente extensões suportadas pela Store API/Blocks e provisiona CTA editorial em Gutenberg sem sobrescrever edições posteriores.
- `Petshop\Core\WooCommerce\OrderTracking`: usa `WC_Order::get_meta()`, `update_meta_data()` e `save()`, metabox compatível com HPOS e saída condicional em conta/e-mails.
- `Petshop\Core\WooCommerce\GuestAccount`: cria conta somente mediante ação autenticada pelo `order_key`, e-mail do pedido e nonce; usa APIs WooCommerce, associa o pedido uma única vez e envia o fluxo oficial de definição de senha.
- `Petshop\Core\Analytics\FunnelEvents`: emite eventos locais estruturados; nenhum script de terceiro é carregado pelo plugin e o despacho externo depende de consentimento fornecido por integração via filtro.
- `StorefrontProvisioner`: sobe uma versão idempotente para rotas, páginas Gutenberg, configurações de conta e fixtures locais. Conteúdo existente só é atualizado quando a assinatura gerenciada ainda corresponde ao valor anterior.
- `petshop-theme`: recebe apenas CSS responsivo/estados e JS progressivo; regras, migrações e dados permanecem no plugin.

As APIs de referência são a [Products Store API](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/products/), a [extensibilidade de Cart/Checkout Blocks](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/) e o [recipe book de HPOS](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/).

### 6.2 Pré-requisitos externos e regra de aceite

- O ambiente local inicia com Mercado Pago 8.9.0 ativo, mas os gateways Pix/cartão estão desabilitados e nenhuma credencial sandbox está configurada.
- Virtuaria Correios foi escolhido como integração de frete para validação. Em 2026-08-11, o runtime local validou `virtuaria-correios-sedex` com origem `01001000`, serviço `03220`, modo fácil sem credenciais versionadas, tarifa para CEP `01310000` e ausência do método para CEP inválido/sem retorno. O `flat_rate` permanece permitido apenas como fallback local/desenvolvimento; produção continua bloqueada até origem real, zonas, serviços, embalagem, contrato/credenciais e contingência do Virtuaria estarem aprovados.
- Bug visual da calculadora da PDP (preço com entidade HTML, ex.: `&#82; &#36;&nbsp;27,00` no CEP `94010450`) **não** fecha neste plano: correção e aceite estão no [027](./027-calculadora-frete-hub.md).
- Privacidade e reembolso existentes continuam em rascunho. As cinco páginas separadas serão provisionadas como estrutura Gutenberg administrável, sem inventar texto jurídico, e permanecerão em rascunho até aprovação.
- A implementação pode fechar código e testes automatizados sem segredos, mas o plano não muda para `Concluído` enquanto os cenários Mercado Pago sandbox e o gate humano NVDA/VoiceOver não tiverem evidência.

## 7. Matriz de validação

Os comandos, fixtures, evidências e o ledger de tentativas ficam em [013-TESTING.md](./013-TESTING.md).

| Superfície | Cenários obrigatórios |
| --- | --- |
| PHPUnit/integração | filtros, canonical, 301, migração idempotente, conta, rastreamento e HPOS |
| Catálogo | 390/768/1024/1440, filtros combinados, limpar, paginação, retorno da PDP |
| Busca | nome, SKU, sugestão, acento, plural/erro simples quando suportado e estado vazio |
| Produto | simples, variável, indisponível, atributos obrigatórios, CEP e prazos |
| Carrinho | quantidade, remoção, continuar comprando, Virtuaria Correios com CEP válido/inválido/sem cobertura |
| Checkout | visitante, logado, validação sem perda, políticas e envio duplicado |
| Mercado Pago | Pix/cartão aprovado, recusado e pendente em sandbox, sem duplicidade |
| Pedido/conta | confirmação, e-mail, rastreamento, cadastro posterior e vínculo único |
| Acessibilidade | teclado, foco, nomes, erros, painel mobile, contraste e gate humano NVDA/VoiceOver |
| Qualidade | links, console, logs, overflow, texto cortado, LCP/INP/CLS em laboratório |
| Persistência | páginas, políticas, produto, imagem/alt e configurações após reprovisionamento |

## 8. Critérios globais de aceite

- [ ] Todos os requisitos P0/P1 das superfícies WooCommerce listadas estão implementados ou vinculados explicitamente ao Plano 012 (Mercado Pago sandbox depende de credenciais externas).
- [x] Rotas portuguesas, redirecionamentos, canonical e menus são coerentes.
- [x] Catálogo e busca funcionam progressivamente em desktop/mobile e preservam contexto.
- [x] Produto simples/variável não permite combinação inválida e informa frete/prazos sem fabricar valores.
- [x] Carrinho e Checkout Blocks preservam dados pela Store API.
- [ ] Mercado Pago sandbox cobre estados aprovado, recusado e pendente sem pedido duplicado.
- [x] Pedidos e rastreamento usam CRUD HPOS.
- [ ] Checkout visitante e criação posterior de conta funcionam sem duplicar pedido.
- [x] Conteúdo comercial, políticas e imagens permanecem administráveis e persistentes.
- [x] Nenhum segredo, política jurídica inventada ou dado pessoal está versionado.
- [x] Testes automatizados, browser gates e gates de persistência passam.
- [x] `Plans/STATUS.md` e documentação operacional refletem o resultado real.

## 9. Fora do escopo

- páginas Eventos, Por Raça, Animal Republik, premium e profissionais;
- editor visual, arquivos e fila de produção do Plano 012;
- ativação de Stripe em produção;
- implementação de fornecedor alternativo ao Virtuaria Correios sem novo registro de decisão;
- saneamento automático de todo o catálogo;
- publicação de políticas não aprovadas pelo cliente/jurídico;
- correção do preço da calculadora da PDP com entidade HTML (`&#82;&#36;` / `&nbsp;`) — Plano 027.

## 10. Critério de conclusão

O Plano 013 somente poderá ser concluído depois que um comprador localizar um produto, aplicar filtros, selecionar uma combinação válida, calcular entrega pelo Virtuaria Correios, adicionar ao carrinho, concluir como visitante pelo Mercado Pago sandbox, consultar o pedido e criar sua conta sem perder ou duplicar dados; e quando todas as edições administrativas sobreviverem ao reprovisionamento.

Sua execução deve respeitar as dependências declaradas no cabeçalho, sem incorporar o escopo dos Planos 008, 007 ou 012 ao Plano 013.
