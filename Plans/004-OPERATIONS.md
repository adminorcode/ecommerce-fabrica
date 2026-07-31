# Operação do storefront — Plano 004

## Taxonomia canônica

O plugin `petshop-core` cria, sem duplicar, as categorias do catálogo aprovado: Promoções, Adesivos, Babador, Bandanas, Colarinhos, Conjuntos, Copa, Festa Junina, Gargantilhas, Gravatas, Inverno, Laços, Laços Adesivos e Penteados. `Laços Adesivos` é sempre filha de `Laços`.

As categorias sazonais recebem `petshop_seasonal=1` e iniciam com `petshop_visible_in_menu=0`; a ordem comercial fica em `petshop_menu_order`. Esses campos são editáveis em **Produtos → Categorias**, sem edição direta de metadados.

## Mapa de navegação

| Região | Conteúdo administrável |
| --- | --- |
| Apoio | Benefício comercial, atendimento e políticas |
| Principal | Categorias canônicas, coleções e item futuro `Personalize` |
| Rodapé | Atendimento, políticas e envio; redes sociais somente após aprovação dos URLs oficiais |
| Rotas WooCommerce | Busca, conta, carrinho e checkout pelos blocos oficiais |

Os menus são criados e atribuídos às localizações **Navegação de apoio**, **Navegação principal** e **Navegação do rodapé**. O menu principal também alimenta as localizações desktop e mobile do Blocksy. O item `Personalize` aponta para uma página informativa até que o plano próprio do configurador seja aprovado.

## Regras das vitrines

| Vitrine | Regra administrável |
| --- | --- |
| Principais categorias | Categorias raiz ordenadas por `petshop_menu_order`; sazonais ocultas respeitam `petshop_visible_in_menu` |
| Kits e conjuntos | Consulta pela categoria `conjuntos`, sem IDs de produto |
| Mais procurados | Ordenação nativa do WooCommerce por popularidade |
| Seleção profissional | Produtos das categorias `adesivos`, `gravatas` e `lacos` |
| Novidades | Ordenação por data, limitada a produtos publicados |
| Coleção sazonal | Produtos de categorias sazonais atualmente marcadas como visíveis; a seção fica oculta quando vazia |
| Avaliações | Somente avaliações de produto aprovadas no WooCommerce; nenhum depoimento é fabricado |
| Relacionados | Relação nativa por categoria, limitada a quatro produtos |

A home é criada com blocos Gutenberg e shortcodes WooCommerce editáveis. A rotina de configuração só cria páginas ausentes; não sobrescreve páginas editoriais existentes.

## Edição de conteúdo

- Home, Sobre, Atendimento, Envios, Personalize e Políticas da loja: editar em **Páginas**, pelo Gutenberg.
- Menus principal, de apoio e do rodapé: editar em **Aparência → Menus**.
- Barra superior, aviso global do produto e descrição SEO da loja: editar em **Aparência → Personalizar → Conteúdo da loja**.
- Nomes, descrições, imagens e visibilidade de categorias: editar em **Produtos → Categorias**.

Os textos no código são somente valores iniciais de instalação. Páginas existentes e conteúdo salvo pelo administrador não são sobrescritos nas atualizações.

O provisionamento roda somente em uma requisição administrativa, protegido por lock. Atualizações não redefinem home, menus, logo, paleta ou opções já configuradas; migrações editoriais pontuais usam uma versão própria e preservam o conteúdo existente.

Textos funcionais curtos da interface, como rótulos de filtro e mensagens de estado, permanecem no código com suporte à tradução. Textos comerciais, institucionais, SEO e chamadas editoriais têm uma origem administrável no WordPress.

## Checklist de conteúdo antes de publicar

- Cada produto publicado tem SKU, preço maior que zero, categoria, imagem principal, texto alternativo e descrição comercial mínima.
- Vitrines de kits, mais vendidos, seleção profissional e sazonais usam consultas por categoria/coleção; nunca IDs fixos.
- Produtos sem estoque ou ocultos não entram em vitrines, exceto decisão editorial documentada.
- Hero, prova social, WhatsApp, metas e políticas recebem conteúdo original aprovado; nenhuma referência da Moda Bicho é reutilizada.

## Validação em runtime

Após iniciar o Docker Desktop, execute `docker compose up --build -d` e valide: categoria, busca, produto simples e sem estoque, cupom, quantidade, carrinho, checkout e conta. Faça ainda a revisão com teclado, zoom de 200%, viewport mobile e o console do navegador.

O contrato repetível de conteúdo e configuração está em `scripts/validate-storefront.php`.

Por padrão, o contrato atua como health check e aceita ordem/visibilidade alteradas pelo cliente. Use `PETSHOP_VALIDATE_DEFAULTS=1` apenas em uma instalação descartável para conferir os valores iniciais de fábrica.

Enquanto o problema de CRLF do inicializador registrado no Plano 003 não for corrigido, a validação local pode usar um contêiner WordPress temporário ligado aos mesmos volumes, normalizando o script somente dentro desse contêiner. Essa recuperação não altera o script pertencente ao outro plano.
