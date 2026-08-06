# Status dos planos

| Plano | Status | Observação |
|---|---|---|
| [000-bootstrap-woocommerce-local.md](./000-bootstrap-woocommerce-local.md) | Concluído | Ambiente, versões, plugins, tema, loja e painel validados em runtime |
| [001-instalacao-blocksy-petsy.md](./001-instalacao-blocksy-petsy.md) | Concluído | Blocksy/Petsy com Gutenberg importado; child theme e fluxos essenciais validados |
| [003-ambiente-totalmente-docker.md](./003-ambiente-totalmente-docker.md) | Em andamento | Stack validada; docs/CI unificados no 006; wp-env legado pendente remoção |
| [004-identidade-visual-e-navegabilidade.md](./004-identidade-visual-e-navegabilidade.md) | Em andamento | Implementação e 004b validados; a11y manual pendente (Plano 009 Etapa 5) |
| [004b-correcao-vitrine-e-catalogo.md](./004b-correcao-vitrine-e-catalogo.md) | Concluído | Catálogo demonstrável, hero full-bleed editável, densidade comercial e persistência validados |
| [005-refinamento-comercial-do-storefront.md](./005-refinamento-comercial-do-storefront.md) | Bloqueado | Sessões 01–02 concluídas; Sessão 03 aguarda fotografias reais; Sessões 04–08 pendentes |
| [006-infraestrutura-ci-e-documentacao.md](./006-infraestrutura-ci-e-documentacao.md) | Concluído | Docker/docs unificados, run-gates, CI workflow; gates PHP validados em runtime |
| [007-refatoracao-petshop-core.md](./007-refatoracao-petshop-core.md) | Pendente | Decomposição do plugin, migrador, Customizer; após baseline de testes |
| [008-suite-de-testes-automatizados.md](./008-suite-de-testes-automatizados.md) | Em andamento | PHPUnit e gates Playwright implementados; validação legada bloqueada por produto publicado sem imagem/alt |
| [009-design-system-acessibilidade-e-checkout.md](./009-design-system-acessibilidade-e-checkout.md) | Concluído | Tokens, cart/checkout, a11y técnica; NVDA/VoiceOver manual pendente |
| [010-layout-secoes-produto-home.md](./010-layout-secoes-produto-home.md) | Concluído | Vitrines da Home: cards compactos, cabeçalho “Ver todos”, badges reais, schema 17 |
| [010b-wishlist-lista-de-desejos.md](./010b-wishlist-lista-de-desejos.md) | Concluído | Página, endpoint Minha conta, link no header, merge localStorage → conta |
| [011-banners-gerenciaveis-home.md](./011-banners-gerenciaveis-home.md) | Concluído | Blocos `petshop/home-campaigns` e `petshop/home-campaign`; carrossel manual acessível; persistência validada |
| [012-personalizador-produtos-e-fila-producao.md](./012-personalizador-produtos-e-fila-producao.md) | Pendente | Editor open source no `petshop-core`, Store API/HPOS, arquivos privados e fila WooCommerce → Personalizações |
| [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) | Pendente | Usabilidade WooCommerce, rotas, filtros, produto, frete, checkout e conta; depende de 008 e 007 |
| [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md) | Pendente | Tokens de marca, Nunito Sans, componentes acessíveis, footer teal e campanhas editoriais Gutenberg; depende de 009 e 011 |
| [015-secao-atendimento-home.md](./015-secao-atendimento-home.md) | Pendente | Seção de atendimento Gutenberg na Home, em substituição ao banner-imagem; depende do 014 |

## Ordem recomendada de execução

```
006 (infra/CI/docs)
  ├── 008 (testes automatizados)
  │     └── 007 (refatoração plugin — com rede de segurança)
  └── 009 (design system / a11y / checkout CSS)

005 (storefront comercial — sessões 03–08, conteúdo e produto)
  └── Sessão 03 bloqueada por fotos reais; 04–08 após ou em paralelo com 009
      └── 010 (layout vitrines Home — especializa 005 Sessões 04–06)
      └── 011 (banners gerenciáveis na Home via Gutenberg)

008 (baseline de testes)
  └── 007 (PSR-4, ciclo de vida e modularização do petshop-core)
      └── 012 (personalizador, arquivos privados e fila de produção)

009 + 011 (base visual e campanhas)
  └── 014 (evolução da identidade visual e campanhas editoriais)
      └── 015 (seção de atendimento editorial da Home)
```

## Origem

Planos 006–009 derivados da review técnica do repositório (2026-07-31): arquitetura, testes, infraestrutura, UI/UX e documentação.

**Última atualização:** 2026-08-03 (Plano 012 adicionado)
