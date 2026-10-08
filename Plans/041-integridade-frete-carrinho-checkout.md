# Plano 041 — Frete e carrinho consistentes em toda a loja

**Status:** Pendente — planejamento detalhado; implementação não iniciada.
**Data:** 2026-10-08.
**Alcance:** global nos fluxos próprios de produto, cards/vitrines, minicarrinho, carrinho, checkout e formulários de endereço.
**Branch de implementação:** `041-integridade-frete-carrinho-checkout`, a partir de `master`, conforme convenção do projeto.
**ClickUp:** tarefa não criada nesta etapa. Título definido: `Plano 041 - Frete e carrinho consistentes em toda a loja`.
**Origem:** [auditoria de 08/10/2026](../docs/analise-frete-carrinho-2026-10-08.md), relato de produção e solicitação de plano fundamentado em documentação oficial.
**Dependências:** Planos [026](./026-checkout-dados-salvos-viacep.md), [027](./027-calculadora-frete-hub.md), [036](./036-dependencias-frete-checkout-versionadas.md), [037](./037-atualizacao-automatica-valores-carrinho.md), [038](./038-total-quantidade-pagina-produto.md) e [039](./039-quantidade-carrinho-apos-frete.md).
**Baseline examinada:** `bdf6632` com alterações locais preexistentes; WordPress 7.0.2 / WooCommerce 10.9.4 no Docker. Produção não foi inspecionada diretamente.

## Por quê

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
3. Aguardar pendências nativas antes de enviar a operação própria; observar `isCalculating()` e seletores públicos de cart. Não bloquear operações nativas por interceptor. Após 15 s de espera, interromper a espera própria e mostrar erro recuperável, sem declarar sucesso.
4. Usar `extensionCartUpdate({ namespace, data, overwriteDirtyCustomerData: false })`. O callback grava somente destino de cotação e sua origem na sessão do cliente WooCommerce, por APIs oficiais. Não altera quantidades, cupons, preços, dados da conta ou conteúdo personalizado.
5. Ao mudar o CEP, invalidar cidade, rua, bairro e complemento associados ao CEP anterior no destino de entrega estimado. Não copiar esses campos de billing para shipping em uma resposta posterior ao cálculo. Preservar identidade/contato e não sobrescrever rascunho de endereço que o usuário esteja editando.
6. O fluxo nativo calcula e aplica a resposta integral coerente. Não chamar `receiveCart`, combinar apenas frete/totais, reescrever batch nem pular uma resposta por ter zero taxas.
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
- O adaptador não substitui a API de rede. Os endpoints próprios recebem URLs geradas no PHP, inclusive `rest_url()` e URL AJAX; nada de `/wp-json` fixo. Scripts usam dependências/versionamento declarados.
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
