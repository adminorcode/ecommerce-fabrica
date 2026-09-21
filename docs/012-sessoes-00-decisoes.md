# Decisões da Sessão 00 — Plano 012

Documento operacional gerado na implementação. Valores abaixo são a base do MVP até o
cliente da loja revisar medidas físicas e o proprietário do código decidir sobre GPL.

## 1. Licença do `petshop-core`

**Decisão atual (provisória, aguarda confirmação do proprietário):**

- `petshop-core` permanece `proprietary` (composer + cabeçalho).
- A solução **não** será descrita como open source neste MVP.
- Fabric.js (MIT) e demais dependências JS serão listadas em
  `wp-content/plugins/petshop-core/third-party-notices.txt` quando empacotadas.
- Migração para `GPL-2.0-or-later` (WordPress-compatible) exige aprovação explícita
  do proprietário e atualização de `LICENSE`, `composer.json` e cabeçalho do plugin.

## 2. Sem plugin/SaaS de terceiro

- Personalização é módulo próprio `Petshop\Core\Personalization`.
- Fabric.js empacotado localmente (sem CDN).
- Nenhum plugin de personalização de terceiros será instalado.

## 3. Storage privado

| Ambiente | Caminho no contêiner | Volume Compose |
| --- | --- | --- |
| Desenvolvimento / runtime | `/var/petshop-personalizations` | `petshop_personalization_data` |
| Testes | `/var/petshop-personalizations` | `test_petshop_personalization_data` |

- Fora do document root (`/var/www/html`).
- Override opcional por constante `PETSHOP_PERSONALIZATION_STORAGE` (não versionar segredos).
- Feature recusa habilitar se o path estiver sob webroot ou não for gravável.

## 4. Política de retenção (defaults)

| Tipo | Prazo padrão | Observação |
| --- | --- | --- |
| Rascunhos (`draft`) sem uso | 7 dias | Cron + WP-CLI dry-run |
| Itens em `cart` abandonados | 14 dias | Após última atualização |
| Pedidos cancelados/refundados | 90 dias | Arquivos marcados; não exclusão imediata |
| Pedidos concluídos (originais) | 365 dias | PNG de produção segue a mesma retenção até config global |
| Pedidos concluídos (produção) | 365 dias | Configurável em Settings |

Desativação do plugin **não** apaga dados. Desinstalação preserva por padrão.

## 5. Especificação de produção (exemplos MVP)

Valores iniciais para cadastro de produto; o lojista ajusta na aba Personalização.

| Produto | Área imprimível (mm) | DPI alvo | Mockup | Notas |
| --- | --- | --- | --- | --- |
| Bandana | 280 × 280 | 150 | Attachment WooCommerce | Texto central; máscara circular/quadrada conforme arte |
| Laço / lacinho | 80 × 50 | 150 | Attachment WooCommerce | Área irregular; máscara PNG com alpha |
| Adesivo | 100 × 100 | 300 | Attachment WooCommerce | Imagem do comprador; validar megapixels vs mm/DPI |

Fórmula de pixels: `px = mm / 25.4 × DPI`.

## 6. Responsáveis

- Operação de fila / downloads: papéis com `manage_petshop_personalizations`
  (administrator, shop_manager por migração).
- Backup: volume `petshop_personalization_data` + dump SQL das tabelas
  `wp_petshop_personalizations` e `wp_petshop_personalization_files`.
