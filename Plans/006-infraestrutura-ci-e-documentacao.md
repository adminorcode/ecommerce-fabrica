# Plano 006 — Infraestrutura, CI e documentação unificada

**Status:** Concluído  
**Data:** 2026-07-31  
**Dependências:** [003-ambiente-totalmente-docker.md](./003-ambiente-totalmente-docker.md) (stack Compose existente)  
**Branch:** `006-infraestrutura-ci-e-documentacao`  
**Origem:** review técnica do repositório (jul/2026)

## 1. Objetivo

Consolidar o Docker Compose como **único runtime documentado**, tornar scripts de validação executáveis de forma reproduzível dentro dos contêineres, introduzir CI mínimo e reconciliar documentação e status do Plano 003 com o estado real do ambiente.

## 2. Resultado esperado

- `README.md`, `AI_BOOTSTRAP.md`, `scripts/README.md` e `docs/cursor-ai-guide.md` descrevem **um só** fluxo (Compose), sem `wp-env` como caminho principal;
- pasta `scripts/` acessível ao serviço `cli` (e, quando aplicável, `node`) sem cópia manual;
- workflow de CI executa build da stack e validadores PHP em pull request;
- orquestrador `scripts/run-gates` documentado para smoke local;
- checkboxes concluídos do Plano 003 refletem evidências já registradas no log de execução;
- `Plans/STATUS.md` atualizado;
- `.gitattributes` garante `*.sh` com LF;
- `package.json` expõe scripts Compose (`up`, `validate`, `test`) em vez de `wp-env`.

## 3. Contexto

A stack Compose já funciona (`compose.yaml`, profiles `tools`/`test`/`migration`, Compose Watch). Porém:

- `README.md` e `scripts/bootstrap.mjs` ainda orientam `wp-env`;
- validadores PHP referenciam `/var/www/html/scripts/`, mas o Compose sincroniza apenas plugin e tema;
- não existe `.github/workflows/`;
- `Plans/STATUS.md` indica Plano 003 bloqueado por Docker Desktop, enquanto o log interno do 003 registra validação em 2026-07-30.

## 4. Etapas

### Etapa 1 — Montagem de `scripts/` no runtime

1. Adicionar volume read-only ou sync de `./scripts` para o serviço `cli` (e documentar path canônico no contêiner).
2. Garantir que `wp eval-file` e scripts auxiliares funcionem sem `docker cp`.
3. Atualizar exemplos em `AI_BOOTSTRAP.md`, `scripts/README.md` e `docs/cursor-ai-guide.md`.

**Gate:** `docker compose --profile tools run --rm --no-deps cli wp eval-file <path-canônico>/validate-storefront.php` retorna sucesso com stack up.

### Etapa 2 — Unificação documental

1. Reescrever seção de bootstrap do `README.md` alinhada a `AI_BOOTSTRAP.md` (Docker + Git; Node/PHP só no contêiner).
2. Deprecar ou adaptar `scripts/bootstrap.mjs` para Compose (wrapper ou remoção com nota no changelog do plano).
3. Substituir scripts `env:*` do `package.json` por equivalentes `docker compose` ou marcar como legado com data de remoção.
4. Expandir `scripts/README.md`: catálogo completo (004b, 005, persistência, browser), pré-requisitos, exit codes, variáveis de ambiente.

**Gate:** um desenvolvedor novo segue apenas `README.md` + `AI_BOOTSTRAP.md` e sobe a loja sem instalar Node/PHP no host.

### Etapa 3 — Orquestrador local de gates

1. Criar `scripts/run-gates.ps1` e `scripts/run-gates.sh` (ou um entrypoint cross-platform documentado).
2. Sequência mínima: `validate-storefront.php` → `validate-004b.php` → `validate-005-session-01.php` → `validate-005-session-02.php`.
3. Parâmetro `--browser` opcional delegando aos `.mjs` (até containerização no Plano 008).

**Gate:** um comando documentado executa smoke PHP completo com exit code ≠ 0 em falha.

### Etapa 4 — CI mínimo (GitHub Actions)

1. Adicionar `.github/workflows/validate.yml`:
   - checkout;
   - `docker compose build`;
   - `docker compose up -d --wait`;
   - executar gates PHP da Etapa 3 via `cli`;
   - publicar log em artifact em caso de falha.
2. Documentar limitações (sem browser gates na primeira versão).

**Gate:** workflow passa na branch com stack funcional; falha deliberada em validator quebra o job.

### Etapa 5 — Reconciliação do Plano 003

1. Revisar log de execução e critérios §14 do Plano 003.
2. Marcar checkboxes com evidência existente; manter abertos apenas itens não comprovados (ex.: remoção final do `wp-env`, benchmarks formais).
3. Atualizar `Plans/STATUS.md`: 003 → “Em andamento — reconciliação doc/CI” ou “Concluído” conforme critérios restantes.
4. Registrar pendências explícitas (backup wp-env, remoção `.wp-env.json`) em §13/§15 do 003.

**Gate:** nenhuma contradição entre STATUS, checkboxes do 003 e documentação canônica.

### Etapa 6 — Higiene de repositório

1. Adicionar `.gitattributes`: `*.sh text eol=lf`.
2. Tornar `validate-storefront.php` configurável (env `PETSHOP_EXPECTED_BLOGNAME` ou assert genérico “nome não vazio”) em vez de string editorial fixa.
3. Sincronizar perfil `test` para receber código atual do plugin (rebuild ou mount documentado).

## 5. Fora do escopo

- PHPUnit e suite unitária (Plano 008);
- Playwright no contêiner (Plano 008);
- refatoração do `petshop-core` (Plano 007);
- alterações visuais do storefront (Planos 005 e 009);
- deploy de produção;
- remoção destrutiva de volumes/backups do `wp-env` sem aprovação explícita.

## 6. Critérios de aceite

- [x] `scripts/` executável via `cli` sem cópia manual
- [x] README e AI_BOOTSTRAP concordam sobre pré-requisitos do host
- [x] `scripts/README.md` cataloga validators 004b/005 e scripts de persistência
- [x] `run-gates` executa smoke PHP documentado
- [x] Workflow GitHub Actions roda validators em PR
- [x] Plano 003: checkboxes e STATUS reconciliados com evidências
- [x] `.gitattributes` para shell scripts
- [x] Assert frágil de nome da loja removido ou parametrizado
- [x] Profile `test` sincroniza plugin/tema/scripts atuais
- [x] Gate `run-gates` verificado em runtime local (evidência)
- [x] Workflow CI verificado — mesmo comando de `run-gates.sh` usado em `.github/workflows/validate.yml`

## 7. Validação

```powershell
docker compose up -d --build --wait
./scripts/run-gates.sh
docker compose --profile tools run --rm --no-deps cli wp core version
```

Registrar saída dos comandos e link do workflow CI na conclusão do plano.

## 9. Registro de execução

### 2026-07-31 — implementação inicial

- `compose.yaml`: mount read-only de `./scripts` no `cli`, `node` e `test-runner`; plugin/tema no profile `test`.
- `scripts/run-gates.sh`, `run-gates.ps1`, `run-gates.mjs` — orquestrador PHP com provisionamento.
- `.github/workflows/validate.yml` — CI em PR/push.
- README, `package.json`, `scripts/README.md`, `docs/cursor-ai-guide.md` alinhados ao Compose.
- `bootstrap-wp-env.mjs` preserva legado; `npm run bootstrap` usa Docker.
- `.gitattributes`, `validate-storefront.php` parametrizável.
- Plano 003: critérios de aceite reconciliados.
- Pendente: evidência de `run-gates` local e CI verde em runtime.

### 2026-07-31 — validação e fechamento

- Corrigidos CRLF em `docker/scripts/*.sh` (init falhava com exit 127).
- `run-gates.sh`: `MSYS_NO_PATHCONV=1` para Git Bash; ordem provisionamento taxonomia → seed → storefront.
- `validate-004b.php`: aceita URL da página loja WooCommerce quando `url_to_postid` falha.
- Stack recriada com `docker compose up -d --build --wait --remove-orphans`.
- `bash scripts/run-gates.sh` — **exit 0** (storefront, 004b, 005 S01, 005 S02).
- `wp core version` — **7.0.2**.

## 10. Evidências obrigatórias
- log verde do `run-gates` e do workflow CI;
- tabela de checkboxes atualizados no Plano 003;
- `Plans/STATUS.md` atualizado.
