# Plano 028 — Recuperação de pagamento pendente

**Status:** Pendente  
**Data:** 2026-08-22  
**Branch sugerida:** `028-recuperacao-pagamento-pendente`  
**Dependências:** [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) para remetente/domínio/entregabilidade dos e-mails; WooCommerce **11.0 ou superior** (hoje o runtime está em **10.9.4**)  
**Origem:** pedido de recuperar pedidos em **Pagamento pendente** com um aviso controlado e botão para concluir o pagamento, sem instalar plugin de marketing nem criar e-mail extra no `petshop-core`.  
**ClickUp:** [86e2xzfdy](https://app.clickup.com/t/86e2xzfdy) — Open  

## 1. Objetivo

Quando o cliente cria o pedido e **não paga**, a loja envia **um** e-mail: o pedido foi recebido, o pagamento não foi concluído, e há um botão para retomar o pagamento. Sem sequência repetitiva.

User story: como comprador que gerou um pedido de R$ 41,36 e saiu sem pagar, quero receber um único aviso com link para concluir o pagamento, e não quero receber a mesma mensagem várias vezes.

## 2. O que já existe no WooCommerce

O core **não** recupera carrinho sem pedido (itens no carrinho, checkout nunca enviado).

No **10.9.4** (este projeto): pedido em **Pagamento pendente** **não** dispara e-mail automático de “volte e pague”. A fatura com link de pagamento é **manual** (ações do pedido).

A partir do **WooCommerce 11.0** existe o recurso experimental **Abandoned cart recovery** ([documentação oficial](https://woocommerce.com/document/managing-orders/abandoned-cart-recovery-emails/)):

- Liga em **WooCommerce → Configurações → Avançado → Recursos**.
- E-mail em **WooCommerce → Configurações → E-mails → Abandoned cart recovery**.
- **Um** disparo. Automático: **2 horas** se o pedido continuar pendente. Manual: pedido com pelo menos **1 hora**.
- Link **Finish checking out** / pagar o pedido (`$order->get_checkout_payment_url()`).
- Cancela o envio se o status sair de pendente. Não reenvia. Tem descadastro.
- Se AutomateWoo ou MailPoet estiver ativo, o core desliga o aviso para não duplicar.

O nome fala em “carrinho abandonado”, mas o fluxo é o desta loja: **pedido pendente / checkout-draft**, não carrinho sem pedido.

Este plano **usa esse recurso nativo**. Não instala AutomateWoo, MailPoet nem plugin de recovery.

## 3. Escopo comprometido

- Atualizar o WooCommerce do ambiente (Docker/`WOOCOMMERCE_VERSION` e `.wp-env.json`) para **11.0 ou a 11.x estável mais recente validada no runtime**, com gates do storefront e HPOS verdes.
- Ligar **Abandoned cart recovery** e o e-mail correspondente.
- Ligar **Send automatically** (um e-mail, 2 horas, cancelado se pagar).
- Texto em **pt-BR**, editável em **WooCommerce → Configurações → E-mails**: assunto, cabeçalho e conteúdo adicional no sentido “Recebemos o seu pedido, mas o pagamento ainda não foi concluído”, com o botão/link nativo para pagar.
- O link abre a página oficial de pagamento do pedido. Sem URL inventada no `petshop-core`.
- No máximo **um** e-mail de recuperação por pedido. Sem fila extra, sem segundo template no plugin.
- Não instalar plugin de recovery. Não criar classe de e-mail no `petshop-core`.
- Documentar no guia operacional: onde ligar, onde editar o texto, como reenviar na mão, como o descadastro funciona.

### Fora de escopo

- Recuperar carrinho **sem** pedido (visitante que só adicionou ao carrinho).
- Campanha de vários disparos, segmentação ou cupom automático.
- AutomateWoo, MailPoet ou qualquer plugin de marketing.
- Copiar templates internos do WooCommerce para o tema sem necessidade comprovada (o texto administrativo do e-mail nativo basta).
- Configurar SMTP/domínio (017). Sem entregabilidade do 017 o e-mail nativo não chega.
- Alterar Mercado Pago, frete (027), cadastro (025) ou checkout ViaCEP (026).

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Origem | Recurso nativo do WooCommerce 11+, experimental até a Automattic marcar como estável. |
| Gatilho | Pedido em Pagamento pendente (e checkout-draft, se o core incluir no automático/manual). |
| Volume | Um e-mail. Automático em 2 h. Manual pelo pedido depois de 1 h. |
| Conteúdo | Settings de e-mail do WooCommerce; copy inicial em pt-BR; edições do cliente preservadas. |
| Duplicidade | Sem AutomateWoo/MailPoet neste projeto. Se alguém instalar depois, o core suprime o nativo — registrar no guia. |
| Upgrade | Plano específico de versão, exigido pelo 000: só sobe WooCommerce com validação local. |

## 5. Conteúdo administrável

| Item | Origem |
|---|---|
| Assunto, cabeçalho, conteúdo adicional do e-mail de recuperação | **WooCommerce → Configurações → E-mails → Abandoned cart recovery** (rótulo pode aparecer traduzido) |
| Ligar/desligar e envio automático | Mesma tela + **Avançado → Recursos** |
| Remetente e domínio | 017 |

Nenhum texto comercial deste e-mail fica fixo em PHP/CSS/JS.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Versão | `docker/wordpress/Dockerfile` `WOOCOMMERCE_VERSION`, `.wp-env.json`, `scripts/bootstrap-wp-env.mjs` | Fixar WooCommerce ≥ 11.0 |
| Feature | options oficiais do WooCommerce | `abandoned_cart_recovery` / e-mail enabled + automated |
| Copy | settings do e-mail WC | pt-BR inicial, sem sobrescrever edição posterior |
| Docs | guia operacional 013/017 | Como operar o recurso |
| Gates | PHP/WP-CLI + fluxo de pedido pendente | Versão, feature on, um envio, cancelamento se pagar |

Compatibilidade HPOS e Checkout Block permanecem. Não editar o core do WooCommerce.

## 7. Sessões

### Sessão 01 — WooCommerce 11+

- [ ] Subir WooCommerce para 11.0 ou 11.x estável validada.
- [ ] Rodar storefront, HPOS, Checkout Block e personalização 012 sem regressão bloqueante.

**Gate**

- [ ] `wp plugin get woocommerce --field=version` ≥ 11.0 no runtime.
- [ ] Recurso Abandoned cart recovery aparece em Avançado → Recursos.

### Sessão 02 — Ligar e configurar o e-mail nativo

- [ ] Ativar o recurso e o e-mail; ligar envio automático.
- [ ] Gravar assunto/cabeçalho/conteúdo em pt-BR nas settings oficiais, sem sobrescrever se o cliente já editou.
- [ ] Documentar operação e descadastro.

**Gate**

- [ ] Pedido de teste que permanece pendente por 2 h (ou o delay do core) gera **um** e-mail com link de pagamento.
- [ ] Pedido que paga antes do delay **não** recebe o e-mail.
- [ ] Reenvio manual no pedido funciona uma vez e não duplica o automático.
- [ ] Preview/teste do e-mail em pt-BR mostra o sentido da copy acordada.

### Sessão 03 — Handoff

- [ ] Atualizar `Plans/STATUS.md` e o guia de e-mails.
- [ ] Registrar que 017 continua sendo o gate de SMTP.

**Gate**

- [ ] Sem plugin novo de recovery no `plugin list`.
- [ ] Sem template/classe de e-mail nova no `petshop-core`.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Recurso experimental muda de nome/flag | Validar na 11.x instalada; ajustar options sem inventar e-mail próprio |
| Upgrade quebra Blocks/HPOS/012 | Sessão 01 com gates antes de ligar o e-mail |
| E-mail não chega | Bloqueio explícito do 017; este plano não finge SMTP |
| Loja ainda em 10.9.4 | Não implementar recovery custom “enquanto isso”; o ticket começa pelo upgrade |
