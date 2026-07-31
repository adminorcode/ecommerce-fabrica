# Plano 009 — Design system, acessibilidade técnica e checkout

**Status:** Pendente  
**Data:** 2026-07-31  
**Dependências:** [005-refinamento-comercial-do-storefront.md](./005-refinamento-comercial-do-storefront.md) (sessões 01–02 concluídas); pode paralelizar com [007-refatoracao-petshop-core.md](./007-refatoracao-petshop-core.md)  
**Branch:** `009-design-system-acessibilidade-e-checkout`  
**Origem:** review UI/UX — tokens inconsistentes, a11y parcial, checkout/cart blocks sem estilo

## 1. Objetivo

Fechar dívida técnica de interface **sem depender de fotografias reais**: consolidar design tokens, corrigir acessibilidade objetiva, limpar CSS, estilizar cart/checkout blocks e preparar base visual para as sessões 04–08 do Plano 005.

## 2. Resultado esperado

- tokens CSS completos (hover, disabled, error, sale) — zero hex solto salvo exceções documentadas;
- `--color-brand-orange` aplicado onde o design system define preço/badge promocional;
- CSS morto removido; seletores de catálogo consolidados com `:is()`;
- header mobile legível (≥ 13px efetivo ou ícone+texto);
- busca de produtos com label/`aria-label` acessível;
- estilos dedicados `.wc-block-cart` e `.wc-block-checkout` alinhados a tokens;
- skip link para conteúdo principal se Blocksy não fornecer;
- template parts opcionais para header/footer comercial;
- gate a11y manual do Plano 004 executado ou pendência formal registrada;
- `/ecommerce-design-review` aplicado com evidências.

## 3. Contexto

O child theme tem boa base (`:focus-visible`, 44px targets, tokens em `:root`), mas review identificou: links ~11px no mobile, 15+ hex hardcoded, classes `.petshop-utility-nav` / `.petshop-primary-nav` sem uso, 11 `!important` no hero, checkout visualmente genérico.

Conteúdo comercial das sessões 03+ do 005 **permanece no Plano 005**; este plano trata infraestrutura visual e a11y técnica.

## 4. Etapas

### Etapa 1 — Tokens e consistência

1. Adicionar tokens: `--color-brand-orange-hover`, `--color-brand-orange-active`, `--color-disabled`, `--color-error`, `--color-neutral-border`.
2. Substituir hex literais em botões, estados disabled, promo bar, filtros.
3. Usar `--color-brand-orange` em preço promocional / `.price ins` quando houver sale real (somente estilo; lógica de exibição continua no 005 Sessão 04).
4. Unificar sombras (`--shadow-card` vs `0 6px 20px...`).

**Gate:** busca por `#7f3008`, `#652505`, `#747172` no `style.css` retorna zero ou exceções listadas em comentário `/* token-exception */`.

### Etapa 2 — Limpeza e estrutura CSS

1. Remover ou conectar `.petshop-utility-nav` / `.petshop-primary-nav` (render menu ou delete CSS).
2. Consolidar seletores triplicados `.woocommerce-shop`, `.tax-product_cat`, `.tax-product_tag` via `:is()`.
3. Reduzir duplicação de regras de botão (hero vs global) com classe compartilhada ou `@layer components`.
4. (Opcional) split em `assets/css/` enfileirados por `functions.php` se `style.css` > 1000 linhas após limpeza.

**Gate:** nenhum seletor morto referenciado em comentário TODO; diff CSS net-negative ou neutral em linhas.

### Etapa 3 — Header, busca e mobile

1. Ajustar `.petshop-commercial-header__actions` mobile: `clamp()` tipográfico mínimo legível.
2. Filtro ou template para `aria-label`/`label` visível na busca WooCommerce.
3. Revisar promo bar linkado: alvo ≥ 44px quando interativo.
4. Nav horizontal mobile: wrap ou indicador de scroll documentado.

**Gate:** screenshot 390px aprovado; teste teclado Tab no header documentado.

### Etapa 4 — Cart e Checkout blocks

1. Seção CSS para `.wc-block-cart`, `.wc-block-checkout`, notices, totais, botão finalizar.
2. Hierarquia: um primário laranja por etapa; secundários outline teal.
3. Não alterar fluxo ou templates WC core — apenas child theme CSS + hooks mínimos se necessário.

**Gate:** cart e checkout HTTP 200; contraste AA nos CTAs; screenshots 390/1440 anexados.

### Etapa 5 — A11y Plano 004

1. Executar roteiro de `Plans/004-TESTING.md` com NVDA ou VoiceOver **ou** registrar aceite explícito do usuário adiando.
2. Corrigir issues P0/P1 encontrados (foco, labels, ordem de leitura).
3. Atualizar `Plans/004-identidade-visual-e-navegabilidade.md` e STATUS.

**Gate:** checklist 004 a11y marcado ou pendência assinada no plano.

### Etapa 6 — Template parts (opcional recomendado)

1. Extrair markup de `wp_body_open` / `wp_footer` para `template-parts/header-commercial.php` e `footer-links.php`.
2. Manter escaping e Customizer iguais.

**Gate:** nenhuma regressão nos validators 005-01; PHPStan/sintaxe ok.

## 5. Inventário de conteúdo

Este plano **não introduz** texto comercial novo. Itens afetados:

| Área | Alteração | Origem administrativa |
|------|-----------|------------------------|
| busca | label a11y | filtro/tema (funcional, traduzível) |
| cart/checkout | estilo apenas | N/A |
| header mobile | tipografia | CSS |

## 6. Fora do escopo

- Fotografias reais (005 Sessão 03);
- cards com dados reais, kits, rodapé institucional completo (005 Sessões 04–07);
- refatoração PHP extensa (Plano 007);
- migração do catálogo para product-collection block;
- gateway Pix ou badges comerciais fabricados.

## 7. Critérios de aceite

- [ ] Tokens novos aplicados; hex soltos eliminados ou documentados
- [ ] CSS morto removido; `:is()` nos seletores de catálogo
- [ ] Mobile header legível e busca acessível
- [ ] Cart/checkout estilizados com tokens petshop
- [ ] Hero mantém gates 005-02 (ratio, eixo CTAs)
- [ ] Plano 004 a11y: teste manual ou aceite documentado
- [ ] `ecommerce-design-review` sem bloqueadores críticos
- [ ] Validators browser existentes passam

## 8. Validação

```powershell
node scripts/validate-005-session-01-browser.mjs
node scripts/validate-005-session-02-browser.mjs
# após Plano 008 containerizado, via docker compose ... node
```

Manual: NVDA/VoiceOver no roteiro 004.

## 9. Evidências obrigatórias

- screenshots before/after 390 e 1440 (header, cart, checkout);
- relatório `/ecommerce-design-review`;
- lista de tokens adicionados;
- status atualizado do Plano 004 a11y.
