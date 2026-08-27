# Arquitetura do petshop-core

O `petshop-core` concentra as regras próprias da loja e deixa o tema responsável pela apresentação. Desde o Plano 007, o bootstrap usa o autoload PSR-4 do Composer e delega cada domínio a uma classe coesa.

## Mapa de responsabilidades

Antes da refatoração, `StorefrontExperience` reunia mais de 3.400 linhas de migração, provisionamento, catálogo, shortcodes, SEO e administração. A fachada compatível agora tem cerca de 130 linhas e encaminha as APIs legadas para estes módulos:

| Domínio | Componentes principais |
|---|---|
| Bootstrap e ciclo de vida | `Plugin`, `Lifecycle`, `Cli\MigrateCommand` |
| Migrações da Home | `Migration\HomeMigrator` e traits de schemas/editorial |
| Provisionamento | `Provisioning\StorefrontProvisioner` e traits de páginas/menus |
| Catálogo | `Storefront\CatalogFilter`, `CategoryGrid` |
| Vitrines e shortcodes | `ProductShortcodes`, `ProductGridShortcodes`, `ProductShowcaseView` |
| SEO e conteúdo auxiliar | `Storefront\SeoMeta`, `SupportContent` |
| Administração | `Admin\Customizer`, `Admin\CategoryTermMeta` |
| Defaults globais | `Settings\DefaultSettings` |

Os arquivos PascalCase na raiz de `includes/` são bridges PSR-4 para classes legadas ainda armazenadas em `class-*.php`. Eles preservam compatibilidade pública sem `require_once` manual no bootstrap.

## Migração e provisionamento

`HomeMigrator` mantém o schema atual e o registry de migrações. As transformações são idempotentes, preservam blocos e valores editoriais reconhecidos e persistem a versão somente depois de uma execução bem-sucedida. Falhas expõem um código estável na option de estado e enviam o detalhe técnico ao log.

A ativação agenda a migração, que roda no fallback administrativo seguinte, ou manualmente com:

```sh
npm run wp -- petshop migrate
```

O provisionador cria apenas a estrutura inicial administrável (páginas, menu, associações e conteúdo Gutenberg inicial). Reexecuções não substituem conteúdo que o cliente já editou.

Na desativação, o agendamento e o lock técnico são limpos. O `uninstall.php` remove somente options técnicas próprias; páginas, posts, mídia, menus e `theme_mods` são preservados para impedir perda editorial.

## Inventário de conteúdo administrável

| Rotas/área | Conteúdo | Onde editar |
|---|---|---|
| Home | Hero, benefícios, vitrines e campanhas, incluindo imagens e texto alternativo | Páginas → Home, no canvas Gutenberg; imagens pela Biblioteca de mídia |
| Cabeçalho global | Barra promocional (texto e link opcional), links e rótulos de atendimento, conta e desejos | Aparência → Personalizar → Barra promocional; Conteúdo da loja |
| Confirmação de pedido | Frase “pedido recebido” no topo da página `/finalizar-compra/order-received/` | Aparência → Personalizar → Conteúdo da loja |
| Rodapé global | Descrição, WhatsApp, horário, CNPJ, endereço, redes e formas de pagamento | Aparência → Personalizar → Rodapé da loja |
| Loja e categorias | Descrição SEO da loja; nome, descrição, ícone e imagem das categorias | Aparência → Personalizar → Petshop; Produtos → Categorias |
| Produto | Dados comerciais, galeria, alt, descrição e aviso global de compra | Produtos; Biblioteca de mídia; Aparência → Personalizar → Petshop |
| Navegação | Destinos do menu comercial | Aparência → Menus |

Os defaults globais estão centralizados em `Settings\DefaultSettings`. Eles são apenas valores iniciais/fallbacks: valores salvos no Customizer continuam prevalecendo após migração ou reprovisionamento.

## Compatibilidade

Não há breaking change de interface para o usuário final. As fachadas estáticas existentes continuam disponíveis, o plugin declara compatibilidade com HPOS e Cart/Checkout Blocks, e o tema consome os defaults do plugin quando ele está ativo.
