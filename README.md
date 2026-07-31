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
# Sync contínuo do plugin e tema
docker compose up --watch

# WP-CLI
npm run wp -- plugin list

# Validators PHP (provisiona + smoke)
npm run validate

# Testes (PHPUnit quando configurado — Plano 008)
npm run test
```

## Comandos npm

| Script | Ação |
|--------|------|
| `bootstrap` | `docker compose up -d --build --wait` |
| `up` | `docker compose up --watch` |
| `down` | `docker compose down` |
| `validate` | `scripts/run-gates.sh` (bash) ou `scripts/run-gates.ps1` |
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
