# Plano 017 - Fechamento de publicacao P0

**Status:** Pendente

**Data:** 2026-08-11

**Branch sugerida:** `017-fechamento-publicacao-p0`

**Dependencias:** [013-alinhamento-usabilidade-paginas-woocommerce.md](./013-alinhamento-usabilidade-paginas-woocommerce.md) com codigo entregue; depende de credenciais, politicas e decisoes operacionais externas.

**Origem:** `Orcode_Requisitos_Website_Loja_Pet_v2.pdf`, secoes 13, 15, 16, 17, 18.1 e 19; lacunas nao encerradas pelos Planos 013, 012 e 015.

## 1. Objetivo

Fechar os requisitos P0 necessarios para primeira publicacao da loja: pagamento sandbox, frete real ou regra operacional aprovada, emails transacionais, politicas publicadas, SEO tecnico, Core Web Vitals, backup/restore, monitoramento, acessibilidade manual e checklist de go-live.

Este plano nao substitui o Plano 013. Ele separa o que depende de credenciais, validacao juridica, ambiente de producao e aceite humano do que ja foi implementado localmente no fluxo WooCommerce.

## 2. Lacunas confirmadas

| Frente | Estado atual | Lacuna para publicacao |
| --- | --- | --- |
| Mercado Pago | Plano 013 preparou checkout e bloqueio sem segredo versionado | Pix/cartao em sandbox precisam cobrir aprovado, recusado e pendente sem pedido duplicado. |
| Frete | Calculo local e contrato de taxas foram preparados; Virtuaria Correios foi escolhido para validacao | Zonas, embalagens, origem, cobertura, servicos Correios, contrato/credenciais quando aplicavel e contingencia precisam ser aprovados. |
| Politicas | Paginas Gutenberg foram provisionadas sem inventar texto juridico | Trocas/devolucoes, privacidade, termos, entrega/frete e personalizacao precisam de conteudo validado e publicacao. |
| E-mails | WooCommerce envia emails transacionais quando configurado | Dominio/remetente, entregabilidade e templates devem ser verificados no ambiente alvo. |
| SEO tecnico | Rotas/canonical foram tratados no Plano 013 | Sitemap, robots, dados estruturados, indexacao de filtros e paginas removidas precisam de auditoria final. |
| Performance | Tokens e paginas criticas possuem gates de layout | Core Web Vitals laboratorio e estrategia de dados reais precisam ser medidos. |
| Seguranca | Codigo local segue nonces/capabilities nos modulos entregues | HTTPS, atualizacoes, backup/restore, logs, monitoramento e contas individuais sao gates de ambiente. |
| Acessibilidade | Gates automatizados existem | NVDA/VoiceOver e teclado em fluxo real ainda precisam de evidencia humana. |

## 3. Conteudo administravel e publicacao

| Rota/area | Conteudo exigido | Origem administrativa |
| --- | --- | --- |
| `/sobre/` | marca, fabricacao, proposta e informacoes aprovadas | Pagina Gutenberg, Biblioteca de midia e alt editavel. |
| `/contato/` | canais, horarios, expectativa de resposta e formulario aprovado | Pagina Gutenberg + configuracao do formulario/canal. |
| `/perguntas-frequentes/` | compra, medidas, producao, pagamento, frete, troca, personalizacao e atendimento | Pagina Gutenberg com secoes editaveis. |
| `/entrega-e-frete/` | calculo por CEP, prazos, cobertura, rastreamento e contingencia | Pagina Gutenberg; dados operacionais aprovados. |
| `/trocas-e-devolucoes/` | politica validada pela empresa/juridico | Pagina Gutenberg; publicacao bloqueada ate aprovacao. |
| `/politica-de-personalizacao/` | aprovacao, divergencias, prazos, cancelamento e limitacoes | Pagina Gutenberg; alinhada ao Plano 012. |
| `/privacidade/` e `/termos/` | documentos aprovados e links do checkout | Paginas Gutenberg/WooCommerce; sem texto juridico inventado. |
| `404` | orientacao para busca, loja e categorias principais | Template/gancho do tema com textos traduziveis e links administraveis quando forem editoriais. |

Nenhum texto institucional, juridico, comercial ou imagem de conteudo pode ficar fixo em PHP, CSS ou JavaScript. Valores iniciais so podem ser provisionados como conteudo gerenciavel e preservado em reprovisionamento.

## 4. Sessoes de implementacao

### Sessao 01 - Pagamento, frete e emails

- [ ] Configurar Mercado Pago sandbox sem versionar credenciais.
- [ ] Testar Pix/cartao aprovado, recusado e pendente, incluindo retorno/webhook e ausencia de pedido duplicado.
- [ ] Instalar/configurar Virtuaria Correios em ambiente de validacao, sem versionar credenciais.
- [ ] Configurar origem, zonas, embalagens, servicos Correios, contrato/credenciais quando aplicavel e regras de producao.
- [ ] Confirmar consistencia do frete em produto, carrinho e checkout.
- [ ] Validar falha de CEP/frete com mensagem orientativa e canal de atendimento sem valor ficticio.
- [ ] Configurar remetente, dominio e entregabilidade dos emails transacionais.

**Gate verificavel**

- [ ] Pedido aprovado, recusado e pendente possuem status e orientacao corretos.
- [ ] Virtuaria Correios considera endereco, produtos, quantidades, peso, dimensoes, classes e regras configuradas.
- [ ] Emails de pedido e mudanca de status chegam com resumo correto e sem dados privados indevidos.

### Sessao 02 - Politicas, paginas institucionais e checkout legal

- [ ] Publicar ou manter bloqueadas as paginas juridicas conforme aprovacao formal.
- [ ] Garantir que o checkout exige aceite das politicas aplicaveis e abre links acessiveis.
- [ ] Criar ou revisar Sobre, Contato e FAQ com conteudo aprovado.
- [ ] Criar pagina 404 util com busca, loja e categorias principais.
- [ ] Atualizar rodape e menus sem criar paginas orfas.

**Gate verificavel**

- [ ] Todas as paginas publicadas possuem titulo, conteudo, CTA ou proximo passo e caminho de navegacao.
- [ ] Conteudo juridico nao aprovado permanece em rascunho e nao desbloqueia publicacao.
- [ ] Alteracoes editoriais sobrevivem a reprovisionamento.

### Sessao 03 - SEO tecnico e indexacao

- [ ] Auditar titulo, meta description, H1, canonical e conteudo coerente por pagina indexavel.
- [ ] Validar sitemap XML, robots e estrategia para paginas removidas.
- [ ] Validar dados estruturados de produto, oferta, breadcrumb e organizacao.
- [ ] Impedir indexacao ilimitada de filtros e combinacoes de URL.
- [ ] Confirmar equivalencia de conteudo essencial entre mobile e desktop.

**Gate verificavel**

- [ ] Nao ha URLs indexaveis duplicadas ou filtros infinitos sem controle.
- [ ] Dados estruturados passam em validacao aplicavel.
- [ ] Paginas comerciais e institucionais possuem metadados administraveis ou extensiveis.

### Sessao 04 - Performance, seguranca, backup e acessibilidade manual

- [ ] Medir LCP, INP e CLS em laboratorio para Home, loja, busca, PDP, carrinho, checkout e conta.
- [ ] Registrar estrategia para monitorar Core Web Vitals em dados reais apos publicacao.
- [ ] Confirmar HTTPS, atualizacoes, contas individuais e menor privilegio administrativo.
- [ ] Testar backup, restauracao e procedimento de reversao.
- [ ] Validar logs de erro e monitoramento de indisponibilidade.
- [ ] Executar gate humano de teclado, NVDA e VoiceOver nas tarefas principais.

**Gate verificavel**

- [ ] Meta laboratorio: LCP ate 2,5 s, INP ate 200 ms e CLS ate 0,1 no teste adotado.
- [ ] Backup restaurado em ambiente de teste preserva loja, pedidos, uploads e configuracoes.
- [ ] Tarefas principais sao concluidas por teclado e leitor de tela sem bloqueio critico.

## 5. Fora de escopo

- Implementar o personalizador visual e fila de producao, que permanecem no Plano 012.
- Criar paginas comerciais P1 como Eventos, Por Raca, Animal Republik, premium, bandanas/adesivos e capa de chuva, cobertas pelo Plano 018.
- Criar area profissional/laceiros ou editor de layout, cobertos pelo Plano 019.
- Definir juridicamente politicas sem aprovacao da empresa.
- Trocar Virtuaria Correios por outro fornecedor de frete, gateway ou configuracao fiscal sem novo registro de decisao.

## 6. Criterio de conclusao

O Plano 017 so podera ser concluido quando um usuario nao autenticado conseguir comprar em fluxo real de publicacao com frete, pagamento, emails, politicas e paginas legais coerentes; quando SEO tecnico, Core Web Vitals, backup/restore, logs, monitoramento e acessibilidade manual estiverem validados; e quando nenhum conteudo institucional, juridico, comercial ou imagem de conteudo depender de alteracao de codigo para ser atualizado.
