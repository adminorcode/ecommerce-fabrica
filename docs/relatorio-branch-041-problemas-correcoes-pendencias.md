# Branch 041 — problemas, correções e pendências

**Atualizado em:** 09/10/2026, após a aprovação da rodada ampliada de desempenho do carrinho.
**Branch:** `codex/041-integridade-frete-carrinho-checkout`.
**Plano:** [041 — Frete e carrinho consistentes em toda a loja](../Plans/041-integridade-frete-carrinho-checkout.md).
**Situação:** em andamento. Há correções implementadas e verificadas localmente, mas a branch ainda não tem aceite integral.

**Decisão posterior — 09/10/2026:** por solicitação do usuário, a investigação/correção da divergência financeira de 44 kg fica fora da continuação imediata. O [plano de continuação no §17 do Plano 041](../Plans/041-integridade-frete-carrinho-checkout.md#17-plano-de-continuação--confiabilidade-segurança-e-operação) detalha as demais frentes, sua ordem, cobertura e critérios de aceite. O caso fica identificado como ADIADA-44KG; os demais testes financeiros permanecem obrigatórios. Esta decisão permite avançar, sem aprovar o defeito conhecido ou autorizar publicação. Os resultados históricos abaixo permanecem preservados.

Este relatório consolida a auditoria, os relatos do usuário e os problemas encontrados durante a implementação. O histórico detalhado e as tentativas estão nos §§10–16 do plano. Alterações locais anteriores foram preservadas; o conjunto de diferenças da branch não corresponde exclusivamente à última sessão.

## 1. Resumo do estado atual

- A integração própria passou a usar o estado e as ações públicas do WooCommerce para confirmar carrinho, endereço e entrega.
- O carrinho exibe as alternativas de entrega disponíveis, com CEP acima do total e os estilos da loja preservados.
- O checkout tem campos únicos e disposição conforme a referência aprovada; consulta ViaCEP ao digitar e ao receber CEP da sessão.
- Alterações de quantidade e CEP no Cart Block são coordenadas: apenas uma operação física de carrinho fica em andamento; trabalhos substituídos ainda na fila não são enviados.
- Quantidade e estimativa de produtos respondem imediatamente. Frete e total do pedido aguardam confirmação própria. O valor confirmado da linha vem dos totais oficiais, incluindo desconto e a configuração de exibição de imposto.
- A última rodada ampliada de desempenho passou em visitante/autenticado, desktop1440/mobile390, incluindo teclado, estoque, produto vendido individualmente, falha503, retry, cupom e remoção.
- **A divergência financeira do pacote de44kg continua aberta.** Não há comprovação de que todas as falhas restantes sejam um defeito esperado da API dos Correios.
- Staging, pedido de teste e a matriz completa T01–T20 permanecem pendentes. Não houve commit, push, PR, merge ou publicação nesta continuação.

## 2. Problemas da auditoria inicial

“Implementado” abaixo descreve a correção do mecanismo identificado. Não significa que todas as combinações da matriz global tenham passado.

| ID | Problema encontrado | Correção aplicada | Situação / limite da evidência |
|---|---|---|---|
| F01 | Quantidade, total da linha e total geral podiam pertencer a respostas diferentes | Retirada de merges e pintura manual de valores; confirmação integral pelo fluxo nativo; pendências por região | Mecanismo corrigido e cenários focados aprovados. Consistência financeira ampla ainda bloqueada |
| F02 | Quantidades históricas eram reenviadas, desfazendo alterações posteriores | Retirada do replay histórico; intenção atual por chave real da linha e revisão | Unit e browser de concorrência aprovados, incluindo retorno ao valor anterior |
| F03 | Interceptação antiga descartava alteração de endereço ou parceiro | Remoção do interceptor legado; ações públicas; coordenação posterior restrita ao Cart Block e às operações de carrinho | Contratos e cenários focados aprovados; checkout/pagamento e batch misto não entram nessa fila |
| F04 | Batch podia receber resposta sintética incompatível | Preservação dos envelopes e respostas oficiais; inspeção dos erros internos sem fabricar sucesso | Contratos unitários e observação de batch no browser aprovados |
| F05 | PHP restaurava quantidade depois de calcular totais/sessão | Remoção da restauração; cotação não altera itens e não restaura snapshot completo de sessão | Destino/preview PHP aprovados; não resolve por si a divergência de transportadora |
| F06 | Resposta ViaCEP antiga sobrescrevia o novo endereço | Geração por formulário/CEP, cancelamento lógico, deduplicação e aplicação somente da consulta atual | Corridas de endereço e checkout focados aprovados |
| F07 | Falha de gravação podia ficar escondida por estado otimista | Erro explícito, conservação do desejo, retry e releitura oficial quando estoque exige reconciliação | Falhas503 e estoque verificadas; cobertura completa de falhas integradas ainda parcial |
| F08 | CEP sem taxas conservava frete antigo | Aplicação da resposta oficial, inclusive lista vazia; invalidação imediata da consulta antiga | Cobertura focada de destino/preview. Desaparecimento real de método no Cart ainda precisa de aceite completo |
| F09 | PDP cotava sempre uma unidade e não invalidava corretamente ao trocar variação | Consulta identifica produto/variação, quantidade e CEP atuais; geração invalida resultado obsoleto | Quantidades1/2/3 e erros de PDP aprovados com transporte induzido; preview PHP ampliado para promoção/virtual |
| F10 | Sessão/preferência podiam formar endereço híbrido | Origem e CEP explícitos; limpeza geográfica coerente e hidratação única; dados pessoais preservados | Ensaios de destino e checkout aprovados; todas as combinações autenticadas billing/shipping ainda não cobertas |
| F11 | Município fictício e prioridade de hooks simulavam endereço completo | Uso da configuração nativa para estimar por CEP; migração única preserva futuras decisões administrativas; endereço final continua obrigatório | Gates PHP aprovados; nenhuma cidade fictícia usada como confirmação |
| F12 | Controles nativos eram forçados e linhas identificadas pela posição | Limites e componentes nativos; identificação por chave real de item | Estoque, sold-individually e duas linhas verificados; cobertura global de todas as superfícies ainda parcial |
| F13 | URLs, nonce, caches e fornecedores ampliavam falhas difíceis de rastrear | URLs geradas no servidor, dependências versionadas, preview isolado, timeout restrito ao endpoint de cotação e transporte público | Gates focados aprovados; instalação real em subdiretório e transporte externo pesado ainda pendentes |

Referência: [auditoria F01–F13](analise-frete-carrinho-2026-10-08.md).

## 3. Problemas reportados ao longo da branch

| Problema observado | Causa identificada / correção | Resultado atual |
|---|---|---|
| CEP do carrinho fora da coluna de totais | Migração antiga inseria o bloco após o fechamento do wrapper; reparo restrito ao conteúdo legado, com backup e preservação das edições | Campo reposicionado acima do total; persistência e layout desktop/mobile aprovados em checkpoints |
| Carrinho mostrava uma entrega, PDP mostrava duas ou três | No caso reproduzido, API e PDP tinham três métodos; resumo nativo mostrava somente o selecionado e não havia seletores | Bloco lista taxas por pacote e seleciona pelo fluxo público, com preços nativos, teclado, rollback e retry |
| Estilos de CEP e opções ficaram diferentes da loja | Classes do formulário ausentes e CSS atingia radios | Classes restauradas e CSS localizado; cores/estilo anteriores preservados |
| Prazo de entrega aparentemente incorreto | A interface apresenta o prazo recebido do método; não foi comprovada causa própria para o prazo “1 dia útil” | **Ainda requer rastreamento do valor de origem.** Não foi criado prazo artificial para encobrir a diferença |
| Checkout confuso, CEP abaixo e campos duplicados de bairro/número/CEP | Registros legados e integração de campos sobrepostos | Campos únicos, aplicação pela API oficial de campos adicionais e consulta imediata do CEP da sessão |
| Disposição do checkout diferente da referência | Organização dos campos e espaçamentos | Ordem aprovada: destinatário Nome/Sobrenome; País/CEP; rua/número; complemento; bairro/cidade; estado/telefone; mesmo endereço de cobrança. Visual da loja mantido |
| Campos manuais eram perdidos no preenchimento/reload | Lookup/hidratação sem preservar origem e edição posterior | Rua, bairro, cidade e complemento editados são preservados; endereço de cobrança independente e remontagem verificados |
| Preferência de CEP não era salva em HTTP local | `crypto.randomUUID` indisponível fora de contexto seguro | Fallback com `crypto.getRandomValues`; expiração30min, logout, troca de conta e storage bloqueado cobertos |
| Trocar parceiro era lento | Woo Better apagava caches mesmo com regras de frete grátis desativadas; havia duas esperas próprias de500ms | Guarda restrita pelo hook público e retirada dessas esperas. Benchmark histórico: carrinho571/715/1622ms; checkout580/531/507ms |
| Warning React de `autocomplete` | Atributo dos campos próprios incompatível com React | Corrigido por atributo público e propriedade DOM; autofill conferido |
| Warning `useSelect/getValidationError` | Seletor da versão instalada do WooCommerce cria função nova | **Permanece externo ao seletor próprio.** Não foi ocultado nem corrigido editando vendor |
| Quantidade/CEP voltavam para valores antigos | Respostas e erros atrasados, retry de nonce e intenção perdida no debounce/retorno ao valor confirmado | Coordenador por chave/revisão, descarte de snapshots obsoletos e resgate pela ação oficial; monitoramento a cada10ms nas sequências1→3→1 e1→3→1→3 aprovado |
| Serialização deixou a interação visual lenta | Esconder valores de forma global obrigava usuário a esperar todo cálculo; pendências precisavam ser consolidadas | Estimativa imediata por linha/subtotal, unitário legível e estados separados de entrega/total; fila mantém somente desejo vigente por linha |
| Total do produto confundido com total do pedido | Apresentação não distinguia linha, subtotal e agregados durante atualização | Linha confirmada usa `line_total` e imposto de exibição oficial; estimativa é rotulada antes dos descontos; pedido usa totais oficiais com entrega |

Os tempos de troca de parceiro são medições históricas locais do §14, não SLA e não comparação direta com o teste atual de quantidade.

## 4. Problemas encontrados durante implementação e revisão

| Problema / tentativa reprovada | Correção realizada | Verificação |
|---|---|---|
| Retry de nonce ressuscitava quantidade antiga depois de limpar pendências | Revisão mantida por opções/chave; intenção obsoleta rejeitada antes de alterar falhas atuais | Unit do coordenador aprovado |
| Erro antigo com `data.cart` podia aplicar snapshot desatualizado | Guardas de revisão também no caminho de erro | Unit e concorrência browser aprovados |
| Retorno1→3→1 podia não gerar a alteração final nativa | Resgate somente do desejo atual omitido, com update-item e sincronização pública | Concorrência1440/390 visitante/autenticado aprovada |
| Rejeição antiga liberava hook e restaurava input enquanto desejo final estava em voo | Encerramento de quantidade descartada espera aplicação final; CEP mantém sua semântica própria | Amostragem de input a cada10ms aprovada |
| Erro real400 de estoque sem `data.cart` deixava rascunho preso | Releitura integral oficial e reconciliação com quantidade aceita | Unit e browser ampliado aprovados |
| Batch HTTP200 escondia erro400 interno | Observação de status/resposta de cada operação, sem transformar payload | Unit e browser aprovados |
| `apiFetch(parse:false)` rejeitava `Response` bruto | Clone drenado e lido no catch; erro original preservado para WooCommerce | Unit de reprodução e estoque browser aprovados |
| Corpo de resposta ainda em leitura podia liberar próximo trabalho cedo | Drenagem do corpo antes de avançar fila | Unit com stream atrasado aprovado |
| Falha de uma linha era limpa por alteração de outra | Estado de falha por chave; revisão obsoleta não limpa erro atual | Unit de múltiplas linhas aprovado |
| Releitura de estoque antiga disputava edição mais recente | Rechecking invalidado pela nova intenção; conclusão ignora chaves substituídas; não repete GET desnecessário | Unit aprovado |
| Render nativo sobrescrevia classe de rascunho | Aplicação idempotente em cada commit, separada da validação pública | Erro503/retry browser aprovado |
| Clique bloqueado no checkout deixava spinner nativo preso | Bloqueio síncrono de clique e propagação quando há desejo não confirmado | Teste de clique imediato e ausência de spinner aprovado |
| CSS legado ocultava preço unitário sem promoção | Regra removida; valor unitário e badge de economia preservados | Teste exige dimensões reais, não apenas existência do elemento |
| Aviso de atualização não surgia embora componente renderizasse | Removido setState sem mudança de targets; efeitos acompanham pendência efetiva | Status após300ms, foco e reduced-motion aprovados no browser |
| Ao sair do campo depois de503, quantidade restaurada apagava desejo e retry | Reconciliação de focusout limitada a entrada inválida que precisa de clamp nativo | Rodada ampliada final aprovada nos quatro contextos |
| Teste de vendido individualmente esperava um input não editável | Harness corrigido: componente nativo não exibe editor de quantidade nesse produto | Ausência de input e rejeição oficial `readonly_quantity` aprovadas |
| Harness observava apenas POST direto e perdia batch | Observação de ambos envelopes e sucesso/erro internos | Gates de cards, quantidade e entrega corrigidos |
| Runner Node não tinha fonte do asset montada | Unit usa asset entregue no runtime quando fonte não está montada | Validação geral reexecutada e aprovada |
| `strict_types` não funcionava com execução direta por eval-file | Novos testes strict executados por `wp eval` com require | Runners PHP/PS/Bash ajustados |
| Falhas de cleanup/reprovisionamento parcial deixavam fixtures ou impediam restauração | Cleanup armado antes do setup; todos os cleanups/restores tentados, preservando erro original | Gate próprio de cleanup com falhas induzidas aprovado |
| Reset de socket em GET interrompia teste | Retry limitado a leitura GET/HEAD; escritas não repetidas automaticamente | Ensaios afetados reexecutados; erros não expõem cookies |
| Assert de sessão/cache comparava identidade de objeto desserializado | Comparação do conteúdo pertinente | Gate PHP de cache aprovado |

As duas revisões dedicadas do §16 identificaram P1, corrigidos. O delta posterior teve revisão local e testes focados; não foi declarado aprovado apenas com base no parecer anterior.

## 5. Como funciona o algoritmo atual

1. O componente nativo captura a quantidade. O plugin acompanha o desejo por chave real e mostra uma estimativa imediata, sem gravá-la no estado financeiro.
2. Mantém-se o debounce nativo. Na versão instalada, há400ms no hook de quantidade; a digitação também passa pelo componente numérico, com600ms e confirmação por blur/Enter. O plugin não acrescenta debounce próprio.
3. Operações Store API de carrinho são serializadas no Cart Block. Pendências da mesma linha são consolidadas; alterações de outras linhas são preservadas.
4. Se um novo desejo substitui trabalho ainda na fila, o anterior não chega ao servidor. Se a operação anterior já começou, ela termina fisicamente; sua resposta obsoleta não confirma a interface. A operação final segue depois.
5. Quantidades omitidas por retorno ao valor confirmado são resgatadas pela ação pública, sem replay de histórico.
6. O WooCommerce confirma quantidade, preço, desconto, imposto, pacote e frete. A interface aplica o snapshot oficial atual e encerra somente sua própria pendência.
7. Falhas atuais permanecem visíveis. Retry de rede é explícito; rejeição de estoque dispara releitura oficial e reconciliação, sem loop.

**Limite importante:** abortar o fetch não garante que PHP parou de gravar a sessão. Por isso, o código descarta a intenção antiga e sua resposta, mas espera a operação física já iniciada terminar. Não promete cancelar processamento do servidor ou garantir exclusão entre abas/dispositivos.

## 6. Validação e métricas disponíveis

| Validação | Resultado registrado | Limite |
|---|---|---|
| Geral alterada | Aprovada em68 arquivos, após entrega no runtime | Antes da última correção pequena de focusout; essa correção teve sintaxe e browser ampliado próprios |
| PHPUnit |58 testes,219 asserções aprovados em checkpoint anterior | Não é uma nova execução após cada mudança de apresentação |
| Unit coordinator/estimate/cleanup | Aprovados | Contratos determinísticos, não transportadora real |
| Concorrência Cart | Quatro cenários1440/390 visitante/autenticado aprovados, inclusive CEP e retornos de quantidade | Última execução precede o ajuste de normalização de focusout; rodada ampliada seguinte cobre o ajuste e recuperação |
| Desempenho Cart — rodada final ampliada | Quatro cenários aprovados: simples/variável, duas linhas, cupom, teclado sem blur, CTA imediato, foco, loading, reduced-motion, clamp,400,503/retry, remoção e sold-individually | Carrinho normal e fixtures controladas; não pacote financeiro pesado |
| Entrega, checkout/endereço, editor, PDP, cards e hub | Aprovados nos checkpoints descritos nos §§10–15 | A regressão browser conjunta completa ainda não foi concluída após todo o §16 |
| Comércio nativo PHP | Cupons, impostos incl/excl, dois pacotes, entrega/retirada zero, estoque e sold-individually aprovados | Métodos sintéticos; não substitui browser financeiro real |
| Financeiro pesado | Reprovado; divergência de preço e último timeout20s | Bloqueador de fechamento, sem relaxamento da comparação |
| Higiene local | Sete arquivos próprios de produção fonte/runtime com SHA-256 iguais; sintaxe do delta e diff check aprovados | Runtime local; não publicação |

### Métricas da última rodada ampliada

| Medida | Resultado |
|---|---|
| Cinco cliques separados por150ms | Uma escrita de quantidade final6 em cada contexto |
| Nova sequência com quantidade2 já em voo | Apenas2 e7 enviadas |
| Operações físicas simultâneas de carrinho | Máximo1 |
| Resposta visual da estimativa por clique |14,7–58,5ms |
| Pintura após resposta final |89,4–106,2ms |
| Tempo completo do burst até confirmação visual |5,49–5,83s, incluindo atraso controlado de900ms, interação, debounce e cálculo/cotação |

Os resultados demonstram resposta visual rápida e limite de requisições; **não demonstram que o cálculo externo ficou abaixo de um segundo**. A baseline visitante já tinha uma escrita no burst; não afirmar redução medida de cinco requisições para uma nesse par. A baseline pesada/autenticada de desempenho não foi capturada integralmente.

Evidências detalhadas ficam em `.local/evidence/041-cart-performance/`, `041-cart-concurrency/`, `041-cart-delivery/`, `041-checkout-address/`, `041-product-quote/`, `041-editor/` e `041-final/`. São arquivos locais ignorados pelo Git; este relatório preserva o resumo revisável sem credenciais, cookies ou payloads pessoais.

## 7. O que falta — em ordem de prioridade

### P1 — Divergência financeira do pacote pesado

No par autenticado controlado, produtos2406×3 e2405×1, pesos unitários11kg, peso total44kg, dimensões, valor dos itensR$84,40, sessão e endereço observado eram iguais. POST/batch e GET são chamadas diferentes, embora comparem o mesmo carrinho.

- O batch recebido pela interface tinha SedexR$263,62 e Jadlog selecionadoR$248,95.
- O GET independente tinha Sedex selecionadoR$20,48.
- O último ensaio amplo também atingiu timeout20s no visitante desktop; a evidência final tinha quantidades3/1 corretas, mas taxas diferentes.
- Peso cadastrado não comprova peso tarifável/cubado ou pacote externo. O corpo efetivamente enviado à transportadora e a primeira diferença de contexto/cache precisam ser rastreados.

**Ação necessária:** capturar, em sessão isolada, identidade e conteúdo dos pacotes, invalidações de cache, contexto de cálculo e chamadas/respostas externas; identificar a primeira divergência e corrigir sua causa antes de repetir o gate. Não fixar tarifa, não aumentar timeout para declarar aprovação e não atribuir causa aos Correios sem prova.

Referência: [comparação de API, produtos, quantidade, endereço e peso](diagnostico-frete-041-2026-10-08.md).

### P2 — Prazo exibido e desempenho externo

- Rastrear de onde vem o prazo questionado e comparar as respostas reais para entradas equivalentes; a UI não deve inventar prazo.
- Separar tempo de cálculo próprio, cotação e deadline. Há ciclos de cotação repetidos em alguns caminhos nativos; não ampliar preservação de cache sem provar equivalência de pacote/destino/regras.
- Não garantir que uma operação PHP já iniciada será cancelada. A fila mantém segurança da sessão, com retorno visual imediato ao usuário.

### Aceite restante da matriz T01–T20 e §16

- Completar consistência autenticada/pesada, entrega selecionada e reordenação real.
- Completar recuperação integrada no Cart para todos os negativos previstos: nonce expirado,429, timeout/conexão e desaparecimento de método. Estoque e503 já ganharam cobertura ampliada.
- Completar browser autenticado com endereço restrito, billing/shipping distintos e todas as combinações de hidratação.
- Completar rotas/cards/minicarrinho, variações/virtual e múltiplas linhas em todas as superfícies previstas.
- Completar browser financeiro de preço por quantidade, cupons/impostos, frete zero, múltiplos pacotes e preview concorrente real com reload. O PHP isolado não encerra esse aceite.
- Consolidar teclado e árvore acessível da matriz global; desempenho já verifica foco/status/reduced-motion e390/1440.
- Completar a regressão browser conjunta depois do §16, preservando os testes obrigatórios que ainda não rodaram nessa rodada. Os gates interrompidos deliberadamente têm estado “não executado”, não aprovação.
- Baseline anterior pesada/autenticada e relatório antes/depois equivalente continuam incompletos; não fabricar evidência retrospectiva.
- Validar instalação real em subdiretório.
- Executar staging com transportadoras reais e pedido de teste sem cobrança, conferindo endereço e totais finais.
- Concluir a entrega final do runtime/rebuild quando exigido pelo fechamento, revisão do conjunto e documentação operacional atualizada.

### Operação e entrega

- O conector ClickUp não estava disponível; tarefa/vínculo externo não foram criados. Isso está registrado no plano, sem ticket duplicado.
- Commit, push, PR, merge e publicação não foram feitos e não fazem parte desta autorização.
- O plano deve continuar **Em andamento** enquanto os critérios obrigatórios acima não forem comprovados.

## 8. Como validar manualmente

1. No ambiente local `http://localhost:8888`, adicione produto ao carrinho e aumente cinco unidades rapidamente. Observe estimativa identificada por linha, unitário legível e total do pedido em atualização. Na rede, depois do burst, confirme somente a quantidade final.
2. Com um cálculo ainda em andamento, mude novamente quantidade e CEP. Confirme que o último desejo prevalece e nenhuma resposta antiga volta como valor confirmado.
3. Confira que CEP fica acima do total e todas as taxas retornadas aparecem como alternativas. Troque parceiro pelo teclado; preço e total devem confirmar juntos após o cálculo.
4. Digite quantidade sem sair do campo, ultrapasse o estoque e teste falha de rede controlada. Confira foco, limite nativo, mensagem, retry e bloqueio de finalização enquanto houver alteração não confirmada.
5. No checkout, confira campos únicos e ordem aprovada; CEP salvo deve consultar imediatamente. Edite complemento/número e recarregue, verificando persistência.
6. Repita em desktop/mobile e visitante/autenticado. Os passos normais não encerram a pendência de44kg: esse caso exige conferir taxas e totais contra leitura independente, conforme o diagnóstico vinculado.

## 9. Referências e regra de atualização

- [Plano041 e ledger de execução](../Plans/041-integridade-frete-carrinho-checkout.md).
- [Status dos planos](../Plans/STATUS.md).
- [Auditoria original](analise-frete-carrinho-2026-10-08.md).
- [Diagnóstico financeiro](diagnostico-frete-041-2026-10-08.md).
- [Operação, conteúdo administrável e rollback](operacao-frete-plano-041.md).

Atualizar este arquivo após uma correção e sua validação, indicando o teste que passou e os limites da evidência. Problema implementado, teste focado aprovado e aceite integral são estados distintos. Não apagar tentativas reprovadas do ledger nem transformar pendência em aprovação por ausência de nova execução.
