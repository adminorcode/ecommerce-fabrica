# Workflow oficial de tickets

Este documento é a fonte de verdade para executar tickets no projeto
`ecommerce-fabrica`. Todo novo chat de ticket deve começar lendo este arquivo e
seguindo integralmente o processo antes de qualquer alteração.

## 1. Princípios

- Trabalhar em um ticket por vez.
- Nunca misturar correções de tickets diferentes.
- Encontrar um problema não significa autorização para corrigi-lo.
- `Implemented != Validated != Approved`.
- Não realizar merge sem revisão humana.
- Não usar `git add .`.
- Não alterar arquivos preexistentes fora do ticket.
- Diagnosticar antes de corrigir.
- Preservar arquitetura, stack, regras de negócio e documentação existente.
- Não transformar sugestões, preferências ou ideias em critérios obrigatórios.
- Critérios obrigatórios devem vir do ticket/plano ou de decisão explícita.
- Se houver ambiguidade entre obrigatório e opcional, parar e esclarecer antes
  de implementar.

## 2. Início de cada ticket

Antes de qualquer código:

1. Atualizar referências remotas.
2. Confirmar o estado de `origin/master`.
3. Confirmar se trabalhos recém-finalizados já entraram na `master`.
4. Atualizar `master` local com `git pull --ff-only`.
5. Criar branch nova a partir da `master` atualizada.
6. Conferir `git status`.
7. Identificar alterações locais preexistentes que não pertencem ao ticket.
8. Ler o plano do ticket.
9. Ler planos e dependências relacionados.
10. Verificar `Plans/STATUS.md`.
11. Verificar a implementação existente.
12. Verificar tickets que podem sobrepor ou conflitar.

## 3. Revisão do escopo antes da implementação

Antes de codar, responder:

- Qual é o problema real?
- Qual é o critério de aceite literal?
- O que é obrigatório?
- O que é apenas sugestão ou opcional?
- Existe requisito excessivo ou UX ruim embutido no plano?
- Existe conflito entre critérios?
- Existe dependência técnica?
- Existe implementação parcial já presente?
- Existe risco de regressão?
- Existe alternativa mais simples e segura?

Não implementar enquanto houver ambiguidade relevante.

Registrar explicitamente:

- **Obrigatório**
- **Opcional**
- **Fora de escopo**

## 4. Investigação read-only

Antes de editar:

- Inspecionar código atual.
- Inspecionar histórico e diff relevante.
- Inspecionar runtime quando necessário.
- Mapear integrações.
- Mapear testes e gates existentes.
- Mapear riscos.
- Propor escopo mínimo de arquivos.

Nenhuma alteração deve ser feita antes de autorização explícita.

## 5. Implementação

- Fazer mudança mínima.
- Manter a alteração isolada ao ticket.
- Evitar refatorações oportunistas.
- Não corrigir outros tickets.
- Não usar hacks para fazer gate passar.
- Manter correta a fonte de verdade do sistema.
- Preservar comportamento existente fora do escopo.

## 6. Testes e gates

Depois de implementar, executar validações proporcionais:

- Sintaxe.
- Testes unitários e de integração existentes.
- Gates específicos do ticket.
- Gates das dependências.
- Regressões relevantes.
- Runtime real.
- `git diff --check`.

Nunca enfraquecer assert existente apenas para fazer novo ticket passar.

Quando um gate fora do escopo falhar:

- Reproduzir isoladamente.
- Documentar a falha.
- Não corrigir sem autorização.
- Não atribuir automaticamente a falha ao ticket atual.

### Falhas de CI/GitHub Actions

Gate local aprovado não equivale automaticamente a CI aprovado. Toda falha de
GitHub Actions deve ser classificada antes de qualquer correção:

- bootstrap/runner/infra;
- instalação/dependências;
- build;
- gate/teste relacionado ao ticket;
- regressão funcional.

Registrar o ponto exato da falha e a evidência/log relevante. Se a falha ocorrer
antes de o gate do ticket ser executado, não atribuir automaticamente a falha ao
código do ticket. Sempre que possível, reproduzir localmente o mesmo
bootstrap/comando executado pelo CI antes de concluir a causa.

Quando existir script/comando local equivalente ao GitHub Actions, executá-lo
antes do PR ou handoff.

Se for problema de infraestrutura recorrente:

- não corrigir dentro de ticket funcional sem autorização;
- registrar como débito técnico;
- abrir ticket/plan separado de CI/infra;
- manter referência ao PR/ticket onde foi detectado.

Nunca declarar "todos os checks passaram" ou "CI aprovado" quando algum check do
GitHub estiver falhando.

No handoff/PR, registrar separadamente:

- gates locais;
- checks do GitHub;
- checks aprovados;
- checks falhos;
- estágio da falha;
- classificação ticket vs infraestrutura;
- bloqueio ou não bloqueio.

Finding de CI não autoriza automaticamente alterar código funcional.

## 7. Regra obrigatória para tarefas de UI

Toda tarefa que altera interface deve ter revisão visual real.

Depois da implementação e dos gates:

1. Abrir a interface real.
2. Gerar screenshots nas larguras relevantes.
3. Como padrão do projeto para tarefas de interface/storefront, usar:
   - 1440 px.
   - 1024 px.
   - 768 px.
   - 390 px.
4. Enviar os screenshots diretamente no chat do ChatGPT.
5. Pedir revisão visual de:
   - hierarquia;
   - espaçamento;
   - alinhamento;
   - proporções;
   - legibilidade;
   - excesso de altura;
   - responsividade;
   - overflow;
   - consistência com mockup ou design;
   - acessibilidade;
   - elementos obrigatórios versus desnecessários.

Quando o ticket exigir outras larguras, elas também devem ser testadas. Nenhum
desses quatro breakpoints deve ser omitido sem justificativa explícita no
próprio ticket.

Gate automatizado não substitui revisão visual.

Problemas visuais como telas excessivamente longas, formulários em "tripa
longa", elementos desnecessariamente obrigatórios, hierarquia ruim e
espaçamento ruim podem passar em testes automatizados. A revisão de screenshot
deve capturar esse tipo de problema.

Nenhum ticket de UI deve ser considerado tecnicamente pronto sem essa etapa.

## 8. Revisão obrigatória com Bugbot

Depois de testes/gates e, quando aplicável, revisão visual:

1. Executar `/review-bugbot`.
2. Analisar todos os apontamentos.
3. Corrigir os problemas válidos.
4. Repetir testes/gates.
5. Executar `/review-bugbot` novamente.
6. Prosseguir somente quando não houver problema bloqueante.

Auditoria manual por prompt não substitui `/review-bugbot`.

Para tickets com superfície relevante de segurança, executar também
`/review-security`.

Se `/review-bugbot` não estiver disponível na sessão:

1. Registrar claramente que a capacidade oficial não estava disponível.
2. Executar uma revisão estruturada direta do diff atual.
3. Não declarar "Bugbot PASS".
4. Registrar no handoff que foi usado "review estruturado de fallback".
5. Revisar obrigatoriamente:
   - bugs;
   - regressões;
   - lógica;
   - edge cases;
   - segurança aplicável;
   - arquitetura;
   - alterações fora do escopo;
   - tratamento de erros;
   - testes insuficientes;
   - acessibilidade quando aplicável;
   - documentação inconsistente.
6. Corrigir findings válidos.
7. Repetir testes afetados.
8. Executar novamente o review estruturado até não haver finding bloqueante.

Para `/review-security`, quando obrigatório pelo risco do ticket, tentar o
recurso oficial. Se indisponível, registrar a limitação e executar revisão de
segurança estruturada de fallback. Nunca declarar que o review oficial passou
quando ele não pôde ser executado.

O `/review-bugbot` deve procurar especialmente:

- bugs;
- regressões;
- segurança;
- arquitetura;
- lógica;
- edge cases;
- duplicação;
- tratamento de erro;
- acessibilidade quando aplicável;
- testes insuficientes;
- alterações fora do escopo.

## 9. Auditoria final antes do staging

Revisar:

- `git status --short`.
- `git diff --stat`.
- Diff completo dos arquivos do ticket.
- Arquivos inesperados.
- Whitespace.
- Encoding.
- Alterações acidentais.
- Arquivos preexistentes fora do ticket.

## 10. Staging seletivo

- Adicionar arquivos nominalmente.
- Nunca usar `git add .`.
- Conferir `git diff --cached --name-status`.
- Conferir `git diff --cached --stat`.
- Rodar `git diff --cached --check`.
- Garantir que somente arquivos autorizados estejam staged.

## 11. Commit e push

- Fazer commit apenas depois da aprovação da auditoria staged.
- Auditar o commit com `git show`.
- Conferir status pós-commit.
- Fazer push da branch.
- Confirmar tracking e commit remoto.
- Nunca mergear automaticamente.

## 12. Pull request

O PR deve conter:

- objetivo;
- implementação;
- escopo;
- arquivos e áreas preservadas;
- validações;
- regressões relevantes;
- limitações;
- commits;
- observações sobre falhas externas ao escopo;
- mensagem clara: "Pronto para revisão humana. Não realizar merge antes da
  aprovação."

O campo GitHub Reviewers pode permanecer vazio quando a equipe controla a
revisão pelo ClickUp.

## 13. ClickUp

Depois do PR:

- Mover o ticket para status Review.
- Atribuir o responsável humano pela revisão, conforme fluxo da equipe.
- Comentar com PR, commits e validações.
- Não marcar como concluído antes da revisão humana.
- Não fazer merge antes da aprovação.

## 14. Encerramento do ticket

O trabalho do executor termina quando:

- implementação concluída;
- testes aprovados;
- UI revisada visualmente quando aplicável;
- `/review-bugbot` aprovado ou fallback sem findings bloqueantes;
- `/review-security` aprovado quando aplicável ou fallback de segurança sem
  findings bloqueantes;
- staged auditado;
- commit e push concluídos;
- PR criado;
- ClickUp em Review.

Aprovação humana e merge são etapas posteriores.

## 15. Checkpoint para troca de chat

Ao final de cada ticket, gerar um checkpoint curto:

```text
Ticket:
Branch:
Base:
Commits:
PR:
Status ClickUp:
Validações:
Pendências externas:
Arquivos locais fora do ticket:
Próximo passo:
```

Esse checkpoint pode ser levado para um novo chat.

## 16. Início de um novo chat

Todo novo chat de ticket deve começar com a instrução:

> Leia `docs/WORKFLOW-TICKETS.md` e siga integralmente o workflow oficial antes
> de qualquer alteração.

O workflow do repositório é a fonte de verdade. Não depender de memória da
conversa anterior.

## 17. Lições incorporadas do Ticket 025

Este fluxo foi reforçado após revisão humana do Ticket 025. A revisão mostrou a
necessidade de:

- realizar várias correções antes da aprovação;
- identificar UI excessivamente longa;
- evitar tratar elementos como obrigatórios sem necessidade;
- confirmar a execução do `/review-bugbot`;
- enviar screenshots e fazer revisão visual humana/ChatGPT.

Essas observações são melhoria de processo. Não atribuem culpa a pessoas.
