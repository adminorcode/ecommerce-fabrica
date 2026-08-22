---
name: merge-and-close-ticket
description: Finaliza uma entrega: confirma o PR, faz merge autorizado, atualiza a branch de integração e fecha o ticket no ClickUp. Use somente quando o usuário pedir merge e fechamento.
---

# Merge e fechamento de ticket

## Pré-requisitos

- O trabalho do ticket está commitado e o Pull Request correspondente existe.
- O PR contém somente mudanças relacionadas ao ticket.
- As validações e aprovações exigidas pelo projeto foram concluídas.
- A estratégia de merge e a branch-alvo são conhecidas.

## Sequência

1. Validar branch, estado Git, PR, checks e aprovações.
2. Confirmar que o PR está vinculado ao ticket ClickUp correto.
3. Fazer merge usando a estratégia definida pelo repositório.
4. Atualizar a branch local de integração a partir do remote.
5. Atualizar o ticket ClickUp para o status final configurado pelo projeto.
6. Adicionar um comentário com a URL do PR mergeado, quando esse for o padrão do time.
7. Informar o PR, a branch integrada e o status final do ticket.

## Regras

- Não usar privilégios administrativos para ignorar proteção de branch sem pedido explícito.
- Não fechar o ticket se o merge falhar ou se os gates obrigatórios estiverem pendentes.
- Se o status padrão não existir no ClickUp, consultar os status válidos da lista antes de atualizar.
- Não assumir `develop`, `main`, `master`, estratégia squash ou lista ClickUp específica.
