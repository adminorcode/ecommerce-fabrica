# Plano 001 — Instalação do Blocksy + Petsy

**Status:** Concluído
**Data:** 2026-07-29
**Tema pai:** Blocksy 2.1.50
**Starter site:** Petsy
**Editor:** Gutenberg
**Page builder externo:** Nenhum
**Plugin auxiliar:** Blocksy Companion 2.1.50

---

## 1. Objetivo

Substituir o tema mínimo criado no bootstrap por uma base visual pronta para um e-commerce pet, usando:

- Blocksy como tema pai;
- `petshop-theme` como child theme versionado;
- starter site Petsy;
- Gutenberg como editor;
- WooCommerce como motor da loja;
- nenhum uso de Elementor.

Ao final, a loja deverá possuir homepage, catálogo, páginas de produto, cabeçalho, rodapé e páginas institucionais iniciais funcionando localmente.

---

## 2. Decisão técnica

```text
Tema pai: Blocksy
Child theme: petshop-theme
Starter site: Petsy
Editor: Gutenberg
Elementor: não instalar
Blocksy Pro: não utilizar
```

O Blocksy será tratado como dependência externa e nunca deverá ser alterado diretamente.

Customizações visuais versionadas pertencem a:

```text
wp-content/themes/petshop-theme/
```

Regras de negócio permanecem em:

```text
wp-content/plugins/petshop-core/
```

---

## 3. Escopo

Este plano inclui:

- instalação do Blocksy;
- instalação do Blocksy Companion;
- transformação do `petshop-theme` em child theme;
- importação do Petsy com Gutenberg;
- validação dos plugins importados;
- remoção do Elementor, caso seja instalado;
- validação de páginas, menus e WooCommerce;
- atualização do README e das regras do Cursor.

---

## 4. Fora do escopo

Não implementar neste plano:

- identidade visual definitiva;
- logo, cores e tipografia finais;
- produtos e preços reais;
- Pix, cartão ou gateway;
- cálculo de frete;
- impostos e emissão fiscal;
- ERP;
- SEO e analytics;
- LGPD;
- deploy;
- otimização avançada.

---

## 5. Pré-condições

- [x] O Plano 000 foi concluído.
- [x] `npm run env:start` funciona.
- [x] WooCommerce está ativo.
- [x] `petshop-core` está ativo.
- [x] Não existem dados reais no banco.
- [x] O Git não possui alterações importantes pendentes.

Validar:

```bash
git status
npm run env:status
npm run wp -- core version
npm run wp -- plugin list
npm run wp -- theme list
```

---

## 6. Preparar backup local

Adicionar ao `.gitignore`:

```gitignore
.local/
```

Criar diretório:

```bash
mkdir -p .local/backups
```

Como o ambiente ainda é descartável, a opção preferencial antes da importação é iniciar com banco limpo:

```bash
npm run env:reset
npm run env:start
```

Não utilizar essa opção caso já existam dados que precisem ser preservados.

---

## 7. Atualizar `.wp-env.json`

Usar Blocksy e Blocksy Companion com versões fixadas:

```json
{
  "$schema": "https://schemas.wp.org/trunk/wp-env.json",
  "core": "https://wordpress.org/wordpress-7.0.2.zip",
  "phpVersion": "8.3",
  "plugins": [
    "https://downloads.wordpress.org/plugin/woocommerce.10.9.4.zip",
    "https://downloads.wordpress.org/plugin/blocksy-companion.2.1.50.zip",
    "./wp-content/plugins/petshop-core"
  ],
  "themes": [
    "https://downloads.wordpress.org/theme/blocksy.2.1.50.zip",
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

Atualizações do Blocksy ou Blocksy Companion deverão possuir plano próprio.

---

## 8. Converter `petshop-theme` em child theme

Remover arquivos do tema mínimo anterior:

```text
wp-content/themes/petshop-theme/parts/
wp-content/themes/petshop-theme/templates/
wp-content/themes/petshop-theme/patterns/
wp-content/themes/petshop-theme/theme.json
```

Manter:

```text
wp-content/themes/petshop-theme/
├── assets/
├── functions.php
└── style.css
```

### `style.css`

Substituir por:

```css
/*
Theme Name: Petshop Theme
Theme URI: https://example.invalid
Description: Child theme do Blocksy para o e-commerce do petshop.
Author: Petshop
Version: 0.1.0
Template: blocksy
Requires at least: 7.0
Requires PHP: 8.3
Text Domain: petshop-theme
*/
```

O campo obrigatório é:

```text
Template: blocksy
```

### `functions.php`

Substituir por:

```php
<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action(
    'wp_enqueue_scripts',
    static function (): void {
        wp_enqueue_style(
            'petshop-theme',
            get_stylesheet_uri(),
            [],
            wp_get_theme()->get('Version')
        );
    }
);
```

Não adicionar regras de preço, estoque, checkout, pagamentos ou integrações ao `functions.php`.

---

## 9. Recriar o ambiente

```bash
npm run env:stop
npm run env:start
```

Caso as novas dependências não sejam atualizadas:

```bash
npm run env:update
```

Validar:

```bash
npm run wp -- theme list
npm run wp -- plugin list
```

Esperado:

```text
Temas:
- blocksy
- petshop-theme

Plugins:
- woocommerce
- blocksy-companion
- petshop-core
```

---

## 10. Ativação inicial

Antes da importação, ativar o tema pai:

```bash
npm run wp -- theme activate blocksy
npm run wp -- plugin activate blocksy-companion
npm run wp -- plugin activate woocommerce
npm run wp -- plugin activate petshop-core
```

Validar:

```bash
npm run wp -- theme status blocksy
npm run wp -- plugin status blocksy-companion
```

---

## 11. Importar o Petsy

A importação será executada pelo painel administrativo.

Abrir:

```text
http://localhost:8888/wp-admin
```

Acessar:

```text
Blocksy → Starter Sites
```

Selecionar:

```text
Petsy
```

Escolher obrigatoriamente:

```text
Gutenberg
```

Não escolher:

```text
Elementor
```

### Opções de importação

Em uma instalação local limpa, importar:

- páginas;
- produtos demonstrativos;
- imagens demonstrativas;
- menus;
- widgets;
- configurações do Customizer;
- configurações visuais do WooCommerce.

### Child theme

Não permitir que o importador crie outro child theme.

O projeto já possui:

```text
wp-content/themes/petshop-theme/
```

---

## 12. Plugins durante a importação

O Petsy pode sugerir:

- WooCommerce;
- Stackable;
- WPForms;
- Elementor.

Decisão:

| Plugin | Decisão |
|---|---|
| WooCommerce | Manter |
| Blocksy Companion | Manter |
| Stackable | Manter somente se os blocos Gutenberg dependerem dele |
| WPForms Lite | Manter somente se a página de contato utilizar o formulário |
| Elementor | Não instalar |
| Elementor Pro | Não instalar |

Após a importação:

```bash
npm run wp -- plugin list
```

Caso Elementor tenha sido instalado:

```bash
npm run wp -- plugin deactivate elementor
npm run wp -- plugin delete elementor
```

Também remover addons que dependam dele.

---

## 13. Ativar o child theme

Depois que o Petsy estiver importado:

```bash
npm run wp -- theme activate petshop-theme
```

Validar:

```bash
npm run wp -- theme status petshop-theme
```

A aparência importada deverá permanecer, pois o child theme herda o Blocksy.

Se o visual for perdido, verificar:

- `Template: blocksy` no `style.css`;
- tema pai instalado;
- configurações do Customizer importadas;
- starter escolhido na versão Gutenberg;
- plugins de blocos necessários ativos.

---

## 14. Idioma

```bash
npm run wp -- language theme install blocksy pt_BR
npm run wp -- language plugin install blocksy-companion pt_BR
npm run wp -- language plugin install woocommerce pt_BR

npm run wp -- language core update
npm run wp -- language plugin update --all
npm run wp -- language theme update --all
```

---

## 15. Validar páginas

Listar páginas:

```bash
npm run wp -- post list   --post_type=page   --fields=ID,post_title,post_status,post_name
```

Confirmar páginas equivalentes a:

- Home;
- Loja;
- Carrinho;
- Finalização de compra;
- Minha conta;
- Sobre;
- Contato.

Verificar páginas do WooCommerce:

```bash
npm run wp -- option get woocommerce_shop_page_id
npm run wp -- option get woocommerce_cart_page_id
npm run wp -- option get woocommerce_checkout_page_id
npm run wp -- option get woocommerce_myaccount_page_id
```

Nenhum valor deve ser `0`.

---

## 16. Validar homepage

```bash
npm run wp -- option get show_on_front
npm run wp -- option get page_on_front
```

Esperado:

```text
show_on_front = page
page_on_front = ID da homepage importada
```

Caso necessário:

```bash
npm run wp -- option update show_on_front page
npm run wp -- option update page_on_front <ID_DA_HOME>
```

---

## 17. Validar menus

```bash
npm run wp -- menu list
npm run wp -- menu location list
```

O menu principal deverá possuir pelo menos:

- Home;
- Loja;
- Sobre;
- Contato;
- Minha conta, quando aplicável.

Remover links de demonstração que não serão utilizados.

---

## 18. Produtos demonstrativos

Listar:

```bash
npm run wp -- post list   --post_type=product   --fields=ID,post_title,post_status   --format=table
```

Os produtos importados são apenas conteúdo local de desenvolvimento.

Não reutilizar automaticamente em produção:

- nomes;
- preços;
- SKUs;
- descrições;
- imagens;
- marcas;
- avaliações.

---

## 19. Imagens demonstrativas

As imagens importadas não devem ser tratadas como ativos finais.

Regras:

- substituir imagens antes do lançamento;
- validar licenças antes de qualquer uso em produção;
- não versionar `wp-content/uploads/`;
- não reutilizar logos ou marcas da demonstração.

---

## 20. Validar Stackable

Antes de remover Stackable:

- abrir a homepage;
- editar a homepage no Gutenberg;
- verificar blocos ausentes;
- verificar erros no console.

Encontrar o slug:

```bash
npm run wp -- plugin list
```

Manter o plugin somente se páginas importadas dependerem dele.

---

## 21. Validar WPForms

Verificar se a página de contato utiliza WPForms.

Caso não utilize:

```bash
npm run wp -- plugin deactivate wpforms-lite
npm run wp -- plugin delete wpforms-lite
```

Não configurar SMTP nem e-mails reais neste plano.

---

## 22. Limpeza

Após validar a importação:

- remover posts genéricos;
- remover comentários de demonstração;
- remover formulários não utilizados;
- remover páginas duplicadas;
- remover plugins inativos desnecessários;
- remover temas não utilizados, preservando o Blocksy;
- manter opcionalmente um tema oficial como fallback.

---

## 23. Testes visuais

Validar nas larguras:

```text
375 px
768 px
1024 px
1440 px
```

Verificar:

- cabeçalho;
- menu mobile;
- busca;
- carrinho;
- cards de produtos;
- categorias;
- banners;
- depoimentos;
- rodapé;
- página de produto;
- carrinho;
- checkout;
- minha conta.

Não aceitar:

- scroll horizontal;
- botões sobrepostos;
- textos cortados;
- imagens deformadas;
- conteúdo fora da tela;
- blocos ausentes.

---

## 24. Testes funcionais mínimos

### Catálogo

- [x] A loja abre.
- [x] Categorias abrem.
- [x] Produtos abrem.
- [x] A busca retorna resultados.

### Carrinho

- [x] Produto pode ser adicionado.
- [x] Quantidade pode ser alterada.
- [x] Produto pode ser removido.
- [x] Total é recalculado.

### Checkout

- [x] A página abre.
- [x] Campos são exibidos.
- [x] Não há erro fatal.

### Conta

- [x] Minha conta abre.
- [x] Login e registro aparecem conforme configuração.

---

## 25. Logs

```bash
npm run env:logs
```

Verificar debug log:

```bash
npx wp-env run cli bash -c   "test -f wp-content/debug.log && cat wp-content/debug.log || true"
```

Não aceitar:

- fatal errors;
- parse errors;
- exceções não tratadas;
- warnings recorrentes do child theme;
- erros de blocos ausentes.

---

## 26. Segurança

Validar:

```bash
npm run wp -- config get DISALLOW_FILE_EDIT
npm run wp -- config get WP_DEBUG_DISPLAY
npm run wp -- config get WP_ENVIRONMENT_TYPE
```

Esperado:

```text
DISALLOW_FILE_EDIT = 1
WP_DEBUG_DISPLAY = false ou vazio
WP_ENVIRONMENT_TYPE = local
```

Não inserir dados reais de clientes, pagamentos ou credenciais.

---

## 27. Atualizar README

Adicionar:

```md
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
```

---

## 28. Atualizar regras do Cursor

Adicionar em `.cursor/rules/project.mdc`:

```md
# Tema e interface

- Blocksy é o tema pai e não pode ser alterado diretamente.
- `petshop-theme` é o child theme versionado.
- O projeto utiliza Gutenberg.
- Não instalar ou utilizar Elementor.
- Não sobrescrever templates do Blocksy sem necessidade comprovada.
- Preferir hooks e CSS localizado no child theme.
- Não remover Stackable antes de verificar dependências dos blocos importados.
```

---

## 29. Reprodutibilidade

A importação do starter site grava conteúdo no banco.

Consequências:

- o Git reproduz código e dependências;
- o banco local contém o conteúdo importado;
- `wp-env reset` remove o Petsy;
- a importação ainda possui uma etapa manual.

Criar futuramente:

```text
Plans/002-seed-conteudo-local.md
```

Esse plano deverá automatizar:

- páginas;
- menus;
- categorias;
- produtos fictícios;
- configurações;
- conteúdo local após reset.

Não versionar banco bruto como solução definitiva.

---

## 30. Rollback

Em ambiente descartável:

```bash
npm run env:reset
npm run env:start
```

Para desfazer alterações de código:

```bash
git restore -p .wp-env.json
git restore wp-content/themes/petshop-theme
git restore .cursor/rules/project.mdc
git restore README.md
```

Ao restaurar `.wp-env.json`, reverter somente as dependências do Plano 001 e preservar
o bloco preexistente `env.tests.phpmyadminPort`.

---

## 31. Critérios de aceite

- [x] Blocksy 2.1.50 está instalado.
- [x] Blocksy Companion 2.1.50 está instalado.
- [x] `petshop-theme` é reconhecido como child theme.
- [x] `petshop-theme` está ativo.
- [x] Petsy foi importado com Gutenberg.
- [x] Elementor não está instalado.
- [x] WooCommerce está ativo.
- [x] `petshop-core` está ativo.
- [x] Stackable 3.19.10 está fixado no `wp-env` e ativo.
- [x] Fluent Forms 6.2.9 está fixado no `wp-env` e ativo.
- [x] Homepage está configurada.
- [x] Loja, carrinho, checkout e conta estão vinculados.
- [x] Catálogo demonstrativo abre.
- [x] Carrinho funciona.
- [x] Checkout abre sem erro fatal.
- [x] Menu mobile funciona.
- [x] Não existem blocos ausentes.
- [x] Não existem erros fatais no log.
- [x] Banco e uploads não aparecem no Git.
- [x] README foi atualizado.
- [x] Regras do Cursor foram atualizadas.

---

## 32. Validação final

```bash
git status
git diff --check

npm run env:status

npm run wp -- core version
npm run wp -- plugin list
npm run wp -- theme list

npm run wp -- theme status petshop-theme
npm run wp -- theme status blocksy

npm run wp -- plugin status woocommerce
npm run wp -- plugin status blocksy-companion
npm run wp -- plugin status petshop-core

npm run wp -- option get show_on_front
npm run wp -- option get page_on_front

npm run wp -- option get woocommerce_shop_page_id
npm run wp -- option get woocommerce_cart_page_id
npm run wp -- option get woocommerce_checkout_page_id
npm run wp -- option get woocommerce_myaccount_page_id
```

---

## Notas da implementação

**Data:** 2026-07-29

- Blocksy e Blocksy Companion 2.1.50 foram fixados no `wp-env`.
- Petsy foi importado com Gutenberg usando o comando oficial do Blocksy Companion após o importador visual permanecer em 0%.
- Stackable 3.19.10 e Fluent Forms 6.2.9 foram mantidos ativos porque o conteúdo importado depende de seus blocos.
- Stackable 3.19.10 e Fluent Forms 6.2.9 também foram fixados no `.wp-env.json`, permitindo recriar as dependências do conteúdo após destruir o ambiente.
- As configurações importadas do Blocksy foram aplicadas ao child theme e o menu principal foi atribuído às localizações desktop e mobile.
- Links do starter site presentes nos CTAs e categorias foram convertidos para URLs locais.
- Páginas duplicadas, comentário genérico, plugins padrão inativos e temas padrão excedentes foram removidos; Twenty Twenty-Five foi preservado como fallback.
- O `wp-env` reportou modo offline ao aplicar a configuração. Os arquivos de runtime e as dependências fixadas foram recuperados localmente; a configuração versionada permanece em `.wp-env.json`.
- A loja saiu do modo “Em breve” para permitir validação como visitante local.

## 33. Prompt para o Cursor

```text
Leia integralmente:

- Plans/000-bootstrap-woocommerce-local.md
- Plans/001-instalacao-blocksy-petsy.md
- .cursor/rules/project.mdc

Implemente somente o Plano 001.

Decisões obrigatórias:

- Blocksy será o tema pai.
- petshop-theme será o child theme.
- Petsy deverá usar Gutenberg.
- Elementor não deve ser instalado.
- Não altere Blocksy, WooCommerce ou plugins de terceiros.
- Regras de negócio permanecem no petshop-core.

Execute primeiro as alterações versionáveis.
A importação pelo painel deve ser apresentada como etapa manual.

Após cada etapa:

1. valide os arquivos;
2. execute os comandos aplicáveis;
3. registre falhas;
4. marque somente checkboxes concluídos.

Ao final, informe:

- arquivos criados, removidos ou alterados;
- comandos executados;
- etapas manuais restantes;
- plugins instalados;
- critérios de aceite;
- limitações.
```

---

## 34. Commit sugerido

```bash
git add .cursor/rules/project.mdc .gitignore README.md
git add Plans/000-bootstrap-woocommerce-local.md Plans/001-instalacao-blocksy-petsy.md Plans/STATUS.md
git add wp-content/themes/petshop-theme
git add -p .wp-env.json
git commit -m "feat: configure Blocksy Petsy storefront"
```

No `git add -p .wp-env.json`, usar a edição interativa (`e`) para incluir somente
as dependências do Plano 001 e excluir o bloco preexistente
`env.tests.phpmyadminPort`. Não adicionar
`wp-content/plugins/petshop-core/composer.lock` sem revisão e intenção explícitas.

Antes:

```bash
git status
git diff --check
git ls-files
```

Confirmar que não foram incluídos:

- uploads;
- banco;
- logs;
- cache;
- credenciais;
- plugins de terceiros;
- tema pai Blocksy.

---

## 35. Referências

- https://wordpress.org/themes/blocksy/
- https://wordpress.org/plugins/blocksy-companion/
- https://creativethemes.com/blocksy/starter-site/petsy/
- https://creativethemes.com/blocksy/docs/general/install-demo-contents/
- https://creativethemes.com/blocksy/docs/general/child-theme/
- https://creativethemes.com/blocksy/docs/troubleshooting/minimum-system-requirements/
