# Operação e evidências do Plano 041

## Superfícies administrativas

| Rota | Conteúdo | Onde editar |
|---|---|---|
| `/carrinho/` | Textos, imagens e alt existentes da página; ordem e presença do campo CEP | Páginas → Carrinho → Gutenberg; bloco “CEP para entrega” dentro de Totais do carrinho |
| PDP | Texto/imagens/alt do produto, preço, promoção, variações, prazo próprio | Produtos → Editar produto; Biblioteca de mídia |
| Home, loja, busca, categoria | Cards derivados dos produtos e vitrines existentes | Produtos; Gutenberg nas páginas que contêm vitrines |
| Checkout, Minha conta | Conteúdo de página e dados de endereço | Gutenberg nas páginas; Minha conta → Endereços |
| Todas | Rótulos funcionais de CEP, validação e estado de cálculo | Tradução oficial `petshop-core`; não são conteúdo comercial novo |

O plano não introduz fotos nem texto comercial. A migração adiciona uma vez o bloco de CEP e registra o conteúdo anterior em `petshop_cart_shipping_quote_block_v1`. Remoção, reordenação e alterações editoriais posteriores devem permanecer. A configuração de frete antes do endereço completo é migrada uma vez para `no`, com valor anterior registrado em `petshop_shipping_quote_destination_v1`. Alterações administrativas posteriores são respeitadas. Não apagar esses marcadores para reprovisionar uma loja em uso.

A correção estrutural v2 registra o conteúdo anterior em `petshop_cart_shipping_quote_wrapper_v2` e move somente o slot legado salvo após o fechamento do wrapper de Totais. Não recria um bloco removido nem muda sua ordem editorial. O marcador v1 precisa apontar para a página de carrinho atual para autorizar esse reparo.

Por solicitação do usuário em 09/10, a posição original do CEP é restaurada acima do resumo e do total. `petshop_cart_shipping_quote_position_v3` move somente o bloco existente uma vez e guarda o conteúdo anterior; não recria remoções e preserva reordenações editoriais posteriores. Novas instalações já inserem o bloco nessa posição. As opções usam `RadioControl` nativo; os estilos próprios novos da lista foram removidos. O tema 0.6.29 invalida o CSS antes servido com versão fixa.

## Integração

Quantidade, descontos, seleção e valores continuam nativos do WooCommerce. A operação própria de CEP usa `extensionCartUpdate`, namespace `petshop-shipping-quote`, ação `set_quote_destination`. O adaptador aguarda pendências públicas e a sincronização de endereço resultante. ViaCEP usa geração por campo, aborta consultas anteriores e aplica somente campos ainda vigentes pela ação pública de endereço. A cotação não usa ViaCEP.

No Cart Block do WooCommerce 10.9.4, o resumo de entrega exibe apenas o método selecionado. O bloco próprio lista `shippingRates` da store pública por pacote, usa `FormattedMonetaryAmount` e a configuração nativa de impostos para os valores, e seleciona por `selectShippingRate(rateId, packageId)`. A ação nativa mantém o total e restaura a seleção anterior em caso de erro. O adaptador confirma a seleção efetiva antes de anunciar sucesso. Editar o CEP oculta as taxas do CEP anterior até o novo cálculo.

Na versão WooCommerce 10.9.4 verificada, a sincronização nativa de endereço tem debounce de 1500 ms. Após a extensão, o adaptador exige 2000 ms sem pendências antes de concluir a operação própria, com limite de espera de 15 segundos e erro recuperável. Atualizações do WooCommerce exigem repetir a matriz concorrente e confirmar esse contrato temporal. O preenchimento respeita endereço de entrega usado como cobrança e a configuração pública `forcedBillingAddress` no sentido inverso.

No checkout, a referência visual aprovada organiza Nome/Sobrenome; País/CEP; Rua/Número; Complemento; Bairro/Cidade; Estado/Telefone. A ordem de locale usa índices positivos, incluindo os campos adicionais. O tema abre o complemento pelo toggle nativo e o reposiciona no mesmo pai após número, porque o componente WooCommerce agrupa rua/complemento e ignora o índice deste último. O layout é restrito aos formulários de entrega/cobrança e conserva as cores da loja. O lookup consulta imediatamente o CEP hidratado da sessão após a resolução pública do carrinho, inclusive após consumir preferência de produto. Na entrada, completa somente campos vazios; detalhes já salvos pelo cliente são preservados. Ao digitar um novo CEP, mantém a proteção de gerações e edições manuais.

O callback específico `Virtuaria_Correios_Front_Fields::add_checkout_blocks_fields` é removido do hook público `woocommerce_init` antes do registro, pois seus campos CPF/telefone/número/bairro duplicavam os campos canônicos próprios e nativos. Os métodos de frete, os campos clássicos e os hooks legados de persistência do fornecedor permanecem. A persistência canônica do pedido sincroniza billing_person_type/_billing_person_type em pf/pj e billing_cpf/_billing_cpf ou billing_cnpj/_billing_cnpj, limpando os valores do tipo oposto. O gate PHP 026 persiste e recarrega PF → PJ → PF. Conferir o registro de campos e repetir o gate de checkout ao atualizar a transportadora.

Um CEP novo invalida rua, cidade, complemento e bairro da sessão; não grava endereço na conta. O snapshot geográfico preserva campos explicitamente vazios ao recarregar uma sessão autenticada. Dados adicionais obrigatórios continuam sendo validados no checkout.

O script `virtuaria-correios-autofill` é retirado por `wp_dequeue_script` somente nas telas de endereço cobertas pelo lookup próprio, após conferir sua origem. A consulta e as taxas da transportadora permanecem ativas. Quando o cliente salva os detalhes da conta, os campos registrados de tipo de pessoa/documento são reconciliados pela API `persist_field_for_customer`; isso evita que um PF antigo desfaça uma edição validada como PJ. Defaults leem metadados do objeto, sem invocar recursivamente seu próprio filtro.

A PDP calcula com produto, variação e quantidade atuais em cart/customer/session isolados. Reutiliza o cálculo dos métodos instalados. O cache PHP de cotação do Melhor Envio recebe um namespace separado; a sessão inteira não é restaurada. A preferência entre páginas usa somente CEP, versão, horário, identificador da consulta e identificador da conta em sessionStorage, com expiração de 30 minutos. Não inclui endereço, token ou dados de pagamento.

Em HTTP, o identificador usa `crypto.getRandomValues` quando `crypto.randomUUID` não está disponível; a ausência deste último não impede salvar a preferência. Com armazenamento bloqueado, a estimativa continua funcionando e a transferência entre páginas não é garantida.

## Validação automatizada

`npm run validate:changed` seleciona os gates por arquivos; `npm run validate:changed:browser` também executa browser. `npm run validate` inclui os gates PHP novos; `npm run validate -- --browser` inclui os cenários browser novos. O runner cria e remove uma conta sintética para o ensaio autenticado. Credenciais temporárias ficam em `.local/041-browser-fixture.json`, ignorado pelo Git, e são removidas no cleanup.

- `validate-041-shipping-destination.php`: geografia de sessão, conta permanente, mesmo CEP, contato isolado, edição parcial e limpeza explícita.
- `validate-041-shipping-preview.php`: limiar nativo de frete grátis com carrinho diferente, quantidade, variação em promoção/peso, produto virtual, contexto de preço por destino e isolamento. **Taxas sintéticas; não prova integração real de transportadora.**
- `validate-041-block-persistence.php`: inserção inicial, bloco já existente e preservação de remoção/texto/imagem/alt do cliente em página sintética.
- `validate-041-editor-browser.mjs`: canvas Gutenberg visível, edição de texto, substituição de mídia/alt, reordenação/remoção e persistência após salvar/recarregar. Usa editor, página e mídias sintéticos, removidos ao terminar; credenciais somente em `.local/041-editor-fixture.json`.
- `validate-041-quote-preference.mjs`: consumo único, geração antiga, expiração, conta, subdiretório da chave, armazenamento bloqueado e HTTP. O teste da chave não equivale a uma instalação real em subdiretório.
- `validate-041-cart-operations.mjs`: contrato público isolado do adaptador, timeout de pendência, erro propagado, tentativa explícita e serialização; não prova integração browser.
- `validate-041-product-quote-browser.mjs`: quantidades 1/2/3, resposta atrasada, taxa vazia, nonce/429/503/offline/timeout e entrada direta no checkout com consumo único da preferência. O transporte de prévia é induzido; o isolamento do cálculo real é verificado pelo gate PHP.
- `validate-041-native-commerce.php`: cupons fixo/percentual, impostos incl/excl, comparação de totais oficiais antes/depois do CEP, dois pacotes com entrega/retirada zero, estoque e vendido individualmente. Métodos e sessão isolados no processo, sem alteração de opções globais ou fornecedores. Não substitui ensaio browser nem transportadora real.
- `validate-041-runner-cleanup.mjs`: funções reais dos runners com dependências simuladas; verifica erro original, tentativas de cleanup/restauração apesar de falhas e limpeza de provisionamento parcial. Não acessa banco ou URLs da loja.
- `validate-041-address-races-browser.mjs`: respostas ViaCEP induzidas fora de ordem, falha/retry, edição manual e invalidação de CEP incompleto.
- `validate-041-checkout-address-browser.mjs`: ordem da referência no DOM/teclado e pares de campos na mesma linha, bairro/número/CEP únicos, ViaCEP automático com CEP da sessão sem input/blur, digitação, reload, número preservado, detalhes salvos diferentes da API preservados no DOM/Store API, e preferência do mesmo CEP com endereço completo. Cobrança independente, alternância/remontagem e ausência de erros React/reconciliação DOM também verificados; demais pageerrors gravados com stack para identificação da origem. Respostas ViaCEP induzidas; desktop e mobile.
- `validate-041-cart-delivery-browser.mjs`: produto físico real, mesma quantidade/CEP na PDP e no Cart, IDs de todos os métodos disponíveis, preços em unidade monetária correta, CEP dentro de Totais, teclado, seleção e total oficial, erro 503 induzido em chamada direta ou batch, rollback/retry e reload em 1440/390 px. Testa a configuração de impostos instalada; não cobre todas as combinações tributárias no browser.
- `validate-041-cart-consistency-browser.mjs`: leitura independente da Store API, duas linhas e quantidade/CEP com latência, visitante/autenticado em 1440 e 390 px. Evidências em `.local/evidence/041-cart-consistency/`.

O resultado de cada execução e a cobertura parcial de T01–T20 ficam no ledger do plano. Arquivos de diagnóstico ou screenshots de execução interrompida não representam aprovação do cenário.

Para repetir o cenário de cobrança obrigatória, usar temporariamente a opção WooCommerce `woocommerce_ship_to_destination=billing_only`, limpar o cache e executar `validate-026-checkout-browser.mjs` com `PETSHOP_DIAG_VIACEP=1` e `PETSHOP_EXPECT_FORCED_BILLING=1`. O segundo parâmetro verifica `forcedBillingAddress` ativo e igualdade dos campos geográficos billing/shipping aceitos. Restaurar a opção anterior e limpar o cache no `finally`; `billing` define somente o padrão e não comprova esse cenário.

## Rollback

Baseline desta continuação: HEAD `fe55572`, branch `codex/041-integridade-frete-carrinho-checkout`, com alterações locais já existentes. Reverter somente os arquivos próprios desta entrega como conjunto compatível de PHP/JS/metadados. Não reinstalar guard de quantidade sobre o fluxo novo.

Antes de publicar, guardar versão de código/assets e os quatro marcadores de migração (destino, bloco v1, wrapper v2 e posição v3). Restaurar a opção anterior apenas se a decisão administrativa ainda corresponder à migração. Restaurar conteúdo da página exige comparar com a edição atual do cliente; o backup inicial não autoriza sobrescrever edições posteriores. Não restaurar tabelas de sessões de compradores.

## Seleção de entrega e desempenho — 09/10/2026

A seleção isolada usa a fila pública sem os dois períodos artificiais de 500 ms; as esperas de sincronização de CEP permanecem. `ShippingRateSelection` substitui temporariamente apenas o callback `lkn_set_shipping_calculation_flag` do Woo Better na rota POST `cart/select-shipping-rate`, quando as duas regras próprias de frete grátis estão desativadas. A guarda roda no cálculo dos totais, após a inicialização nativa da sessão, e mantém o callback se houver oferta antiga com ID `free_shipping_min`/`free_shipping_product` nos dez slots que o fornecedor limpa. Depois da resposta, restaura callback/prioridade/argumentos e remove a guarda. Regras ativas e outras rotas conservam o fluxo original. Nenhum preço/prazo é calculado pelo adaptador; invalidação por mudança do pacote continua nativa.

Gates: `validate-041-shipping-selection-cache.php` via `wp eval` + require (strict_types impede eval-file); `validate-041-delivery-performance-browser.mjs` grava seis trocas reais em `.local/evidence/041-delivery-performance/results.json`. Baseline 3–5 s; execução final 507–715 ms em cinco trocas e 1622 ms em uma. Medição local, não um SLA. Probe temporário de HTTP foi removido; evidência anterior mostra cotation/deadline dos Correios em cada seleção causada pela limpeza incondicional do cache.

Campos adicionais usam atributo público data-petshop-autocomplete e o script define a propriedade DOM autocomplete, evitando aviso React sem perder a semântica de autofill. O aviso useSelect/getValidationError tem origem no hook de validação do WooCommerce instalado e permanece; não foi ocultado nem modificado o fornecedor. Repetir gates de seleção/cache ao atualizar WooCommerce ou Woo Better. As lacunas financeiras do plano permanecem abertas.

## Concorrência no carrinho — 09/10/2026

O middleware público wp.apiFetch da integração Cart Block mantém uma requisição cart por vez e rejeita respostas substituídas com AbortError, inclusive erros com snapshot e retry de nonce. Não aborta a gravação física supondo que isso a reverta: drena o transporte anterior antes de começar o seguinte. Não transforma payloads ou valores. Batch misto e checkout/pagamento passam intactos. A coordenação é nesta página/aba; não é trava global do servidor.

O Woo instalado retorna sem requisição quando quantidade desejada coincide com o valor ainda confirmado, e pode manter o debounce em um valor cujo job foi descartado. Nessas situações, captura do desejo atual pelo atributo nativo data-cart-item-key resgata somente a alteração omitida por update-item oficial; sincroniza o carrinho integral pela ação pública syncCartWithIAPIStore, incluindo nonce/cart hash. Não conserva histórico nem calcula preços. Quantidades e CEP podem ser substituídos; CEP antigo não encerra a pendência nova. Valores monetários só aparecem confirmados após o ciclo vigente e cálculo do CEP digitado, com estado funcional de atualização.

Gates novos: validate-041-cart-request-coordinator.mjs (contratos, erros, nonce e retornos) e validate-041-cart-concurrency-browser.mjs (visitante/autenticado, desktop/mobile, atraso900ms, quantidade/CEP, 1→3→1/3, leitura independente). Executar com fixture041 criada/removida pelos runners. Repetir ambos ao atualizar WooCommerce, especialmente seu early return, debounce e abort de quantidade. O ensaio usa produto1486; divergência financeira antiga com pacote44kg continua sendo critério separado aberto no plano.

As pendências nativas de quantidade substituída só são encerradas após o resultado vigente, impedindo o hook de restaurar o input pelo snapshot antigo. Atualizações de linhas distintas são executadas em ordem; somente seus snapshots intermediários são descartados. A releitura nativa usada para recuperar intenção também passa pela fila única.

## Responsividade de quantidade — §16

A fila consolida alterações ainda não enviadas pela chave da linha. O debounce nativo reúne cliques rápidos; durante uma escrita ativa, somente o último desejo da mesma linha segue depois dela. Cancelamento de transporte não significa reversão de uma escrita já iniciada no servidor: a fila aguarda seu término e descarta sua resposta obsoleta. Linhas distintas e operações de cupom/remoção conservam suas operações. Retry de nonce não pode ressuscitar uma revisão já substituída, mesmo após limpar o estado visual.

`cart-estimate.js` calcula apenas estimativas visuais em unidades monetárias inteiras, com moeda e precisão da resposta oficial. `cart-feedback.js` usa portais React próprios e formatação WooCommerce: preço unitário permanece legível, linha e subtotal mostram estimativa antes dos descontos, entrega e total do pedido aguardam confirmação. O valor confirmado da linha vem de `line_total` e, conforme a configuração, `line_total_tax`; não usa o total geral do pedido. As estimativas nunca alteram a store financeira. O status de atualização aparece após 300 ms e a conclusão é anunciada; nenhuma animação nova é introduzida. A validação pública e o CTA impedem avançar enquanto há quantidade pendente.

Erros reais `invalid_quantity`/`readonly_quantity` sem snapshot exigem releitura integral pela ação pública. Observar também o status de cada operação dentro de respostas batch HTTP 200. Falha de rede conserva o desejo e oferece tentativa explícita; não cria loop de retry. Nova edição durante uma releitura prevalece sobre essa releitura. Scripts de coordenação e feedback não executam na prévia administrativa do Gutenberg.

A guarda do Woo Better na rota `update-item` conserva a primeira invalidação obrigatória e evita a limpeza incondicional seguinte na mesma requisição quando ambas as regras próprias de frete grátis estão desativadas. O hash nativo continua invalidando pacotes diferentes. Isso não garante uma única chamada externa em todos os fluxos: os cálculos nativos ainda podem produzir pacotes distintos. Não reutilizar frete de outra quantidade nem interpretar a medição local como SLA de transportadora.

Validação: `validate-041-cart-estimate.mjs`, `validate-041-cart-request-coordinator.mjs` e `validate-041-cart-performance-browser.mjs`. Este último usa fixture simples/variação/cupom marcada e removida no cleanup, além do produto 1486 para comparação histórica. Registra escrita ativa/final, máximo em voo, estimativa ≤100 ms e pintura após resposta ≤200 ms; rejeição real de estoque, 503/retry, desconto e remoção. Consulte o ledger do §16 para execuções e limites efetivamente verificados. O baseline de 8213 ms inclui a leitura de diagnóstico e não representa tempo de pintura; compare transporte e renderização separadamente.

A rodada ampliada final passou em visitante/autenticado1440/390: digitação sem blur, clique imediato de finalização, foco/loading/reduced-motion, clamp nativo, estoque e vendido individualmente. Focusout reconcilia apenas entrada inválida que exige normalização; não apaga desejo/retry quando o input foi restaurado após falha. Não há debounce adicional: o hook nativo observado tem400ms e o componente de digitação600ms, com flush por blur/Enter. O feedback evita setState sem mudança de targets para manter os efeitos de pendência previsíveis.

Resumo revisável de toda a branch: [problemas, correções e pendências](relatorio-branch-041-problemas-correcoes-pendencias.md). A aprovação desse gate não encerra a divergência financeira pesada nem a regressão browser conjunta após o §16.
