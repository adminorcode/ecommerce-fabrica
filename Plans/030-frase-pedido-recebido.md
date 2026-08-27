# Plano 030 — Frase da confirmação de pedido

**Status:** Implementado na branch
**Data:** 2026-08-22  
**Branch sugerida:** `030-frase-pedido-recebido`  
**Dependências:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) (página de pedido recebido / Checkout Block)  
**Origem:** pedido do cliente (Ruivo): trocar “Obrigado. Seu pedido foi recebido.” por **“Parabéns! Seu pedido foi recebido!”** na confirmação (pedido 2904 na referência).  
**ClickUp:** [86e2xzmck](https://app.clickup.com/t/86e2xzmck) — Open  

## 1. Objetivo

Na página de pedido recebido, o título passa a ser **Parabéns! Seu pedido foi recebido!** — e o cliente da loja consegue alterar essa frase no painel sem editar código.

User story: como lojista, quero que o comprador leia “Parabéns! Seu pedido foi recebido!” ao finalizar, e quero poder mudar essa frase depois no WordPress.

## 2. Baseline

| Superfície | Estado | Problema |
|---|---|---|
| Pedido recebido | WooCommerce clássico: “Obrigado. Seu pedido foi recebido.” (`woocommerce_thankyou_order_received_text` / tradução pt_BR) | Copy pedida pelo cliente não aparece |
| Confirmação do Checkout Block | Bloco `woocommerce/order-confirmation` na página Finalizar compra | Pode repetir o mesmo texto nativo; precisa da mesma frase |
| `petshop-core` | `GuestAccount` e rastreio no `thankyou`; sem filtro dessa frase | Nada provisiona o texto comercial |

## 3. Escopo comprometido

- Valor inicial provisionado: **Parabéns! Seu pedido foi recebido!**
- A frase aparece na confirmação clássica e na confirmação do Checkout Block.
- O cliente edita o texto em **Aparência → Personalizar** (campo global desta loja). Migração grava o inicial e **não sobrescreve** edição posterior.
- Filtros oficiais do WooCommerce (`woocommerce_thankyou_order_received_text` e o equivalente do bloco de confirmação). Sem copiar template do WooCommerce ou do Blocksy.
- Persistência: alterar no Personalizar → reprovisionar → a alteração permanece.

### Fora de escopo

- Redesign da tabela de pedido, totais, método de pagamento ou header do checkout (020).
- E-mails transacionais, retorno Mercado Pago (029) e recovery (028).
- Trocar outras strings “Obrigado” da loja.

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Frase inicial | `Parabéns! Seu pedido foi recebido!` |
| Onde edita | Customizer (`petshop_store_content` ou seção equivalente já usada para textos globais). Exceção documentada: a confirmação é hook WooCommerce, não bloco editorial da Home. |
| Vazio | Campo obrigatório na UI; vazio no painel volta ao valor inicial provisionado, sem cair no “Obrigado.” nativo. |

## 5. Conteúdo administrável

| Rota | Item | Origem |
|---|---|---|
| `/finalizar-compra/order-received/` e confirmação do bloco | Título “pedido recebido” | Personalizar → conteúdo da loja |

Sem imagem. Sem texto fixo em PHP/CSS/JS depois do primeiro provisionamento.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Setting | `DefaultSettings` (`petshop_order_received_text`) + Customizer `petshop_store_content` | theme_mod, sanitizar vazio → frase inicial, não sobrescrever |
| Render | `Petshop\Core\WooCommerce\OrderReceivedMessage` | `woocommerce_thankyou_order_received_text` (clássico e bloco `order-confirmation-status`); só substitui a string nativa de “pedido recebido”, sem alterar recusado/cancelado |
| Docs | `docs/guia-edicao-home.md`, `docs/guia-operacional-woocommerce-plano-013.md` | onde editar a frase |
| Gates | `scripts/validate-030.php`, `scripts/validate-030-order-received-browser.mjs` | texto inicial, bloco e clássico, persistência |

O Checkout Block usa o mesmo filtro oficial na classe `OrderConfirmation\Status`. Não há filtro equivalente separado para copiar.

## 7. Sessão única

- [x] Provisionar a frase inicial no setting administrável.
- [x] Aplicar na confirmação clássica e no Checkout Block.
- [x] Documentar no guia; gates de persistência.

**Gate**

- [x] Pedido de teste mostra **Parabéns! Seu pedido foi recebido!** no lugar de “Obrigado. Seu pedido foi recebido.”
- [x] A mesma frase aparece com Checkout Block.
- [x] Editar no Personalizar sobrevive a migrate/reprovisionar.
- [x] WooCommerce e Blocksy não foram copiados nem editados.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Bloco ignora o filtro clássico | Usar o filtro/extensão documentada do order-confirmation; gate nos dois renders |
| Translate Press / gettext sobrescreve | Setting da loja tem prioridade sobre a string nativa |

## 9. Validação (2026-08-26)

- PHPUnit: 32 testes, 75 asserções.
- `wp eval-file scripts/validate-030.php` — sucesso.
- `validate-030-order-received-browser.mjs` — sucesso; evidência em `.local/evidence/030/order-received.png`.
- Commit, PR, merge e ClickUp permanecem pendentes de pedido explícito.
