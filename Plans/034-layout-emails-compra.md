# Plano 034 — Layout dos e-mails de compra

**Status:** Concluído  
**Data:** 2026-08-24  
**Branch sugerida:** `034-layout-emails-compra`  
**Dependências:** [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md) (tokens); [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) (pedido, rastreio em e-mail); [023-rodape-institucional-editavel.md](./023-rodape-institucional-editavel.md) (canais de atendimento no rodapé)  
**Origem:** mockup de “Pagamento confirmado” (2026-08-24). O PNG define **composição**; as cores vêm da identidade atual da loja, não dos hex impressos no rodapé da arte (`#5bc1c3`, `#f37d35`, `#e6e7e9`).  
**Referência visual:** [docs/referencias/034-email-pagamento-confirmado.jpg](../docs/referencias/034-email-pagamento-confirmado.jpg)  
**ClickUp:** [86e2yypv9](https://app.clickup.com/t/86e2yypv9) — Open  

## 1. Objetivo

Os e-mails HTML do WooCommerce passam a usar o casco da referência como padrão global: logo, saudação quando houver pedido, título, conteúdo do e-mail e rodapé de marca — com a paleta AUTeliê já aplicada no site. Nos e-mails de compra, o casco inclui também caixa de status, resumo do pedido, linha do tempo, CTA e caixas de apoio.

User story: como comprador, quero abrir o e-mail de pagamento confirmado (e os demais avisos do pedido) e reconhecer a mesma loja que vi no site, com o resumo do pedido e um botão para acompanhar.

## 2. Baseline

| Superfície | Estado | Problema |
|---|---|---|
| E-mails WooCommerce | Template padrão (tabela, cor base do WC, cabeçalho genérico) | Não segue o layout da referência nem os tokens 014 |
| Identidade no site | Tokens em `petshop-theme/style.css` e `docs/guia-identidade-visual-autelie.md` | O PNG usa teal/laranja diferentes (`#5bc1c3` / `#f37d35`) |
| Conteúdo do pedido | `WC_Order` + `woocommerce_email_order_details` + `OrderTracking` | Manter dados reais; só mudar o cromado |
| 030 | Frase na **página** de pedido recebido | Não cobre e-mail |
| 028 | E-mail nativo de pagamento pendente (WC 11+) | Trigger e copy da recuperação ficam no 028 |
| 017 | Remetente, domínio, SMTP | Entregabilidade não é deste plano |

Exceção documentada: e-mail transacional **não** é página Gutenberg. Edição = WooCommerce → E-mails + Personalizar (caixas globais) + Identidade do site (logo) + rodapé 023 (canais).

## 3. Escopo comprometido

O casco HTML global (largura 600 px, tabelas, CSS inline, logo/nome da loja e rodapé de marca) vale para todos os `WC_Email` HTML registrados pelo WooCommerce. A composição completa de compra vale para estes IDs WooCommerce:

| ID | Título inicial (heading) | Tracker |
|---|---|---|
| `customer_processing_order` | Pagamento confirmado! | Passo 1 atual |
| `customer_completed_order` | Pedido concluído! | Passo 4 atual; 1–3 preenchidos |
| `customer_on_hold_order` | Recebemos o seu pedido | Sem tracker |
| `customer_invoice` | Fatura do pedido | Sem tracker |
| `customer_cancelled_order` | Pedido cancelado | Sem tracker |
| `customer_refunded_order` | Reembolso confirmado | Sem tracker |
| `customer_failed_order` | Não foi possível confirmar o pagamento | Sem tracker |
| `customer_note` | Atualização do seu pedido | Sem tracker |

Composição obrigatória (de cima para baixo), alinhada ao mockup:

1. Filete superior teal (`brand-teal-700` `#126E70`).
2. Logo centralizado da **Identidade do site**. Sem logo, o nome da loja em texto.
3. Saudação `Olá, {primeiro nome}!` em teal-700. Sem nome, `Olá!`.
4. Heading do e-mail (valores iniciais da tabela acima) em `neutral-950`.
5. Conteúdo adicional do WooCommerce como subtítulo em `neutral-700`.
6. Caixa de status com ícone de confirmação (teal-700), **Pedido #{número}** e data do pedido.
7. **Resumo do pedido:** itens reais (nome, qtd, preço, variação), subtotal, entrega, total e método de pagamento. Total em `brand-orange-500` `#F47721` (destaque gráfico, não CTA).
8. **Próximos passos:** quatro nós — Pagamento confirmado → Separação / produção → Pedido enviado → Pedido concluído. Passo atual em `brand-orange-action`; concluídos em teal-700; futuros em `neutral-300`. Só nos e-mails da tabela que marcam tracker.
9. CTA preenchido `#C94B0B` com texto branco, **não** em caixa alta integral:
   - `customer_invoice` e `customer_on_hold_order`: **Pagar agora** → `$order->get_checkout_payment_url()`.
   - Demais IDs da lista: **Acompanhar meu pedido** → URL de visualização do pedido (conta ou chave de pedido).
10. Apoio funcional: “Você também pode acompanhar tudo pela área Minha conta.”
11. Caixa **Informação importante** (Personalizar). Vazio oculta.
12. Caixa **Precisa de ajuda?** com texto administrável + WhatsApp e/ou e-mail do rodapé 023 quando preenchidos.
13. Rodapé em bloco `brand-teal-900` `#004F50`, texto branco: nome da loja, tagline do 023 (`petshop_footer_description`), URL de `home_url()`. Sem a frase “Layout de referência para desenvolvimento”. Sem domínio inventado.

Cores — o PNG **não** dita hex. Mapear assim:

| Papel no mockup | Token da loja |
|---|---|
| Teal de títulos, ícones, filete | `brand-teal-700` `#126E70` |
| Teal de apoio / caixa clara | `brand-aqua-400` `#58C2C7` em baixa opacidade sobre branco |
| Laranja de CTA | `brand-orange-action` `#C94B0B` + branco |
| Laranja de total | `brand-orange-500` `#F47721` |
| Fundo do rodapé de marca | `brand-teal-900` `#004F50` |
| Texto, borda, fundo | `neutral-950` / `neutral-700` / `neutral-300` / `neutral-100` / branco |

Tipografia: Nunito Sans com fallback `Arial, Helvetica, sans-serif` (clientes de e-mail bloqueiam webfont). Não carregar Google Fonts no HTML do e-mail.

Migração grava headings e conteúdos adicionais iniciais em **WooCommerce → Configurações → E-mails** e **não sobrescreve** edição posterior. `OrderTracking` continua depois da tabela quando houver transportadora/código/URL.

### Fora de escopo

- SMTP, domínio, remetente e entregabilidade (017).
- Trigger, prazo e copy da recuperação de pagamento pendente (028).
- Frase da **página** de pedido recebido (030).
- E-mails de administrador (`new_order` e afins) e de conta (nova conta, redefinir senha).
- Redesign do HTML em texto puro (plain text permanece o do WooCommerce).
- Novo status “enviado”, AutomateWoo, MailPoet ou builder de e-mail.
- Copiar arquivos internos do plugin WooCommerce; Blocksy; Elementor.
- Inventar prazo de produção, CNPJ, endereço ou URL que não existam nas settings.
- Pixel-perfect de fonte/espaçamento contra o JPG; paleta nova no site.
- ViaCEP (não há CEP neste fluxo).

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Referência | Composição do JPG; cores do guia 014 / `style.css` |
| Casco | Um wrapper no `petshop-core` filtrado pelos IDs da lista |
| Heading / adicional | Settings nativos de cada e-mail WooCommerce |
| Logo | Identidade do site (`custom_logo`) |
| Ajuda | Customizer + canais 023 |
| Informação importante | Customizer; vazio oculta; inicial sem prazo inventado: “Pedidos com item personalizado seguem o prazo descrito na página do produto.” |
| CTA | Sentence case; laranja-action |
| Texto branco no laranja-500 | Proibido (contraste). CTA só no action |

## 5. Conteúdo administrável

| Item | Origem |
|---|---|
| Logo | Aparência → Personalizar → Identidade do site |
| Assunto, heading, conteúdo adicional de cada e-mail da lista | WooCommerce → Configurações → E-mails |
| Informação importante (título + corpo) | Personalizar → conteúdo da loja / seção de e-mails |
| Precisa de ajuda? (corpo) | Mesmo Personalizar |
| WhatsApp e e-mail na caixa de ajuda | Personalizar → Rodapé da loja (023) |
| Tagline do rodapé do e-mail | `petshop_footer_description` (023) |
| Nome da loja e URL | Ajustes do WordPress / `home_url()` |
| Saudação, rótulos do resumo, passos do tracker, CTAs, “Minha conta” | Tradução/`__()` do `petshop-core` |
| Itens, preços, frete, pagamento, rastreio | Pedido WooCommerce + `OrderTracking` |

Nenhum texto comercial novo fica fixo em PHP/CSS/JS depois do primeiro provisionamento.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Casco e CSS de e-mail | `petshop-core` (`WooCommerce\TransactionalEmails` ou equivalente) | Header, footer, status, tracker, CTA, caixas; `woocommerce_email_styles`; markup em tabelas |
| Dados | `WC_Order` + hooks oficiais de e-mail | Totais e itens; não recalcular regra de preço |
| Settings | `DefaultSettings` + Customizer | Caixas globais; persistência |
| Heading WC | option dos e-mails nativos | Valores iniciais; não sobrescrever |
| Gates | `scripts/validate-034-emails.php` | HTML gerado dos IDs da lista: tokens, estrutura, CTA, ocultar caixa vazia, ausência de `#5bc1c3` e `#f37d35` |

Não editar Core, WooCommerce ou Blocksy. Override de `email-header.php` / `email-footer.php` só no plugin/tema filho se os hooks não derem o casco.

## 7. Sessões

### Sessão 01 — Casco e pagamento confirmado

- [x] Wrapper HTML + CSS com tokens 014 aplicado globalmente aos `WC_Email` HTML.
- [x] `customer_processing_order` com a composição da referência (passos 1–13 aplicáveis).
- [x] Heading/adicional iniciais provisionados sem sobrescrever.

**Gate**

- [x] HTML do processing contém logo ou nome da loja, “Pagamento confirmado!”, Pedido #{id}, resumo com total, tracker com passo 1 atual, CTA “Acompanhar meu pedido” com URL do pedido.
- [x] CSS do e-mail usa `#126E70`, `#C94B0B` e `#004F50`; **não** contém `#5bc1c3` nem `#f37d35`.

### Sessão 02 — Demais atualizações de compra

- [x] Aplicar o casco global aos demais e-mails WooCommerce e a composição de compra aos outros IDs da lista, com heading e regra de tracker/CTA da tabela.
- [x] Caixas Informação importante e Precisa de ajuda? com persistência; vazio oculta só a de informação.
- [x] Documentar no guia operacional onde editar heading, caixas e logo.

**Gate**

- [x] Cada `WC_Email` HTML recebe o casco global; cada ID da lista gera HTML com a composição de compra; invoice e on-hold usam “Pagar agora” e URL de pagamento.
- [x] completed mostra os quatro passos preenchidos.
- [x] Alterar heading no WooCommerce e o texto da caixa no Personalizar sobrevive a migrate/reprovisionar.
- [x] Atualizar `Plans/STATUS.md`.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Outlook ignora `border-radius` no tracker | Tabela de 4 colunas; círculos degradam para quadrados sem quebrar a ordem |
| Cor base do WC sobrescreve o casco | Filtrar `woocommerce_email_styles` e não depender da “cor base” do painel para o layout |
| Preview admin ≠ HTML enviado | Gate gera o e-mail pela API `WC_Email`, não só screenshot do Customizer |
