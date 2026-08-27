# Plano 027 — Calculadora de frete única (hub)

**Status:** Concluído
**Data:** 2026-08-22  
**Branch sugerida:** `027-calculadora-frete-hub`  
**Dependências:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) (calculadora da PDP e Virtuaria); [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) para credenciais reais de produção  
**Origem:** a PDP mostra duas calculadoras (a nossa e a do plugin). Depois de calcular, o preço da nossa sai com entidade HTML em vez de `R$`. Reprodução confirmada em 2026-08-22 no produto Ruivo, CEP `94010450`: `SEDEX: &#82; &#36;&nbsp;27,00` e `PAC: &#82; &#36;&nbsp;34,70`. A loja vai usar Virtuaria Correios e Melhor Envio; a UI de frete da loja precisa ser uma só e consultar todos os métodos WooCommerce ativos.  
**ClickUp:** [86e2xzf9w](https://app.clickup.com/t/86e2xzf9w) — Open  

## 1. Objetivo

A calculadora **Calcular entrega** do `petshop-core` é a única UI de CEP de frete na PDP. Ela pergunta ao WooCommerce e lista todas as cotações ativas. Widgets de Virtuaria e Melhor Envio na PDP e no carrinho saem da vitrine.

User story: como comprador, quero informar o CEP uma vez na página do produto, ver todos os métodos de entrega cadastrados e ativos no WooCommerce numa lista só, e seguir no carrinho/checkout com o mesmo CEP, sem segunda calculadora na tela.

## 2. Baseline atual

| Superfície | Estado | Problema |
|---|---|---|
| PDP — nossa | `ProductDetails::calculateShipping()` chama `WC()->shipping()->calculate_shipping()` | Já é um hub. `wc_price()` devolve HTML (`&#82;&#36;&nbsp;27,00`); `wp_strip_all_tags()` não decodifica; o JS grava em `textContent` e o comprador lê o código |
| PDP — plugin | Virtuaria (e o Melhor Envio, quando ativo) injeta calculadora própria | Duas UIs, dois botões, dois resultados |
| Destino | Pacote só com país BR e CEP | Sem cidade/UF; alguns métodos cotam melhor com destino mais completo |
| Carrinho/checkout | Cart/Checkout Blocks usam métodos da zona | Widgets extras dos plugins competem com o frete nativo |
| Fornecedores | 013 elegeu Virtuaria para Correios | Melhor Envio ainda não está registrado em plano |

A calculadora de frete **não** usa ViaCEP (`.cursor/rules/viacep-address.mdc`).

## 3. Escopo comprometido

- Uma única calculadora na PDP: a seção `petshop-shipping-calculator`.
- O cálculo usa a API oficial de envio do WooCommerce e devolve **todas** as taxas cadastradas e ativas na zona de entrega para o CEP informado.
- **Sem filtro por transportadora:** Correios, Melhor Envio, Virtuaria e qualquer outro método ativo retornado pelo WooCommerce aparecem na calculadora, sem descartar PAC, SEDEX, Mini Envios ou equivalentes quando estiverem cadastrados e ativos.
- **Melhor Envio** entra neste plano: instalar o plugin oficial, ativar na zona de entrega, sem versionar token/credencial, e listar todos os serviços habilitados no painel.
- Esconder as calculadoras de PDP/carrinho da Virtuaria e do Melhor Envio por setting oficial do plugin; se o setting não existir ou for ignorado, remover via hook do `petshop-core` ou CSS do child theme. Não editar o código dos plugins.
- Carrinho e checkout mostram só o seletor nativo de frete do WooCommerce/Blocks. Sem widget extra de plugin.
- CEP informado na PDP grava no cliente/sessão WooCommerce e hidrata o cálculo do carrinho e do checkout.
- Resultado da PDP: lista com nome do método, preço em real brasileiro **legível** (exemplo: `SEDEX: R$ 27,00`) e prazo quando o método enviar prazo. Prazo de produção do produto continua separado (013).
- **Bug visual do preço (obrigatório):** depois de Calcular entrega, o texto visível **não** contém entidade HTML. São inválidos `&#82;`, `&#36;`, `&nbsp;` e qualquer sequência `&#` + número. Causa: `ProductDetails` serializa `wp_strip_all_tags(wc_price($cost))` e `product-experience.js` faz `item.textContent = label + cost`. Correção: enviar valor numérico + texto já formatado sem HTML, ou formatar no JS. Nunca `wc_price()` dentro de `textContent`.
- CEP inválido ou nenhuma taxa: mensagem em pt-BR e canal de atendimento, sem valor inventado.
- Fallback `flat_rate` local do 013 continua só em `local`/`development` e some quando houver método real.

### Fora de escopo

- ViaCEP nesta calculadora (CEP aqui é cotação, não formulário de endereço).
- Plugin extra “Calculadora de Frete e Campos Checkout para o Brasil”.
- Trocar Virtuaria por outro Correios; editar Virtuaria, Melhor Envio, WooCommerce ou Blocksy.
- Credenciais de produção, contrato Correios e go-live de frete (017).
- Prefill de endereço do checkout (026), senha temporária (025) e personalizador (012).

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Hub | `petshop-core` chama `WC()->shipping()->calculate_shipping()`. Não chama API Correios nem Melhor Envio direto. |
| Transportadoras | Exibir todos os métodos cadastrados e ativos retornados pelo WooCommerce para a zona do CEP, sem excluir por `method_id`, rótulo ou fornecedor. |
| Melhor Envio | Plugin `melhor-envio-cotacao` (ou o slug oficial vigente) registrado neste plano. Token só no ambiente, nunca no Git. |
| Superfícies | PDP = nossa UI. Carrinho = Blocks nativos, widgets de plugin ocultos. Checkout = Blocks nativos. |
| CEP | Um CEP na PDP segue para carrinho e checkout. |
| Preço | Formato visível `R$ 27,00` (vírgula decimal BR). Sem entidade HTML. Gate: o nó do resultado não contém `&#`. |
| Lista | Todas as taxas ativas retornadas, sem “escolher a mais barata” no servidor. |

## 5. Plugin novo (registro obrigatório)

| Plugin | Motivo | Onde configura |
|---|---|---|
| Melhor Envio (cotação oficial) | Serviços de entrega habilitados no painel, incluindo Correios quando estiver cadastrado e ativo | WooCommerce → Entrega + painel Melhor Envio; credencial fora do repositório |

Virtuaria Correios já está aprovada no 013. Este plano **não** a substitui.

## 6. Conteúdo administrável e textos funcionais

Exceção documentada: calculadora é hook de produto, não bloco editorial.

| Item | Origem |
|---|---|
| Título “Calcular entrega”, rótulo CEP, botão, vazios e erros | Tradução/`__()` do `petshop-core` |
| Nomes e prazos das taxas | Virtuaria / Melhor Envio / WooCommerce |
| Copy da página Entrega e frete | Gutenberg (013/017) |

## 7. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Hub | `ProductDetails` + classe pequena (ex.: `ShippingQuotes`) | Montar pacote, calcular e serializar todas as taxas ativas retornadas pelo WooCommerce |
| Persistência de CEP | sessão / `WC_Customer` | PDP → carrinho → checkout |
| Ocultar widgets | hooks `petshop-core` + CSS do tema | PDP e carrinho |
| Front | `product-experience.js` | Lista acessível, preço sem entidade HTML |
| Melhor Envio | plugin de terceiro, só configuração | Métodos na zona, calculadora dele desligada |
| Gates | `scripts/validate-027-*.php` e browser | uma UI, todos os métodos ativos, persistência de CEP, preço legível |

## 8. Sessões

### Sessão 01 — Uma UI e preço legível

- [x] Remover da PDP a calculadora da Virtuaria (e a do Melhor Envio quando existir).
- [x] Corrigir o preço: o comprador lê `SEDEX: R$ 27,00` / `PAC: R$ 34,70` (valores da cotação), nunca `&#82;`, `&#36;` ou `&nbsp;`.
- [x] Listar nome, preço e prazo quando o método enviar prazo.

**Gate**

- [x] Na PDP há um único bloco “Calcular entrega”.
- [x] CEP `94010450` (ou outro CEP válido com taxa) mostra `R$` + valor; o HTML/texto do resultado não contém `&#`.

### Sessão 02 — Hub e Melhor Envio

- [x] Registrar e instalar Melhor Envio no ambiente, sem credencial no Git.
- [x] Ativar na zona os métodos de entrega definidos para operação, incluindo os serviços Correios do Melhor Envio quando cadastrados.
- [x] Listar todas as cotações ativas retornadas pelo WooCommerce, sem filtro por transportadora, serviço ou rótulo.
- [x] Esconder widgets do Melhor Envio e da Virtuaria no carrinho.

**Gate**

- [x] CEP válido na PDP mostra todos os métodos ativos configurados para a zona, incluindo Virtuaria, Melhor Envio, PAC, SEDEX e demais serviços quando retornados pelo WooCommerce.
- [x] Nenhuma taxa ativa retornada pelo WooCommerce é removida por conter Correios, PAC, SEDEX, Melhor Envio ou Virtuaria no rótulo.
- [x] Carrinho e checkout não mostram a calculadora extra do plugin.

### Sessão 03 — CEP persistente e handoff

- [x] CEP da PDP hidrata carrinho e checkout.
- [x] Gates PHP/browser; atualizar guia operacional e `Plans/STATUS.md`.

**Gate**

- [x] Depois de calcular na PDP, o mesmo CEP chega no carrinho e no checkout.
- [x] Reprovisionar não religa widget de plugin nem o `flat_rate` em produção.

## 9. Riscos

| Risco | Mitigação |
|---|---|
| Melhor Envio reinsere a calculadora | Setting oficial + hook/CSS; gate visual |
| `method_id` muda entre versões | Não depender de ids ou rótulos para excluir taxas; serializar o que o WooCommerce retornar como ativo |
| Token no repositório | Só `.env`/painel; recusar commit |
| Pacote sem cidade/UF | Manter só CEP (exceção ViaCEP); se um método exigir UF, usar o estado que o próprio método aceitar com CEP, sem ViaCEP nesta tela |

## 10. Evidência de entrega

- Branch: `codex/027-calculadora-frete-hub`.
- Runtime: `docker compose build wordpress node`; `docker compose up -d --force-recreate --wait wordpress`; cópia de `petshop-core` e `petshop-theme` da imagem para o volume WordPress.
- Melhor Envio: `wp plugin install melhor-envio-cotacao --activate` instalou e ativou a versão 2.16.6 no runtime local, sem token ou arquivo versionado.
- Gates: `npm run validate:changed`; `npm run validate:changed:browser`.
- Evidência browser: `.local/evidence/027/shipping-hub-checkout.png`.
