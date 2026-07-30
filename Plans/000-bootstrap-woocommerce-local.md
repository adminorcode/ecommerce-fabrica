# Plano 000 — Bootstrap do repositório WooCommerce local

**Status:** Concluído
**Responsável:** A definir  
**Última revisão:** 2026-07-19  
**Objetivo:** Criar um repositório WooCommerce local, reproduzível e preparado para desenvolvimento assistido pelo Cursor.

---

## 1. Resultado esperado

Ao concluir este plano, o repositório deverá possuir:

- WordPress e WooCommerce executados localmente por Docker através do `wp-env`;
- versões iniciais fixadas para evitar diferenças entre máquinas;
- tema próprio para apresentação da loja;
- plugin próprio para regras de negócio e integrações;
- comandos padronizados no `package.json`;
- regras de projeto para o Cursor;
- arquivos gerados, uploads, segredos e dependências fora do Git;
- documentação organizada na pasta `Plans/`.

O ambiente local deverá ser iniciado com:

```bash
npm ci
npm run env:start
```

A loja deverá ficar disponível em:

```text
http://localhost:8888
```

Administração local:

```text
http://localhost:8888/wp-admin
```

Credenciais padrão do `wp-env`:

```text
Usuário: admin
Senha: password
```

Essas credenciais são exclusivamente locais.

---

## 2. Decisões técnicas

### 2.1 Ambiente local

Usar `@wordpress/env`, conhecido como `wp-env`.

Motivos:

- é mantido no ecossistema oficial do WordPress;
- usa Docker e isola WordPress, PHP e banco de dados;
- permite fixar versões de WordPress, PHP, plugins e temas;
- fornece WP-CLI, Composer, PHPUnit e Xdebug;
- reduz diferenças entre Windows, Linux e macOS;
- permite que o Cursor execute comandos previsíveis.

### 2.2 Versões iniciais

Baseline validado em **19 de julho de 2026**:

| Componente | Versão |
|---|---:|
| WordPress | 7.0.2 |
| WooCommerce | 10.9.4 |
| PHP | 8.3 |
| Node.js | 24 LTS |
| Banco local | MariaDB gerenciado pelo `wp-env` |

As versões de WordPress e WooCommerce devem ser atualizadas somente através de um plano específico, com validação local antes da produção.

### 2.3 Organização do código

Separar responsabilidades:

- `petshop-core`: regras de negócio, integrações, tipos de dados, automações e customizações funcionais;
- `petshop-theme`: identidade visual, templates, estilos e apresentação;
- WooCommerce: dependência externa, nunca alterada diretamente;
- WordPress Core: dependência externa, nunca alterada diretamente.

Nenhuma regra de negócio deve ser implementada diretamente no `functions.php` do tema.

### 2.4 Versionamento

Aplicar a regra:

> Código sobe; conteúdo e dados não sobem.

Devem ser versionados:

- tema próprio;
- plugin próprio;
- configuração do ambiente local;
- scripts;
- planos;
- regras do Cursor;
- arquivos de lock.

Não devem ser versionados:

- banco de dados;
- uploads;
- cache;
- logs;
- WordPress Core;
- código de plugins de terceiros;
- senhas, tokens ou chaves;
- `node_modules`;
- diretórios `vendor`.

---

## 3. Pré-requisitos

Instalar antes de iniciar:

- Git;
- Docker Desktop ou Docker Engine com Docker Compose;
- Node.js 24 LTS;
- npm;
- Cursor.

Verificações:

```bash
git --version
docker --version
docker compose version
node --version
npm --version
```

Versão esperada do Node:

```text
v24.x
```

### Windows

Preferir Docker Desktop com backend WSL2.

Para melhor desempenho de arquivos, manter o repositório dentro do sistema de arquivos do WSL, por exemplo:

```text
~/projects/petshop-ecommerce
```

Evitar trabalhar em `/mnt/c/...` quando houver lentidão significativa no Docker.

---

## 4. Estrutura planejada

```text
petshop-ecommerce/
├── .cursor/
│   └── rules/
│       └── project.mdc
├── Plans/
│   └── 000-bootstrap-woocommerce-local.md
├── scripts/
│   └── README.md
├── wp-content/
│   ├── plugins/
│   │   └── petshop-core/
│   │       ├── includes/
│   │       ├── tests/
│   │       ├── composer.json
│   │       └── petshop-core.php
│   └── themes/
│       └── petshop-theme/
│           ├── assets/
│           │   ├── css/
│           │   ├── images/
│           │   └── js/
│           ├── parts/
│           │   ├── footer.html
│           │   └── header.html
│           ├── patterns/
│           ├── templates/
│           │   └── index.html
│           ├── functions.php
│           ├── style.css
│           └── theme.json
├── .cursorignore
├── .editorconfig
├── .gitignore
├── .nvmrc
├── .wp-env.json
├── package-lock.json
├── package.json
└── README.md
```

---

## 5. Inicialização do repositório

### 5.1 Criar a pasta

```bash
mkdir petshop-ecommerce
cd petshop-ecommerce
git init
```

### 5.2 Criar os diretórios

```bash
mkdir -p Plans
mkdir -p scripts
mkdir -p .cursor/rules
mkdir -p wp-content/plugins/petshop-core/includes
mkdir -p wp-content/plugins/petshop-core/tests
mkdir -p wp-content/themes/petshop-theme/assets/css
mkdir -p wp-content/themes/petshop-theme/assets/images
mkdir -p wp-content/themes/petshop-theme/assets/js
mkdir -p wp-content/themes/petshop-theme/parts
mkdir -p wp-content/themes/petshop-theme/patterns
mkdir -p wp-content/themes/petshop-theme/templates
```

No PowerShell, caso não esteja utilizando WSL:

```powershell
$directories = @(
  "Plans",
  "scripts",
  ".cursor/rules",
  "wp-content/plugins/petshop-core/includes",
  "wp-content/plugins/petshop-core/tests",
  "wp-content/themes/petshop-theme/assets/css",
  "wp-content/themes/petshop-theme/assets/images",
  "wp-content/themes/petshop-theme/assets/js",
  "wp-content/themes/petshop-theme/parts",
  "wp-content/themes/petshop-theme/patterns",
  "wp-content/themes/petshop-theme/templates"
)

$directories | ForEach-Object {
  New-Item -ItemType Directory -Force -Path $_
}
```

### 5.3 Fixar Node.js

Criar `.nvmrc`:

```text
24
```

Com NVM:

```bash
nvm install
nvm use
```

---

## 6. Configuração do npm

Inicializar:

```bash
npm init -y
npm install --save-dev @wordpress/env
```

O `package-lock.json` deve ser versionado.

Editar o `package.json` para conter:

```json
{
  "name": "petshop-ecommerce",
  "version": "0.1.0",
  "private": true,
  "description": "E-commerce do petshop baseado em WordPress e WooCommerce.",
  "scripts": {
    "env:start": "wp-env start --runtime=docker",
    "env:start:xdebug": "wp-env start --runtime=docker --xdebug",
    "env:update": "wp-env start --runtime=docker --update",
    "env:stop": "wp-env stop",
    "env:status": "wp-env status",
    "env:logs": "wp-env logs",
    "env:reset": "wp-env reset all",
    "env:destroy": "wp-env destroy --force",
    "wp": "wp-env run cli wp",
    "composer:plugin": "wp-env run cli --env-cwd=wp-content/plugins/petshop-core composer"
  },
  "engines": {
    "node": ">=24 <25"
  },
  "devDependencies": {
    "@wordpress/env": "^10.0.0"
  }
}
```

A versão real de `@wordpress/env` instalada pelo npm pode ser diferente da apresentada no exemplo. O `package-lock.json` será a fonte de verdade.

Depois de editar:

```bash
npm install
```

---

## 7. Configuração do wp-env

Criar `.wp-env.json`:

```json
{
  "$schema": "https://schemas.wp.org/trunk/wp-env.json",
  "core": "https://wordpress.org/wordpress-7.0.2.zip",
  "phpVersion": "8.3",
  "plugins": [
    "https://downloads.wordpress.org/plugin/woocommerce.10.9.4.zip",
    "./wp-content/plugins/petshop-core"
  ],
  "themes": [
    "./wp-content/themes/petshop-theme"
  ],
  "port": 8888,
  "phpmyadminPort": 8890,
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "WP_DEBUG_DISPLAY": false,
    "SCRIPT_DEBUG": true,
    "WP_ENVIRONMENT_TYPE": "local",
    "WP_DEVELOPMENT_MODE": "all",
    "DISALLOW_FILE_EDIT": true
  }
}
```

Observações:

- `WP_DEBUG_DISPLAY` fica desabilitado para não expor erros no HTML;
- os erros continuam sendo gravados no log;
- `DISALLOW_FILE_EDIT` impede edição de PHP pelo painel administrativo;
- o phpMyAdmin ficará disponível na porta `8890`;
- WordPress e WooCommerce estão fixados para tornar o ambiente reproduzível.

Nunca inserir tokens ou segredos nesse arquivo.

Para configurações pessoais não versionadas, usar `.wp-env.override.json` e mantê-lo no `.gitignore`.

---

## 8. Plugin próprio: petshop-core

Criar `wp-content/plugins/petshop-core/petshop-core.php`:

```php
<?php
/**
 * Plugin Name: Petshop Core
 * Plugin URI:  https://example.invalid
 * Description: Regras de negócio e integrações específicas do e-commerce do petshop.
 * Version:     0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Author:      Petshop
 * Text Domain: petshop-core
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action(
    'before_woocommerce_init',
    static function (): void {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }
);
```

Esse arquivo:

- registra o plugin;
- declara dependência do WooCommerce;
- declara compatibilidade inicial com HPOS;
- não contém regras de negócio ainda.

Criar `wp-content/plugins/petshop-core/composer.json`:

```json
{
  "name": "petshop/petshop-core",
  "description": "Regras de negócio e integrações específicas do petshop.",
  "type": "wordpress-plugin",
  "license": "proprietary",
  "require": {
    "php": "^8.3"
  },
  "autoload": {
    "psr-4": {
      "Petshop\\Core\\": "includes/"
    }
  },
  "config": {
    "sort-packages": true,
    "allow-plugins": {}
  }
}
```

Instalar e gerar o autoload:

```bash
npm run env:start
npm run composer:plugin -- install
```

Quando classes forem adicionadas ao plugin, incluir no arquivo principal:

```php
$autoload = __DIR__ . '/vendor/autoload.php';

if (is_readable($autoload)) {
    require_once $autoload;
}
```

Não adicionar esse trecho antes de executar o Composer, pois o arquivo ainda não existirá.

---

## 9. Tema próprio: petshop-theme

### 9.1 style.css

Criar `wp-content/themes/petshop-theme/style.css`:

```css
/*
Theme Name: Petshop Theme
Theme URI: https://example.invalid
Author: Petshop
Description: Tema em blocos do e-commerce do petshop.
Version: 0.1.0
Requires at least: 7.0
Requires PHP: 8.3
Text Domain: petshop-theme
*/
```

### 9.2 functions.php

Criar `wp-content/themes/petshop-theme/functions.php`:

```php
<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action(
    'after_setup_theme',
    static function (): void {
        add_theme_support('woocommerce');
    }
);
```

O `functions.php` deve permanecer pequeno. Regras funcionais pertencem ao `petshop-core`.

### 9.3 theme.json

Criar `wp-content/themes/petshop-theme/theme.json`:

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 3,
  "settings": {
    "appearanceTools": true,
    "layout": {
      "contentSize": "760px",
      "wideSize": "1200px"
    },
    "spacing": {
      "units": [
        "px",
        "rem",
        "%",
        "vw"
      ]
    },
    "typography": {
      "fluid": true
    }
  },
  "styles": {
    "spacing": {
      "blockGap": "1.5rem"
    },
    "typography": {
      "fontFamily": "-apple-system, BlinkMacSystemFont, \"Segoe UI\", sans-serif",
      "lineHeight": "1.6"
    }
  }
}
```

### 9.4 Header

Criar `wp-content/themes/petshop-theme/parts/header.html`:

```html
<!-- wp:group {"tagName":"header","layout":{"type":"constrained"}} -->
<header class="wp-block-group">
    <!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
    <div class="wp-block-group">
        <!-- wp:site-title /-->
        <!-- wp:navigation {"overlayMenu":"mobile"} /-->
    </div>
    <!-- /wp:group -->
</header>
<!-- /wp:group -->
```

### 9.5 Footer

Criar `wp-content/themes/petshop-theme/parts/footer.html`:

```html
<!-- wp:group {"tagName":"footer","layout":{"type":"constrained"}} -->
<footer class="wp-block-group">
    <!-- wp:paragraph {"align":"center"} -->
    <p class="has-text-align-center">Petshop</p>
    <!-- /wp:paragraph -->
</footer>
<!-- /wp:group -->
```

### 9.6 Template principal

Criar `wp-content/themes/petshop-theme/templates/index.html`:

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
    <!-- wp:query-title {"type":"archive"} /-->
    <!-- wp:post-content /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

Esse tema é propositalmente mínimo. A implementação visual deverá possuir plano próprio.

---

## 10. Arquivos de controle do repositório

### 10.1 .gitignore

Criar `.gitignore`:

```gitignore
# Dependencies
node_modules/
**/vendor/

# WordPress runtime and generated data
wp-content/uploads/
wp-content/cache/
wp-content/upgrade/
wp-content/backups/
wp-content/backup*/
wp-content/ai1wm-backups/

# WordPress local files
wp-config.php
.htaccess
*.sql
*.sqlite
*.sqlite3

# Logs
*.log
debug.log

# Secrets and local overrides
.env
.env.*
!.env.example
.wp-env.override.json
auth.json

# IDE and OS
.idea/
.vscode/
.DS_Store
Thumbs.db

# Test and coverage artifacts
coverage/
.phpunit.result.cache
```

Não ignorar:

- `package-lock.json`;
- `composer.lock`, quando criado;
- código compilado necessário para deploy, caso o futuro pipeline exija.

### 10.2 .cursorignore

Criar `.cursorignore`:

```gitignore
node_modules/
**/vendor/
wp-content/uploads/
wp-content/cache/
wp-content/upgrade/
*.sql
*.sqlite
*.sqlite3
*.log
.env
.env.*
auth.json
```

Isso evita indexar dependências, dados locais e possíveis segredos.

### 10.3 .editorconfig

Criar `.editorconfig`:

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 4
trim_trailing_whitespace = true

[*.{json,jsonc,md,yml,yaml,css,scss,js,jsx,ts,tsx,html}]
indent_size = 2

[*.md]
trim_trailing_whitespace = false
```

---

## 11. Regras do Cursor

Criar `.cursor/rules/project.mdc`:

```md
---
description: Regras globais do projeto WooCommerce do petshop
alwaysApply: true
---

# Contexto

Este repositório contém um e-commerce baseado em WordPress e WooCommerce.

Antes de implementar uma funcionalidade:

1. leia o plano correspondente em `Plans/`;
2. identifique o escopo, os critérios de aceite e o que está fora do escopo;
3. altere somente os arquivos necessários;
4. execute as validações previstas;
5. atualize os checkboxes do plano quando a tarefa realmente estiver concluída.

# Arquitetura

- Regras de negócio pertencem a `wp-content/plugins/petshop-core`.
- Apresentação pertence a `wp-content/themes/petshop-theme`.
- Não editar WordPress Core.
- Não editar WooCommerce ou plugins de terceiros.
- Não implementar regras de negócio no `functions.php` do tema.
- Não adicionar plugins sem registrar a decisão em um plano.
- Não armazenar segredos, tokens, credenciais ou dados pessoais no repositório.

# WordPress e WooCommerce

- Utilizar APIs públicas do WordPress e WooCommerce.
- Preferir hooks, filtros, Store API e extensibilidade dos blocos.
- Não copiar arquivos internos do WooCommerce sem necessidade comprovada.
- Toda integração com pedidos deve ser compatível com HPOS.
- Toda alteração no checkout deve considerar o Checkout Block.
- Sanitizar entradas.
- Escapar saídas.
- Verificar nonces em operações mutáveis.
- Verificar capabilities em operações administrativas.
- Usar consultas preparadas quando acesso SQL direto for inevitável.
- Evitar SQL direto quando uma API oficial estiver disponível.

# Código

- PHP mínimo: 8.3.
- Usar `declare(strict_types=1)` em novos arquivos PHP próprios.
- Usar PSR-4 dentro do plugin.
- Preferir classes pequenas e responsabilidades claras.
- Evitar funções globais.
- Não silenciar exceções ou erros sem justificativa.
- Não fazer alterações massivas fora do plano atual.
- Preservar compatibilidade com atualizações de WordPress e WooCommerce.

# Validação

Antes de declarar uma implementação concluída:

- verificar se o ambiente inicia;
- verificar se não existem erros fatais no log;
- verificar o fluxo funcional afetado;
- listar arquivos alterados;
- registrar limitações ou decisões pendentes no plano.
```

A regra deve permanecer curta o suficiente para não consumir contexto desnecessário, mas específica o suficiente para evitar alterações perigosas no Core ou em plugins externos.

---

## 12. README inicial

Criar `README.md`:

```md
# Petshop Ecommerce

E-commerce baseado em WordPress e WooCommerce.

## Requisitos

- Docker
- Node.js 24 LTS
- npm
- Git

## Iniciar ambiente

```bash
npm ci
npm run env:start
```

Loja:

```text
http://localhost:8888
```

Admin:

```text
http://localhost:8888/wp-admin
```

Credenciais locais:

```text
admin / password
```

## Comandos

```bash
npm run env:start
npm run env:stop
npm run env:status
npm run env:logs
npm run env:start:xdebug
npm run wp -- plugin list
```

## Organização

- `Plans/`: planos de implementação;
- `wp-content/plugins/petshop-core/`: regras de negócio;
- `wp-content/themes/petshop-theme/`: tema da loja.

Não altere WordPress Core, WooCommerce ou plugins de terceiros diretamente.
```

Atenção: o bloco acima contém blocos de código aninhados. Ao criar o arquivo real, ajustar os delimitadores externos ou copiar as seções separadamente.

Criar `scripts/README.md`:

```md
# Scripts

Scripts repetíveis de bootstrap, validação, importação de dados de demonstração e automação local serão armazenados aqui.

Scripts devem ser:

- idempotentes quando possível;
- documentados;
- seguros para execução local;
- independentes de segredos versionados.
```

---

## 13. Primeira execução

### 13.1 Instalar dependências

```bash
npm install
```

### 13.2 Iniciar ambiente

```bash
npm run env:start
```

O primeiro início pode baixar imagens Docker, WordPress e WooCommerce.

### 13.3 Verificar status

```bash
npm run env:status
```

### 13.4 Verificar versões

```bash
npm run wp -- core version
npm run wp -- plugin get woocommerce --field=version
npm run wp -- eval "echo PHP_VERSION;"
```

Resultados esperados:

```text
WordPress: 7.0.2
WooCommerce: 10.9.4
PHP: 8.3.x
```

### 13.5 Configuração inicial via WP-CLI

Executar:

```bash
npm run wp -- language core install pt_BR
npm run wp -- site switch-language pt_BR
npm run wp -- language plugin install woocommerce pt_BR
npm run wp -- option update blogname "Petshop"
npm run wp -- option update blogdescription "Tudo para o seu pet"
npm run wp -- option update timezone_string "America/Sao_Paulo"
npm run wp -- option update date_format "d/m/Y"
npm run wp -- option update time_format "H:i"
npm run wp -- rewrite structure "/%postname%/"
npm run wp -- plugin activate woocommerce
npm run wp -- plugin activate petshop-core
npm run wp -- theme activate petshop-theme
npm run wp -- rewrite flush
```

Caso o comando de idioma do plugin ainda não encontre a tradução, não bloquear o bootstrap. O WordPress poderá baixá-la posteriormente.

### 13.6 Confirmar plugins e tema

```bash
npm run wp -- plugin list
npm run wp -- theme list
```

Esperado:

- `woocommerce`: ativo;
- `petshop-core`: ativo;
- `petshop-theme`: ativo.

---

## 14. Logs e depuração

### Logs do ambiente

```bash
npm run env:logs
```

### Log do WordPress

O `wp-env` mantém o WordPress em uma área gerenciada. Para ler o log pelo container:

```bash
npx wp-env run cli bash
```

Dentro do container:

```bash
cat wp-content/debug.log
```

Sair:

```bash
exit
```

### Xdebug

Iniciar:

```bash
npm run env:start:xdebug
```

A configuração específica do Cursor/VS Code para breakpoints deverá ser tratada em plano separado, pois pode variar por sistema operacional.

---

## 15. Reset e destruição do ambiente

### Parar sem apagar dados

```bash
npm run env:stop
```

### Resetar banco e conteúdo local

```bash
npm run env:reset
npm run env:start
```

Esse comando apaga produtos, pedidos, páginas e configurações locais.

### Destruir completamente

```bash
npm run env:destroy
```

Esse comando remove containers, volumes e arquivos gerenciados do ambiente.

Não executar reset ou destroy sem considerar a perda dos dados locais.

---

## 16. Fluxo de trabalho com Plans

Cada implementação relevante deverá possuir um arquivo próprio:

```text
Plans/NNN-nome-da-implementacao.md
```

Exemplos:

```text
Plans/001-configuracao-base-da-loja.md
Plans/002-catalogo-e-categorias.md
Plans/003-identidade-visual-e-home.md
Plans/004-pagamentos-pix-cartao.md
Plans/005-frete-e-entrega.md
Plans/006-lgpd-privacidade-cookies.md
Plans/007-seo-e-analytics.md
Plans/008-deploy-staging-producao.md
```

### Estrutura recomendada para cada plano

```md
# Plano NNN — Nome

**Status:** Planejado | Em andamento | Bloqueado | Concluído

## Objetivo

## Contexto

## Escopo

## Fora do escopo

## Decisões técnicas

## Arquivos afetados

## Etapas

- [ ] Etapa 1
- [ ] Etapa 2

## Critérios de aceite

- [ ] Critério 1
- [ ] Critério 2

## Testes

## Riscos

## Rollback

## Notas da implementação
```

### Regra de status

Um checkbox só deve ser marcado quando:

- o código estiver implementado;
- a validação tiver sido executada;
- o critério de aceite tiver sido comprovado.

Não marcar tarefas apenas porque arquivos foram criados.

---

## 17. Prompt inicial para o Cursor

Após abrir a raiz do repositório no Cursor, utilizar:

```text
Leia integralmente o arquivo Plans/000-bootstrap-woocommerce-local.md e a regra .cursor/rules/project.mdc.

Implemente o plano por etapas, mantendo o escopo definido. Não altere WordPress Core, WooCommerce ou código de terceiros.

Após cada etapa:
1. valide os arquivos criados;
2. execute os comandos aplicáveis;
3. informe falhas encontradas;
4. marque no plano somente os itens realmente concluídos.

No final, apresente:
- arquivos criados ou alterados;
- comandos executados;
- resultado dos critérios de aceite;
- pendências ou limitações.
```

Evitar solicitar ao Cursor que implemente toda a loja em uma única instrução. Utilizar um plano por funcionalidade.

---

## 18. Critérios de aceite do bootstrap

- [x] O repositório Git foi inicializado.
- [x] A pasta `Plans/` existe.
- [x] O plano atual está salvo como `Plans/000-bootstrap-woocommerce-local.md`.
- [x] O Node.js utilizado é da linha 24 LTS.
- [x] `npm ci` funciona após um clone limpo.
- [x] `npm run env:start` inicia o ambiente.
- [x] A loja abre em `http://localhost:8888`.
- [x] O painel abre em `http://localhost:8888/wp-admin`.
- [x] WordPress está na versão 7.0.2.
- [x] WooCommerce está na versão 10.9.4.
- [x] PHP está na linha 8.3.
- [x] `petshop-core` está ativo.
- [x] `petshop-theme` está ativo.
- [x] O plugin declara compatibilidade com HPOS.
- [x] Não existem erros fatais no log.
- [x] Uploads, banco, logs, dependências e segredos não aparecem no Git.
- [x] O Cursor reconhece a regra em `.cursor/rules/project.mdc`.
- [x] `git status` mostra somente arquivos de código e configuração esperados.

---

## 19. Primeiro commit

Antes do commit:

```bash
git status
git diff --check
npm run env:status
npm run wp -- core version
npm run wp -- plugin get woocommerce --field=version
npm run wp -- plugin list
npm run wp -- theme list
```

Commit sugerido:

```bash
git add .
git commit -m "chore: bootstrap local WooCommerce environment"
```

Antes de enviar para um repositório remoto, confirmar que nenhum segredo, banco ou upload foi incluído:

```bash
git status
git ls-files
```

---

## 20. Fora do escopo deste plano

Este bootstrap não deverá implementar:

- identidade visual final;
- cadastro real de produtos;
- importação de catálogo;
- gateway de pagamento;
- Pix;
- antifraude;
- cálculo de frete;
- Correios ou transportadoras;
- emissão fiscal;
- integração com ERP;
- política de privacidade;
- banner de cookies;
- analytics;
- SEO;
- e-mails transacionais;
- backup;
- staging;
- produção;
- CI/CD;
- observabilidade;
- otimização de performance;
- segurança de infraestrutura.

Cada item deverá possuir plano próprio.

---

## 21. Riscos e cuidados

### Dependência excessiva de plugins

Não instalar um plugin apenas para resolver uma pequena alteração visual ou uma regra simples. Cada plugin aumenta:

- superfície de ataque;
- chance de conflito;
- custo de atualização;
- dependência de terceiros;
- carga de execução.

### Alteração direta de terceiros

Mudanças em WordPress, WooCommerce ou plugins externos serão perdidas em atualizações.

Usar:

- hooks;
- filtros;
- APIs;
- templates somente quando realmente necessário;
- plugin próprio;
- tema filho ou tema próprio.

### Banco local como fonte de configuração

Configurações feitas apenas pelo painel podem ser esquecidas ou não reproduzidas.

Sempre que possível:

- documentar a configuração;
- automatizar com WP-CLI;
- criar scripts idempotentes;
- registrar decisões no plano correspondente.

### Versões automáticas

Não usar versões beta ou release candidates no ambiente principal de desenvolvimento sem um objetivo específico de teste.

Atualizações devem ter plano, backup e validação.

---

## Notas da implementação

**Data:** 2026-07-19

### Entregue

- Estrutura de diretórios, plugin `petshop-core`, tema `petshop-theme`, configuração `wp-env`, scripts npm, regras Cursor, `.gitignore`, `.cursorignore`, `.editorconfig`, `README.md`.
- `npm install` e `npm ci` executados com sucesso (Node v24.18.0, `@wordpress/env` instalado).

### Validação runtime concluída

**Data:** 2026-07-29

- Docker e `wp-env` iniciaram o ambiente de desenvolvimento e de testes.
- Loja e painel administrativo responderam localmente.
- WordPress 7.0.2, WooCommerce 10.9.4 e PHP 8.3.32 foram confirmados.
- `petshop-core` e `petshop-theme` foram confirmados ativos.
- O log de depuração não apresentou erros fatais.

---

## 22. Próximo plano recomendado

Criar:

```text
Plans/001-configuracao-base-da-loja.md
```

Esse plano deverá tratar:

- país e endereço da loja;
- moeda BRL;
- unidades de peso e dimensão;
- impostos;
- política de estoque;
- checkout como convidado;
- contas de clientes;
- e-mails;
- páginas institucionais;
- estrutura inicial de categorias;
- dados fictícios para desenvolvimento;
- configuração reproduzível via WP-CLI ou código.
