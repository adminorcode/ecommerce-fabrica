# Guia operacional — páginas WooCommerce do Plano 013

## Rotas e conteúdo

- **Loja, Carrinho, Finalizar compra e Minha conta:** edite as páginas vinculadas em **WooCommerce → Configurações → Avançado**. Os IDs foram preservados; as rotas públicas são `/loja/`, `/carrinho/`, `/finalizar-compra/` e `/minha-conta/`.
- **Carrinho e checkout:** mantenha os blocos nativos WooCommerce. Os blocos editoriais “Continuar comprando” e “Voltar ao carrinho” podem ser alterados no Gutenberg e não são sobrescritos pelo reprovisionamento.
- **Categorias:** nome, descrição, imagem e texto alternativo ficam em **Produtos → Categorias** e na Biblioteca de mídia.
- **Políticas:** entrega, trocas, personalização, privacidade e termos são páginas Gutenberg separadas. Privacidade, trocas e termos já estão atribuídos às configurações aplicáveis, mas permanecem em rascunho até aprovação jurídica.

## Catálogo e busca

Os filtros públicos usam query string e continuam operando sem JavaScript: categoria, preço mínimo/máximo, cor, tamanho, estoque e ordenação. Cor e tamanho vêm dos atributos globais WooCommerce; opções sem produtos não são exibidas, exceto quando já selecionadas.

A busca aceita nome e SKU exato. As sugestões usam a Store API do WooCommerce e não criam endpoint próprio.

## Cadastro de produto

Em **Produtos → editar produto → Dados do produto → Geral**, preencha quando aplicável:

- prazo de produção;
- materiais;
- conteúdo da embalagem;
- cuidados;
- medidas;
- página Gutenberg do guia de medidas.

Variações podem ter prazo de produção próprio. Imagem, preço, estoque e SKU continuam sendo cadastrados nos campos nativos de cada variação.

Em **Produtos → Atributos → Cor → Configurar termos**, informe a amostra hexadecimal de cada cor. O nome e a amostra aparecem juntos na página do produto. A troca dessa amostra é administrativa e não exige alteração de código.

Use `scripts/seed-013-catalog-samples.php` somente no ambiente local para criar, sem sobrescrever, as três fixtures de referência. O relatório somente leitura `scripts/audit-013-catalog.php` e o checklist `docs/checklist-catalogo-plano-013.md` orientam o saneamento do restante do catálogo.

## Frete e pagamento

O ambiente local possui a zona **Brasil (desenvolvimento)** com tarifa fixa de teste como fallback. Não reutilize essa tarifa em produção.

Para validação de frete real, o runtime local usa **Virtuaria Correios** com o método `virtuaria-correios-sedex`. Configure no painel em **Virtuaria Correios** e em **WooCommerce → Configurações → Entrega → Áreas de entrega**. A validação local registrada em 2026-08-11 usou origem `01001000`, serviço `03220` e modo fácil sem credenciais versionadas; produção ainda exige origem real, zonas aprovadas, serviços Correios, embalagem, contrato/credenciais quando aplicável e contingência de indisponibilidade.

O Mercado Pago deve ser configurado no painel com credenciais sandbox não versionadas. Valide Pix e cartão nos estados aprovado, recusado e pendente antes de liberar a loja. Enquanto isso, o plano permanece em andamento.

## Pedido, rastreamento e conta

Na edição do pedido WooCommerce, o card **Rastreamento da entrega** aceita transportadora, código e URL. Os dados são persistidos pelo CRUD `WC_Order`, compatível com HPOS, e só aparecem para o cliente quando preenchidos.

O texto global de próximos passos é editável no Personalizador. Compra como visitante permanece habilitada; na confirmação, o comprador pode criar uma conta para o e-mail novo do pedido e recebe o fluxo oficial de definição de senha.

## Validação antes de publicar

1. Execute `npm test` e `npm run validate -- --browser`.
2. Revise `Plans/013-TESTING.md` e confirme que nenhuma credencial foi versionada.
3. Faça os três fluxos Mercado Pago sandbox.
4. Execute o roteiro humano com NVDA ou VoiceOver.
5. Publique as políticas somente após aprovação jurídica e substitua a tarifa local pelo contrato real de frete.
