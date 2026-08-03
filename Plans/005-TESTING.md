# Evidências de teste — Plano 005

**Data:** 2026-07-31
**Branch:** `005-refinamento-comercial-do-storefront`

## Ledger de execução

| Sessão/step | Evidência | Tentativa 1 | Tentativa 2 | Estado |
|---|---|---|---|---|
| 01 — baseline desktop/mobile | Playwright, 1440 × 1000 e 390 × 844 | aprovado | — | concluído |
| 01 — busca por SKU exato | `C1100046` | retornou zero produtos | redirecionou ao produto correto | aprovado |
| 01 — persistência administrativa | `test-005-session-01-persistence.php` | aprovado | — | concluído |
| 01 — cadastro WordPress | `validate-005-session-01.php` | aprovado | — | concluído |
| 01 — gate funcional/responsivo | `validate-005-session-01-browser.mjs` | aprovado | — | concluído |
| 02 — H1 mobile | linhas renderizadas | palavra isolada | 2/3/2 palavras por linha | aprovado |
| 02 — persistência Gutenberg | `test-005-session-02-persistence.php` | aprovado | — | concluído |
| 02 — gate funcional/responsivo | `validate-005-session-02-browser.mjs` | aprovado | — | concluído |
| 02 — matriz de migração | fresh/legacy/campanha/customizações/remoção | aprovado | — | concluído |
| 02 — editor Gutenberg autenticado | salvar título/imagem/alt/URLs/benefício | Cover inválido | schema 9 válido e save aprovado | concluído |

## Sessão 01 — Cabeçalho

### Baseline

Runtime `petshop-storefront-preview`: `healthy`.

| Medida | Desktop | Mobile |
|---|---:|---:|
| status HTTP | 200 | 200 |
| inputs de busca visíveis | 1 | 1 |
| gatilhos adicionais de busca | 2 | 1 |
| links textuais `Carrinho` | 1 | 1 |
| minicarrinhos | 1 | 1 |
| títulos do site duplicando o logo | 1 | 1 |
| topo do hero | 224 px | 304,59 px |
| overflow horizontal | 0 | 0 |
| erros de página | 0 | 0 |

Menu desktop inicial: `Início`, `Personalize`, `Comprar`.

Capturas:

- `.local/evidence/005/session-01/baseline-desktop.png`
- `.local/evidence/005/session-01/baseline-mobile.png`

### Gate final

O cabeçalho foi validado em 1440, 1024, 768 e 390 px.

| Critério | Resultado |
|---|---|
| status HTTP da Home | 200 nas quatro viewports |
| busca de produto visível | exatamente 1 |
| minicarrinho visível | exatamente 1 |
| link textual redundante `Carrinho` | 0 |
| título do site duplicando o logo | 0 |
| menu comercial | 7 rótulos esperados; 7 destinos HTTP 200 |
| busca por nome | redireciona para `Conjunto Babador + Laço em Feltro` |
| busca por SKU `C1100046` | redireciona para `Conjunto Babador + Laço em Feltro` |
| contador do carrinho | muda para 1 depois da adição |
| overflow horizontal | 0 px nas quatro viewports |
| erros JavaScript de página | 0 |
| foco por teclado | outline visível no menu |
| persistência | mensagem, link, logo, rótulo e localização preservados após reprovisionamento |

Capturas finais:

- `.local/evidence/005/session-01/final-desktop-1440.png`
- `.local/evidence/005/session-01/final-mobile-390.png`

Comandos reproduzíveis:

```powershell
docker exec petshop-storefront-preview wp eval-file /tmp/validate-005-session-01.php --path=/var/www/html --allow-root
docker exec petshop-storefront-preview wp eval-file /tmp/test-005-session-01-persistence.php --path=/var/www/html --allow-root
$env:NODE_PATH='C:\Users\lucas\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node scripts/validate-005-session-01-browser.mjs
```

Revisão crítica: aprovada após duas rodadas. Foram corrigidos conteúdo global ainda fixo, falso positivo de persistência, header nativo mantido no DOM, Atendimento oculto no mobile, estado vazio da barra, cobertura real de teclado e preservação da escolha administrativa de ocultar Atendimento.

## Sessão 02 — Hero institucional e benefícios

### Reabertura visual — contraste do hero e da busca

A inspeção visual do cliente revelou dois desvios que os checks estruturais não
capturavam: o SVG da busca permanecia escuro sobre o botão azul-petróleo por uma
variável do tema-base, e a opacidade nativa do bloco Cover enfraquecia o
gradiente claro atrás do texto do hero. A correção define cores de primeiro
plano para fundos de marca, força o SVG a herdar `currentColor` e mantém o
gradiente do hero com opacidade integral.

| Step reaberto | Tentativa 1 | Tentativa 2 | Recuperação segura | Estado |
|---|---|---|---|---|
| superfície de contraste do hero | falhou: opacidade computada `0.2` | falhou: runtime ainda servia a folha anterior | sincronização explícita do CSS e seletor compatível com o Gutenberg | passou nas quatro viewports |
| contraste do ícone da busca | — | — | `currentColor` explícito no SVG | passou nas quatro viewports |
| eixo esquerdo do hero | falhou na inspeção do cliente: elementos com recuos diferentes | — | neutralização das margens automáticas do layout constrained | passou nas quatro viewports |

### Baseline

- campanha `Coleção Dia dos Pais` ocupava o hero e o H1;
- havia apenas um CTA sazonal;
- benefícios comerciais estavam misturados à campanha;
- não existia faixa própria de três benefícios.

Capturas:

- `.local/evidence/005/session-02/baseline-desktop.png`
- `.local/evidence/005/session-02/baseline-mobile.png`

### Gate final

| Critério | Resultado |
|---|---|
| hero desktop | 1440 × 440 px; proporção 3,27:1 |
| H1 | copy institucional, sem `<br>` e sem linha com palavra isolada nas quatro viewports |
| imagem provisória | alt editável; cabeça e bandana visíveis na inspeção desktop/mobile |
| CTAs | 2 links editáveis, alcançáveis por Tab e destinos HTTP 200 |
| benefícios | 3 blocos editáveis, sem overflow |
| campanha sazonal | ausente do H1/hero; categoria sazonal preservada |
| persistência | título, imagem, alt, dois URLs e benefício preservados após reprovisionamento |
| migrações | fresh, legacy, campanha, hash divergente, hero removido e benefícios customizados/ausentes aprovados |
| Gutenberg | blocos válidos; edição autenticada salvou título, imagem, alt, URLs e benefício em página controlada |
| runtime | HTTP 200 e zero erro de página em 1440, 1024, 768 e 390 px |
| contraste do hero | gradiente computado com opacidade `1`; texto escuro sobre superfície clara AA |
| contraste da busca | SVG computado branco sobre botão `rgb(23, 103, 106)`; razão acima de 3:1 |
| alinhamento do hero | eyebrow, H1, descrição e CTAs compartilham o mesmo eixo esquerdo nas quatro viewports |

Capturas finais:

- `.local/evidence/005/session-02/final-desktop-1440.png`
- `.local/evidence/005/session-02/final-mobile-390.png`

Validadores:

- `scripts/validate-005-session-02.php`
- `scripts/test-005-session-02-persistence.php`
- `scripts/validate-005-session-02-browser.mjs`

A imagem continua provisória, conforme permitido nesta sessão. A substituição final depende do acervo real exigido pela Sessão 03.

Revisão crítica: aprovada após três rodadas. O fechamento incluiu assinatura fresh, schema 9 compatível com Gutenberg, matriz de migração, recorte sem cortar a cabeça, gate estrutural pós-edição, campanha secundária renderizada e substituição real de attachment pelo editor autenticado.

## Sessão 03 — Auditoria da pré-condição fotográfica

**Estado:** bloqueada por dependência editorial do cliente.

Foram executadas três verificações independentes:

1. repositório e `scripts/data/004b-products.json`: nove imagens explicitamente licenciadas como Pexels/placeholder e nenhuma fotografia real versionada;
2. Biblioteca de mídia do WordPress: 13 attachments, dos quais nove são placeholders Pexels, um é placeholder WooCommerce, um é o logo, um é arte auxiliar e apenas `Adesivo Cascão` (attachment 13) é fotografia real identificável do produto;
3. `products (12).xlsx`: uma planilha `Sheet1`, intervalo `A1:T175`, 20 colunas cadastrais e zero drawing, imagem incorporada ou coluna de URL fotográfica.

Consequência: não há acervo suficiente para dar imagem distinta e semanticamente correta ao hero, às categorias principais e aos produtos da Home. Reutilizar Pexels, gerar imagens ou inventar fotografias violaria a pré-condição não negociável e o gate de zero placeholder genérico.

Evidências:

- `.local/evidence/005/session-03-attachment-13.webp`
- `.local/spreadsheet-audit-005/Sheet1.png`
- inventário WP-CLI de attachments registrado na execução de 2026-07-31.

Para retomar: fornecer fotografias reais autorizadas, idealmente ao menos uma por categoria principal e uma imagem horizontal para o hero, ou indicar uma fonte oficial existente da própria marca que possa ser importada.

## Preparação visual da Sessão 04 — layout do catálogo

Escopo autorizado antes do desbloqueio fotográfico: substituir o bloco horizontal
de chips por uma composição de sidebar e grade. Esta entrega valida somente o
layout; o campo textual e os checkboxes não devem ser considerados filtros de
produto funcionais até a aprovação visual e o gate comportamental subsequente.

| Check | Método | Resultado esperado |
|---|---|---|
| desktop | `node scripts/validate-005-catalog-layout-browser.mjs` | sidebar à esquerda e toolbar/grade à direita |
| mobile | mesmo comando em 390 px | sidebar antes da grade, sem overflow |
| semântica | inspeção Playwright | um campo textual, checkboxes rotulados e categoria atual marcada |
| funcionalidade real | query string `petshop_categories` + `tax_query IN` | seleção simples e múltipla atualiza os produtos |

Resultado do gate estrutural: aprovado em 1440, 1024 e 390 px. A sidebar mede
288 px no desktop, contém 11 categorias com contagens reais e marca a categoria
atual; no mobile ocupa a largura disponível e antecede a toolbar e os dois cards.
Não houve overflow horizontal nem erro de página.

Capturas:

- `.local/evidence/005/catalog-layout/desktop-1440.png`
- `.local/evidence/005/catalog-layout/mobile-390.png`

Gate funcional adicionado após o feedback: a busca textual deve encontrar
`Gravatas` mesmo com entrada acentuada; marcar `Gravatas` a partir de
`/product-category/conjuntos/` deve navegar para
`/shop/?petshop_categories=conjuntos,gravatas`; ao desmarcar `Conjuntos`, a URL
deve permanecer em `/shop/?petshop_categories=gravatas`. Em ambos os casos, os
cards precisam pertencer a pelo menos uma categoria selecionada.

Revisão crítica: os dois P1 foram corrigidos. A seleção não navega mais a cada
checkbox e exige o botão visível `Aplicar filtros`; parâmetros recebidos numa
URL de taxonomia são redirecionados para a URL canônica equivalente da loja.
Revisão final aprovada sem P0/P1 remanescente nesse recorte.
