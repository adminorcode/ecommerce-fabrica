---
name: implement-feature-ticket
description: Entrega um ticket de ponta a ponta: plano, branch, implementação, revisão, testes e handoff. Use quando o usuário pedir implementação completa de um ticket ou plano.
---

# Implementar ticket ou plano

## Resultado esperado

Entregar a implementação completa do escopo aceito, com critérios de aceite atendidos, validação executada e handoff claro. Commit, PR e merge são etapas separadas e exigem pedido explícito, salvo fluxo documentado em contrário.

## Fase 0 — entender o trabalho

1. Identificar ticket, plano e critérios de aceite.
2. Quando houver numeração, confirmar que ticket, pasta do plano e branch compartilham `{numero}-{slug}`.
3. Ler as regras do repositório, documentação técnica e instruções de teste.
4. Registrar dependências, riscos, itens fora do escopo e baseline de testes quando aplicável.
5. Localizar o ticket no ClickUp quando o projeto usar ClickUp.

## Fase 1 — enriquecer o plano

- Transformar critérios em passos implementáveis e verificáveis.
- Definir arquivos, contratos, migrações, UX, segurança e testes necessários.
- Incluir testes unitários, integração, E2E ou visuais conforme a stack e o impacto.
- Manter propostas adicionais fora do escopo até aprovação.

## Fase 2 — preparar a branch

1. Atualizar a branch de integração definida pelo projeto.
2. Criar a branch do ticket conforme a convenção do repositório. Para tickets numerados, usar exatamente o nome da pasta do plano: `{numero}-{slug}`.
3. Confirmar que o diretório de trabalho não contém mudanças não relacionadas.

## Fase 3 — implementar

1. Implementar o núcleo da mudança e seus contratos.
2. Implementar integrações, persistência, interface ou automação afetadas.
3. Criar ou atualizar testes junto com cada parte da entrega.
4. Respeitar arquitetura, segurança, acessibilidade, internacionalização e padrões de código do projeto.

## Fase 4 — fechar lacunas

- Comparar a implementação com cada critério de aceite e checkbox do plano.
- Corrigir lacunas encontradas; não declarar entrega enquanto houver requisito obrigatório pendente.
- Atualizar documentação e checkboxes somente após a validação correspondente.

## Fase 5 — revisão e testes

- Antes de validar no storefront: skill `docker-compose-watch-build` (`docker compose up --watch --build`) se plugin, tema ou Docker mudaram.
- Executar revisão de código conforme as ferramentas disponíveis no projeto.
- Corrigir achados críticos e relevantes e revisar novamente se a correção for ampla.
- Rodar os comandos reais de build, lint, testes unitários e testes de integração/E2E aplicáveis.
- Distinguir falha de código de bloqueio de ambiente e registrar ambos com evidências.

## Fase 6 — handoff

Informar:

- comportamento implementado;
- arquivos e áreas afetadas;
- comandos de validação e seus resultados;
- pendências, bloqueios ou decisões fora do escopo;
- branch atual e próximos passos.

Parar antes de commit, PR, merge ou alteração do ticket, a menos que o usuário peça uma dessas ações ou o fluxo do repositório determine explicitamente o contrário.
