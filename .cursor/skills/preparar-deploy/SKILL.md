---
name: preparar-deploy
description: >-
  Prepara o pacote de deploy HostGator/cPanel do ecommerce-petshop (wp-content
  copiável, petshop-theme.zip, petshop-core.zip, uploads.tar.gz, petshop-db.sql).
  Use when the user asks to preparar deploy, exportar wp-content, gerar pacote
  de publicação, empacotar tema/plugin/uploads/banco, or publish to cPanel/HostGator.
---

# Preparar deploy

Gera artefatos de publicação a partir do ambiente Docker atual, sem enviar o
repositório inteiro.

## Quando usar

- usuário pede para **preparar deploy**, exportar `wp-content`, gerar pacote cPanel/HostGator
- empacotar tema/plugin/uploads/banco para copiar ao servidor
- publicar a loja após merge em `master`

## Pré-requisitos

1. Stack Docker com WordPress em execução (`docker compose up -d --wait` se necessário).
2. Python 3 disponível no host (`python` ou `python3`) para ZIP/TAR.
3. Não usar `docker compose down --volumes`.

## Execução

Na raiz do repositório:

```bash
npm run prepare:deploy
```

Equivalente:

```bash
node scripts/prepare-deploy.mjs
```

No Windows/Git Bash, preserve paths Docker:

```bash
export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'
npm run prepare:deploy
```

Se o comando falhar, corrigir o bloqueio (Docker parado, Python ausente, arquivo
obrigatório faltando) e **repetir até passar**. Não declarar sucesso sem o pacote.

## O que o script produz

Pasta: `outputs/deploy-cpanel/<stamp>/`

| Artefato | Uso |
|---|---|
| `wp-content/` | Árvore pronta para copiar (`themes/petshop-theme`, `plugins/petshop-core`, `uploads`) |
| `wp-content-deploy.tar.gz` | Mesma árvore em um único arquivo |
| `petshop-theme.zip` | Extrair em `public_html/wp-content/themes/` |
| `petshop-core.zip` | Extrair em `public_html/wp-content/plugins/` (sem tests/phpunit) |
| `uploads.tar.gz` | Extrair em `public_html/wp-content/` |
| `petshop-db.sql` | Importar no MySQL do servidor |
| `MANIFEST.txt` | branch, commit, home/siteurl, versão do tema |
| `WHERE.txt` | caminho absoluto da pasta do pacote |

Regras do pacote:

- inclui apenas código próprio (`petshop-theme`, `petshop-core`) + uploads + SQL
- exclui `tests`, `node_modules` e vendor de desenvolvimento (PHPUnit etc.)
- tema deve declarar `Template: blocksy`
- sincroniza tema/plugin do worktree para o contêiner antes de exportar

Guia operacional: `docs/guia-publicacao-hostgator-cpanel.md`.

## Relatório final (obrigatório)

Ao terminar com sucesso, a resposta ao usuário **deve** começar informando onde
está o pacote, com caminho absoluto, neste formato:

```markdown
### Pacote de deploy pronto

Pasta: `<caminho-absoluto>`

- `wp-content/` — copiar para o servidor
- `wp-content-deploy.tar.gz` — alternativa em arquivo único
- `petshop-theme.zip` / `petshop-core.zip` / `uploads.tar.gz` / `petshop-db.sql`
```

Use o caminho impresso pelo script (também gravado em `WHERE.txt`). Não invente
outro diretório.

## Fora do escopo

- upload FTP automático (ver `scripts/deploy-hostgator-ftp.ps1` só se o usuário pedir)
- instalar WordPress/Blocksy/WooCommerce no servidor
- search-replace de URL no SQL sem pedido explícito
- versionar o conteúdo de `outputs/` no Git
