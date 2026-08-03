# Plano 010b — Lista de desejos (extensão do 010)

**Status:** Concluído  
**Data:** 2026-08-02  
**Branch:** `010-layout-secoes-produto-home`  
**Dependências:** [010-layout-secoes-produto-home.md](./010-layout-secoes-produto-home.md) (botão nos cards)  
**Relacionamento:** conclui a Sessão 06 opcional do Plano 010

## 1. Objetivo

Permitir que clientes **vejam e gerenciem** produtos salvos na wishlist: página pública administrável, rota em **Minha conta**, link no header e sincronização visitante → conta logada.

## 2. Resultado esperado

- página **`/lista-de-desejos/`** com shortcode `[petshop_wishlist]`;
- endpoint **`/minha-conta/lista-de-desejos/`** (logado) com a mesma grade;
- link **Lista de desejos** no header (rótulo editável no Customizer);
- visitante: lista via `localStorage`; ao logar, merge para user meta;
- logado: lista persistida em `petshop_wishlist_product_ids`;
- seção vazia com mensagem editável na página (Gutenberg);
- mesmos cards da vitrine (imagem full-bleed, wishlist, CTA).

## 3. Inventário de conteúdo

| Item | Origem de edição |
| --- | --- |
| Título e texto introdutório da página | **Páginas → Lista de desejos** |
| Mensagem de lista vazia (fallback do shortcode) | Atributo `empty` do shortcode na página |
| Rótulo do link no header | **Personalizar → Conteúdo da loja → Rótulo da lista de desejos** |
| Produtos exibidos | WooCommerce (somente IDs salvos pelo cliente) |

## 4. Escopo

- shortcode `[petshop_wishlist empty="…"]`;
- endpoint WooCommerce `lista-de-desejos`;
- provisionamento da página via `petshop-core`;
- merge `localStorage` → conta no login;
- CSS da página vazia + link no header;
- script de validação PHP.

## 5. Fora de escopo

- plugin de terceiros (YITH, Blocksy Pro wishlist);
- e-mail de alerta de preço;
- wishlist compartilhável por URL pública;
- contador dinâmico no header (fase futura).

## 6. Critérios de aceite

- [x] Página `/lista-de-desejos/` HTTP 200 com shortcode
- [x] Minha conta exibe item **Lista de desejos** e renderiza produtos salvos
- [x] Header exibe link configurável para a página
- [x] Visitante vê produtos salvos no browser na página
- [x] Login preserva itens do `localStorage` na conta
- [x] Lista vazia sem buraco de layout
- [x] `Plans/STATUS.md` atualizado

## 7. Validação

```bash
docker compose --profile tools run --rm --no-deps cli wp eval-file scripts/validate-010b-wishlist.php
```
