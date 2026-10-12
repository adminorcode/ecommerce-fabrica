# Plano 042 — Login com identidade Auteliê e fundo com pets

**Status:** Em validação
**Data:** 2026-10-11
**Branch de implementação:** `042-repaginacao-login` (a partir de `master` sincronizada com `origin/master`)
**ClickUp:** [86e3p1dqh](https://app.clickup.com/t/86e3p1dqh) — Open; lista Autelie Ecommerce (`901715697289`).
**Pull Request:** [#16](https://github.com/adminorcode/ecommerce-fabrica/pull/16) — draft, alvo `master`.
**Origem:** referência visual fornecida pelo usuário, preservada em `docs/referencias/042-login-autelie.png`.
**Dependências:** Planos 014 (tokens da marca), 025 (cadastro e senha escolhida), 010b (lista de desejos).
**Alcance:** tela de entrada de visitante em Minha conta, pela página configurada no WooCommerce (slug atual `/minha-conta/`). Cadastro e recuperação recebem somente os ajustes necessários para manter navegação, legibilidade e funcionamento no novo invólucro. Área autenticada, login administrativo e login do checkout estão fora do redesenho.

## Por quê

A tela de entrada deve comunicar a identidade Auteliê e os benefícios da conta, seguindo a composição da referência: card branco de login à esquerda, benefícios e pets à direita, fundo claro com detalhes aqua e pêssego. O visitante deve entrar, recuperar a senha ou abrir o cadastro com clareza, mantendo a autenticação nativa WooCommerce.

## Escopo e decisões

Em 2026-10-11, o usuário autorizou corrigir os três bloqueios do fechamento:
overflow do header global em 390/360 px, fechamento do dropdown por Escape e
compatibilidade do Docker Compose no CI. São correções de comportamento e
infraestrutura; não alteram a edição do conteúdo nem redesenham o header.

- Desktop a partir de 1024 px: seção em duas áreas, card de login à esquerda e painel editorial à direita. Usar os tokens existentes da marca para teal, laranja, tipografia, espaçamento e foco.
- Card branco com borda suave, sombra discreta e cantos arredondados; título Entrar, introdução, campos com labels visíveis e ícones decorativos, lembrar-me, recuperação, botão Acessar com seta, separador ou e botão secundário contornado Criar conta.
- Campo de identificação aceita nome de usuário ou e-mail. Identificação e senha são obrigatórios; lembrar-me continua uma escolha do usuário, inicialmente desmarcada. Nunca provisionar admin nem senha preenchida do mockup.
- Preservar autocomplete de usuário/senha, preenchimento por gerenciadores e envio por Enter. O controle mostrar/ocultar senha informa seu estado, funciona por teclado e não envia o formulário.
- Criar conta abre o cadastro existente, com foco no primeiro campo; Voltar para entrar devolve foco à identificação. Preservar os campos e a validação do Plano 025, sem senha temporária nem exigência de ativação por e-mail.
- Perdeu sua senha? aponta para o endpoint público configurado pelo WooCommerce, sem fixar URL. Solicitação e redefinição continuam funcionando.
- Painel editorial inicial: Uma conta cheia de vantagens; Acompanhe seus pedidos; Salve seus produtos favoritos; Gerencie seus dados com facilidade; Receba ofertas exclusivas; Mais estilo para quem você ama. São textos Gutenberg, separados da arte. A frase de ofertas não cria inscrição nem promete automação de marketing.
- Ícones de benefícios seguem o sistema existente, decorativos e sem informação transmitida somente pela cor.
- Em larguras inferiores a 1024 px: uma coluna, formulário primeiro e conteúdo editorial abaixo; pets em área própria da composição, sem texto ou campos sobre os animais. Em 390 e 360 px, card ocupa a largura disponível com respiro lateral e rolagem única.
- Manter header, navegação comercial, breadcrumb e faixas globais existentes. A faixa de cinco benefícios do print orienta a referência global, mas não deve ser duplicada nem criada por este plano.
- Imagem gerada pronta em `docs/assets/042/login-background.png`: 1672 × 941 px, PNG, pets à direita e fundo claro sem textos. Importar para a Biblioteca de mídia na implementação e gerar derivação WebP de até 350 KB; guardar PNG como matriz. A arte é conteúdo substituível, não URL fixa em CSS.
- Usar blocos nativos Capa/Imagem, Grupo, Colunas, Título e Parágrafo para conteúdo editorial em Páginas → Minha conta. Isolar o formulário dinâmico WooCommerce em sua integração funcional; o conteúdo editorial deve estar visível no canvas, sem shortcode opaco para textos ou mídia.
- Provisionar valores iniciais por migração versionada que verifica estado anterior e preserva conteúdo existente. Não substituir integralmente `post_content` de página editada pelo cliente. Guardar ID de attachment e metadados próprios da composição; sem caminhos de conteúdo fixos no frontend.

## Critérios de aceite

- [x] Header global não gera overflow na Home e em Minha conta em 768/390/360 px, preservando ações com área mínima de 44 × 44 px.
- [x] Escape fecha o dropdown desktop e retorna foco ao pai; hover e foco continuam disponíveis sem JavaScript.
- [ ] CI instala Docker Compose compatível e aprova os gates PHP e PHPUnit antes do merge.

- [x] Em 1440 e 1024 px, a entrada reproduz a hierarquia do print: card à esquerda, benefícios e pets à direita, fundo claro, CTA teal e detalhes aqua/laranja sem alterar os tokens globais.
- [x] Em 768, 390 e 360 px, formulário vem primeiro; não há overflow horizontal, rolagem interna do card, sobreposição com pets nem corte de labels, mensagens ou CTAs.
- [x] Entrar por usuário e por e-mail funciona; credencial inválida e campo obrigatório vazio apresentam mensagens acessíveis do WooCommerce. Nonces, sessão, lembrar-me e redirecionamento continuam sob controle nativo.
- [x] Senha pode ser exibida/ocultada por teclado com nome acessível e estado anunciado. Inputs mantêm labels, required e autocomplete corretos; ícones são ignorados por leitor de tela.
- [x] Acessar funciona por clique e Enter; foco é visível; controles interativos têm área mínima de 44 × 44 px. Texto normal tem contraste mínimo 4,5:1, texto grande 3:1 e foco/controles 3:1.
- [x] Criar conta e Voltar para entrar alternam os painéis e o foco; cadastro com senha escolhida permanece funcional e conserva erros/dados após validação. Não há dois controles duplicados de Criar conta.
- [x] Recuperação e redefinição de senha continuam funcionais; mensagens longas cabem no layout. Sem JS, login/cadastro permanecem utilizáveis pelo fluxo nativo.
- [x] Usuário autenticado vê a área Minha conta atual; `wp-login.php` e login do checkout não recebem CSS nem JS deste redesenho.
- [x] Todos os textos editoriais inventariados podem ser alterados em Páginas → Minha conta; arte pode ser substituída pela Biblioteca de mídia, com alt e enquadramento editáveis. O editor exibe a composição e os textos no canvas.
- [x] Editar introdução, um benefício, frase afetiva, mídia e alt persiste no frontend após salvar, atualizar código e executar o reprovisionamento duas vezes. Nenhum texto/imagem de conteúdo próprio exige editar código.
- [x] Imagem de uso web tem dimensões declaradas, no máximo 350 KB e reserva espaço sem deslocar o card; imagem acima da dobra não usa lazy load. Recortes desktop/mobile preservam rostos e acessórios.
- [x] Header, navegação comercial e faixas globais não são duplicados. Não há promessa de oferta com inscrição automática.
- [ ] Gates focados PHP/browser cobrem fluxo afetado e persistência; fechamento roda os checks obrigatórios do projeto. Evidências e limitações reais são registradas antes de concluir o plano.

## Inventário de conteúdo administrável

| Rota/estado | Item e valor inicial | Origem de edição |
|---|---|---|
| Minha conta / visitante / login | Título Entrar | Bloco Título em Páginas → Minha conta |
| Mesma rota | Acesse sua conta para acompanhar pedidos e favoritos. | Bloco Parágrafo em Páginas → Minha conta |
| Mesma rota | Uma conta / cheia de vantagens | Bloco Título com destaques estilizados em Páginas → Minha conta |
| Mesma rota | Acompanhe seus pedidos; Salve seus produtos favoritos; Gerencie seus dados com facilidade; Receba ofertas exclusivas | Quatro grupos de benefícios com Parágrafos em Páginas → Minha conta |
| Mesma rota | Mais estilo para quem você ama | Bloco Parágrafo em Páginas → Minha conta |
| Mesma rota | Fundo com cachorro e gato usando acessórios | Capa/Imagem em Páginas → Minha conta; attachment na Biblioteca de mídia; alt e enquadramento no bloco |
| Login, cadastro e recuperação | Labels, obrigatoriedade, erros e controles de autenticação; Acessar, Criar conta, Voltar para entrar, mostrar/ocultar senha e separador ou | Tradução/extensibilidade WordPress/WooCommerce e domínio próprio para controles próprios; não são copy comercial |
| Navegação da mesma página | Breadcrumb e links da conta/cadastro/recuperação | APIs de URL e navegação WooCommerce; títulos da página no WordPress |
| Header/rodapé/faixas globais | Logo, menus, atendimento e benefícios globais já existentes | Origens administrativas globais atuais; este plano não reprovisiona esses conteúdos |

Texto alternativo inicial da arte: Cachorro e gato usando acessórios em tons de verde e laranja. Quando usada exclusivamente como decoração em Capa, expor como decorativa (alt vazio), mantendo descrição editável no attachment; quando exibida como Imagem no mobile, usar alt do attachment. Não repetir a imagem para leitores de tela.

## Contexto técnico

Plano local: `Plans/042-repaginacao-login.md`.

Baseline inspecionada: `includes/WooCommerce/AccountRegistration.php` enfileira assets apenas em página de conta para visitante; `assets/js/account-registration.js` encontra `#customer_login`, alterna `.petshop-register-open`, cria os controles e administra foco. `assets/css/account-registration.css` organiza os painéis e o cadastro. Integrar com essa lógica sem duplicar formulário nem botões.

Negócio, hooks, migração e integração Gutenberg em `wp-content/plugins/petshop-core`; apresentação em `wp-content/themes/petshop-theme`, escopada à entrada de visitante. Preferir hooks/APIs públicas; não editar WordPress, WooCommerce ou Blocksy e não instalar plugins. Não copiar template de login sem necessidade comprovada e registrada.

Separar editorial nativo do formulário dinâmico é a exceção funcional Gutenberg: dados de autenticação não são conteúdo editorial. Renderização pública precisa impedir que a composição de login vaze para endpoints autenticados. Documentar a edição em `docs/guia-edicao-home.md`, com seção Minha conta.

### Fora de escopo

- Redesenhar cadastro completo, endpoints da conta autenticada, checkout, header, menu, breadcrumb global ou rodapé.
- Alterar campos, regras de negócio ou políticas de cadastro do Plano 025.
- Autenticação social, magic link, CAPTCHA novo, MFA, plugins novos ou troca do mecanismo de login.
- Criar automação de ofertas, assinatura de newsletter ou consentimento de marketing.
- Copiar textos, logos, formulário ou credenciais para dentro da imagem.
- Commit, push, PR, merge, deploy ou alteração de status de outros tickets.

## Etapas de implementação

1. [x] Registrar baseline visual/funcional do login, cadastro e recuperação e conferir a origem Gutenberg da página configurada.
2. [x] Integrar a composição editorial nativa e o formulário existente; provisionar conteúdo e attachment sem sobrescrever edições.
3. [x] Aplicar layout, estados, comportamento de senha e responsividade com estilos locais.
4. [x] Validar fluxos WooCommerce, teclado, contraste, breakpoints, ausência de JS e conta autenticada.
5. [x] Validar edição e persistência após atualização/reprovisionamento; registrar screenshots e atualizar o guia.
6. [ ] Executar gates de fechamento, registrar evidências e só então concluir checkboxes e plano.

## Arte e referência

- Referência: `docs/referencias/042-login-autelie.png`.
- Matriz gerada: `docs/assets/042/login-background.png`.
- Ferramenta: ImageGen integrada; prompt completo em `docs/assets/042/image-prompt.txt`.
- Verificação visual ao criar ticket: pets, acessórios teal/laranja, espaço claro à esquerda e ausência de textos/interface confirmados. Otimização web e recortes serão validados na implementação.
- Criação do plano não executou testes da loja nem comprova aceite da implementação.
- Ticket criado e verificado no ClickUp em 2026-10-11. Upload das imagens bloqueado pela extensão Chrome sem acesso a arquivos locais; referência e arte estão preservadas no repositório e indicadas na descrição do ticket.

## Execução — 2026-10-11

Pedido de implementação recebido pela skill implement-feature-ticket. As alterações
locais iniciais eram exclusivamente plano/arte deste ticket e acompanharam a branch;
`master...origin/master` retornou `0 0` após fetch. Nenhuma alteração alheia foi incluída.

Baseline: página Minha conta ID 8 contém um bloco Shortcode WooCommerce, sem
editorial. WordPress local está healthy; WooCommerce 10.9.4, PHP 8.3 e Compose 5.3.1.
Watch existente `docker compose up --watch --build` sincroniza plugin/tema; não foi
iniciado outro processo.

### Contratos e arquivos

- `AccountLoginContent.php`: migração independente versão 1 por página, importação
  idempotente do attachment e composição com blocos nativos. Substitui somente a
  ocorrência canônica do shortcode, preservando o restante de post_content; páginas
  com composição diferente não são resetadas. Uma composição já editada é preservada
  mesmo quando a meta de versão está ausente.
- `render_block_data` remove o invólucro editorial antes de renderizar filhos nos
  endpoints da conta e para autenticados; mantém uma única execução do shortcode.
- `account-registration.js`: controle acessível de senha, integração com a
  inicialização tardia do controle nativo, opções de entrada e alternância/foco do
  cadastro. Labels funcionais traduzidos em `AccountRegistration.php`.
- `assets/css/account-login.css` no child theme: composição local e estilos do
  canvas Gutenberg. `functions.php` carrega CSS apenas na entrada de visitante,
  adiciona-o ao editor e suprime o hero automático apenas nessa composição.
- Arte de produção `assets/images/account-login.webp`: 70.788 bytes, importada
  para Biblioteca de mídia com alt editável. A matriz PNG permanece em docs.
- Gates 042 PHP, navegador e editor; regressão 025 e gates afetados pelo projeto.
  Fixtures sintéticas são removidas por cleanup; nenhum cliente real é usado.

### Validação e revisão

- Fechamento Docker: `docker compose build wordpress node`, recriação do WordPress
  e sincronização final de plugin/tema aprovados; serviço healthy.
- `npm test`: aprovado, 58 testes e 219 assertions.
- `npm run validate`: aprovado, incluindo o gate PHP 042 ao final. A primeira
  execução encontrou perda de aspas em uma chamada PowerShell do gate 041; a
  chamada passou a usar aspas simples no PHP. O fechamento completo foi repetido
  e passou. Logs em `.local/042-final-validation.log` e `.local/042-unit-tests.log`.
- PHP 042: blocos nativos, mídia única de 70.788 bytes, dimensões, carregamento
  eager, persistência após dois reprovisionamentos, autenticação por usuário/e-mail,
  redefinição e invalidação da chave de reset e área autenticada aprovados.
- Browser 042: 1440/1024/768/390/360 px; formulário primeiro no mobile,
  composição sem overflow próprio, controles de 44 px, contraste de CTA/título,
  autocomplete/required, senha por teclado, foco no cadastro/retorno, cadastro
  real sintético, entrada por e-mail/usuário via clique/Enter e mensagens nativas para campos vazios e credencial inválida aprovados.
  Sem JavaScript, login e cadastro permanecem visíveis e utilizáveis.
- Editor 042: blocos válidos; introdução, benefício, frase afetiva, mídia e alt
  editados, salvos e recarregados em página sintética, com cleanup confirmado.
- Regressão 025 PHP/browser aprovada. Rodada browser afetada também aprovou busca
  032, filtros 021, ausência de hero duplicado, carrinho/minicart 037 e quantidade
  039. O gate 035 encontrou falha no encerramento de requisições durante a rodada
  conjunta. Seu teardown passou a aguardar os proxies com unrouteAll(wait).
  A repetição expôs a falha funcional `desktop-1440: Escape nao fechou o dropdown`,
  reproduzida na Home, fora da conta. Log `.local/042-menu-regression.log`.
  Nenhum comportamento do menu/header foi alterado.
- Revisão manual: hooks preservam nonces, sessão e regras WooCommerce;
  renderização autenticada executa um único shortcode; migração não apaga conteúdo
  editado; URLs e alt escapados; CSS restrito à composição; controles funcionais
  traduzidos e ícones decorativos. `git diff --check` e sintaxe PHP/JS aprovados.
- Evidências: `.local/evidence/042/login-desktop-preview.png`, `login-390.png`,
  `login-360.png`, `gutenberg.png`, `gutenberg-saved.png` e `report.json`.
  Logs browser em `.local/042-final-browser.log` e
  `.local/042-final-browser-regression.log`. Fixtures e URLs temporárias são
  restauradas em `finally`.

### Pendência de aceite e handoff

O plano permanece **Em validação**, sem declarar aceite integral. Em 390 px,
o header comercial global excede a largura em aproximadamente 9 px; em 360 px,
em aproximadamente 38 px. O mesmo problema foi reproduzido na Home, sem a
composição de login. A composição 042 não transborda. Evidências comparativas
em `global-header-baseline-390.png` e `global-header-baseline-360.png` e medidas
em `report.json`. O critério de ausência total de overflow permanece aberto;
corrigir o header requer trabalho fora do redesenho definido neste plano.

A regressão browser 035 também permanece aberta pelo fechamento do dropdown
desktop por Escape. Os gates 042 PHP/browser/editor e o fechamento geral PHP/
contratos JS passaram; a suíte browser ampliada não foi declarada aprovada.

Textos e mídia são administráveis em Páginas → Minha conta, com guia atualizado.
A arte WebP foi importada na Biblioteca de mídia; a matriz PNG permanece em docs.
Branch `042-repaginacao-login`, implementação no commit `04dd72e`, enviada ao remoto.
PR #16 criado em draft e vinculado por comentário ao ClickUp, que permanece Open.
Merge, fechamento e deploy não foram executados.

Pedido de merge e fechamento recebido em 2026-10-11. Além das pendências de
header/menu, o check `php-gates` do PR falhou antes de executar testes: o Docker
Compose do runner rejeita `services.wordpress.develop.watch.0.initial_sync`.
[Log do CI](https://github.com/adminorcode/ecommerce-fabrica/actions/runs/38187357515).
A skill merge-and-close-ticket impede fechar o ticket com gates obrigatórios
pendentes. O PR foi preparado para revisão; integração e fechamento aguardam a
resolução das pendências. Comentário confirmado no ticket; evidência
`.local/evidence/042/pr-vinculado-clickup.jpg`.

### Correções de bloqueios autorizadas — 2026-10-11

- Header mobile: coluna do logo pode encolher, link da imagem limita-se à coluna
  e gaps do grid/ações foram reduzidos. Não há máscara de overflow; a largura
  real foi validada na Home e em Minha conta em 768/390/360 px, com ações de 44 px.
- Dropdown: a classe `petshop-commercial-menu--enhanced` reserva a abertura
  hover/foco por CSS ao fallback sem JavaScript. Com JS, `is-submenu-open` passa
  a controlar a visibilidade, permitindo Escape fechar mesmo com foco no pai.
  Gate 035 valida foco restaurado, reabertura, drawer, navegação das filhas e
  fallback sem JS; passou após o rebuild.
- CI: `docker/setup-compose-action@v2` instala explicitamente Compose v5.3.1,
  baseline validada localmente e atualizada no bootstrap, antes de validar a configuração e iniciar a
  stack. Os runners Windows/Bash também executam o gate PHP 042 por `wp eval`
  com require, compatível com strict_types do script.
- Browser 042 agora exige ausência de overflow da página inteira, removendo a
  exceção anterior do header. Cinco breakpoints, teclado, contraste, ausência
  de JS, cadastro e login usuário/e-mail passaram; log
  `.local/042-blockers-browser.log`. Evidências em `.local/evidence/035/` e
  `.local/evidence/042/`.
- Rebuild concluído com WordPress healthy. A primeira tentativa browser foi
  interrompida pela recriação do serviço; outra coincidiu com atualização
  automática do Core. Integridade do runtime conferida por checksums e URLs
  locais restauradas; a rodada posterior passou, com cleanup das fixtures.
- `npm run validate` aprovado com gate PHP 042, incluindo persistência após dois
  reprovisionamentos, autenticação e redefinição de senha. `npm test`: 58 testes
  e 219 assertions. Logs `.local/042-blockers-validation.log` e
  `.local/042-blockers-unit.log`. Revisão das correções e `git diff --check`
  aprovados. Gate 035 repetido após proteger foco durante mouseleave, aprovado
  em `.local/042-blockers-menu.log`.
- As pendências de header/menu descritas acima são históricas e foram resolvidas
  nesta rodada. O aceite final aguarda o CI do novo commit.
- O CI de `53b03da` demonstrou que o mínimo antigo 2.32.2 ainda rejeita
  `initial_sync`; o CI e a baseline de Compose em README/bootstrap/validador/skill
  operacional foram alinhados a 5.3.1, versão usada na validação local. Não houve
  alteração de Compose Watch nem remoção da sincronização inicial.
- CI passou pela configuração/build em 5.3.1 e expôs diferença de capitalização
  no gate 005: seed cria `Kits econômicos`, enquanto o teste esperava `Kits
  Economicos`. Comparação de labels passou a normalizar caixa tanto na origem
  quanto na expectativa, mantendo a validação dos sete destinos e das URLs.
  Nenhum menu do cliente foi alterado.
- CI com instalação nova também encontrou Fabric.js ausente: `.dockerignore`
  excluía o vendor de assets versionado do personalizador. A exceção específica
  foi alinhada ao `.gitignore`, preservando a exclusão do vendor Composer do
  host. Validação da imagem confirma o asset antes da nova rodada CI.
- Gate 032 passou a comparar o redirecionamento HTTP 302 do SKU com o permalink
  real do produto sintético, em vez de caminho inglês fixo. Bases e slugs
  configurados/localizados do WooCommerce permanecem válidos; destino incorreto
  ainda falha. Mensagem de falha inclui status, destino e URL esperada.

### Ajustes visuais solicitados — 2026-10-11

- Título Entrar em verde quase preto, derivado dos tokens teal-900 e neutral-950;
  tamanho máximo de 36 px, peso 700 e espaçamento refinado.
- Nunito Sans local para a composição e controles; Kalam 700 local no destaque
  manuscrito laranja. Isso elimina a herança das fontes de títulos do tema e a
  dependência externa para estas duas famílias. WOFF2 Latin somam 53.220 bytes,
  com licenças OFL preservadas em `assets/fonts/` do child theme.
- Fundo claro na área da entrada, ondas aqua/pêssego e patinhas em SVG decorativo
  do tema. O ornamento não contém texto, mídia editorial ou informação semântica;
  é estilo da composição. Textos e foto dos pets permanecem nos blocos Gutenberg.
- Estilos equivalentes no canvas do editor. Fundo aplicado ao contêiner externo,
  sem expandir a largura interna do card. Header e endpoints mantêm seu escopo.
- Gate browser 042 passou a exigir carregamento real das duas fontes locais e
  normalizar a cor CSS color-mix para RGB antes de verificar o contraste do título.
- Evidência e log desta rodada: `.local/evidence/042/` e
  `.local/042-visual-adjustment.log`. Pendências globais anteriores permanecem
  registradas na seção de handoff.
- Resultado: gate de login aprovado nas cinco larguras, com fontes locais,
  contraste, teclado, cadastro e autenticação. Editor aprovado após ajustar a
  espera de navegação do teste administrativo; log `.local/042-visual-editor.log`.
  Prévia final em 1440/390/360 aprovada; versão própria do SVG mobile preserva
  proporção das patinhas. `git diff --check` aprovado. URLs e fixtures restauradas,
  e aba de login local recarregada para revisão do usuário.
- Ajuste de término do fundo solicitado pelo usuário: padding-bottom do contêiner
  Blocksy zerado (antes 50 px) e margem anterior ao rodapé zerada (antes 40 px),
  somente quando existe a composição de login. Verificação no navegador: ambos
  0 px e distância entre main e rodapé igual a zero. Evidência
  `.local/evidence/042/login-sem-espaco-rodape.jpg`.
