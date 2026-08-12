# Plano 018 - Paginas comerciais P1

**Status:** Pendente

**Data:** 2026-08-11

**Branch sugerida:** `018-paginas-comerciais-p1`

**Dependencias:** [016-vitrine-produtos-gutenberg.md](./016-vitrine-produtos-gutenberg.md) concluido; [012-personalizador-produtos-e-fila-producao.md](./012-personalizador-produtos-e-fila-producao.md) para produtos com personalizacao real; [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) para publicacao comercial segura.

**Origem:** `Orcode_Requisitos_Website_Loja_Pet_v2.pdf`, secoes 3, 5, 12, 14.1, 14.2, 16.4, 17.2, 18.1, 19 e 20.

## 1. Objetivo

Criar a camada de paginas comerciais P1 prevista no documento da Orcode: Eventos Pet, Animal Republik, Produtos premium, Por Raca, Bandanas e adesivos personalizados, e Capa de chuva personalizada.

Essas paginas devem ser paginas de descoberta e inspiracao que apontam para produtos reais do catalogo, sem criar ilhas fora do fluxo WooCommerce. Todo texto, imagem, composicao, CTA e relacao com produtos deve ser administravel no WordPress.

## 2. Lacunas que este plano cobre

| Requisito | Lacuna atual | Tratamento |
| --- | --- | --- |
| RF-HOME-006/RF-HOME-007 | Home pode destacar frentes, mas as paginas de destino ainda nao existem como sistema comercial | Criar paginas/rotas e links contextuais administraveis. |
| RF-EVT-001 a RF-EVT-005 | Eventos Pet sem pagina de entrada, composicoes e produtos relacionados | Pagina Gutenberg + relacoes com produtos/categorias/atributos. |
| RF-ARP-001 a RF-ARP-004 | Animal Republik depende de materiais aprovados | Pagina bloqueada ate identidade/catalogo aprovado; sem publicar placeholder comercial enganoso. |
| RF-PRM-001 a RF-PRM-004 | Produtos premium precisam de contexto visual sem esconder compra | Landing/categoria com detalhes e produtos reais. |
| RF-BRD-001 a RF-BRD-004 | Por Raca precisa diferenciar inspiracao de recomendacao de tamanho | Indice e paginas de raca com composicoes compraveis e aviso baseado em medidas. |
| RF-BAN-001 a RF-BAN-004 | Bandanas/adesivos personalizados precisam de pagina propria | Landing/categoria alinhada ao Plano 012 e sem alegacoes sem evidencia. |
| RF-RCO-001 a RF-RCO-004 | Capa de chuva personalizada precisa de guia, variacoes, personalizacao e frete | Pagina/categoria ligada a produtos configurados. |
| RC-ADM-005 | Equipe precisa relacionar produtos a racas, eventos, composicoes e marca pelo painel | Usar taxonomias, atributos, categorias, blocos e metadados administraveis. |

## 3. Decisoes de arquitetura

- Conteudo editorial das paginas deve usar Gutenberg nativo ou padroes estruturados salvos em `post_content`.
- Grades e vitrines de produtos devem reutilizar o bloco `petshop/product-grid` sempre que possivel.
- Relacoes comerciais devem usar entidades administraveis: categorias, tags, atributos, produtos, taxonomias ou metadados no painel.
- O codigo pode provisionar paginas e blocos iniciais, mas nao pode sobrescrever edicoes posteriores.
- Paginas sem materiais aprovados devem permanecer como rascunho ou bloqueadas para publicacao.
- Alegacoes como exclusividade, fixacao, seguranca, conforto ou adequacao precisam de evidencia registrada antes de aparecer em pagina publicada.
- Nenhum produto dessas frentes deve usar checkout, carrinho ou pagina de produto paralelos.

## 4. Inventario de conteudo administravel

| Rota | Conteudo e midia | Origem de edicao |
| --- | --- | --- |
| `/eventos-pet/` | titulo, intro, ocasioes, exemplos visuais, CTAs, composicoes e produtos usados | Pagina Gutenberg; imagens na Biblioteca de midia; produtos via WooCommerce/bloco `petshop/product-grid`. |
| `/animal-republik/` | introducao, identidade aprovada, imagens de marca, catalogo relacionado e avisos de disponibilidade | Pagina Gutenberg; Biblioteca de midia; produtos/categorias/tags WooCommerce; publicacao bloqueada ate aprovacao. |
| `/premium/` ou categoria equivalente | narrativa visual, acabamentos, galerias, combinacoes e produtos premium | Pagina Gutenberg + produtos/categorias; imagens e alt editaveis. |
| `/por-raca/` | indice, busca/navegacao alfabetica quando houver volume, cards de racas/perfis | Pagina Gutenberg ou bloco proprio somente se necessario; relacoes administraveis. |
| `/por-raca/{raca}/` | composicoes visuais, produtos utilizados, orientacao por medidas | Pagina/termo editavel; produtos relacionados pelo painel. |
| `/bandanas-e-adesivos-personalizados/` | aplicacoes, opcoes, materiais, dimensoes, instrucoes, CTAs | Pagina Gutenberg; produtos configurados no WooCommerce; dependencias do Plano 012 quando houver personalizacao. |
| `/capas-de-chuva-personalizadas/` | guia de medidas, tamanhos, materiais, cuidados, imagens, personalizacao e frete | Pagina Gutenberg + produtos variaveis/personalizaveis WooCommerce. |
| Home | modulos de entrada para essas frentes | Blocos Gutenberg/`petshop/home-campaigns`; links para paginas publicadas. |
| Menu principal | nomes finais e ordem | Aparencia -> Menus ou editor equivalente; sem menu fixo em PHP. |

## 5. Sessoes de implementacao

### Sessao 01 - Modelo editorial e relacoes administraveis

- [ ] Definir slugs finais, titulos, ordem de menu e estado de publicacao de cada frente.
- [ ] Mapear quais relacoes usam categorias, tags, atributos, produtos, paginas ou taxonomias novas.
- [ ] Criar padroes Gutenberg iniciais com areas claras para hero, texto, composicao visual, produtos e CTA.
- [ ] Reutilizar `petshop/product-grid` para vitrines e selecionar produtos por IDs/categorias quando aplicavel.
- [ ] Criar validacao contra paginas orfas e links para produtos indisponiveis.

**Gate verificavel**

- [ ] A equipe consegue editar texto, imagem, alt, CTA e produtos relacionados pelo painel.
- [ ] Cada pagina publicada tem caminho de navegacao pela Home, menu, categoria ou link contextual.
- [ ] Reprovisionamento nao sobrescreve conteudo editado.

### Sessao 02 - Eventos Pet e composicoes compraveis

- [ ] Criar pagina de entrada para Eventos Pet.
- [ ] Permitir listar ocasioes/eventos quando houver catalogo suficiente.
- [ ] Modelar composicoes visuais com produtos explicitamente identificados.
- [ ] Exibir personalizacao, prazo, quantidade minima e entrega somente a partir de regras cadastradas.

**Gate verificavel**

- [ ] Cada composicao publicada linka produtos ativos e compraveis.
- [ ] Sem produto ativo, a composicao nao promete compra indisponivel.
- [ ] Conteudo de evento tem valor proprio e nao e apenas uma grade repetida.

### Sessao 03 - Animal Republik e materiais aprovados

- [ ] Criar estrutura da pagina dedicada a Animal Republik.
- [ ] Bloquear publicacao ate receber identidade, imagens, catalogo e regras aprovadas.
- [ ] Garantir coexistencia da marca com header, footer, acessibilidade e fluxo comum da loja.
- [ ] Relacionar produtos sem duplicar paginas de catalogo, carrinho ou checkout.

**Gate verificavel**

- [ ] Conteudo nao aprovado permanece em rascunho ou visivelmente bloqueado para publicacao.
- [ ] Produtos Animal Republik usam o fluxo WooCommerce comum.
- [ ] A pagina nao altera marca parceira sem material aprovado.

### Sessao 04 - Premium, bandanas/adesivos e capas de chuva

- [ ] Criar landing/categoria para Produtos premium com imagens de alta qualidade e dados de compra visiveis.
- [ ] Criar pagina para Bandanas e adesivos personalizados com materiais, dimensoes e instrucoes.
- [ ] Criar pagina para Capas de chuva personalizadas com guia de medidas, tamanhos, materiais, cuidados e frete.
- [ ] Integrar personalizacao ao Plano 012 quando o produto exigir campos, upload, previa, aprovacao ou prazo adicional.
- [ ] Bloquear alegacoes de fixacao, exclusividade, seguranca ou conforto sem evidencia aprovada.

**Gate verificavel**

- [ ] Preco, prazo, variacoes e compra permanecem visiveis e consistentes.
- [ ] Frete por CEP continua disponivel antes da compra nas paginas/produtos aplicaveis.
- [ ] Alegacoes sensiveis possuem fonte registrada ou nao sao publicadas.

### Sessao 05 - Por Raca e inspiracao sem promessa indevida

- [ ] Criar indice Por Raca com busca/navegacao alfabetica somente se o volume justificar.
- [ ] Criar paginas ou termos para racas/perfis com composicoes de produtos existentes.
- [ ] Diferenciar inspiracao visual de recomendacao de tamanho.
- [ ] Linkar guia de medidas e produto como fonte da decisao de tamanho.
- [ ] Planejar evolucao RF-BRD-005 apenas se a operacao precisar publicar novas racas sem suporte tecnico.

**Gate verificavel**

- [ ] Pagina de raca nao promete adequacao sem medidas aprovadas.
- [ ] Cada composicao lista produtos utilizados e leva a PDPs reais.
- [ ] Conteudo proprio evita paginas finas compostas somente por grade.

### Sessao 06 - Home, navegacao, SEO e regressao

- [ ] Adicionar modulos de entrada na Home somente para frentes com pagina publicavel.
- [ ] Atualizar menu principal conforme nomes aprovados.
- [ ] Validar breadcrumbs, canonical, titulos, descriptions e sitemap.
- [ ] Testar mobile, teclado, leitor de tela basico, links quebrados, console e layout.
- [ ] Atualizar `docs/guia-edicao-home.md` e guia administrativo das paginas comerciais.

**Gate verificavel**

- [ ] Nenhuma pagina comercial publicada fica orfa.
- [ ] Paginas Eventos e Por Raca possuem conteudo proprio util para SEO.
- [ ] Home nao vira uma sequencia excessiva de banners e preserva foco em compra.

## 6. Fora de escopo

- Implementar pagamentos, frete real, politicas e backup de publicacao, cobertos pelo Plano 017.
- Implementar editor visual, uploads, arquivos privados e fila de producao, cobertos pelo Plano 012.
- Criar area profissional/laceiros, coberta pelo Plano 019.
- Publicar materiais Animal Republik sem aprovacao.
- Criar recomendacao automatica de tamanho por raca sem regras e medidas aprovadas.

## 7. Criterios de aceite globais

- [ ] Eventos Pet, Animal Republik, Premium, Por Raca, Bandanas/Adesivos e Capas de Chuva possuem plano de publicacao claro: publicado com conteudo aprovado ou bloqueado como rascunho.
- [ ] Cada pagina publicada possui titulo, conteudo, CTA, caminho de navegacao, metadados e links para produtos ativos.
- [ ] Textos, imagens, alt, CTAs, composicoes e produtos relacionados sao administraveis no WordPress.
- [ ] Nenhuma alegacao sensivel e publicada sem evidencia ou aprovacao comercial registrada.
- [ ] Produtos dessas frentes usam o catalogo, PDP, carrinho e checkout comuns.
- [ ] Mobile, teclado, foco, contraste e ausencia de overflow foram verificados.
- [ ] Reprovisionamento nao sobrescreve edicoes editoriais.

## 8. Criterio de conclusao

O Plano 018 so podera ser concluido quando as paginas comerciais P1 estiverem publicadas com materiais aprovados ou formalmente bloqueadas, e quando cada frente publicada apontar para produtos reais do catalogo, sem conteudo hardcoded, paginas orfas, promessas nao validadas ou fluxo de compra paralelo.
