---
name: merge-branch
description: "Finaliza uma branch Git com segurança: revisa e cria o commit das alterações, identifica a branch-base definida pelo projeto entre master, main ou develop, envia a branch de origem, realiza o merge autorizado, envia a branch-base e confirma que as branches envolvidas estão sincronizadas com o remoto. Use quando o usuário pedir para finalizar, integrar, mesclar ou publicar uma branch e sincronizá-la com o remoto."
---

# Merge Branch

## Objetivo

Executar o fluxo completo de publicação e integração de uma branch sem presumir a branch-base, perder alterações locais ou reescrever o histórico remoto. Tratar a invocação desta skill como autorização para revisar, adicionar e criar commits das alterações em escopo, além de fazer push e merge das branches envolvidas. Não tratá-la como autorização para incluir alterações ambíguas, forçar push, apagar branches ou ignorar proteções.

## Regras invioláveis

- Ler `AGENTS.md` e as instruções de Git do projeto antes de qualquer operação mutável.
- Usar a branch-base definida pelo projeto: somente `master`, `main` ou `develop`.
- Nunca usar `git push --force`, `--force-with-lease`, `git reset --hard`, rebase, stash ou descarte de arquivos neste fluxo.
- Nunca apagar a branch de origem local ou remota sem pedido explícito.
- Nunca contornar branch protection, hooks, verificações ou políticas de revisão.
- Revisar o conteúdo antes de adicionar arquivos. Nunca usar `git add .`, `git add -A` ou equivalente sem confirmar exatamente o escopo listado por `git status` e pelo diff.
- Não versionar segredos, credenciais, dumps, dependências, artefatos gerados ou arquivos ignorados pelas regras do projeto.
- Se houver alterações alheias, inesperadas ou de escopos independentes cuja intenção não esteja clara, não incluí-las; pedir orientação quando não for possível separar com segurança.
- Não sincronizar todas as branches locais. Limitar o fluxo à branch de origem e à branch-base.
- Usar o mesmo shell durante todo o fluxo e citar nomes de remote e branch como argumentos literais.

## Determinar origem, destino e remote

1. Confirmar que o diretório pertence a um repositório com `git rev-parse --show-toplevel`.
2. Obter a branch atual com `git branch --show-current`. Se estiver em detached HEAD, parar.
3. Determinar a branch-base nesta ordem:
   1. destino informado explicitamente pelo usuário;
   2. regras versionadas, como `AGENTS.md`, `AI_BOOTSTRAP.md`, `CONTRIBUTING.md`, `README.md` ou documentação de workflow;
   3. branch padrão do remote, obtida por `git symbolic-ref --short refs/remotes/<remote>/HEAD`;
   4. única candidata existente entre `master`, `main` e `develop`.
4. Se fontes do mesmo nível divergirem, ou não houver uma única resposta segura, perguntar ao usuário.
5. Se a branch atual for a branch-base, pedir a branch de origem; não inferir uma branch arbitrária.
6. Escolher o remote pelo upstream da branch atual. Se não houver upstream, usar `origin` apenas quando ele existir e for inequívoco. Com múltiplos remotes plausíveis, perguntar.
7. Validar nomes com `git check-ref-format --branch` e confirmar a existência das referências antes de alterar estado.

## Pré-verificação

Antes do primeiro push:

1. Registrar branch atual, commit atual e estado com `git status --short --branch`.
2. Verificar se há merge, rebase, cherry-pick ou revert em andamento. Se houver, parar e explicar.
3. Executar `git fetch <remote> --prune`.
4. Comparar origem e destino com suas referências remotas usando `git rev-list --left-right --count`.
5. Se a origem estiver atrás ou tiver divergido do remoto, não rebasear nem forçar. Informar o estado e pedir orientação.
6. Executar as validações obrigatórias definidas pelo projeto. Se falharem, não criar o commit nem realizar o merge, salvo instrução explícita do usuário reconhecendo o risco.

## Criar o commit

1. Inspecionar `git status --short`, o diff de arquivos rastreados e o conteúdo dos arquivos não rastreados que possam entrar no commit.
2. Separar arquivos em mudanças coerentes. Quando todos pertencerem claramente ao pedido atual, incluí-los no mesmo commit; quando houver escopos independentes claramente autorizados, preferir commits separados.
3. Adicionar somente caminhos explícitos com `git add -- <caminhos>`.
4. Usar a mensagem fornecida pelo usuário. Sem mensagem fornecida, gerar uma mensagem curta, específica e coerente com o padrão do histórico do projeto.
5. Criar o commit e conferir com `git show --stat --oneline --decorate HEAD` e `git status --short --branch`.
6. Se não houver alterações, não criar commit vazio; continuar o fluxo e informar que nenhum commit novo foi necessário.

## Publicar a branch de origem

1. Se a origem já tiver upstream, executar `git push`.
2. Se não tiver upstream, executar `git push -u <remote> <origem>`.
3. Se o push for rejeitado, buscar o estado remoto novamente e parar. Nunca converter uma rejeição em force push.
4. Registrar o commit publicado em `<remote>/<origem>`.

## Atualizar e mesclar na branch-base

1. Trocar para a branch-base existente. Criá-la como tracking branch somente quando `<remote>/<destino>` existir claramente.
2. Atualizar com `git pull --ff-only <remote> <destino>`. Se não for possível fast-forward, parar sem reescrever histórico.
3. Registrar o commit da branch-base antes do merge.
4. Consultar a política de merge do projeto:
   - se exigir uma estratégia, segui-la;
   - sem política, usar `git merge --ff-only <origem>` quando a branch-base for ancestral direta da origem;
   - se fast-forward não for possível, usar merge não-fast-forward somente quando o pedido autorizar merge direto e as políticas permitirem: `git merge --no-ff <origem>`.
5. Em conflito, resolver apenas quando a intenção for inequívoca e todas as validações puderem ser repetidas. Caso contrário, abortar o merge com `git merge --abort`, preservar as duas branches e pedir orientação.
6. Executar novamente as validações obrigatórias no resultado integrado.
7. Enviar a branch-base com `git push <remote> <destino>`. Se o remote rejeitar por avanço concorrente ou proteção, não forçar; buscar o estado e informar o bloqueio.

## Sincronizar e verificar

Após o push bem-sucedido da branch-base:

1. Executar `git fetch <remote> --prune`.
2. Confirmar que a branch-base local e `<remote>/<destino>` apontam para o mesmo commit.
3. Confirmar que a branch de origem local contém o commit publicado em `<remote>/<origem>` e não possui commits locais sem push.
4. Não mesclar a branch-base de volta na origem apenas para igualar os hashes. Elas estão sincronizadas quando cada branch local corresponde à sua própria referência remota e a origem está integrada ao destino.
5. Permanecer na branch-base ao final, salvo regra do projeto ou pedido explícito em contrário.
6. Se a origem já estivesse integrada, tratar como operação idempotente: atualizar, verificar e informar que não houve novo merge.

## Relatório final

Informar de forma objetiva:

- branch de origem, branch-base e remote usados;
- commits publicados e tipo de merge realizado;
- validações executadas e resultado;
- estado de sincronização local/remoto das duas branches;
- branch ativa ao final;
- qualquer proteção, conflito, divergência ou etapa que permaneceu pendente.

Emitir as diretivas Git da aplicação somente para ações que realmente concluíram com sucesso.
