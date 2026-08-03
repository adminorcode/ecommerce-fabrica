# Convenções para planos

Todo plano que criar ou alterar páginas, templates ou componentes visuais deve tratar conteúdo administrável como requisito obrigatório.

## Textos e imagens

- Todo texto editorial, comercial ou institucional próprio do projeto deve ser editável no painel do WordPress.
- Toda foto ou imagem de conteúdo deve poder ser selecionada ou substituída pela Biblioteca de mídia e possuir texto alternativo editável.
- Valores iniciais podem ser provisionados pelo código somente quando forem salvos no WordPress e não sobrescreverem alterações posteriores.
- Textos funcionais do WordPress, WooCommerce ou plugins seguem suas APIs oficiais de tradução e extensibilidade. Textos funcionais próprios devem ser traduzíveis; se também forem comerciais, precisam ser administráveis.

## Conteúdo mínimo do plano

Um plano de interface deve:

1. inventariar, por rota, os textos, imagens, CTAs e regras de seleção;
2. indicar a origem de edição de cada item, como Gutenberg, Biblioteca de mídia, produto, categoria, menu ou configuração global;
3. proibir dependência de textos comerciais ou caminhos de imagens fixos em PHP, templates, CSS ou JavaScript;
4. definir migrações que preservem conteúdo já salvo;
5. validar que o cliente consegue alterar textos, substituir imagens e editar textos alternativos sem modificar código;
6. testar que uma atualização ou reprovisionamento não desfaz essas alterações.

## Critério transversal de aceite

O plano não pode ser marcado como concluído enquanto algum texto ou imagem de conteúdo próprio da interface depender de edição de código para ser atualizado.

## Roadmap pós-005 (engenharia)

| Plano | Foco |
|-------|------|
| [006](./006-infraestrutura-ci-e-documentacao.md) | Infra, CI, documentação, reconciliação do 003 |
| [008](./008-suite-de-testes-automatizados.md) | PHPUnit, Playwright containerizado |
| [007](./007-refatoracao-petshop-core.md) | Refatoração arquitetural do plugin |
| [009](./009-design-system-acessibilidade-e-checkout.md) | Design tokens, a11y técnica, cart/checkout |
| [010](./010-layout-secoes-produto-home.md) | Layout das vitrines de produto na Home (cards, cabeçalho, badges) |

Consulte [STATUS.md](./STATUS.md) para ordem de execução e dependências.
