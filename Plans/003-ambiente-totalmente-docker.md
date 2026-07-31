# Plano 003 — Ambiente de desenvolvimento totalmente em Docker

**Status:** Em andamento
**Responsável:** A definir
**Última revisão:** 2026-07-30
**Objetivo:** Substituir o runtime atual do `wp-env` por uma stack Docker Compose reproduzível, sem executar ferramentas da aplicação no host e sem bind mount do WordPress no NTFS.

---

## 1. Contexto

O ambiente atual já utiliza contêineres, mas não está totalmente isolado dentro do
filesystem do Docker. O `wp-env` monta no contêiner caminhos do Windows, incluindo:

```text
C:\Users\lucas\source\repos\ecommerce-petshop\.local\wp-env\wordpress
    -> /var/www/html

C:\Users\lucas\source\repos\ecommerce-petshop\.local\wp-env\woocommerce
    -> /var/www/html/wp-content/plugins/woocommerce
```

O diagnóstico realizado em 30 de julho de 2026 encontrou:

- Docker com 20 CPUs e aproximadamente 15,5 GiB de RAM disponíveis;
- ausência de limites individuais de CPU ou memória nos contêineres;
- consumo total de memória inferior a 1 GiB;
- arquivo CSS levando aproximadamente 1,4 segundo para responder;
- homepage sem entregar o primeiro byte após 20 segundos;
- processos Apache em estado `D`, aguardando I/O;
- WordPress Core, WooCommerce, plugin e temas acessados por bind mounts no NTFS.

A lentidão, portanto, não é explicada por falta de CPU ou memória. O principal
gargalo é o acesso intensivo do PHP a arquivos do Windows através do Docker
Desktop/WSL2.

---

## 2. Resultado esperado

Ao concluir este plano:

- o único pré-requisito de runtime no host será Docker Desktop com Docker Compose;
- PHP, Apache, WordPress, MariaDB, Node.js, npm, Composer, WP-CLI e PHPUnit serão
  executados somente em contêineres;
- WordPress Core, plugins de terceiros, temas de terceiros, dependências, banco,
  uploads, cache e logs permanecerão em imagens ou volumes Docker;
- nenhum serviço da aplicação fará bind mount de `C:\...` ou `/mnt/c/...`;
- apenas o código versionado continuará editável no host;
- plugin e tema próprios serão enviados aos volumes Linux com Docker Compose Watch;
- desenvolvimento e testes usarão bancos e volumes separados;
- o ambiente será inicializado por comandos `docker compose`, sem depender do
  `wp-env` ou de Node.js instalado no host;
- a loja existente, o conteúdo Petsy e os uploads locais serão preservados durante
  a migração;
- o ambiente poderá ser destruído e recriado de forma documentada e previsível.

---

## 3. Definição de “tudo no Docker”

### 3.1 Permitido no host

- Docker Desktop;
- Docker Compose;
- Git;
- editor ou IDE;
- arquivos versionados do repositório;
- `.env` local não versionado;
- cliente HTTP ou navegador para acessar a aplicação.

### 3.2 Obrigatoriamente em contêineres

- WordPress e PHP;
- Apache;
- MariaDB;
- phpMyAdmin;
- WP-CLI;
- Composer;
- PHPUnit;
- Node.js e npm;
- scripts de bootstrap;
- instalação de dependências;
- execução de testes, lint e build;
- arquivos utilizados pelo runtime.

### 3.3 Proibido no runtime

- bind mount do diretório completo do projeto;
- bind mount do WordPress Core;
- bind mount de WooCommerce, Blocksy ou outros pacotes de terceiros;
- bind mount originado em `C:\...` ou `/mnt/c/...`;
- dependência de PHP, Node.js, npm, Composer, MariaDB ou WP-CLI instalados no host;
- gravação de banco, uploads, cache, logs ou `vendor/` no repositório.

O código próprio continuará existindo no host para edição e versionamento, mas será
copiado para o filesystem Linux do Docker por sincronização, não lido diretamente
por PHP através de bind mount.

---

## 4. Decisões técnicas

### 4.1 Orquestração

Substituir `@wordpress/env` por um `compose.yaml` versionado.

Usar Docker Compose 2.32.2 ou superior para disponibilizar:

- `develop.watch`;
- `initial_sync`;
- `sync`;
- `sync+exec`, quando for necessário executar uma ação após a sincronização;
- profiles para serviços opcionais e testes.

O projeto deve falhar cedo, com mensagem clara, quando a versão instalada do
Compose for inferior à mínima.

### 4.2 Imagem da aplicação

Criar uma imagem própria baseada em:

```text
wordpress:7.0.2-php8.3-apache
```

A imagem deverá fixar versões e conter:

- WordPress 7.0.2;
- PHP 8.3 e extensões necessárias;
- Apache;
- WooCommerce 10.9.4;
- Blocksy 2.1.50;
- Blocksy Companion 2.1.50;
- Stackable 3.19.10;
- Fluent Forms 6.2.9;
- configuração PHP de desenvolvimento;
- script idempotente de inicialização.

Imagens nunca deverão usar apenas tags flutuantes como `latest`. Quando possível,
fixar também o digest após validar a imagem.

### 4.3 Volumes

Volumes nomeados planejados:

| Volume | Conteúdo |
|---|---|
| `wordpress_runtime` | Document root compartilhado pelo WordPress e WP-CLI |
| `wordpress_uploads` | Uploads persistentes |
| `mariadb_data` | Banco de desenvolvimento |
| `node_modules` | Dependências npm usadas pelos contêineres |
| `composer_cache` | Cache do Composer |
| `npm_cache` | Cache do npm |
| `test_wordpress_runtime` | WordPress isolado para testes |
| `test_mariadb_data` | Banco isolado para testes |

Usar `tmpfs` somente para dados descartáveis, como temporários e determinados
caches de teste. Não usar `tmpfs` para banco ou uploads.

### 4.4 Sincronização do código próprio

Configurar Compose Watch com sincronização inicial para:

```text
wp-content/plugins/petshop-core/
    -> /var/www/html/wp-content/plugins/petshop-core/

wp-content/themes/petshop-theme/
    -> /var/www/html/wp-content/themes/petshop-theme/
```

Requisitos:

- `initial_sync: true`;
- ignorar `.git`, `vendor`, caches, logs e arquivos temporários;
- propagar criação, alteração e remoção de arquivos;
- executar correção de proprietário/permissões após a sincronização, se necessário;
- documentar que `docker compose up --watch` é o comando normal de desenvolvimento;
- comprovar por `docker inspect` que não existem mounts do tipo `bind` nos serviços
  da aplicação.

Caso Compose Watch não preserve corretamente permissões ou exclusões no Docker
Desktop utilizado, a alternativa aprovada é um contêiner sincronizador dedicado
que copie para volumes nomeados. Não retornar ao bind mount do WordPress.

### 4.5 Inicialização idempotente

Criar um serviço one-shot `init` que:

1. aguarde o banco ficar saudável;
2. prepare `wordpress_runtime`;
3. copie ou atualize apenas as dependências externas fixadas;
4. preserve `wp-config.php`, uploads e código próprio sincronizado;
5. instale o WordPress somente quando ainda não estiver instalado;
6. configure URL, idioma e opções locais;
7. ative os plugins obrigatórios e o child theme;
8. execute migrações necessárias;
9. possa ser executado novamente sem duplicar conteúdo ou apagar dados.

O serviço deve registrar um manifesto/checksum das versões instaladas. Mudanças em
WordPress ou dependências externas exigirão rebuild explícito da imagem.

### 4.6 Serviços

Serviços mínimos:

| Serviço | Responsabilidade | Execução |
|---|---|---|
| `db` | MariaDB de desenvolvimento | Padrão |
| `init` | Bootstrap idempotente | One-shot |
| `wordpress` | Apache, PHP e WordPress | Padrão |
| `cli` | WP-CLI e Composer | Sob demanda |
| `node` | Node.js 24, npm, lint e builds | Sob demanda |
| `phpmyadmin` | Administração local do banco | Profile `tools` |
| `test-db` | Banco isolado de testes | Profile `test` |
| `test-init` | Bootstrap do ambiente de testes | Profile `test` |
| `test-runner` | PHPUnit e demais testes | Profile `test` |

O ambiente de testes não deverá subir durante o desenvolvimento normal.

### 4.7 Rede e portas

- expor WordPress somente em `127.0.0.1:8888`;
- expor phpMyAdmin somente em `127.0.0.1:8890`;
- não publicar a porta do MariaDB por padrão;
- não publicar portas dos serviços de teste;
- usar rede interna do Compose para comunicação entre serviços;
- usar nomes dos serviços, nunca `localhost`, nas conexões internas.

### 4.8 Configuração e segredos

Versionar `.env.example` com valores locais não sensíveis e nomes das variáveis.

Manter `.env` no `.gitignore`.

Não gravar senhas diretamente em:

- `compose.yaml`;
- Dockerfiles;
- scripts;
- documentação operacional;
- imagens;
- histórico do Git.

As credenciais locais podem possuir defaults de desenvolvimento no `.env.example`,
mas devem estar claramente marcadas como descartáveis e proibidas em produção.

### 4.9 Healthchecks e dependências

Adicionar healthchecks para:

- MariaDB aceitando conexões;
- Apache respondendo HTTP;
- WordPress concluindo o bootstrap;
- ambiente de testes pronto.

Usar `depends_on` com condições de saúde onde aplicável. O simples estado
“container running” não deve ser considerado ambiente pronto.

---

## 5. Estrutura planejada

```text
ecommerce-petshop/
├── docker/
│   ├── wordpress/
│   │   ├── Dockerfile
│   │   ├── entrypoint.d/
│   │   │   └── 10-bootstrap-runtime.sh
│   │   └── php/
│   │       └── development.ini
│   ├── cli/
│   │   └── Dockerfile
│   ├── node/
│   │   └── Dockerfile
│   └── scripts/
│       ├── init-wordpress.sh
│       ├── healthcheck-wordpress.sh
│       ├── backup.sh
│       └── restore.sh
├── wp-content/
│   ├── plugins/
│   │   └── petshop-core/
│   └── themes/
│       └── petshop-theme/
├── Plans/
│   └── 003-ambiente-totalmente-docker.md
├── .dockerignore
├── .env.example
├── compose.yaml
├── Makefile
├── package.json
└── README.md
```

O `Makefile` é apenas uma interface opcional. Todos os fluxos devem possuir também
o comando Docker Compose equivalente para funcionar no PowerShell sem `make`.

---

## 6. Comandos-alvo

Os comandos finais deverão ser equivalentes a:

```bash
docker compose build
docker compose up --watch
docker compose down
docker compose logs -f wordpress
docker compose run --rm cli wp plugin list
docker compose run --rm cli composer --version
docker compose run --rm node npm ci
docker compose --profile tools up -d phpmyadmin
docker compose --profile test run --rm test-runner
```

Scripts npm poderão permanecer apenas como atalhos para Docker Compose:

```json
{
  "scripts": {
    "env:build": "docker compose build",
    "env:start": "docker compose up --watch",
    "env:stop": "docker compose down",
    "env:logs": "docker compose logs -f wordpress",
    "wp": "docker compose run --rm cli wp",
    "test": "docker compose --profile test run --rm test-runner"
  }
}
```

Executar esses atalhos não poderá exigir `npm` no host. Eles existirão por
compatibilidade documental; o caminho canônico será `docker compose`.

---

## 7. Etapas de implementação

### Etapa 1 — Inventário e backup

- [ ] Registrar imagens, volumes, redes e contêineres atuais.
- [ ] Exportar o banco atual com `wp db export`.
- [ ] Arquivar uploads e arquivos gerados que precisem ser preservados.
- [ ] Registrar plugins, temas, versões, status e opções principais.
- [ ] Gerar checksums dos backups.
- [ ] Testar a leitura do dump antes de alterar o ambiente.
- [ ] Não destruir o ambiente `wp-env` nesta etapa.

Artefatos locais esperados, ignorados pelo Git:

```text
.local/backups/003/database.sql
.local/backups/003/uploads.tar.gz
.local/backups/003/manifest.txt
```

### Etapa 2 — Criar imagens e Compose

- [ ] Criar `compose.yaml`.
- [ ] Criar a imagem WordPress com versões fixadas.
- [ ] Criar as imagens de WP-CLI/Composer e Node.js.
- [ ] Criar `.dockerignore`.
- [ ] Criar `.env.example`.
- [ ] Declarar redes, volumes, profiles e healthchecks.
- [ ] Validar com `docker compose config`.
- [ ] Validar que segredos não aparecem na imagem ou no Git.

### Etapa 3 — Bootstrap reproduzível

- [ ] Implementar o serviço `init`.
- [ ] Tornar scripts idempotentes.
- [ ] Configurar instalação limpa do WordPress.
- [ ] Instalar idioma `pt_BR`.
- [ ] Ativar plugins e tema obrigatórios.
- [ ] Garantir que o bootstrap não apague banco ou uploads existentes.
- [ ] Executar duas vezes e comprovar que a segunda execução não altera conteúdo.

### Etapa 4 — Sincronização sem bind mounts

- [ ] Configurar `develop.watch` para `petshop-core`.
- [ ] Configurar `develop.watch` para `petshop-theme`.
- [ ] Validar sincronização inicial.
- [ ] Validar criação, edição, renomeação e exclusão.
- [ ] Validar permissões dos arquivos no contêiner.
- [ ] Confirmar que PHP lê os arquivos a partir de volume Linux.
- [ ] Confirmar ausência de mounts do tipo `bind`.

Comando de auditoria:

```bash
docker inspect petshop-wordpress \
  --format '{{range .Mounts}}{{println .Type .Source "->" .Destination}}{{end}}'
```

O resultado não poderá conter:

```text
bind C:\...
bind /mnt/c/...
```

### Etapa 5 — Migrar dados existentes

- [ ] Subir a nova stack com volumes vazios.
- [ ] Restaurar o dump do banco.
- [ ] Restaurar uploads no volume `wordpress_uploads`.
- [ ] Atualizar URLs somente se necessário.
- [ ] Preservar o conteúdo Petsy.
- [ ] Validar homepage, menus, produtos, carrinho, checkout e conta.
- [ ] Comparar contagens de posts, páginas, produtos, usuários e anexos.

### Etapa 6 — Ferramentas totalmente conteinerizadas

- [ ] Executar WP-CLI apenas no serviço `cli`.
- [ ] Executar Composer apenas no serviço `cli`.
- [ ] Executar npm apenas no serviço `node`.
- [ ] Executar PHPUnit apenas no serviço `test-runner`.
- [ ] Manter caches em volumes nomeados.
- [ ] Remover dos scripts qualquer chamada a executáveis PHP/Node do host.
- [ ] Documentar depuração com Xdebug em profile opcional.

### Etapa 7 — Isolar testes

- [ ] Criar banco e runtime próprios para testes.
- [ ] Não expor portas dos testes.
- [ ] Não compartilhar banco, uploads ou cache com desenvolvimento.
- [ ] Garantir limpeza previsível entre execuções.
- [ ] Executar a suíte sem manter oito contêineres ativos permanentemente.

### Etapa 8 — Desativar `wp-env`

Somente após a nova stack passar por todos os critérios de aceite:

- [ ] Remover `@wordpress/env` das dependências.
- [ ] Remover `.wp-env.json`.
- [ ] Remover o uso de `.wp-env.override.json`.
- [ ] Substituir `scripts/bootstrap.mjs`.
- [ ] Atualizar `package-lock.json`.
- [ ] Atualizar README e regras do projeto.
- [ ] Manter o backup do ambiente antigo até a validação final.
- [ ] Destruir os contêineres e volumes antigos somente após aprovação explícita.

Não remover dados antigos automaticamente durante a implementação.

---

## 8. Validação funcional

Validar:

- [ ] `http://localhost:8888` abre.
- [ ] `/wp-admin` permite login.
- [ ] homepage Petsy permanece configurada.
- [ ] catálogo e categorias abrem.
- [ ] produto pode ser adicionado ao carrinho.
- [ ] quantidade e total do carrinho são atualizados.
- [ ] checkout abre sem erro fatal.
- [ ] Minha Conta abre.
- [ ] mídia existente carrega.
- [ ] permalinks funcionam.
- [ ] tarefas cron locais continuam disponíveis.
- [ ] envio de e-mail real permanece desabilitado ou capturado localmente.

Validar plugins e temas:

```bash
docker compose run --rm cli wp core version
docker compose run --rm cli wp plugin list
docker compose run --rm cli wp theme list
docker compose run --rm cli wp option get home
docker compose run --rm cli wp option get siteurl
```

---

## 9. Validação de desempenho

Executar medições com o ambiente aquecido e sem Xdebug:

```bash
curl -o /dev/null -s \
  -w 'status=%{http_code} ttfb=%{time_starttransfer} total=%{time_total}\n' \
  http://localhost:8888/
```

Critérios mínimos:

- [ ] arquivo estático aquecido com mediana inferior a 100 ms;
- [ ] homepage aquecida com mediana de TTFB inferior a 1 segundo;
- [ ] homepage aquecida com mediana total inferior a 2 segundos;
- [ ] nenhuma requisição comum fica sem primeiro byte por 20 segundos;
- [ ] nenhum processo Apache permanece em estado `D` por I/O do NTFS;
- [ ] CPU e memória não apresentam throttling;
- [ ] dez requisições sequenciais concluem sem timeout ou erro 5xx.

Registrar antes e depois no final deste plano. Caso o conteúdo ou chamadas externas
impeçam atingir os limites, registrar separadamente o tempo de PHP, banco, HTTP
externo e filesystem antes de ajustar o critério.

---

## 10. Segurança e qualidade

- [ ] Serviços expostos somente em `127.0.0.1`.
- [ ] Banco não publicado no host por padrão.
- [ ] Contêineres sem privilégios desnecessários.
- [ ] Nenhum socket Docker montado em contêiner.
- [ ] Nenhum segredo versionado.
- [ ] Scripts usam `set -eu` ou tratamento equivalente.
- [ ] Downloads validados por versão e checksum quando disponível.
- [ ] Imagens passam por inspeção de vulnerabilidades disponível no Docker Desktop.
- [ ] Logs não exibem senhas ou tokens.
- [ ] `docker compose config` não apresenta erro.
- [ ] `git diff --check` não apresenta erro.

---

## 11. Reprodutibilidade

Validar em uma stack descartável:

1. criar um nome de projeto Compose novo;
2. construir as imagens sem reutilizar volumes;
3. iniciar o ambiente;
4. aguardar healthchecks;
5. executar bootstrap;
6. validar versões e página inicial;
7. executar testes;
8. destruir somente a stack descartável.

Exemplo:

```bash
docker compose -p petshop-repro build
docker compose -p petshop-repro up -d --wait
docker compose -p petshop-repro run --rm cli wp core version
docker compose -p petshop-repro --profile test run --rm test-runner
docker compose -p petshop-repro down --volumes
```

Antes de `down --volumes`, confirmar por inspeção que o project name é exatamente
`petshop-repro`. Nunca executar remoção de volumes usando alvo vazio ou ambíguo.

---

## 12. Rollback

Enquanto a migração não for aceita:

- manter os volumes do `wp-env`;
- manter dump e uploads arquivados;
- não sobrescrever os backups;
- parar a nova stack;
- reiniciar temporariamente o ambiente antigo;
- restaurar código versionado apenas de forma seletiva.

Procedimento planejado:

```bash
docker compose down
npx wp-env start --runtime=docker
```

O comando acima é temporário e somente estará disponível antes da remoção final do
`wp-env`. Depois da aceitação, o rollback suportado será restaurar o dump e uploads
em uma versão anterior da nova stack.

---

## 13. Fora do escopo

- deploy de produção;
- Kubernetes;
- Swarm;
- registry privado;
- alta disponibilidade;
- CDN;
- Redis ou cache de página;
- proxy HTTPS público;
- otimização de consultas do conteúdo Petsy;
- atualização de WordPress, PHP ou plugins além das versões já adotadas;
- implementação do Plano 002 de seed de conteúdo;
- alteração visual ou funcional da loja.

---

## 14. Critérios de aceite

- [x] O host precisa somente de Docker, Docker Compose, Git e editor.
- [x] Todos os executáveis da aplicação rodam em contêineres.
- [x] Não existem bind mounts do Windows no serviço `wordpress` (mount de `scripts/` apenas no `cli`/`node`/`test-runner`, read-only).
- [x] WordPress e dependências externas residem em imagem ou volume Docker.
- [x] Código próprio é sincronizado para volumes Linux.
- [x] Banco e uploads persistem em volumes nomeados.
- [x] Ambiente de testes é isolado e sobe somente sob demanda.
- [x] Bootstrap é idempotente.
- [x] Stack limpa pode ser criada sem `wp-env` como caminho principal.
- [x] Dados atuais foram preservados e comparados (log 2026-07-30).
- [x] Fluxos essenciais do WooCommerce funcionam.
- [x] Critérios mínimos de desempenho foram atingidos ou possuem análise comprovada (TTFB 339–404 ms, log 2026-07-30).
- [x] README e comandos foram atualizados (Plano 006).
- [ ] `.wp-env.json` e `.wp-env.override.json` deixaram de ser necessários.
- [ ] Backups e dados antigos só foram removidos após aprovação explícita.

---

## 15. Evidências a registrar na conclusão

- versões de Docker e Docker Compose;
- lista final de serviços, imagens, redes e volumes;
- saída sanitizada de `docker compose config`;
- saída de `docker inspect` comprovando ausência de bind mounts;
- versões de WordPress, PHP, MariaDB, Node.js, npm, Composer e WP-CLI;
- tempos HTTP antes e depois;
- consumo de CPU e memória;
- resultado dos testes;
- contagens de conteúdo antes e depois;
- arquivos adicionados, alterados e removidos;
- limitações restantes.

---

## 16. Referências

- [Docker Compose Develop Specification](https://docs.docker.com/reference/compose-file/develop/)
- [Docker Compose Watch](https://docs.docker.com/compose/how-tos/file-watch/)
- [Docker volumes](https://docs.docker.com/engine/storage/volumes/)
- [Docker bind mounts](https://docs.docker.com/engine/storage/bind-mounts/)
- [Docker Desktop com WSL2](https://docs.docker.com/desktop/features/wsl/best-practices/)
- [Imagem oficial do WordPress](https://hub.docker.com/_/wordpress/)
- [Documentação oficial do wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)

---

## 17. Registro de execução

### 2026-07-30 — implementação inicial

- Stack Compose, Dockerfiles, scripts de bootstrap e volumes nomeados foram adicionados.
- O runtime legado `wp-env`, seus volumes e os dados existentes permanecem intactos até
  que a migração e a validação funcional sejam executadas.
- A validação da stack em runtime está bloqueada neste computador: o Docker Compose
  disponível é `2.23.3`, abaixo do mínimo `2.32.2` necessário para
  `develop.watch` com `sync+exec`.
- Nenhum checkbox de validação ou critério de aceite foi marcado sem evidência de runtime.

### 2026-07-30 — validação de runtime e migração

- Docker Desktop 29.6.2 e Docker Compose 5.3.1 foram validados pelo preflight.
- As imagens `petshop-wordpress:local` e `petshop-node:local` foram construídas.
- O banco do `wp-env` foi exportado para `.local/backups/003/database.sql`, teve
  checksum SHA-256 registrado e foi restaurado no volume `petshop_mariadb_data`.
- O runtime legado não possuía `wp-content/uploads`; portanto não havia uploads a
  migrar.
- A nova stack iniciou com WordPress 7.0.2, WooCommerce 10.9.4 e tema child ativo;
  o container WordPress possui apenas volumes nomeados, sem mounts `bind`.
- O conteúdo restaurado possui 7 páginas, 0 produtos e 1 usuário. A homepage e
  `/wp-admin` responderam localmente; cinco medições aquecidas da homepage ficaram
  entre 339 ms e 404 ms de TTFB.
- O banco e runtime de teste sobem isolados sob o profile `test` e foram removidos
  após a execução. O runner falha deliberadamente até que o plugin passe a conter
  PHPUnit e `phpunit.xml.dist`; não há uma suíte versionada no repositório atual.
