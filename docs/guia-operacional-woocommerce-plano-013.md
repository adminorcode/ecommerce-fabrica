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

Desde o Plano 027, a PDP deve exibir somente a calculadora **Calcular entrega** do `petshop-core`. Ela chama a API oficial de frete do WooCommerce e lista todos os métodos ativos retornados para o CEP, sem filtrar por Virtuaria, Melhor Envio, Correios, PAC ou SEDEX. O CEP calculado na PDP é gravado na sessão/cliente WooCommerce e deve chegar ao carrinho e ao checkout. Widgets próprios de cálculo da Virtuaria e do Melhor Envio ficam ocultos na PDP, carrinho e checkout; configure também os settings oficiais dos plugins para não exibir calculadora quando disponíveis. O plugin oficial **Melhor Envio** (`melhor-envio-cotacao`) foi registrado e ativado no runtime local sem credenciais versionadas; habilite os serviços operacionais no painel Melhor Envio e em **WooCommerce → Configurações → Entrega** somente com token/contrato fora do Git.

Desde o Plano 036, **Melhor Envio** (`melhor-envio-cotacao`) e **Calculadora de Frete e Campos Checkout para o Brasil** (`woo-better-shipping-calculator-for-brazil`) ficam versionados no repositorio e entram no bootstrap local e no pacote HostGator/cPanel. O Brazilian Market foi analisado como alternativa aceita pelo Melhor Envio, mas nao deve ficar ativo junto da Calculadora BR porque os plugins podem conflitar nos campos brasileiros. A Calculadora BR permanece ativa como base de compatibilidade do Melhor Envio, com calculadoras de produto e carrinho desativadas por padrao para preservar a calculadora propria do `petshop-core`. Atualizacoes desses plugins devem ser feitas trocando os arquivos no Git e rodando os gates, nao por clique direto de atualizacao no painel.

O Mercado Pago deve ser configurado no painel com credenciais sandbox não versionadas. Valide Pix e cartão nos estados aprovado, recusado e pendente antes de liberar a loja. Enquanto isso, o plano permanece em andamento.

## Pedido, rastreamento e conta

Na edição do pedido WooCommerce, o card **Rastreamento da entrega** aceita transportadora, código e URL. Os dados são persistidos pelo CRUD `WC_Order`, compatível com HPOS, e só aparecem para o cliente quando preenchidos.

O texto global de próximos passos é editável no Personalizador. A frase de confirmação **Parabéns! Seu pedido foi recebido!** fica em **Aparência → Personalizar → Conteúdo da loja → Frase da confirmação de pedido**. Em branco, a loja volta à frase inicial; o reprovisionamento não sobrescreve uma edição posterior. Compra como visitante permanece habilitada; na confirmação, o comprador pode criar uma conta para o e-mail novo do pedido e recebe o fluxo oficial de definição de senha.

## Validação antes de publicar

1. Execute `npm test` e `npm run validate -- --browser`.
2. Revise `Plans/013-TESTING.md` e confirme que nenhuma credencial foi versionada.
3. Faça os três fluxos Mercado Pago sandbox.
4. Execute o roteiro humano com NVDA ou VoiceOver.
5. Publique as políticas somente após aprovação jurídica e substitua a tarifa local pelo contrato real de frete.
