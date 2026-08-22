# Plano 029 — Retorno à loja após pagamento Mercado Pago

**Status:** Pendente  
**Data:** 2026-08-22  
**Branch sugerida:** `029-retorno-mercado-pago-pix`  
**Dependências:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) e [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) (Mercado Pago sandbox, webhook, Pix aprovado/pendente/recusado)  
**Origem:** reclamação de compra Pix: o cliente pagou e a tela ficou em `mercadopago.com.br/checkout/v1/payment/redirect` (QR “Falta pouco!”), sem reconhecer o pagamento nem voltar à loja. Pedido: após confirmar, ir para **Meus pedidos**.  
**ClickUp:** [86e2xzgqb](https://app.clickup.com/t/86e2xzgqb) — Open  

## 1. Objetivo

Depois que o Pix (ou cartão no checkout hospedado) for **aprovado**, o cliente volta sozinho para a loja. Logado cai em **Minha conta → Pedidos**. Visitante cai na confirmação do pedido. O link “Voltar para a loja” na tela do Mercado Pago aponta para o mesmo destino.

User story: como comprador que já pagou o Pix no app do banco, quero sair da tela do QR e ver meus pedidos na loja, sem ficar preso no Mercado Pago.

## 2. Baseline

| Superfície | Estado | Problema |
|---|---|---|
| Checkout | Checkout Block + plugin oficial Mercado Pago (013) | Cliente é enviado ao Checkout Pro (`mercadopago.com.br/checkout/v1/payment/redirect`) |
| Pix | QR + “Copiar código”, válido 24 h | O pagamento acontece no app do banco; a página do MP só atualiza quando o status vira aprovado |
| Retorno | Link “Voltar para [loja]” | Após pagar, a tela não redireciona; o pedido na loja pode já ter sido atualizado por webhook sem o cliente perceber |
| Código | `petshop-core` não configura `back_urls` / `auto_return` | Depende do default do plugin e do sandbox do 017 |

Documentação Mercado Pago (Checkout Pro): `back_urls` (`success` / `pending` / `failure`) e `auto_return = approved`. O redirecionamento automático após aprovação leva **até 40 segundos** e não é configurável. `localhost` **não** serve como `back_url`.

## 3. Escopo comprometido

- Preferência de pagamento do Checkout Pro envia `auto_return = approved` e `back_urls` da loja (HTTPS com domínio real ou URL de homologação, nunca `localhost`).
- Pagamento **aprovado** + cliente **logado**: redirecionar para **Minha conta → Pedidos** (`/minha-conta/orders/` ou o endpoint oficial equivalente).
- Pagamento **aprovado** + **visitante**: redirecionar para a página de pedido recebido daquele pedido (`order-received` / chave do pedido). Visitante não tem Meus pedidos.
- Pagamento **recusado**: voltar para a página de pagamento do pedido, para tentar de novo.
- Pagamento **ainda pendente** (QR na tela): o Mercado Pago pode permanecer no QR até aprovar. O botão “Voltar para a loja” leva o visitante à confirmação do pedido pendente e o logado a **Pedidos**.
- Usar settings e filtros públicos do plugin oficial / Store API. **Não editar** o plugin Mercado Pago, WooCommerce ou Blocksy.
- Webhook do 017 continua obrigatório para o status do pedido mudar; este plano não substitui IPN.
- Credenciais continuam fora do Git.

### Fora de escopo

- Trocar Checkout Pro por Checkout Transparente/Bricks só para evitar o redirect.
- Implementar gateway próprio ou copiar SDK do Mercado Pago no `petshop-core`.
- Prometer redirect instantâneo no segundo em que o banco confirma (o MP leva até 40 s após `approved`).
- SMTP (028/017), frete (027), cadastro (025).

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Destino sucesso logado | Minha conta → Pedidos |
| Destino sucesso visitante | Confirmação do pedido WooCommerce |
| Destino falha | URL oficial de pagar o pedido |
| Destino pendente (voltar sem ter pago) | Pedidos (logado) ou confirmação do pedido pendente (visitante) |
| Meio | Checkout Pro já em uso: `back_urls` + `auto_return` |
| Validação | Sandbox Pix: pagar, esperar aprovação, confirmar o redirect e o status HPOS |

## 5. Conteúdo administrável e textos funcionais

A tela do QR é do Mercado Pago. Copy “Falta pouco!” não é nossa.

| Item | Origem |
|---|---|
| Confirmação / Pedidos na volta | WooCommerce + textos funcionais já traduzíveis |
| Mensagem se o pedido ainda estiver pendente ao voltar | Tradução/`__()` do `petshop-core` se a loja precisar de uma linha extra na confirmação |

Sem texto comercial novo hardcoded.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Preferência | filtro/hook oficial do plugin MP no `petshop-core` | `back_urls` e `auto_return` |
| Destinos | rotas 013 (`/minha-conta/`, pedido recebido) | URLs absolutas HTTPS |
| Status | webhook Mercado Pago (017) | Pedido HPOS aprovado antes ou junto do return |
| Gates | fluxo sandbox + script se couber | Redirect e status sem pedido duplicado |

## 7. Sessões

### Sessão 01 — Contrato de retorno

- [ ] Mapear no plugin oficial onde a preference recebe `back_urls` / `auto_return`.
- [ ] Definir as três URLs (sucesso, pendente, falha) conforme a seção 4.
- [ ] Recusar `localhost` no valor enviado ao MP.

**Gate**

- [ ] Preference criada no sandbox contém `auto_return = approved` e as três `back_urls` da loja.

### Sessão 02 — Fluxo Pix aprovado

- [ ] Pagar Pix sandbox e esperar o MP marcar aprovado.
- [ ] Confirmar redirect automático (até 40 s) ou o botão Voltar para o destino da seção 4.
- [ ] Pedido HPOS fica pago, sem duplicar.

**Gate**

- [ ] Logado: após aprovação, a loja abre Pedidos e o pedido aparece pago.
- [ ] Visitante: após aprovação, abre a confirmação daquele pedido.
- [ ] Recusado: volta para pagar o mesmo pedido.

### Sessão 03 — Handoff

- [ ] Documentar no guia operacional: webhook + return URL + limite de 40 s.
- [ ] Atualizar `Plans/STATUS.md`.

**Gate**

- [ ] Sem alteração no plugin Mercado Pago versionado.
- [ ] Sem credencial no repositório.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Pix aprovado no banco e QR ainda aberto | `auto_return` após `approved` (até 40 s) + webhook atualizando o pedido |
| Homologação em localhost | Usar túnel/domínio; MP recusa localhost |
| Plugin sem filtro estável | Usar o hook público documentado da versão instalada; não fork |
| Visitante sem conta | Confirmação com `order_key`, não Pedidos |
