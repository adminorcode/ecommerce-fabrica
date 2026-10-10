# Plano 036 - Dependencias de frete e checkout versionadas

**Status:** Concluido
**Data:** 2026-08-31
**Branch sugerida:** `036-dependencias-frete-checkout-versionadas`
**Dependencias:** [025-cadastro-senha-escolhida.md](./025-cadastro-senha-escolhida.md), [026-checkout-dados-salvos-viacep.md](./026-checkout-dados-salvos-viacep.md), [027-calculadora-frete-hub.md](./027-calculadora-frete-hub.md)
**Origem:** o painel do Melhor Envio mostra aviso administrativo exigindo um plugin brasileiro de campos/calculadora ativo. O Plano 027 instalou Melhor Envio apenas no runtime local e deixou plugin extra de checkout fora de escopo. A entrega agora passa a versionar a integracao de frete e sua base brasileira no repositorio, com dependencias empacotadas e sem credenciais.
**ClickUp:** pendente

## 1. Objetivo

Deixar a integracao de frete brasileira reproduzivel no repositorio e no pacote de deploy: Melhor Envio e a base brasileira de checkout/frete ficam versionados em `wp-content/plugins`, entram na imagem Docker, no runtime local e no pacote HostGator/cPanel, sem token, credencial, option do banco ou dado pessoal.

User story: como operador da loja, quero subir o ambiente ou preparar deploy e encontrar os plugins de frete/checkout brasileiro ja presentes, ativaveis e com dependencias de codigo completas, para nao depender de instalacao manual no painel e nao quebrar o Melhor Envio por falta de plugin base.

## 2. Baseline atual

| Area | Estado | Problema |
|---|---|---|
| Melhor Envio | Instalado e ativo no volume local como `melhor-envio-cotacao` 2.16.6 | Nao esta no Git, nao entra no Dockerfile nem no deploy; novo ambiente perde o plugin |
| Dependencia do Melhor Envio | Health check exige `woo-better-shipping-calculator-for-brazil` ou `woocommerce-extra-checkout-fields-for-brazil` ativo | Nenhum dos dois esta instalado; aviso aparece no admin |
| `.gitignore` | Ignora `**/vendor/` globalmente | Melhor Envio depende de `vendor/autoload.php`; se copiar sem excecao, o plugin versionado fica incompleto |
| Docker | Copia apenas plugins baixados no build e `petshop-core` | Plugins versionados de terceiro nao sao sincronizados para `/opt/project-source/plugins` |
| Deploy | `scripts/prepare-deploy.mjs` copia apenas `petshop-core` | Melhor Envio e base brasileira nao chegam no pacote de publicacao |
| Checkout | Planos 025 e 026 planejam campos, CPF/CNPJ e ViaCEP no `petshop-core` | Plugin brasileiro tambem altera campos de checkout e pode duplicar comportamento |

## 3. Escopo comprometido

- Versionar `wp-content/plugins/melhor-envio-cotacao` com os arquivos do plugin oficial e `vendor/autoload.php` completo.
- Versionar `wp-content/plugins/woo-better-shipping-calculator-for-brazil` como base brasileira ativa para satisfazer o health check do Melhor Envio.
- Analisar `woocommerce-extra-checkout-fields-for-brazil` como alternativa compatibilizada pelo Melhor Envio, mas manter fora da instalacao/ativacao porque a base escolhida orienta desativar Brazilian Market para evitar conflito de campos.
- Alterar `.gitignore` para permitir apenas os `vendor/` dos plugins versionados aprovados neste plano, mantendo o bloqueio geral de vendors acidentais.
- Alterar `docker/wordpress/Dockerfile` e `docker/scripts/init-wordpress.sh` para copiar e ativar os plugins versionados aprovados neste plano.
- Alterar `scripts/prepare-deploy.mjs` para incluir os plugins versionados aprovados no pacote, preservando `vendor/autoload.php` quando o plugin de terceiro exigir.
- Garantir que token do Melhor Envio, endereco de origem, agencias, options do banco, uploads, logs e dados pessoais nao sejam versionados.
- Validar que o aviso do Melhor Envio some com a base brasileira ativa.
- Validar que a PDP continua usando somente a calculadora `petshop-core`, sem widget extra do Melhor Envio nem da base brasileira.
- Validar carrinho e checkout em desktop e mobile contra duplicacao de campos, quebra visual e erro fatal.
- Registrar na documentacao operacional que atualizacoes desses plugins deixam de ser clique direto no admin e passam por atualizacao versionada no Git.

## 4. Fora de escopo

- Versionar WooCommerce, Blocksy, Stackable, Fluent Forms, Virtuaria, Mercado Pago, PayPal, Stripe, Jetpack, MailPoet, Google Listings, TikTok, Akismet ou All-in-One WP Migration.
- Versionar banco de dados, options, tokens, credenciais, arquivos de upload, logs, caches ou chaves.
- Editar codigo interno do Melhor Envio, da Calculadora de Frete e Campos Checkout para o Brasil ou do Brazilian Market.
- Ativar simultaneamente `woo-better-shipping-calculator-for-brazil` e `woocommerce-extra-checkout-fields-for-brazil`.
- Substituir o Checkout Block por checkout classico.
- Remover a calculadora propria do `petshop-core` na PDP.
- Alterar contrato de frete real, servicos, precos, origem operacional ou token de producao.

## 5. Decisoes de produto

| Tema | Decisao |
|---|---|
| Base brasileira ativa | `woo-better-shipping-calculator-for-brazil` sera a base ativa inicial porque esta atualizada em 2026 e declara compatibilidade com Blocks/Gutenberg. |
| Brazilian Market | Sera analisado como alternativa reconhecida pelo Melhor Envio, mas nao ativado junto da base escolhida porque ha conflito de campos documentado. |
| Melhor Envio | Passa de plugin instalado manualmente no runtime para plugin versionado em `wp-content/plugins/melhor-envio-cotacao`. |
| Dependencias Composer | `vendor/` de plugin de terceiro aprovado entra no Git quando for parte obrigatoria do pacote oficial do plugin. |
| Atualizacao | Atualizar plugin de terceiro versionado exige troca de arquivos no Git, validacao e registro no plano ou entrega correspondente. |
| Segredos | Token e configuracoes operacionais continuam somente no banco/ambiente de destino. |

## 6. Conteudo administravel e textos funcionais

Esta entrega altera plugins e superficies funcionais de frete/checkout, nao cria conteudo editorial.

| Item | Origem |
|---|---|
| Avisos do Melhor Envio e base brasileira | Traducoes/textos dos plugins de terceiro |
| Labels de campos brasileiros no checkout | Plugin brasileiro ativo e filtros do `petshop-core` quando necessario |
| Copy editorial de paginas de entrega, politicas e ajuda | Gutenberg, fora desta entrega |

## 7. Arquitetura

| Area | Onde | Responsabilidade |
|---|---|---|
| Plugins versionados | `wp-content/plugins/melhor-envio-cotacao`, `wp-content/plugins/woo-better-shipping-calculator-for-brazil` | Codigo de terceiro congelado no Git, sem edicao local |
| Ignore rules | `.gitignore` | Permitir vendors aprovados e bloquear vendors acidentais |
| Imagem local | `docker/wordpress/Dockerfile` | Copiar plugins versionados para `/opt/project-source/plugins` |
| Bootstrap | `docker/scripts/init-wordpress.sh` | Instalar no volume e ativar plugins aprovados |
| Test runner | `compose.yaml` | Montar plugins versionados nos testes quando necessario |
| Deploy | `scripts/prepare-deploy.mjs` | Incluir plugins aprovados no pacote cPanel |
| Gates | `scripts/validate-027-*` e novo gate 036 | Confirmar health check, ausencia de widgets duplicados e checkout funcional |

## 8. Sessoes

### Sessao 01 - Inventario e versionamento

- [x] Baixar/copiar Melhor Envio 2.16.6 com `vendor/autoload.php` completo para o repositorio.
- [x] Baixar/copiar Calculadora de Frete e Campos Checkout para o Brasil 4.17.1 para o repositorio.
- [x] Analisar Brazilian Market 4.0.2 como alternativa de conflito, sem instalar/ativar junto da base escolhida.
- [x] Confirmar que nenhum arquivo versionado contem token, e-mail operacional, endereco real, agencia, log ou option exportada.

**Gate**

- [x] `git status --short` mostra apenas arquivos de codigo/plugin/documentacao esperados.
- [x] `vendor/autoload.php` do Melhor Envio existe no worktree.
- [x] Busca por token/segredo nos plugins versionados nao encontra credencial local.

### Sessao 02 - Runtime, Docker e deploy

- [x] Dockerfile copia os plugins versionados aprovados para a imagem.
- [x] Bootstrap ativa WooCommerce, Melhor Envio e a base brasileira escolhida.
- [x] Deploy cPanel inclui os plugins versionados aprovados.
- [x] Documentacao operacional explica o fluxo de atualizacao versionada.

**Gate**

- [x] Novo build sobe com os plugins presentes sem instalacao manual no painel.
- [x] Pacote de deploy contem Melhor Envio, base brasileira e `vendor/autoload.php` exigido.

### Sessao 03 - Compatibilidade checkout/frete

- [x] Aviso administrativo do Melhor Envio desaparece com a base brasileira ativa.
- [x] PDP continua exibindo somente a calculadora `petshop-core`.
- [x] Carrinho e checkout nao exibem widget extra de calculadora.
- [x] Checkout Block nao duplica CPF/CNPJ, telefone, numero, bairro ou CEP contra campos do `petshop-core`.
- [x] Plano 025 e Plano 026 ficam reconciliados com a decisao da base brasileira.

**Gate**

- [x] `validate-027-shipping-hub.php` passa.
- [x] `validate-027-shipping-hub-browser.mjs` passa.
- [x] Gate 036 confirma plugins ativos, ausencia do aviso e calculadoras duplicadas desativadas.

## 9. Riscos

| Risco | Mitigacao |
|---|---|
| Base brasileira duplica campos planejados no `petshop-core` | Gate 036 bloqueia duplicacao; Planos 025 e 026 devem reaproveitar ou desativar campos conflitantes |
| Brazilian Market e Calculadora BR conflitam | Nao ativar os dois ao mesmo tempo |
| Plugin de terceiro com vendor ignorado | Excecao pontual no `.gitignore` e gate para `vendor/autoload.php` |
| Atualizacao pelo painel diverge do Git | Documentar atualizacao versionada e validar status de update |
| Segredo do Melhor Envio entrar no Git | Copiar apenas arquivos de plugin, nunca options; rodar busca por padroes sensiveis |
| Plugin nao suportar WooCommerce/WordPress atuais | Gate de runtime e registro de versoes testadas antes de deploy |

## 10. Evidencia de entrega

- Melhor Envio 2.16.6 ativo e versionado em `wp-content/plugins/melhor-envio-cotacao`, incluindo `vendor/autoload.php`.
- Calculadora de Frete e Campos Checkout para o Brasil 4.17.1 ativa e versionada em `wp-content/plugins/woo-better-shipping-calculator-for-brazil`, incluindo `vendor/autoload.php`.
- Brazilian Market on WooCommerce 4.0.2 analisado como alternativa aceita pelo Melhor Envio, mas nao instalado/ativado porque conflita com a Calculadora BR.
- Dockerfile e `.dockerignore` validam os vendors dentro da imagem; `prepare-deploy` gerou pacote com os dois plugins versionados.
- Gates executados em 2026-08-31: `npm run validate:changed`, `npm run validate:changed:browser`, `docker compose build wordpress`, `npm run prepare:deploy`.
