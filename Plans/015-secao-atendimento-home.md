# Plano 015 - Secao de atendimento da Home

**Status:** Pendente

**Data:** 2026-08-05

**Branch sugerida:** `015-secao-atendimento-home`

**Dependencias:** Plano 014 implementado (tokens, tipografia e padrao de midia); Home editavel dos Planos 004b e 011.

**Origem:** substituicao do banner de atendimento atual, que e uma unica imagem clicavel e mistura copy, icone e CTA dentro da arte.

## 1. Objetivo

Substituir a faixa atual de "Fale conosco" por uma secao editorial de atendimento, visualmente coerente com o banner promocional, mas composta por blocos Gutenberg nativos. Titulo, texto, beneficio, CTA, destino, foto e texto alternativo devem ser editaveis sem codigo.

A secao oferece uma unica acao clara: abrir atendimento pelo WhatsApp da loja, preferencialmente por link direto `https://wa.me/<numero>` com mensagem inicial opcional. O WhatsApp e o melhor caminho para atendimento porque reduz friccao, preserva contexto da conversa e evita criar uma segunda experiencia de suporte dentro da Home. Nao prometer resposta, horario ou condicao comercial nao confirmados.

## 2. Decisao de produto

- Usar um padrao Gutenberg estruturado em `core/group`, `core/image`, `core/heading`, `core/paragraph` e `core/buttons`; nao criar shortcode, Customizer opaco ou arte final como unica origem de copy.
- Aplicar a classe de apresentacao `petshop-support-banner` no grupo externo e manter a edicao no canvas da Home.
- No desktop, seguir a logica dos banners promocionais: copy a esquerda (ate 40% da largura) e foto/ilustracao de apoio a direita (aproximadamente 60%).
- No mobile, conteudo empilha: copy e CTA antes da midia. A secao nao pode depender de texto sobre fotografia.
- Ha exatamente um CTA primario. Nao incluir formulario, telefone solto, redes sociais, carrossel, segundo CTA, chat de terceiros ou icone grande do WhatsApp como concorrente visual.
- O CTA deve apontar preferencialmente para WhatsApp (`https://wa.me/...`). Pagina de atendimento so e fallback documentado quando o numero/link de WhatsApp ainda nao estiver configurado.
- O CTA pode conter um icone oficial pequeno como apoio, mas o rotulo em texto e o destino devem continuar visiveis e editaveis.
- A URL inicial deve ser provisionada a partir da configuracao global de WhatsApp somente se ela for valida; depois de salva no botao Gutenberg, nao pode ser sobrescrita pelo codigo. Se nao houver URL valida, o botao nao e provisionado/publicado como link funcional.

### Copy inicial proposta

Os textos abaixo sao apenas valores iniciais administraveis, nunca strings fixas de template:

| Campo | Valor inicial |
| --- | --- |
| Eyebrow | `Atendimento especializado` |
| Titulo | `Precisa de ajuda para escolher?` |
| Texto | `Nossa equipe ajuda voce a encontrar acessorios adequados para seu pet ou negocio.` |
| Beneficio opcional | `Orientacao para pedidos, kits e reposicao.` |
| CTA | `Falar pelo WhatsApp` |

### Midia inicial sugerida

- **Imagem desktop utilizada/sugerida:** 1920 x 640 px, proporcao 3:1, sem texto dentro da arte. Usar foto real ou composicao fotografica de atendimento com produtos Autelie organizados, mao segurando celular/WhatsApp em contexto discreto ou bancada de separacao de pedidos; manter o assunto principal no lado direito para preservar a copy a esquerda.
- **Imagem mobile utilizada/sugerida:** 1080 x 1350 px, proporcao 4:5, sem reaproveitar corte horizontal. Usar o mesmo conceito visual em enquadramento vertical, com area segura no centro e sem elementos importantes nas bordas.
- **Imagem placeholder aceitavel:** enquanto nao houver foto real aprovada, usar imagem simples de bancada, produtos, embalagem ou separacao de pedidos, sem promessas comerciais e sem texto embutido.
- **Nao usar:** print de conversa, numero de telefone dentro da imagem, selo verde gigante do WhatsApp, arte com copy fixa, foto escura generica ou imagem que dependa de texto para explicar a acao.

## 3. Conteudo administravel por rota

### Rota `/` - Home

| Item | Onde o cliente edita | Regra de exibicao |
| --- | --- | --- |
| Posicao e estrutura | **Paginas -> Home**, padrao/grupo `petshop-support-banner` | Ultima secao editorial antes do rodape; nao substitui hero ou campanhas. |
| Eyebrow, titulo, texto e beneficio | Blocos `Paragrafo` e `Titulo` no canvas | Todos opcionais individualmente, mas titulo e CTA sao necessarios para uma chamada completa. |
| Rotulo e URL do CTA | Bloco `Botoes` -> `Botao` | Um unico botao. Caminho preferencial: `https://wa.me/<numero>` administravel; pagina de atendimento apenas como fallback temporario. |
| Foto/ilustracao desktop | Bloco `Imagem` -> Biblioteca de midia | Tamanho utilizado/sugerido: 1920 x 640 px; assunto no lado direito; sem texto embutido. |
| Foto/ilustracao mobile | Segundo bloco `Imagem`, visivel por breakpoint | Tamanho utilizado/sugerido: 1080 x 1350 px; nao repetir arte horizontal cortada. |
| Alt | Painel lateral de cada imagem | Obrigatorio quando a imagem comunica atendimento; vazio somente para ornamento puramente decorativo. |
| Ordem, remocao e edicao | Controles nativos do Gutenberg | Padrao pode ter travas de layout, mas administrador pode destravar conscientemente. |

## 4. Especificacao visual e responsiva

| Aspecto | Desktop (>= 1024 px) | Mobile (< 768 px) |
| --- | --- | --- |
| Container | Largura de conteudo, ate 1280 px; minimo de 320 px de altura; imagem-fonte 1920 x 640 px | Largura total do conteudo; sem altura fixa; imagem-fonte 1080 x 1350 px |
| Layout | Grid 40/60, alinhamento vertical central | Pilha unica; copy -> CTA -> imagem |
| Fundo | `brand-teal-900` com gradiente discreto ate `brand-teal-700` | Mesma identidade, sem reduzir contraste |
| Copy | Titulo branco, texto `neutral-100`, beneficio curto | Mesma hierarquia, sem texto sobre a foto |
| CTA | `brand-orange-action`, texto branco, minimo 44 x 44 px | Largura natural ou 100% quando o espaco exigir |
| Midia | Imagem 3:1 com assunto a direita; borda/raio do sistema; sem texto essencial | Fonte vertical 4:5, `object-fit: cover`, sem corte do assunto; sem texto essencial |
| Espacamento | 48 px de margem interna minima; 24 px entre copy e CTA | 24 px laterais; 16-24 px entre elementos |

Nao usar o verde oficial do WhatsApp como fundo principal da secao. A identidade AUTelle conduz a composicao; o verde do WhatsApp e reservado ao icone/referencia oficial pequena no CTA, quando usada.

## 5. Migracao e persistencia editorial

- A origem atual e uma imagem unica dentro do grupo `petshop-support-banner`, documentada em `docs/guia-edicao-home.md`.
- A migracao so substituira esse conteudo quando encontrar exatamente o markup legado provisionado ou o attachment placeholder identificado pelo projeto. Conteudo alterado pelo cliente nao sera interpretado como legado e nao sera substituido.
- Quando nao for seguro migrar, a secao atual permanece e o guia administrativo explica a substituicao manual pelo padrao novo.
- O novo markup e salvo em `post_content`; URL, copy, imagem e alt nao podem depender de `theme_mod`, PHP ou CSS depois de provisionados. A configuracao global de WhatsApp pode ser usada apenas para preencher a URL inicial do botao.
- A URL inicial deve ser `https://wa.me/<numero>` quando houver WhatsApp valido. Fallback para pagina de atendimento precisa ficar documentado como temporario.
- Apos provisionar o padrao, uma atualizacao/reprovisionamento nao pode modificar qualquer bloco, URL, imagem ou alt editado pelo cliente.

## 6. Arquitetura e arquivos previstos

| Area | Arquivos previstos | Responsabilidade |
| --- | --- | --- |
| Tema | `wp-content/themes/petshop-theme/style.css` | Layout, tokens, estados, responsividade e variantes de midia. |
| Tema | `wp-content/themes/petshop-theme/assets/css/editor-storefront.css` | Paridade do padrao no canvas Gutenberg. |
| Plugin | `wp-content/plugins/petshop-core/includes/class-storefront-experience.php` | Migracao versionada e segura do markup legado, sem sobrescrever conteudo. |
| Documentacao | `docs/guia-edicao-home.md` | Instrucao passo a passo, tamanho utilizado, imagem sugerida, alt, CTA para WhatsApp e fallback sem URL. |
| Documentacao | `docs/guia-identidade-visual-autelle.md` | Registrar a secao de atendimento como variante de banner editorial. |
| Validacao | `scripts/validate-015-*.php`, `scripts/validate-015-*-browser.mjs` | Estrutura Gutenberg, persistencia, CTA WhatsApp, contraste, mobile e regressao. |

## 7. Sessoes de implementacao

### Sessao 01 - Padrao Gutenberg e migracao segura

- [ ] Criar markup versionado com grupo externo, copy, botao e duas fontes de midia, usando apenas blocos nativos.
- [ ] Provisionar CTA inicial `Falar pelo WhatsApp` apontando para `https://wa.me/...` quando houver URL global valida.
- [ ] Provisionar o padrao somente onde houver assinatura inequivoca do banner legado.
- [ ] Preservar a secao existente em conteudo customizado e documentar conversao manual.
- [ ] Validar que todos os campos sao identificaveis no canvas e no painel lateral do Gutenberg.

**Gate verificavel**

- [ ] Nao ha shortcode, texto comercial ou URL de atendimento fixos no codigo.
- [ ] O melhor caminho de atendimento esta documentado e implementado como link de WhatsApp editavel no botao Gutenberg.
- [ ] Em Home nova/legada reconhecida, titulo, copy, CTA, imagens e alt sao editaveis em **Paginas -> Home**.
- [ ] Reprovisionamento nao altera uma secao editada pelo cliente.

### Sessao 02 - Storefront, acessibilidade e midia

- [ ] Aplicar grid desktop e pilha mobile segundo a secao 4.
- [ ] Garantir contraste AA, foco visivel, um unico link/botao e alvo minimo de toque de 44 px.
- [ ] Renderizar a fonte desktop 1920 x 640 px e a fonte mobile 1080 x 1350 px sem imagem quebrada, overflow ou corte de assunto.
- [ ] Ocultar com seguranca elementos opcionais vazios; nao deixar botao sem URL ou secao visualmente incompleta.

**Gate verificavel**

- [ ] A CTA anuncia destino compreensivel e abre apenas URL valida, preferencialmente `https://wa.me/...`.
- [ ] A foto nao contem texto essencial e o conteudo permanece legivel sem ela.
- [ ] A ordem de leitura e eyebrow, titulo, texto, beneficio, CTA e midia; nao ha duplicacao para leitor de tela.

### Sessao 03 - Documentacao, persistencia e regressao

- [ ] Atualizar os guias de edicao e identidade visual com dimensoes, area segura, imagem sugerida, formato e instrucoes claras de substituicao.
- [ ] Executar os gates do plano e validar Home em 390, 768, 1024 e 1440 px.
- [ ] Testar URL WhatsApp valida, URL ausente, troca de copy, imagens e alt, alem de migracao de banner legado e conteudo ja personalizado.
- [ ] Verificar logs PHP/console e ausencia de regressao em hero, campanhas e footer.

## 8. Riscos e mitigacao

| Risco | Mitigacao |
| --- | --- |
| Banner continua sendo uma arte dificil de atualizar | Copy e CTA sao blocos reais; imagem e somente apoio. |
| Migracao apaga edicao do cliente | Detectar apenas assinatura exata/placeholder legado; demais casos exigem conversao manual. |
| CTA visual nao aponta para WhatsApp funcional | Exigir URL `https://wa.me/...` valida e testar o `href`; sem URL de WhatsApp, nao publicar botao funcional ou documentar fallback temporario para pagina de atendimento. |
| Secao disputa atencao com campanhas | Posicionar no fim da Home e restringir a uma acao primaria. |
| Foto corta no mobile | Exigir fonte 4:5 dedicada e revisar ponto focal nos breakpoints. |
| Imagem comunica informacao que deveria ser editavel | Proibir texto essencial dentro da arte; todo texto precisa estar em blocos Gutenberg. |

## 9. Criterio de conclusao

O Plano 015 so podera ser concluido quando o banner-imagem atual for substituido ou preservado com seguranca, e o cliente conseguir em **Paginas -> Home** editar copy, CTA, URL do WhatsApp, imagem desktop, imagem mobile e alt sem codigo. A nova secao deve comunicar atendimento com uma unica acao clara, passar nos testes de contraste/teclado/responsividade, usar os tamanhos definidos (1920 x 640 desktop e 1080 x 1350 mobile) e manter todas as edicoes apos atualizacao ou reprovisionamento.
