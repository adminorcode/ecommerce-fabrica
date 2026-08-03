# Plano 013 — Alinhamento de usabilidade e páginas WooCommerce

**Status:** Pendente
**Data:** 2026-08-03
**Branch:** `013-alinhamento-usabilidade-paginas-woocommerce`
**Dependências:** implementar após o baseline do [008](./008-suite-de-testes-automatizados.md) e a base PSR-4/ciclo de vida do [007](./007-refatoracao-petshop-core.md), antes do [012](./012-personalizador-produtos-e-fila-producao.md)
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
- O contrato de frete permanecerá desacoplado do fornecedor; desenvolvimento usa zona/método de teste e publicação aguarda transportadora e credenciais reais.
- Rotas em português com redirecionamento 301 das rotas antigas.
- Cadastro em Minha Conta e após a compra; checkout continua aceitando visitante.
- Saneamento de uma amostra representativa mais checklist operacional, sem reescrita automática de todo o catálogo.
- Editor visual e fila de produção permanecem no Plano 012.

## 4. Sessões de implementação

### Sessão 00 — Pré-requisitos, baseline e arquitetura

- [ ] Plano 008 fornece PHPUnit executável, testes de catálogo/SKU e Playwright containerizado.
- [ ] Plano 007 fornece bootstrap por Composer, módulos PSR-4 e migrador versionado.
- [ ] Registrar baseline desktop/mobile das rotas críticas.
- [ ] Confirmar ausência de regressões antes de alterar comportamento.

### Sessão 01 — Rotas, títulos e navegação

- [ ] Migrar páginas centrais para `/loja`, `/carrinho`, `/finalizar-compra` e `/minha-conta`, preservando IDs WooCommerce quando possível.
- [ ] Redirecionar `/shop`, `/cart`, `/checkout` e `/my-account` por 301, preservando query string e impedindo loops.
- [ ] Atualizar títulos, breadcrumbs, canonical, menus e metadados para português.
- [ ] Manter somente um skip link e um breadcrumb por página.
- [ ] Substituir o hero vazio dos arquivos WooCommerce por cabeçalho compacto com descrição administrável da categoria.

### Sessão 02 — Catálogo e busca

- [ ] Implementar filtros GET canônicos para categoria, faixa de preço, cor, tamanho, estoque e ordenação.
- [ ] Usar OR dentro do mesmo atributo e AND entre grupos; mostrar filtros aplicados e limpeza individual/global.
- [ ] Ocultar opções zeradas, salvo quando selecionadas.
- [ ] Implementar painel mobile acessível com contador, aplicar, limpar e retorno correto de foco.
- [ ] Preservar URL, filtros, paginação e posição ao retornar da PDP.
- [ ] Manter paginação e envio de busca funcionais sem JavaScript.
- [ ] Implementar sugestões com debounce pela Store API oficial, sem endpoint REST próprio.
- [ ] Criar estado sem resultados com consulta visível, categorias e produtos alternativos ativos.

### Sessão 03 — Página de produto

- [ ] Consolidar galeria, preço, promoção, estoque, SKU, quantidade e CTA.
- [ ] Garantir atualização de imagem, preço, estoque, SKU e prazo em produtos variáveis.
- [ ] Exibir cor com amostra/nome e tamanho com rótulo/guia administrável.
- [ ] Bloquear CTA quando escolha obrigatória estiver ausente e anunciar o campo pendente.
- [ ] Adicionar cálculo de entrega por CEP com taxas WooCommerce.
- [ ] Separar prazo de produção do prazo de transporte.
- [ ] Estruturar materiais, dimensões, conteúdo, cuidados e medidas a partir do produto.
- [ ] Exibir avaliações somente com moderação e atendimento habilitados.
- [ ] Reservar extensão junto ao CTA para o Plano 012 sem reorganização posterior.

### Sessão 04 — Carrinho, checkout, frete e pagamento

- [ ] Preservar Cart/Checkout Blocks e extensões pela Store API.
- [ ] Exibir no carrinho imagem, variação, personalização futura, quantidade, preços, economia real, CEP, frete e total.
- [ ] Adicionar “Continuar comprando” e estados de carregamento/erro junto à ação afetada.
- [ ] Validar contrato de frete com zona brasileira e método local de teste.
- [ ] Configurar Mercado Pago Pix/cartão em sandbox sem versionar credenciais.
- [ ] Manter checkout visitante, dados mínimos e proteção contra envio duplicado.
- [ ] Aplicar checkout sem distrações, mantendo logo, suporte e retorno ao carrinho.
- [ ] Criar páginas Gutenberg separadas para entrega, trocas, personalização, privacidade e termos.
- [ ] Atribuir páginas aplicáveis ao WooCommerce e bloquear publicação enquanto o conteúdo jurídico estiver pendente.

### Sessão 05 — Confirmação, pedidos, rastreamento e conta

- [ ] Exibir número, status, itens, endereço, pagamento, entrega e próximos passos na confirmação.
- [ ] Criar rastreamento HPOS com transportadora, código e URL, editável no pedido e condicional em conta/e-mails.
- [ ] Habilitar cadastro em Minha Conta sem exigir autenticação no checkout.
- [ ] Oferecer criação segura de senha após compra visitante e vincular pedidos por APIs oficiais.
- [ ] Preservar pedidos, endereços, dados, recuperação, logout e wishlist.
- [ ] Preparar detalhe do pedido para prévia/estado do Plano 012.
- [ ] Adicionar atendimento contextual sem dados pessoais ou número em URL pública.

### Sessão 06 — Conteúdo, amostra do catálogo e analytics

- [ ] Garantir origem administrativa por rota conforme o inventário da seção 5.
- [ ] Sanear um produto simples, um variável e um preparado para personalização.
- [ ] Entregar checklist e relatório de inconsistências para o restante do catálogo.
- [ ] Emitir eventos locais compatíveis com GA4 para busca, visualização, variação, carrinho, checkout e compra.
- [ ] Não carregar fornecedor externo de analytics antes de consentimento.

### Sessão 07 — Testes, persistência e aceite

- [ ] Executar toda a matriz da seção 7.
- [ ] Validar persistência editorial após reprovisionamento.
- [ ] Atualizar guia administrativo, Planos 005/009/012 e `Plans/STATUS.md`.
- [ ] Registrar fornecedor de frete, credenciais de produção e políticas como bloqueios externos quando ainda ausentes.

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

## 7. Matriz de validação

| Superfície | Cenários obrigatórios |
| --- | --- |
| PHPUnit/integração | filtros, canonical, 301, migração idempotente, conta, rastreamento e HPOS |
| Catálogo | 390/768/1024/1440, filtros combinados, limpar, paginação, retorno da PDP |
| Busca | nome, SKU, sugestão, acento, plural/erro simples quando suportado e estado vazio |
| Produto | simples, variável, indisponível, atributos obrigatórios, CEP e prazos |
| Carrinho | quantidade, remoção, continuar comprando, CEP válido/inválido/sem cobertura |
| Checkout | visitante, logado, validação sem perda, políticas e envio duplicado |
| Mercado Pago | Pix/cartão aprovado, recusado e pendente em sandbox, sem duplicidade |
| Pedido/conta | confirmação, e-mail, rastreamento, cadastro posterior e vínculo único |
| Acessibilidade | teclado, foco, nomes, erros, painel mobile, contraste e gate humano NVDA/VoiceOver |
| Qualidade | links, console, logs, overflow, texto cortado, LCP/INP/CLS em laboratório |
| Persistência | páginas, políticas, produto, imagem/alt e configurações após reprovisionamento |

## 8. Critérios globais de aceite

- [ ] Todos os requisitos P0/P1 das superfícies WooCommerce listadas estão implementados ou vinculados explicitamente ao Plano 012.
- [ ] Rotas portuguesas, redirecionamentos, canonical e menus são coerentes.
- [ ] Catálogo e busca funcionam progressivamente em desktop/mobile e preservam contexto.
- [ ] Produto simples/variável não permite combinação inválida e informa frete/prazos sem fabricar valores.
- [ ] Carrinho e Checkout Blocks preservam dados pela Store API.
- [ ] Mercado Pago sandbox cobre estados aprovado, recusado e pendente sem pedido duplicado.
- [ ] Pedidos e rastreamento usam CRUD HPOS.
- [ ] Checkout visitante e criação posterior de conta funcionam sem duplicar pedido.
- [ ] Conteúdo comercial, políticas e imagens permanecem administráveis e persistentes.
- [ ] Nenhum segredo, política jurídica inventada ou dado pessoal está versionado.
- [ ] Testes automatizados, browser gates e gates de persistência passam.
- [ ] `Plans/STATUS.md` e documentação operacional refletem o resultado real.

## 9. Fora do escopo

- páginas Eventos, Por Raça, Animal Republik, premium e profissionais;
- editor visual, arquivos e fila de produção do Plano 012;
- ativação de Stripe em produção;
- escolha unilateral do fornecedor definitivo de frete;
- saneamento automático de todo o catálogo;
- publicação de políticas não aprovadas pelo cliente/jurídico.

## 10. Critério de conclusão

O Plano 013 somente poderá ser concluído depois que um comprador localizar um produto, aplicar filtros, selecionar uma combinação válida, calcular entrega, adicionar ao carrinho, concluir como visitante pelo Mercado Pago sandbox, consultar o pedido e criar sua conta sem perder ou duplicar dados; e quando todas as edições administrativas sobreviverem ao reprovisionamento.

Sua execução deve respeitar as dependências declaradas no cabeçalho, sem incorporar o escopo dos Planos 008, 007 ou 012 ao Plano 013.
