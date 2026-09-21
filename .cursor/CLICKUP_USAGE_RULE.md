# ClickUp — Autelie Ecommerce

Fonte canônica da lista deste repositório. Não versionar tokens nem credenciais.

| Campo | Valor |
|---|---|
| Workspace | `90171434043` |
| Space | Autelie Ecommerce (`90176706662`) |
| Lista | List (`901715697289`) |
| URL | https://app.clickup.com/90171434043/v/l/li/901715697289 |

## Status da lista

`Open` → `in progress` → `review` → `Closed`

## Regras de criação

1. Ler `.cursor/skills/write-kanban-tickets/SKILL.md` antes de criar ou revisar o ticket.
2. Criar **uma tarefa ClickUp por plano** na lista acima. Não criar lista, pasta ou space novos.
3. Antes de criar, buscar na lista pelo número do plano (`Plano 0NN`) e pelo título. Reutilizar a tarefa existente; não duplicar.
4. Título: `Plano NNN - Resultado curto` (exemplo: `Plano 025 - Frete com prazo por CEP`).
5. Descrição em Markdown, em português, com as seções obrigatórias da skill:
   - `## Por quê`
   - `## Critérios de aceite`
   - `## Contexto técnico`
6. **Nada opcional:** nenhum critério, campo ou parâmetro pode aparecer como opcional, nice to have ou proposta. Ou é obrigatório ou está fora de escopo (`plans-no-optional.mdc`).
7. CEP de endereço implica ViaCEP (`viacep-address.mdc`). Calculadora de frete é a única exceção.
8. Sempre incluir o caminho local: `Plano local: Plans/{numero}-{slug}.md`.
9. Não depender de links externos para o mínimo necessário; o ticket ClickUp deve ser acionável sozinho.
10. Não preencher assignee, tag, prioridade, due date ou campo customizado sem pedido explícito.
11. Status inicial: `Open`. Só mudar status quando o usuário pedir ou o fluxo (`pr-to-clickup`, `merge-and-close-ticket`) exigir.
12. Após criar, gravar no plano local o ID, a URL e o status do ticket ClickUp.

## Relação com o repositório

```text
ClickUp: Plano 025 - ...
Local:   Plans/025-slug.md
Branch:  025-slug   (a partir de master)
```
