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
- Critérios de aceite delimitam o escopo: itens solicitados são obrigatórios.
- **Nada opcional:** nenhum campo, critério ou parâmetro pode ser marcado como opcional, nice to have ou proposta. Ou é obrigatório no escopo ou está fora de escopo. Ideia não aceita não entra no ticket.
- CEP de endereço implica ViaCEP. Calculadora de frete é a única exceção.
- Use user story para funcionalidades de usuário final quando ela ajudar a esclarecer o valor.
- Não dependa de links ou documentos externos para explicar o mínimo necessário do ticket.
- Use o formato suportado pela ferramenta: Markdown no ClickUp/Jira e o formato configurado no Azure DevOps.
