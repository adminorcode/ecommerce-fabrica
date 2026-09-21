# Guia — cadastro de produtos personalizáveis

Público: quem cadastra produtos na loja. Nenhuma etapa exige alteração de código.

## 1. Habilitar a personalização em um produto

1. Acesse **Produtos → Todos os produtos** e edite o produto (bandana, laço, adesivo).
2. Em **Dados do produto**, abra a aba **Personalização**.
3. Marque **Habilitar personalização**.
4. Preencha os campos abaixo e clique em **Atualizar**.

| Campo | O que faz | Recomendação |
| --- | --- | --- |
| Instrução para o cliente | Texto exibido acima do editor | Frase curta, por exemplo “Escreva o nome do pet com até 12 letras”. |
| Mockup base | Foto usada como fundo do editor | Imagem quadrada do produto real, fundo claro. Se ficar vazio, a imagem principal do produto é usada. |
| Máscara de recorte | PNG com transparência que define a área irregular | Opcional. Onde a máscara é transparente, a arte é cortada no arquivo final. |
| Largura / Altura imprimível (mm) | Tamanho físico da área de impressão | Meça a área realmente impressa, não o produto inteiro. |
| DPI alvo | Resolução do arquivo de produção | 150 para tecido, 300 para adesivo. Aceita 72 a 600. |
| Permitir texto | Libera caixas de texto | Ligado por padrão. |
| Permitir imagem do cliente | Libera o envio de uma imagem | Use em adesivos; evite em produtos com área muito pequena. |
| Máximo de caixas de texto | Limite por arte | 1 a 5. |
| Fontes permitidas | Lista separada por vírgula | Somente fontes que a gráfica reproduz. |
| Cores permitidas | Lista de cores hexadecimais | Ex.: `#111111, #ffffff, #17676a`. |

Abaixo do DPI o painel mostra o tamanho exato do arquivo final, por exemplo
`Arquivo de produção: 1654 × 1654 px (2,74 MP)`. Essa é a dimensão que o sistema exige do PNG.

### Valores de referência

| Produto | Área (mm) | DPI | Arquivo final |
| --- | --- | --- | --- |
| Bandana | 280 × 280 | 150 | 1654 × 1654 px |
| Laço / lacinho | 80 × 50 | 150 | 472 × 295 px |
| Adesivo | 100 × 100 | 300 | 1181 × 1181 px |

Fórmula: `pixels = milímetros ÷ 25,4 × DPI`.

## 2. Trocar mockup ou máscara depois

Basta abrir a aba **Personalização**, clicar em **Selecionar imagem** e escolher outro arquivo na
Biblioteca de mídia. O texto alternativo é editado na própria Biblioteca de mídia. Pedidos já
fechados **não** mudam: cada pedido guarda um snapshot da configuração usada na hora da compra.

## 3. Produto sem personalização

Se a caixa **Habilitar personalização** estiver desmarcada, ou se largura/altura/DPI estiverem
inválidos, o produto continua idêntico ao fluxo convencional: nenhum botão extra aparece na
página, nenhum script do editor é carregado.

## 4. Página `/personalize/`

- Editada em **Páginas → Personalize**, com blocos Gutenberg nativos.
- O título, a introdução, imagens e CTAs são blocos comuns — edite como qualquer página.
- A vitrine é o bloco **Produtos personalizáveis** (`petshop/personalizable-products`), que lista
  apenas produtos publicados, compráveis e com personalização habilitada. No painel lateral do
  bloco você ajusta **Quantidade de produtos** e **Colunas**.
- A migração automática substitui somente o texto provisório “Personalização em preparação”.
  Se a página já foi editada, o conteúdo do cliente é preservado e nada é sobrescrito.

## 5. O que o cliente vê

1. Na página do produto aparece a instrução e o botão **Personalizar produto**.
2. O editor abre em uma janela: escrever texto, escolher fonte e cor, enviar uma imagem
   (quando permitido), mover, girar e redimensionar, desfazer/refazer e recomeçar.
3. Ao confirmar, o sistema gera a prévia e o PNG de produção e libera o botão de compra.
4. A prévia acompanha o carrinho, o checkout, o e-mail e **Minha conta → Pedidos**.
5. Artes diferentes do mesmo produto nunca se juntam na mesma linha do carrinho.

## 6. Limites de arquivo

- Formatos aceitos do cliente: JPEG, PNG e WebP. SVG é recusado.
- Tamanho e megapixels máximos ficam em **WooCommerce → Configurações → Produtos → Personalizações**.
- Imagens com resolução abaixo do recomendado geram aviso antes da confirmação, com a
  dimensão ideal em pixels.

## 7. Checklist antes de publicar um produto

- [ ] Mockup mostra o produto real e o local da impressão.
- [ ] Largura, altura e DPI conferidos com a produção.
- [ ] Fontes e cores existem no processo de impressão.
- [ ] Instrução comercial revisada.
- [ ] Teste real: personalizar, adicionar ao carrinho e conferir a prévia no checkout.
