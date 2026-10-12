---
name: write-kanban-tickets
description: >-
  Escreve ou revisa o plano local e o ticket ClickUp do ecommerce-petshop
  com critérios testáveis. Use ao criar ticket, enriquecer um pedido solto
  ou revisar aceite. O plano em Plans/ vem antes da tarefa. Não implementa.
---

# Escrever tickets Kanban

Esta skill fecha o que será construído. `implement-feature-ticket` consome o plano. Não codar aqui.

Enriquecer `Plans/{numero}-{slug}.md` primeiro. Depois criar ou atualizar a tarefa ClickUp com o mesmo contrato. Lista, título `Plano NNN - Resultado`, status e campos estão em `.cursor/CLICKUP_USAGE_RULE.md`. Buscar a tarefa existente antes de criar. Não preencher assignee, tag, prioridade ou prazo sem pedido.

Fatos saem do código e das rules. Pergunta só para decisão de produto que a leitura não resolve, uma por vez.

## Plano e ticket

O ticket é acionável sem abrir outro documento. O plano registra o caminho e, depois da criação, o ID e a URL do ClickUp.

### Por quê

Uma ou duas frases: problema, quem usa e o que muda.

### Alcance

Uma frase: global, rota, componente ou lista fechada. Padrão, global, todos e cada vencem o exemplo isolado.

### Fora de escopo

Exclusão com motivo. Ideia não aceita não entra. Sem opcional, nice to have ou proposta.

### Critérios de aceite

Um comportamento e um resultado observável por item. Valor concreto vence “adequado” ou “rápido”.

- quando [evento], o sistema [resultado]
- enquanto [estado], o sistema [resultado]
- se [entrada inválida ou falha], o sistema [resultado]
- o sistema sempre [invariante]

User story só quando o papel de quem usa não estiver claro no Por quê. Sem segunda prioridade.

Dimensão presente no trabalho vira critério ou entra em fora de escopo com motivo: validação de entrada, falha parcial, repetição, permissão, concorrência, dado externo, transição de estado. Dimensão ausente não gera requisito.

### Premissas

Só quando uma decisão seguir sem confirmação. Cada linha: ambiguidade, padrão escolhido, motivo. Sem premissa, omitir a seção.

### Inventário editorial

Só quando o trabalho exibe texto ou imagem de conteúdo. Por rota: o item e onde o cliente edita. Página editorial usa Gutenberg. Customizer fica para conteúdo global de header, rodapé ou aviso repetido. CEP de endereço implica ViaCEP. Calculadora de frete não usa ViaCEP.

### Validação

Como provar cada critério. Alcance global inclui uma amostra fora do exemplo principal. Uma baseline, gates da iteração e um fechamento. Critério sem prova correspondente não está coberto.

### Contexto técnico

Só o que orienta a implementação: contratos, arquivos, restrições. Omitir se não acrescentar.

## Antes de criar a tarefa

- cada critério tem uma leitura e um resultado
- fora de escopo está explícito
- o alcance cabe numa frase
- interface tem inventário, ou o trabalho não tem conteúdo editorial
- endereço com CEP cita ViaCEP, salvo cotação de frete
- pergunta em aberto virou premissa ou foi feita

Aí criar a tarefa em `Open` e gravar ID e URL no plano.

## Revisão

Reescrever critério vago no formato acima. Não acrescentar escopo. Sugestão fica fora do plano e do ticket até aceite.
