---
name: implement-feature-ticket
description: >-
  Entrega um plano ou ticket do ecommerce-petshop: especificação testável,
  implementação, verificação com evidência e handoff. Use quando o usuário
  pedir para implementar, continuar, validar ou retomar um plano em Plans/
  ou um ticket ClickUp. Não cria .specs/, não commita e não abre PR sem
  pedido explícito.
---

# Implementar ticket ou plano

O plano em `Plans/{numero}-{slug}.md` é a especificação. Ticket ClickUp, plano e branch `{numero}-{slug}` apontam para o mesmo trabalho. Profundidade segue o tamanho da mudança. Especificar e executar são obrigatórios. Design e lista formal de tarefas só entram quando o tamanho pede.

Commit, push, PR, merge e deploy exigem pedido explícito. Implementar autoriza editar o worktree e o plano, e mover a tarefa ClickUp existente de `Open` para `in progress`. Não autoriza outro status, outra operação remota nem operação destrutiva.

Não narrar as fases. Entregar o artefato da etapa.

## Tamanho

Classificar antes de agir:

| Tamanho | Sinal | Design | Tarefas | Verificador |
| --- | --- | --- | --- | --- | --- |
| Pequeno | Até 3 arquivos, um comportamento | Pular | Listar os passos e executar | Subagent de code review no fechamento |
| Médio | Feature clara, poucos passos | Só se houver decisão de arquitetura | Passos no plano se passarem de 3 | Subagent de code review no fechamento |
| Grande | Vários componentes ou rotas | Registrar a decisão no plano | Passos com arquivo e verificação, antes de codar | Subagent de code review no fechamento, evidência por critério |
| Ambíguo | Comportamento de produto indefinido | Depois de decidir | Depois da spec fechada | Idem grande |

Se a lista de passos passar de 5, ou se houver dependência entre eles, tratar como grande e gravar os passos no plano antes de codar.

## Especificar

1. Ler o plano inteiro, `Plans/STATUS.md`, `AGENTS.md` e as rules aplicáveis. UI, endereço, checkout e conteúdo de página puxam as rules correspondentes. Operação Docker e validação seguem `petshop-workflow`.
2. Escrever o alcance numa frase: global, rota, componente ou lista fechada. Palavras como padrão, global, todos e cada vencem o exemplo isolado.
3. Consultar o código e a documentação antes de perguntar. Perguntar só decisão de produto que a leitura não resolve, uma por vez.
4. Cada critério descreve um comportamento e um resultado observável. Um critério não junta dois comportamentos. Valor concreto vence “rápido” ou “adequado”.
5. Forma do critério, em português:
   - quando [evento], o sistema [resultado]
   - enquanto [estado], o sistema [resultado]
   - se [entrada inválida ou falha], o sistema [resultado]
   - o sistema sempre [invariante]
6. Varrer dimensões só quando o trabalho as toca: validação de entrada, falha parcial, repetição, permissão, concorrência, dado externo, transição de estado. Dimensão presente vira critério ou fica fora de escopo com motivo. Dimensão ausente não gera requisito. Trabalho pequeno pula a varredura.
7. Ambiguidade restante entra no plano como premissa (decisão adotada e motivo) ou volta como pergunta. Nada segue sem marcação.
8. Fora de escopo é exclusão. Ideia não aceita não entra no plano. Não existe prioridade secundária.

A spec está fechada quando outro agente implementaria sem inventar comportamento.

## Design

Pular quando a mudança segue um padrão já usado no repositório.

Registrar no plano só decisão material: plugin versus tema, migração que preserva edição do cliente, HPOS, Checkout Block, ViaCEP, Gutenberg versus Customizer. A regra de negócio fica em `petshop-core`. A apresentação fica em `petshop-theme`.

## ClickUp

Antes de editar o worktree, localizar a tarefa do plano pelo ID ou URL gravados nele. Se o plano não tiver ID, buscar na lista Autelie Ecommerce (`901715697289`) pelo título `Plano NNN`. Sem tarefa, seguir a implementação sem criar card.

Tarefa em `Open` passa para `in progress`. Atualizar só o status. Não alterar descrição, assignee, prioridade nem prazo. Gravar o novo status na linha ClickUp do plano. Tarefa já em `in progress`, `review` ou `Closed` permanece. Falha do conector: registrar e continuar a implementação.

Retomar repete esta localização. Tarefa ainda em `Open` passa para `in progress` do mesmo modo.

## Executar

Antes do primeiro arquivo:

- worktree sem mudança de outro escopo; se houver, parar
- branch `{numero}-{slug}` a partir de `master` atualizado, para plano novo; plano já iniciado continua na branch dele
- baseline mínima do que o plano pode quebrar
- passos: um entregável, arquivos, como provar

Um passo por vez.

1. Teste ou gate nasce do critério, não do código atual. A asserção mira o resultado escrito no plano.
2. Implementar o mínimo que faz esse critério passar.
3. Não enfraquecer, apagar nem pular teste para obter verde. Teste que afirma o comportamento errado para o plano: parar e perguntar.
4. Rodar o gate do passo. `npm run validate:changed` na iteração. Script nomeado no plano quando o passo for o comportamento que esse script cobre. O comando decide. Falha de código corrige e repete. Falha de ambiente registra comando e causa.
5. Duas execuções do mesmo passo sem correção nova e segura: parar e reportar as duas tentativas.
6. Storefront, plugin ou tema: entregar o runtime com `docker-compose-watch-build` antes de confiar em gate que roda no contêiner.

Checkbox do plano só muda depois da evidência daquele item.

## Verificar

No fechamento, lançar um subagent de code review. Isso é obrigatório em todo plano, pequeno, médio ou grande. Quem implementou não substitui essa revisão lendo o próprio diff, os testes ou os comandos.

O subagent recebe o diff, o plano e os comandos executados, e revisa contra os critérios. Esperar o retorno antes do handoff. Sem esse retorno, a verificação não fecha.

Antes do handoff, corrigir os achados do code review em loop. Cada correção volta para um novo subagent de code review. O loop termina quando o retorno não tem issues.

Achado que depende de decisão relevante ou ambígua: comunicar ao usuário e esperar. Não escolher no lugar dele e não seguir o loop nesse ponto. Achado sem essa ambiguidade entra na correção.

Evidência ou zero. Cada critério obrigatório aponta para `arquivo:linha` do teste, saída de comando, ou passo manual com resultado observado. Critério sem essa citação está descoberto.

A verificação confere:

- o resultado afirmado é o do plano, não apenas “existe um teste”
- diff dentro do alcance
- plugin, tema, HPOS, Checkout Block, nonce, capability, escape e migração que não sobrescreve o cliente, quando o diff os toca
- interface: `ecommerce-design-review`
- checkout, login, pedido, estoque ou endereço: revisão de segurança; em endereço, ViaCEP

Para pagamento, autenticação, estoque ou transição de pedido, o teste precisa falhar se o resultado do critério for o oposto. Julgar pela asserção. Não alterar a árvore de trabalho para simular a falha.

Lacuna do code review vira correção no mesmo escopo e entra de novo no loop. Não há limite de rodadas enquanto o achado for claro.

UI com fluxo de loja: incluir em “Como validar manualmente” o que a pessoa deve ver. Não substituir isso por “rodar o script de novo”.

## Retomar

Ler o plano e o Git (`status`, branch, commits recentes). Evidência no código e nos comandos vence checkbox antigo. Propor o próximo passo antes de editar.

## Handoff

O handoff só sai depois que o code review retorna sem issues. Se uma decisão estiver com o usuário, o handoff espera essa resposta.

- comportamento entregue
- alcance
- tabela critério → evidência → resultado
- comandos e se falharam por código ou por ambiente
- como validar manualmente: URL, caminho no painel, resultado esperado, caso vazio quando o plano exigir
- pendência real ou fora de escopo já aceito
- branch, sem commit até pedido

## Não trazer de spec-driven genérico

- pasta `.specs/` paralela a `Plans/`
- prioridade “nice to have”
- commit atômico por tarefa
- sensor que modifica worktree, stash ou worktree temporário
- lições automáticas em arquivo próprio
