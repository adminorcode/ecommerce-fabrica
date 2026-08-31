---
name: preparar-deploy
description: >-
  Prepara o pacote de deploy HostGator/cPanel do ecommerce-petshop (wp-content
  copiável e petshop-db.sql). Use when the user asks to preparar deploy, exportar
  wp-content, gerar pacote de publicação, empacotar tema/plugin/uploads/banco,
  publish to cPanel/HostGator, or when production fatals on Composer autoload
  (myclabs, deep_copy.php, phpunit vendor, Permission denied on vendor).
---

# Preparar deploy

Gera só `wp-content/` e o dump SQL. Tema e plugin saem do worktree; o Docker
entra só para buscar uploads e exportar o banco.

## Quando usar

- usuário pede para **preparar deploy**, exportar `wp-content` ou gerar pacote cPanel/HostGator
- publicar a loja após merge em `master`
- fatal em produção com `deep_copy.php`, `myclabs` ou `Permission denied` em `vendor/`

## Pré-requisitos

1. Stack Docker com WordPress em execução (`docker compose up -d --wait` se necessário).
2. Não usar `docker compose down --volumes`.

## Autoload de produção (obrigatório)

O `petshop-core.php` faz `require vendor/autoload.php` em todo request. O
`composer.json` tem PHPUnit em `require-dev`. Um `composer install` local gera
autoload que **exige** `vendor/myclabs/deep-copy/.../deep_copy.php`.

**Proibido:**

- copiar `vendor/` de desenvolvimento e só apagar pastas (`myclabs`, `phpunit`,
  `sebastian`) — o autoload continua apontando para elas e o PHP fataliza no
  cPanel (`Failed opening required .../deep_copy.php`, muitas vezes como
  `Permission denied`)
- enviar o `vendor/` do worktree sem `composer dump-autoload --no-dev`
- “corrigir” produção com chmod na pasta `myclabs` leftover

**Obrigatório no `scripts/prepare-deploy.mjs` (não fazer na mão):**

1. Excluir `tests`, `node_modules` e vendor de desenvolvimento (`phpunit`,
   `myclabs`, `sebastian`, `phar-io`, `theseer`, `nikic`).
2. Rodar no plugin **já copiado para o pacote**:
   `composer dump-autoload --no-dev --optimize` (via Docker `cli`).
3. Falhar o pacote se `vendor/composer/autoload_files.php`,
   `autoload_static.php` ou `autoload_psr4.php` ainda citarem `myclabs`,
   `phpunit/phpunit` ou `deep-copy`.
4. Só então exportar uploads/SQL e declarar sucesso.

`installed.json` / `installed.php` podem ainda listar pacotes dev; isso não
carrega o arquivo. O que mata o site é o **autoload**.

Depois de publicar: substituir o `petshop-core` inteiro. Apagar leftovers no
servidor (`vendor/myclabs`, `vendor/phpunit`, `vendor/sebastian`). Pastas `755`,
arquivos `644`.

## Execução

Na raiz do repositório:

```bash
export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'
npm run prepare:deploy
```

Equivalente: `node scripts/prepare-deploy.mjs`.

Se falhar, corrigir o bloqueio e repetir. Não declarar sucesso sem o pacote.
Não declarar sucesso se o dump `--no-dev` não rodou.

## O que o script produz

Pasta: `outputs/deploy-cpanel/<stamp>/`

| Artefato | Uso |
|---|---|
| `wp-content/` | Copiar para `public_html/wp-content/` (`themes/petshop-theme`, `plugins/petshop-core`, `uploads`) |
| `petshop-db.sql` | Importar no MySQL do servidor |
| `MANIFEST.txt` / `WHERE.txt` | branch, commit e caminho absoluto |

Regras:

- tema e plugin vêm do disco; exclui `node_modules`, `tests` e vendor de desenvolvimento
- autoload de produção = `composer dump-autoload --no-dev --optimize` no pacote
- uploads e SQL vêm do contêiner `wordpress` já em execução (`exec`/`cp`, sem `compose run --rm`)
- tema deve declarar `Template: blocksy`

Guia operacional: `docs/guia-publicacao-hostgator-cpanel.md`.

## Relatório final (obrigatório)

A resposta **deve** começar assim, com o caminho impresso pelo script (`WHERE.txt`):

```markdown
### Pacote de deploy pronto

Pasta: `<caminho-absoluto>`

- `wp-content/` — copiar para o servidor
- `petshop-db.sql` — importar no MySQL
```

## Fora do escopo

- ZIP de tema/plugin, `uploads.tar.gz` e `wp-content-deploy.tar.gz`
- `docker cp` de tema/plugin para o contêiner
- upload FTP automático
- search-replace de URL no SQL sem pedido explícito
- versionar `outputs/` no Git
