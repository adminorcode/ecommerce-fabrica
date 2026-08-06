# Plano 015 — Seção de atendimento da Home

**Status:** Pendente

**Data:** 2026-08-05

**Branch sugerida:** `015-secao-atendimento-home`

**Dependências:** Plano 014 implementado (tokens, tipografia e padrão de mídia); Home editável dos Planos 004b e 011.

**Origem:** substituição do banner de atendimento atual, que é uma única imagem clicável e mistura copy, ícone e CTA dentro da arte.

## 1. Objetivo

Substituir a faixa atual de “Fale conosco” por uma seção editorial de atendimento, visualmente coerente com o banner promocional, mas composta por blocos Gutenberg nativos. Título, texto, benefício, CTA, destino, foto e texto alternativo devem ser editáveis sem código.

A seção oferece uma única ação clara: abrir o canal de atendimento configurado pela loja (normalmente WhatsApp), sem prometer resposta, horário ou condição comercial não confirmados.

## 2. Decisão de produto

- Usar um padrão Gutenberg estruturado em `core/group`, `core/image`, `core/heading`, `core/paragraph` e `core/buttons`; não criar shortcode, Customizer opaco ou arte final como única origem de copy.
- Aplicar a classe de apresentação `petshop-support-banner` no grupo externo e manter a edição no canvas da Home.
- No desktop, seguir a lógica dos banners promocionais: copy à esquerda (até 40% da largura) e foto/ilustração de apoio à direita (aproximadamente 60%).
- No mobile, conteúdo empilha: copy e CTA antes da mídia. A seção não pode depender de texto sobre fotografia.
- Há exatamente um CTA primário. Não incluir formulário, telefone, redes sociais, carrossel, segundo CTA, chat de terceiros ou ícone grande do WhatsApp como concorrente visual.
- O CTA pode conter um ícone oficial pequeno como apoio, mas o rótulo em texto e o destino devem continuar visíveis e editáveis.
- A URL inicial pode ser provisionada a partir da configuração global de WhatsApp somente se ela for válida; depois de salva no botão Gutenberg, não pode ser sobrescrita pelo código. Se não houver URL válida, o botão não é provisionado/publicado como link funcional.

### Copy inicial proposta

Os textos abaixo são apenas valores iniciais administráveis, nunca strings fixas de template:

| Campo | Valor inicial |
| --- | --- |
| Eyebrow | `Atendimento especializado` |
| Título | `Precisa de ajuda para escolher?` |
| Texto | `Nossa equipe ajuda você a encontrar acessórios adequados para seu pet ou negócio.` |
| Benefício opcional | `Orientação para pedidos, kits e reposição.` |
| CTA | `Falar com a equipe` |

## 3. Conteúdo administrável por rota

### Rota `/` — Home

| Item | Onde o cliente edita | Regra de exibição |
| --- | --- | --- |
| Posição e estrutura | **Páginas → Home**, padrão/grupo `petshop-support-banner` | Última seção editorial antes do rodapé; não substitui hero ou campanhas. |
| Eyebrow, título, texto e benefício | Blocos `Parágrafo` e `Título` no canvas | Todos opcionais individualmente, mas título e CTA são necessários para uma chamada completa. |
| Rótulo e URL do CTA | Bloco `Botões` → `Botão` | Um único botão. A URL aceita `https://wa.me/…`, página de atendimento ou outro canal aprovado. |
| Foto/ilustração desktop | Bloco `Imagem` → Biblioteca de mídia | Recomendação: 1920 × 640 px; assunto no lado direito. |
| Foto/ilustração mobile | Segundo bloco `Imagem`, visível por breakpoint | Recomendação: 1080 × 1350 px; não repetir arte horizontal cortada. |
| Alt | Painel lateral de cada imagem | Obrigatório quando a imagem comunica atendimento; vazio somente para ornamento puramente decorativo. |
| Ordem, remoção e edição | Controles nativos do Gutenberg | Padrão pode ter travas de layout, mas administrador pode destravar conscientemente. |

## 4. Especificação visual e responsiva

| Aspecto | Desktop (≥ 1024 px) | Mobile (< 768 px) |
| --- | --- | --- |
| Container | largura de conteúdo, até 1280 px; mínimo de 320 px de altura | largura total do conteúdo; sem altura fixa |
| Layout | grid 40/60, alinhamento vertical central | pilha única; copy → CTA → imagem |
| Fundo | `brand-teal-900` com gradiente discreto até `brand-teal-700` | mesma identidade, sem reduzir contraste |
| Copy | título branco, texto `neutral-100`, benefício curto | mesma hierarquia, sem texto sobre a foto |
| CTA | `brand-orange-action`, texto branco, mínimo 44 × 44 px | largura natural ou 100% quando o espaço exigir |
| Mídia | imagem 3:1 com assunto à direita; borda/raio do sistema | fonte vertical 4:5, `object-fit: cover`, sem corte do assunto |
| Espaçamento | 48 px de margem interna mínima; 24 px entre copy e CTA | 24 px laterais; 16–24 px entre elementos |

Não usar o verde oficial do WhatsApp como fundo principal da seção. A identidade AUTellê conduz a composição; o verde do WhatsApp é reservado ao ícone/referência oficial.

## 5. Migração e persistência editorial

- A origem atual é uma imagem única dentro do grupo `petshop-support-banner`, documentada em `docs/guia-edicao-home.md`.
- A migração só substituirá esse conteúdo quando encontrar exatamente o markup legado provisionado ou o attachment placeholder identificado pelo projeto. Conteúdo alterado pelo cliente não será interpretado como legado e não será substituído.
- Quando não for seguro migrar, a seção atual permanece e o guia administrativo explica a substituição manual pelo padrão novo.
- O novo markup é salvo em `post_content`; URL, copy, imagem e alt não podem depender de `theme_mod`, PHP ou CSS.
- Após provisionar o padrão, uma atualização/reprovisionamento não pode modificar qualquer bloco, URL, imagem ou alt editado pelo cliente.

## 6. Arquitetura e arquivos previstos

| Área | Arquivos previstos | Responsabilidade |
| --- | --- | --- |
| Tema | `wp-content/themes/petshop-theme/style.css` | Layout, tokens, estados, responsividade e variantes de mídia. |
| Tema | `wp-content/themes/petshop-theme/assets/css/editor-storefront.css` | Paridade do padrão no canvas Gutenberg. |
| Plugin | `wp-content/plugins/petshop-core/includes/class-storefront-experience.php` | Migração versionada e segura do markup legado, sem sobrescrever conteúdo. |
| Documentação | `docs/guia-edicao-home.md` | Instrução passo a passo, padrão de mídia, alt, CTA e fallback sem URL. |
| Documentação | `docs/guia-identidade-visual-autelle.md` | Registrar a seção de atendimento como variante de banner editorial. |
| Validação | `scripts/validate-015-*.php`, `scripts/validate-015-*-browser.mjs` | Estrutura Gutenberg, persistência, CTA, contraste, mobile e regressão. |

## 7. Sessões de implementação

### Sessão 01 — Padrão Gutenberg e migração segura

- [ ] Criar markup versionado com grupo externo, copy, botão e duas fontes de mídia, usando apenas blocos nativos.
- [ ] Provisionar o padrão somente onde houver assinatura inequívoca do banner legado.
- [ ] Preservar a seção existente em conteúdo customizado e documentar conversão manual.
- [ ] Validar que todos os campos são identificáveis no canvas e no painel lateral do Gutenberg.

**Gate verificável**

- [ ] Não há shortcode, texto comercial ou URL de atendimento fixos no código.
- [ ] Em Home nova/legada reconhecida, título, copy, CTA, imagens e alt são editáveis em **Páginas → Home**.
- [ ] Reprovisionamento não altera uma seção editada pelo cliente.

### Sessão 02 — Storefront, acessibilidade e mídia

- [ ] Aplicar grid desktop e pilha mobile segundo a seção 4.
- [ ] Garantir contraste AA, foco visível, um único link/botão e alvo mínimo de toque de 44 px.
- [ ] Renderizar a fonte desktop e mobile correta sem imagem quebrada, overflow ou corte de assunto.
- [ ] Ocultar com segurança elementos opcionais vazios; não deixar botão sem URL ou seção visualmente incompleta.

**Gate verificável**

- [ ] A CTA anuncia destino compreensível e abre apenas URL válida.
- [ ] A foto não contém texto essencial e o conteúdo permanece legível sem ela.
- [ ] A ordem de leitura é eyebrow, título, texto, benefício, CTA e mídia; não há duplicação para leitor de tela.

### Sessão 03 — Documentação, persistência e regressão

- [ ] Atualizar os guias de edição e identidade visual com dimensões, área segura, formato e instruções claras de substituição.
- [ ] Executar os gates do plano e validar Home em 390, 768, 1024 e 1440 px.
- [ ] Testar URL válida, URL ausente, troca de copy, imagens e alt, além de migração de banner legado e conteúdo já personalizado.
- [ ] Verificar logs PHP/console e ausência de regressão em hero, campanhas e footer.

## 8. Riscos e mitigação

| Risco | Mitigação |
| --- | --- |
| Banner continua sendo uma arte difícil de atualizar | Copy e CTA são blocos reais; imagem é somente apoio. |
| Migração apaga edição do cliente | Detectar apenas assinatura exata/placeholder legado; demais casos exigem conversão manual. |
| CTA visual não aponta para WhatsApp funcional | Exigir URL válida e testar o `href`; sem URL, não publicar botão funcional. |
| Seção disputa atenção com campanhas | Posicionar no fim da Home e restringir a uma ação primária. |
| Foto corta no mobile | Exigir fonte 4:5 dedicada e revisar ponto focal nos breakpoints. |

## 9. Critério de conclusão

O Plano 015 só poderá ser concluído quando o banner-imagem atual for substituído ou preservado com segurança, e o cliente conseguir em **Páginas → Home** editar copy, CTA, URL, imagem desktop, imagem mobile e alt sem código. A nova seção deve comunicar atendimento com uma única ação clara, passar nos testes de contraste/teclado/responsividade e manter todas as edições após atualização ou reprovisionamento.
