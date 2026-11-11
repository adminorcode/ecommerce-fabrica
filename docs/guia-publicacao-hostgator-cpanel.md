# Guia correto de publicacao na HostGator/cPanel

Este projeto e uma loja WordPress/WooCommerce. A publicacao correta nao e enviar o
repositorio inteiro para `public_html`.

Suba apenas estes artefatos:

- `wp-content/` (tema `petshop-theme`, plugins versionados e `uploads`)
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

Na raiz do repositorio:

```powershell
npm run prepare:deploy
```

Saida: `outputs/deploy-cpanel/<stamp>/` com `wp-content/` e `petshop-db.sql`.

O `wp-content` gerado deve conter:

```text
wp-content/themes/petshop-theme/style.css
wp-content/plugins/petshop-core/petshop-core.php
wp-content/plugins/petshop-core/vendor/autoload.php
wp-content/plugins/melhor-envio-cotacao/melhor-envio-beta.php
wp-content/plugins/melhor-envio-cotacao/vendor/autoload.php
wp-content/plugins/woo-better-shipping-calculator-for-brazil/wc-better-shipping-calculator-for-brazil.php
wp-content/plugins/woo-better-shipping-calculator-for-brazil/vendor/autoload.php
wp-content/uploads/
```

O `style.css` do tema precisa ter:

```css
Template: blocksy
```

O plugin nao deve conter `node_modules/`, `tests/` nem vendor de desenvolvimento
(`phpunit`, `myclabs`, `sebastian`). O `prepare-deploy` remove essas pastas e
regenera o Composer com `dump-autoload --no-dev`, para o `vendor/autoload.php`
nao tentar carregar `deep_copy.php`.

O erro real encontrado em producao foi:

```text
Failed opening required .../vendor/myclabs/deep-copy/src/DeepCopy/deep_copy.php
Permission denied
```

Isso acontece quando o autoload ainda aponta para PHPUnit e a pasta `myclabs`
foi apagada (ou ficou no servidor com permissao ilegivel). Reenvie o
`petshop-core` gerado apos o dump `--no-dev` e apague leftovers
`vendor/myclabs`, `vendor/phpunit` e `vendor/sebastian` no servidor.

Verificacao local:

```powershell
$pkg = Get-ChildItem .\outputs\deploy-cpanel | Sort-Object Name -Descending | Select-Object -First 1
Test-Path "$pkg\wp-content\themes\petshop-theme\style.css"
Select-String "$pkg\wp-content\themes\petshop-theme\style.css" -Pattern "Template: blocksy"
Test-Path "$pkg\wp-content\plugins\petshop-core\petshop-core.php"
Test-Path "$pkg\wp-content\plugins\petshop-core\vendor\autoload.php"
Test-Path "$pkg\wp-content\plugins\melhor-envio-cotacao\melhor-envio-beta.php"
Test-Path "$pkg\wp-content\plugins\melhor-envio-cotacao\vendor\autoload.php"
Test-Path "$pkg\wp-content\plugins\woo-better-shipping-calculator-for-brazil\wc-better-shipping-calculator-for-brazil.php"
Test-Path "$pkg\wp-content\plugins\woo-better-shipping-calculator-for-brazil\vendor\autoload.php"
Test-Path "$pkg\wp-content\plugins\petshop-core\vendor\myclabs"
Select-String "$pkg\wp-content\plugins\petshop-core\vendor\composer\autoload_*.php" -Pattern "myclabs|phpunit/phpunit|deep-copy"
Test-Path "$pkg\wp-content\plugins\petshop-core\node_modules"
Test-Path "$pkg\wp-content\plugins\petshop-core\tests"
Test-Path "$pkg\petshop-db.sql"
```

Os quatro `Test-Path` dos plugins de frete versionados devem retornar `True`.
`node_modules`, `tests` e `vendor/myclabs` devem retornar `False`. O
`Select-String` no autoload do `petshop-core` nao pode achar `myclabs`,
`phpunit/phpunit` nem `deep-copy`.

## 3. Enviar arquivos no cPanel

Use FileZilla ou File Manager para enviar a pasta `wp-content/` gerada para
`public_html/wp-content/`, mesclando `themes/petshop-theme`, `plugins/petshop-core`
e `uploads`. Importe `petshop-db.sql` no MySQL do servidor.

Verificacao:

```text
public_html/wp-content/themes/petshop-theme/style.css
public_html/wp-content/plugins/petshop-core/petshop-core.php
public_html/wp-content/plugins/petshop-core/assets/js/wishlist.js
public_html/wp-content/plugins/petshop-core/vendor/autoload.php
public_html/wp-content/plugins/melhor-envio-cotacao/melhor-envio-beta.php
public_html/wp-content/plugins/woo-better-shipping-calculator-for-brazil/wc-better-shipping-calculator-for-brazil.php
public_html/wp-content/uploads/2026/
public_html/wp-content/uploads/woocommerce-placeholder.webp
```

## 4. Ativar WooCommerce, plugin e tema

No WordPress Admin:

1. Ative `WooCommerce`.
2. Ative `Petshop Core`.
3. Ative `Calculadora de Frete e Campos Checkout para o Brasil`.
4. Ative `Melhor Envio`.
5. Ative `Petshop Theme`.

Nao ative `Brazilian Market on WooCommerce` junto com a Calculadora BR. No painel da
Calculadora BR, mantenha desativadas as calculadoras de produto e carrinho; a PDP
usa a calculadora propria do `petshop-core`. Configure token, origem, agencias e
servicos do Melhor Envio somente no painel do WordPress de destino, sem copiar
credenciais para o Git.

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

- `vendor/myclabs`, `phpunit`, `sebastian`, `deep_copy.php` ou `Permission denied` no
  autoload: o plugin foi enviado com autoload de desenvolvimento. Rode
  `npm run prepare:deploy` (o script faz `dump-autoload --no-dev` no pacote),
  substitua o `petshop-core` inteiro e apague leftovers `vendor/myclabs`,
  `vendor/phpunit` e `vendor/sebastian` no servidor. Nao “corrija” com chmod na
  pasta leftover. Nao rode `dump-autoload --no-dev` no plugin do worktree.
- `filemtime() ... assets/js/wishlist.js`: o plugin foi enviado incompleto. Envie
  `assets/js/wishlist.js`.
- `PETSHOP_HERO_ATTACHMENT_MISSING`: o banco e os uploads ainda nao estao alinhados,
  ou a pasta `uploads` nao foi copiada para `public_html/wp-content/`.
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
