# Plano 005 — Refinamento comercial do storefront

**Status:** Bloqueado — aguarda acervo fotográfico real
**Data:** 2026-07-31  
**Branch de implementação:** `005-refinamento-comercial-do-storefront` — entrega parcial incorporada em `master`
**Dependência:** base funcional entregue pelo Plano 004/004b

## 1. Objetivo

Remover as redundâncias e sinais de acabamento provisório ainda presentes no storefront, aproximando cabeçalho, Home, cards e rodapé de uma loja profissional de acessórios para banho e tosa.

O Plano 005 será executado em sessões independentes e verificáveis. Cada sessão deve produzir uma alteração utilizável, evidência própria e um gate explícito de aprovação antes do avanço. Uma sessão não pode ser marcada como concluída apenas porque o código foi escrito.

## 2. Resultado esperado

- cabeçalho sem busca, carrinho, marca ou navegação duplicados;
- hero institucional/comercial com copy clara, produto visível e campanhas sazonais em posição secundária;
- fotografias reais, consistentes e administráveis;
- cards comerciais que exibem somente dados verdadeiros do WooCommerce;
- Home sem seções vazias, grandes áreas improdutivas ou categorias promocionais misturadas às categorias de produto;
- rodapé completo, administrável e sem crédito provisório do tema quando a licença permitir;
- linguagem visual baseada em branco/neutros, azul-petróleo e laranja apenas para ação/destaque;
- comportamento desktop e mobile validado sem regressão de compra.

## 3. Protocolo obrigatório de cada sessão

Toda sessão seguirá o mesmo ciclo:

1. registrar baseline da área em 1440 × 1000 e 390 × 844;
2. listar conteúdo, origem administrativa e arquivos afetados;
3. implementar somente o escopo daquela sessão;
4. validar sintaxe, runtime e ausência de erros de página;
5. testar edição no WordPress e reprovisionamento sem perda da alteração;
6. capturar evidências desktop/mobile após carregar imagens lazy;
7. executar revisão crítica das alterações;
8. corrigir lacunas e repetir os testes afetados;
9. marcar a sessão como concluída somente após todos os critérios do gate passarem.

Se uma verificação falhar, o agente deve diagnosticar e corrigir problemas seguros antes de repetir o gate. Se uma decisão editorial, comercial ou destrutiva do usuário continuar necessária após a segunda tentativa, deve parar e solicitar a decisão. Falhas solucionáveis sem impacto negativo não interrompem o plano.

## 4. Regras transversais

- Todo texto editorial, comercial ou institucional deve ser editável no WordPress.
- Toda foto de conteúdo deve vir da Biblioteca de mídia, ser substituível e possuir alt editável.
- Menus são administrados em **Aparência → Menus**.
- Produtos, preços, promoções, estoque, avaliações e categorias vêm do WooCommerce.
- Conteúdo global de cabeçalho e rodapé deve usar configurações administrativas próprias ou recursos oficiais do tema; não pode ficar fixo em PHP.
- Migrações devem identificar conteúdo gerenciado por hash/versão e preservar qualquer divergência feita pelo cliente.
- Não fabricar avaliações, vendas, preço anterior, desconto, preço no Pix, CNPJ, endereço, contato ou rede social.
- Elementos sem dados reais devem ser ocultados sem deixar whitespace residual.
- O Plano 005 não pode ser concluído com imagens genéricas de banco nas áreas comerciais finais.

## 5. Direção visual mensurável

- container principal máximo: `1280px`;
- espaçamento padrão entre seções desktop: `72px`;
- bordas arredondadas: `12px` a `16px`;
- sombras discretas e consistentes;
- uma única família tipográfica;
- títulos com peso e escala menores que o hero atual do Plano 004b;
- branco e neutros como superfície dominante;
- azul-petróleo como cor institucional principal;
- laranja restrito a CTA, preço, badge e campanhas pontuais;
- nenhuma grande seção da Home com fundo laranja/marrom integral.

A proporção 70% neutros, 20% azul-petróleo e 10% laranja é uma direção de composição, não uma obrigação de contagem pixel a pixel.

## 6. Sessões de implementação

### Sessão 01 — Cabeçalho comercial sem redundâncias

**Status da sessão:** [x] Concluída

**Escopo**

- manter uma única barra promocional fina;
- logo maior à esquerda, sem repetir o nome da marca abaixo da própria imagem;
- uma única busca, central e visualmente dominante;
- conta, atendimento e minicarrinho à direita;
- remover o link textual duplicado de carrinho e o segundo gatilho de busca;
- exibir contador do carrinho no minicarrinho;
- reduzir altura e whitespace improdutivo;
- substituir a navegação genérica pelo menu:
  - Laços;
  - Bandanas;
  - Adesivos;
  - Gravatas;
  - Kits Econômicos;
  - Coleções;
  - Personalizados.
- `Kits Econômicos` deve apontar para a categoria/landing administrável de conjuntos;
- `Coleções` deve apontar para uma página Gutenberg administrável e pode expor coleções sazonais como submenu;
- `Personalizados` deve ser tratado como página/serviço, não como categoria de produto.

**Origem editável**

| Conteúdo | Origem |
|---|---|
| mensagem e link promocional | configuração global da loja |
| logo | Identidade do site/Biblioteca de mídia |
| itens e rótulos do menu | Aparência → Menus |
| atendimento | página Atendimento + configuração global |
| conta | página Minha conta do WooCommerce |
| busca e carrinho | WooCommerce/Blocksy |

**Gate verificável**

- exatamente um `search`/formulário de produtos no desktop;
- exatamente um minicarrinho e nenhum link textual redundante “Carrinho” no header;
- logo sem título duplicado;
- sete entradas comerciais presentes e com destinos HTTP 200;
- contador do carrinho muda após adicionar produto;
- busca retorna produtos por nome/SKU;
- header sem overflow em 1440, 1024, 768 e 390 px;
- mensagem, logo e menus permanecem alterados após reprovisionamento;
- screenshots antes/depois e teste de teclado anexados à sessão.

### Sessão 02 — Hero institucional e faixa de benefícios

**Status da sessão:** [x] Concluída

**Escopo**

- substituir a campanha sazonal como mensagem principal por um hero institucional;
- usar inicialmente a copy editável:
  - título: `Acessórios que valorizam cada banho e tosa`;
  - apoio: `Bandanas, laços, gravatas e adesivos com acabamento profissional e opções para diferentes volumes.`;
  - CTA 1: `Comprar mais vendidos`;
  - CTA 2: `Conhecer kits econômicos`;
- CTA 1 deve usar uma vitrine baseada em venda real; enquanto não houver histórico, seu rótulo e destino devem ser `Ver destaques da loja`;
- CTA 2 deve apontar para Kits Econômicos;
- posicionar o produto/pet sem corte de cabeça e com acessório claramente visível;
- adicionar abaixo do hero a faixa editável:
  - Pronta entrega;
  - Condições para volume;
  - Envio para todo o Brasil;
- mover Dia dos Pais para banner menor ou coleção sazonal secundária.

**Origem editável**

- hero e benefícios: blocos Gutenberg da Home;
- imagem: Cover/Media da Biblioteca;
- CTAs: links dos blocos Gutenberg;
- campanha sazonal: bloco/banner da Home e categoria WooCommerce.

**Gate verificável**

- hero full-bleed desktop entre 2,4:1 e 3,3:1;
- eyebrow, título, descrição e grupo de CTAs compartilham um único eixo esquerdo;
- título sem palavras isoladas ou quebras artificiais em 1440 e 390 px;
- cabeça do animal e acessório visíveis no recorte desktop/mobile;
- dois CTAs com destinos cadastrados e foco visível;
- três benefícios visíveis sem overflow;
- Dia dos Pais ausente do H1 e presente somente como conteúdo secundário;
- edição de título, imagem, alt e URLs persiste após reprovisionamento.

Esta sessão pode validar o layout com a imagem provisória existente, mas o Plano 005 só poderá terminar depois da substituição por fotografia real na Sessão 03.

### Sessão 03 — Fotografia real e padronização das imagens

**Status da sessão:** [ ] Bloqueada — aguarda acervo fotográfico real autorizado

**Pré-condição não negociável**

Receber ou localizar fotografias reais dos produtos autorizadas pelo cliente. Ausência desse acervo não autoriza banco de imagens, IA ou fotografia inventada como resultado final.

**Escopo**

- inventariar hero, categorias e produtos exibidos na Home;
- substituir imagens Pexels/genéricas e repetições por fotografias reais;
- impedir que Conjuntos, Gargantilhas ou outras categorias compartilhem a mesma foto sem justificativa;
- adotar proporção `1:1` para cards e categorias;
- usar enquadramento e iluminação consistentes;
- preservar produto completo com `object-fit: contain` quando necessário;
- gerar tamanhos WordPress otimizados sem hotlink;
- manter origem/autor quando aplicável e alt específico.

**Gate verificável**

- zero imagem de banco ou placeholder genérico nas áreas comerciais finais;
- zero imagem quebrada;
- cada categoria principal possui imagem distinta e semanticamente correspondente;
- cards medidos em proporção 1:1 nas viewports-alvo;
- alt não vazio, editável e coerente com o produto fotografado;
- auditoria visual lado a lado confirma iluminação/enquadramento consistentes;
- substituição de uma imagem no painel aparece no site e sobrevive ao reprovisionamento.

### Sessão 04 — Cards de produto orientados por dados reais

**Status da sessão:** [ ] Pendente

**Escopo**

- preparar a listagem de loja/categoria com sidebar esquerda no desktop e acima
  da grade no mobile;
- apresentar campo textual para localizar opções de categoria e lista vertical
  com checkbox e contagem real de produtos;
- separar a aprovação do layout da implementação da consulta: controles não
  podem ser declarados funcionais antes do gate específico de filtragem;
- limitar título a duas linhas sem cortar informação essencial;
- uniformizar imagem, espaçamento, preço e CTA;
- exibir preço anterior somente quando existir preço promocional válido;
- exibir preço no Pix somente quando houver regra real/configurada do meio de pagamento;
- exibir avaliação somente quando houver reviews aprovadas;
- exibir `Mais vendido` somente a partir de vendas reais (`total_sales`) e regra documentada;
- garantir CTA claro, estoque e quantidade acessíveis;
- criar estado visual consistente quando campos opcionais estiverem ausentes.

**Gate verificável**

- sidebar e grade formam colunas distintas em desktop, sem grande bloco
  horizontal de chips;
- sidebar aparece antes da toolbar/grade no mobile e não cria overflow;
- campo textual, checkboxes, nomes e contagens têm rótulos acessíveis;
- screenshot do layout é aprovado antes da implementação da filtragem real;
- seleção múltipla usa query string canônica da loja e combina categorias com
  semântica OR, preservando todos os checkboxes selecionados após a navegação;
- busca textual reduz apenas as opções da sidebar, tolera acentos e não altera
  os produtos até que uma categoria seja selecionada;
- nenhum card contém desconto, preço no Pix, avaliação ou badge fabricado;
- produtos sem sale/review não deixam buracos no card;
- produtos com dados reais exibem os campos correspondentes;
- quatro cards alinhados por linha em 1280 px quando houver quatro produtos;
- alturas e CTAs permanecem alinhados com títulos de uma e duas linhas;
- adição ao carrinho funciona por teclado e atualiza minicarrinho;
- screenshots cobrem cards com e sem dados opcionais.

### Sessão 05 — Kits econômicos como seção comercial

**Status da sessão:** [ ] Pendente

**Escopo**

- renomear para `Economize comprando kits`;
- remover fundo marrom/laranja de grande área;
- usar fundo bege ou cinza muito claro, cards brancos e contraste AA;
- quatro produtos por linha quando houver dados suficientes;
- adicionar CTA `Ver todos os kits`;
- selecionar produtos da categoria administrável de kits/conjuntos;
- aplicar os cards da Sessão 04;
- não fabricar descontos para preencher a seção.

**Gate verificável**

- nenhum fundo laranja/marrom integral na seção;
- CTA resolve para a landing/categoria de kits;
- seção tem quatro cards em desktop ou se adapta ao número real sem grande vazio;
- com zero kits publicados, a seção inteira desaparece;
- com dois kits, o layout permanece compacto e sem coluna vazia dominante;
- título, texto, CTA e seleção permanecem editáveis.

### Sessão 06 — Ordem da Home e tratamento de estados vazios

**Status da sessão:** [ ] Pendente

**Escopo**

Adotar esta ordem:

1. barra de benefício;
2. cabeçalho e busca;
3. hero institucional/comercial;
4. benefícios da compra;
5. principais categorias;
6. mais vendidos ou destaques;
7. kits econômicos;
8. coleção sazonal;
9. produtos para groomers;
10. avaliações reais;
11. atendimento via WhatsApp;
12. rodapé.

Regras:

- usar `Mais vendidos` somente quando houver vendas reais suficientes;
- usar `Destaques da loja` como fallback editorial administrável;
- ocultar `Quem compra, conta` enquanto não houver reviews aprovadas;
- remover whitespace de shortcodes/blocos sem resultado;
- retirar `Promoções` da grade equivalente de categorias; promoções aparecem como campanha/banner/badge somente quando reais.

**Gate verificável**

- ordem das seções corresponde à lista aprovada;
- zero heading órfão ou seção vazia;
- zero espaço vertical superior a 120 px causado por bloco sem conteúdo;
- ao remover todos os reviews, avaliações e seu espaçamento desaparecem;
- ao adicionar review aprovado de teste, a seção reaparece; o review é removido ao final;
- sem vendas reais, a Home não usa o título `Mais vendidos`;
- Promoções não aparece na grade de categorias principais.

### Sessão 07 — Rodapé institucional completo

**Status da sessão:** [ ] Pendente

**Escopo**

- remover `WordPress Theme by Petshop` após verificar a permissão/licença e o mecanismo oficial do Blocksy;
- adicionar:
  - logo e descrição curta;
  - atendimento e WhatsApp;
  - horários;
  - categorias;
  - Minha conta e pedidos;
  - trocas, entregas e privacidade;
  - redes sociais;
  - formas de pagamento;
  - CNPJ e endereço quando fornecidos/aplicáveis;
- ocultar individualmente campos institucionais ainda não fornecidos;
- não usar ícone, número, CNPJ ou endereço fictício.

**Origem editável**

| Conteúdo | Origem |
|---|---|
| logo | Identidade do site/Biblioteca de mídia |
| descrição, WhatsApp, horários, CNPJ e endereço | configuração global da loja |
| categorias e políticas | menus de rodapé |
| conta/pedidos | páginas WooCommerce |
| redes sociais | configuração global da loja |
| formas de pagamento | configuração + imagem administrável |

**Gate verificável**

- crédito provisório ausente de forma compatível com a licença;
- nenhum dado institucional inventado;
- links de políticas, conta, pedidos e categorias respondem sem 404;
- WhatsApp usa URL válida quando configurado;
- campos vazios somem sem deixar rótulo ou coluna vazia;
- todas as informações globais podem ser alteradas sem código e persistem após reprovisionamento;
- rodapé sem overflow e com ordem de leitura coerente no mobile.

### Sessão 08 — Consolidação da identidade visual

**Status da sessão:** [ ] Pendente

**Escopo**

- consolidar tokens de container, espaçamento, raio, sombra e tipografia;
- limitar largura útil a 1280 px;
- padronizar `72px` entre seções desktop e escala fluida no mobile;
- reduzir peso/tamanho de headings;
- eliminar variações de borda e sombra sem propósito;
- limitar laranja a ações e destaques;
- usar azul-petróleo em navegação, texto institucional e áreas de confiança.

**Gate verificável**

- Playwright confirma container máximo de 1280 px;
- amostra de cinco seções confirma espaçamento de 72 px no desktop;
- cards usam somente raios entre 12 e 16 px;
- uma família tipográfica computada em headings, body, botões e menu;
- nenhuma seção extensa usa laranja como fundo;
- contraste AA em texto, links, preços, badges e botões;
- ícones devem herdar a cor de primeiro plano do controle e manter contraste AA
  contra seu fundo em todos os estados; texto sobre fotografia deve usar uma
  superfície, faixa ou gradiente de contraste determinístico, nunca depender
  apenas da luminosidade circunstancial da imagem;
- comparação visual confirma predominância de branco/neutros.

### Sessão 09 — Mobile, acessibilidade e integração final

**Status da sessão:** [ ] Pendente

**Escopo**

- reorganizar header mobile com logo, busca, conta/carrinho e menu sem duplicação;
- validar hero, benefícios, categorias, cards, kits e rodapé em 390 px;
- garantir áreas interativas mínimas de 44 × 44 px;
- validar foco, menu, busca, minicarrinho e estados dinâmicos;
- repetir fluxo completo de produto → carrinho → checkout;
- executar auditoria final de persistência editorial e revisão crítica.

**Gate verificável**

- zero overflow horizontal em 1440, 1024, 768 e 390 px;
- zero imagem quebrada e zero erro de página;
- busca, menu, conta, carrinho e CTAs têm nome acessível e foco visível;
- header mobile possui uma busca e um carrinho;
- produto pode ser adicionado, quantidade alterada e checkout aberto;
- home, categoria, produto, carrinho e checkout respondem HTTP 200;
- screenshots finais e comparação com baseline registradas;
- revisão crítica não encontra bloqueador P0/P1 dentro do escopo;
- validação humana com NVDA/VoiceOver executada ou pendência formalmente aceita pelo usuário.

## 7. Inventário de conteúdo e edição por rota

| Rota/área | Conteúdo | Origem administrativa |
|---|---|---|
| global/header | promoção, atendimento | configuração global da loja |
| global/header | logo | Identidade do site/Biblioteca de mídia |
| global/header | navegação | Aparência → Menus |
| Home | hero, benefícios, campanhas e CTAs | Gutenberg |
| Home | categorias, kits e vitrines | WooCommerce + blocos/shortcodes configuráveis |
| categoria | nome, descrição e imagem | categoria WooCommerce/Biblioteca de mídia |
| produto/card | imagem, título, preço, sale, estoque, avaliação | produto WooCommerce |
| global/footer | descrição, contato, horários, CNPJ, endereço e redes | configuração global da loja |
| global/footer | links institucionais/categorias | Aparência → Menus |
| global/footer | meios de pagamento | configuração global + Biblioteca de mídia |

## 8. Dependências e decisões pendentes

- Fotografias reais e autorizadas dos produtos são obrigatórias para concluir a Sessão 03.
- WhatsApp, horários, redes sociais, CNPJ, endereço e formas de pagamento dependem de dados fornecidos pelo cliente; campos ausentes ficam ocultos.
- `Preço no Pix` depende de regra real do gateway/meio de pagamento e não será calculado apenas no front-end.
- `Mais vendidos` e seu badge dependem de histórico real de vendas.
- Avaliações dependem de reviews reais e aprovadas.
- A remoção do crédito do tema depende da licença e da opção oficial disponível.

## 9. Fora de escopo

- produzir sessão fotográfica;
- criar descontos, vendas ou avaliações fictícias;
- configurar gateway de pagamento ou regra financeira de Pix;
- criar dados legais/institucionais;
- refazer checkout além dos ajustes necessários para não regredir;
- implementar personalizador de produtos;
- alterar WordPress Core, WooCommerce, Blocksy pai ou plugins de terceiros.

## 10. Evidências obrigatórias

Cada sessão deve registrar:

- checklist de aceite atualizado neste plano;
- arquivos alterados;
- origem administrativa de todo conteúdo novo;
- comandos e resultado dos testes;
- captura desktop/mobile antes e depois;
- teste de persistência editorial;
- achados e fechamento da revisão crítica;
- limitações reais ou dependências transferidas.

O Plano 005 só poderá mudar para **Concluído** quando as nove sessões estiverem aprovadas e nenhuma área comercial final depender de imagem genérica ou conteúdo editável fixo em código.
