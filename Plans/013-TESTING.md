# Plano 013 — matriz de testes e evidências

## Pré-requisitos

- Docker Compose 2.32.2 ou superior, stack local saudável e `.env` local não versionado.
- Plugin `petshop-core`, WooCommerce e Mercado Pago ativos; credenciais sandbox somente no painel/ambiente.
- Fixtures: um produto simples, um variável e um preparado para o Plano 012, todos administráveis em Produtos.

## Comandos obrigatórios

| Gate | Comando ou método | Evidência esperada |
| --- | --- | --- |
| PHPUnit | `npm test` | filtros/canonical/301/conta/rastreamento verdes |
| Gates legados | `npm run validate` | provisionamento, smoke e persistência verdes |
| Browser 013 | `docker compose --profile tools run --rm -e PETSHOP_BASE_URL=http://host.docker.internal:8888 -e PETSHOP_CANONICAL_HOST=localhost:8888 node node /workspace/scripts/validate-013-browser.mjs` | 390/768/1024/1440, rotas, busca, filtro, PDP, cart/checkout e conta |
| Persistência 013 | `docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/test-013-persistence.php` | páginas, produto, imagem/alt e configurações preservados |
| HPOS | `docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/validate-013-hpos.php` | rastreamento salvo/lido via CRUD e nenhum acesso direto a tabelas de pedido |
| Logs | `docker compose logs --since 10m wordpress` | sem fatal/uncaught novo |
| Mercado Pago | fluxo manual sandbox aprovado/recusado/pendente | um pedido por tentativa; IDs registrados sem credencial na evidência |
| Acessibilidade humana | NVDA ou VoiceOver | filtro, variação, erros, checkout e confirmação compreensíveis |

## Ledger de execução

| Etapa | Tentativa 1 | Tentativa 2 | Estado atual |
| --- | --- | --- | --- |
| PHPUnit baseline | saída perdida pelo agrupamento, execução iniciada | 12 testes/17 asserções, passou | baseline verde |
| Gate legado completo | falhou em persistência da imagem | falhou em persistência da imagem | correção obrigatória no plano |
| Browser interno desktop | sessão expirou | timeout de navegação | substituído por Playwright containerizado |
| Catálogo Playwright | conexão interna recusada | passou via `host.docker.internal` | baseline verde |
| Cart/Checkout Playwright | conexão interna recusada | carrinho vazio sem CTA primário | correção obrigatória no plano |
| PHPUnit final | 15 testes/25 asserções | repetido após hardening | verde |
| Gates PHP/persistência/HPOS/segurança | persistência e HPOS verdes | suíte integrada verde | verde |
| Browser 013 | foco do drawer e host absoluto da Store API falharam | 390/768/1024/1440, busca, filtros, PDP/CEP, cart/checkout e conta verdes | verde |
| Browser integrado legado + 013 | loop canonical e sessão Store API corrigidos | `npm run validate -- --browser` concluído | verde |
| Revisão crítica | 1 P0 e P1 em analytics, políticas, frete, conta, foco e persistência | correções aplicadas e gates ampliados | rechecagem sem P0/P1 remanescente |

## Persistência editorial

O teste deve alterar pelo painel/API WordPress, reprovisionar e comparar novamente:

- slug/título e conteúdo Gutenberg das páginas;
- descrição/imagem/alt de categoria;
- textos, atributos, medidas, prazo, imagem e alt dos produtos-amostra;
- estrutura editorial do carrinho, checkout, conta e políticas;
- configurações globais e rastreamento do pedido.

Nenhum teste pode considerar como sucesso um valor restaurado pelo código sobre a edição do cliente.

## Bloqueios que impedem conclusão

- credenciais Mercado Pago sandbox ausentes ou gateway incompatível com Checkout Block;
- fornecedor/credenciais/zonas reais de frete ausentes para publicação;
- texto jurídico ainda não aprovado;
- gate humano NVDA/VoiceOver sem execução.

## Resultado automatizado de 2026-08-08

- `npm test`: 15 testes, 25 asserções, verde.
- `npm run validate -- --browser`: provisionamento, gates legados, persistência 004b/005/013, segurança de `order_key`, metabox HPOS, Home, catálogo, busca, PDP, CEP, carrinho e checkout verdes.
- Evidências PNG/JSON locais em `.local/evidence/013`, `.local/evidence/005` e `.local/evidence/009` (não versionadas).
- Avisos `sendmail: not found` ocorreram apenas nos pedidos temporários de teste; não houve fatal/uncaught no WordPress.
