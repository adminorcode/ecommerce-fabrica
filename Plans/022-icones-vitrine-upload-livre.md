# Plano 022 - Icones de vitrine por upload livre

## 1. Status

Concluído.

## 2. Problema

Hoje as categorias de produto possuem dois recursos visuais separados:

- **Miniatura WooCommerce**, enviada pela Biblioteca de midia e usada pela pagina/listagem da categoria.
- **Icone da vitrine**, usado na Home em "Compre por categoria", escolhido apenas de uma galeria fixa de SVGs do projeto.

Isso limita o repasse ao cliente porque a identidade visual aprovada pode exigir icones proprios, desenhados pelo cliente ou por fornecedor, sem alterar codigo. A galeria atual funciona como fallback, mas nao permite upload livre por categoria.

## 3. Objetivo

Permitir que o cliente selecione um icone personalizado para a vitrine de cada categoria diretamente em **Produtos -> Categorias**, usando a Biblioteca de midia, sem perder a galeria atual como fallback.

O resultado esperado:

- se houver icone personalizado salvo, a Home usa esse arquivo;
- se nao houver, a Home usa o icone escolhido na galeria atual;
- se nada estiver configurado, a Home usa o icone automatico por slug da categoria.

## 4. Escopo

Rotas e superficies afetadas:

- Home, na grade "Compre por categoria" renderizada por `[petshop_categories]`;
- admin de categorias WooCommerce em **Produtos -> Categorias**;
- documentacao de identidade visual e guia operacional para o cliente.

Componentes afetados:

- `Petshop\Core\CategoryIcons`;
- `Petshop\Core\Storefront\CategoryGrid`;
- CSS/admin do seletor de icones;
- CSS da vitrine de categorias no `petshop-theme`, somente se necessario;
- documentacao em `docs/guia-identidade-visual-autelle.md`;
- documentacao em `docs/guia-edicao-home.md`, se a instrucao operacional ficar incompleta sem ela.

Fora do escopo:

- redesenhar a grade de categorias;
- alterar miniaturas WooCommerce;
- criar novos icones oficiais;
- trocar o sistema de categorias;
- upload por bloco Gutenberg na Home;
- editor vetorial ou recorte automatico de arquivos.

## 5. Decisoes de produto

- A galeria atual de icones continua existindo e deve permanecer como fallback.
- O upload livre deve usar a Biblioteca de midia, nao campo de URL manual.
- O campo deve ficar na mesma tela de edicao da categoria, proximo ao campo "Icone da vitrine".
- O cliente deve conseguir selecionar, trocar e remover o icone personalizado sem codigo.
- A miniatura WooCommerce continua separada e documentada como imagem da categoria, nao como icone da Home.

## 6. Regras de arquivo e identidade visual

Formatos aceitos inicialmente:

- SVG;
- PNG;
- WebP, se a Biblioteca de midia local aceitar o formato;
- JPG/JPEG somente como fallback tecnico, mas nao recomendado para icones.

Recomendacao para repasse ao cliente:

- usar SVG sempre que possivel;
- se usar PNG/WebP, preferir fundo transparente;
- proporcao 1:1;
- area util centralizada;
- minimo recomendado: 256 x 256 px para raster;
- traco simples, arredondado e coerente com a linguagem visual Autelle;
- evitar icones com texto pequeno, sombra pesada, foto, degrade complexo ou fundo colorido obrigatorio;
- o icone deve funcionar em uma cor de interface ou em versao monocromatica quando usado como mascara.

**Decisão (implementada):** ícone personalizado renderiza como `<img>` (preserva cores do arquivo). Galeria/automático continua com máscara CSS na cor do tema. Documentado em `docs/guia-identidade-visual-autelle.md`.

## 7. Conteudo administravel e dados

| Item | Origem de edicao | Observacao |
| --- | --- | --- |
| Icone personalizado da vitrine | Produtos -> Categorias -> editar categoria -> Biblioteca de midia | Novo campo administravel por categoria. |
| Icone da galeria atual | Produtos -> Categorias -> editar categoria -> Icone da vitrine | Mantido como fallback. |
| Miniatura da categoria | Produtos -> Categorias -> editar categoria -> Miniatura | Continua sendo imagem da categoria, separada do icone da Home. |
| Nome/descricao da categoria | Produtos -> Categorias | Sem mudanca. |

Metadado proposto:

- `petshop_category_icon_attachment_id` para guardar o attachment ID do icone personalizado.

O plano nao deve salvar caminhos absolutos de arquivo em term meta. A URL deve ser resolvida via attachment ID.

## 8. Implementacao proposta

### Sessao 01 - Campo administrativo

- [x] Adicionar campo "Icone personalizado da vitrine" no formulario de criacao/edicao de `product_cat`.
- [x] Usar Media Library do WordPress para selecionar/remover attachment.
- [x] Exibir preview do icone escolhido no admin.
- [x] Salvar attachment ID em term meta com sanitizacao `absint`.
- [x] Validar capability adequada para edicao de categoria.
- [x] Preservar o campo atual da galeria fixa.

### Sessao 02 - Renderizacao na Home

- [x] Atualizar `CategoryIcons` para resolver primeiro o attachment personalizado.
- [x] Atualizar `CategoryGrid` para renderizar o icone personalizado quando existir.
- [x] Manter fallback para galeria atual e padrao automatico.
- [x] Garantir que categoria sem icone personalizado nao muda visualmente.
- [x] Evitar layout shift quando o arquivo personalizado carregar.

### Sessao 03 - Validacao e fallback

- [x] Validar attachment inexistente, removido ou sem URL publica.
- [x] Validar SVG e PNG transparente.
- [x] Confirmar que remover o icone personalizado restaura a galeria/fallback.
- [x] Confirmar que a miniatura WooCommerce nao interfere no icone da Home.

### Sessao 04 - Documentacao para cliente

- [x] Atualizar `docs/guia-identidade-visual-autelle.md` com regras de criacao e entrega dos icones.
- [x] Incluir no guia a diferenca entre **Miniatura da categoria** e **Icone da vitrine**.
- [x] Registrar formatos, proporcao, tamanho minimo, fundo transparente e recomendacao de SVG.
- [x] Indicar como o cliente deve nomear/exportar arquivos para passar ao responsavel de conteudo.
- [x] Atualizar `docs/guia-edicao-home.md` com o caminho operacional no WordPress, se necessario.

## 9. Criterios de aceite

- O admin de categoria permite selecionar, trocar e remover um icone personalizado pela Biblioteca de midia.
- O icone personalizado aparece na Home para a categoria correspondente.
- Ao remover o icone personalizado, a Home volta para o icone da galeria ou automatico.
- A miniatura WooCommerce continua independente e nao muda o icone da Home.
- Categorias existentes sem upload personalizado continuam funcionando sem migracao manual.
- A grade de categorias nao tem overflow, quebra de alinhamento ou layout shift relevante em mobile e desktop.
- O novo campo usa attachment ID, nao URL fixa.
- Entradas administrativas sao sanitizadas e saidas escapadas.
- O guia de identidade visual explica como preparar os icones para repassar ao cliente.
- O guia deixa claro que a miniatura e o icone da vitrine sao imagens diferentes.

## 10. Validacao

Executar:

- [x] lint PHP nos arquivos alterados do `petshop-core`;
- [x] `npm run validate:changed` (suite `category-icons-022` + docs);
- [x] gate browser da Home (`validate-022-category-icons-browser.mjs`) em 1440/1024/390;
- [x] gate `validate-no-theme-hero-browser.mjs` (tema tocado);
- [x] `npm run test`;
- teste manual em **Produtos -> Categorias** (smoke operacional residual):
  - selecionar SVG (se o ambiente permitir upload);
  - selecionar PNG transparente;
  - remover icone personalizado;
  - confirmar fallback.

Evidencias:

- Home desktop/tablet/mobile em `.local/evidence/022/`: `home-desktop-1440.png`, `home-tablet-1024.png`, `home-mobile-390.png`.
- Gate PHP cobre attachment PNG, prioridade, remocao, fallback e independencia da miniatura.
- Screenshot do admin com o campo de upload: validar manualmente em **Produtos → Categorias** (campo + preview + Media Library).

## 11. Riscos

- Upload de SVG pode depender da permissao/configuracao de seguranca do WordPress. Se o ambiente bloquear SVG, o plano deve documentar o comportamento e aceitar PNG transparente como fallback.
- Renderizar SVG como `<img>` preserva cores do arquivo, mas pode reduzir consistencia visual; renderizar como mascara preserva a cor do tema, mas exige SVG/PNG adequado.
- Icones com detalhes finos podem perder legibilidade no tamanho compacto da Home.
- Arquivos raster sem transparencia podem parecer selos ou fotos, nao icones.

## 12. Entrega manual esperada

Depois de implementar:

1. Abrir **Produtos -> Categorias**.
2. Editar uma categoria visivel na Home.
3. Selecionar um icone personalizado pela Biblioteca de midia.
4. Salvar a categoria.
5. Abrir a Home e confirmar o novo icone.
6. Remover o icone personalizado.
7. Confirmar que o fallback anterior voltou.
8. Revisar `docs/guia-identidade-visual-autelle.md` antes de enviar ao cliente.

## 13. Evidencia de entrega

Implementado em `codex/022-icones-vitrine-upload-livre`.

- `CategoryIcons`: meta `petshop_category_icon_attachment_id`, resolução attachment → galeria → slug, campo Media Library no admin.
- `CategoryGrid`: `<img>` para personalizado; máscara CSS para galeria.
- Docs: identidade visual + guia da Home atualizados.
- Gates: `validate-022-category-icons.php` e `validate-022-category-icons-browser.mjs` integrados em `run-gates.mjs`.
