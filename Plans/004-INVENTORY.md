# Inventário e arquitetura de informação — Plano 004

## Snapshot inicial

Levantamento registrado em 2026-07-31 antes da criação das páginas e menus deste plano:

- tema ativo: `petshop-theme`, filho do Blocksy;
- plugins de storefront ativos: WooCommerce, Blocksy Companion, Stackable, Fluent Forms e `petshop-core`;
- páginas publicadas: Sample Page, Shop, Cart, Checkout e My account;
- menus: nenhum;
- página inicial estática: não configurada;
- catálogo publicado: um produto, `Adesivo Cascão`;
- produto inicial com preço, imagem e categoria, mas sem SKU e texto alternativo;
- carrinho e checkout já baseados nos blocos oficiais do WooCommerce;
- conteúdo importado do Petsy não estava ativo nas páginas publicadas.

O SKU `AUT-ADESIVO-CASCAO-10` e o texto alternativo da imagem foram preenchidos durante a execução. Nenhum produto novo foi inferido ou importado.

## Taxonomia e normalização

| Entrada de catálogo | Destino canônico | Regra |
| --- | --- | --- |
| Promoções | `promocoes` | Categoria raiz |
| Adesivos | `adesivos` | Categoria raiz |
| Babador | `babador` | Categoria raiz |
| Bandanas | `bandanas` | Categoria raiz |
| Colarinhos | `colarinhos` | Categoria raiz |
| Conjuntos | `conjuntos` | Categoria raiz |
| Copa | `copa` | Sazonal, inicialmente oculta |
| Festa Junina | `festa-junina` | Sazonal, inicialmente oculta |
| Gargantilhas | `gargantilhas` | Categoria raiz |
| Gravatas | `gravatas` | Categoria raiz |
| Inverno | `inverno` | Sazonal, inicialmente oculta |
| Laços | `lacos` | Categoria raiz |
| Laços Adesivos | `lacos-adesivos` | Filha obrigatória de Laços |
| Penteados | `penteados` | Categoria raiz |

Uma planilha de importação não está versionada neste repositório. A futura carga deve aceitar somente os nomes acima; linhas vazias, desconhecidas ou ambíguas devem ser rejeitadas para correção, sem categoria inferida.

## Rotas e conteúdo administrável

| Rota | Conteúdo | Regra de seleção | Onde o cliente edita |
| --- | --- | --- | --- |
| Início | Hero, categorias, kits, populares, novidades, sazonal, seleção profissional, avaliações reais e atendimento | Blocos Gutenberg e shortcodes por slug | **Páginas → Início**; imagens em blocos pela Biblioteca de mídia |
| Loja/categoria | Breadcrumbs, ordenação, filtro de categorias e cards | Consulta WooCommerce; sem estoque oculto | **Produtos → Categorias** para nome, descrição, imagem, ordem e visibilidade |
| Busca | Resultado com imagem, nome, preço, estoque e compra | Busca nativa por produto | **Produtos**, usando os dados editáveis de cada produto |
| Produto | Galeria, descrição comercial, pacote, cuidados e relacionados | Produto editorial + relacionados por categoria | **Produtos → Editar produto**, incluindo galeria e textos alternativos na Biblioteca de mídia |
| Carrinho | Itens, quantidade, cupom e totais | Bloco oficial WooCommerce | **Páginas → Carrinho** e configurações do WooCommerce |
| Checkout | Contato, endereço, entrega, pagamento e resumo | Bloco oficial WooCommerce | **Páginas → Finalização de compra** e configurações do WooCommerce |
| Minha conta | Login e rotas do cliente | WooCommerce | **Páginas → Minha conta** e configurações do WooCommerce |
| Atendimento | Orientação de contato | Página Gutenberg | **Páginas → Atendimento** |
| Políticas da loja | Conteúdo comercial, privacidade e trocas a aprovar | Página Gutenberg | **Páginas → Políticas da loja** |
| Personalize | Aviso da área futura | Página Gutenberg, sem configurador | **Páginas → Personalize** |

Logo e imagens globais são substituídos em **Aparência → Personalizar** pela Biblioteca de mídia. Nenhuma foto editorial depende de um caminho fixo em PHP ou CSS para ser atualizada pelo cliente.

## Navegação

- barra superior: benefício editável, Atendimento, Minha conta, Carrinho, busca e minicarrinho;
- menu principal: Início, Personalize e Comprar, com categorias no submenu;
- mobile: mesma árvore do menu principal no off-canvas Blocksy;
- rodapé: Sobre o Auteliê, Atendimento, Envios e entregas e Políticas da loja;
- categorias sazonais permanecem na taxonomia e são projetadas ou ocultadas por `petshop_visible_in_menu`.

Os perfis sociais e um canal de WhatsApp não foram inferidos. Eles devem ser adicionados aos menus pelo WordPress somente depois que o cliente fornecer e aprovar os URLs oficiais.
