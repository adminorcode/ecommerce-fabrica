---
name: write-kanban-tickets
description: Cria ou revisa tickets Kanban claros, acionáveis e verificáveis. Use para tickets em ClickUp, Jira, Azure DevOps ou documentação de planos.
---

# Escrever tickets Kanban

## Estrutura obrigatória

Todo ticket deve conter:

1. **Motivação** — 1–2 frases sobre objetivo, problema e contexto de uso.
2. **Critérios de aceite** — itens objetivos, verificáveis e focados no comportamento esperado.
3. **Contexto técnico** — somente quando necessário para orientar a implementação.

```markdown
## Por quê
[Objetivo, problema e contexto]

## Critérios de aceite
- [ ] Critério objetivo e verificável
- [ ] Critério objetivo e verificável

## Contexto técnico
[Contratos, componentes, dependências ou restrições relevantes]
```

## Regras

- O título deve ser curto, descritivo e orientado ao resultado.
- Critérios de aceite delimitam o escopo: itens solicitados são obrigatórios; sugestões ficam fora do ticket até aprovação.
- Use user story para funcionalidades de usuário final quando ela ajudar a esclarecer o valor.
- Não dependa de links ou documentos externos para explicar o mínimo necessário do ticket.
- Use o formato suportado pela ferramenta: Markdown no ClickUp/Jira e o formato configurado no Azure DevOps.
