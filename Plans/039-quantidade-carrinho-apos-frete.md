# Plano 039 — Quantidade do carrinho após o frete

**Status:** Concluído
**Data:** 2026-09-10
**Branch sugerida:** `039-quantidade-carrinho-apos-frete`
**Dependências:** [027-calculadora-frete-hub.md](./027-calculadora-frete-hub.md) (CEP da PDP e Blocks no carrinho); [036-dependencias-frete-checkout-versionadas.md](./036-dependencias-frete-checkout-versionadas.md) (plugin brasileiro versionado)
**Origem:** ticket ClickUp [86e31yvgj](https://app.clickup.com/t/86e31yvgj). Reprodução local em 2026-09-10: no Cart Block, depois de Consultar o CEP do plugin brasileiro, o `+` vai a 2 e em ~1,5 s a quantidade (UI e Store API) volta a 1. Antes do CEP o `+` permanece em 2.
**ClickUp:** [86e31yvgj](https://app.clickup.com/t/86e31yvgj) — in progress

**Alcance:** global na rota `/carrinho` (Cart Block), para qualquer item com quantidade maior que 1. A Bandana Neon - P é só a amostra do gate.

## 1. Objetivo

No carrinho, depois de calcular o frete pelo CEP, aumentar a quantidade precisa persistir. O comprador não volta sozinho para 1.

User story: como comprador, quero informar o CEP no carrinho, ver as cotações e depois aumentar a quantidade sem o item voltar para 1.

## 2. Baseline atual

| Superfície | Estado | Problema |
|---|---|---|
| `/carrinho` sem CEP do plugin brasileiro | Cart Block nativo | `+` de 1 para 2 permanece |
| `/carrinho` com `woo_better_calc_enable_cart_page=yes` | JS `woo-better-cart-custom-postcode` intercepta `update-item`, reconsulta o CEP e chama `invalidateResolutionForStore` | `+` sobe para 2 e em ~1,5 s UI e Store API voltam a 1 |
| PDP | Calculadora `petshop-core` (027) | Fora deste bug; CEP da PDP continua hidratando a sessão |
| Plugin de terceiro | Versionado no 036; edição proibida | O race está no JS de carrinho desse plugin |

A calculadora de frete **não** usa ViaCEP.

## 3. Escopo comprometido

- Na rota `/carrinho`, depois de calcular frete pelo CEP, aumentar a quantidade (botão `+` ou digitação) é imediato e fluido: 1→2→3 na hora, sem esperar o recálculo do frete, sem desabilitar o seletor e sem voltar nem piscar para o valor anterior.
- O mesmo vale para qualquer produto simples em estoque que aceite quantidade maior que 1, não só a amostra do gate.
- O comprador continua calculando frete no carrinho por um CEP de cotação do `petshop-core` (Store API `update-customer`), sem o JS `woo-better-cart-custom-postcode`.
- Com `woo_better_calc_enable_cart_page=yes` no banco, o script `woo-better-cart-custom-postcode` **não** é enfileirado no carrinho.
- O formulário/painel `#custom-postcode-form` e `.woo-better-info-block` não ficam visíveis no carrinho.
- O seletor nativo de métodos do Cart Block continua listando as taxas WooCommerce ativas após o CEP.
- CEP inválido (menos de 8 dígitos) mostra aviso em pt-BR e não inventa endereço nem taxa.
- Textos do campo/botão/erros do CEP do carrinho são funcionais traduzíveis (`__()`), sem ViaCEP.
- Depois do CEP, o recálculo de frete (Store API `update-item`) espera **1 s** após a última alteração de quantidade; cliques seguidos geram um único recálculo.

### Fora de escopo

- Editar `woo-better-shipping-calculator-for-brazil`, Melhor Envio, Virtuaria, WooCommerce ou Blocksy.
- ViaCEP (CEP aqui é cotação).
- Mini-carrinho, checkout, PDP e calculadora do 027 além de não regressar o CEP persistido.
- Frete grátis, progresso de frete e campos de checkout do plugin brasileiro.
- Commit, PR ou merge.

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Superfície | Só `/carrinho` (Cart Block) |
| CEP no carrinho | Campo do `petshop-core` via Store API; sem interceptor de `update-item` |
| Plugin brasileiro | Continua ativo para health check/campos; o JS de CEP do carrinho é desligado no storefront |
| Quantidade | A UI muda na hora; a Store API converge para o valor pedido sem travar o `+` |
| Frete após quantidade | Recalcular **1 s** após o último `+`/`−`/digitação; um único `update-item` |
| Amostra do gate | Bandana Neon - P (ou outro simples em estoque); o comportamento é de toda a rota |

## 5. Conteúdo administrável e textos funcionais

Exceção documentada: o CEP do carrinho é hook/Store API, não bloco editorial da página.

| Item | Origem |
|---|---|
| Rótulo CEP, botão Calcular, erros de CEP | `__()` em `petshop-core` |
| Nomes e preços das taxas | WooCommerce / métodos ativos |
| “Continue explorando a loja” | Gutenberg da página Carrinho (013) |

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Neutralizar race | `petshop-core` (`CartQuantityStability`) | `pre_option` no storefront + dequeue dos JS de CEP/progresso do plugin brasileiro no carrinho |
| CEP | `assets/js/cart-quantity-stability.js` + Store API `update-customer` | Grava país BR e CEP; o Cart Block recalcula taxas |
| Quantidade | `cart-quantity-guard.js` no head + JS do CEP | O `+` atualiza na hora; `update-item` e o frete esperam 1 s após o último clique |
| CSS | `petshop-theme/style.css` | Ocultar widget extra do plugin; estilizar o CEP próprio com tokens |
| Gates | `scripts/validate-039-cart-qty.php` e `validate-039-cart-qty-browser.mjs` | Script ausente, CEP próprio presente, quantidade persiste após o frete |

Não editar Core, WooCommerce nem o plugin de terceiro.

## 7. Sessões

### Sessão 01 — Quantidade estável após o CEP

- [x] Desligar o JS de CEP do carrinho do plugin brasileiro no storefront, mesmo com a option ligada.
- [x] Exibir o CEP de cotação do `petshop-core` no carrinho e gravar o CEP na Store API.
- [x] Depois do cálculo, `+` de 1 para 2 permanece 2 na UI e na Store API.
- [x] Digitar quantidade maior que 1 também persiste.

**Gate**

- [x] PHP: com `woo_better_calc_enable_cart_page=yes`, o HTML do carrinho não inclui `CustomCartPostcode` e inclui `petshop-cart-quantity-stability`.
- [x] Browser: amostra fora do exemplo principal (produto simples distinto) também mantém quantidade 2 após o CEP.
- [x] Browser 1440: após Consultar/Calcular CEP `01001-000`, `+` permanece em 2 por 10 s; Store API `items[0].quantity === 2`.
- [x] Atualizar `Plans/STATUS.md`.

### Sessão 02 — Sem flicker ao aumentar 1 ou 2

- [x] Depois do CEP, cada `+` (1→2 e 2→3 quando o estoque permitir) mantém a quantidade pedida na UI sem voltar ao valor anterior enquanto as taxas recalculam.
- [x] A Store API converge para a quantidade pedida; snapshot atrasado de `update-customer`/`GET cart` não vence o `update-item`.
- [x] O carrinho não bloqueia o seletor de quantidade por espera artificial após o CEP.

**Gate**

- [x] Browser: após Calcular CEP `01001-000`, amostras a cada 150 ms por 5 s em cada incremento não mostram quantidade menor que a pedida (Bandana e amostra extra).
- [x] PHP: HTML do carrinho sem `CustomCartPostcode`, `PublicCEPField.COMPILED` nem `ProgressBar.COMPILED`.
- [x] `npm run validate:changed` nas superfícies 039.

### Sessão 03 — Seletor fluido

- [x] Depois do CEP, dois `+` seguidos atualizam 1→2→3 na hora, sem o botão ficar desabilitado.
- [x] O recálculo do frete não bloqueia nem esmaece o seletor de quantidade.
- [x] A quantidade pedida permanece estável depois dos cliques rápidos.

**Gate**

- [x] Browser: após Calcular CEP, dois `+` com `force` em sequência chegam à quantidade pedida em menos de 500 ms por clique e não piscam nos 5 s seguintes.
- [x] Browser: o botão `+` não fica `disabled` após o primeiro clique.

### Sessão 04 — Frete 1 s após a quantidade

- [x] Depois do CEP, cliques seguidos no `+` não disparam `update-item` nos primeiros 800 ms.
- [x] Um único `update-item` sai ~1 s após o último clique e a Store API converge.
- [x] O CEP informado pelo comprador continua calculando o frete na hora.

**Gate**

- [x] Browser: após Calcular CEP, dois `+` geram 0 `update-item` em 800 ms e exatamente 1 após o debounce de 1 s (Bandana e amostra extra).
- [x] PHP: `petshopCartQtyConfig.shippingDebounceMs` presente no HTML do carrinho.

### Evidência de validação

- Runtime com `docker compose up --watch --build`; plugin/tema sincronizados no volume.
- Sessão 01: o gate antigo aprovou persistência em 10 s, mas a UI ainda piscava a quantidade antiga durante o recálculo do frete.
- Sessão 02: `validate-039-cart-qty.php` aprovado; o gate browser antigo aprovou persistência, mas o `+` ainda esperava o frete.
- Sessão 03: `validate-039-cart-qty.php` aprovado (inclui `petshop-cart-quantity-guard`); `validate-039-cart-qty-browser.mjs` aprovado (Bandana e Perfume: dois `+` em < 500 ms, sem `disabled`, sem flicker em 5 s; Store API converge).
- Sessão 04: gate browser aprovado — 0 `update-item` em 800 ms e 1 recálculo ~1 s após o último `+` (Bandana e Perfume).
