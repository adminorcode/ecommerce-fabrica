# Status dos planos

| Plano | Status | Observação |
|---|---|---|
| [000-bootstrap-woocommerce-local.md](./000-bootstrap-woocommerce-local.md) | Concluído | Ambiente, versões, plugins, tema, loja e painel validados em runtime |
| [001-instalacao-blocksy-petsy.md](./001-instalacao-blocksy-petsy.md) | Concluído | Blocksy/Petsy com Gutenberg importado; child theme e fluxos essenciais validados |
| [003-ambiente-totalmente-docker.md](./003-ambiente-totalmente-docker.md) | Abandonado | Stack Compose e documentação existente foram preservadas; não haverá remoção do `wp-env` legado neste plano |
| [004-identidade-visual-e-navegabilidade.md](./004-identidade-visual-e-navegabilidade.md) | Abandonado | Implementação e 004b preservadas; a11y manual pendente não será continuada neste plano |
| [004b-correcao-vitrine-e-catalogo.md](./004b-correcao-vitrine-e-catalogo.md) | Concluído | Catálogo demonstrável, hero full-bleed editável, densidade comercial e persistência validados |
| [005-refinamento-comercial-do-storefront.md](./005-refinamento-comercial-do-storefront.md) | Abandonado | Sessões 01–02 e entregas posteriores preservadas; sessões pendentes não serão continuadas neste plano |
| [006-infraestrutura-ci-e-documentacao.md](./006-infraestrutura-ci-e-documentacao.md) | Concluído | Docker/docs unificados, run-gates, CI workflow; gates PHP validados em runtime |
| [007-refatoracao-petshop-core.md](./007-refatoracao-petshop-core.md) | Concluído | PSR-4, modularização, migrador versionado, Customizer/defaults, lifecycle/CLI e matriz PHP/browser validados |
| [009-design-system-acessibilidade-e-checkout.md](./009-design-system-acessibilidade-e-checkout.md) | Concluído | Tokens, cart/checkout, a11y técnica; NVDA/VoiceOver manual pendente |
| [010-layout-secoes-produto-home.md](./010-layout-secoes-produto-home.md) | Concluído | Vitrines da Home: cards compactos, cabeçalho “Ver todos”, badges reais, schema 17 |
| [010b-wishlist-lista-de-desejos.md](./010b-wishlist-lista-de-desejos.md) | Concluído | Página, endpoint Minha conta, link no header, merge localStorage → conta |
| [011-banners-gerenciaveis-home.md](./011-banners-gerenciaveis-home.md) | Concluído | Blocos `petshop/home-campaigns` e `petshop/home-campaign`; carrossel manual acessível; persistência validada |
| [012-personalizador-produtos-e-fila-producao.md](./012-personalizador-produtos-e-fila-producao.md) | Em andamento | Inventário do editor fechado (§0.1): página dedicada, sem limite de objetos, tipografia/cores livres, camadas, só upload, confirmação→carrinho/editor. Próximo: implementar Sessão 03 |
| [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) | Em andamento | Código, gates automatizados e Virtuaria Correios local validados; aguarda Mercado Pago sandbox, jurídico, origem/contrato reais e NVDA/VoiceOver |
| [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md) | Concluído | Tokens AUTellê, Nunito Sans, campanha editorial Gutenberg e rodapé no tom escuro anterior validados em PHP/browser |
| [015-secao-atendimento-home.md](./015-secao-atendimento-home.md) | Concluido | Secao de atendimento Gutenberg na Home, schema 26, CTA WhatsApp editavel com fallback para Atendimento, imagens 1920 x 640 e 1080 x 1350, gates PHP/browser validados |
| [016-vitrine-produtos-gutenberg.md](./016-vitrine-produtos-gutenberg.md) | Concluído | Bloco único `petshop/product-grid`, variações manual/categoria/popular/sazonal, migração schema 25 e gates PHP/browser/editor validados |
| [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) | Pendente | Fecha bloqueios P0 do PDF Orcode: Mercado Pago sandbox, frete real, políticas, e-mails, SEO, CWV, backup, monitoramento e a11y manual |
| [018-paginas-comerciais-p1.md](./018-paginas-comerciais-p1.md) | Concluído | Animal Republik e Premium publicados como páginas Gutenberg editáveis, com placeholders de mídia substituíveis, vitrines manuais `petshop/product-grid`, navegação e gates PHP/browser validados |
| [019-area-profissionais-laceiros.md](./019-area-profissionais-laceiros.md) | Pendente | Primeira entrega institucional para profissionais/laceiros; editor e área restrita ficam condicionados a validação comercial |
| [020-header-checkout-sem-distracoes.md](./020-header-checkout-sem-distracoes.md) | Concluido | Header reduzido do checkout redesenhado com logo global, mensagem de seguranca administravel, atendimento global, matriz responsiva/estabilidade e gates PHP/browser validados |
| [021-filtros-catalogo-funcionais.md](./021-filtros-catalogo-funcionais.md) | Concluído | Entrega em `master`. Filtros com toolbar/chips, accordions acessíveis, ação fixa, drawer responsivo e gate browser 021 validados em 1440/1024/768/390 |
| [022-icones-vitrine-upload-livre.md](./022-icones-vitrine-upload-livre.md) | Concluído | Entrega em `master`. Ícone personalizado da vitrine por categoria via Biblioteca de mídia; galeria como fallback; docs e gates PHP/browser validados |
| [023-rodape-institucional-editavel.md](./023-rodape-institucional-editavel.md) | Concluído | Customizer + composição da referência (4 colunas, ícones, selos); gates PHP/browser |
| [024-carrossel-banner-promocional.md](./024-carrossel-banner-promocional.md) | Concluído | Até 3 banners, tempo por imagem (padrão 10 s), autoplay com setas/indicadores sobrepostos; gates PHP/browser |
| [025-cadastro-senha-escolhida.md](./025-cadastro-senha-escolhida.md) | Pendente | Cadastro PF/PJ, telefone, endereço com ViaCEP e senha escolhida; remove senha temporária. ClickUp [86e2xz60k](https://app.clickup.com/t/86e2xz60k) |
| [026-checkout-dados-salvos-viacep.md](./026-checkout-dados-salvos-viacep.md) | Pendente | Checkout hidrata dados da conta e preenche endereço via ViaCEP ao informar o CEP. ClickUp [86e2xzer3](https://app.clickup.com/t/86e2xzer3) |
| [027-calculadora-frete-hub.md](./027-calculadora-frete-hub.md) | Concluído | Hub de frete único na PDP; exibe todos os métodos WooCommerce ativos retornados, incluindo Virtuaria/Melhor Envio; CEP persiste; preço sem entidade HTML; gates PHP/browser validados. ClickUp [86e2xzf9w](https://app.clickup.com/t/86e2xzf9w) |
| [028-recuperacao-pagamento-pendente.md](./028-recuperacao-pagamento-pendente.md) | Pendente | Um e-mail nativo de pagamento pendente no WooCommerce 11+; sem plugin de recovery. ClickUp [86e2xzfdy](https://app.clickup.com/t/86e2xzfdy) |
| [029-retorno-mercado-pago-pix.md](./029-retorno-mercado-pago-pix.md) | Pendente | Após Pix aprovado, voltar à loja: Pedidos (logado) ou confirmação (visitante). ClickUp [86e2xzgqb](https://app.clickup.com/t/86e2xzgqb) |
| [030-frase-pedido-recebido.md](./030-frase-pedido-recebido.md) | Concluído | Confirmação: “Parabéns! Seu pedido foi recebido!” editável no Personalizar. ClickUp [86e2xzmck](https://app.clickup.com/t/86e2xzmck) |
| [031-card-variavel-comprar-preco.md](./031-card-variavel-comprar-preco.md) | Pendente | Card variável: chips, um preço, Comprar agora; sem faixa nem “Ver opções”. ClickUp [86e2xzn0r](https://app.clickup.com/t/86e2xzn0r) |
| [032-busca-lupa-enter-resultados.md](./032-busca-lupa-enter-resultados.md) | Concluído | Lupa e Enter abrem resultados com `s` + `post_type=product`; canonical/filtros preservam busca; SKU exato segue PDP. Gates PHP/browser validados. ClickUp [86e2yy549](https://app.clickup.com/t/86e2yy549) |
| [033-rodape-aproximacao-mockup.md](./033-rodape-aproximacao-mockup.md) | Pendente | Rodapé: três faixas, títulos com sublinhado, selos sem bloco teal-900. ClickUp [86e2yy6mc](https://app.clickup.com/t/86e2yy6mc) |
| [034-layout-emails-compra.md](./034-layout-emails-compra.md) | Concluído | Casco global aplicado aos e-mails HTML WooCommerce; composição de compra nos avisos de cliente; tokens 014, CTAs, rastreio, plain text preservado e persistência validados. ClickUp [86e2yypv9](https://app.clickup.com/t/86e2yypv9) |
| [035-dropdown-subcategorias-menu-comercial.md](./035-dropdown-subcategorias-menu-comercial.md) | Pendente | Menu comercial: subcategorias em dropdown vertical (padrão Moda Bicho), não soltas na faixa. ClickUp [86e31cb6z](https://app.clickup.com/t/86e31cb6z) |
| [036-dependencias-frete-checkout-versionadas.md](./036-dependencias-frete-checkout-versionadas.md) | Concluído | Melhor Envio 2.16.6 e Calculadora BR 4.17.1 versionados com vendor, Docker/deploy/gates reconciliados e Brazilian Market mantido fora para evitar conflito |
## Ordem recomendada de execução

```
006 (infra/CI/docs)
  ├── 007 (refatoração plugin — com rede de segurança)
  └── 009 (design system / a11y / checkout CSS)

005 (storefront comercial — sessões 03–08, conteúdo e produto)
  └── Sessão 03 bloqueada por fotos reais; 04–08 após ou em paralelo com 009
      ├── 010 (layout vitrines Home — especializa 005 Sessões 04–06)
      │   └── 016 (bloco Gutenberg único para seleção das vitrines)
      └── 011 (banners gerenciáveis na Home via Gutenberg)

007 (PSR-4, ciclo de vida e modularização do petshop-core)
  └── 013 (usabilidade e páginas WooCommerce)
      └── 012 (personalizador, arquivos privados e fila de produção)

009 + 011 (base visual e campanhas)
  └── 014 (evolução da identidade visual e campanhas editoriais)
      └── 015 (seção de atendimento editorial da Home)

013 (fluxo WooCommerce implementado)
  ├── 017 (fechamento P0 para publicação: pagamento, frete, políticas, SEO, CWV, backup e a11y)
  ├── 018 (páginas comerciais P1 após 016; publicação operacional completa respeita gates do 017)
  ├── 019 (profissionais/laceiros como entrega institucional P2)
  ├── 020 (header de checkout sem distrações e com aparência intencional)
  ├── 021 (filtros de catálogo funcionais e responsivos)
  ├── 022 (ícones de vitrine por upload livre)
  ├── 023 (rodapé institucional editável — Customizer/menus + composição da referência)
  ├── 024 (carrossel do banner promocional — até 3 imagens, autoplay configurável)
  ├── 025 (cadastro com senha escolhida; remove senha temporária do 013)
  ├── 026 (checkout hidrata dados da conta e ViaCEP no CEP)
  ├── 027 (calculadora de frete única; Virtuaria + Melhor Envio)
  ├── 028 (recuperação de pagamento pendente; WooCommerce 11+ nativo)
  ├── 029 (retorno à loja após Pix Mercado Pago)
  ├── 030 (frase da confirmação: Parabéns! Seu pedido foi recebido!)
  ├── 031 (card variável: Comprar agora + preço único)
  ├── 032 (busca: lupa e Enter listam produtos; query `s` + `post_type=product`)
  ├── 033 (rodapé: aproximação visual ao mockup de três faixas)
  ├── 034 (layout HTML dos e-mails de compra; cores da identidade atual)
  ├── 035 (dropdown de subcategorias no menu comercial do header)
  └── 036 (dependencias de frete e checkout versionadas)
```

## Origem

Planos 006–009 derivados da review técnica do repositório (2026-07-31): arquitetura, testes, infraestrutura, UI/UX e documentação.

Planos 017–019 derivados da análise de lacunas do `Orcode_Requisitos_Website_Loja_Pet_v2.pdf` em 2026-08-11, cruzada com o estado dos Planos 012, 013, 015 e 016.

Plano 020 derivado da avaliação do checkout em 2026-08-12: manter navegação reduzida, mas redesenhar o header para comunicar fluxo de compra seguro e não parecer navbar quebrada.

Plano 021 derivado da avaliação de UX dos filtros em 2026-08-15, cruzada com padrões observados em Petlove, Petz, Cobasi, Chewy e Amazon.

Plano 022 derivado da necessidade de permitir que o cliente forneça ícones próprios para a vitrine de categorias sem alterar código, preservando a galeria atual como fallback.

Plano 023 derivado do pedido de rodapé mais editável (2026-08-19): mockup como composição visual e inventário de campos (redes, atendimento, selos, legal), com tokens do Plano 014.

Plano 024 derivado do pedido de transformar o banner promocional da Home em carrossel (2026-08-19): até 3 imagens, tempo de visualização configurável por imagem (padrão 10 s) e controles sobrepostos à arte.

Plano 025 derivado da fricção de cadastro (2026-08-21): senha temporária por e-mail após informar o endereço eletrônico; o cliente precisa escolher a senha, informar CPF/CNPJ e endereço, e entrar sem validar a conta no e-mail.

Plano 026 derivado do checkout (2026-08-22): dados já gravados na conta devem hidratar o Checkout Block; CEP consulta ViaCEP e preenche o endereço.

Plano 027 derivado da PDP com duas calculadoras (2026-08-22): a UI da loja vira hub WooCommerce e exibe todos os métodos cadastrados e ativos retornados para o CEP, incluindo Virtuaria e Melhor Envio.

Plano 028 derivado da recuperação de pedidos em Pagamento pendente (2026-08-22): um e-mail nativo do WooCommerce 11+, sem plugin extra.

Plano 029 derivado da reclamação de Pix no Checkout Pro (2026-08-22): após aprovação, redirecionar à loja (Pedidos ou confirmação).

Plano 030 derivado do pedido de copy na confirmação (2026-08-22): “Parabéns! Seu pedido foi recebido!” administrável.

Plano 031 derivado da comparação de card variável (2026-08-22): Comprar agora, chips e um preço, sem faixa.

Plano 032 derivado da busca do header (2026-08-24): lupa e Enter não listam produtos; corrigir a query string (`s` + `post_type=product`).

Plano 033 derivado do print do rodapé (2026-08-24): aproximar a composição visual (três faixas, sublinhado, selos com filetes) sem reabrir os campos do 023.

Plano 034 derivado do mockup de e-mail “Pagamento confirmado” (2026-08-24): redesenhar o casco HTML dos avisos de compra; composição da arte, tokens da loja (não os hex do PNG).

Plano 035 derivado do print da Moda Bicho (2026-08-30): no menu comercial do header (`menu-navegacao-comercial-container`), as subcategorias abrem em dropdown vertical sob o pai, não na faixa.

Plano 036 derivado do aviso administrativo do Melhor Envio (2026-08-31): o plugin exige uma base brasileira de checkout/frete ativa e precisa deixar de depender de instalacao manual no runtime; Melhor Envio e a base escolhida passam a ser versionados sem credenciais.

**Última atualização:** 2026-08-31 (Plano 036 concluído: dependencias de frete e checkout versionadas)
