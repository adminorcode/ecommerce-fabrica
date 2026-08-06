# Plano 008 — matriz de testes automatizados

## Pré-requisitos

- Docker Desktop em execução e `.env` criado a partir de `.env.example`;
- stack local ativa com `docker compose up --watch` ou `docker compose up -d --build --wait`;
- após alterar `composer.lock`, `package-lock.json` ou Dockerfiles: `docker compose build wordpress node`.

## Comandos

| Cobertura | Comando | Resultado esperado |
| --- | --- | --- |
| Unidade: filtros, SKU e query string | `docker compose --profile test run --rm test-runner` | PHPUnit com 6 ou mais testes aprovados |
| Smoke/persistência legada | `npm run validate` | Validators PHP aprovados |
| Header, hero e catálogo | `npm run validate -- --browser` | Gates Playwright no serviço `node` aprovados |
| PDP | `npm run validate -- --pdp` | Preço, CTA e aviso administrável visíveis |
| Carrinho | `npm run validate -- --cart` | CTA adiciona item e minicarrinho incrementa |

`PETSHOP_BASE_URL` é opcional. No contêiner, o Compose usa por padrão `http://host.docker.internal:8888`, que preserva o host canônico do WordPress; fora dele, os scripts mantêm `http://localhost:8888` como fallback.

## CI

`validate.yml` executa PHPUnit em todo push para `master` e em pull requests. `browser-gates.yml` é manual (`workflow_dispatch`) para a validação de release/storefront; ele sobe a stack, executa `run-gates --browser` e anexa `.local/evidence` caso falhe.

## Diagnóstico

- Falha de PHPUnit: execute novamente o comando do profile `test`; a fonte montada é o worktree atual. Se mudaram dependências, reconstrua a imagem `wordpress`.
- Falha de Playwright: execute o comando específico e consulte `.local/evidence/`. Recrie a imagem `node` se Playwright ou seu lockfile mudaram.
- Falha de rota HTTP: execute `npm run validate` primeiro, pois os gates browser dependem do seed/migração local.
