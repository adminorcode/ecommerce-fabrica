---
name: pr-to-clickup
description: Cria um Pull Request e vincula sua URL ao ticket correspondente no ClickUp. Use quando o usuário pedir PR, vínculo de PR ao ClickUp ou ambos.
---

# Pull Request com vínculo no ClickUp

## Parâmetros a descobrir antes de agir

- Repositório e remote Git corretos.
- Branch atual e branch-alvo definida pelo projeto.
- Ticket ClickUp correspondente.
- Convenção de título, descrição e PR draft do projeto.

## Sequência

1. Validar branch, estado Git, remote e autenticação.
2. Confirmar que o escopo da branch corresponde a um ticket.
3. Enviar a branch ao remote.
4. Criar o Pull Request, normalmente como draft quando não houver pedido de revisão final.
5. Localizar o ticket no ClickUp pelo identificador da branch, referência do plano ou busca textual.
6. Adicionar a URL do PR como comentário no ticket.
7. Se comentários não estiverem disponíveis, preservar a descrição existente e acrescentar uma seção `## Pull Request` com a URL.
8. Informar URL do PR, ticket vinculado, branch-alvo e validações realizadas.

## Regras de segurança

- Não assumir repositório, branch-alvo, lista ClickUp ou ID de ticket; obter essas informações no projeto atual.
- Não criar PR, push ou alterar o ticket sem solicitação explícita do usuário.
- Não sobrescrever a descrição do ticket ao registrar o PR.
- Se já houver PR aberto para a branch, reutilizá-lo e apenas garantir o vínculo com o ticket.
