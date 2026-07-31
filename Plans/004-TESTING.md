# Evidências de teste — Plano 004

**Data:** 2026-07-31

## Contratos e qualidade técnica

- Sintaxe PHP: aprovada nos arquivos alterados do tema, plugin e validador.
- Contrato `scripts/validate-storefront.php`: taxonomia, home, navegação, blocos oficiais do WooCommerce, catálogo publicado e identidade aprovados.
- Persistência editorial: alterações temporárias feitas em Atendimento e na home permaneceram intactas após executar novamente a configuração do storefront; os conteúdos originais foram restaurados depois do teste.
- Preservação administrativa: uma migração simulada manteve a home selecionada e um `theme_mod` alterado pelo cliente.
- Concorrência: o lock de migração impediu uma segunda execução enquanto o provisionamento estava marcado como ativo.
- Taxonomia editável: metadados ausentes são preenchidos, mas ordem e visibilidade já escolhidas pelo cliente são preservadas; ocultar uma categoria não sazonal removeu-a da grade e do menu.
- SEO: sitemap e `robots.txt` respondem com HTTP 200; loja com meta description e URL canônica.
- Contraste dos pares funcionais:
  - `#17676a`/branco: 6,59:1;
  - `#9f3e0a`/branco: 6,63:1;
  - foco `#005fcc`/branco: 5,98:1.

## Navegadores, responsividade e acessibilidade

- Chromium, Firefox e WebKit: home, loja, produto e carrinho responderam com HTTP 200.
- Viewports desktop (1440 px) e mobile (390 px): sem overflow horizontal.
- Zoom de 200%: conteúdo refluiu sem overflow horizontal.
- Navegação por teclado: foco visível de 3 px.
- Busca, botão de busca, minicarrinho e gatilho do menu mobile: área mínima de 44 × 44 px.
- Categorias sazonais configuradas como ocultas não apareceram na navegação nem na grade inicial.
- Auditoria semântica automatizada: busca, minicarrinho, botão mobile e navegação principal possuem nome/papel acessível; o botão anuncia o estado expandido, Escape fecha o menu e o foco retorna ao gatilho.
- Produto, carrinho e checkout: ação de compra com nome acessível, quantidade com rótulo e regiões `aria-live`/status presentes (4 no carrinho e 10 no checkout).

Uma sessão manual ouvindo NVDA ou VoiceOver não foi executada neste ambiente automatizado. Ela permanece como validação humana pendente; os testes acima verificam a árvore e os estados consumidos por tecnologias assistivas, mas não substituem a escuta dos anúncios.

## Fluxos WooCommerce

- Busca e abertura de produto: aprovadas.
- Adição ao carrinho e alteração de quantidade: aprovadas.
- Cupom local de teste: aprovado e removido ao fim da validação.
- Produto sem estoque: estado exibido sem botão de compra; estoque original restaurado.
- Carrinho e checkout: blocos oficiais carregados e fluxo crítico acessível.
- Conta: formulário de autenticação carregado.

O WooCommerce emite um aviso de depreciação React `findDOMNode` no bundle oficial de carrinho/checkout em modo de desenvolvimento. O aviso não parte do tema ou do `petshop-core`, não interrompe o fluxo e fica registrado como dependência externa conhecida.

## Desempenho com cache aquecido

| Navegador | Tempo observado |
| --- | ---: |
| Chromium | 846–862 ms |
| Firefox | 1.154–1.455 ms |
| WebKit | 911–1.033 ms |

Os valores são medições locais de navegação, úteis para detectar regressões neste ambiente; não substituem métricas de produção.

Não existe uma medição anterior comparável ao Plano 004. Por isso, não é possível fabricar um “antes/depois”; para este ambiente local foi adotado e atendido o orçamento de até 2 segundos por navegação crítica com cache aquecido. A próxima alteração visual deverá usar estes valores como baseline.

## Capturas

- `.local/evidence/004/home-desktop.png`
- `.local/evidence/004/home-mobile.png`

## Dependência de ambiente conhecida

O `docker compose up --build -d` padrão ainda encontra o CRLF preexistente em `docker/scripts/init-wordpress.sh`, de responsabilidade do Plano 003. Os testes deste plano foram executados em um contêiner temporário conectado aos volumes persistentes, com normalização somente interna, sem alterar o arquivo fora do escopo.

## Correção 004b — vitrine e catálogo

- Catálogo: 26 SKUs únicos distribuídos por 14 categorias-alvo; nove anexos de imagem com fonte, autor, licença e alt.
- Idempotência: duas execuções consecutivas do seed preservaram os 26 produtos e não criaram anexos duplicados.
- Persistência editorial: título, CTA e alt atuais sobreviveram à reprovisão; um hero legacy com imagem e CTA alterados não foi substituído; ausência de `hero-wide` bloqueou a versão sem marcar sucesso. O conteúdo original foi restaurado ao fim de cada teste.
- Hero desktop: 1440 × 492 px, proporção 2,93:1 e largura igual à viewport.
- Hero mobile: 390 × 330 px, sem overflow e CTA com 44 px.
- Home, categoria e produto em desktop/mobile: HTTP 200, nenhuma imagem quebrada, nenhum overflow e nenhum erro de página.
- Categoria: fotografia, alt e descrição provenientes de campos administráveis do WordPress.
- Compra: produto demonstrativo adicionado ao carrinho; carrinho e checkout oficiais carregaram corretamente.
- Runtime final: `healthy`.

Capturas finais:

- `.local/evidence/004b-home-desktop-final.png`
- `.local/evidence/004b-home-mobile-final.png`
- `.local/evidence/004b-category-desktop-final.png`
- `.local/evidence/004b-category-mobile-final.png`
- `.local/evidence/004b-product-desktop-final.png`
- `.local/evidence/004b-product-mobile-final.png`
- `.local/evidence/004b-reference-comparison.png`
