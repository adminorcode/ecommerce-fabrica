# Regras do projeto

Leia `.cursor/rules/project.mdc`, `.cursor/rules/gutenberg-content-editing.mdc`, `AI_BOOTSTRAP.md`, `Plans/README.md`, `Plans/STATUS.md` e o plano solicitado antes de implementar alterações.

## Conteúdo administrável

- Todo texto editorial, comercial ou institucional próprio e toda foto ou imagem de conteúdo exibida em páginas devem ser editáveis pelo cliente no painel do WordPress.
- **Regra determinística:** sempre que possível, conteúdo de página = edição no Gutenberg com paridade ao hero (blocos nativos visíveis no canvas). Customizer e shortcodes opacos ficam reservados a conteúdo global ou dados WooCommerce dinâmicos.
- Use Gutenberg, Biblioteca de mídia, produtos/categorias WooCommerce, menus ou configurações administrativas conforme a natureza do conteúdo.
- O código pode provisionar valores iniciais, mas deve salvá-los em uma origem administrável e preservar alterações posteriores.
- Não deixe textos comerciais ou caminhos de imagens fixos em PHP, templates, CSS ou JavaScript quando sua alteração exigir edição de código.
- Textos funcionais da plataforma usam tradução/extensibilidade oficial; textos funcionais próprios com caráter comercial também devem ser administráveis.
- Imagens administráveis precisam permitir substituição e texto alternativo.

Todo plano de interface deve inventariar textos e imagens por rota, indicar onde cada item é editado e validar persistência após atualização ou reprovisionamento. O plano não pode ser concluído enquanto conteúdo próprio depender de alteração no código.
