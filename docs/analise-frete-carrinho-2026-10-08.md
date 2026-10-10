# Análise de frete e carrinho — 08/10/2026

## Resultado

O problema ultrapassa a composição do destino de frete. Há concorrência entre atualizações de quantidade, endereço, seleção de entrega e respostas completas do carrinho. A proteção atual mantém algumas quantidades na tela, mas permite combinar itens, totais e endereço de momentos diferentes. Em certos cenários, também regrava quantidades antigas no servidor.

A correção local melhora o cálculo por CEP, porém precisa de ajustes antes de ser considerada uma solução completa: a restauração de quantidade no PHP acontece depois de calcular os totais e depois de copiar o carrinho para a sessão; e o JavaScript continua descartando operações legítimas e reenviando intenções antigas.

Esta entrega é uma análise e uma proposta técnica. Não modifica código de produção, planos, tickets, commits ou configuração persistente da loja.

## Base e limites da análise

- Repositório em `bdf6632`, incluindo o commit `2da5262` e as seis alterações locais existentes no início da revisão.
- Runtime local: WordPress **7.0.2**, WooCommerce **10.9.4**. Os hashes do `cart-quantity-stability.js` e do `ShippingQuoteDestination.php` do contêiner coincidiram com os arquivos locais.
- No ambiente local, `woocommerce_shipping_cost_requires_address=no`, `woocommerce_ship_to_destination=billing` e impostos exibidos sem inclusão. A configuração de endereço completo em produção e os resultados comerciais relatados pelo outro agente foram usados como contexto, sem acesso independente à produção.
- Revisão das regras do projeto e dos Planos 026, 027, 037, 038 e 039, dos fluxos próprios, das integrações relevantes e do código instalado do WooCommerce.
- Reprodução em Chromium no contêiner, sessão anônima própria; testes isolados do interceptor e do módulo ViaCEP; reprodução PHP com o WooCommerce instalado.
- Os cenários com respostas antigas, alteração concorrente externa e mutação durante o cálculo foram deliberadamente induzidos. Demonstram vulnerabilidades do código; não medem sua frequência em produção.
- Não houve pedido real, pagamento, edição de conta de cliente ou alteração de opções persistentes. As sessões anônimas de teste não reutilizaram o carrinho do usuário.

## Superfícies e fontes de estado

| Superfície | Caminho atual | Pontos relevantes |
|---|---|---|
| Cards/vitrines | `product-card.js` → Store API `add-item` → eventos de atualização | Outra entrada de mutação; notifica Blocks e também solicita fragments clássicos |
| Produto simples/variável | `product-experience.js` → AJAX `ProductDetails` → `ShippingQuotes` | Estimativa de frete com pacote próprio; persiste CEP na sessão; quantidade fixada em 1 |
| Minicarrinho | Bloco WooCommerce no header de `petshop-theme/functions.php` | O guard do Plano 039 não é carregado na Home; na página do carrinho divide a página com o interceptor global |
| Carrinho | Cart Block + `cart-quantity-guard.js` + `cart-quantity-stability.js` | Estado nativo, mapas de intenção, pintura do DOM, respostas reescritas e debounce coexistem |
| Checkout | Checkout Block + `address-lookup.js` + `CheckoutCustomerData` | ViaCEP dispara eventos nativos e um POST próprio; pode aplicar `receiveCart` integral |
| Minha conta/endereço | `address-lookup.js` + `AddressLookup.php` | Mesmo lookup assíncrono do checkout; formulário de endereço deve continuar usando ViaCEP |
| Servidor de frete | WooCommerce + métodos das zonas + Melhor Envio/Virtuaria + `ShippingQuoteDestination` | Destino de estimativa, validação dos Blocks, caches e sessão influenciam o resultado |
| Personalização | `Personalization/WooCommerce/CartIntegration.php` | Metadados tornam a chave do item essencial; não identificar linhas apenas pelo ID do produto ou posição |

## Achados prioritários

P1 indica risco alto de incoerência ou perda de uma operação de compra. P2 indica divergência funcional, fragilidade ou lacuna de cobertura a corrigir na sequência.

### F01 — P1 — Quantidade, total da linha e total geral pertencem a versões diferentes

**Confirmado em teste isolado e no navegador.** Em `cart-quantity-guard.js:34–63`, `merge()` ajusta quantidade e escala os totais da linha, mas preserva `cart.totals`, `items_count` e demais agregados. As ações de recebimento também são interceptadas em `cart-quantity-stability.js:360–408`, sem rejeitar a resposta antiga como um todo.

No navegador, um produto de R$ 49,90 passou de 1 para 3 unidades. A linha chegou a R$ 149,70 enquanto o estado ainda continha total geral de R$ 69,80, correspondente a uma unidade e R$ 19,90 do fallback local. Depois da confirmação, o total oficial era R$ 169,60. Entregar deliberadamente a resposta inicial fez `storeTotal` voltar para R$ 69,80 mantendo quantidade 3 e linha R$ 149,70.

**Relação com o sintoma relatado:** existe um mecanismo comprovado para o total regredir enquanto a quantidade permanece correta. Não foi reproduzida espontaneamente toda a sequência visual de produção; no ensaio, o bloco também ocultou o valor durante parte do carregamento. É preciso registrar DOM e rede em produção/staging para identificar qual resposta dispara o caso observado pelo cliente.

**Correção proposta:** guardar a quantidade desejada como estado de interação; aceitar um snapshot oficial coerente após confirmar sua atualidade; manter totais anteriores identificados como pendentes durante o cálculo. Não compor um carrinho “oficial” com itens novos e agregados antigos.

### F02 — P1 — Intenções já confirmadas são reenviadas e desfazem atualizações posteriores

**Confirmado no navegador e no servidor.** `intendedQty` nunca é limpo após confirmação. `live()` expira em 12 segundos, mas `liveIntended()` ignora essa expiração enquanto `flushing=true`. O flush percorre todas as entradas históricas em `cart-quantity-stability.js:202–209`.

Reprodução: item A chegou a 3; após mais de 12 segundos, uma atualização pela Store API o levou a 4; item B foi adicionado; ao aumentar B de 1 para 2 na tela, o flush também reenviou A=3. A leitura independente do servidor confirmou **A=3/B=2**, embora antes estivesse **A=4/B=1**.

**Correção proposta:** manter somente itens pendentes, indexados pela chave real do carrinho. Ao confirmar uma geração, removê-la apenas se ainda for a intenção vigente. Remover entradas de itens excluídos; nunca reenviar valores já confirmados. Mudanças por minicarrinho ou outra aba exigem nova leitura antes de reutilizar estado antigo.

### F03 — P1 — O interceptor descarta alteração de endereço e seleção de entrega

**Confirmado em testes isolados com o arquivo real.** `cart-quantity-guard.js:94–115` considera quantidade, cliente e método de entrega como operações retíveis. `resolveHeld()` devolve a resposta do flush de quantidade para todas, sem executar suas requisições originais.

Após o flush, `update-customer` pode receber uma resposta anterior durante a janela de silêncio, de 8 segundos após `resolveHeld()` ou 15 segundos após `beginFlush()`. O teste recebeu HTTP 200 com **zero requisições de rede**, tanto para a seleção de entrega retida quanto para a atualização de cliente suprimida.

**Correção proposta:** debounce somente de alterações sucessivas da quantidade do mesmo item. Endereço e escolha de entrega precisam executar e receber a própria resposta, em ordem compatível com as quantidades pendentes. Coalescer intenções equivalentes é válido; substituir uma operação diferente por sucesso antigo não é.

### F04 — P1 — Requisição em lote pode receber um corpo incompatível

**Confirmado em teste isolado.** O interceptor reconhece mutações dentro de `/wc/store/v1/batch`, mas resolve a requisição retida com um JSON comum de carrinho. O teste confirmou ausência de `responses` e presença de `items` na resposta de uma chamada em lote.

**Correção proposta:** retirar a substituição global de respostas. Usar as ações públicas dos Blocks e preservar o protocolo de cada operação. Uma fila própria precisa enfileirar a execução real, não fabricar uma resposta com o formato de outro endpoint.

### F05 — P1 — A restauração PHP deixa quantidade, totais e sessão divergentes

**Confirmado com o WooCommerce instalado, sob mutação induzida durante o cálculo.** A correção local registra `restoreCartQuantities` em `woocommerce_after_calculate_totals`, prioridade `PHP_INT_MAX`, e usa `set_quantity(..., false)` (`ShippingQuoteDestination.php:122–140`). O WooCommerce registra `WC_Cart_Session::set_session` no mesmo hook com prioridade **1000**.

Reprodução do caso que a proteção pretende corrigir: snapshot com 2 unidades; mutação para 1 durante o cálculo; resultado ao final:

```json
{
  "quantity": 2,
  "line_total": 49.9,
  "cart_total": "49.90",
  "session_quantity": 1
}
```

O hook também atua em qualquer `update-customer`, sem delimitar a cotação própria. O snapshot pertence apenas à requisição atual; ele não impede que outra requisição grave a sessão carregada anteriormente.

**Correção proposta:** identificar a origem da mutação e impedi-la antes de calcular. Se uma restauração continuar necessária, garantir novo cálculo consistente e persistência posterior, com proteção contra recursão. Não apenas antecipar a prioridade: restaurar depois de calcular ainda exige recalcular os valores afetados.

### F06 — P1 — ViaCEP antigo sobrescreve endereço do CEP novo

**Confirmado no Chromium com respostas simuladas e o módulo real.** `address-lookup.js:395–479` deduplica o CEP consultado, mas não invalida respostas antigas. Não verifica se o campo continua contendo o CEP original antes de preencher os campos ou aplicar a resposta do Store API.

Consulta SP lenta, seguida de RS rápida: resultado **CEP 91210320 + Avenida Paulista + São Paulo + SP**. O teste executou o formulário de Minha conta; o checkout compartilha esse código e ainda acrescenta o POST de sincronização e `receiveCart` integral.

**Correção proposta:** geração por grupo de endereço, descarte de respostas obsoletas antes de qualquer efeito, e cancelamento da consulta anterior quando possível. Checar novamente após cada `await`. Cancelar fetch não desfaz uma escrita que já chegou ao servidor; mutações do checkout também precisam de coordenação.

### F07 — P1 — Falha ao gravar quantidade pode ficar mascarada pelo estado otimista

**Confirmado por leitura do fluxo; falhas de rede/estoque não foram induzidas nesta auditoria.** Em `cart-quantity-stability.js:206–219`, respostas HTTP malsucedidas não geram reconciliação explícita; as promessas retidas são resolvidas mesmo assim. Exceções de transporte são capturadas sem aviso visível. O mapa de intenções continua ativo. `settleQuantities()` pode terminar sem a quantidade estar efetivamente confirmada e deixar a cotação prosseguir.

**Correção proposta:** separar falha de rede, nonce, estoque e validação. Rejeitar o flush quando falhar; mostrar aviso traduzível; reconciliar com o servidor; preservar a intenção somente como tentativa pendente explícita. Cotação dependente de quantidade e navegação para checkout precisam aguardar confirmação ou apresentar recuperação clara.

### F08 — P1 — CEP novo sem taxas preserva frete antigo na interface

**Confirmado por leitura da correção local.** O POST de CEP já altera o cliente no servidor. Se a resposta vier sem taxas, `cart-quantity-stability.js:571–573` retorna antes de aplicar o novo destino e limpar a entrega anterior no estado do bloco.

Assim, “não há opção” pode coexistir com método e total referentes ao CEP anterior. Não aplicar itens antigos é necessário, mas não aplicar a invalidade do frete novo deixa outra incoerência.

**Correção proposta:** após confirmar que a resposta pertence à intenção e ao carrinho atuais, aplicar também o estado sem disponibilidade: destino novo, lista vazia, seleção anterior invalidada e total oficial correspondente. Erro de provedor e ausência efetiva de cobertura devem ser estados distintos.

### F09 — P2 — Cotação da PDP ignora quantidade e pode ficar desatualizada após trocar variação

**Confirmado no código.** `ShippingQuotes.php:25` e `:85` fixam quantidade em **1**, inclusive na formatação para Melhor Envio. O formulário da cotação não envia a quantidade do formulário de compra. Selecionar outra variação atualiza o campo oculto, mas não invalida uma cotação já exibida nem uma resposta em trânsito.

**Correção proposta:** incluir quantidade válida e variação selecionada na entrada da estimativa; invalidar o resultado quando mudar produto/variação/quantidade/CEP. Não cotar o pai variável como se fosse a variação escolhida. Persistir somente a preferência de destino após validar a intenção; não tratar a estimativa do produto isolado como taxa confirmada do carrinho completo.

### F10 — P1 — Cotação e hidratação podem montar endereço híbrido

**Risco demonstrável no código; percurso autenticado completo não reproduzido.** O carrinho copia `current.shipping_address` inteiro e troca país, CEP e estado (`cart-quantity-stability.js:32`). A PDP grava CEP/UF em cliente e sessão sem reconciliar rua/cidade anteriores (`ShippingQuotes.php:116–135`).

`CheckoutCustomerData::hydrateShippingAddress()` completa campos vazios individualmente com o endereço salvo; `inheritBillingResponseWhenShippingEmpty()` faz herança campo a campo e continua atuando em respostas posteriores. Um CEP recente pode ser combinado com cidade, rua e bairro de outro endereço. A resposta também pode ser enriquecida depois de os totais já terem sido calculados com outro conjunto de campos.

**Correção proposta:** distinguir destino de estimativa e endereço de entrega validado, com origem e CEP de referência. Se o CEP mudou, não transportar automaticamente campos geográficos de outro CEP. Hidratar endereço salvo como conjunto coerente e apenas quando não houver uma intenção posterior; executar a política antes do cálculo. A regra de ViaCEP continua obrigatória no formulário de endereço.

### F11 — P2 — Cidade transitória é uma adaptação frágil de compatibilidade

**Confirmado no código instalado.** `WC_Customer::has_full_shipping_address()` usa campos de país/cidade/UF/CEP e verifica presença. O `ShippingController::remove_shipping_if_no_address()` filtra taxas em `woocommerce_shipping_packages`, prioridade **11**. A correção local abre uma janela por esse filtro para retornar uma cidade composta por espaço durante a verificação, além de preencher o pacote.

Isso explica o problema relatado da segunda leitura e o motivo da nova adaptação. A estratégia depende de prioridade, sequência de hooks e aceitação de espaço como valor. Ela não fornece um município real a um método que precise dele, e hoje não está limitada a um marcador explícito da estimativa própria.

**Correção proposta:** centralizar a política de destino de cotação e delimitar seu contexto. Validar cálculo inicial e serialização de taxas com endereço completo ligado/desligado. Não persistir município fictício nem relaxar a validação final de entrega. Se um método exigir cidade real, tratar como necessidade de endereço completo na etapa apropriada; não consultar ViaCEP na calculadora de frete contrariando a regra vigente.

### F12 — P2 — Controles nativos são reativados e linhas identificadas por posição

**Confirmado no código.** `unlockQuantityControls()` remove indiscriminadamente `disabled`/`aria-disabled`; passos usam `current + direction` e limites min/max, sem respeitar `multiple_of`. Os listeners são globais, enquanto `itemForRow()` procura linhas do carrinho e associa itens pelo índice.

Riscos: remover restrições legítimas de estoque/quantidade, permitir valores fracionários na digitação, interferir com o minicarrinho aberto na rota `/carrinho`, ou associar o item incorreto quando o DOM tiver outra composição. A associação incorreta com minicarrinho não foi reproduzida nesta rodada.

**Correção proposta:** preservar restrições de negócio; distinguir bloqueio por rede de proibição de quantidade; usar chave estável do item e eventos/extensões dos componentes. Respeitar os limites oficiais completos, inclusive múltiplos. Evitar observer global que reativa qualquer controle.

### F13 — P2 — Integrações e infraestrutura ampliam falhas difíceis de diagnosticar

**Confirmado por leitura; impacto de cada configuração deve ser validado no ambiente alvo.**

- URLs `/wp-json/wc/store/v1/...` estão fixas no JS do carrinho; `AddressLookup` já recebe URLs produzidas por `rest_url()`. Instalação em subdiretório ou outra configuração de REST pode quebrar apenas um fluxo.
- Nonce, parsing, erros e recebimento de carrinho são implementados separadamente no CEP do carrinho, ViaCEP/checkout e cards. Uma falha inicial de nonce no lookup pode deixar `noncePromise` resolvida sem um nonce utilizável.
- O plugin Melhor Envio versionado contém `'timeout '` com espaço em `Services/RequestService.php:70`; o parâmetro não configura a chave `timeout` da API HTTP como pretendido. Não foi medida a latência efetiva com credenciais reais.
- “Nenhuma taxa” não distingue indisponibilidade de transportadora, credencial inválida, destino incompleto, configuração de zona e ausência real de cobertura.
- Há cache de pacotes do WooCommerce e cache de cotação do Melhor Envio. A PDP usa o cálculo de shipping e a sessão da mesma loja; não presumir isolamento entre estimativa e carrinho sem teste.
- `product-card.js:339–350` solicita atualização dos Blocks e de fragments clássicos. Validar quais consumidores ainda precisam dos dois antes de eliminar a duplicação.
- O plugin brasileiro tem outras opções de preenchimento automático, progresso e frete grátis por produto. Desligar seus widgets de carrinho não neutraliza todos os hooks PHP. Não atribuí a ele a mutação PHP induzida em F05.

**Correção proposta:** um adaptador para operações próprias, com URLs localizadas pelo PHP, tratamento uniforme de nonce/erro, timeout e diagnóstico sem dados pessoais/tokens. Preferir mecanismos nativos e caches existentes. Corrigir incompatibilidade de terceiro por atualização compatível ou hook estritamente limitado ao fornecedor, preservando os arquivos versionados de terceiros.

## Avaliação do trabalho local

| Mudança local | Avaliação |
|---|---|
| Esperar o flush de quantidade antes do POST do CEP | Direção correta; deve confirmar sucesso, não apenas término da Promise |
| Derivar UF a partir do CEP | Reutilizar `BrazilianPostcode`; faixa numérica não comprova existência do CEP nem cobertura |
| Disponibilizar cidade transitória na releitura das taxas | Endereça a segunda leitura relatada; exige delimitação e regressão de compatibilidade |
| Preservar itens ao receber cotação | Evita uma substituição direta; precisa garantir que os totais recebidos pertencem aos mesmos itens e cupons |
| Ignorar resposta sem taxas | Rever: deixa frete antigo no bloco depois de mudar o destino no servidor |
| Restaurar quantidade em `after_calculate_totals` | Rever antes de publicar: reprodução mostrou totais e sessão divergentes |
| Mostrar CEP salvo | Útil; diferenciar valor em edição e valor confirmado para não sobrescrever o que a pessoa está digitando |

## Centralização proposta

O WooCommerce deve continuar calculando preços, descontos, impostos, pacotes e taxas. A centralização própria deve organizar intenção, destino e integração; não implementar outro motor financeiro ou outra Store API.

| Responsabilidade | Reuso/estrutura proposta | Reduz duplicação em |
|---|---|---|
| Normalização de CEP e derivação de UF | Manter `BrazilianPostcode`; expor normalização/validação estrutural comum; regra de destino em `ShippingQuoteDestination` | `ShippingQuotes`, carrinho, validação de destino |
| Destino para estimativa | Uma política explícita de país/CEP/UF/contexto, sem endereço residencial fictício | PDP e CEP do carrinho |
| Cotação de produto | Manter `ShippingQuotes`, recebendo produto/variação/quantidade e delegando aos métodos WooCommerce | PDP; retirar acoplamento desnecessário a persistência de cliente |
| Operações próprias de carrinho | Adaptador JS fino sobre ações públicas dos Blocks; uma coordenação das mutações próprias e intenções pendentes | Campo de CEP, integração ViaCEP e ações próprias de compra |
| Endereço de entrega | `AddressLookup` como único cliente ViaCEP; `CheckoutCustomerData` como política de hidratação coerente | Conta e checkout |
| Apresentação de estado | Componentes com estados de edição, confirmação, recálculo, indisponibilidade e erro | Produto, carrinho e checkout |
| Diagnóstico e evidências | Identificador de operação, geração, duração, resultado e versão das dependências; não registrar endereço completo/documento/token | Investigação de regressões e divergência local/produção |

As ações públicas documentadas incluem `changeCartItemQuantity`, `updateCustomerData` e `selectShippingRate`. Conferir disponibilidade e semântica na versão instalada antes da migração. O adaptador não deve substituir `window.fetch`, `wp.data.dispatch` nem reescrever respostas globais. A documentação oficial descreve esses contratos em [Cart Store](https://developer.woocommerce.com/docs/block-development/reference/data-store/cart/).

Para uma mutação de negócio específica da extensão, avaliar o caminho oficial [Updating the cart on-demand](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-update-cart/). Sua existência não exige criar um endpoint próprio para operações já fornecidas pelo WooCommerce.

### Regras de consistência da implementação

1. Quantidade desejada e quantidade confirmada são estados distintos; só intenções ainda pendentes permanecem na fila.
2. Apenas mudanças sucessivas da mesma quantidade são coalescidas. Escolha de entrega, CEP, cupom e remoção não podem desaparecer.
3. Resposta obsoleta não atualiza parte financeira nem destino confirmado; respostas aplicadas mantêm consistência entre itens, cupons, taxas e totais.
4. Um CEP novo invalida cotação e seleção anteriores; indisponibilidade também é um resultado que precisa chegar à interface.
5. Totais oficiais vêm do servidor. Não escalar manualmente descontos e impostos no carrinho: desconto fixo, regras por quantidade e arredondamento não são necessariamente lineares.
6. A cotação não altera quantidades nem cria um endereço de entrega fictício; endereço salvo não sobrescreve intenção recente.
7. Toda Promise termina com sucesso da operação correspondente ou erro recuperável explícito. Falha ao confirmar quantidade impede tratá-la como concluída.
8. Checkout recebe o estado confirmado. Ao sair da rota com debounce pendente, sincronizar antes da navegação; `beforeunload` não é garantia de gravação.
9. Coordenação no navegador não resolve duas abas por si só. Validar concorrência entre requisições da mesma sessão; se houver perda de atualização, adotar controle de versão/serialização no escopo necessário, sem SQL ad hoc nem bloqueios globais.

## Ordem recomendada de correção

1. **Fechar integridade de quantidade e totais:** F01, F02, F05 e F07; retirar replay de valores já confirmados e corrigir o momento de cálculo/persistência.
2. **Preservar todas as operações:** F03 e F04; migrar a interceptação global para integração explícita com os Blocks.
3. **Unificar destino e endereço:** F06, F08, F10 e F11; resolver respostas atrasadas, CEP sem cobertura e endereço híbrido.
4. **Alinhar superfícies:** F09 e F12; quantidade/variação da PDP, minicarrinho, identificação por chave e limites nativos.
5. **Consolidar operação e manutenção:** F13; URLs, nonce, diagnóstico, compatibilidade de terceiros e gates comuns.

Essas etapas constituem uma sequência técnica, não tickets novos nem um plano marcado como aprovado. Antes da implementação, enriquecer o plano correspondente segundo as regras locais, com escopo e critérios obrigatórios.

## Regressões necessárias

| Grupo | Cenários obrigatórios | Evidência de aceite |
|---|---|---|
| Concorrência de quantidade | `1→2→3`, `3→2→4`, digitação, mudança durante resposta lenta; dois itens; item removido | Sem reenvio de intenção confirmada; estado final igual à Store API independente; sem regressão financeira silenciosa |
| Operações sobrepostas | Quantidade + CEP; quantidade + escolha de entrega; quantidade + cupom/remover; navegação para checkout | Cada intenção executa ou é explicitamente substituída por outra da mesma natureza; nenhuma resposta falsa de sucesso |
| Protocolo/erros | Requisição simples e batch; 400/401/403/409/500; offline; timeout; estoque alterado | Formato preservado, erro visível, fila reconciliada, sem loading permanente nem estado otimista definitivo |
| Endereço e sessão | Visitante e logado; endereço salvo de outra UF; faturamento diferente; recarregar; logout/login | CEP/cidade/UF/rua coerentes e quantidade/totais iguais após nova requisição |
| ViaCEP | A lento/B rápido; remoção parcial do CEP; edição manual durante consulta; erro de API | Resposta antiga não preenche campos nem grava cliente; endereço manual permanece utilizável |
| Frete | Exigir endereço completo ligado/desligado; sem taxa após CEP com taxa; retirada; múltiplos pacotes; provedor indisponível | Totais, opções, seleção e destino coerentes; nenhuma cidade fictícia gravada |
| Produto e minicarrinho | Simples, variável, promoção, virtual, personalizado; quantidade/variação alteradas; minicarrinho na Home e na rota de carrinho | Cotação corresponde à entrada atual; metadados preservados; nenhum listener cruza componentes |
| Valores | Impostos incluídos/excluídos, cupom fixo/percentual, desconto por quantidade, frete grátis/limiar, arredondamento | Comparar contra valores oficiais; não usar `preço × quantidade` como oráculo do carrinho |
| Ambiente | Configuração equivalente à produção, plugins/versões, cache e instalação com caminho base | O gate não passa somente por fallback local ou configuração diferente |

Os gates 037/039 atuais são úteis, mas insuficientes: 037 prioriza convergência e estabilidade posterior, e admite valores antigos na transição; 039 monitora principalmente quantidade e contagem de requisições. É preciso capturar a sequência de valores financeiros desde o primeiro clique e introduzir latência/reordenação controladas. O novo validator de destino contém verificações textuais e de hooks; a presença de uma chamada não demonstra consistência entre resposta e sessão.

Para qualquer interface nova, textos funcionais permanecem traduzíveis; textos comerciais próprios devem ter origem administrável. Não introduzir copy comercial ou imagens fixas na correção. Preservar os blocos Gutenberg já editáveis e suas alterações após reprovisionamento.

## Evidências desta auditoria

Arquivos de investigação em `.local/audit-shipping-cart/`, ignorados pelo Git:

- `guard-probe.mjs` / `guard-evidence.json`: mistura de totais, seleção de entrega descartada, endereço suprimido, batch com corpo incorreto e intenção expirada retida.
- `browser-probe.mjs` / `browser-evidence.json`: sequência do DOM/estado, entrega controlada de resposta antiga e reenvio de quantidade antiga com dois itens.
- `address-probe.mjs` / `address-evidence.json`: ViaCEP fora de ordem com CEP RS e endereço SP.
- `restore-probe.php`: reprodução no WooCommerce de quantidade 2, total de 1 e sessão com 1.
- `read-runtime.php` e `triage.json`: configuração não sensível e classificação do repositório.

Reproduções Node/Chromium executadas pelo serviço `node` do Compose; ensaio PHP no contêiner WordPress. `git diff --check` passou. Não foi executada a suíte global porque não houve implementação funcional; os testes acima são evidência de falhas, não aceite de uma correção.

A explicação das duas verificações de endereço foi confrontada com o código instalado de `WC_Customer`, `WC_Cart` e `ShippingController`. Para referência de manutenção: [ShippingController oficial](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/Blocks/Shipping/ShippingController.php), [fluxo de dados dos Blocks](https://developer.woocommerce.com/docs/block-development/reference/overview-of-data-flow/) e [eventos DOM de carrinho](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/dom-events/). O código de `trunk` serve como referência; o diagnóstico local usou a versão instalada 10.9.4.
