# Plano 041 — Frete e carrinho consistentes em toda a loja

**Status:** Em andamento — integração Store API, destino de sessão, ViaCEP por geração, preferência PDP, preview isolado e persistência do bloco implementados. Fechamento bloqueado por divergência financeira no browser autenticado; T01–T20 ainda não têm aceite completo, staging e pedido de teste pendentes. Ver ledger de execução (§10).
**Data:** 2026-10-08.
**Alcance:** global nos fluxos próprios de produto, cards/vitrines, minicarrinho, carrinho, checkout e formulários de endereço.
**Branch de implementação:** `codex/041-integridade-frete-carrinho-checkout` (branch existente retomada; HEAD `fe55572`, alterações locais anteriores preservadas).
**ClickUp:** tarefa não criada nesta etapa. Título definido: `Plano 041 - Frete e carrinho consistentes em toda a loja`.
**Origem:** [auditoria de 08/10/2026](../docs/analise-frete-carrinho-2026-10-08.md), relato de produção e solicitação de plano fundamentado em documentação oficial.
**Dependências:** Planos [026](./026-checkout-dados-salvos-viacep.md), [027](./027-calculadora-frete-hub.md), [036](./036-dependencias-frete-checkout-versionadas.md), [037](./037-atualizacao-automatica-valores-carrinho.md), [038](./038-total-quantidade-pagina-produto.md) e [039](./039-quantidade-carrinho-apos-frete.md).
**Baseline examinada:** `bdf6632` com alterações locais preexistentes; WordPress 7.0.2 / WooCommerce 10.9.4 no Docker. Produção não foi inspecionada diretamente.

**Continuação vigente (09/10/2026):** executar o §17. Por solicitação do usuário, a investigação/correção da divergência financeira dos 44 kg fica adiada. Essa exclusão não dispensa os demais testes financeiros, de segurança, pedido e operação; não representa aprovação do cenário adiado nem autorização de publicação. A revisão estática de segurança da branch está no §18; ela não aprova a Sessão D.

## Por quê

**Continuação autorizada e implementada:** reduzir a latência visual da atualização de quantidade e separar produtos, entrega e total do pedido. Implementação e validação ampliada no §16; aceite integral ainda pendente. A coordenação do §15 é o baseline de desempenho. Resumo consolidado: [problemas, correções e pendências da branch](../docs/relatorio-branch-041-problemas-correcoes-pendencias.md).

As proteções próprias de quantidade e as atualizações de CEP/endereço disputam o estado do WooCommerce. A auditoria demonstrou totais de uma unidade combinados com quantidade maior, reenvio de quantidades antigas, operações descartadas e respostas ViaCEP aplicadas fora de ordem. A correção deve devolver ao WooCommerce a responsabilidade pelo carrinho e centralizar apenas a integração própria de destino, endereço e apresentação da estimativa.

Como comprador, quero alterar produtos, quantidades, CEP e entrega e acompanhar valores coerentes até concluir o pedido, sem perder alterações ou ver um total antigo apresentado como confirmado.

## 1. Resultado e limites de evidência

- Eliminar os mecanismos comprovados de regressão e perda de intenção F01–F13 da auditoria.
- Reproduzir a sequência de interação rápida com registro de DOM, store e rede, incluindo o intervalo de cálculo; validar somente o valor final é insuficiente.
- Tratar o relato de produção como sintoma a cobrir, sem afirmar que a sequência visual espontânea já foi reproduzida integralmente. Os ensaios da auditoria incluíram respostas antigas e falhas deliberadamente induzidas.
- Garantir consistência entre quantidades, linhas, descontos, impostos, taxas disponíveis, método selecionado e totais confirmados pela Store API. Uma estimativa da PDP não é um orçamento do carrinho completo.
- Manter cálculo financeiro, limites de quantidade, zonas, empacotamento, descontos e validação final no WooCommerce e nos métodos de frete instalados.

### Fora de escopo

- Editar WordPress, WooCommerce, Blocksy, Melhor Envio, Virtuaria ou calculadora brasileira; atualizar versões desses fornecedores neste plano.
- Criar motor próprio de preços/frete, carrinho paralelo, tabelas de bloqueio distribuído ou garantia transacional para edição simultânea em várias abas/dispositivos.
- Redesenhar páginas comerciais, adicionar imagens, alterar contratos/tabelas das transportadoras ou modificar regras comerciais de frete grátis e descontos.
- Consultar ViaCEP na calculadora de frete; a exceção de CEP apenas para cotação permanece.
- Persistir o CEP de estimativa no cadastro da conta; sincronizar essa preferência entre dispositivos.
- Commit, PR, merge, publicação, pedido real ou cobrança. A implementação e o aceite técnico devem ser preparados antes dessas ações separadas.

## 2. Fundamentos oficiais e decisões

As referências foram consultadas em **08/10/2026**. Documentação pública pode evoluir; os comportamentos específicos de implementação foram confrontados com a tag **10.9.4**. Código interno serve para diagnóstico e testes, não como API a importar ou copiar.

| ID | Fundamento confirmado | Decisão para esta loja |
|---|---|---|
| D01 | O [Cart Store](https://developer.woocommerce.com/docs/block-development/reference/data-store/cart/) expõe ações para quantidade, cliente e entrega | Preservar componentes nativos; operações próprias usam ações públicas. Não substituir `fetch`, `dispatch` ou reducers |
| D02 | O [fluxo dos Blocks](https://developer.woocommerce.com/docs/block-development/reference/overview-of-data-flow/) sincroniza endereço e calcula o carrinho | ViaCEP altera o endereço por uma integração única; remover o POST paralelo seguido de `receiveCart` |
| D03 | [Updating the cart on-demand](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-update-cart/) define o contrato de mutação de uma extensão | O CEP de cotação do carrinho usa `extensionCartUpdate` e um callback próprio; a aplicação da resposta pertence aos Blocks |
| D04 | A [orientação de frete](https://woocommerce.com/document/troubleshooting-core-shipping/) permite exibir custos antes do endereço completo pela configuração da loja | Migrar uma vez `woocommerce_shipping_cost_requires_address` para `no`; retirar cidade fictícia e filtros que fingem endereço completo |
| D05 | [IntegrationInterface](https://developer.woocommerce.com/docs/block-development/reference/integration-interface/) organiza integração dos scripts dos Blocks; [block.json](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) é o registro canônico do bloco | Campo de CEP como inner block funcional, com dependências declaradas, editor e migração idempotente |
| D06 | O [minicarrinho usa Interactivity API desde 10.4](https://developer.woocommerce.com/2025/12/10/woocommerce-10-4-the-interactivity-api-mini-cart-goes-live/); os [eventos de carrinho](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/dom-events/) têm semântica de invalidação | Usar a ponte nativa e eventos documentados; testar minicarrinho e Cart Block juntos e separados |
| D07 | [Nonce Tokens](https://developer.woocommerce.com/docs/apis/store-api/nonce-tokens/) e [Cart API](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart/) definem autenticação e respostas de operações | Preferir wrappers nativos; centralizar somente o transporte próprio remanescente, sem falsificar sucesso HTTP |
| D08 | O [Checkout Store](https://developer.woocommerce.com/docs/block-development/reference/data-store/checkout/) fornece `isCalculating()` | Usar estado público de cálculo e pendência para apresentação e coordenação das operações próprias |

### 2.1 Quantidade: substituir o mecanismo do 039

O [hook de quantidade da tag 10.9.4](https://github.com/woocommerce/woocommerce/blob/10.9.4/plugins/woocommerce/client/blocks/assets/js/base/context/hooks/cart/use-store-cart-item-quantity.ts) já mantém a interação local, usa debounce de 400 ms e evita sincronização prematura com quantidade antiga. A [linha nativa do carrinho](https://github.com/woocommerce/woocommerce/blob/10.9.4/plugins/woocommerce/client/blocks/assets/js/base/components/cart-checkout/cart-line-items-table/cart-line-item-row.tsx) também aplica os limites de quantidade.

Este plano **substitui os critérios técnicos do 039** que exigiam debounce próprio de 1 s, nenhuma requisição em 800 ms, um único `update-item`, botão sempre habilitado e clique forçado em teste. O aceite passa a exigir interação nativa, respeito aos limites e consistência financeira durante a transição. Os 400 ms observados não viram contrato do plugin.

Não importar o hook interno, copiar sua implementação nem manipular contadores internos de cálculo. Remover mapas históricos de quantidade, pintura manual de preços, interceptação de respostas e desbloqueio global de controles.

### 2.2 Cotação por CEP: configuração e endereço são responsabilidades distintas

O [WC_Customer 10.9.4](https://github.com/woocommerce/woocommerce/blob/10.9.4/plugins/woocommerce/includes/class-wc-customer.php), o [WC_Cart 10.9.4](https://github.com/woocommerce/woocommerce/blob/10.9.4/plugins/woocommerce/includes/class-wc-cart.php) e o [ShippingController 10.9.4](https://github.com/woocommerce/woocommerce/blob/10.9.4/plugins/woocommerce/src/Blocks/Shipping/ShippingController.php) explicam a validação de endereço e a releitura que pode remover taxas. Filtros legados da calculadora não substituem automaticamente esse contrato da Store API.

- A configuração pretendida para estimar por CEP é `no`; registrar valor anterior e versão da migração. Não impor a opção em cada requisição nem escondê-la com `pre_option` permanente.
- Reprovisionamento preserva alterações administrativas posteriores. Se o administrador voltar para `yes`, o carrinho informa a necessidade de completar endereço no checkout e respeita a restrição; não inventa cidade nem mantém um preço confirmado sem taxa correspondente.
- País BR, CEP normalizado e UF derivada são suficientes para solicitar uma estimativa aos métodos que aceitam esses dados. Uma faixa numérica não comprova existência do CEP nem cobertura.
- Métodos que exigem município real só se tornam elegíveis após endereço real; o cliente continua podendo preenchê-lo no checkout. Não fabricar resultado positivo nem converter falha de fornecedor em falta de cobertura.
- O checkout continua exigindo os campos obrigatórios da localidade e os campos adicionais registrados. A configuração de exibição antecipada não dispensa endereço final.

## 3. Escopo por superfície e propriedade do estado

| Superfície | Responsabilidade própria | Dono do estado confirmado |
|---|---|---|
| Cards da Home, loja, busca e categorias | Compra, variação escolhida, erro e evento de atualização | Store API e mecanismo nativo de sincronização |
| PDP simples/variável | Total estimado do produto e cotação para produto/variação/quantidade atuais | WooCommerce para preços e taxas; componente guarda apenas o rascunho da consulta |
| Minicarrinho global | Compatibilidade dos eventos e apresentação existente | Bloco nativo / Interactivity API |
| `/carrinho` | Inner block de CEP e integração da preferência de destino | Cart Block, Store API e sessão WooCommerce |
| `/finalizar-compra` | Lookup ViaCEP, hidratação inicial coerente e erros | Checkout Block, ações públicas e validação WooCommerce |
| Minha conta → Endereços e formulários próprios de endereço | Mesmo lookup e validação; salvamento normal do formulário | Conta WooCommerce após envio autorizado pelo cliente |
| Personalização | Preservar metadados e chaves distintas de itens | Integração existente de personalização e WooCommerce |

Totais REST usam o schema da API, inclusive valores monetários em unidades mínimas e precisão informada. O store JavaScript normaliza propriedades para camelCase. Não espalhar objetos de um schema no outro; não reconstruir total geral com `preço × quantidade`.

Durante cálculo, a interface usa o estado nativo de pendência e não apresenta uma combinação sintética de linhas novas e agregados antigos como confirmada. O último total confirmado pode permanecer visível com indicação de atualização; a interação local de quantidade não é confirmação financeira. Erros deixam a operação identificável e permitem correção/reenvio explícito.

## 4. Arquitetura e reuso

Os nomes novos abaixo definem responsabilidades; seguir o bootstrap PSR-4 e os módulos de assets já existentes. Classes pequenas em `petshop-core`; tema recebe apenas apresentação localizada. Não criar framework de sincronização.

| Módulo/arquivo | Entrega definida |
|---|---|
| `includes/WooCommerce/BrazilianPostcode.php` | Fonte única PHP de normalização, validação estrutural e derivação de UF; fixtures de fronteiras das faixas |
| `includes/WooCommerce/ShippingQuoteDestination.php` | Política de destino real de estimativa, origem e invalidação de dados de outro CEP; eliminar cidade transitória e restauração de quantidade |
| `includes/WooCommerce/ShippingQuotes.php` | Receber produto/variação/quantidade; consultar métodos WooCommerce para pacote de estimativa isolado; não gravar cliente, linhas ou totais do carrinho |
| `includes/WooCommerce/ShippingQuoteCartExtension.php` — novo | Callback único `petshop-shipping-quote` via `woocommerce_store_api_register_update_callback`; validar dados e aplicar destino antes do cálculo nativo |
| `includes/WooCommerce/CartBlocksIntegration.php` — novo | Implementar `IntegrationInterface`, registrar assets/dados e carregar integrações nas superfícies declaradas |
| `assets/src/cart-shipping-quote/` — novo | `block.json` API v3, editor, frontend e estado local do CEP; registro pela extensibilidade oficial dos Blocks |
| `assets/src/shared/cart-operations.js` — novo | Adaptador fino para ações públicas, `extensionCartUpdate`, estados públicos e erros; sem carrinho próprio e sem patches globais |
| `assets/src/shared/quote-preference.js` — novo | Preferência transitória da PDP no navegador; schema versionado, expiração e consumo confirmado |
| `assets/js/address-lookup.js` e `includes/WooCommerce/AddressLookup.php` | Um cliente ViaCEP com adaptadores de destino para Blocks e formulários; cancelamento e geração por formulário/tipo de endereço |
| `includes/WooCommerce/CheckoutCustomerData.php` | Hidratação inicial por conjunto coerente, antes do cálculo, preservando intenção recente e campos editados |
| `assets/js/product-experience.js` / handler `ProductDetails` | Enviar quantidade e variação; invalidar consulta antiga; remover persistência implícita de endereço da estimativa |
| `assets/js/product-card.js` | Unificar operações próprias com o adaptador; notificar consumidores nativos sem atualização duplicada indevida |
| `includes/WooCommerce/CartQuantityStability.php` | Extrair apenas compatibilidade necessária com widgets de terceiros; remover carregamento dos guards e montagem manual do CEP |
| `assets/js/cart-quantity-guard.js` / `cart-quantity-stability.js` | Retirar de produção e excluir após migração dos consumidores e gates; não manter caminho legado executável |
| `petshop-theme/style.css` | Estilo do campo e estados próprios; retirar regras de desbloqueio, preço pintado e ocultação usadas para mascarar divergências |

### 4.1 Contrato da cotação do carrinho

1. Registrar um callback no namespace `petshop-shipping-quote` em `woocommerce_blocks_loaded`. Payload definido: `{ action: 'set_quote_destination', postcode: '91210320' }`; rejeitar ação desconhecida e CEP estruturalmente inválido.
2. O frontend conserva CEP em edição separado do último confirmado. Submissões próprias são serializadas; uma nova edição invalida a apresentação da consulta anterior. Não cancelar requisição já enviada supondo que isso desfaça a mutação no servidor.
3. Aguardar pendências nativas antes de enviar a operação própria; observar `isCalculating()` e seletores públicos de cart. A continuação do §15 substitui esta restrição no carrinho: middleware público serializa requisições nativas e próprias e descarta intenções obsoletas; checkout/pagamento permanecem fora dele. Após 15 s de espera, interromper a espera própria e mostrar erro recuperável, sem declarar sucesso.
4. Usar `extensionCartUpdate({ namespace, data, overwriteDirtyCustomerData: false })`. O callback grava somente destino de cotação e sua origem na sessão do cliente WooCommerce, por APIs oficiais. Não altera quantidades, cupons, preços, dados da conta ou conteúdo personalizado.
5. Ao mudar o CEP, invalidar cidade, rua, bairro e complemento associados ao CEP anterior no destino de entrega estimado. Não copiar esses campos de billing para shipping em uma resposta posterior ao cálculo. Preservar identidade/contato e não sobrescrever rascunho de endereço que o usuário esteja editando.
6. O fluxo nativo calcula e aplica a resposta integral coerente. Não combinar apenas frete/totais, reescrever batch nem pular uma resposta por ter zero taxas. Exceção restrita do §15: retorno de quantidade perdido no early return/debounce nativo usa update-item oficial seguido de syncCartWithIAPIStore público, com leitura integral vigente e atualização nativa de nonce/cart hash.
7. Sucesso sem cobertura atualiza o destino e remove taxa/método antigo incompatível. Falha de transporte não pode receber a mensagem de cobertura inexistente. A retomada faz uma leitura oficial e nova ação explícita; não reaplica intenções antigas.
8. Identificar taxas por pacote + ID de taxa/instância. Alteração de quantidade, cupom, produto ou endereço deixa a invalidação e a seleção válida a cargo do WooCommerce; não preservar método que desapareceu.

### 4.2 Destino, hidratação e preferência da PDP

- Usar origem explícita `quote` ou `address` e CEP de referência nos dados próprios da sessão. Metadados necessários ao frontend ficam em namespace próprio de `extensions`, por [ExtendSchema](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-data/), sem adulterar campos nativos.
- Endereço digitado/selecionado nesta interação tem precedência sobre preferência herdada; preferência recente de cotação tem precedência sobre hidratação automática de endereço salvo. Endereço salvo só hidrata quando não há intenção posterior.
- Hidratar país/UF/CEP/cidade/logradouro como conjunto da mesma origem; não preencher lacunas geográficas com outro endereço. Executar antes do cálculo; remover enriquecimento de endereço em `rest_request_after_callbacks`.
- PDP mantém preferência em `sessionStorage`, chave por origem/base da loja, apenas CEP normalizado, versão, instante e identificador da consulta; expiração de 30 minutos. Não armazenar rua, documento, nome ou token. Falha de armazenamento não impede cotação nem compra.
- A preferência é gravada após consulta válida e atual, inclusive retorno válido sem cobertura. A PDP não grava endereço WooCommerce durante a estimativa. Esta decisão substitui a persistência implícita em `ShippingQuotes::persistPostcode` do 027.
- Na primeira entrada em carrinho ou checkout na mesma aba, consumir a preferência pelo mesmo adaptador e callback, antes de permitir confirmar entrega. Remover a preferência somente após confirmação; nunca aplicá-la por cima de edição de endereço iniciada nessa tela. Checkout então preenche o endereço real com ViaCEP.
- Logout/troca de conta limpa a preferência e os marcadores de hidratação pertinentes; preferência expirada é descartada. Não sincronizar preferência entre abas. Mudança sequencial feita fora do componente exige releitura nativa ao voltar ao fluxo, sem reenvio de quantidades históricas.

### 4.3 ViaCEP: um lookup, aplicação única

- Manter endpoint próprio já existente com nonce e sanitização; validar 8 dígitos e a resposta do [ViaCEP](https://viacep.com.br/). O lookup retorna dados e não salva o carrinho por conta própria.
- Cada formulário/endereço mantém geração e `AbortController`; aplicar resultado apenas se geração, CEP e tipo billing/shipping ainda coincidirem. Deduplicar consulta em andamento do mesmo CEP; cache de sucesso não impede repetir uma falha.
- No Checkout Block, aplicar uma atualização coerente usando `setBillingAddress`/`setShippingAddress` públicos e o ciclo nativo de sincronização. Retirar o POST próprio de `update-customer` e o `receiveCart` manual; não disparar simultaneamente a via nativa e outra via de transporte.
- Para número/bairro e demais campos adicionais, preservar o registro pela [API oficial de campos adicionais](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/additional-checkout-fields/), com salvamento HPOS compatível. Usar a interface pública desses campos; não gravar metadados paralelos por SQL.
- Nos formulários de conta/cadastro, o adaptador preenche os inputs do formulário e o salvamento ocorre no submit normal. Não reutilizar o transporte do Cart Block nesses formulários.
- Número é informado pelo cliente. Complemento retornado pelo serviço preenche somente campo ainda não editado para o CEP vigente. Não apagar edição manual feita após o início do lookup.
- CEP inexistente/serviço indisponível produz erro funcional em pt-BR e permite preenchimento manual; não fabricar endereço. Limpar dados vinculados ao CEP antigo sem apagar contato/identidade.
- A API de [address autocomplete](https://developer.woocommerce.com/docs/features/address-autocomplete) foi examinada: ela oferece sugestões de endereço e não é necessária para o preenchimento automático ao digitar CEP deste escopo. Não introduzir um segundo componente de busca de endereço.

### 4.4 Estimativa de produto e fornecedores

- Assinatura de cotação: produto efetivamente selecionado, quantidade válida e CEP. Para variável, exigir variação comprável; para virtual, mostrar estado sem entrega. Revalidar produto, estoque e limites no servidor.
- Chave de consulta: produto/variação + quantidade + CEP + parâmetros relevantes de preço/imposto/método. Mudança de qualquer entrada invalida resultado e geração anterior imediatamente.
- Construir pacote com quantidade, valor base, peso/dimensões e `formatted_data` do Melhor Envio correspondentes à seleção. Não usar preço já acrescido de imposto como base de linha sem considerar o contrato do WooCommerce.
- Reutilizar a API pública de [cálculo de pacotes do WooCommerce](https://github.com/woocommerce/woocommerce/blob/10.9.4/plugins/woocommerce/includes/class-wc-shipping.php), com chave de cache reservada ao preview, fora dos índices reais do carrinho. Não chamar `calculate_shipping` com pacote 0 substituindo os pacotes efetivos do cliente.
- Estimativa é leitura quanto a cliente, itens, totais e seleção do carrinho. Cache de preview e caches do fornecedor são isolados do cache dos pacotes reais. Medir requisições concorrentes em sessão própria e exigir preservação desses campos; não restaurar um snapshot completo de sessão no final da requisição.
- Manter métodos e filtros oficiais da loja; não implementar tarifa paralela. A estimativa usa apenas o produto consultado, sem prometer benefícios dependentes de outros itens ou cupons do carrinho.
- Corrigir o timeout efetivo do Melhor Envio pelo filtro público `http_request_args`, restrito aos hosts e rota de cotação verificados, com **10 s**. Não alterar o arquivo de terceiro com `'timeout '` nem afetar etiquetas, pagamentos ou outros hosts. Conferir o contrato da [API HTTP do WordPress](https://developer.wordpress.org/reference/hooks/http_request_args/).
- Diferenciar erro HTTP/credencial/timeout, ausência válida de taxas e necessidade de endereço completo conforme dados efetivamente disponíveis. Quando o fornecedor não expuser causa confiável, usar erro genérico de consulta, sem afirmar ausência de cobertura.

### 4.5 Cards, minicarrinho e transporte

- Para adição própria, usar `addItemToCart` público e preservar dados de variação/personalização aceitos pelo servidor. Um item é identificado por sua chave de carrinho, nunca pela posição do DOM ou apenas pelo ID de produto.
- Auditar consumidores de `wc_fragment_refresh`. Retirar disparo clássico das superfícies que já usam exclusivamente Blocks; preservá-lo apenas em consumidor clássico existente e demonstrado pelo teste.
- Emitir evento documentado de adição somente após sucesso. `preserveCartData: true` só quando o store já tiver sido atualizado pela ação responsável. Não acessar stores privados da Interactivity API para forçar sincronização.
- O adaptador conserva ações públicas; o coordenador específico do §15 usa middleware público wp.apiFetch exclusivamente no Cart Block, sem substituir fetch nem editar WooCommerce. Os endpoints próprios recebem URLs geradas no PHP, inclusive `rest_url()` e URL AJAX; nada de `/wp-json` fixo. Scripts usam dependências/versionamento declarados.
- Centralizar nonce e parsing apenas nos transportes próprios remanescentes. Uma falha limpa a Promise de nonce para permitir recuperação. Não desativar validação; não repetir automaticamente adição/remoção após timeout de resultado desconhecido.

## 5. Conteúdo administrável e acessibilidade

| Rota/superfície | Conteúdo e seleção | Origem de edição/persistência |
|---|---|---|
| PDP | Nome, imagem/alt, variação, preço e prazo próprio do produto | Produtos WooCommerce e Biblioteca de mídia; preservar metadados existentes |
| PDP | Rótulo CEP, Calcular, pendência e erro; aviso de estimativa | Textos funcionais traduzíveis no `petshop-core`; não criar copy comercial |
| Home/loja/busca/categoria | Cards, nome/miniatura e CTA funcional | Produtos/mídia; textos editoriais ao redor continuam Gutenberg |
| Header/minicarrinho | Logo/alt, títulos globais existentes; itens e totais dinâmicos | Configuração global existente + WooCommerce; nenhuma nova imagem |
| Carrinho | Campo CEP/consulta e estados funcionais | Inner block funcional visível no editor; traduções; dados de sessão |
| Carrinho | Textos comerciais e CTA editorial já existentes | Blocos nativos da página Carrinho; não substituir `post_content` inteiro |
| Checkout | Rótulos, validações, endereço e frete | APIs oficiais WooCommerce/traduções e campos adicionais existentes |
| Checkout | Mensagem global de segurança/atendimento existente | Fonte administrativa do Plano 020; preservar edição |
| Minha conta/cadastro | Rótulos e feedback ViaCEP | WooCommerce e traduções próprias; endereço salvo pelo formulário |

- Não adicionar conteúdo editorial ou caminhos de imagem fixos. Exceções Gutenberg: dados dinâmicos de WooCommerce e superfícies globais já existentes.
- O bloco funcional de CEP terá preview identificável no editor, label associado, teclado, foco previsível, estado ocupado e aviso acessível. Não mover foco a cada recálculo nem anunciar sucesso de resposta antiga.
- Migrar por `parse_blocks`/`serialize_blocks`: inserir uma vez sob `woocommerce/cart-totals-block`, com `parent` declarado e registro pela API oficial. Confirmar a inserção em editor/frontend da versão instalada; não montar via footer, polling do DOM ou observer global.
- Versionar migração de conteúdo e opção. Guardar evidência/backup do conteúdo e valor anteriores, registrar aplicação e preservar reordenação, remoção e edições posteriores do cliente. Não recriar automaticamente bloco removido após migração concluída.
- Validar alteração de texto editorial, troca de imagem/alt e reprovisionamento nas páginas tocadas: as alterações devem sobreviver. Não duplicar o campo próprio nem o widget de terceiro.

## 6. Sequência de implementação e gates de saída

Todas as sessões abaixo pertencem ao mesmo plano. Não publicar etapa intermediária que dependa dos guards removidos ou de resposta parcial para funcionar. Manter os arquivos locais preexistentes; reavaliar suas mudanças ao implementar, sem reset/stash automático.

### Sessão 01 — Baseline reproduzível e rede de regressão

- [ ] Registrar branch/HEAD, diff inicial, versões reais e opções não sensíveis de frete, impostos, destino e plugins; confirmar sincronização de plugin/tema no Docker.
- [ ] Transformar provas F01–F08 em testes determinísticos com sessões próprias, duas linhas e latência/reordenação controladas. Capturar o caso 1→3 com total de uma unidade como falha de baseline quando presente.
- [ ] Criar fixtures de produto simples/promocional/variável, dois itens com mesmo produto e metadados diferentes, estoque limitado e pacote múltiplo; restaurar dados de teste no encerramento.
- [ ] Criar registro por operação de início/fim/erro, requisições e leituras de DOM/store/API, sem dados reais de clientes.

**Gate:** testes reproduzem as falhas induzidas da auditoria e distinguem pendência de valor confirmado. Nenhum aceite depende de transportadora externa ou pagamento real.

### Sessão 02 — Destino e integração oficial do carrinho

- [ ] Implementar política única de CEP/UF e namespace da extensão; migração versionada de exibição antecipada de frete; remover cidade fictícia e restauração PHP tardia.
- [ ] Registrar inner block e `IntegrationInterface`; migrar página sem sobrescrever conteúdo; extrair compatibilidade de widgets de terceiro.
- [ ] Substituir CEP montado por DOM/POST manual pelo callback oficial e estados de erro/sem taxas; respeitar configuração alterada posteriormente pelo administrador.
- [ ] Retirar guards/interceptores e restaurar controles nativos; migrar gates 037/039 para comportamento funcional.

**Gate:** quantidade e totais coerentes sob cliques rápidos; endereço e seleção de frete não são descartados; CEP RS/SP funciona com configuração pretendida e não produz falso frete no modo de endereço completo. Resposta sem taxas elimina frete antigo. Sessão recarregada coincide com resposta.

### Sessão 03 — Endereço coerente em checkout e conta

- [ ] Implementar gerações/cancelamento no lookup único; separar adaptadores Blocks/formulários e retirar atualização de carrinho duplicada.
- [ ] Refatorar hidratação para execução inicial antes dos totais, com origem/CEP; preservar rascunhos e remover herança tardia campo a campo.
- [ ] Validar número/bairro/complemento, billing/shipping distintos, endereço salvo autenticado, visitante e transição de conta.

**Gate:** resposta SP atrasada não preenche CEP RS; nenhuma edição manual recente é apagada; formulários de endereço usam ViaCEP e cotação não usa. Nenhum endereço fictício chega a sessão, resposta ou pedido de teste.

### Sessão 04 — Produto, preferência e isolamento de cotação

- [ ] Enviar seleção completa, corrigir `formatted_data`, invalidar estimativas antigas e isolar pacote/cache de preview.
- [ ] Retirar `persistPostcode`; implementar preferência transitória e consumo único em carrinho/checkout, respeitando intenção de endereço mais recente.
- [ ] Implementar timeout restrito e distinção de falha/cobertura; preservar textos/metadados administráveis.

**Gate:** quantidades 1/2/3 e variações diferentes consultam pacotes diferentes; resposta antiga não aparece como atual; preview concorrente não altera itens, totais, destino efetivo, seleção ou cache real de carrinho. Navegação na mesma aba transmite preferência válida e não revive preferência expirada.

### Sessão 05 — Cards, minicarrinho e falhas de transporte

- [ ] Migrar consumidores próprios para adaptador; retirar duplicações de nonce/URL/recebimento e refresh sem consumidor.
- [ ] Validar adição/remoção, duas linhas, minicarrinho aberto sobre carrinho, navegação e releitura após atualização externa sequencial.
- [ ] Cobrir nonce expirado, erro de validação, 429/5xx, timeout e perda de conexão, sem sucesso sintético ou repetição automática indevida.

**Gate:** cards, minicarrinho, carrinho e checkout convergem para servidor; nenhum item histórico é reenviado e itens personalizados permanecem distintos. Instalação em subdiretório funciona.

### Sessão 06 — Fechamento técnico e operacional

- [ ] Executar matriz da seção 7, gates focados atualizados e validação completa obrigatória; revisar diff final para patches globais, hooks tardios e dados sensíveis.
- [ ] Rebuild/recreate de fechamento e conferência de assets/dependências conforme `AI_BOOTSTRAP.md`; comprovar que fonte e runtime coincidem.
- [ ] Validar staging com versões/configuração de destino reais e smoke de transportadoras; executar pedido apenas de teste, sem cobrança, conferindo total e endereços persistidos.
- [ ] Registrar evidências, limitações reais e rollback; atualizar este plano, documentação de edição e `Plans/STATUS.md` somente conforme resultado demonstrado.

**Gate:** não concluir com falha global silenciada, fornecedor simulado apresentado como real ou pendência de consistência. Falha externa à mudança deve ser identificada e registrada, sem classificar suíte reprovada como aprovada.

## 7. Matriz de validação

| ID | Ensaio mínimo obrigatório | Oráculo de aceite |
|---|---|---|
| T01 | 1→2→3 rápido, redução, alternância +/− e digitação; com e sem CEP | Linha/total nunca são compostos artificialmente; última intenção válida converge; nenhum valor antigo reaparece como confirmado |
| T02 | Duas linhas; A confirmado=3, alteração externa sequencial A=4, depois editar B | A permanece 4; nenhuma requisição própria reenvia A=3 |
| T03 | Quantidade em voo + CEP novo + seleção de entrega; latência 100/800/2000 ms e respostas fora de ordem | Operações próprias têm resultado explícito; nenhuma é descartada com HTTP 200 falso; estado final reflete ações confirmadas |
| T04 | Falha de quantidade antes de consultar CEP; timeout de operação própria | Erro visível; sem anunciar sucesso/reutilizar snapshot; recuperação lê estado real e não duplica operação |
| T05 | CEP com taxa → CEP sem taxa; método selecionado desaparece | Nenhum preço/método antigo permanece confirmado; cobertura e falha técnica são diferenciadas |
| T06 | `requires_address=no` e `yes`, visitante e autenticado; CEP 01310-100 e 91210-320 | Primeiro cálculo e serialização coerentes; modo restrito solicita endereço; sem cidade fictícia |
| T07 | Endereço completo salvo + CEP novo em outra UF; billing/shipping distintos; destino=billing | Sem endereço híbrido, hidratação tardia ou sobrescrita de rascunho; preço calculado para destino confirmado |
| T08 | ViaCEP SP lento/RS rápido, erro, inexistente, repetir CEP após falha e edição manual no meio | Apenas resultado vigente aplicado; preenchimento manual e campos próprios preservados |
| T09 | PDP: qtd 1/2/3, promoção, variação, sem seleção, virtual e mudanças durante consulta | Pacote corresponde à seleção; taxa antiga invalidada; nenhuma mutação do carrinho por preview |
| T10 | Preferência PDP → carrinho e checkout direto; expiração, logout, armazenamento indisponível | Consumo confirmado uma vez; prevalece edição recente; preferência contém apenas CEP e metadados definidos, sem endereço completo ou token |
| T11 | Cards Home/loja/busca/categoria, produto simples/variável; minicarrinho em Home e aberto sobre Cart | Quantidade, contagem e valores confirmados sincronizam pela ponte oficial; sem listeners de quantidade cruzando componentes |
| T12 | Estoque máximo, vendido individualmente, múltiplos e limites permitidos; personalização | Restrições nativas mantidas; chave do item correta; nenhum desbloqueio global |
| T13 | Cupom fixo/percentual, desconto por quantidade, imposto incl/excl, arredondamento e frete grátis por limiar | Comparação com totais oficiais, sem aritmética linear como oráculo; seleção e pacotes válidos |
| T14 | Dois pacotes, retirada e entrega; taxa de custo zero | Identificação por pacote/ID; zero não confundido com ausência; métodos somados/selecionados pelo WooCommerce |
| T15 | Preview e alteração real concorrentes; leitura independente e reload de sessão | Quantidades/linhas/totais/destino/seleção e caches reais preservados; sem restauração posterior de sessão inteira |
| T16 | Nonce expirado, 429/5xx/timeout; subdiretório e URL REST fornecida pelo PHP | Erro recuperável; nenhum falso sucesso, perda de envelope batch ou repetição de compra |
| T17 | 1440 e 390 px, teclado e leitores/árvore de acessibilidade | Campo utilizável, avisos e foco adequados; não depender de clique `force` em botão proibido |
| T18 | Edição Gutenberg de texto, imagem/alt, reordenação/remoção do bloco e reprovisionamento | Conteúdo e decisão administrativa preservados; bloco/widget não duplicados |
| T19 | Transportadoras reais em staging, configuração de zona e credencial válida/erro controlado | Integração real demonstrada sem fixar preço comercial como resultado esperado; logs sem dados sensíveis |
| T20 | Checkout com endereço incompleto/completo e pedido de teste sem cobrança | Incompleto bloqueado; pedido válido coincide com último total e endereço reais confirmados |

Repetir T01–T08 e T11 para visitante e autenticado, com ao menos dois produtos além da amostra histórica do 039. Usar contexto de teste isolado. Dados necessários aos ensaios são sintéticos; credenciais reais permanecem fora de arquivos/evidências versionadas.

### Instrumentação e scripts

- Criar `scripts/validate-041-cart-consistency-browser.mjs`, `validate-041-address-races-browser.mjs`, `validate-041-shipping-destination.php` e `validate-041-shipping-preview.php`; registrar seleção por arquivos no `scripts/run-gates.mjs`.
- Reaproveitar helpers de sessão, catálogo, login e transporte dos gates existentes. Atualizar 026/027/037/038/039 e `validate-shipping-quote-destination.php` para os contratos novos, retirando asserts de nome de guard, cidade transitória e debounce artificial.
- No browser, registrar respostas reais e mudanças do DOM desde a primeira interação, com observer de valores e marcas de tempo. Separar mudanças de seleção local, pendência e confirmação; após estabilizar, comparar com `GET cart` independente e reload.
- Em respostas reordenadas, atrasar requisições/respostas no harness; não injetar `receiveCart` fabricado no código de produção para fazê-lo passar. Não exigir que uma resposta sem confirmação seja instantaneamente igual à intenção local.
- Testes PHP verificam resultado calculado e sessão recarregada; presença textual de hooks não substitui teste de comportamento. Fixtures determinísticas de taxas não substituem T19.
- Diagnóstico usa `wc_get_logger()`/telemetria de teste com ID de operação, contexto, duração, resultado, versão e código de erro; sem CEP, endereço, documento, cookie, payload completo ou token. Debug detalhado somente em ambiente de teste.
- Durante iteração: gates focados e `npm run validate:changed`; regressão browser por `npm run validate:changed:browser`. Fechamento: `npm run validate` e os gates browser obrigatórios deste plano. Usar runtime Node/PHP do Compose, conforme bootstrap.

## 8. Rastreabilidade da auditoria

| Achado | Tratamento principal | Evidência exigida |
|---|---|---|
| F01 — snapshots misturados | Remoção de merges/pintura; aplicação nativa integral | T01, T03, T13 |
| F02 — intenção antiga reenviada | Remoção de histórico próprio de quantidades | T02, T11 |
| F03 — operação legítima descartada | Remoção de interceptor; wrappers oficiais | T03, T04, T16 |
| F04 — batch inválido | Remoção de resposta sintética; transporte nativo | T03, T16 |
| F05 — restauração depois de totais/sessão | Remoção do restore; cálculo nativo sem mutação de quantidade pela cotação | T06, T15 |
| F06 — ViaCEP fora de ordem | Geração por formulário e aplicação única | T07, T08 |
| F07 — erro silenciado | Resultado explícito, leitura/reconciliação e retry consciente | T04, T16 |
| F08 — taxa vazia ignorada | Aplicar resposta oficial incluindo ausência de taxas | T05 |
| F09 — PDP sempre com uma unidade | Seleção completa e invalidação por geração | T09, T15 |
| F10 — endereço híbrido | Origem/CEP e hidratação coerente antes do cálculo | T07, T08, T10 |
| F11 — município fictício/prioridade de hooks | Configuração nativa e endereço final obrigatório | T06, T20 |
| F12 — controles/associação por posição | Componentes nativos e chave real de item | T11, T12 |
| F13 — transporte, fornecedores, caches | Adaptador fino, timeout restrito, isolamento e diagnóstico | T14–T19 |

## Critérios de aceite

- [ ] F01–F13 têm implementação e evidência vinculadas, sem declarar o sintoma espontâneo de produção reproduzido quando só houver reprodução induzida.
- [ ] Não existe patch global de rede/store, resposta HTTP sintética, merge financeiro parcial, mapa histórico de quantidade ou desbloqueio indiscriminado de controles.
- [ ] Quantidade nativa respeita estoque/limites e as alterações rápidas não apresentam total de uma unidade como confirmado para várias unidades.
- [ ] CEP e seleção de entrega não são descartados; retorno sem taxas invalida entrega antiga; erros são recuperáveis e visíveis.
- [ ] Cidade fictícia e restauração tardia de quantidade foram removidas; configuração pretendida foi migrada uma vez e decisões posteriores do administrador são preservadas.
- [ ] PDP consulta quantidade/variação reais sem alterar o carrinho e transmite somente preferência válida no fluxo definido.
- [ ] ViaCEP é único nos formulários de endereço, rejeita respostas antigas e não é usado na cotação por CEP.
- [ ] Endereço salvo, CEP novo, rascunho manual e billing/shipping têm precedência e hidratação coerentes; nenhum enriquecimento tardio muda o destino depois dos totais.
- [ ] Cards, minicarrinho, carrinho e checkout refletem estado oficial; personalizações e pacotes distintos mantêm identidade.
- [ ] URLs/dependências, nonce, timeout e erros usam contratos públicos e não alteram fornecedores versionados.
- [ ] T01–T20 foram executados com evidências e cobertura definida; regressões existentes foram atualizadas sem manter critérios que exigem o mecanismo removido.
- [ ] Conteúdo/alt editáveis, traduções, teclado, responsividade e persistência após migração/reprovisionamento foram validados.
- [ ] Checkout bloqueia endereço incompleto e o pedido de teste confere com o último carrinho confirmado, sem cobrança real.
- [ ] Gates finais e revisão técnica estão registrados; não há segredo/dado pessoal nas evidências e nenhum item do escopo permanece sem validação.

## 9. Entrega, riscos e rollback

**Risco principal:** retirar guards pode revelar interferência ainda ativa de terceiros. Os testes devem identificar o emissor real; neutralizar apenas integração conflitante própria/por hook público com escopo comprovado. Não corrigir por outro interceptor ou pela restauração de um carrinho antigo.

**Configuração de produção:** a auditoria conheceu `requires_address=yes` por relato; a execução deve confirmar configuração e versão em staging antes de qualquer migração de produção. Frete por CEP não garante elegibilidade de todo método; métodos que exigem município dependem do endereço real.

**Atualizações WooCommerce:** verificar contratos públicos e bridge IAPI no runtime efetivo; não usar `trunk` como prova do instalado. Dependências novas de build ficam com lockfile, bundles e `.asset.php` reproduzíveis; declarar compatibilidade Blocks apenas após os gates.

**Rollback preparado:** registrar versão anterior de código/assets, valor anterior da opção e backup do conteúdo da página. Reverter como conjunto código + assets compatíveis e a migração administrativa, preservando conteúdo mais recente do cliente e carrinhos ativos. Não reinstalar um guard isolado sobre o fluxo novo nem restaurar tabelas de sessões de clientes a partir de backup.

**Handoff de implementação:** listar arquivos finais, cenários aprovados, versões/opções verificadas, evidências de staging e procedimentos de migração/rollback. Uma única tarefa ClickUp representa o plano quando sua criação for solicitada; não há tarefa, branch ou publicação criada por este documento.

## 10. Ledger da continuação — 08/10/2026

Branch retomada `codex/041-integridade-frete-carrinho-checkout`, HEAD `fe55572`. Alterações locais anteriores preservadas; sem commit, push, PR, merge ou publicação. Runtime local WordPress 7.0.2 / WooCommerce 10.9.4, PHP 8.3.32 e Node 24.18.0. Produção/staging não inspecionados. A auditoria original e esta continuação têm baselines distintas; não atribuir todos os diffs locais à última sessão.

| Etapa | Evidência/check | Tentativa anterior | Correção/reexecução | Estado atual |
|---|---|---|---|---|
| Entrega Docker | `docker compose build wordpress node` e `up -d --force-recreate --wait wordpress` | Build/recreate executados | Compose Watch manteve alterações posteriores; SHA-256 fonte/runtime de AddressLookup JS e CheckoutCustomerData PHP conferidos | Aprovado no checkpoint local; rebuild final após fechamento ainda necessário |
| Gates focados PHP/JS | `npm run validate:changed` | Falhas 036 (opção administrativa), 025 (cache do ensaio), corrigidas pelos contratos atuais | Suíte focada aprovada; 026 reexecutado após correções finais, incluindo metadados runtime preservados | Aprovado nos checkpoints descritos |
| Validação completa PHP | `npm run validate` | Executada com imagem atualizada | Aprovada, incluindo destino, preview e persistência 041; correções posteriores de checkout tiveram gate 026 próprio | Aprovada antes das últimas correções; não representa fechamento final |
| Unitários | `npm test` | Executado | 58 testes, 219 asserções, PHPUnit 10.5.64 | Aprovado |
| Destino e migração | `validate-041-shipping-destination.php`, `validate-041-block-persistence.php` | Ensaios com sessão/conta/página sintéticos | Mesmo CEP, novo CEP, contato isolado, limpeza explícita, reload e remoção/texto preservados aprovados | Cobertura parcial T06/T07/T18 |
| Preview | `validate-041-shipping-preview.php` | Fixture determinística nativa de frete grátis | Limiar, quantidade, contexto shipping/billing antes do preço e isolamento aprovados | Cobertura parcial T09/T13/T15; não comprova transportadora real |
| ViaCEP | `validate-041-address-races-browser.mjs`, ViaCEP isolado 026 | Gate 026 falhou por roteamento/batch, resolução inicial e autofill concorrente | Corrigidos fallback do harness, observação batch, espera inicial e dequeue público restrito; ViaCEP 026 isolado aprovado | Corridas clássicas e checkout básico aprovados; billing obrigatório aprovado na continuação adicional descrita abaixo |
| Regressões browser | `npm run validate:changed:browser` | 037, 039 e 025 aprovados; 026 reprovou PJ e ViaCEP | Serviço de campos adicionais, sincronização do perfil e lookup corrigidos; reexecução integral isolada de 026 aprovada: PF desktop, PJ mobile, visitante e ViaCEP | 026 aprovado após correção; 031 e 027 aprovados por execuções isoladas posteriores; suíte browser completa continua reprovada pelo gate financeiro 041 |
| Consistência 041 | `validate-041-cart-consistency-browser.mjs` com fixture autenticada | Comparação financeira reprovou entre store e GET independente | Adaptador passou a aguardar janela de 2000 ms após a extensão, cobrindo debounce nativo observado de 1500 ms; nova execução falhou no cenário autenticado: frete store `24895`, GET `2048` (centavos BRL), itens `8440` em ambos | **Bloqueador aberto**. Visitante 1440/390 passou nas latências 100/800/2000 ms e duas linhas; execução completa não passou |
| Revisão dedicada | Agente code-critic `review_041` | Encontrou leitura indevida de postmeta pelo cliente de sessão e falta de billing → shipping obrigatório | Cópia explícita de sete metas e espelhamento público corrigidos; revisão final focada sem novos P0/P1 | Revisão não substitui gate financeiro reprovado |
| Higiene | `git diff --check` e inspeção de arquivos | Executados | Sem erro de whitespace; credenciais 041 restritas a `.local` e removidas pelo cleanup | Aprovado no checkpoint |
| Staging/pedido | T19 e T20 | Não executados | Ambiente/credenciais de staging não fornecidos; pedido 012 de smoke da suíte não é evidência de T20 | Pendentes; sem cobrança real |

### Bloqueador e recuperação

O gate financeiro falhou novamente após uma correção material de sincronização. Não há ainda causa comprovada nem correção segura identificada para a divergência autenticada. Não atribuir a falha à transportadora sem evidência, não fixar um preço esperado e não remover a comparação de todos os totais com uma leitura independente. Os preços acima são resultados sintéticos de teste local, não tabela comercial nem garantia de cobertura.

Próximo passo: instrumentar a sequência autenticada desde o primeiro GET, incluindo identidade do método/pacote, resposta da extensão, sincronização nativa e releitura; identificar a primeira mudança de destino/método/cache que explica a diferença. Separar ensaios determinísticos de consistência de T19 real, com fixture estritamente isolada, sem editar fornecedor ou afetar sessões de compradores. Só repetir o gate após correção concreta ou mudança comprovada de pré-requisito. Após aprovação, voltar às lacunas abaixo e executar a matriz de fechamento.

Evidências locais parciais: `.local/evidence/041-cart-consistency/` (screenshots, transições e rede de visitante), `.local/evidence/026/` e `.local/evidence/039-cart-qty/`. Ausência de `results.json` final de 041 indica execução não aprovada; screenshots antigos/interrompidos não completam cobertura. O harness foi enriquecido após a falha para registrar método/pacote/totais de store/API e transições em `failure.json`, sem endereço, cookies ou tokens; essa instrumentação passou por `node --check`, ainda sem nova execução financeira. Não versionar credenciais ou diagnósticos de payload completo.

O cleanup 026 via painel não autenticou com as credenciais padrão do harness; foram removidas por WP-CLI somente as contas sintéticas desta execução e da tentativa anterior (IDs 122, 123, 143 e 144). Fixtures anteriores de outras execuções foram preservadas. O wrapper restaurou `home`/`siteurl` locais e limpou cache; a fixture 041 foi removida no `finally`.

### Continuação autorizada: correções e cobertura independentes do bloqueador

O usuário autorizou seguir com o restante sanável, preservando o bloqueador financeiro. Não houve nova execução do gate financeiro sem correção de causa. Uma falha própria adicional foi reproduzida e corrigida: `crypto.randomUUID` ausente em HTTP fazia a preferência de CEP não ser gravada. O fallback usa `crypto.getRandomValues`; teste antes/depois reprovou/aprovou. Fonte e asset entregue tiveram SHA-256 conferido; revisão dedicada focada não encontrou problema nessa correção.

| Etapa | Baseline/tentativa | Correção e evidência | Estado |
|---|---|---|---|
| Preferência HTTP | Gate isolado reprovou quando randomUUID não existia | `validate-041-quote-preference.mjs`: HTTP, campos permitidos, consumo único, geração antiga, expiração, logout/troca de conta, armazenamento bloqueado e separação por chave de subdiretório aprovados | Corrigido; instalação real em subdiretório ainda pendente |
| Cards 031 | Harness esperava somente POST direto; operação oficial também usa batch | Observação de requests/responses e negativos atualizada para os dois envelopes, verificando sucesso interno. Reexecução aprovada em Home, loja, busca, relacionados, promoção, variações, minicarrinho, sem seleção, sem estoque e personalizável | Aprovado isoladamente; fixture/Home restaurados |
| Hub 027 | Gate legado supunha que preview persistia CEP no servidor | Verifica carrinho/endereço intactos após preview, preferência local, adição real, consumo no Cart e manutenção no Checkout | PHP e browser aprovados |
| PDP ampliada | Falhas iniciais de harness: caminho não montado e interpretação de FormData multipart | `validate-041-product-quote-browser.mjs`: 12 verificações aprovadas, incluindo qtd 1/2/3, resposta atrasada, taxas vazias, 429/503/nonce/offline/timeout e preferência PDP → checkout direto → reload | Transporte de preview induzido; não comprova API da transportadora |
| Adaptador | Teste isolado dos contratos públicos | `validate-041-cart-operations.mjs`: pendência expira sem enviar destino, geração antiga não envia, erros propagados, retry recupera snapshot oficial, fila serializada | Aprovado; não substitui falha de quantidade browser |
| Preview real em PHP | Estendido o ensaio existente | Variação em promoção, quantidade 2, peso específico e produtos/variações virtuais aprovados; virtuais não acionam cálculo; referências/sessão/caches preservados | Cobertura parcial T09/T15 ampliada |
| Billing obrigatório | Primeiro ensaio usava billing (somente padrão), portanto não era prova de forcedBillingAddress | Reexecutado com billing_only e asserção explícita da configuração pública; geografia billing/shipping coincide na Store API; número/complemento manual, troca de CEP, inexistente e indisponibilidade aprovados | Aprovado no visitante; opção restaurada no finally |
| Gutenberg | Harness corrigido para WP-CLI eval sem strict_types e atributo RichText convertido em texto; reset de socket em leitura recuperado | `validate-041-editor-browser.mjs`: preview no canvas, texto, substituição de mídia/alt, reordenação, remoção e save/reload aprovados. Gate PHP de migração também preserva imagem/alt/texto/remoção | Editor/página/mídias sintéticos removidos; T18 ampliado |
| Comércio nativo | Novo processo isolado, sem opções globais alteradas | `validate-041-native-commerce.php`: cupons fixo/percentual, impostos incl/excl, totais nativos antes/depois do CEP, dois pacotes e seleção entrega/retirada zero, estoque máximo e vendido individualmente aprovados | Métodos sintéticos; cobertura parcial T12–T14 |
| Harness HTTP | Reset de socket em asset de leitura interrompeu um ensaio | Retry de transporte limitado a GET/HEAD; escritas nunca repetidas. Erros omitem call log com cookies. Editor e PDP aprovados após ajuste | Correção de infraestrutura de teste, não de produção |
| Revisão dos ensaios novos | Identificou early return no comércio nativo, cleanup Bash interrompido por erro, provisionamento JS parcial e assert insuficiente após reload | Comércio agora alterna CEP e exige origem quote/geografia limpa; cleanup/restauração continuam após falha; cleanup armado antes do provisionamento; PDP compara CEP e itens de store/API após reload e intercepta o AJAX próprio do lookup | Achados corrigidos; comércio e PDP reexecutados e aprovados |
| Cleanup e sintaxe dos runners | Falhas induzidas nas funções reais, sem alterar banco/URLs | `validate-041-runner-cleanup.mjs` aprovado: Bash preserva erro original e tenta todos os cleanups/restores; JS remove fixture após provisionamento parcial e tenta cleanup 031 mesmo com erro de cliente 041. Sintaxe PowerShell/JS/Bash aprovada | Gate integrado aos três runners |
| Validação da continuação | `npm run validate:changed` | 50 arquivos detectados, suites focadas PHP/Node aprovadas e fixtures removidas. Ajustes posteriores restritos ao harness/cleanup tiveram gates próprios acima | Aprovado como checkpoint; não equivale à matriz browser completa |
| Ambiente final | SHA-256 fonte/runtime da preferência conferido | `home` e `siteurl` restaurados para localhost:8888; opção de destino restaurada para billing; fixtures próprias de editor, cliente e cards limpas | Sem commit/push/PR/merge |

Os runners incluem os novos ensaios PHP/Node/editor/PDP; credenciais de editor e cliente ficam somente em `.local`, com cleanup. Evidências novas: `.local/evidence/041-product-quote/`, `.local/evidence/041-editor/` e `.local/evidence/027/`. A revisão apontou negativos 031 que observavam só POST direto e alegação excessiva de isolamento com transporte mockado; ambos foram corrigidos e os gates afetados passaram.

### Lacunas obrigatórias ainda abertas

**Diagnóstico adicional solicitado pelo usuário:** [comparação de chamadas, produtos, destino e peso](../docs/diagnostico-frete-041-2026-10-08.md). Reprodução autenticada confirmou mesma sessão e mesmos campos observados do pacote: produtos 2406 × 3 e 2405 × 1, 44 kg e endereço completo idêntico. No batch, Sedex muda para R$ 263,62 e Jadlog R$ 248,95 fica selecionado; no GET, Sedex retorna R$ 20,48 e fica selecionado. Causa e preço correto ainda não comprovados; próximo diagnóstico é cache/contexto e chamada externa, sem enfraquecer o gate. Não houve correção de produção na etapa desse diagnóstico.

- T01–T03: fechar cenário autenticado, entrega selecionada e reordenação real; os ensaios existentes não completam todas as permutações.
- T04/T05/T16: falha real de quantidade antes de CEP e desaparecimento de método selecionado no Cart; timeout/nonce/429/5xx/conexão têm cobertura PDP e contrato isolado, faltam Cart integrado e instalação real em subdiretório.
- T06–T08: repetir cobertura browser autenticada e configuração restrita, billing/shipping distintos; billing obrigatório visitante agora aprovado; o PHP não substitui as combinações restantes.
- T09/T10/T11/T12: completar variações/virtual na interface PDP e todas as rotas/mini sobre Cart/múltiplos; seleção/promoção/virtual PHP, preferência expirada/logout/storage e checkout direto têm evidência focada; não representam toda a matriz.
- T13/T14/T15: completar browser de cupons/impostos, múltiplos pacotes e preview concorrente real com reload; totais oficiais/cuponagem/impostos/pacotes/retirada zero têm ensaio PHP isolado aprovado.
- T17/T18: completar teclado/árvore acessível. Gutenberg com edição real de texto/imagem/alt, reordenação, remoção e reload aprovado; reprovisionamento idempotente aprovado em ensaio PHP separado.
- T19/T20: staging real e pedido sem cobrança com endereço e total finais conferidos.

Documentação operacional: [operação, superfícies administrativas e rollback](../docs/operacao-frete-plano-041.md). Critérios amplos e sessões permanecem desmarcados para não transformar implementação parcial em aceite.

## 11. Correção do carrinho reportado — 09/10/2026

O print do usuário mostrou CEP fora da coluna de totais e uma única entrega no resumo. Reprodução local com a bandana real (produto 1486 × 1), CEP 91210-320: PDP e Store API retornaram três métodos (IDs `4`, `5` e `virtuaria-correios-sedex:2`), mas a interface do Cart tinha zero seletores e o CEP estava fora de Totais. Portanto, a ausência das alternativas neste caso era de interface, sem evidência de perda de métodos na API.

Causas e correções:

- A migração v1 inseria o slot após `</div>` do wrapper salvo de Totais. A inserção inicial agora precede o fechamento; reparo v2 restrito ao conteúdo legado preserva ordem, remoção do bloco e conteúdo editorial, com backup em `petshop_cart_shipping_quote_wrapper_v2`. `wp_slash` preserva atributos serializados com aspas/barras.
- O componente React não aplicava as classes de formulário/linha/botão/estado usadas pelo tema. Classes e linha de campo/botão restauradas; CSS de CEP restrito ao campo de texto, sem atingir radios.
- O resumo nativo de Cart do WooCommerce 10.9.4 só mostra o método selecionado. O bloco agora lista todos os métodos da store pública por pacote, formata os valores pelo componente nativo e seleciona pela ação pública, com confirmação, rollback nativo e erro recuperável.

Revisão dedicada sem novos P0/P1. Observações sobre integração do gate nos runners e verificação dos preços renderizados atendidas. O teste de erro cobre o transporte direto e o batch nativo; o primeiro ensaio não interceptava batch e foi corrigido, sem alterar a implementação de produção.

Validação deste checkpoint: gate de entrega aprovado em 1440/390 px com produto real 1486 × 1 e CEP 91210-320, todos os três métodos da PDP/API, preços formatados, teclado, seleção, 503 induzido em batch, rollback/retry e reload. Persistência PHP aprovada, incluindo atributos com aspas/barras. Gates gerais alterados: `npm run validate:changed` aprovado em 53 arquivos; browser de entrega executado separadamente e aprovado. Contratos isolados de seleção por pacote, preferência e cleanup de runners passaram (cleanup executado no container Node; o Bash não está disponível no Node nativo do Windows). A matriz financeira autenticada descrita no §10 continua aberta; esta correção de interface não prova sua causa nem fecha o plano. Sem commit, push ou publicação.

Fonte/runtime dos JS do bloco e adaptador, PHP de migração e CSS conferidos por SHA-256. Evidências em .local/evidence/041-cart-delivery/. Carrinho e campo CEP cabem no viewport mobile; a página tem 9 px de transbordamento já observado também na Home, fora do bloco corrigido. URLs restauradas pelo wrapper para localhost:8888.

### Ajuste visual solicitado após o checkpoint — 09/10/2026

Usuário determinou restaurar a apresentação anterior à branch, manter a funcionalidade e colocar o CEP acima do total. Comparação com `bdf6632` confirmou que o formulário era inserido no início da coluna de Totais, e que o tema já possuía classes próprias para label/linha/input/botão. O formulário mantém esses estilos anteriores; os seletores novos de entrega passaram a usar `RadioControl` nativo em vez de CSS próprio. O seletor CSS de input permanece limitado ao CEP para não deformar radios. Tema incrementado de 0.6.28 para 0.6.29 para invalidar CSS cacheado.

Inserção inicial passou para o primeiro filho de Totais. Migração única de posição v3 move somente o bloco existente e armazena o conteúdo anterior, sem recriar remoções nem sobrepor reordenações posteriores feitas no editor. Não altera preços, prazos, fornecedores, destino, fila de operações ou totais.

Gate PHP de persistência aprovado, incluindo posição antes do resumo, conteúdo escapado, remoção e preservação de reordenação posterior. Revisão dedicada sem P0/P1. Browser de entrega com posição acima do total e RadioControl nativo aprovado em 1440/390 px; inspeção visual identificou moldura herdada de fieldset. Substituído por grupo acessível, com bloqueio pendente mantido no componente nativo; revalidação final aprovada em 1440/390 px, incluindo preços renderizados, posição acima do total, teclado, seleção, erro/retry, total oficial e reload. Screenshots finais inspecionados. PHP lint, contrato do adaptador, sintaxe JS e diff check aprovados; fonte/runtime sincronizados. URLs locais restauradas. O aceite financeiro autenticado permanece aberto conforme §10.
## 12. Checkout: CEP primeiro e campos únicos — 09/10/2026

Usuário reportou CEP no fim do endereço, consulta ausente ao entrar com CEP da sessão e campos repetidos. Registro real mostrou `petshop/number`, `petshop/neighborhood`, `petshop/person-type`, `petshop/document` junto de `virtuaria-correios/cpf`, `cellphone`, `neighborhood` e `number`; a UI rotulava o campo extra de CPF como CEP. O registro redundante do fornecedor foi removido pelo callback público específico antes da execução, mantendo transportadora, campos clássicos e persistência legada.

Filtros oficiais de locale colocam CEP primeiro e mantêm ordem de DOM/teclado. A primeira tentativa com índice zero falhou no browser porque o normalizador instalado ignora zero; corrigido para índice -10. Lookup único agora observa a resolução pública do carrinho e consulta CEP da sessão sem input/blur. Na consulta inicial, preenche somente valores vazios e preserva os detalhes salvos do cliente. Novo CEP digitado mantém o comportamento de lookup completo com proteção de corrida/edição manual.

A revisão dedicada identificou um ramo que consumia preferência do mesmo CEP com endereço completo sem garantir nova consulta; corrigido com chamada explícita após consumo. Cobertura ampliada para endereço salvo customizado e preferência. PHP 026 aprovado, incluindo registro real sem campos duplicados e prioridade efetiva do Checkout Block. Browser 1440/390 aprovado para campos únicos/ordem e lookup de sessão/digitação/reload; versão ampliada aprovada em 1440/390, com rua/cidade/bairro/complemento customizados preservados no DOM e Store API, preferência de mesmo CEP e consulta imediata. Aviso ViaCEP movido para linha própria, sem sobreposição ao país (geometria verificada no browser); tema 0.6.30 invalida CSS. Sintaxe JS/PHP, parsing dos três runners e diff check aprovados. Regressão browser 026 integral aprovada: PF em desktop, PJ em mobile, visitante e negativos ViaCEP (CEP inválido, falha de rede, edição manual e sincronização billing/shipping). O cleanup administrativo do harness não autenticou; os dois usuários sintéticos desta execução foram removidos explicitamente por WP-CLI. URLs restauradas para localhost:8888.

A revisão encontrou incompatibilidade de metadados de documento ao retirar os campos redundantes: consumidores dos Correios exigem tipo pf/pj e CPF/CNPJ com e sem prefixo underscore. A persistência do campo canônico agora sincroniza esses formatos no pedido e limpa o documento do tipo anterior. PHP 026 aprovado após o ajuste, com pedido draft persistido e recarregado em PF → PJ → PF; fixture removida no finally. A regressão browser acima precede apenas este ajuste de metadados de pedido, coberto pelo ensaio PHP. Revisão final dedicada confirmou o P1 resolvido, sem P0/P1 restantes nesses arquivos. SHA-256 dos três arquivos de produção conferidos contra o runtime; URLs locais restauradas.

Sem alteração de preços, prazos ou métodos de frete. Plano continua em andamento pelas lacunas financeiras e de matriz anteriores. Sem commit/push/publicação.
## 13. Composição de endereço conforme referência — 09/10/2026

Usuário confirmou substituir CEP primeiro pela ordem da imagem e preservar o visual da loja. Alcance: formulários de entrega e cobrança do Checkout Block; contato, frete e pagamento mantidos. Destinatário: Nome/Sobrenome; Endereço: País/CEP, aviso ViaCEP, Rua/Número (3:1), Complemento em largura total sempre disponível, Bairro/Cidade, Estado/Telefone. Duas colunas no desktop e uma no mobile estreito, com espaçamento de 16/12 px e 14/10 px respectivamente. Textos novos Destinatário/Endereço e labels de rua/complemento são funcionais traduzíveis; nenhum conteúdo editorial ou imagem novo. Títulos principais continuam no Checkout Block editável pelo Gutenberg.

Ordem por locale público inclui campos adicionais. WooCommerce agrupa complemento após rua e ignora seu índice: decoração restrita do tema usa o toggle nativo para abrir complemento e o reposiciona no mesmo pai após número, conservando componente/valor/eventos. Headings idempotentes realinhados após hidratação. CSS restrito a shipping/billing, tema 0.6.31. Baseline: layout anterior aprovado no §12. Primeiros ensaios novos identificaram ordem do complemento, especificidade de display e margem interna do select de estado; correções localizadas aplicadas. PHP 026 aprovado para sequência pública e contratos de persistência. Validação browser ampliada em execução, com entrega/cobrança independente, remontagem, consulta automática, preservação e erros React. Plano continua em andamento conforme §10; sem commit/push/publicação.

Revisão dedicada do delta visual sem P0/P1. Gate adicional de cobrança revelou fixture incompleta após uncheck: WooCommerce limpa os campos e não envia endereço incompleto; fixture agora completa os obrigatórios antes de verificar Store API, mantendo edição de número/complemento consecutiva. Assert geral pageerror expôs crypto.randomUUID e timeout Melidata; strings confirmadas nos assets instalados de Mercado Pago, fora do código alterado. Gate guarda message/stack e bloqueia erros React/reconciliação DOM e origem do script próprio. Sintaxe PHP/JS e diff check aprovados. SHA-256 CSS/script do tema conferidos contra runtime por Compose Watch.

Validação final aprovada em 1440/390: sequência DOM/teclado, pares desktop alinhados, campos únicos, complemento aberto, consulta automática da sessão/digitação/reload, preservação de rua/cidade/bairro/complemento customizados, preferência do mesmo CEP, cobrança independente completa com número/complemento editados consecutivamente, remontagem e persistência Store API. Screenshots finais inspecionados; CSS/card com título e checkbox conferidos. Pageerrors gravados apontam exclusivamente para Melidata/Mercado Pago. Revisão P2 atendida com exceção restrita às duas mensagens e seus assets conhecidos; auditoria independente sobre os stacks da execução confirmou zero erros próprios/desconhecidos com a regra final. Não foi necessário repetir browser por esse endurecimento do filtro sobre a mesma evidência. URLs localhost:8888 restauradas; sem alteração de fornecedor nem publicação. Evidências: .local/evidence/041-checkout-address/results.json e 1440.png/390.png. As lacunas financeiras anteriores continuam abertas.

## 14. Lentidão na troca de parceiro — 09/10/2026

Usuário reportou demora e avisos React. Baseline do mesmo produto 1486 × 1, CEP 91210-320 e endereço completo: carrinho 3,87–5,03 s e checkout 3,05–3,29 s. Probe temporário demonstrou que Woo Better apaga shipping_for_package_0..9 em cada cálculo mesmo com regras de frete grátis desativadas; selecionar parceiro refazia cotation e deadline dos Correios. Adaptador removeu dois períodos artificiais de 500 ms apenas da seleção, conservando fila, pendências e espera de CEP.

Integração pelo hook público REST substitui temporariamente o callback específico por guarda no totals, quando ambas regras estão inativas. Preserva cache nativo pago; se existir oferta gratuita legada pelos IDs próprios, executa limpeza original. Revisão detectou primeiro a necessidade da proteção de sessão legada, depois identidade de rate (get_id, não method_id genérico free_shipping). Revalidação real expôs sessão ausente antes do callback REST: proteção agora consulta no totals após hidratação Store API, sem inicializar sessão antecipadamente. PHP gate cobre sessão tardia, cache pago preservado, descarte efetivo de ofertas antigas via callback instalado, regras ativas, outras rotas e restauração. Fixture usa identidade real do fornecedor. Assert inicial de igualdade estrita falhou por desserialização de objetos da sessão; comparação de conteúdo corrigida e gate aprovado. Revisão final sem P0/P1, cobertura P2 atendida. Não há hooks administrativos globais novos.

Aviso autocomplete corrigido nos campos próprios com atributo data público e propriedade DOM aplicada pelo lookup. Autofill conferido no browser. O aviso useSelect/getValidationError continua: fonte minificada instalada do WooCommerce cria função nova dentro do seletor de validação; não pertence ao seletor próprio. Não houve supressão de console nem edição de vendor.

Benchmark final real sem probe: carrinho 571/715/1622 ms; checkout 580/531/507 ms. Cinco de seis trocas ficaram entre 0,5–0,7 s; variação restante não investigada como garantia de SLA. Evidências baseline/results/server-profile em .local/evidence/041-delivery-performance/. ViaCEP induzido para fixture; transportadoras/Store API reais. PHP 026, cache, adaptador isolado, lint PHP/JS, parsing PS/Bash e diff check aprovados. Novo teste strict executado por wp eval require nos três runners; eval-file inicialmente falhou antes da alteração do runner. Regressão de entrega final após guarda aprovada em 1440/390: métodos/preços, teclado, seleção, total oficial, 503 induzido, rollback/retry e reload. Comércio nativo isolado aprovado para cupons, impostos, dois pacotes, retirada zero e estoque; não substitui matriz financeira com transportadora real. Fonte/runtime dos cinco arquivos de produção conferidos por SHA-256. Probe MU e log temporário removidos. URLs restauradas pelo wrapper para localhost:8888. Sem commit/push/publicação; plano continua em andamento pelas lacunas do §10.

## 15. Concorrência de quantidade e CEP — 09/10/2026

Usuário reportou retorno temporário a valores antigos/irreais e exigiu uma única requisição, com descarte da anterior substituída. Alcance desta continuação: requisições Store API de carrinho no Cart Block que carrega a integração própria; mantém APIs oficiais de preço, validação, estoque e sessão. Pedido substitui a restrição anterior de não coordenar rede para a exceção descrita abaixo; a sincronização continua pela ação pública nativa, sem receiveCart manual; não autoriza patches de fornecedor nem montagem de totais.

Baseline: relato e falha financeira autenticada do §10 permaneciam conhecidos; leitura do Woo instalado confirmou AbortController por linha que aborta HTTP antigo, mas não sua gravação em PHP. Adaptador próprio só aguardava pendências e não serializava esse transporte. Não houve ensaio baseline novo de concorrência antes da primeira mudança; não tratar testes posteriores como prova de falha anterior reproduzida.

Coordenador usa wp.apiFetch.use público, restrito a cart ou batch formado inteiramente de operações cart. Requisição física anterior termina antes da seguinte; signal nativo é conservado como intenção lógica, mas não enviado ao fetch para liberar a fila antes do fim da gravação. Corpo Response é drenado. Respostas e erros substituídos recebem AbortError; chamadas abortadas ainda na fila são descartadas sem envio. Não transforma payloads, batch nem preços. Request de checkout/pagamento e batch misto passam intactos. Retry de nonce reconhece as opções originais por WeakMap/revisão e não reintroduz escrita antiga depois de nova intenção. Endpoint próprio auxiliar vem de rest_url, incluindo reconhecimento de rest_route.

CEP permite editar e reenviar durante atualização; edição invalida a consulta antiga, cada geração só encerra sua própria pendência. Valores monetários ficam sem apresentação confirmada enquanto há atualização/rascunho, com aviso funcional traduzível. Não há preços sintetizados; estilo e disposição anteriores mantidos, tema 0.6.32. Campos, imagens e conteúdo comercial continuam nas origens administrativas anteriores; apenas mensagens funcionais traduzíveis adicionadas.

Revisão dedicada encontrou P1 de erro antigo com data.cart/nonce, corrigido no catch. Também encontrou early return de quantidade: store1, escrita3 em voo, retorno1 não cria requisição nativa; debounce também pode perder 1→3→1→3. Atributo nativo data-cart-item-key expõe a chave real da linha; captura somente o desejo atual de input/stepper e identifica jobs ativos/pendentes por chave, sem mapa histórico de quantidades. No retorno ao valor confirmado ou de um job descartado, requisição auxiliar update-item oficial resgata a intenção omitida, sincronizando pela ação pública syncCartWithIAPIStore, que relê o carrinho integral e atualiza os headers nativos; erro atual continua nativo, resposta antiga nunca é aplicada. Revisões apontaram esses casos antes de os testes os cobrirem. Após duas revisões com achados, fechamento por revisão local do delta corrigido e gates específicos, conforme limite da skill.

Gates: unit novo aprovado para uma escrita física, abort em fila, descarte de resposta/erro.data.cart/nonce antigos, retry durante refresh, checkout intacto e retornos de intenção; adaptador isolado aprovado. Browser real com atraso900ms aprovado em 1440/390 visitante/autenticado: 3→5 junto de CEP, nova consulta substituindo CEP em voo, input real1→3→1 e 1→3→1→3, máximo1 requisição, ausência da quantidade antiga no store e totais iguais ao GET independente. Produto1486; não equivale à matriz financeira autenticada de44kg do §10. Evidências em .local/evidence/041-cart-concurrency/. Primeiro gate foi ampliado em etapas; ensaios visitantes precederam os dois casos de retorno e a ampliação autenticada. Validação geral alterada e regressão final de entrega aprovadas conforme checkpoint abaixo. Plano continua Em andamento, sem commit/push/publicação.

Revisão local adicional identificou que encerrar imediatamente uma quantidade descartada libera o hook nativo, que pode restaurar o input a partir do snapshot anterior enquanto a intenção vigente ainda está sendo gravada. Rejeições antigas de quantidade ficam pendentes até a resposta final ser aplicada; cancelamentos de CEP não são adiados, preservando erros atuais. Escritas de linhas distintas são mantidas em ordem, mas seus snapshots intermediários são descartados. Unit cobre duas chaves simultâneas e encerramento tardio; browser amostra o input a cada 10 ms na sequência de retorno para detectar reversão transitória.

Checkpoint final de concorrência após sincronização nativa: unit no container Node aprovado, browser final aprovado nos quatro cenários 1440/390 visitante/autenticado, máximo físico1, input sem reversão transitória e totais iguais ao GET independente. SHA-256 dos cinco arquivos próprios de produção confere com runtime. Gate geral alterado aprovado em 63 arquivos; a primeira execução encontrou arquivo fonte ausente no container Node, corrigido por leitura do asset entregue quando não há fonte montada. O rerun geral passou; os deltas JS posteriores de pendência/sincronização tiveram unit e browser finais próprios. Adaptador/preferência, sintaxe JS e diff check aprovados. URLs restauradas e cliente sintético removido pelo finally. Não representa aprovação da matriz44kg nem conclusão integral do plano.

Regressão final de entrega após o coordenador aprovada em1440/390: todos os métodos/preços PDP/API, seleção por teclado, erro503, rollback/retry, total nativo e reload. O CEP permanece acima do total, sem regressão de disposição. Evidência .local/evidence/041-cart-delivery/results.json; URLs restauradas. Teste financeiro com duas linhas executado separadamente, registrado abaixo.

Ensaio amplo final validate-041-cart-consistency-browser.mjs executado após a alteração concreta do transporte. Falhou no primeiro cenário visitante desktop: waitQuantity atingiu limite20s durante cotação do pacote pesado; na evidência final quantidade3/1 está correta e pendência nativa encerrada. Snapshot do carrinho tem Jadlog248,95 (Sedex263,62 disponível), GET independente retorna Sedex20,48, reproduzindo a divergência financeira já registrada no §10. Não atribuir causa ao fornecedor sem prova. Demais cenários desse gate não foram executados nesta tentativa. Não aumentar timeout para declarar aprovação nem encerrar plano. Evidence failure.json inclui snapshots/transições; URLs localhost:8888 restauradas, fixture removida. Gate específico de concorrência com produto1486 e unit de duas chaves permaneceram aprovados; não equivalem a essa matriz financeira.

## 16. Plano de ação: quantidade responsiva e totais separados — 09/10/2026

**Estado:** em implementação e validação desde 09/10/2026. Continuação do mesmo plano e branch; não criar outro número para corrigir esta regressão. Este §16 define o comportamento alvo e substitui o algoritmo de desempenho do §15. Evidências anteriores permanecem checkpoints históricos.

### 16.1 Motivação, alcance e limites

O usuário reportou lentidão extrema após a serialização: aumentar cinco unidades não deve pagar o custo de cinco cálculos completos. Quer retorno imediato sobre os produtos, com entrega e total geral claramente separados. O baseline elimina respostas antigas, mas espera a gravação ativa, encadeia pendências e oculta indiscriminadamente valores de produtos e do resumo.

Alcance desta melhoria: todas as linhas do Cart Block em `/carrinho/`, incluindo produtos simples/variáveis, alterações por teclado/stepper, CEP e seleção de entrega na mesma aba; regressão obrigatória no minicarrinho, PDP e checkout por compartilharem dados/assets. Mantém layout e cores aprovados, CEP acima do total e o endereço do checkout conforme referência. A divergência financeira do pacote pesado continua critério obrigatório do plano 041, separada das métricas de responsividade.

Fora do escopo desta continuação: redesenho comercial, edição de fornecedor, motor financeiro no navegador, novo endpoint de escrita que duplique o Store API, worker de cotação em segundo plano, locks distribuídos, garantia de cancelamento PHP e concorrência entre dispositivos. Não criar tarefa ClickUp duplicada; o vínculo externo continua ausente e depende da disponibilidade do conector, conforme cabeçalho.

### 16.2 Fundamentos e decisões

- O servidor confirma quantidades, preços, descontos, impostos e entrega; estado transitório de interação permanece no cliente. Fonte: [fluxo oficial WooCommerce](https://developer.woocommerce.com/docs/block-development/reference/overview-of-data-flow/).
- Usar ações públicas do [Cart Store](https://developer.woocommerce.com/docs/block-development/reference/data-store/cart/) e middleware público [wp.apiFetch](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/). Não sobrescrever actions de vendor, importar módulos internos ou misturar fragmentos financeiros de respostas distintas.
- Cancelamento de transporte e descarte de resultado são contratos diferentes. Eliminar trabalho antes de enviar é a otimização principal. Abortar HTTP não será tratado como prova de reversão de uma escrita já iniciada no servidor.
- Anunciar conclusão/erro sem mover foco, com status acessível, conforme [ARIA22/W3C](https://www.w3.org/WAI/WCAG22/Techniques/aria/ARIA22.html). Evitar anunciar cada clique. Indicador de carregamento após 300 ms; estado pendente e impedimento de confirmação indevida começam imediatamente.

### 16.3 Algoritmo alvo de quantidade e rede

1. **Desejo atual por chave:** manter somente `{key, quantity, revision}` vigente de cada linha, estado efêmero e limitado às linhas presentes. Não conservar uma lista de quantidades anteriores nem reenviá-las na edição de outra linha.
2. **Sem debounce adicional no plugin:** preservar o debounce nativo de 400 ms do hook da versão instalada; remover esperas próprias adicionais do caminho de quantidade. Inspeção do componente instalado durante a implementação confirmou também um debounce interno de 600 ms para digitação numérica, liberado por blur/Enter; o stepper não depende desse período interno. Cada input atualiza o desejo imediatamente e substitui o trabalho ainda não enviado da mesma chave. Não adicionar outro debounce por cima do nativo nem alterar fornecedor para remover esses períodos.
3. **Coalescência antes do envio:** ao chegar uma nova alteração da mesma chave ao coordenador, remover/rejeitar a anterior ainda na fila, preservando só a última. Fazer isso também quando não houve evento DOM, pois uma ação pública pode originar a mudança. A própria fila valida a revisão/chave; não depender apenas de AbortSignal, que o early return nativo pode deixar de cancelar.
4. **Uma operação ativa:** a escrita iniciada drena até terminar; se ficou obsoleta, descartar seu resultado e erro. Em seguida enviar diretamente o último desejo pendente. Não executar os valores intermediários. Cinco mudanças durante uma escrita lenta devem resultar em, no máximo, a escrita ativa mais uma escrita final para aquela chave.
5. **Linhas independentes:** substituir trabalhos da mesma chave sem apagar os de outra linha. Adição, remoção e cupom são operações preservadas em ordem; remoção elimina desejos de quantidade da chave removida. Nunca cancelar uma alteração de B por mudar A. Limpar estado ao desmontar/remover linha.
6. **CEP e método:** reter apenas o último destino pendente; a cotação pendente deve usar as quantidades vigentes já persistidas. Não confirmar frete de pacote intermediário. A escolha de método usa o último pacote confirmado e é revalidada se o pacote mudar. Não cancelar operações de checkout/pagamento nem interpretar batch misto como atualização substituível de quantidade.
7. **Aplicação de resposta:** validar revisão e dependências antes de confirmar. Aplicar carrinho integral pelo fluxo nativo, sem montar totais. Quando uma intenção foi omitida pelo early return/debounce, resgatá-la uma vez e sincronizar nativamente ao fim do ciclo; não emitir GET de confirmação após cada clique. No máximo uma releitura auxiliar por ciclo de resgate, além das sincronizações nativas necessárias e das leituras de diagnóstico do harness.
8. **Pendências e erros:** encerrar pendências descartadas sem permitir que o hook de quantidade restaure o campo antigo; preservar erros da intenção vigente. Estoque/limite inválido usa resposta oficial, corrige a linha e explica o motivo. Falha de rede mantém o desejo visível como não confirmado e oferece nova tentativa explícita; não fazer retry infinito. Retry de nonce não pode ressuscitar revisão antiga.

Exemplos de contrato: cinco cliques com intervalos de 150 ms, antes do primeiro envio, geram uma mutação final. Uma escrita em voo seguida por quatro desejos da mesma linha gera no máximo duas mutações físicas no ciclo. Cliques separados por tempo suficiente para concluir cada operação são alterações distintas e não têm como ser cancelados retroativamente.

### 16.4 UI e UX: resposta imediata e confirmação segura

| Região | Durante a edição/requisição | Após confirmação |
|---|---|---|
| Quantidade | Desejo atual aparece imediatamente; input, stepper e foco permanecem operáveis dentro dos limites nativos | Quantidade aceita pelo servidor; erro de limite junto da linha |
| Preço unitário | Mantém o preço confirmado legível; não desaparece por uma consulta de frete | Atualiza se o servidor mudou o preço |
| Valor da linha alterada | Exibe estimativa identificada, ou estado de atualização quando não há base monetária válida | Total oficial da linha, sem frete |
| Linhas não alteradas | Continuam legíveis; ficam pendentes somente se a operação afeta seu desconto/preço | Valores oficiais da resposta integral |
| Subtotal de produtos | Estimativa identificada enquanto há quantidades não confirmadas; não inclui entrega | Subtotal oficial, com descontos discriminados no resumo |
| Entrega | Estado localizado de cálculo; último valor não aparece como confirmado para o pacote/CEP novo | Método e preço oficiais vigentes |
| Total do pedido | Estado localizado de atualização até concluir produtos, descontos, taxas, impostos e entrega | Total oficial do carrinho |

**Regra de estimativa:** usar somente o preço unitário fornecido pela última resposta oficial e a quantidade desejada, com moeda/precisão da API. Valor é previsão visual antes dos descontos do carrinho; não é inserido no store, enviado ao servidor ou usado em pagamento. Cálculo em unidades monetárias inteiras, sem soma em float; formatação nativa. Se preço/moeda/quantidade não forem válidos, mostrar atualização em vez de fabricar um valor. Quantidade pode mudar preço/desconto no servidor, portanto o rótulo de estimativa permanece até a resposta atual. Não misturar a estimativa com o total confirmado nem prometer preço fixo por volume.

Exemplo de aceite visual: ao editar para 5 unidades com preço confirmado de R$28, a linha pode mostrar **R$140 estimados**, imediatamente. Frete e total geral continuam em atualização. Ao receber o carrinho vigente, retirar o rótulo e exibir o valor oficial, incluindo uma diferença de desconto/preço se houver.

Remover o seletor global que oculta todos os valores em `.petshop-cart-updating`; pendência deve pertencer à linha ou região afetada. Reservar o espaço dos valores/indicadores para evitar saltos. Indicadores são inline, sem overlay geral ou modal; manter cores/tipografia da loja, contraste e foco. Respeitar `prefers-reduced-motion`, teclado, leitura por leitor de tela e alvos de toque de 44 px para controles alterados.

O CTA de finalização informa atualização e não permite avançar usando dados pendentes; usar integração pública com pendências/validação, preservando foco e label acessível. Não bloquear a edição de quantidade/CEP para conseguir essa proteção. Após erro, não apresentar valor estimado como pedido confirmado.

### 16.5 Medição e otimização do cálculo

Antes de implementar, capturar uma baseline nova do relato: cinco cliques rápidos e cinco durante uma escrita lenta, produto normal e pacote pesado, mesmo cliente/CEP/método. Registrar quantidade desejada, revisão, envio/fim HTTP, início/fim do cálculo e número/duração das chamadas externas. Separar atraso de debounce, fila, PHP/transportadoras e renderização. Não registrar endereço, cookie, token ou dados pessoais.

Inspecionar os hooks que invalidam cache em mudança de quantidade e a eventual duplicação de cotação entre update-item, destino e releitura. Evitar cálculos intermediários pela coalescência; preservar cache somente quando pacote, destino, método e regras continuarem equivalentes. Mudança real de quantidade/peso/dimensões/valor declarado deve invalidar a cotação. Não ampliar indiscriminadamente a guarda de seleção de parceiro do §14 nem reutilizar frete de outra quantidade.

Entregar relatório antes/depois com os mesmos cenários. Budgets próprios em ambiente controlado: desejo/estimativa refletidos em até100ms; uma mutação final no burst de cinco cliques150ms; até duas mutações quando já havia uma ativa; aplicação visual em até200ms após a resposta final estar disponível, sem esperas artificiais. Mostrar loading após300ms. Tempo externo é medido separadamente, sem prometer SLA de transportadora ou relaxar correção financeira para ganhar velocidade.

### 16.6 Sequência de implementação e responsabilidades

1. **Baseline e contratos:** acrescentar medições/asserts aos gates existentes; registrar o sintoma de demora antes da alteração. Não repetir toda a matriz a cada iteração.
2. **Fila:** alterar `assets/src/shared/cart-request-coordinator.js`, consolidando pendências por chave/revisão e ciclos de resgate; ajustar `cart-operations.js` somente nos períodos próprios comprovadamente redundantes. Manter APIs públicas, erros atuais e uma escrita física.
3. **Apresentação:** estados localizados no `cart-shipping-quote-block.js` e integração pública das linhas em `CartBlocksIntegration.php`; CSS localizado no child theme. Nenhuma mutação manual de texto de preço nativo ou DOM de campos React para simular cálculo confirmado.
4. **Servidor:** corrigir somente invalidações/cálculos redundantes demonstrados pela baseline, no plugin próprio e por hooks públicos. Confirmar equivalência de cache e limites financeiros com testes; não editar vendor.
5. **Aceite:** unit, browser e gates alterados; revisão técnica dedicada do delta conforme implement-plan; entregar assets no runtime e conferir hashes. Registrar resultados/limitações no ledger e STATUS, mantendo os critérios financeiros restantes abertos se falharem.

### 16.7 Critérios de aceite obrigatórios

- [ ] Baseline anterior à melhoria e relatório depois usam os mesmos produtos, quantidades, endereço/CEP, método e condição de login; identificam custo próprio versus externo.
- [x] Cinco cliques150ms geram uma mutação de quantidade; cinco desejos durante uma operação lenta geram no máximo a ativa e a final. Assert de contagem inspeciona endpoints diretos e batch; não confunde descarte de resposta com cancelamento de processamento.
- [x] Máximo uma operação física de carrinho em voo; nenhum trabalho de quantidade substituído ainda na fila é enviado; erros/nonce de revisão antiga não são aplicados.
- [ ] Duas linhas independentes, remoção durante edição, cupom, CEP e troca de parceiro não perdem alterações nem reenviam histórico; cleanup não mantém mapa de itens removidos.
- [x] Sequências 1→3→1 e 1→3→1→3 confirmam último desejo; monitoramento durante toda a transição não encontra reversão transitória do campo.
- [ ] Quantidade e estimativa respondem dentro dos budgets controlados; preço unitário e linhas não afetadas continuam legíveis. Frete e total geral indicam sua própria pendência, sem flashes de valor confirmado antigo e sem mudança do layout aprovado.
- [ ] Estimativa é identificada visualmente e para tecnologia assistiva, respeita moeda/precisão e nunca altera o store financeiro; confirmação coincide com Store API independente, incluindo preço por quantidade, descontos, impostos e frete zero.
- [ ] Erros de estoque/limite, rede/503, retry explícito, nonce expirado e ausência de método têm recuperação sem loop, congelamento de input ou confirmação falsa.
- [x] CTA não avança com alterações não confirmadas; teclado, foco, status polite, mobile390/desktop1440 e reduced-motion verificados.
- [ ] Gates de concorrência/entrega atualizados passam em visitante/autenticado; regressões de checkout, minicarrinho/PDP, persistência Gutenberg e comércio nativo preservadas. A matriz pesada do §10 continua sem dispensa nem aumento de timeout para fabricar aprovação.
- [ ] Asset entregue corresponde à fonte; diagnósticos e fixtures removidos, URLs restauradas, gates alterados/revisão registrados. Nenhum commit/push/PR/publicação nesta etapa de planejamento.

### 16.8 Inventário de conteúdo e persistência

| Rota/elemento | Origem e edição | Regra desta entrega |
|---|---|---|
| Carrinho: nomes, fotos, alt, atributos e preços de produto | Produto WooCommerce / Biblioteca de mídia | Manter edição pelo painel; testar produto simples e variável |
| Carrinho: estrutura, conteúdo editorial e bloco de CEP | Gutenberg da página Carrinho | Preservar remoção, reordenação e textos salvos; não reprovisionar layout |
| Carrinho: quantidade, subtotal, entrega, total e CTA nativos | WooCommerce / tradução e extensibilidade oficiais | Não fixar cópias próprias de labels nativas |
| Carrinho: estimativa, atualização, erro e retry próprios | Tradução `petshop-core`, strings funcionais | Novas mensagens estritamente funcionais, sem conteúdo comercial fixo |
| PDP, minicarrinho e checkout | Origens administrativas já inventariadas no plano | Regressão, sem novos textos/imagens comerciais |

Nenhuma imagem nova. Validar alteração de nome/foto/alt e edição/reordenação do conteúdo no painel antes/depois de atualização dos assets/reprovisionamento; alterações do cliente devem persistir. Critérios permanecem desmarcados até evidência nova.

### 16.9 Ledger de implementação — 09/10/2026

Implementados: consolidação por chave/revisão, proteção de retry de nonce após cleanup, pendências por região, estimativas inteiras identificadas, total oficial por linha após desconto, loading após 300 ms, status de conclusão, validação pública do CTA, erros/retry e releitura oficial de estoque. Assets próprios recebem versão por mtime; tema 0.6.34. Editor administrativo não ativa coordenação/feedback. Nenhuma edição de fornecedor.

| Etapa | Evidência / execução | Resultado e correção |
|---|---|---|
| Baseline §16 | `041-cart-performance/baseline.json`, visitante1440, produto1486, CEP01310100, quantidades6 e2→7 | Uma escrita no burst e ativa+final já presentes; preço unitário oculto, estimativa ausente. Os 8213 ms incluem GET diagnóstico; não comparar diretamente com latência visual final. Baseline pesada/autenticada não capturada, critério completo aberto. |
| Revisões dedicadas | Duas execuções `review_041` | P1: retry de nonce ressuscitava intenção após cleanup; erro real400 sem cart não reconciliava o desejo. Corrigidos no middleware; revisão local do delta e testes específicos subsequentes. |
| Unit | Coordinator, estimate e runner-cleanup | Aprovados após correções; cobertura de Response rejeitado/retornado, erro interno de batch HTTP200, nonce antigo, outra linha, nova edição durante releitura, moeda inteira/zero/overflow e limpeza dos runners. Primeira unit após guarda de erro foi reprovada por ocultar erro atual de outra linha; guarda limitada a batch de múltiplas operações, rerun aprovado. |
| Browser inicial §16 | Quatro cenários visitante/autenticado1440/390 às15:02 | Aprovado antes de ampliar fixtures/erros: estimativas22–65ms, pintura112–131ms, uma escrita burst, ativa+final em operação lenta, máximo1. Checkpoint histórico, não aprovação da versão final ampliada. |
| Browser ampliado | Primeira execução às15:10 | Harness parou na rejeição esperada da ação nativa; correção do teste para afirmar `invalid_quantity` e continuar a recuperação. |
| Recuperação de estoque | Execuções às15:15 e15:20 | Reprovadas: desejo9 mantinha draft após rejeição. Observação dos erros internos de batch adicionada; segunda execução demonstrou também Response rejeitado bruto na chamada direta. Correção distinta: ler clone no catch sem consumir o erro nativo. Unit de reprodução aprovada; releitura de estoque passou na execução seguinte. |

| Recuperação de rede / revisão visual | Execução seguinte e screenshot1440 às15:26 | Erro503 revelou classe de draft sobrescrita pelo render nativo. Efeito de classe passou a ser idempotente a cada commit, separado da validação pública. Primeira combinação completa1440 passou; rodada interrompida deliberadamente após inspeção visual encontrar CSS legado ocultando unitário sem promoção e spinner nativo acionado pelo clique bloqueado. Removido CSS, preservado badge de economia confirmado, bloqueada propagação do clique; teste passou a conferir dimensões reais de preço unitário e ausência de spinner após retry. Execução final em andamento; interrupção não contabilizada como aprovação/falha da matriz final. |

Evidência local fica em `.local/evidence/041-cart-performance/`; fixtures temporárias e URLs são restauradas no finally. A divergência financeira do pacote pesado e a matriz T01–T20 permanecem critérios do plano 041; nenhum resultado de responsividade substitui esses aceites.

### Checkpoint consolidado e relatório da branch — 09/10/2026

Rodada completa às15:38 aprovada nos quatro contextos após correções de estoque/rede. Ampliação seguinte de teclado/loading revelou efeito sem atualização por setState de targets sem mudança; removido esse setState, revisão visual desktop/mobile aprovada. Gate geral alterado aprovado em68 arquivos às16:13; concorrência final nos quatro contextos aprovada às16:23. Ensaios browser seguintes do runner foram pausados deliberadamente e não contam como aprovação nem falha funcional.

Rodada de desempenho ampliada reprovou retry503: focusout capturava valor restaurado pelo componente nativo e apagava o desejo. Correção distinta limita reconciliação de blur às entradas inválidas que exigem clamp, com conjunto removido junto da linha. Novo ensaio passou essa recuperação e parou somente no harness de sold-individually, que esperava input não editável onde o Woo não renderiza input. Assert corrigido para ausência do editor; rodada final ampliada aprovada em1440/390 visitante/autenticado. Inclui digitação sem blur, clique de finalização na mesma interação, foco, loading300ms, reduced-motion, clamp9→8, erro oficialinvalid_quantity/readonly_quantity,503/retry, cupom, duas linhas e remoção durante edição.

Métricas finais: máximo físico1; burst de cinco cliques150ms envia somente6; sequência durante operação ativa envia2 e7; estimativas14,7–58,5ms; pintura89,4–106,2ms após resposta. Confirmação completa do burst5,49–5,83s, incluindo atraso controlado900ms, interação, debounce e cálculo/cotação; não confundir tempo visual com SLA externo. Baseline visitante já tinha uma escrita no burst; não declarar redução medida5→1 nem baseline pesada/autenticada completa.

SHA-256 de sete arquivos próprios de produção confere com runtime. Sintaxe do delta e diff check aprovados; home/siteurllocalhost:8888 restaurados, opções de fixtures041 ausentes, credenciais temporárias e probe removidos. Relatório solicitado pelo usuário criado em [docs/relatorio-branch-041-problemas-correcoes-pendencias.md](../docs/relatorio-branch-041-problemas-correcoes-pendencias.md), com problemas iniciais, regressões, falhas de implementação/harness, evidências e próximos passos. Regressão browser conjunta após todo§16, critério financeiro pesado, impostos/preço por quantidade no browser, staging e pedido permanecem abertos. Nenhum commit/push/publicação.

## 17. Plano de continuação — confiabilidade, segurança e operação

**Registrado em:** 09/10/2026. **Status:** planejado; nenhuma aprovação de teste é concedida por esta seção.

### 17.1 Por quê e decisão de escopo

Concluir a validação integrada da compra e corrigir os defeitos encontrados, cobrindo sessão, endereço, totais, pagamento, proteção de dados e recuperação operacional. Os checkpoints anteriores são contexto, não prova de que a revisão final passou em todos os fluxos.

O usuário autorizou deixar o ponto 1 da análise fora por hora: investigar/corrigir a divergência de transportadora do pacote autenticado de 44 kg. Ela permanece registrada como **ADIADA-44KG**, com causa e tarifa correta ainda não demonstradas. Não investigar essa causa nesta continuação nem exigir sua resolução para avançar nas sessões abaixo. Não mascarar suas falhas, apagar seu teste, reduzir tolerância financeira ou classificá-la como corrigida.

O escopo restante é global para as superfícies de compra existentes: Home, loja, busca, categoria, PDP, minicarrinho, carrinho, checkout, confirmação, conta e endpoints próprios associados. Produtos personalizados entram na regressão do fluxo já implementado; construir o editor e a fila ainda pendentes do Plano 012 está fora de escopo.

Todos os itens abaixo são obrigatórios. Permanecem fora: redesenho visual, novos meios de pagamento/transportadoras, funcionalidades comerciais dos planos 028/040, substituição de fornecedores, alterações no Core/vendor, cobrança real, commit/push/PR/merge/publicação sem solicitação própria. A validação do retorno existente do Mercado Pago entra no escopo, sem presumir que o status documental do Plano 029 prova ausência de implementação.

**Regra de conclusão:** pode-se concluir a execução deste §17 com ADIADA-44KG explicitamente registrada; isso não conclui automaticamente o plano 041 integral nem libera publicação. O status integral continua Em andamento enquanto o requisito original adiado não for resolvido ou seu escopo formalmente revisto. Uma falha em outro pacote é um novo defeito, não herda a exclusão dos 44 kg.

### 17.2 Pré-requisitos, baseline e evidências

- [ ] Registrar branch/HEAD, alterações existentes, versões reais de WordPress/WooCommerce/PHP/plugins e hashes dos assets fonte/runtime. Reutilizar a branch 041; preservar alterações alheias.
- [ ] Ler regras, este plano, relatório consolidado e operação 041. Conferir quais gates existentes cobrem cada cenário antes de ampliar testes.
- [ ] Confirmar Docker e serviços saudáveis; usar a stack canônica. Executar uma baseline focada de coordenador, estimativa, operações e smoke de compra local, sem repetir toda a suíte nesta fase.
- [ ] Inventariar staging: URL HTTPS, versões, gateway sandbox, credenciais de transportadoras, SMTP de teste, configuração de cache/proxy e acesso a logs/backup. Registrar o que estiver indisponível; prosseguir nas etapas locais independentes. Credenciais não entram no plano, Git ou evidências.
- [ ] Criar fixtures isoladas: visitante, dois clientes A/B, gestor e usuário sem privilégio; simples, promocional, variável, virtual, estoque limitado, vendido individualmente, duas linhas do mesmo produto com metadados distintos, cupons, preço por quantidade e dois pacotes. Usar mecanismo real instalado para desconto por quantidade e fixture isolada apenas no ensaio determinístico.
- [ ] Cada fixture registra IDs criados e valores anteriores; cleanup em finally tenta todas as restaurações, preserva o erro original e confirma remoção. Não usar contas, pedidos ou produtos reais de compradores.

Evidências em `.local/evidence/041-continuacao/`, por sessão e execução; resumo sanitizado versionado em `docs/operacao-frete-plano-041.md` e ledger §17.12. Cada execução registra cenário, revisão/hashes, versões, contexto, comando, resultado esperado/obtido e artefatos. Estados: aprovado, reprovado, bloqueado por pré-requisito, não executado ou ADIADA-44KG. Falha externa não vira aprovação.

**Gate:** ambiente e fixtures reproduzíveis, baseline registrada e dependências externas discriminadas. Não expor cookies, chaves de pedido, documentos, tokens ou endereços pessoais nas evidências compartilháveis.

### 17.3 Sessão A — falhas integradas e entrega (T03–T05/T16)

- [ ] Ampliar os gates browser de carrinho/entrega com nonce expirado, 429, 503, timeout e interrupção de conexão. Cobrir POST direto e erro interno em batch HTTP 200.
- [ ] Distinguir falha antes de o servidor aceitar a operação de resposta perdida após a gravação. Recuperar por leitura oficial; não repetir automaticamente adição, remoção ou compra de resultado desconhecido.
- [ ] Testar quantidade falhando seguida de CEP novo; erro antigo chegando após intenção nova; duas linhas em que uma falha e outra confirma; remoção de linha ainda pendente.
- [ ] Testar CEP com cobertura → sem cobertura, desaparecimento do método selecionado, mudança de seleção durante cálculo e retorno de cobertura. Separar ausência válida de taxa de falha técnica.
- [ ] Confirmar mensagem/retry utilizáveis, intenção vigente preservada, total antigo não anunciado como confirmado e CTA bloqueado enquanto houver alteração própria não confirmada. Recuperação deve liberar o CTA sem spinner permanente.

**Gate:** todos os negativos terminam em confirmação oficial coerente ou erro explícito recuperável; nenhuma operação recebe sucesso fictício, nenhuma taxa inválida é mantida e nenhum erro de uma linha é apagado pela outra.

### 17.4 Sessão B — endereço, hidratação e sessão (T06–T08/T10/T15)

- [ ] Cobrir visitante/autenticado, endereço completo salvo, endereço incompleto, cobrança=entrega, cobrança independente, destino de frete por cobrança e configuração que exige endereço completo.
- [ ] Trocar CEP entre UFs; atrasar a resposta antiga do ViaCEP; editar manualmente rua, número, bairro e complemento enquanto a consulta está em voo; salvar/recarregar conta e checkout.
- [ ] Confirmar consulta ViaCEP nos campos de endereço, incluindo CEP hidratado, sem lookup na calculadora de frete. CEP inválido/serviço indisponível permite preenchimento manual; não fabrica cidade/endereço.
- [ ] Confirmar que campos exigidos seguem validação do checkout; complemento aceita vazio como dado válido. Não confundir campo de valor vazio permitido com entrega facultativa.
- [ ] Testar preferência PDP expirada, logout, troca entre contas A/B, storage bloqueado e entrada direta no checkout; edição atual prevalece e nenhum dado de A aparece para B.
- [ ] Testar duas abas da mesma sessão: modificar item/CEP/entrega na aba A, retornar à aba B com checkout aberto e finalizar apenas após reconciliação. Registrar comportamento nativo, corrigir perdas comprovadas sem prometer mutex entre dispositivos.
- [ ] Testar preview concorrente com edição real e reload: itens, endereço, seleção, total e caches do carrinho permanecem coerentes; preview não restaura snapshot antigo da sessão.

**Gate:** formulário, sessão recarregada e dados preparados para o pedido têm a mesma origem vigente; nenhuma hidratação tardia apaga edição, mistura endereços ou cruza contas.

### 17.5 Sessão C — superfícies e valores (T01/T02/T09/T11–T15)

- [ ] Percorrer Home, loja, busca e categoria: adicionar simples/variável, conferir minicarrinho e navegar para carrinho/checkout; abrir minicarrinho sobre o carrinho e alterar/remover itens sem listeners cruzados.
- [ ] Cobrir PDP promocional, variação ausente/inválida/trocada em voo, quantidade 1/2/3, virtual sem entrega, estoque e vendido individualmente. Linhas personalizadas distintas preservam suas chaves e dados.
- [ ] No navegador, testar cupom fixo/percentual, desconto por quantidade, impostos incl/excl, arredondamento, limiar de frete grátis, taxa zero, retirada e dois pacotes com seleção independente.
- [ ] Comparar totais oficiais por linha, descontos, impostos, frete por pacote e total com sessão recarregada. Usar valores em unidades monetárias inteiras; nenhuma tolerância nova para esconder divergência e nenhuma multiplicação linear como oráculo para preço não linear.
- [ ] Confirmar distinção entre estimativa antes dos descontos e valor confirmado, manutenção do preço unitário e ausência de total antigo apresentado como definitivo durante atualização.

**Cobertura mínima:** 1440/390 px, visitante/autenticado nos fluxos de quantidade, CEP, falha/recuperação e navegação; ambos os perfis nos casos financeiros, desktop e ao menos um percurso financeiro completo mobile por perfil. Testes PHP complementam, mas não substituem esses ensaios browser. ADIADA-44KG é a única exclusão financeira desta continuação.

**Gate:** todas as superfícies convergem para o servidor, limites de compra são respeitados e os cenários financeiros incluídos coincidem após reload.

### 17.6 Sessão D — segurança de aplicação e dados

- [ ] Inventariar rotas REST/AJAX, uploads/downloads, retorno de pagamento, cadastro e alterações de conta. Registrar para cada uma autenticação esperada, autorização, nonce, entrada/saída e dado protegido; não classificar endpoint público legítimo como falha por ser público.
- [ ] Testar cliente A, cliente B, visitante e gestor: leitura/alteração de pedido/endereço, preview e arquivo original/produção, tokens de sessão/pedido inválidos ou de outro proprietário. Exigir recusa sem conteúdo privado para acessos indevidos.
- [ ] Revisar/testar validação e escape nos parâmetros dessas rotas; rejeitar estruturas/tipos inválidos de forma controlada. Cobrir manipulação de IDs, variação de outro produto e quantidade inválida.
- [ ] Testar limites de bytes/dimensões, MIME/conteúdo inválido e acesso HTTP direto ao armazenamento privado de uploads. Confirmar ausência de execução e preservação da autorização após logout/expiração.
- [ ] Verificar proteção contra abuso em checkout, cadastro, ViaCEP, cotação e upload. Medir primeiro em staging isolado; documentar camada, chave de limitação, janela, limite e comportamento 429. Definir limites com base na carga legítima medida, validar burst normal e abuso controlado e implementar proteção onde não houver cobertura. Nonce não é limitador de frequência.
- [ ] Verificar cache de página/CDN/proxy com clientes A/B: carrinho, checkout, conta, retornos e arquivos privados não podem compartilhar resposta personalizada. Confirmar configuração real de HTTPS e proxy antes de testar identificação por IP.
- [ ] Inventariar versões instaladas e dependências de produção, consultar avisos oficiais/fontes de vulnerabilidade e registrar aplicabilidade. Corrigir ou mitigar achados críticos/altos aplicáveis com regressão; não atualizar tudo indiscriminadamente nem confundir dependência apenas de teste com código publicado.
- [ ] Conferir menor privilégio administrativo, exposição de debug, segredos no diff/pacote e redação de logs/artefatos. Toda correção precisa de teste negativo que reproduza o acesso/entrada indevido.

**Gate:** nenhum achado crítico/alto aplicável aberto no escopo auditado; acessos cruzados negados, arquivos protegidos e limites compatíveis com compras legítimas. Registrar limites da auditoria; não declarar certificação de segurança.

### 17.7 Sessão E — pedido, pagamento, estoque e comunicação (T19/T20)

**Pré-requisito:** staging isolado, métodos reais configurados e gateway em sandbox; sem cobrança real. Ausência de acesso bloqueia esta sessão, não as anteriores.

- [ ] Validar transportadoras reais com pacotes normais, origem/destino conhecidos e credencial válida/erro controlado. Conferir prazo na origem e sua apresentação; não inventar prazo nem fixar tarifa comercial como constante de teste.
- [ ] Criar pedidos de teste visitante/autenticado, com endereços iguais/distintos, registrando o último total confirmado antes da submissão e o pedido recarregado via API WooCommerce compatível com HPOS.
- [ ] Conferir itens/variações/personalização, quantidade, desconto, imposto, pacote/método, frete, total, documento e endereço persistidos. Endereço incompleto bloqueia o pedido.
- [ ] Exercitar Pix/cartão sandbox aprovado, pendente e recusado; retorno interrompido, refresh/voltar e duplo clique. Confirmar orientação correta e ausência de cobrança/pedido efetivo duplicado pelo mesmo envio.
- [ ] Reentregar notificação legítima do gateway e variar ordem/atraso de notificações usando os recursos suportados do sandbox. Notificação inválida não altera pedido; duplicata não duplica baixa de estoque/e-mail de mudança de status. Retorno de navegador, sozinho, não confirma pagamento.
- [ ] Testar duas compras concorrentes da última unidade e cancelamento/expiração: reserva, baixa e liberação seguem configuração nativa, sem venda além do permitido ou reposição duplicada.
- [ ] Conferir confirmação, Minha conta e acesso do visitante, retorno do Mercado Pago, e-mails efetivamente recebidos em caixa de teste e processamento das tarefas agendadas. Conteúdo reflete status e totais do pedido, com links autorizados.

**Gate:** ciclo checkout → pedido → pagamento → estoque → e-mail → consulta coerente, sem duplicação ou vazamento. Registrar IDs sintéticos e cleanup; não usar smoke antigo de personalização como substituto.

### 17.8 Sessão F — acessibilidade, conteúdo e desempenho

- [ ] Executar percurso completo por teclado em 1440/390, árvore acessível, leitor NVDA e VoiceOver: variações, quantidade, CEP, entrega, erro/retry e checkout. Registrar ambiente/operador; automação de árvore não comprova teste manual de leitor.
- [ ] Confirmar labels, foco após erro, anúncios de atualização/conclusão, reduced-motion e bloqueios de CTA compreensíveis. Corrigir bloqueios de compra encontrados.
- [ ] Validar o inventário editorial abaixo com edição, salvar/reload e atualização/reprovisionamento: texto, imagem/alt e reordenação/remoção persistem sem duplicação do bloco.
- [ ] Medir em revisão final resposta visual, requisições, espera nativa, cálculo PHP e tempo externo separadamente, em pacote normal, visitante/autenticado. Fazer 10 repetições por contexto desktop/mobile e registrar mediana/p95, ambiente e erros; não comparar contra baseline incompatível.
- [ ] Preservar os contratos já medidos: máximo de uma operação física coordenada de carrinho, só intenção final ainda na fila, estimativa até 100 ms, pintura até 200 ms após resposta e aviso de pendência a partir de 300 ms nos cenários controlados. Regressão precisa ser explicada/corrigida, não ocultada por timeout maior.
- [ ] Conferir deadline e recuperação de cotação sem inventar SLA externo; cache só pode ser otimizado após prova de equivalência de pacote/destino/regras. A baseline histórica pesada permanece não capturada, sem fabricação retrospectiva.

| Superfície | Textos e imagens próprios | Origem de edição / persistência |
|---|---|---|
| Home e páginas comerciais | Títulos, campanhas, imagens/alt, links e vitrines | Gutenberg e Biblioteca de mídia; preservar composição salva |
| Loja/busca/categoria/PDP/minicarrinho | Nome, foto/alt, atributos e conteúdo de produto | Produtos/categorias WooCommerce e mídia; preços/estoque são dados dinâmicos |
| Carrinho | Conteúdo de página, posição/presença do CEP | Gutenberg; remoção/reordenação não são desfeitas pela migração |
| Checkout/conta/confirmação | Conteúdo de página e mensagem comercial existente | Gutenberg e configuração global já existente; dados pessoais via formulários autorizados |
| Header/rodapé/e-mails | Logo, atendimento, textos globais e mensagens comerciais | Configuração administrativa existente; traduções/extensibilidade oficial para textos da plataforma |
| Erros, retry e estados novos | Mensagens estritamente funcionais | Tradução `petshop-core`; nenhuma nova imagem/copy comercial fixa |

**Gate:** compra utilizável sem mouse, conteúdo editável e persistente, responsividade preservada e métricas externas separadas das internas.

### 17.9 Sessão G — CI e manutenção

- [ ] Tornar os testes browser determinísticos críticos automáticos em PR: quantidade/CEP, falha/retry, seleção, endereço e convergência financeira com taxas isoladas. Manter staging/transportadora/pagamento como gates de integração separados, sem depender de credenciais em PR de forks.
- [ ] Preservar gates PHP e PHPUnit; executar o workflow em branch/PR de teste quando houver autorização de publicação da branch. Validar localmente os comandos antes disso.
- [ ] Separar seleção de ensaios para ADIADA-44KG sem apagar/desabilitar seu caso original. O relatório da suíte deve expor teste adiado e resultado integral; a suíte completa que falhar nesse caso não recebe rótulo de aprovada.
- [ ] Publicar evidências sanitizadas de falha no CI com retenção definida; testar redação/cleanup antes de habilitar upload de novos artefatos.
- [ ] Documentar nomes dos checks a exigir para integração e verificar proteção de branch no remoto. Aplicar exigência quando houver acesso/autorização de administração; enquanto isso, registrar pendência operacional, sem alegar bloqueio automático já ativo.
- [ ] Documentar matriz obrigatória após atualização de WooCommerce e transportadoras: temporização de endereço, campos adicionais, seleção/cache e retorno de pagamento. Retestar contratos vinculados à versão 10.9.4, sem editar fornecedor.

**Gate:** comandos reproduzíveis e workflow automático configurado; execução remota e proteção têm evidência própria ou pendência explícita. Nenhum teste com fornecedor simulado é apresentado como integração real.

### 17.10 Sessão H — pacote, recuperação e fechamento

- [ ] Executar revisão do conjunto de mudanças: escopo, dados sensíveis, hooks tardios, patches globais, acessos privados e compatibilidade HPOS/Blocks. Corrigir achados e repetir apenas gates afetados antes do fechamento.
- [ ] Rebuild/recreate final conforme bootstrap; conferir assets e dependências do runtime contra fonte e repetir validação completa PHP/Unit/browser no mesmo estado final. Registrar falha de gate fora do escopo como falha, sem desabilitá-lo para obter verde.
- [ ] Validar instalação real em subdiretório: URLs REST/AJAX/retorno, assets, cookies, nonce e fluxo de compra sintético; teste de chave de storage isolado não encerra este item.
- [ ] Preparar pacote HostGator/cPanel com a skill `preparar-deploy`, sem publicar. Verificar autoload de produção, dependências, ausência de segredos/fixtures e instalação limpa do pacote em staging.
- [ ] Restaurar backup em destino isolado, incluindo banco, uploads, configurações e arquivos privados. Medir tempo de recuperação e ponto restaurado; confirmar leitura de pedidos e compra de teste depois da restauração.
- [ ] Escrever e ensaiar rollback: código/assets, migrações e configuração. Não restaurar banco antigo sobre pedidos novos; definir preservação/reconciliação dos pedidos posteriores e demonstrar com pedidos sintéticos em staging.
- [ ] Configurar/verificar alertas de indisponibilidade, falhas de checkout/cotação, webhook e tarefas agendadas; simular falha controlada, comprovar alerta e registrar responsável/canal operacional sem enviar mensagens a terceiros nesta execução sem autorização.
- [ ] Atualizar relatório, operação e STATUS com resultados por cenário, pendências reais e ADIADA-44KG. Gerar parecer de prontidão discriminando execução local, staging e operação; não declarar loja sem bugs.

**Gate:** pacote e recuperação demonstrados, regressão final documentada e decisão de publicação separada. Não publicar automaticamente ao terminar esta sessão.

### 17.11 Ordem de execução e critérios de aceite da continuação

Ordem: baseline → A → B → C → D → E → F → G → H. Ausência de staging permite continuar F/G e revisão local de H; testes dependentes continuam bloqueados. Corrigir defeito junto ao teste que o reproduz; não acumular correções sem revalidar o fluxo afetado.

- [ ] A/B/C aprovadas com a cobertura definida e sem divergências financeiras novas nos cenários incluídos.
- [ ] D aprovada, com controles testados e nenhum achado crítico/alto aplicável aberto.
- [ ] E aprovada em staging, com pedido/pagamento/estoque/e-mail e acesso do cliente comprovados.
- [ ] F aprovada, incluindo leitores de tela, persistência editorial e métricas reproduzíveis.
- [ ] G implementada e verificada; execução remota e exigência dos checks não confundidas com configuração local de YAML.
- [ ] H aprovada com revisão final, pacote instalado, backup restaurado, rollback ensaiado e monitoramento demonstrado.
- [ ] Cada item não executado tem motivo/pré-requisito explícito e permanece aberto; ADIADA-44KG é o único adiamento funcional aceito por esta solicitação.
- [ ] Relatório final distingue conclusão do §17, conclusão integral do 041 e autorização de publicação. Nenhum desses estados é inferido do outro.

### 17.12 Ledger a preencher na execução

| Sessão | Estado inicial | Evidência exigida |
|---|---|---|
| Baseline | Não executado nesta continuação | HEAD/hashes, versões, saúde, fixtures e gates focados |
| A — falhas/entrega | Fatia de resposta perdida no §19; nonce, duas linhas e timeout ainda não fecham a sessão | Negativos integrados, rede/store/DOM e recuperação |
| B — endereço/sessão | Não executado | Matriz de endereços, contas, duas abas e reload |
| C — superfícies/valores | Não executado | Navegação global e comparação financeira incluída |
| D — segurança | Revisão estática no §18; testes negativos da sessão não executados | Inventário, testes negativos e achados/resoluções |
| E — pedido/pagamento | Não executado; depende de staging | Pedidos sandbox, notificações, estoque e caixa de e-mail |
| F — experiência/conteúdo | Não executado | Teclado/leitores, edição persistente e métricas |
| G — CI/manutenção | Não executado | Workflow, execução/checks e política de atualização |
| H — fechamento/operação | Não executado | Revisão, suíte final, pacote, restauração e alertas |
| ADIADA-44KG | Adiado por solicitação do usuário | Diagnóstico anterior preservado; sem aprovação implícita |

Este registro planeja o trabalho; não altera os resultados históricos nem cria um segundo plano/ticket para a continuação do 041. Vínculo ClickUp continua no estado já registrado; nenhuma criação/alteração externa foi realizada ao escrever esta seção.

## 18. Security review da branch — 09/10/2026

**Registrado em:** 09/10/2026. **Branch:** `codex/041-integridade-frete-carrinho-checkout`. **HEAD:** `1a5bede`, com alterações locais não commitadas. **Método:** revisão estática do diff da branch em duas passagens. **Resultado:** nenhum achado crítico, alto ou médio com caminho de ataque realista no escopo revisado.

Esta seção não aprova a Sessão D, não certifica a loja e não fecha o plano. Os testes negativos, o inventário de rotas com contas A/B, a medição de limite de frequência, o cache compartilhado e a consulta de avisos de dependências continuam não executados.

### 18.1 O que foi revisado

| Passagem | Escopo | Resultado |
|---|---|---|
| Infraestrutura de teste e documentação | Planos, docs, runners de gate, helpers de browser e fixtures 041 que entraram no primeiro recorte (19 arquivos) | Nenhum achado médio ou acima |
| Produção PHP/JS | Plugin, tema e módulos de carrinho/endereço/cotação listados abaixo | Nenhum achado médio ou acima |

Arquivos de produção revisados: `Plugin.php`, `AddressLookup.php`, `BrazilianPostcode.php`, `CartBlocksIntegration.php`, `CartQuantityStability.php`, `CartShippingQuoteBlocksIntegration.php`, `CheckoutCustomerData.php`, `ProductDetails.php`, `ShippingQuoteCartExtension.php`, `ShippingQuoteDestination.php`, `ShippingQuotes.php`, `ShippingRateSelection.php`, `class-storefront-product-card.php`, `address-lookup.js`, `cart-shipping-quote-block.js`, `product-card.js`, `product-experience.js`, `cart-estimate.js`, `cart-feedback.js`, `cart-operations.js`, `cart-request-coordinator.js`, `quote-preference.js`, `editor.js` do bloco de cotação, `checkout-address-layout.js` e `functions.php` do tema.

Controles conferidos no código:

- ViaCEP AJAX exige nonce e CEP de 8 dígitos; a URL de saída é o host fixo da ViaCEP (`AddressLookup.php`).
- A extensão da Store API só aceita `set_quote_destination` com CEP brasileiro válido e grava o destino na sessão corrente do WooCommerce (`ShippingQuoteCartExtension.php`).
- A hidratação do checkout marca a sessão com o usuário, limpa dados da ponte no logout e quando o usuário marcado difere do atual (`CheckoutCustomerData.php`).
- Estimativas no cliente são só apresentação; não gravam total de checkout.
- A preferência de CEP da PDP fica em `sessionStorage`, separada por URL da loja e conta atual.

### 18.2 Abaixo do limiar — não são achados abertos da Sessão D

- Se um gate for interrompido antes do cleanup, uma conta sintética de editor pode permanecer no banco local até a próxima limpeza. A senha é aleatória e o arquivo fica em `.local/`, já ignorado pelo Git.
- `scripts/run-gates.mjs` ainda altera `home` e `siteurl` sem o guarda de loopback que `.sh` e `.ps1` já têm. É ferramenta de desenvolvimento, não superfície da loja.
- O AJAX da ViaCEP não tem limite de frequência próprio da aplicação. O nonce restringe a mesma origem; o abuso possível é custo operacional, não leitura cruzada de contas.
- Migrações de opção única rodam em `init` sem checagem de capability e gravam valores predeterminados.
- A divergência financeira já registrada entre a store e o GET independente permanece bug de consistência. Não há evidência de que o cliente force um total cobrado menor.

### 18.3 Fora desta revisão

- Scripts `validate-*` omitidos na primeira passagem, inclusive a maior parte dos gates `validate-041-*`, não tiveram passagem dedicada. São harness de teste.
- Não houve teste dinâmico de cliente A contra cliente B, token de outro pedido, cache/CDN, HTTPS/proxy, burst de cadastro/cotação/upload nem varredura de avisos das dependências instaladas.
- `cart-quantity-guard.js` e `cart-quantity-stability.js` não estão na árvore; a remoção segue o plano e reduz superfície de interceptação.

**Gate desta seção:** revisão estática registrada, sem achado crítico/alto/médio no escopo coberto. A Sessão D continua aberta até os testes do §17.6.

## 19. Resposta perdida e falha antes da gravação — 09/10/2026

**Estado:** fatia da Sessão A implementada e verificada. A sessão inteira continua aberta.

Quando a Store API responde com status HTTP, a rejeição continua explícita: 429 não é reenviado sozinho e o botão Tentar novamente repete a quantidade. Quando a conexão cai sem status (`fetch_error` / `offline_error`), o coordenador lê o carrinho oficial e não repete adição, remoção nem a quantidade. Se a leitura confirma o desejo, a falha some. Se o servidor não aplicou a quantidade, o desejo e o retry permanecem.

A seleção de frete recebe a geração do CEP. Se o CEP muda antes da seleção terminar, ela não é anunciada como sucesso nem como falha de seleção. Lista oficial vazia, com o CEP pedido, mostra ausência de cobertura. CEP devolvido diferente do pedido mostra falha técnica.

| Verificação | Resultado |
|---|---|
| `node scripts/validate-041-cart-request-coordinator.mjs` | Aprovado, inclusive `fetch_error` com uma escrita e um GET |
| `node scripts/validate-041-cart-operations.mjs` | Aprovado; seleção obsoleta não é enviada |
| `scripts/validate-041-cart-failures-browser.mjs` | Aprovado em 1440 e 390, visitante, produto 1486. 429 direto, resposta perdida com leitura, retry, CTA no carrinho, ausência de cobertura. Evidência em `.local/evidence/041-cart-failures/results.json` |

Neste browser o WooCommerce enviou `update-item` direto, não batch. O erro interno de batch HTTP 200 segue coberto no contrato do coordenador, não nesta passagem de browser. Nonce expirado, timeout nomeado, duas linhas com uma falha e remoção de linha pendente continuam abertos na Sessão A. URLs `home` e `siteurl` restauradas para `http://localhost:8888`. Sem commit.
