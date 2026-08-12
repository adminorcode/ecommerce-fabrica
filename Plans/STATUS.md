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
| [012-personalizador-produtos-e-fila-producao.md](./012-personalizador-produtos-e-fila-producao.md) | Pendente | Editor open source no `petshop-core`, Store API/HPOS, arquivos privados e fila WooCommerce → Personalizações |
| [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) | Em andamento | Código, gates automatizados e Virtuaria Correios local validados; aguarda Mercado Pago sandbox, jurídico, origem/contrato reais e NVDA/VoiceOver |
| [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md) | Concluído | Tokens AUTellê, Nunito Sans, campanha editorial Gutenberg e rodapé no tom escuro anterior validados em PHP/browser |
| [015-secao-atendimento-home.md](./015-secao-atendimento-home.md) | Pendente | Secao de atendimento Gutenberg na Home com CTA preferencial para WhatsApp, imagens 1920 x 640 e 1080 x 1350, em substituicao ao banner-imagem; depende do 014 |
| [016-vitrine-produtos-gutenberg.md](./016-vitrine-produtos-gutenberg.md) | Concluído | Bloco único `petshop/product-grid`, variações manual/categoria/popular/sazonal, migração schema 25 e gates PHP/browser/editor validados |
| [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) | Pendente | Fecha bloqueios P0 do PDF Orcode: Mercado Pago sandbox, frete real, políticas, e-mails, SEO, CWV, backup, monitoramento e a11y manual |
| [018-paginas-comerciais-p1.md](./018-paginas-comerciais-p1.md) | Pendente | Eventos Pet, Animal Republik, premium, Por Raça, bandanas/adesivos e capas de chuva com conteúdo administrável e produtos reais |
| [019-area-profissionais-laceiros.md](./019-area-profissionais-laceiros.md) | Pendente | Primeira entrega institucional para profissionais/laceiros; editor e área restrita ficam condicionados a validação comercial |
| [020-header-checkout-sem-distracoes.md](./020-header-checkout-sem-distracoes.md) | Concluido | Header reduzido do checkout redesenhado com logo global, mensagem de seguranca administravel, atendimento global, matriz responsiva/estabilidade e gates PHP/browser validados |

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
  └── 017 (fechamento P0 para publicação: pagamento, frete, políticas, SEO, CWV, backup e a11y)
      ├── 018 (páginas comerciais P1 quando houver conteúdo e materiais aprovados)
      ├── 019 (profissionais/laceiros como entrega institucional P2)
      └── 020 (header de checkout sem distrações e com aparência intencional)
```

## Origem

Planos 006–009 derivados da review técnica do repositório (2026-07-31): arquitetura, testes, infraestrutura, UI/UX e documentação.

Planos 017–019 derivados da análise de lacunas do `Orcode_Requisitos_Website_Loja_Pet_v2.pdf` em 2026-08-11, cruzada com o estado dos Planos 012, 013, 015 e 016.

Plano 020 derivado da avaliação do checkout em 2026-08-12: manter navegação reduzida, mas redesenhar o header para comunicar fluxo de compra seguro e não parecer navbar quebrada.

**Última atualização:** 2026-08-12 (Plano 020 criado para header de checkout sem distrações; próximos recomendados: 015, 017 e 020)
