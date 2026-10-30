# Comparação da divergência de frete — Plano 041

Diagnóstico local em 08/10/2026, WordPress 7.0.2 / WooCommerce 10.9.4. Duas reproduções autenticadas com contas sintéticas novas confirmaram a divergência. Não é evidência de staging ou de preço comercial correto.

## Entradas comparadas

O par comparado é a resposta recebida pelo Cart Block e um GET independente na mesma sessão autenticada. Não é uma comparação entre pedidos diferentes. A chamada HTTP não é idêntica: a alteração usa POST (incluindo `/wc/store/v1/batch`) e a conferência usa GET `/wc/store/v1/cart`.

| Campo | Resposta da alteração / store | GET independente |
|---|---|---|
| Produtos / variações | 2406 / 0; 2405 / 0 | Mesmos IDs e chaves de linha |
| Quantidades | 3; 1 | 3; 1 |
| Peso unitário cadastrado | 11 kg; 11 kg | 11 kg; 11 kg |
| Peso total dos produtos | 44.000 g | 44.000 g |
| Dimensões unitárias | 10 × 10 × 10 cm, ambos | Idênticas |
| Classe de entrega | 0, ambos | Idêntica |
| Valor dos itens | R$ 84,40 | R$ 84,40 |
| Destino de entrega e cobrança | Endereço sintético completo SP | Impressões SHA-256 normalizadas idênticas |
| Sessão WooCommerce | Conta sintética do ensaio | Mesma impressão da identidade da sessão |

Os campos observados dos pacotes de entrada também coincidem: IDs/chaves/variações, quantidade, peso/dimensões, classe, valor das linhas, `formatted_data` ausente, custo do conteúdo e impressão do destino. Não confundir peso total dos produtos com peso tarifável/cubado ou corpo efetivamente enviado à API externa: esses últimos ainda precisam ser capturados. Os produtos são fixtures; 11 kg não foi inferido de um produto comercial.

Visitante e autenticado não têm endereço completo idêntico por construção: o visitante informa CEP, enquanto a conta sintética tem endereço salvo. No caso autenticado reproduzido, consultar o mesmo CEP preserva o endereço completo. Portanto, os dois contextos não devem ser usados como par equivalente sem controlar essa diferença.

## Primeira diferença observada

| Etapa | Sedex `virtuaria-correios-sedex:2` | Jadlog `5` / `melhorenvio_jadlog_package` | Seleção / frete total |
|---|---|---|---|
| POST `cart/update-item` (3 + 1 unidades) | R$ 20,48 | R$ 248,95 | Sedex / R$ 20,48 |
| POST `batch`, recebido pelo Cart Block | R$ 263,62 | R$ 248,95 | Jadlog / R$ 248,95 |
| GET independente `cart` | R$ 20,48 | R$ 248,95 | Sedex / R$ 20,48 |
| Segundo GET independente | R$ 20,48 | R$ 248,95 | Sedex / R$ 20,48 |

O store permanece em R$ 248,95 depois dos GETs e de mais três segundos. O GET independente é diagnóstico e não aplica resposta ao store. A divergência não foi explicada por produtos, quantidades, peso cadastrado ou endereço diferentes. Há mudança da própria taxa Sedex, seguida de mudança do método escolhido. Ainda não há prova de qual preço é correto nem da causa da mudança; cache, contexto de cálculo, empacotamento efetivo e resposta externa devem ser rastreados antes de corrigir.

## Método e evidências

Observer PHP temporário, instalado somente no runtime local, sem alteração de taxas. O observer só registrou requisições com marcador de diagnóstico e usuário igual à fixture 041. Capturou `woocommerce_cart_shipping_packages`, pacotes calculados e estado ao encerrar cada requisição; endereço e identidade de sessão foram comparados por hash, sem registrar endereço completo/cookie/token. O observer foi removido após a captura.

Arquivos ignorados pelo Git:

- `.local/evidence/041-cart-consistency/comparison-browser.json`: respostas e snapshots antes/depois dos GETs.
- `.local/evidence/041-cart-consistency/comparison-server.jsonl`: pacotes, pesos, destino resumido, taxas e escolha.
- `.local/diag-041-cart-consistency.mjs` e `.local/probe-041.php`: ferramentas locais reproduzíveis do diagnóstico.

Na primeira captura, o marcador do browser não acompanhou automaticamente `page.request`; por isso ela não comprova o pacote interno do GET. A segunda captura enviou o marcador explicitamente também nas requisições independentes e confirmou os campos de entrada iguais. Não somar capturas como execuções aprovadas do gate financeiro.

O wrapper restaurou `home` e `siteurl`, limpou cache e removeu a conta/arquivo de credenciais. Não houve alteração de código de produção, peso de produtos, regras de transportadoras ou fornecedor nesta investigação.

Próxima investigação: correlacionar os subendpoints do batch, hash/hit de cache WooCommerce e entrada/saída sanitizada da consulta externa Sedex. Conferir peso tarifável e dimensões empacotadas, origem, serviço e valor declarado em cada cálculo, sem registrar credenciais ou dados pessoais. Manter a comparação financeira do gate e o plano em andamento.
