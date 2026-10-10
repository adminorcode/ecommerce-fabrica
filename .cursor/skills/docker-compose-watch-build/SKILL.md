---
name: docker-compose-watch-build
description: >-
  Sobe e atualiza o ambiente Docker do ecommerce-petshop com
  `docker compose up --watch --build`. Use ao iniciar a stack, após
  alterar petshop-core, petshop-theme, docker/, Dockerfiles ou lockfiles,
  e antes de validar, smoke, browser ou handoff. Também quando o usuário
  pedir compose up, watch, rebuild, sync do plugin/tema ou código antigo
  no contêiner.
---

# Docker Compose Watch + Build

Comando canônico deste repositório:

```powershell
docker compose up --watch --build
```

Equivalente: `docker compose up --build --watch`. Alias: `npm run up`.

`--build` reconstrói as imagens com o `COPY` atual do worktree.
`--watch` faz `initial_sync` e depois sincroniza `petshop-core` e `petshop-theme`
para o volume `wordpress_runtime` (`develop.watch` em `compose.yaml`).

O host **não** tem PHP/WP-CLI. O volume **não** é bind mount do repo. O `init`
só copia plugin/tema na **primeira** criação do volume. Sem este comando, o
contêiner fica com código antigo.

## Quando executar

- stack parada, primeira sessão, ou agente novo no worktree
- após editar `wp-content/plugins/petshop-core/` ou `wp-content/themes/petshop-theme/`
- após mudar `docker/`, Dockerfiles, `compose.yaml`, `composer.lock` ou `package-lock.json`
- **antes** de `npm run validate`, smoke PHP, Playwright, PHPUnit de storefront ou handoff
- gate falhou com classe/arquivo ausente no WordPress

Não declare validação concluída se o runtime ainda tiver código velho.

## Fluxo do agente

1. Confirme Compose **2.32.2+** (`.\scripts\require-compose.ps1` ou `docker compose version`).
2. Veja os terminais já abertos. Se já existir `docker compose up --watch` saudável e a mudança for só plugin/tema, o sync contínuo basta — não suba outro watch.
3. Se a stack estiver down, a imagem estiver velha, Dockerfiles/lockfiles mudaram, ou o runtime não tiver o código: rode o comando canônico **em background** (`block_until_ms: 0`). `--watch` é processo em primeiro plano; não use `-d` junto.
4. Espere o WordPress ficar healthy e o watch habilitar (`Watch enabled` / `Syncing`). Confirme com `docker compose ps` (serviço `wordpress` healthy).
5. URLs: loja `http://localhost:8888`, admin `http://localhost:8888/wp-admin`.

Não inicie um segundo `up --watch` no mesmo projeto. Se o watch morreu ou o rebuild exige recriar o `wordpress`, pare o processo antigo e rode o comando canônico de novo.

## Watch já no ar

| Situação | Ação |
|---|---|
| Só PHP/CSS/JS de `petshop-core` ou `petshop-theme` | Nada. O watch faz `sync+exec` + `chown`. |
| `docker/`, Dockerfile, `compose.yaml` ou lockfile | Reinicie com `docker compose up --watch --build`. |
| Precisa validar já e o watch não está rodando | Use o one-shot abaixo, depois suba o watch. |

`cli` e `node` montam `./scripts` como bind read-only. Mudança só em `scripts/` **não** exige este comando.

## One-shot (sem processo watch)

Quando não puder deixar o watch ligado (gate pontual, sessão curta):

```powershell
docker compose build wordpress node
docker compose up -d --force-recreate --wait wordpress
docker compose exec wordpress sh -lc "cp -a /opt/project-source/plugins/petshop-core/. /var/www/html/wp-content/plugins/petshop-core/ && cp -a /opt/project-source/themes/petshop-theme/. /var/www/html/wp-content/themes/petshop-theme/ && chown -R www-data:www-data /var/www/html/wp-content/plugins/petshop-core /var/www/html/wp-content/themes/petshop-theme"
```

Isso **não** substitui `--watch` no desenvolvimento contínuo. Bootstrap sem watch: `npm run bootstrap` (`docker compose up -d --build --wait`) — ainda assim entregue plugin/tema (watch ou o `cp` acima) antes de validar.

## Confirmação

```powershell
docker compose ps
docker compose --profile tools run --rm --no-deps cli wp eval "echo class_exists('Petshop\\\\Core\\\\Settings\\\\DefaultSettings') ? 'ok' : 'fail';"
docker inspect petshop-wordpress-1 --format '{{range .Mounts}}{{println .Type .Name .Destination}}{{end}}'
```

O `inspect` deve listar só mounts do tipo `volume`. Bind para `C:\` ou `/mnt/c/` é regressão.

## Proibido

- `docker compose down --volumes`, `wp-env destroy` ou apagar volumes sem autorização explícita
- bind mount do repositório inteiro no WordPress
- `up --watch` **sem** `--build` depois de Dockerfile, `docker/` ou lockfile
- validar/smoke com plugin ou tema antigo no volume
- substituir `sync+exec` por bind mount

## Depois que a stack estiver no ar

WP-CLI, validators e o restante do fluxo: skill `petshop-workflow`.
