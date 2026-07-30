# Petshop Ecommerce

E-commerce baseado em WordPress e WooCommerce.

## Requisitos

- Docker
- Node.js 24 LTS
- npm
- Git

## Bootstrap em um computador novo

```bash
npm run bootstrap
```

Esse comando único:

- valida Node.js 24 e Docker;
- instala as dependências Node;
- baixa e valida WordPress 7.0.2 e WooCommerce 10.9.4;
- sobe o `wp-env`;
- instala o WordPress quando necessário;
- configura idioma, timezone, formatos e permalinks;
- ativa WooCommerce, o plugin local e o tema;
- valida a instalação, os plugins e o tema pelo WP-CLI.

Os downloads e o runtime ficam em `.local/`, fora do Git. O comando é
idempotente e pode ser executado novamente.

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
npm run bootstrap
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

## Tema

O projeto utiliza:

- Blocksy como tema pai;
- `petshop-theme` como child theme;
- Petsy como starter site;
- Gutenberg como editor.

Não editar o tema Blocksy diretamente.

Customizações visuais versionadas:

`wp-content/themes/petshop-theme/`

Regras de negócio:

`wp-content/plugins/petshop-core/`
