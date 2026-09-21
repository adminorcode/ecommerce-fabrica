# Plano 025 — Cadastro com senha escolhida pelo cliente

**Status:** Pendente  
**Data:** 2026-08-21  
**Branch sugerida:** `025-cadastro-senha-escolhida`  
**Dependências:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) (Minha conta, checkout visitante e `GuestAccount`)  
**Origem:** fricção no cadastro atual — após o e-mail, o cliente precisa abrir a caixa de entrada, copiar senha temporária e só então alterar a senha. Isso já fez o solicitante desistir da compra.  
**ClickUp:** [86e2xz60k](https://app.clickup.com/t/86e2xz60k) — Open  

## 1. Objetivo

Permitir que o cliente **conclua o cadastro na própria loja**, escolhendo a senha no formulário, sem ir ao e-mail para validar a conta ou receber senha temporária.

User story: como visitante, quero informar e-mail, nome e a senha que eu escolhi, para entrar na conta imediatamente e seguir a compra sem abrir o e-mail. Telefone, documento e endereço eu completo depois na conta.

## 2. Baseline atual

| Superfície | Estado | Problema |
|---|---|---|
| Minha conta → cadastro | Só e-mail; WooCommerce gera senha | `woocommerce_registration_generate_password` = `yes` (migração do Plano 013 em `CartCheckout::configureAccountOptions`) |
| Após o cadastro | Banner em inglês: “Your account is using a temporary password…”, botão **Resend** | Conta nasce com senha temporária; o cliente precisa do e-mail para usar a loja |
| Confirmação de pedido (`GuestAccount`) | Oferece criar conta e promete “link seguro para definir a senha” | `wc_create_new_customer()` sem senha; reforça o mesmo fluxo por e-mail |
| Endereço / documento | Endereço existe no checkout; CPF/CNPJ de cliente não é coletado no cadastro | O cadastro não pede os dados que a loja precisa para nota/entrega |

A opção 013 fica travada por `petshop_account_options_013_configured`. Uma nova migração versionada precisa **desligar** a geração automática de senha sem reabrir as demais opções de conta.

## 3. Escopo comprometido

- Formulário de cadastro inicial em `/minha-conta/` só com **e-mail**, **nome**, **sobrenome**, **senha escolhida pelo cliente** e **confirmação de senha**. Telefone, tipo de pessoa, CPF/CNPJ e endereço brasileiro **não** aparecem nessa tela; o cliente preenche depois em **Detalhes da conta** e **Endereços**.
- O cadastro em desktop usa **grade de duas colunas**: senha/confirmação e nome/sobrenome na mesma linha. Em 390px os pares empilham. Um único scroll de página; sem coluna estreita nem área interna com overflow.
- A conta **aceita pessoa física e pessoa jurídica** na edição posterior. PF exige CPF válido; PJ exige CNPJ válido. Os dois tipos são caminhos do mesmo cadastro de dados, não exclusões.
- Ao enviar com dados válidos, a conta é criada e o cliente **já entra autenticado**. Nenhum passo no e-mail é necessário para concluir o cadastro.
- Remover de ponta a ponta a funcionalidade de **senha temporária**: opção WooCommerce, e-mail de senha gerada, banner “temporary password”, botão Resend e qualquer copy que mande o cliente ao e-mail para definir a primeira senha.
- `GuestAccount` (criar conta depois da compra visitante) também coleta a senha no formulário da confirmação. Não envia link de definição de senha.
- Username continua gerado pelo WooCommerce a partir do e-mail (`woocommerce_registration_generate_username` permanece `yes`).
- Checkout visitante do Plano 013 permanece. Quem marcar “criar conta” no checkout também escolhe a senha ali.
- Recuperação de senha esquecida (`/minha-conta/lost-password/`) **permanece**. Isso não é senha temporária de cadastro.
- CEP de **Minha conta → Endereços** consulta ViaCEP e preenche logradouro, bairro, cidade e UF (regra do projeto). Número é do cliente.

### Fora de escopo

- Verificação obrigatória de e-mail (double opt-in) para ativar a conta.
- Trocar o Checkout Block por shortcode/clássico.
- Instalar Brazilian Market on WooCommerce ou outro plugin de terceiros (decisão nova exigiria registro neste plano).
- Autenticação social, magic link ou cadastro só no checkout.
- Calculadora de frete da PDP: CEP só para cotação, sem ViaCEP.
- Alterar o personalizador (012), frete real, Mercado Pago, políticas jurídicas (017) ou o prefill do checkout (026).
- Inventar texto jurídico; aceite de políticas no cadastro só reutiliza as páginas já atribuídas pelo 013 quando o WooCommerce as exigir.
- Coletar telefone, tipo de pessoa, CPF/CNPJ ou endereço no cadastro inicial de `/minha-conta/`.

## 4. Decisões de produto

| Tema | Decisão |
|---|---|
| Tipo de pessoa | Fora do cadastro inicial. Em Detalhes da conta, PF e PJ são aceitos; se o tipo for informado, precisa ser um dos dois. |
| Documento | Fora do cadastro inicial. Em Detalhes da conta: PF exige CPF (11 dígitos) e PJ exige CNPJ (14 dígitos). Se preenchido, validar dígitos verificadores. Recusar documento inválido, do tipo errado ou já usado por outra conta. |
| Telefone | Fora do cadastro inicial. Em Endereços, se preenchido, recusar formato inválido do Brasil (DDD + número) e gravar como telefone de cobrança. |
| Endereço | Fora do cadastro inicial. Em Minha conta → Endereços: CEP, logradouro, número, complemento, bairro, cidade, UF. Complemento vazio é válido quando o endereço não tem complemento. |
| ViaCEP | CEP com 8 dígitos em Minha conta → Endereços consulta ViaCEP pelo `petshop-core` e preenche logradouro, bairro, cidade e UF. Número é do cliente. CEP inválido ou API fora: aviso em pt-BR, sem inventar endereço. |
| Persistência | Salvar tipo de pessoa, telefone, endereço e documento (CPF ou CNPJ) como dados do cliente WooCommerce e meta própria no `petshop-core`. Sem SQL direto. |
| E-mail transacional | Enviar aviso de “conta criada” sem senha em texto. O login não depende desse e-mail. |
| Idioma | Rótulos, erros e avisos em pt-BR. O aviso em inglês da senha temporária não pode reaparecer. |

## 5. Conteúdo administrável e textos funcionais

Exceção documentada: o formulário é superfície WooCommerce/hook global, não bloco editorial da Home.

| Item | Origem |
|---|---|
| Título/introdução editorial da página Minha conta | Gutenberg em **Páginas → Minha conta** |
| Rótulos e erros do cadastro inicial (e-mail, nome, senha) e da edição posterior (telefone, PF/PJ, CPF, CNPJ, endereço) | Tradução/`__()` do `petshop-core` — texto funcional |
| CTA “Cadastrar” / “Entrar” | WooCommerce + tradução própria quando o rótulo for nosso |
| Banner de senha temporária | Removido; não substituir por outro aviso comercial hardcoded |
| Políticas no cadastro | Links das páginas já atribuídas no 013; conteúdo jurídico continua nessas páginas Gutenberg |

Nenhum texto comercial novo pode ficar fixo em PHP/CSS/JS. Imagens de conteúdo não entram neste plano.

## 6. Arquitetura

| Área | Onde | Responsabilidade |
|---|---|---|
| Migração | `CartCheckout` / migrador versionado | `woocommerce_registration_generate_password` = `no`; lock novo, sem desfazer edições do cliente |
| Cadastro | classe pequena em `petshop-core` (ex.: `AccountRegistration`) | campos, sanitização, tipo PF/PJ, validação de telefone, CPF e CNPJ, persistência, nonce, capability |
| ViaCEP | módulo único no plugin (ex.: `AddressLookup`) | consulta no servidor; reutilizado no checkout (026) |
| Conta pós-pedido | `GuestAccount` | senha no formulário; login imediato; sem e-mail de senha temporária |
| Tema | `petshop-theme` CSS | layout do formulário, tokens, 390–1440, alvos 44×44 |
| Docs | guia operacional WooCommerce / Minha conta | o cliente não precisa de e-mail para criar conta |
| Gates | `scripts/validate-025-*.php` e browser | opção, campos, ausência do banner, persistência |

Não editar WordPress Core, WooCommerce ou Blocksy. Checkout Block permanece. Dados pessoais (telefone, CPF/CNPJ, endereço) sanitizados, escapados e incluídos no exportador/apagador de privacidade.

## 7. Sessões

### Sessão 01 — Desligar senha temporária

- [ ] Migrar `woocommerce_registration_generate_password` para `no` de forma versionada.
- [ ] Exibir senha + confirmação no cadastro de Minha conta e no signup do checkout.
- [ ] Garantir que cadastro válido autentica na hora e abre o painel sem o banner de senha temporária.
- [ ] Ajustar `GuestAccount` para senha no formulário e login imediato.

**Gate**

- [ ] A opção WooCommerce de gerar senha está `no` após migrate e permanece após reprovisionar.
- [ ] Não há banner “temporary password” / “Resend” em pt-BR nem em inglês.
- [ ] Conta nova faz login sem abrir e-mail.

### Sessão 02 — Cadastro inicial curto; demais dados depois

- [x] Cadastro inicial de `/minha-conta/` só com e-mail, senha, confirmação, nome e sobrenome.
- [ ] Telefone, tipo PF/PJ, CPF ou CNPJ e endereço brasileiro ficam em **Detalhes da conta** / **Endereços**.
- [ ] CEP de Minha conta → Endereços consulta ViaCEP e preenche logradouro, bairro, cidade e UF.
- [ ] Validar telefone e o documento do tipo escolhido na edição posterior; recusar documento duplicado ou do tipo errado.
- [ ] Gravar tipo, telefone, endereço e documento no cliente WooCommerce quando o cliente os informar depois.

**Gate**

- [x] Cadastro inicial com e-mail, nome, sobrenome e senha cria cliente autenticado, sem telefone, documento nem endereço na tela.
- [ ] CEP válido em Endereços preenche rua, bairro, cidade e UF; número permanece com o cliente.
- [ ] CEP inválido ou ViaCEP fora: aviso em pt-BR, sem inventar endereço.
- [x] Envio sem senha, sem confirmação, sem nome ou sem sobrenome falha junto ao campo, sem criar usuário.
- [ ] CPF no fluxo PJ e CNPJ no fluxo PF são recusados na edição posterior.
- [x] Desktop 1440 e mobile 390: formulário usável, sem overflow, foco visível. Em 1440 nome/sobrenome e senha/confirmação compartilham a linha; em 390 empilham.

### Sessão 03 — Validação e handoff

- [ ] Gates PHP/browser da ausência de senha temporária e do contrato de campos.
- [ ] Atualizar guia operacional e `Plans/STATUS.md`.
- [ ] Confirmar que recuperação de senha esquecida continua funcionando.

**Gate**

- [ ] Visitante cria conta, entra e vê o painel sem passar pelo e-mail.
- [ ] Compra visitante + “criar conta” na confirmação também define senha na página.
- [ ] Reprovisionamento não religa geração automática de senha.

## 8. Riscos

| Risco | Mitigação |
|---|---|
| Migração 013 impede mudar a opção | Nova option/versão de migrate; não reaproveitar o lock antigo para este flip |
| Checkout Block esconde a senha | Validar o bloco com `generate_password=no`; não copiar template interno |
| CPF/CNPJ sem plugin BR | Validação e meta no `petshop-core`; sem plugin novo |
| E-mail “nova conta” ainda manda senha | Filtrar/template WooCommerce para não incluir senha gerada |
| Contas antigas com senha temporária | Fora do cadastro novo; essas contas usam “esqueci a senha”, não o banner de temp password em contas novas |
