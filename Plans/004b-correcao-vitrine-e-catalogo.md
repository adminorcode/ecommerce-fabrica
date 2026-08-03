# Plano 004b — Correção da vitrine e catálogo demonstrável

**Status:** Concluído  
**Data:** 2026-07-31  
**Plano pai:** [004-identidade-visual-e-navegabilidade.md](./004-identidade-visual-e-navegabilidade.md)  
**Branch:** `004-identidade-visual-e-navegabilidade`

## 1. Contexto e resultado desejado

A primeira execução do Plano 004 criou a estrutura técnica, mas a apresentação ficou distante da referência comercial e não está adequada para demonstração ao cliente. A home tem um hero com proporção de card, categorias sem fotografia, vitrines vazias ou repetindo um único produto e páginas de categoria sem densidade.

O 004b é uma fase corretiva do próprio Plano 004. A prioridade é estabelecer uma base comercial sólida, assumidamente próxima dos padrões da Moda Bicho, antes de buscar diferenciação criativa adicional.

Fontes:

- catálogo fornecido: `C:/Users/lucas/Downloads/products (12).xlsx`;
- referência estrutural: <https://www.modabicho.com.br/>;
- categoria de referência: <https://www.modabicho.com.br/adesivo-pet>;
- produto de referência: <https://www.modabicho.com.br/adesivos-pet/cartela-adesiva-g-flor-strass-10mm-escolha-a-cor-p>.

**Resultado obrigatório:** a home, a categoria e o produto devem parecer uma loja operável e apresentável, com fotografia, densidade de catálogo e hierarquia comercial, sem depender de conteúdo da Moda Bicho.

## 2. Decisões

- Permanecer em WordPress, WooCommerce, Blocksy e Gutenberg.
- Reutilizar padrões comprovados da referência: banner horizontal, categorias fotográficas, vitrines densas, benefícios comerciais, filtros, conteúdo de categoria e produto rico.
- Não copiar logo, avaliações, preços, fotos, ativos ou código da Moda Bicho. A única exceção autorizada é a copy provisória do hero fornecida diretamente pelo usuário, salva como conteúdo editável para substituição posterior.
- Usar temporariamente fotos gratuitas do Pexels/Unsplash, registrando página de origem, autor e licença em um manifesto e nos metadados do anexo.
- Importar as fotos para a Biblioteca de mídia. Nenhuma imagem editorial pode depender de hotlink.
- Todo texto e imagem provisionado deve continuar editável pelo cliente no WordPress e sobreviver a atualizações.
- Produtos demonstrativos devem vir do XLSX. Não inventar SKU, nome, preço ou estoque.
- O seed é local, explícito, idempotente e executado por WP-CLI; não roda no tráfego público.

## 3. Diagnóstico do catálogo

O XLSX possui 174 produtos. Distribuição relevante:

| Categoria da planilha | Registros | Com estoque positivo | Tratamento |
| --- | ---: | ---: | --- |
| Adesivos | 45 | 36 | importar 2 ou mais |
| Babador | 3 | 3 | importar 2 |
| Bandanas | 23 | 22 | importar 2 ou mais |
| Colarinhos | 5 | 5 | importar 2 |
| Conjuntos | 1 | 1 | importar o registro disponível e classificar também o produto real “Conjunto Babador + Laço em Feltro” como conjunto |
| Copa | 4 | 4 | importar 2 |
| Dia dos Pais | 1 | 1 | criar categoria sazonal e importar o registro |
| Festa Junina | 12 | 12 | importar 2 |
| Gargantilhas | 19 | 16 | importar 2 |
| Gravatas | 8 | 8 | importar 2 |
| Inverno | 23 | 18 | importar 2 |
| Laços | 18 | 18 | importar 2 |
| Penteados | 3 | 3 | importar 2 |
| Promoções | 1 | 1 | importar o registro disponível |
| `25` | 8 | 7 | rejeitar como categoria inválida |

O seed deve priorizar registros com preço, estoque positivo, SKU e descrição válida. `Dia dos Pais` e `Promoções` ficam documentadas como exceções de fonte com apenas um registro, sem fabricar um segundo produto. Para `Conjuntos`, o segundo card usa o SKU real `C1100046`, cujo próprio nome no XLSX é “Conjunto Babador + Laço em Feltro”; ele permanece em `Babador` e recebe também a classificação `Conjuntos`.

## 4. Curadoria provisória de imagens

Fontes iniciais aprovadas para pesquisa e download:

- Pexels — cachorro com bandana: <https://www.pexels.com/photo/dog-wearing-a-bandana-16652372/>;
- Pexels — poodle com bandana colorida: <https://www.pexels.com/photo/adorable-poodle-wearing-a-colorful-dog-bandana-34268266/>;
- Pexels — cachorro com gravata: <https://www.pexels.com/photo/cute-dog-with-elegant-bow-tie-5733401/>;
- Pexels — gravata ajustada após grooming: <https://www.pexels.com/photo/close-up-of-a-dog-with-a-bow-tie-10984836/>;
- Pexels — cachorro com lenço: <https://www.pexels.com/photo/adorable-animal-animal-photography-beagle-347683/>;
- Pexels — cachorro com cachecol: <https://www.pexels.com/photo/dog-wearing-cute-beret-and-checkered-scarf-35356504/>;
- Pexels — banho e tosa profissional: <https://www.pexels.com/photo/small-dog-in-grooming-salon-6816837/>;
- Pexels — finalização no salão: <https://www.pexels.com/photo/cute-dog-getting-hairstyle-in-grooming-salon-6816870/>.

As páginas de origem são obrigatórias no manifesto. O arquivo final deve ser otimizado para WebP/JPEG, ter texto alternativo em português e receber metadados `_petshop_placeholder_source` e `_petshop_placeholder_license`.

## 5. Implementação

### Etapa 1 — Seed de catálogo demonstrativo

1. Versionar um manifesto curado com os produtos selecionados do XLSX e suas categorias canônicas.
2. Adicionar `dia-dos-pais` à taxonomia sazonal.
3. Criar um comando/script WP-CLI idempotente que:
   - localize o produto por SKU;
   - crie ou atualize somente produtos marcados como placeholders do 004b;
   - preserve produtos reais e alterações administrativas sem a marca do seed;
   - configure nome, slug, preço, estoque, descrição, categorias e destaque;
   - importe a imagem para a Biblioteca de mídia, com origem/licença/alt;
   - possa ser executado novamente sem duplicar produtos ou anexos.
4. Garantir ao menos dois produtos demonstrativos por categoria quando o XLSX oferece dois registros elegíveis.

**Aceite:** as categorias elegíveis têm a quantidade prevista, nenhum produto é inventado e todos possuem SKU, preço, estoque, descrição, imagem, alt e fonte.

### Etapa 2 — Hero e primeira dobra

1. Substituir o hero atual por um banner comercial full-bleed, usando a referência fornecida como base direta de layout:
   - ocupar 100% da largura da viewport, de uma borda da tela à outra;
   - desktop entre 2,4:1 e 3,3:1;
   - altura máxima de 520 px em 1440 px;
   - copy à esquerda e fotografia do produto/pet à direita;
   - sem aparência de card, container estreito ou banner quadrado;
   - CTA evidente e contraste AA.
2. Criar variação mobile com recorte controlado, sem herdar altura quadrada do desktop.
3. Salvar imagem, legenda, título, texto de apoio, CTA e observação inferior como blocos Gutenberg editáveis.
4. Tornar a URL do botão editável no Gutenberg e compatível com qualquer página, coleção/categoria ou produto cadastrado.
5. Configurar inicialmente o CTA para a categoria cadastrada `Dia dos Pais`.
6. Usar como conteúdo inicial editável a mensagem da referência: “Coleção Dia dos Pais”, “O detalhe que fideliza seu cliente”, texto de apoio, CTA para a coleção e informação de frete.
7. Manter o alt da fotografia editável no anexo da Biblioteca de mídia.
8. Exibir parte das categorias ainda na primeira dobra de 1440 × 1000.

**Aceite:** o hero atravessa toda a viewport e é percebido como banner comercial, não como card; foto, todos os textos e destino do CTA são alteráveis pelo painel.

### Etapa 3 — Home comercial próxima da referência

1. Trocar a grade de placeholders por categorias com imagens administráveis.
2. Exibir pelo menos quatro cards nas vitrines principais quando houver produtos suficientes.
3. Ordenar a narrativa:
   - hero;
   - categorias principais;
   - faixa comercial de kits/atacado;
   - mais procurados;
   - seleção para groomers;
   - novidades;
   - sazonal ativa;
   - avaliações reais ou estado vazio discreto;
   - atendimento.
4. Não renderizar seções vazias como grandes espaços.
5. Reduzir whitespace improdutivo e alinhar CTAs com o conteúdo.

**Aceite:** nenhuma vitrine repete isoladamente o mesmo produto e a home tem densidade visual comparável à base de referência.

### Etapa 4 — Categoria e produto

1. Enriquecer categoria com descrição editável e fotografia.
2. Manter ordenação nativa e adicionar filtros somente para atributos realmente presentes.
3. Organizar cards com foto, nome, preço, quantidade do pacote e CTA.
4. Produto deve evidenciar:
   - galeria;
   - preço e estoque;
   - conteúdo do pacote;
   - material, aplicação e cuidados;
   - categorias/relacionados;
   - aviso interno de imagem provisória sem dominar a comunicação.
5. Não fabricar avaliações, descontos, variações ou selos.

**Aceite:** categoria possui ao menos dois cards nas categorias elegíveis; produto é compreensível e comercialmente apresentável.

### Etapa 5 — Validação visual e editorial

1. Capturar home, categoria e produto em 1440 × 1000 e 390 × 844 após rolar a página para carregar imagens.
2. Criar prancha lado a lado com a referência.
3. Validar:
   - zero imagem quebrada;
   - zero placeholder genérico do WooCommerce acima da dobra;
   - ausência de overflow;
   - hero dentro da proporção definida;
   - quatro cards nas vitrines principais;
   - duas amostras por categoria quando suportado pelo XLSX;
   - textos, fotos e alt editáveis;
   - carrinho e checkout sem regressão.
4. Executar revisão crítica dedicada e repetir o loop após correções.

**Aceite:** a revisão não classifica mais a loja como esqueleto visual ou catálogo vazio.

## 6. Fora de escopo

- importar os 174 produtos;
- produzir fotografia definitiva do cliente;
- inventar dados ausentes;
- configurar pagamentos, frete ou ERP;
- copiar ativos ou conteúdo da referência;
- construir filtros sem atributos reais;
- finalizar identidade criativa definitiva antes da aprovação da base comercial.

## 7. Rollback

- Produtos e anexos do 004b recebem metadado próprio e podem ser removidos por comando explícito sem atingir conteúdo real.
- O conteúdo Gutenberg anterior da home deve ser salvo como revisão antes da migração.
- O seed nunca remove produtos, páginas, anexos ou categorias sem uma ação de rollback explicitamente solicitada.

## 8. Evidências

- resumo do XLSX em `.local/catalog-04b/catalog-summary.json`;
- manifesto versionado em `scripts/data/004b-products.json`: 26 SKUs únicos, 14 categorias-alvo e nove imagens Pexels rastreáveis;
- seed executado duas vezes sem duplicação: `created=0 preserved=26 images=9`;
- `scripts/validate-004b.php`: aprovado;
- `scripts/validate-storefront.php`: taxonomia, Home, navegação, blocos WooCommerce, catálogo e identidade aprovados;
- `scripts/test-004b-persistence.php`: título/CTA/alt atuais, imagem/CTA legacy e ausência do seed foram preservados ou bloqueados corretamente;
- runtime Docker saudável e sintaxe PHP aprovada;
- home, categoria e produto em 1440 × 1000 e 390 × 844: HTTP 200, zero imagens quebradas, zero overflow e zero erros de página;
- hero desktop medido em 1440 × 492 px (2,93:1), mobile em 390 × 330 px, CTA com 44 px;
- CTA do hero aprovado para a categoria cadastrada `Dia dos Pais`;
- carrinho e checkout oficiais aprovados com o produto demonstrativo;
- capturas finais em `.local/evidence/004b-*-final.png`;
- comparação com a referência em `.local/evidence/004b-reference-comparison.png`.

## 9. Registro de execução

- 2026-07-31: catálogo XLSX inspecionado e manifesto de placeholders criado.
- 2026-07-31: categorias, descrições, produtos e imagens provisionados de modo idempotente.
- 2026-07-31: hero refeito como Cover Gutenberg full-bleed, com conteúdo, alt e destino editáveis.
- 2026-07-31: revisão crítica corrigiu preservação editorial atual/legacy por hash, pré-condição do seed, persistência confirmada do schema, alt público, descrições de categoria e proporção mobile.
- 2026-07-31: ciclo final de lint, seed, contratos, persistência, responsividade e compra aprovado.
