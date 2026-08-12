# Plano 019 - Area para profissionais e laceiros

**Status:** Pendente

**Data:** 2026-08-11

**Branch sugerida:** `019-area-profissionais-laceiros`

**Dependencias:** [017-fechamento-publicacao-p0.md](./017-fechamento-publicacao-p0.md) para operacao segura; [012-personalizador-produtos-e-fila-producao.md](./012-personalizador-produtos-e-fila-producao.md) somente se a area evoluir para editor/layouts ou arquivos de producao.

**Origem:** `Orcode_Requisitos_Website_Loja_Pet_v2.pdf`, secoes 1.2, 8, 12.7, 18.1, 19 e 20.

## 1. Objetivo

Validar e implementar a primeira versao da area para profissionais/laceiros sem iniciar pelo editor de layouts. A primeira entrega deve ser uma pagina institucional e um fluxo comercial simples, com proposta, elegibilidade e canal de contato aprovados.

Editor, arquivos, precos restritos, autenticacao especial e propriedade intelectual exigem decisoes de negocio antes de qualquer desenvolvimento.

## 2. Decisoes pendentes obrigatorias

| Tema | Decisao necessaria antes de desenvolver recurso restrito |
| --- | --- |
| Publico | Quem e profissional/laceiro elegivel e como a loja valida esse perfil. |
| Proposta | A area vende produtos, servico, acesso a ferramenta, arquivos ou combinacao. |
| Preco | Precos publicos, atacado, por orcamento, assinatura ou aprovacao manual. |
| Autenticacao | Conteudo publico, conta WooCommerce comum, papel/capacidade propria ou aprovacao manual. |
| Propriedade intelectual | Quem possui layouts, artes, arquivos enviados e arquivos de producao. |
| Suporte | Canal, SLA, revisoes, cancelamento e atendimento operacional. |
| Producao | Como layouts viram pedido, arquivo, etiqueta, fila e entrega. |
| Privacidade | Quais dados/arquivos sao coletados e por quanto tempo sao retidos. |

Sem essas decisoes, o escopo maximo e pagina institucional publica com canal de contato.

## 3. Escopo do MVP institucional

- Criar pagina `/profissionais/` ou slug aprovado.
- Explicar proposta, elegibilidade, beneficios reais e proximo passo.
- Disponibilizar um canal de contato ou formulario validado.
- Manter todo conteudo editorial, imagens, CTA e alt editaveis em Gutenberg/Biblioteca de midia.
- Nao expor preco, arquivo, catalogo restrito ou promessa de ferramenta antes de aprovacao.
- Medir interesse por evento local de analytics condicionado a consentimento.

## 4. Conteudo administravel por rota

| Rota/area | Conteudo e midia | Origem administrativa |
| --- | --- | --- |
| `/profissionais/` | titulo, introducao, proposta, elegibilidade, beneficios, FAQ curta, CTA e imagens | Pagina Gutenberg; Biblioteca de midia; alt editavel. |
| Formulario/canal | campos, aviso de resposta, destino e consentimento | Plugin/formulario aprovado ou configuracao administrativa; sem segredo no codigo. |
| Menu/Home | entrada para profissionais quando aprovada | Menu administravel e bloco Gutenberg; nao hardcoded. |
| Analytics | evento de interesse/contato | Camada local do `petshop-core`, despacho externo condicionado a consentimento. |

## 5. Sessoes de implementacao

### Sessao 01 - Validacao comercial e bloqueios

- [ ] Registrar decisoes da secao 2 ou declarar que a entrega sera apenas institucional.
- [ ] Definir slug, nome de menu, CTA e canal de contato.
- [ ] Aprovar texto, imagens e limites de promessa comercial.
- [ ] Definir se a pagina sera publica, rascunho ou protegida ate aprovacao.

**Gate verificavel**

- [ ] Nao ha implementacao de editor, preco restrito ou area autenticada sem decisao registrada.
- [ ] Conteudo aprovado nao promete ferramenta, arquivo ou condicao ainda inexistente.

### Sessao 02 - Pagina institucional Gutenberg

- [ ] Provisionar pagina com blocos nativos: hero, proposta, elegibilidade, processo, FAQ e CTA.
- [ ] Garantir imagens substituiveis pela Biblioteca de midia e alt editavel.
- [ ] Adicionar entrada no menu ou Home somente quando aprovada.
- [ ] Preservar edicoes apos reprovisionamento.

**Gate verificavel**

- [ ] Cliente edita todo texto, CTA, imagem e alt em Paginas -> Profissionais.
- [ ] Pagina nao fica orfa quando publicada.
- [ ] Reprovisionamento nao sobrescreve conteudo editado.

### Sessao 03 - Contato, consentimento e operacao

- [ ] Integrar formulario ou canal de atendimento aprovado.
- [ ] Exibir expectativa de resposta somente se houver regra operacional.
- [ ] Registrar consentimento quando houver coleta para comunicacoes comerciais.
- [ ] Garantir mensagens de erro junto ao campo e fluxo acessivel por teclado.
- [ ] Emitir evento local de interesse/contato sem carregar terceiro antes de consentimento.

**Gate verificavel**

- [ ] Formulario/canal nao expoe dados pessoais em URL publica indevida.
- [ ] Erros nao dependem somente de cor e nao apagam dados corrigiveis.
- [ ] Logs nao armazenam conteudo sensivel do contato.

### Sessao 04 - Decisao de evolucao

- [ ] Se houver necessidade de area restrita, criar novo plano antes de implementar autenticacao/capacidades.
- [ ] Se houver necessidade de editor/layouts, decompor em plano proprio ou extensao do Plano 012.
- [ ] Mapear dados de pedido, arquivos, aprovacao, cobranca e producao antes de qualquer prototipo publico.
- [ ] Registrar riscos de propriedade intelectual, privacidade e suporte.

**Gate verificavel**

- [ ] Evolucoes P2 possuem plano separado com modelo de negocio e aceite tecnico.
- [ ] Nenhum arquivo, preco ou condicao restrita fica visivel a usuario nao autorizado.

## 6. Fora de escopo

- Editor avancado de layouts.
- Salvamento de layout em pedido.
- Arquivos privados de producao.
- Precos ou catalogos restritos.
- Autenticacao especial, perfis profissionais ou capacidades novas.
- Marketplace, assinatura, aprovacao automatica ou area colaborativa.

Esses itens so entram apos validacao comercial e plano especifico.

## 7. Criterios de aceite globais

- [ ] A primeira entrega e uma pagina institucional validavel, nao um editor.
- [ ] Texto, imagem, alt, CTA e canal sao administraveis sem codigo.
- [ ] Proposta, elegibilidade e proximo passo estao aprovados.
- [ ] A pagina nao expoe preco, arquivo, condicao ou promessa restrita sem autorizacao.
- [ ] Formulario/canal respeita privacidade, consentimento e acessibilidade.
- [ ] Qualquer evolucao restrita esta documentada como novo plano.

## 8. Criterio de conclusao

O Plano 019 so podera ser concluido quando a loja tiver uma pagina institucional para profissionais/laceiros com conteudo aprovado, canal de contato funcional, edicao completa pelo WordPress e sem exposicao de funcionalidades restritas ainda nao especificadas. Se a operacao decidir por editor, area autenticada ou precos profissionais, isso deve abrir um novo plano antes de qualquer implementacao.
