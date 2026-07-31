# Guia de bootstrap para agentes de IA

Este documento permite que qualquer agente de IA prepare e opere este repositório
localmente de forma reproduzível. Leia-o antes de alterar código ou executar um
plano.

## Objetivo e limites

O projeto é uma loja WordPress/WooCommerce. O ambiente canônico usa Docker Compose:

- WordPress, PHP, MariaDB, WP-CLI, Composer e Node.js executam em contêineres;
- banco, uploads e runtime ficam em volumes Docker nomeados;
- apenas o plugin e o child theme próprios são sincronizados para o runtime pelo
  Compose Watch;
- não monte o repositório inteiro, WordPress Core ou dependências de terceiros no
  contêiner.

Não altere WordPress Core, WooCommerce, Blocksy ou qualquer plugin de terceiro.
Regras de negócio pertencem a `wp-content/plugins/petshop-core/`; apresentação
pertence a `wp-content/themes/petshop-theme/`.

## Requisitos no host

Instale apenas:

- [Docker Desktop para Windows](https://docs.docker.com/desktop/setup/install/windows-install/),
  com contêineres Linux e backend WSL 2 quando aplicável;
- Docker Compose **2.32.2 ou superior** (incluído nas versões atuais do Docker
  Desktop; [instruções oficiais](https://docs.docker.com/compose/install/));
- [Git](https://git-scm.com/downloads/);
- um editor e um navegador.

Node.js, npm, PHP, Composer, WP-CLI e MariaDB **não** são pré-requisitos do host.
É necessário acesso à internet no primeiro build para baixar imagens e dependências.

Verifique Docker antes de continuar:

```powershell
docker version
docker compose version
.\scripts\require-compose.ps1
```

Se o último comando falhar, atualize/inicie o Docker Desktop. Não substitua
`sync+exec` por bind mount como alternativa.

## Preparar o repositório

```powershell
git clone <url-do-repositorio>
Set-Location ecommerce-petshop
Copy-Item .env.example .env
```

`.env` contém somente credenciais descartáveis de desenvolvimento e é ignorado pelo
Git. Não versione senhas, tokens, dumps ou chaves. Ajuste portas e credenciais locais
em `.env`, nunca em `compose.yaml`.

## Levantar a loja

Para um bootstrap verificável e sem acompanhamento do terminal:

```powershell
docker compose up -d --build --wait
docker compose ps
```

Para desenvolvimento contínuo, com sincronização do plugin e tema próprios:

```powershell
docker compose up --watch
```

URLs locais:

- Loja: <http://localhost:8888>
- Administração: <http://localhost:8888/wp-admin>
- phpMyAdmin: `docker compose --profile tools up -d phpmyadmin`, depois
  <http://localhost:8890>

As credenciais padrão são as de `.env.example` e são exclusivas do ambiente local.

## Verificar o ambiente

Execute os comandos sem instalar ferramentas no host:

```powershell
docker compose --profile tools run --rm --no-deps cli wp core version
docker compose --profile tools run --rm --no-deps cli wp plugin list
docker compose --profile tools run --rm --no-deps cli wp theme list
docker compose --profile tools run --rm --no-deps cli composer --version
docker compose --profile tools run --rm node npm --version
docker inspect petshop-wordpress-1 --format '{{range .Mounts}}{{println .Type .Name .Destination}}{{end}}'
```

O último comando deve listar somente mounts do tipo `volume`; um mount `bind` para
`C:\...` ou `/mnt/c/...` é uma regressão.

Para acompanhar falhas:

```powershell
docker compose logs -f wordpress
docker compose logs -f init
```

Para parar sem apagar dados:

```powershell
docker compose down
```

Nunca use `docker compose down --volumes`, `wp-env destroy` ou remoção manual de
volumes sem autorização explícita: esses comandos apagam dados locais.

## Testes

O profile `test` usa MariaDB e runtime próprios, sem portas publicadas:

```powershell
docker compose --profile test run --rm test-runner
docker compose --profile test rm -sf test-init test-db
```

No estado atual, o plugin ainda não possui PHPUnit nem `phpunit.xml.dist`; portanto
o runner falha intencionalmente em vez de apresentar um falso positivo. Ao adicionar
testes, versione a configuração e as dependências necessárias antes de declarar a
suite aprovada.

## Trabalhar por plano

1. Leia este arquivo, qualquer `AGENTS.md`, [Plans/STATUS.md](Plans/STATUS.md) e o
   arquivo do plano solicitado por inteiro.
2. Para **um plano novo**, confirme que o worktree está limpo; então atualize
   `master` com fast-forward e crie `codex/<nome-do-arquivo-sem-.md>` a partir dele:

   ```powershell
   git status --short
   git switch master
   git fetch origin master
   git pull --ff-only origin master
   git switch -c codex/<nome-do-plano>
   ```

   Se houver alterações locais, a branch já existir ou o pull não puder ser
   fast-forward, pare e peça orientação. Não faça stash, reset, rebase ou merge
   automático.
3. Respeite escopo, critérios de aceite e validações do plano. Não marque itens como
   concluídos sem evidência.
4. Preserve mudanças alheias e não faça commit, push ou pull request sem solicitação
   explícita.

Todo plano que afete páginas ou componentes visuais deve cumprir a regra de conteúdo
administrável de `.cursor/rules/project.mdc`: textos editoriais/comerciais e fotos
ou imagens de conteúdo precisam ser editáveis pelo cliente no WordPress. O plano
deve identificar a origem administrativa de cada item e comprovar que uma atualização
de código não sobrescreve as alterações salvas pelo cliente.

O Plano 003 está em andamento: a nova stack Compose já funciona, mas o `wp-env`
legado e seus backups devem ser preservados até a aceitação final da migração.

## Arquivos importantes

- [compose.yaml](compose.yaml): serviços, volumes, profiles e Compose Watch;
- [.env.example](.env.example): variáveis locais documentadas;
- [docker/](docker/): imagens e scripts de inicialização;
- [Plans/](Plans/): escopo e critérios de aceite;
- [Plans/README.md](Plans/README.md): convenções obrigatórias para novos planos;
- [.gitignore](.gitignore): dados e segredos que não devem ser versionados.
