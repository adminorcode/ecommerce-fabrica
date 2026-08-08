# Plano 012 — Personalizador de produtos e fila de produção

**Status:** Pendente  
**Data:** 2026-08-03  
**Branch sugerida:** `012-personalizador-produtos-e-fila-producao`  
**Dependências:** [009-design-system-acessibilidade-e-checkout.md](./009-design-system-acessibilidade-e-checkout.md) e [007-refatoracao-petshop-core.md](./007-refatoracao-petshop-core.md) concluídos; implementar após o [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md)
**Relacionamento:** entrega a área `Personalize` reservada nos Planos 004 e 005; não altera o catálogo convencional nem exige plugin de personalização de terceiros.

## 1. Objetivo

Permitir que compradores personalizem produtos WooCommerce — inicialmente bandanas, laços/lacinhos e adesivos — com texto e imagem em uma prévia visual, concluam a compra pelo Carrinho e Checkout Blocks e tenham a personalização preservada no item do pedido.

Entregar ao cliente da loja uma área operacional em **WooCommerce → Personalizações**, vinculada aos pedidos, com prévia, arquivos privados, estado de produção, filtros e downloads protegidos.

O recurso será um módulo próprio do `petshop-core`, sem SaaS obrigatório e sem dependência funcional de plugins de terceiros. O editor visual usará Fabric.js empacotado localmente, com versão fixada e licença registrada.

## 2. Resultado esperado

- página pública `/personalize/` com conteúdo editorial em Gutenberg e vitrine dinâmica de produtos habilitados;
- configuração da personalização dentro de cada produto WooCommerce;
- editor responsivo com mockup, máscara, área imprimível, texto e upload de uma imagem;
- prévia no produto, carrinho, Checkout Block, confirmação, e-mail e Minha conta;
- snapshot imutável da arte e da configuração no fechamento do pedido;
- PNG de produção nas dimensões configuradas, além do original e do JSON editável;
- arquivos fora da exposição pública direta e entregues somente por endpoint autorizado;
- tela **WooCommerce → Personalizações** como fila de produção;
- painel da personalização dentro do pedido HPOS;
- capacidades próprias para equipe de produção;
- retenção e limpeza de rascunhos/arquivos documentadas e configuráveis;
- testes automatizados de segurança, Store API, pedidos, persistência e navegador.

## 3. Decisões de arquitetura e produto

### 3.1 Ownership no projeto

As regras de negócio permanecerão em `wp-content/plugins/petshop-core`, sob o namespace `Petshop\Core\Personalization\`. Apresentação compartilhada e tokens visuais pertencem ao child theme; o estado, arquivos, pedidos, permissões e integrações WooCommerce pertencem ao plugin.

Não será instalado ou modificado plugin de terceiro. Fabric.js será uma dependência JavaScript do módulo, empacotada no build e sem carregamento por CDN.

### 3.2 Licenciamento open source

O `composer.json` atual do `petshop-core` declara licença `proprietary`. Portanto, antes de chamar a solução de open source, a implementação deverá receber aprovação explícita do proprietário para:

- licenciar o `petshop-core` como `GPL-2.0-or-later` ou outra licença GPL compatível aprovada;
- adicionar `License`/`License URI` ao cabeçalho do plugin;
- versionar o arquivo `LICENSE`;
- atualizar `composer.json` e documentação de distribuição;
- registrar Fabric.js e demais dependências no arquivo de avisos de terceiros.

Sem essa aprovação, o código poderá ser próprio e auditável, mas não deverá ser descrito como open source. Esse gate não pode ser contornado apenas porque Fabric.js usa licença MIT.

### 3.3 Escopo funcional do MVP

- uma superfície de personalização por produto;
- produtos simples, que correspondem ao catálogo atual; produtos variáveis ficam preparados no modelo de dados, mas não são requisito de lançamento;
- mockup base, máscara e dimensões configurados por produto;
- uma ou mais caixas de texto, respeitando limite administrativo;
- no máximo uma imagem enviada pelo comprador no MVP;
- posicionar, redimensionar, girar, alinhar e remover objetos;
- fontes e cores permitidas configuradas pelo lojista;
- preview raster e PNG de produção;
- preço final vindo do próprio produto WooCommerce, sem cálculo por objeto;
- revisão interna pela equipe, sem etapa obrigatória de aprovação do comprador.

### 3.4 Compatibilidade obrigatória

- WordPress 7.0+;
- PHP 8.3+;
- WooCommerce 10.9+;
- HPOS;
- Cart Block, Checkout Block e Store API;
- produtos simples no lançamento;
- desktop, tablet e celular sem editor separado por dispositivo;
- Mercado Pago e Stripe não podem perder os metadados da personalização durante a criação do pedido.

## 4. Fora de escopo

- editor 3D ou deformação realista acompanhando dobras do tecido;
- múltiplas superfícies como frente, verso e manga;
- produtos compostos, bundles ou matrizes de nomes/números;
- preço dinâmico por texto, imagem, cor ou área ocupada;
- geração por IA, biblioteca pública de cliparts ou marketplace de artes;
- upload SVG pelo comprador;
- exportação PDF, EPS ou arquivo vetorial de corte;
- integração automática com gráfica, ERP ou print-on-demand;
- colaboração em tempo real ou salvamento de projetos na conta;
- fluxo formal de prova, solicitação de alteração e aprovação pelo comprador;
- personalização de produtos externos, agrupados ou por assinatura;
- alteração de WordPress Core, WooCommerce, Blocksy ou gateways.

Esses itens exigirão planos próprios após validar operação, conversão, qualidade de impressão e volume real de pedidos do MVP.

## 5. Fluxos de usuário

### 5.1 Comprador

1. Acessa `/personalize/` e escolhe um produto habilitado.
2. Na página do produto, aciona **Personalizar produto**.
3. Digita texto e, quando permitido, envia uma imagem.
4. Ajusta os elementos dentro da área imprimível e recebe aviso de baixa resolução quando aplicável.
5. Confirma a arte; o sistema cria prévia, JSON e arquivo de produção associados a um rascunho.
6. Adiciona o item ao carrinho; itens com artes diferentes nunca são mesclados.
7. Vê miniatura e resumo no Cart Block e Checkout Block.
8. Finaliza o pedido; o rascunho vira snapshot imutável do item do pedido.
9. Consulta prévia e estado em **Minha conta → Pedidos**, sem acesso aos arquivos técnicos de produção.

Se o upload ou a renderização falhar, o produto não poderá ser adicionado silenciosamente sem a personalização exigida.

### 5.2 Equipe da loja

1. Acessa **WooCommerce → Personalizações**.
2. Filtra por estado, pedido, produto, cliente ou período.
3. Abre uma personalização e compara preview, texto, original e PNG final.
4. Baixa arquivo individual ou pacote do pedido.
5. Move o trabalho por **Para revisar → Aprovado → Em produção → Concluído**.
6. Abre o pedido WooCommerce correspondente sem pesquisar novamente.

Somente pedidos pagos/em processamento entram automaticamente na fila ativa. Pedidos pendentes permanecem identificados como **Aguardando pagamento**.

## 6. Conteúdo administrável por rota

### 6.1 Rota `/personalize/`

| Item | Onde o cliente edita | Persistência/regra |
| --- | --- | --- |
| Título, introdução, instruções comerciais e CTAs | **Páginas → Personalize**, blocos Gutenberg nativos | Salvos em `post_content`; reprovisionamento não sobrescreve conteúdo editado. |
| Fotos ou artes editoriais | Gutenberg + Biblioteca de mídia | Imagem substituível, alt e link editáveis. |
| Vitrine de produtos personalizáveis | Bloco dinâmico `petshop/personalizable-products` | Consulta apenas produtos publicados e habilitados; sem copy comercial fixa no PHP. |
| Ordem/seleção da vitrine | Inspector do bloco, usando IDs/categorias WooCommerce | Configuração salva no bloco; fallback por data somente quando nenhuma seleção existir. |
| Nome, preço, foto e disponibilidade dos produtos | **Produtos → Todos os produtos** | Dados canônicos do WooCommerce. |

A migração substituirá a mensagem inicial “Personalização em preparação” apenas quando o conteúdo ainda corresponder exatamente ao placeholder gerenciado. Qualquer edição do cliente será preservada.

### 6.2 Rota `/produto/{slug}/`

| Item | Onde o cliente edita | Persistência/regra |
| --- | --- | --- |
| Nome, descrição, preço e imagem comercial | Produto WooCommerce | Fluxo nativo do catálogo. |
| Habilitar personalização | Produto → Dados do produto → **Personalização** | Meta do produto. |
| Instrução comercial acima do editor | Mesma aba | Texto administrável e sanitizado; sem constante PHP. |
| Mockup | Mesma aba → Biblioteca de mídia | Attachment substituível e alt editável. |
| Máscara/recorte | Mesma aba → Biblioteca de mídia | PNG/SVG administrativo validado; não é upload público do comprador. |
| Área e tamanho físico de impressão | Mesma aba | Largura/altura em milímetros e DPI alvo. |
| Ferramentas, fontes, cores e limites | Mesma aba | Configuração validada do produto. |
| Texto do botão, diálogos e mensagens funcionais | Traduções do `petshop-core` | Funcional, traduzível e extensível; não contém campanha comercial. |

### 6.3 Carrinho, checkout, confirmação, e-mails e Minha conta

| Item | Origem | Regra |
| --- | --- | --- |
| Miniatura | Snapshot da personalização | Imutável após o pedido. |
| Resumo de texto/opções | JSON sanitizado + metadados do item | Nunca reconstruído a partir do produto atual. |
| Estado da produção | Registro operacional | Comprador vê rótulo funcional; equipe vê histórico completo. |
| Labels funcionais | Traduções | Sem texto comercial hardcoded. |

### 6.4 WP Admin

| Superfície | Conteúdo | Origem |
| --- | --- | --- |
| **WooCommerce → Personalizações** | Fila, filtros, miniaturas, estados e ações | Dados operacionais; labels traduzíveis. |
| Pedido HPOS | Card “Personalizações” por item | Relação imutável com `order_id` e `order_item_id`. |
| Configurações globais | Retenção, limites máximos e saúde do storage | Settings API; valores sanitizados. |
| Produto | Mockup, máscara, instruções e regras | Meta do produto e Biblioteca de mídia. |

Nenhuma foto, mockup, máscara, instrução comercial ou CTA dependerá de alteração em PHP, CSS ou JavaScript.

## 7. Arquitetura prevista

### 7.1 Módulos PHP

Estrutura indicativa após a base PSR-4 do Plano 007:

```text
wp-content/plugins/petshop-core/
├── src/Personalization/
│   ├── Application/
│   │   ├── CreateDraft.php
│   │   ├── AttachToCart.php
│   │   ├── SnapshotOrderItem.php
│   │   └── TransitionStatus.php
│   ├── Domain/
│   │   ├── Personalization.php
│   │   ├── PersonalizationStatus.php
│   │   └── ProductionSpecification.php
│   ├── Infrastructure/
│   │   ├── PersonalizationRepository.php
│   │   ├── PrivateStorage.php
│   │   ├── SchemaMigrator.php
│   │   └── CleanupScheduler.php
│   ├── WooCommerce/
│   │   ├── ProductConfiguration.php
│   │   ├── CartIntegration.php
│   │   ├── StoreApiIntegration.php
│   │   ├── OrderIntegration.php
│   │   └── AccountIntegration.php
│   ├── Admin/
│   │   ├── PersonalizationsPage.php
│   │   ├── PersonalizationsListTable.php
│   │   ├── OrderPanel.php
│   │   └── Settings.php
│   └── Http/
│       ├── UploadController.php
│       └── DownloadController.php
├── assets/personalizer/
├── blocks/personalizable-products/
└── third-party-notices.txt
```

Os nomes finais podem seguir a convenção entregue pelo Plano 007, preservando classes pequenas, `declare(strict_types=1)` e sem funções globais novas.

### 7.2 JavaScript

- Fabric.js com versão exata no lockfile e bundle local;
- módulo do editor separado do bloco de vitrine;
- estado serializável e versionado, sem depender do DOM como fonte canônica;
- carregamento apenas em produtos habilitados;
- canvas visual desacoplado da resolução do arquivo final;
- exportação em pixels calculados por `mm / 25,4 × DPI`;
- limite de megapixels e memória antes de renderizar;
- suporte a toque, teclado, zoom, desfazer/refazer e `prefers-reduced-motion`;
- nenhuma chave, credencial ou arquivo do usuário em `localStorage`.

### 7.3 Integração WooCommerce

- `FeaturesUtil::declare_compatibility()` para HPOS e Cart/Checkout Blocks somente após testes reais;
- Store API estendida com namespace próprio para ID, miniatura e resumo do item;
- dados do carrinho ligados por UUID não sequencial e hash de integridade;
- metadados mínimos no item do pedido para que o pedido continue compreensível mesmo sem a tela operacional;
- uso exclusivo de CRUD do WooCommerce para pedidos; sem consultar `wp_posts` para HPOS;
- ações de estado idempotentes para webhooks e mudanças repetidas do pedido.

## 8. Modelo de dados

Serão usadas tabelas próprias porque existem rascunhos anteriores ao pedido, arquivos múltiplos, filtros operacionais e retenção independente. `post_meta` não será usado como fila de produção.

### 8.1 `{$wpdb->prefix}petshop_personalizations`

Campos mínimos:

- `id` interno;
- `public_id` UUID único;
- `user_id` nullable;
- hash da sessão/carrinho, nunca o cookie bruto;
- `product_id` e `variation_id` nullable;
- `order_id` e `order_item_id` nullable;
- estado e versão do estado;
- versão do schema do design;
- JSON sanitizado do canvas;
- snapshot da configuração do produto;
- resumo textual seguro;
- hash SHA-256 do snapshot;
- datas de criação, atualização, expiração e conclusão.

### 8.2 `{$wpdb->prefix}petshop_personalization_files`

- `id` e `personalization_id`;
- tipo: `original`, `preview` ou `production`;
- caminho relativo opaco, nunca caminho absoluto salvo no banco;
- MIME detectado, extensão normalizada e tamanho;
- largura, altura, DPI alvo e hash SHA-256;
- data de criação e eventual exclusão.

### 8.3 Metadados do pedido

Cada item personalizado guardará:

- ID público da personalização;
- resumo humano;
- hash do snapshot;
- versão do schema;
- referência interna aos arquivos, sem URL pública.

Alterações posteriores no produto, mockup, máscara, fonte ou preço não modificarão pedidos antigos.

## 9. Armazenamento, segurança e privacidade

### 9.1 Storage privado

- no ambiente Docker, adicionar volume nomeado fora do document root do WordPress;
- caminho configurado por constante/filtro documentado, sem segredo no repositório;
- CLI, runtime, backup e test runner devem montar o mesmo storage quando necessário;
- a feature não poderá ser habilitada se o diretório estiver dentro de uma rota publicamente acessível ou não for gravável;
- downloads serão transmitidos por controlador PHP após autorização, sem revelar o caminho real;
- URLs assinadas públicas e permanentes não fazem parte do MVP.

### 9.2 Uploads

- aceitar do comprador apenas JPEG, PNG e WebP no MVP;
- validar assinatura real, MIME, extensão, dimensões, bytes e megapixels;
- rejeitar SVG, executáveis, arquivos poliglotas e imagens acima dos limites;
- remover EXIF e demais metadados desnecessários;
- gerar nomes opacos no servidor, sem reutilizar nome enviado;
- aplicar nonce, produto habilitado, vínculo com sessão e limitação de requisições;
- nunca confiar em dimensões, MIME, caminhos ou JSON enviados pelo navegador;
- limitar profundidade, quantidade de objetos e tamanho do JSON do canvas;
- impedir path traversal em upload, download, ZIP, limpeza e restore.

### 9.3 Autorização

- capacidade própria `manage_petshop_personalizations`;
- `administrator` e `shop_manager` recebem a capacidade por migração idempotente;
- mudança de estado, download e ações em lote exigem capability e nonce;
- comprador autenticado acessa somente a prévia dos próprios pedidos;
- convidado acessa somente a confirmação imediata autorizada pelo `order_key`, sem endpoint de arquivo reutilizável;
- arquivos `production` e originais nunca são oferecidos ao comprador por padrão.

### 9.4 Retenção e direitos de privacidade

- rascunhos abandonados expiram automaticamente em prazo curto configurado;
- arquivos de pedidos cancelados/refundados seguem política explícita, não exclusão imediata silenciosa;
- retenção de originais e arquivos finais será configurável e documentada;
- cron de limpeza idempotente, com comando WP-CLI de simulação e execução manual;
- integrar exportador/removedor de dados pessoais do WordPress quando o arquivo ou texto identificar o comprador;
- desativar o plugin não apaga nada;
- desinstalação preserva dados por padrão e só remove registros/arquivos mediante opção explícita confirmada e documentada.

## 10. Estados e transições

```text
draft → cart → awaiting_payment → review → approved → in_production → completed
                  │                 │
                  └──────────────→ cancelled
```

- `draft`: arte ainda não ligada ao carrinho;
- `cart`: item presente no carrinho;
- `awaiting_payment`: pedido criado, sem confirmação de pagamento;
- `review`: pedido pago/em processamento e disponível para conferência;
- `approved`: arquivo aprovado internamente;
- `in_production`: produção iniciada;
- `completed`: produção finalizada;
- `cancelled`: pedido cancelado, falho ou removido segundo regra documentada.

Transições automáticas devem reagir a eventos do WooCommerce de forma idempotente. Transições manuais ficam registradas com usuário, data e estado anterior. Não será possível pular validações apenas alterando parâmetros da requisição.

## 11. Sessões de implementação

### Sessão 00 — Pré-requisitos e decisões irreversíveis

**Status:** [ ] Pendente

- confirmar que a suíte PHPUnit e os gates Playwright existentes continuam verdes;
- consumir a arquitetura PSR-4/ciclo de vida do Plano 007 sem duplicá-la;
- obter decisão formal sobre licença GPL do `petshop-core`;
- confirmar medidas físicas, DPI e limites de produção com o cliente;
- documentar política de retenção e responsáveis pelos arquivos;
- registrar a decisão de não usar plugin/SaaS de terceiro.

**Gate verificável**

- [ ] licença e avisos de terceiros documentados;
- [ ] especificação de produção possui exemplos reais para bandana, laço e adesivo;
- [ ] storage privado escolhido para desenvolvimento, teste e produção;
- [ ] nenhuma credencial ou dado pessoal foi adicionado ao repositório.

### Sessão 01 — Domínio, schema, capacidades e storage

**Status:** [ ] Pendente

- criar módulo PSR-4 e bootstrap leve;
- implementar estados e validação de transições;
- criar migração versionada das tabelas via `dbDelta()`;
- criar capacidade e atribuição idempotente a papéis autorizados;
- implementar abstração de storage e health check;
- adicionar volume privado ao Compose e documentação de backup/restore;
- criar WP-CLI para diagnóstico, limpeza em modo dry-run e verificação de integridade.

**Gate verificável**

- [ ] ativação e atualização de schema são idempotentes;
- [ ] storage público ou fora do diretório permitido é recusado;
- [ ] desativação preserva dados;
- [ ] usuário sem capacidade recebe 403 em ações administrativas;
- [ ] backup/restore preserva banco e arquivos com hashes iguais.

### Sessão 02 — Configuração do produto e página Personalize

**Status:** [ ] Pendente

- adicionar aba **Personalização** aos dados do produto;
- cadastrar habilitação, instrução, mockup, máscara, área física, DPI, fontes, cores e limites;
- usar seletores da Biblioteca de mídia e alt administrável;
- criar bloco dinâmico `petshop/personalizable-products`;
- migrar apenas o placeholder gerenciado da página `/personalize/`;
- documentar edição pelo cliente.

**Gate verificável**

- [ ] produto não habilitado permanece idêntico ao fluxo convencional;
- [ ] configuração salva persiste após recarregar e reprovisionar;
- [ ] trocar mockup/máscara não exige código;
- [ ] página editada manualmente não é sobrescrita;
- [ ] vitrine mostra apenas produtos publicados, compráveis e habilitados.

### Sessão 03 — Editor visual e geração dos artefatos

**Status:** [ ] Pendente

- integrar Fabric.js localmente;
- renderizar mockup e área/máscara configuradas;
- implementar texto, imagem, transformação, desfazer/refazer e reset;
- validar qualidade da imagem em relação ao tamanho físico/DPI;
- criar rascunho, original, preview, JSON e PNG final;
- congelar fontes e parâmetros necessários no snapshot;
- garantir navegação por teclado, foco visível, mensagens de erro e toque.

**Gate verificável**

- [ ] editor funciona em 390, 768, 1024 e 1440 px;
- [ ] conteúdo não ultrapassa a máscara no arquivo de produção;
- [ ] PNG possui exatamente as dimensões calculadas para o produto;
- [ ] imagem insuficiente gera aviso antes da confirmação;
- [ ] falha de upload/render impede personalização incompleta;
- [ ] nenhuma requisição do editor depende de CDN ou SaaS.

### Sessão 04 — Carrinho, Store API, Checkout Block e pedido HPOS

**Status:** [ ] Pendente

- anexar UUID e resumo ao item do carrinho;
- impedir merge de artes diferentes;
- estender Cart Item Schema da Store API;
- exibir miniatura e resumo no Cart/Checkout Blocks;
- validar personalização obrigatória antes do checkout;
- criar snapshot imutável no item do pedido;
- sincronizar estados de pagamento/cancelamento de modo idempotente;
- declarar compatibilidades somente após os testes.

**Gate verificável**

- [ ] recarregar carrinho e checkout preserva arte e resumo;
- [ ] duas artes do mesmo produto permanecem em linhas distintas;
- [ ] pedido HPOS contém referência e snapshot corretos;
- [ ] pedido pago aparece uma única vez em `review`;
- [ ] cancelamento não deixa item ativo na fila;
- [ ] Mercado Pago e Stripe preservam a personalização até a criação do pedido.

### Sessão 05 — WooCommerce → Personalizações e painel do pedido

**Status:** [ ] Pendente

- criar tela operacional separada sob WooCommerce;
- implementar filtros, paginação, ordenação e estados vazios;
- exibir miniatura, pedido, cliente, produto, quantidade, qualidade e estado;
- criar detalhe com original, preview, PNG e snapshot;
- permitir download protegido individual e pacote por pedido;
- implementar transições manuais e histórico;
- adicionar card equivalente na tela de pedido HPOS.

**Gate verificável**

- [ ] fila não exige abrir pedido por pedido;
- [ ] filtros mantêm paginação e não expõem dados indevidos;
- [ ] download exige capability e nonce;
- [ ] ZIP contém somente arquivos do pedido solicitado;
- [ ] pedido e personalização possuem navegação bidirecional;
- [ ] ações administrativas funcionam com HPOS sem `post_id` presumido.

### Sessão 06 — Minha conta, e-mails e confirmação

**Status:** [ ] Pendente

- mostrar prévia e resumo nas telas do pedido do comprador;
- mostrar estado funcional da personalização;
- incluir miniatura segura nos e-mails sem anexar arquivos pesados;
- tratar pedido de convidado na confirmação imediata;
- impedir acesso cruzado entre compradores.

**Gate verificável**

- [ ] comprador vê somente personalizações dos próprios pedidos;
- [ ] URL copiada para outra conta retorna 403/404;
- [ ] e-mail não contém caminho privado ou arquivo técnico;
- [ ] pedido antigo continua exibindo snapshot após alteração do produto.

### Sessão 07 — Retenção, privacidade e hardening

**Status:** [ ] Pendente

- implementar limpeza de rascunhos expirados;
- aplicar política de pedidos cancelados e concluídos;
- integrar exportador/removedor de privacidade;
- testar MIME real, limites, path traversal, ZIP e enumeração de IDs;
- testar concorrência, repetição de webhook e falhas parciais;
- registrar logs sem conteúdo de imagem, texto pessoal ou caminhos absolutos.

**Gate verificável**

- [ ] acesso HTTP direto aos arquivos retorna 403/404;
- [ ] arquivos inválidos e oversized são recusados antes de persistir;
- [ ] cron repetido não remove arquivo válido nem falha por duplicidade;
- [ ] dry-run lista exatamente os candidatos sem excluir;
- [ ] logs não expõem dados pessoais ou conteúdo da arte.

### Sessão 08 — Testes, documentação e aceite operacional

**Status:** [ ] Pendente

- adicionar testes PHPUnit/integração para domínio, storage, uploads, Store API e HPOS;
- adicionar Playwright para PDP, editor mobile, carrinho, checkout e admin;
- criar `docs/guia-personalizacao-produtos.md` para cadastro de produto;
- criar `docs/operacao-personalizacoes.md` para revisão, download, produção e retenção;
- documentar backup, restore, diagnóstico e recuperação de falha;
- realizar pedido completo de cada tipo de produto de referência.

**Gate verificável**

- [ ] cenário bandana aprovado com máscara e texto;
- [ ] cenário laço/lacinho aprovado com área irregular;
- [ ] cenário adesivo aprovado com imagem e PNG final;
- [ ] testes PHP, Store API e navegador passam no Compose;
- [ ] operador sem conhecimento técnico conclui revisão e download pelo WP Admin;
- [ ] documentação reflete a interface final.

## 12. Matriz mínima de validação

| Superfície | Cenários obrigatórios |
| --- | --- |
| Produto | habilitado/desabilitado, texto, imagem, máscara, baixa resolução, mobile |
| Carrinho | refresh, remoção, quantidade, duas artes iguais/diferentes, sessão expirada |
| Checkout Block | convidado, logado, validação, criação de draft order, gateway |
| Pedido HPOS | pago, pendente, cancelado, reembolso, webhook repetido |
| Admin | filtros, capabilities, nonce, download, ZIP, transições e histórico |
| Minha conta | proprietário, outra conta, convidado e pedido antigo |
| Arquivos | MIME falso, extensão dupla, excesso de bytes/pixels, traversal e acesso direto |
| Persistência | reprovisionamento, atualização de schema, mudança do produto e restore |
| Acessibilidade | teclado, foco, nomes acessíveis, contraste, erro e movimento reduzido |

Comandos finais serão materializados pelos scripts do plano, mantendo o padrão do repositório:

```bash
npm run test
npm run validate
docker compose --profile test run --rm test-runner
docker compose --profile tools run --rm --no-deps cli wp petshop personalization doctor
docker compose --profile tools run --rm node node scripts/validate-012-personalizer-browser.mjs
```

## 13. Critérios de aceite globais

- [ ] Nenhum plugin/SaaS de personalização é requisito de execução.
- [ ] Licença do código próprio e de terceiros está explícita e juridicamente aprovada.
- [ ] Todo conteúdo comercial e toda imagem administrável possuem origem no Gutenberg, WooCommerce ou Biblioteca de mídia.
- [ ] Reprovisionamento não sobrescreve página, produto, mockup, máscara ou pedido.
- [ ] Carrinho e Checkout Blocks preservam a personalização via Store API.
- [ ] Pedidos e consultas usam CRUD WooCommerce compatível com HPOS.
- [ ] Arquivos originais e de produção não possuem URL pública direta.
- [ ] A equipe opera a fila sem acesso a arquivos do servidor ou alteração de código.
- [ ] Compradores não acessam arquivos de outros pedidos nem arquivos técnicos de produção.
- [ ] Snapshot antigo permanece íntegro após alterações no produto.
- [ ] Uploads, JSON, imagens e ZIPs têm limites e validação defensiva.
- [ ] Retenção, privacidade, backup e restore estão documentados e testados.
- [ ] Testes de bandana, laço/lacinho e adesivo passam em desktop e mobile.
- [ ] Não há erros fatais, warnings WooCommerce ou regressão no catálogo convencional.
- [ ] `Plans/STATUS.md` e documentação operacional estão atualizados.

## 14. Riscos e mitigação

| Risco | Mitigação |
| --- | --- |
| Imagem bonita na tela, mas inadequada para impressão | Separar canvas visual da resolução final; configurar mm/DPI; preservar original; validar pixels efetivos. |
| Arquivos pessoais expostos em `uploads` | Volume fora do document root, endpoint autorizado e teste de acesso direto. |
| Upload usado para execução, XSS ou exaustão de memória | Lista restrita de formatos, assinatura real, reprocessamento, limites de bytes/pixels/objetos e sem SVG público. |
| Checkout Block perder metadados | Integração Store API explícita e E2E com criação de pedido; não depender somente de hooks do checkout clássico. |
| Alteração do produto corromper pedido antigo | Snapshot imutável, hashes e arquivos ligados ao item do pedido. |
| Fila divergir do estado do WooCommerce | Máquina de estados idempotente, histórico e reconciliação WP-CLI. |
| Storage e banco restaurados em momentos diferentes | Hashes, comando `doctor`, runbook de backup/restore e reconciliação sem exclusão automática. |
| Feature crescer até um sistema gráfico genérico | Limitar MVP a uma superfície, texto, uma imagem, máscara e PNG. Novas capacidades exigem outro plano. |
| Código ser chamado open source sem licença válida | Gate jurídico na Sessão 00; atualizar licença antes da divulgação/distribuição. |

## 15. Critério de conclusão

O Plano 012 só poderá ser marcado como concluído quando um comprador conseguir personalizar e comprar uma bandana, um laço/lacinho e um adesivo pelo fluxo real de Cart/Checkout Blocks; quando cada arte chegar intacta ao pedido HPOS e à fila **WooCommerce → Personalizações**; e quando a equipe conseguir revisar e baixar os arquivos privados de produção sem acessar o servidor ou modificar código.

Também será obrigatório demonstrar que conteúdo e imagens permanecem administráveis, que pedidos antigos não mudam após reprovisionamento e que nenhum arquivo privado pode ser obtido por URL direta ou por usuário sem autorização.
