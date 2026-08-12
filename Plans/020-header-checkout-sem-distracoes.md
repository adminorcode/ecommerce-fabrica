# Plano 020 - Header de checkout sem distracoes

**Status:** Concluido

**Data:** 2026-08-12

**Branch sugerida:** `020-header-checkout-sem-distracoes`

**Dependencias:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md), [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) e checkout com WooCommerce Blocks preservado.

**Origem:** avaliacao visual do checkout em 2026-08-12 e comparacao com padroes de checkout de e-commerces: header reduzido e comum, mas precisa parecer intencional, confiavel e consistente com a marca.

## 1. Objetivo

Transformar a navegacao reduzida do checkout em um header proprio de compra, em vez de uma versao incompleta da navbar da loja.

O checkout deve reduzir distracoes sem perder confianca, orientacao ou acesso a atendimento. A experiencia final deve comunicar que o cliente entrou em um fluxo de compra seguro, linear e separado da navegacao comercial normal.

## 2. Decisao de UX

Manter header reduzido no checkout.

Nao voltar com menu completo, busca, wishlist, conta ou minicarrinho no checkout. Esses elementos aumentam as saidas laterais do fluxo e competem com o objetivo principal: concluir o pedido.

Substituir a navbar "amputada" atual por um header de checkout com composicao propria:

| Posicao | Conteudo | Origem administrativa |
| --- | --- | --- |
| Esquerda ou centro visual | Logo Autelie | Customizer/logo do tema ou origem ja usada pelo header comercial |
| Centro ou faixa compacta | Mensagem curta de seguranca, exemplo "Compra segura" | Texto funcional proprio traduzivel; se virar copy comercial editavel, mover para configuracao global administravel |
| Direita | Link/acao "Atendimento" | Configuracao global de atendimento ja administravel ou menu/link global existente |
| Corpo da pagina | "Voltar ao carrinho" | Conteudo Gutenberg da pagina de checkout ja provisionado |

## 3. Baseline atual

| Area | Estado atual | Problema |
| --- | --- | --- |
| Header do checkout | Esconde promo bar, busca, menu, wishlist, conta, minicarrinho e rodape; mantem logo e atendimento | Correto conceitualmente, mas visualmente parece vazio ou quebrado |
| Breadcrumb | Foi ocultado no checkout para remover faixa vazia | OK, desde que "Voltar ao carrinho" e estado do fluxo mantenham orientacao |
| Conteudo | H1, formulario e resumo ja foram alinhados e estabilizados | Ainda falta uma cabeca de checkout com proposito claro |
| Frete | Virtuaria/Correios pode aparecer no checkout; fallback local deve sumir quando ha metodo real | Precisa permanecer validado nos gates |
| Pagamento | Sem metodo ativo no ambiente local atual | Bloqueio coberto por 017, nao por este plano |

## 4. Escopo

### Sessao 01 - Inventario e arquitetura do header

- [x] Identificar como o header comercial atual renderiza logo, atendimento, busca, conta, wishlist e minicarrinho.
- [x] Confirmar se a reducao do checkout deve ser feita por hooks/classes do child theme ou por markup especifico no plugin.
- [x] Evitar sobrescrever template do Blocksy sem necessidade comprovada.
- [x] Documentar quais textos, links e imagens do header sao globais e onde o cliente edita cada um.

**Gate verificavel**

- [x] Nenhum texto comercial novo do header depende de alterar PHP, CSS ou JavaScript.
- [x] Logo e canal de atendimento reutilizam origens administrativas existentes ou criam configuracao global administravel.

### Sessao 02 - Design do header de checkout

- [x] Criar header compacto e intencional para checkout.
- [x] Manter logo com area clicavel previsivel.
- [x] Exibir "Compra segura" ou equivalente curto, sem competir com o H1.
- [x] Manter atendimento acessivel por link/botao claro.
- [x] Manter altura estavel em desktop e mobile.
- [x] Remover aparencia de navbar incompleta: sem buracos de grid, alinhamentos estranhos ou icones soltos.

**Gate verificavel**

- [x] Em desktop, o header cabe em uma linha, alinha com o frame do checkout e nao parece uma navbar comercial incompleta.
- [x] Em mobile, logo, seguranca e atendimento nao sobrepoem nem quebram texto.
- [x] O header nao causa layout shift durante hidratacao dos WooCommerce Blocks.

### Sessao 03 - Integracao com o checkout existente

- [x] Preservar a classe `petshop-distraction-free-checkout`.
- [x] Preservar Checkout Block oficial e Store API.
- [x] Preservar link "Voltar ao carrinho".
- [x] Garantir que a regra de frete local nao reaparece quando Virtuaria/Correios retorna metodo real.
- [x] Garantir que a ausencia de pagamento continua sendo exibida como bloqueio real do Plano 017, sem esconder erro.

**Gate verificavel**

- [x] Checkout com produto no carrinho mostra somente metodo real de frete quando disponivel.
- [x] Sem duplicacao de header, breadcrumb ou skip link.
- [x] Sem regressao no resumo do pedido sticky/estavel.

### Sessao 04 - Acessibilidade e responsividade

- [x] Validar navegacao por teclado pelo header, link de retorno, formulario, opcoes de entrega e CTA.
- [x] Garantir foco visivel em logo, atendimento e retorno ao carrinho.
- [x] Garantir nomes acessiveis para os elementos novos.
- [x] Validar contraste de textos e icones.
- [x] Validar screenshots em 390, 768, 1024, 1440 e viewport largo de referencia.

**Gate verificavel**

- [x] Nenhum texto fica cortado ou sobreposto.
- [x] Nao ha overflow horizontal.
- [x] Nao ha mudanca repetida de posicao do resumo durante 5 segundos apos carregamento.

## 5. Inventario de conteudo administravel

| Rota/area | Conteudo | Origem obrigatoria |
| --- | --- | --- |
| `/finalizar-compra/` header | Logo | Logo global do tema/Customizer ou origem global ja existente |
| `/finalizar-compra/` header | Link de atendimento e destino | Configuracao global de atendimento/menu/opcao administravel |
| `/finalizar-compra/` header | Mensagem curta de seguranca | Texto traduzivel; se for alteravel pelo cliente, configuracao global administravel |
| `/finalizar-compra/` corpo | Voltar ao carrinho | Pagina Gutenberg de checkout |
| `/finalizar-compra/` corpo | Campos, frete, pagamento, totais | WooCommerce Blocks e configuracoes WooCommerce |

Nenhum texto editorial, comercial ou imagem de conteudo deve ficar fixo em CSS. Se o texto "Compra segura" for tratado como promessa comercial customizavel, ele deve ser salvo em origem administravel antes do aceite.

## 6. Validacao obrigatoria

- [x] `node scripts/validate-009-cart-checkout-browser.mjs`
- [x] `node scripts/validate-013-browser.mjs`
- [x] Gate novo ou ampliado medindo estabilidade do header/resumo por pelo menos 5 segundos.
- [x] Screenshot desktop 1440 e viewport largo semelhante a 1850.
- [x] Screenshot mobile 390.
- [x] `git diff --check`
- [x] Logs WordPress sem fatal error recente.

## 9. Evidencia de conclusao

- Header de checkout implementado no child theme, sem template override do Blocksy.
- Logo reutiliza `custom_logo`; atendimento reutiliza `petshop_support_label` e `petshop_support_page`.
- Mensagem de seguranca usa `petshop_checkout_assurance_text`, editavel em Aparencia -> Personalizar -> Conteudo da loja, provisionada uma vez sem sobrescrever alteracoes.
- Evidencias visuais geradas em `.local/evidence/009/checkout-mobile-390.png`, `checkout-tablet-768.png`, `checkout-desktop-1024.png`, `checkout-desktop-1440.png` e `checkout-wide-1850.png`.
- Validado em 2026-08-12 com `validate-009`, `validate-013`, teste de persistencia, PHPUnit, `git diff --check` e logs WordPress sem fatal recente.

## 7. Fora de escopo

- Ativar Mercado Pago, Pix, cartao ou webhook; isso permanece no Plano 017.
- Trocar Virtuaria Correios ou alterar contrato/servicos reais de frete.
- Recriar todo o checkout ou substituir WooCommerce Blocks por shortcode/template.
- Reintroduzir menu completo, busca, wishlist, conta ou minicarrinho no checkout.
- Criar novas paginas institucionais ou juridicas.

## 8. Criterio de conclusao

O Plano 020 so podera ser concluido quando o checkout tiver um header reduzido com aparencia deliberada de compra segura, consistente com a marca, responsivo, acessivel, sem layout shift, sem navegacao comercial desnecessaria e com todo conteudo proprio editavel ou traduzivel conforme sua natureza.
