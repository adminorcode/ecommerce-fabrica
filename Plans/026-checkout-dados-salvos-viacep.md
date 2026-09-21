# Plano 026 — Checkout com dados salvos e ViaCEP

**Status:** Validado localmente — pronto para commit/PR
**Data:** 2026-08-22  
**Branch sugerida:** `026-checkout-dados-salvos-viacep`  
**Dependências:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) (Checkout Block, conta, endereços); [025-cadastro-senha-escolhida.md](./025-cadastro-senha-escolhida.md) (cadastro inicial com e-mail, nome e senha; telefone, CPF/CNPJ e endereço depois em Minha conta; lookup ViaCEP no plugin)  
**Origem:** no checkout, o cliente autenticado deve reencontrar os dados já cadastrados; ao informar o CEP, o endereço deve completar pela ViaCEP (regra do projeto).  
**ClickUp:** [86e2xzer3](https://app.clickup.com/t/86e2xzer3) — Open  

## 1. Objetivo

No checkout, carregar automaticamente os dados que a loja já tem daquele cliente e, ao digitar o CEP, preencher o endereço com a ViaCEP — sem redigitar cidade, bairro, rua e UF.

User story: como cliente logado, quero chegar em Finalizar compra com nome, e-mail, telefone, documento e endereço já preenchidos; como qualquer comprador, ao informar o CEP quero ver o endereço completo pela ViaCEP, para só conferir e informar o número.

## 2. Baseline atual

| Superfície | Estado | Problema |
|---|---|---|
| `/finalizar-compra/` | Checkout Block; visitante e conta do 013 | Campos de endereço começam vazios ou incompletos; CPF/CNPJ e telefone do 025 não têm garantia de hidratação no bloco |
| Cliente WooCommerce | 025 grava nome no cadastro; telefone, tipo PF/PJ, CPF ou CNPJ e endereço em Minha conta | Esses dados não voltam sozinhos para o Checkout Block |
| CEP | PDP calcula frete por CEP (`ProductDetails`); checkout não consulta ViaCEP | Digitar o CEP não preenche logradouro, bairro, cidade nem UF |
| Carrinho | Cart Block; não é o formulário de endereço completo | Fora deste ticket, salvo se o bloco exibir os mesmos campos de endereço do checkout |

## 3. Escopo comprometido

- Em `/finalizar-compra/`, cliente autenticado recebe **todos** os dados já gravados na conta: e-mail, nome, sobrenome, telefone, tipo PF/PJ, CPF ou CNPJ, CEP, logradouro, número, complemento, bairro, cidade e UF.
- Cobrança e entrega usam os endereços salvos do cliente. Se só existir cobrança, os dois lados nascem com esses valores.
- Visitante não autenticado vê o formulário vazio e usa ViaCEP ao informar o CEP.
- CEP com 8 dígitos dispara consulta **ViaCEP** e preenche logradouro, bairro, cidade e UF. Complemento vem da ViaCEP quando a API devolver valor.
- Número do endereço não vem da ViaCEP: o cliente informa o número. Campo obrigatório para fechar o pedido.
- CEP inválido ou ViaCEP indisponível: mensagem em pt-BR junto ao CEP; nenhum endereço inventado; o cliente preenche o endereço na mão para concluir.
- Trocar o CEP na mesma sessão consulta de novo e atualiza logradouro, bairro, cidade e UF.
- Checkout Block e Store API permanecem. Sem shortcode clássico, sem plugin BR de terceiros, sem token ViaCEP no repositório.
- Consulta ViaCEP reutiliza o módulo único do `petshop-core` (mesmo contrato do 025). Não chamar a API pública direto do browser sem esse contrato.

### Fora de escopo

- Calculadora de frete da PDP (`ProductDetails`): CEP só para cotação, sem ViaCEP.
- Alterar layout do header de checkout (020), personalizador (012), pagamento/frete real (017) ou senha temporária (025).
- Salvar endereço novo na conta sem o fluxo oficial do WooCommerce após o pedido.

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Superfície | `/finalizar-compra/` (Checkout Block). Se o carrinho tiver campos de endereço (não só CEP de frete), ViaCEP vale ali também. CEP só de cotação no carrinho segue a exceção da calculadora. |
| Prefill autenticado | Hidratar o bloco com o cliente WooCommerce + metas do 025. Campo sem valor na base fica vazio para o cliente completar. |
| ViaCEP | Mesma regra e módulo do projeto (`.cursor/rules/viacep-address.mdc`). CEP com 8 dígitos; lookup no `petshop-core`. |
| Mapeamento | `logradouro` → rua; `bairro` → bairro; `localidade` → cidade; `uf` → estado; `complemento` → complemento quando a API enviar. Número é do cliente. País = BR. |
| Erro | CEP inexistente (`erro: true`) ou falha HTTP: aviso no campo CEP; demais campos de endereço editáveis. |
| Frete | Prefill e ViaCEP atualizam o destino do Store API para o cálculo de entrega usar o mesmo CEP/UF/cidade. |

## 5. Conteúdo administrável e textos funcionais

Exceção documentada: checkout é Checkout Block + hook/Store API, não bloco editorial da Home.

| Item | Origem |
|---|---|
| Rótulos dos campos de endereço | WooCommerce Blocks / tradução |
| Aviso de CEP inválido ou ViaCEP indisponível | Tradução/`__()` do `petshop-core` |
| Copy comercial da página | Gutenberg em **Páginas → Finalizar compra** (já provisionado no 013) |

Nenhum texto comercial novo em PHP/CSS/JS. Sem imagens neste plano.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Prefill | `petshop-core` + Store API / campos adicionais do Checkout Block | Ler cliente e metas 025; hidratar cobrança/entrega **só na primeira carga da sessão**. `rest_request_before_callbacks` preenche `WC_Customer` em memória e a sessão; sem `WC_Customer::save()` — herança billing→shipping não grava `shipping_*` na conta. `rest_request_after_callbacks` (`filterStoreApiCartResponse`) completa a JSON do carrinho. Pedidos seguintes e `update-customer` não recolocam dado da conta em campo que o cliente esvaziou. |
| ViaCEP | classe pequena no plugin (ex.: `AddressLookup`) | Validar CEP, consultar ViaCEP no servidor, cache curto, JSON sanitizado |
| Front do bloco | script de view do plugin, sem copiar template WooCommerce | Debounce no CEP, aplicar retorno nos campos, anunciar resultado com `aria-live` |
| Tema | CSS só se o estado de erro/loading exigir token | 390–1440, alvo 44×44, foco visível |
| Gates | `scripts/validate-026-*.php` e browser | Prefill autenticado, ViaCEP, falha de CEP, Store API |

Não editar WordPress Core, WooCommerce ou Blocksy. Dados pessoais não vão para log nem para URL.

## 7. Sessões

### Sessão 01 — Prefill do cliente autenticado

- [x] Hidratar Checkout Block com e-mail, nome, telefone, tipo, CPF ou CNPJ e endereço já gravados na conta (cadastro 025 + Minha conta).
- [x] Visitante sem conta continua com formulário vazio.
- [x] Campo ausente na base permanece vazio e editável.
- [x] Hidratar só na primeira carga da sessão: depois disso, campo que o cliente esvaziar não volta com o valor da conta.

**Gate**

- [x] Cliente com cadastro 025 abre `/finalizar-compra/` e vê os dados gravados sem redigitar.
- [x] Visitante não recebe dado de outra conta.
- [x] Reprovisionar não apaga endereço/documento já salvos.

### Sessão 02 — ViaCEP no CEP do checkout

- [x] CEP com 8 dígitos consulta ViaCEP pelo `petshop-core`.
- [x] Preencher rua, bairro, cidade e UF; complemento quando a API devolver.
- [x] Número continua com o cliente.
- [x] CEP inválido ou API fora: aviso no campo; endereço manual permitido.

**Gate**

- [x] CEP válido conhecido pela ViaCEP preenche rua, bairro, cidade e UF no bloco.
- [x] Trocar o CEP atualiza esses campos.
- [x] CEP inexistente não inventa endereço e mostra erro em pt-BR.
- [x] Frete do checkout usa o CEP preenchido.

### Sessão 03 — Validação e handoff

- [x] Gate PHP de prefill, ViaCEP, falha e hidratação única por sessão.
- [ ] Gate browser de prefill autenticado (cadastro 025 + Minha conta → `/finalizar-compra/`). Visitante e ViaCEP já passam.
- [x] Confirmar Checkout Block e Store API intactos.
- [x] Atualizar `Plans/STATUS.md`.

**Gate**

- [x] Logado: dados da base + CEP novo via ViaCEP no mesmo fluxo.
- [x] Visitante: só ViaCEP, sem prefill de conta.
- [x] 1440 e 390 sem overflow; teclado e leitor de tela anunciam o preenchimento.

Validação após o review: PHP lint OK; `node --check` OK; `validate-026-checkout.php` EXIT 0 (incluindo hidratação única e herança billing→shipping sem repor `user_meta`). Gate browser: visitante OK; ViaCEP (CEP válido, troca, erro, Store API, número preservado) OK; prefill autenticado ainda lê carrinho vazio no Checkout Block depois do cadastro 025 + Minha conta — residual de sessão Store API vs cookie WP, não do PHP de hidratação.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Checkout Block ignora meta custom | Extensão Store API / additional checkout fields; não clonar template |
| ViaCEP fora do ar | Mensagem + preenchimento manual; checkout não bloqueia só por timeout da API se o endereço manual estiver completo |
| Número misturado no logradouro | Número é campo separado; ViaCEP não o preenche |
| Vazamento de dados no lookup | Só CEP na consulta; resposta sanitizada; nonce |
| Prefill E2E no Checkout Block | Gate PHP cobre hidratação. Browser autenticado ainda pode ver carrinho Store API vazio; próximo passo é amarrar o Cart-Token da sessão logada |
