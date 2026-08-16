# Guia de publicacao na HostGator com cPanel

Este guia descreve como publicar este projeto em uma hospedagem HostGator com cPanel.
O projeto nao e um site HTML estatico: ele e uma loja WordPress/WooCommerce com codigo
customizado em:

- `wp-content/themes/petshop-theme/`: tema filho do Blocksy.
- `wp-content/plugins/petshop-core/`: plugin proprio com regras de negocio e blocos.

Nao envie o repositorio inteiro para `public_html`. Pastas como `.git`, `docker`,
`node_modules`, `Plans`, `scripts`, `.local` e arquivos `.env` sao de desenvolvimento.

## 1. Pre-requisitos no servidor

Antes de subir o codigo, confirme no cPanel/WordPress:

- WordPress instalado no dominio correto, normalmente em `public_html`.
- PHP 8.3 selecionado para o dominio.
- HTTPS ativo.
- Banco MySQL criado e vinculado ao WordPress.
- Acesso ao painel do WordPress como administrador.
- Acesso ao Gerenciador de Arquivos do cPanel.

Plugins/tema necessarios no WordPress:

- WooCommerce.
- Blocksy, como tema pai.
- Stackable, se as paginas importadas usarem blocos dele.
- Plugin de pagamento aprovado para a loja, por exemplo Mercado Pago.
- Plugin/regra de frete aprovado, conforme decisao operacional do projeto.

## 2. Escolha o tipo de publicacao

Existem dois cenarios diferentes.

### Cenario A - Migrar a loja completa

Use este cenario para levar para a HostGator o site como ele esta no ambiente local:
paginas, produtos, categorias, menus, configuracoes, imagens da Biblioteca de midia e
dados salvos no WordPress.

Arquivos necessarios:

- `petshop-db.sql`: banco de dados WordPress/WooCommerce.
- `uploads.tar.gz`: midias de `wp-content/uploads/`.
- `petshop-theme.zip`: tema filho customizado.
- `petshop-core.zip`: plugin customizado.

Esse e o fluxo correto para primeira publicacao quando o conteudo ja foi montado
localmente.

### Cenario B - Atualizar somente codigo

Use este cenario quando o WordPress de producao ja existe, o banco de producao deve
ser preservado e voce quer atualizar apenas tema/plugin.

Envie somente:

- `petshop-theme.zip`
- `petshop-core.zip`

Nao importe banco nesse cenario, porque isso pode sobrescrever pedidos, clientes,
produtos e configuracoes reais da loja.

## 3. Gerar pacote completo local

O projeto ja possui um servico Docker de backup para exportar banco e uploads.
Com o ambiente local rodando:

```powershell
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$out = Join-Path (Resolve-Path '.').Path ('outputs\hostgator-full-migration\' + $stamp)
New-Item -ItemType Directory -Force -Path $out | Out-Null

docker compose --profile migration run --rm backup

$containerName = 'petshop-copy-backup-' + $stamp
$volumeName = 'petshop_migration_backups'
docker create --name $containerName -v "${volumeName}:/backups:ro" -v "${out}:/out" petshop-wordpress:local sh -c "cp /backups/database.sql /out/petshop-db.sql && cp /backups/uploads.tar.gz /out/uploads.tar.gz && cp /backups/manifest.txt /out/manifest.txt" | Out-Null
docker start -a $containerName | Out-Null
docker rm $containerName | Out-Null
```

Saida esperada:

- `outputs/hostgator-full-migration/<data-hora>/petshop-db.sql`
- `outputs/hostgator-full-migration/<data-hora>/uploads.tar.gz`
- `outputs/hostgator-full-migration/<data-hora>/manifest.txt`

`manifest.txt` guarda hashes SHA256 para conferir se o banco e uploads nao foram
corrompidos.

## 4. Gerar ZIPs de tema e plugin

No computador local, a partir da raiz do repositorio:

```powershell
$root = (Resolve-Path '.').Path
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$out = Join-Path $root ('outputs\deploy-cpanel\' + $stamp)
$stage = Join-Path $out 'stage'
New-Item -ItemType Directory -Force -Path $stage | Out-Null

$themeStage = Join-Path $stage 'petshop-theme'
$pluginStage = Join-Path $stage 'petshop-core'

robocopy (Join-Path $root 'wp-content\themes\petshop-theme') $themeStage /E /XD .local node_modules tests /NFL /NDL /NJH /NJS /NP | Out-Null
robocopy (Join-Path $root 'wp-content\plugins\petshop-core') $pluginStage /E /XD .local node_modules tests /NFL /NDL /NJH /NJS /NP | Out-Null

Compress-Archive -Path $themeStage -DestinationPath (Join-Path $out 'petshop-theme.zip')
Compress-Archive -Path $pluginStage -DestinationPath (Join-Path $out 'petshop-core.zip')
```

Os ZIPs ficam em `outputs/deploy-cpanel/<data-hora>/`.

## 5. Opcoes automaticas da HostGator

A HostGator documenta alternativas mais automaticas para WordPress:

- **All-in-One WP Migration**: recomendado pela HostGator para migrar WordPress pelo
  painel, quando origem e destino permitem usar plugin.
- **WPvivid Backup & Migration**: documentado pela HostGator para gerar `.zip` dos
  arquivos e `.sql` do banco pelo proprio WordPress.
- **Backup manual pelo Portal/cPanel**: permite baixar backup do diretorio inicial e
  banco MySQL.
- **Gator Backup**: servico da HostGator para backups automaticos do site e banco,
  com rotina configuravel e restauracao pela ferramenta.
- **Suporte HostGator**: pode realizar migracao/restauracao em alguns cenarios,
  dependendo do plano, origem e politica vigente.

Para este projeto, se voce esta partindo do ambiente local Docker, o pacote mais
confiavel e o gerado na secao 3. Se voce ja tiver uma instalacao WordPress acessivel
por navegador, plugin de migracao pode ser mais simples.

## 6. Instalar o WordPress no cPanel

Se o WordPress ainda nao estiver instalado:

1. No cPanel, abra o instalador de WordPress disponivel na conta.
2. Escolha o dominio correto.
3. Instale na raiz do dominio. Para a loja principal, o caminho deve ficar como
   `public_html`, sem subpasta intermediaria.
4. Crie usuario administrador individual e senha forte.
5. Acesse `https://seudominio.com/wp-admin`.

Se ja existe WordPress instalado, faca backup antes de substituir tema, plugin, banco
ou uploads. Banco importado em cima de uma loja em uso pode apagar dados atuais.

## 7. Importar o banco de dados

No cPanel:

1. Crie ou identifique o banco MySQL que sera usado pelo WordPress.
2. Abra **phpMyAdmin**.
3. Selecione o banco correto.
4. Abra a aba **Importar**.
5. Envie `petshop-db.sql`.
6. Execute a importacao.

Depois, confira `public_html/wp-config.php`:

```php
define( 'DB_NAME', 'nome_do_banco' );
define( 'DB_USER', 'usuario_do_banco' );
define( 'DB_PASSWORD', 'senha_do_banco' );
define( 'DB_HOST', 'localhost' );
```

Os valores devem bater com o banco criado na HostGator.

Depois da importacao, atualize as URLs antigas para o dominio real. Em WordPress,
essa troca precisa respeitar dados serializados. Prefira uma ferramenta de migracao
WordPress ou WP-CLI com `search-replace`. Nao faca substituicao manual simples dentro
do `.sql` se houver dados serializados.

Exemplo conceitual:

```text
http://localhost:8888 -> https://seudominio.com
```

## 8. Importar uploads

No Gerenciador de Arquivos:

1. Abra `public_html/wp-content/`.
2. Envie `uploads.tar.gz`.
3. Extraia o arquivo ali.
4. Confirme que o caminho ficou:

```text
public_html/wp-content/uploads/
```

Sem `uploads`, o banco pode apontar para imagens que nao existem no servidor.

## 9. Subir o tema pelo cPanel

No Gerenciador de Arquivos:

1. Abra `public_html/wp-content/themes/`.
2. Envie `petshop-theme.zip`.
3. Extraia o ZIP ali.
4. Confirme que o caminho ficou:

```text
public_html/wp-content/themes/petshop-theme/style.css
```

O caminho abaixo esta errado e precisa ser corrigido:

```text
public_html/wp-content/themes/petshop-theme/petshop-theme/style.css
```

No painel do WordPress:

1. Va em **Aparencia > Temas**.
2. Confirme que o tema **Blocksy** esta instalado.
3. Ative **Petshop Theme**.

## 10. Subir o plugin pelo cPanel

No Gerenciador de Arquivos:

1. Abra `public_html/wp-content/plugins/`.
2. Envie `petshop-core.zip`.
3. Extraia o ZIP ali.
4. Confirme que o caminho ficou:

```text
public_html/wp-content/plugins/petshop-core/petshop-core.php
```

No painel do WordPress:

1. Va em **Plugins**.
2. Ative **WooCommerce** primeiro.
3. Ative **Petshop Core** depois.

Se a ativacao falhar por versao de PHP, ajuste o PHP do dominio para 8.3 no cPanel.

## 11. Configurar WooCommerce

No WordPress, revise:

- **WooCommerce > Configuracoes > Geral**: endereco da loja e pais/estado.
- **Produtos**: medidas, peso, estoque e paginas da loja.
- **Entrega**: zonas, metodos, origem, embalagens, servicos e contingencia.
- **Pagamentos**: Pix/cartao, sandbox antes de producao e webhooks/retornos.
- **Emails**: remetente, dominio, templates e recebimento real.
- **Contas e privacidade**: termos, privacidade e comportamento de checkout.
- **Avancado**: paginas de carrinho, checkout, minha conta e termos.

Nao publique a loja em producao sem testar pedido aprovado, recusado e pendente.

## 12. Conteudo editavel

Textos comerciais, institucionais, juridicos e imagens de pagina devem ficar
editaveis no WordPress:

- Home e paginas institucionais: **Paginas > Editar com Gutenberg**.
- Imagens: **Midia > Biblioteca**, com texto alternativo.
- Produtos e categorias: **Produtos** e **Produtos > Categorias**.
- Menus: **Aparencia > Menus** ou editor equivalente do tema.
- Conteudo global: configuracoes administrativas, Customizer ou opcoes do tema.

Nao corrija texto comercial editando PHP, CSS ou JavaScript em producao.

## 13. Checklist antes de abrir a loja

Valide no dominio final:

- Home carrega sem erro visual.
- Loja, categoria, busca e pagina de produto funcionam.
- Carrinho e checkout funcionam em desktop e mobile.
- Pedido aprovado, recusado e pendente foram testados em sandbox.
- Frete calcula com CEP real e produto real.
- Emails transacionais chegam.
- Paginas legais estao publicadas e linkadas no checkout.
- HTTPS esta ativo sem conteudo misto.
- Sitemap e robots estao coerentes.
- Backup e restauracao foram testados.
- Contas administrativas sao individuais e com senha forte.
- Cache/CDN, se usados, nao quebram carrinho e checkout.
- Fluxos principais funcionam por teclado e leitor de tela.

## 14. Checklist apos publicacao

Depois de apontar o dominio e abrir a loja:

- Comprar um produto de baixo valor em producao, se operacionalmente aprovado.
- Confirmar status do pedido no WooCommerce.
- Confirmar email para cliente e loja.
- Confirmar baixa/registro no gateway de pagamento.
- Confirmar calculo de frete e informacao de prazo.
- Monitorar logs de erro no cPanel e no WordPress.
- Medir Core Web Vitals em paginas criticas.
- Verificar indexacao no Google Search Console quando configurado.

## 15. Rotina de backup na HostGator

Para producao, nao dependa apenas de backup manual.

Opcoes:

- **Backup automatico da HostGator**: existe nos planos compartilhados, com rotina
  diaria, semanal e mensal, mas restauracao pode depender do suporte e taxa.
- **Gator Backup**: indicado quando a loja precisa de backup automatico configuravel,
  retencao e restauracao mais rapida pelo cliente.
- **Backup manual antes de mudancas**: baixe banco MySQL e arquivos pelo Portal/cPanel
  antes de atualizar plugin, tema, WordPress ou WooCommerce.

Para WooCommerce, backup deve cobrir banco e arquivos. O banco guarda pedidos,
clientes, produtos e configuracoes; `uploads` guarda imagens e anexos.

## 16. Reversao

Antes de qualquer atualizacao em producao, mantenha:

- backup do banco;
- backup de `wp-content/uploads/`;
- copia dos ZIPs anteriores de `petshop-theme` e `petshop-core`;
- anotacao da versao ativa de WordPress, WooCommerce, Blocksy e plugins.

Para reverter codigo:

1. Desative `Petshop Core` se houver erro fatal.
2. Restaure a versao anterior de `wp-content/plugins/petshop-core/`.
3. Restaure a versao anterior de `wp-content/themes/petshop-theme/` se necessario.
4. Reimporte o banco apenas se a mudanca alterou dados e houver backup consistente.

## 17. Pendencias P0 de producao

O Plano 017 ainda esta pendente. A instalacao tecnica no cPanel nao encerra a
publicacao operacional enquanto estes itens nao forem validados:

- Mercado Pago sandbox e cenarios aprovado/recusado/pendente.
- Frete real, origem, embalagens, contrato/credenciais e contingencia.
- Politicas juridicas aprovadas e publicadas.
- Emails transacionais e entregabilidade.
- SEO tecnico, sitemap, robots e dados estruturados.
- Core Web Vitals.
- Backup/restore.
- Monitoramento e logs.
- Validacao manual de acessibilidade com teclado, NVDA e VoiceOver.

## 18. Fontes HostGator consultadas

- Migracao de WordPress: https://suporte.hostgator.com.br/hc/pt-br/articles/30814843869715-Como-migrar-um-site-feito-no-WordPress
- Gerar `.zip` e `.sql`: https://suporte.hostgator.com.br/hc/pt-br/articles/40007325064595-Como-gerar-arquivos-zip-e-sql-para-migrar-site-WordPress
- Backup manual: https://suporte.hostgator.com.br/hc/pt-br/articles/30807223548563-Como-fazer-uma-c%C3%B3pia-de-seguran%C3%A7a-backup-na-HostGator
- Tipos de backup e Gator Backup: https://suporte.hostgator.com.br/hc/pt-br/articles/51908984420115-Qual-a-diferen%C3%A7a-entre-o-backup-da-HostGator-backup-manual-e-Gator-Backup
- Importar banco via phpMyAdmin: https://suporte.hostgator.com.br/hc/pt-br/articles/30814715222803-Como-importar-um-banco-de-dados-atrav%C3%A9s-do-phpMyAdmin
