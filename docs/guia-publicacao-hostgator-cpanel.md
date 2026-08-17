# Guia correto de publicacao na HostGator/cPanel

Este projeto e uma loja WordPress/WooCommerce. A publicacao correta nao e enviar o
repositorio inteiro para `public_html`.

Suba apenas estes artefatos:

- `petshop-theme.zip`
- `petshop-core.zip`
- `uploads.tar.gz`
- `petshop-db.sql`

Nao envie `.git`, `.local`, `node_modules`, `tests`, `Plans`, `scripts`, `outputs`,
`.env` ou backups locais.

## 1. Preparar o servidor

No cPanel/WordPress, confirme antes de importar:

- WordPress instalado na raiz do dominio, normalmente `public_html`.
- PHP 8.3 ativo para o dominio.
- Banco MySQL do WordPress identificado em `public_html/wp-config.php`.
- Acesso ao WordPress Admin.
- Acesso ao cPanel File Manager.
- Blocksy instalado, pois `petshop-theme` e tema filho de Blocksy.
- WooCommerce instalado.

Verificacao:

- Em WordPress Admin > Diagnostico > Informacoes > Servidor, confirme `Versao do PHP 8.3.x`.
- Em `wp-config.php`, confirme `DB_NAME`, `DB_USER`, `DB_PASSWORD` e `DB_HOST`.
- Em Aparencia > Temas, confirme que `Blocksy` aparece instalado.

## 2. Gerar os pacotes corretos

### Tema

O ZIP do tema deve conter:

```text
petshop-theme/style.css
petshop-theme/functions.php
petshop-theme/assets/...
petshop-theme/patterns/...
```

Verificacao local:

```powershell
Expand-Archive .\petshop-theme.zip -DestinationPath .\tmp-theme-check -Force
Test-Path .\tmp-theme-check\petshop-theme\style.css
Select-String .\tmp-theme-check\petshop-theme\style.css -Pattern "Template: blocksy"
```

O `style.css` precisa ter:

```css
Template: blocksy
```

### Plugin

O ZIP do plugin deve conter:

```text
petshop-core/petshop-core.php
petshop-core/includes/...
petshop-core/assets/js/wishlist.js
petshop-core/assets/js/catalog-filter.js
petshop-core/blocks/...
petshop-core/vendor/autoload.php
```

O plugin nao deve conter:

```text
petshop-core/node_modules/
petshop-core/tests/
petshop-core/vendor/phpunit/
petshop-core/vendor/myclabs/
petshop-core/vendor/sebastian/
```

O erro real encontrado em producao foi causado por `vendor` de desenvolvimento:

```text
Failed opening required .../vendor/myclabs/deep-copy/src/DeepCopy/deep_copy.php
```

Portanto, gere o pacote com dependencias de producao. Se Composer estiver disponivel:

```powershell
cd wp-content\plugins\petshop-core
composer install --no-dev --optimize-autoloader
```

Se o plugin nao usa bibliotecas de runtime alem do autoload PSR-4, `vendor/autoload.php`
pode ser um autoloader minimo:

```php
<?php

declare(strict_types=1);

spl_autoload_register(
    static function (string $class): void {
        $prefix = "Petshop\\Core\\";

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/../includes/' . strtr($relative, chr(92), '/') . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
);

return true;
```

Verificacao local do ZIP:

```powershell
Expand-Archive .\petshop-core.zip -DestinationPath .\tmp-plugin-check -Force
Test-Path .\tmp-plugin-check\petshop-core\petshop-core.php
Test-Path .\tmp-plugin-check\petshop-core\assets\js\wishlist.js
Test-Path .\tmp-plugin-check\petshop-core\vendor\autoload.php
Test-Path .\tmp-plugin-check\petshop-core\node_modules
Test-Path .\tmp-plugin-check\petshop-core\tests
```

Os dois ultimos devem retornar `False`.

## 3. Enviar e extrair arquivos no cPanel

Use FileZilla ou File Manager para enviar os arquivos.

### Tema

Destino:

```text
public_html/wp-content/themes/
```

Depois extraia `petshop-theme.zip`.

Verificacao:

```text
public_html/wp-content/themes/petshop-theme/style.css
```

### Plugin

Destino:

```text
public_html/wp-content/plugins/
```

Depois extraia `petshop-core.zip`.

Verificacao:

```text
public_html/wp-content/plugins/petshop-core/petshop-core.php
public_html/wp-content/plugins/petshop-core/assets/js/wishlist.js
public_html/wp-content/plugins/petshop-core/vendor/autoload.php
```

### Uploads

Destino:

```text
public_html/wp-content/
```

Depois extraia `uploads.tar.gz`.

Verificacao:

```text
public_html/wp-content/uploads/2026/
public_html/wp-content/uploads/woocommerce-placeholder.webp
```

## 4. Ativar WooCommerce, plugin e tema

No WordPress Admin:

1. Ative `WooCommerce`.
2. Ative `Petshop Core`.
3. Ative `Petshop Theme`.

Se `Petshop Core` falhar com erro fatal, nao continue no escuro. Ative log e leia o
erro real.

## 5. Como ativar e ler debug.log

Edite `public_html/wp-config.php` temporariamente.

Antes da linha:

```php
/* That's all, stop editing! Happy publishing. */
```

adicione:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

Depois tente repetir a acao que falhou.

Leia:

```text
public_html/wp-content/debug.log
```

Tambem verifique:

```text
public_html/error_log
```

Erros comuns e correcoes:

- `vendor/myclabs`, `phpunit`, `sebastian` ou `deep-copy`: o plugin foi enviado com
  `vendor` de desenvolvimento. Reempacote sem `require-dev` ou use autoload minimo.
- `filemtime() ... assets/js/wishlist.js`: o ZIP do plugin ficou incompleto. Envie
  `assets/js/wishlist.js`.
- `PETSHOP_HERO_ATTACHMENT_MISSING`: o banco e os uploads ainda nao estao alinhados,
  ou `uploads.tar.gz` nao foi extraido corretamente.
- Tela branca sem fatal: confira `template` e `stylesheet` no banco. Para tema filho,
  `template` precisa ser `blocksy` e `stylesheet` precisa ser `petshop-theme`.

Depois de resolver, remova `WP_DEBUG_LOG` e deixe producao assim:

```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
```

Apague logs criados:

```text
public_html/wp-content/debug.log
public_html/error_log
```

## 6. Importar banco

O banco `petshop-db.sql` e um dump completo com `DROP TABLE`. Ele substitui o WordPress
atual. Use somente em primeira publicacao ou quando for aceitavel sobrescrever dados.

### Caminho preferido: phpMyAdmin

1. Abra cPanel > phpMyAdmin.
2. Selecione o banco usado pelo `wp-config.php`.
3. Abra Importar.
4. Envie `petshop-db.sql`.
5. Execute.

Depois atualize as URLs antigas do dump:

```text
http://localhost:8888 -> https://seudominio.com
```

No caso validado, o dominio temporario foi:

```text
https://viniciusgarciapaladi1786862104000.0330439.meusitehostgator.com.br
```

### Caminho de contingencia: importador PHP temporario

Use somente se o upload/import pelo phpMyAdmin falhar ou o seletor de arquivo do
navegador nao funcionar.

1. Envie `petshop-db.sql` para:

```text
public_html/wp-content/petshop-db.sql
```

2. Crie temporariamente em `public_html/petshop-import-chunk.php` um importador que:

- le `wp-config.php`;
- conecta no banco atual;
- remove a primeira linha MariaDB `/*M!999999...`;
- substitui `http://localhost:8888` pela URL real;
- executa o dump linha por linha;
- ajusta `siteurl`, `home`, `template` e `stylesheet`;
- apaga a si mesmo no final.

3. Execute pelo navegador ou por requisicao HTTP:

```text
https://seudominio.com/petshop-import-chunk.php?token=TOKEN
```

Saida esperada:

```text
Starting streaming import
queries=100 line=...
...
OK streaming imported queries: 788
URL: https://seudominio.com
```

4. Apague qualquer importador temporario que sobrar.

Verificacao no banco:

```text
wp_options.siteurl = https://seudominio.com
wp_options.home = https://seudominio.com
wp_options.template = blocksy
wp_options.stylesheet = petshop-theme
```

## 7. Corrigir tema filho apos importacao

Como `petshop-theme` e tema filho do Blocksy, estas opcoes precisam ficar assim:

```text
template   = blocksy
stylesheet = petshop-theme
```

Se `template = petshop-theme`, a home pode ficar em branco, porque o WordPress tenta
usar o tema filho como tema pai.

Verificacao visual:

- Home nao pode estar em branco.
- Titulo esperado: `Autelie Moda Pet` ou titulo configurado da loja.
- Header deve exibir logo, busca, atendimento, lista de desejos e minha conta.
- Hero deve exibir a imagem `petshop-004b-hero-wide`.

## 8. Limpeza obrigatoria no servidor

Depois que a loja estiver funcionando, remova:

```text
public_html/petshop-import-*.php
public_html/petshop-fix-*.php
public_html/petshop-cleanup.php
public_html/petshop-write-*.php
public_html/wp-content/petshop-db.sql
public_html/wp-content/uploads.tar.gz
public_html/wp-content/debug.log
public_html/error_log
```

Tambem desative cache/drop-ins temporariamente se eles causarem tela branca:

```text
public_html/wp-content/advanced-cache.php
public_html/wp-content/cache/
public_html/wp-content/speedycache-config/
```

Se remover cache, revise depois se o plugin de cache deve ser reativado/configurado.
Nao deixe cache quebrando carrinho, checkout ou lista de desejos.

## 9. Verificacoes finais

### Home

Abrir:

```text
https://seudominio.com/
```

Deve aparecer:

- logo Autelie Moda Pet;
- menu de categorias;
- busca de produtos;
- hero com cachorro usando bandana;
- blocos de pronta entrega, condicoes para volume e frete;
- footer institucional.

Nao deve aparecer:

```text
Notice:
Warning:
Fatal error:
Parse error:
Hello world!
My Blog
```

### Loja

Abrir:

```text
https://seudominio.com/loja/
```

Deve aparecer:

- filtros de categoria, preco, cor, tamanho e disponibilidade;
- contagem de produtos, por exemplo `EXIBINDO 1-16 DE 116 RESULTADOS`;
- cards de produtos com preco;
- botao `Adicionar ao carrinho`;
- icone de lista de desejos.

### Imagens

Verifique se estas imagens carregam:

```text
wp-content/uploads/2026/07/autelie-logo.png
wp-content/uploads/2026/07/petshop-004b-hero-wide-1536x1024.jpg
wp-content/uploads/woocommerce-placeholder-300x300.webp
```

Se varias imagens de produto virarem placeholder, isso indica produto sem imagem
cadastrada ou midia ausente. Nao e falha do tema.

### Plugin

No WordPress Admin > Plugins:

- WooCommerce ativo.
- Petshop Core ativo.

No File Manager:

```text
public_html/wp-content/plugins/petshop-core/assets/js/wishlist.js
```

precisa existir.

### Logs

Depois da validacao final:

- `public_html/wp-content/debug.log` nao deve existir.
- `public_html/error_log` nao deve existir, ou deve estar vazio/sem erros novos.
- `WP_DEBUG_DISPLAY` deve estar `false`.

## 10. Observacoes importantes

- Nao use o ZIP antigo do plugin se ele incluir `vendor` de desenvolvimento.
- Nao ative `Petshop Core` antes do WooCommerce.
- Nao importe banco em loja ja operando sem backup, porque pedidos/clientes podem ser
  sobrescritos.
- Nao deixe arquivos `.sql`, `.tar.gz` ou scripts temporarios acessiveis em
  `public_html`.
- O cPanel Terminal pode estar bloqueado em hospedagem compartilhada. Nesse caso,
  use File Manager, phpMyAdmin e scripts temporarios removidos ao final.
