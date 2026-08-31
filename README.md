# Petshop Ecommerce

E-commerce baseado em WordPress e WooCommerce.

## Requisitos no host

- [Docker Desktop](https://docs.docker.com/desktop/) com Docker Compose **2.32.2+**
- [Git](https://git-scm.com/downloads)
- editor e navegador

Node.js, PHP, Composer, MariaDB e WP-CLI **não** são pré-requisitos do host — rodam nos contêineres.

Guia completo: [AI_BOOTSTRAP.md](./AI_BOOTSTRAP.md)

## Bootstrap rápido

```powershell
git clone <url-do-repositorio>
cd ecommerce-petshop
Copy-Item .env.example .env
npm run bootstrap
```

Ou manualmente:

```powershell
docker compose up -d --build --wait
```

URLs locais:

- Loja: <http://localhost:8888>
- Admin: <http://localhost:8888/wp-admin>

Credenciais: ver `.env.example` (somente ambiente local).

## Desenvolvimento

```powershell
# Rebuild das imagens + sync contínuo do plugin e tema
docker compose up --watch --build

# WP-CLI
npm run wp -- plugin list

# Validators PHP (provisiona + smoke)
npm run validate

# Validacao focada nos arquivos alterados
npm run validate:changed

# Validacao focada + browser apenas das areas alteradas
npm run validate:changed:browser

# Testes PHPUnit
npm run test
```

## Comandos npm

| Script | Ação |
|--------|------|
| `bootstrap` | `docker compose up -d --build --wait` |
| `up` | `docker compose up --watch --build` |
| `down` | `docker compose down` |
| `validate` | gate completo: `scripts/run-gates.sh` (bash) ou `scripts/run-gates.ps1` |
| `validate:changed` | lint/check sintatico + validators PHP mapeados pelos arquivos alterados no Git |
| `validate:changed:browser` | igual ao anterior, adicionando somente os browser gates afetados |
| `test` | profile `test` / PHPUnit |
| `wp` | WP-CLI via serviço `cli` |

Scripts legados `env:*` (`wp-env`) estão deprecados — use Docker Compose.

## Organização

- `Plans/`: planos de implementação e [STATUS](./Plans/STATUS.md)
- `wp-content/plugins/petshop-core/`: regras de negócio
- `wp-content/themes/petshop-theme/`: child theme Blocksy
- `scripts/`: validação, seed e automação ([README](./scripts/README.md))
- `docs/cursor-ai-guide.md`: rules/skills para agentes Cursor

Não altere WordPress Core, WooCommerce ou plugins de terceiros diretamente.

## Tema

- Blocksy (pai, não editar)
- `petshop-theme` (child versionado)
- Gutenberg + Stackable

Regras de negócio ficam em `petshop-core`; apresentação em `petshop-theme`.
