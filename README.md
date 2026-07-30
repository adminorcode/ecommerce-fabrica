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
