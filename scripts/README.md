# Scripts

Scripts repetíveis de bootstrap, validação, importação de dados de demonstração e automação local.

Scripts devem ser:

- idempotentes quando possível;
- documentados;
- seguros para execução local;
- independentes de segredos versionados.

## Caminho no contêiner

O serviço `cli` monta esta pasta em **`/var/www/html/scripts`** (read-only).

```powershell
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/validate-storefront.php
```

## Orquestrador (smoke PHP)

```powershell
# Bash (Git Bash / Linux / CI)
bash scripts/run-gates.sh

# PowerShell
./scripts/run-gates.ps1

# npm (delega ao script acima)
npm run validate
```

Opções:

- `--browser` — inclui todos os gates Playwright no contêiner `node`
- `--pdp` / `--cart` — executa o gate isolado de PDP ou de adicionar ao carrinho
- `--skip-provision` — pula migrações/seed antes dos validators

Sequência padrão:

1. `StorefrontCatalog::maybeEnsureCategories()`
2. `seed-storefront-placeholders.php`
3. `StorefrontExperience::maybeEnsureStorefront()`
4. validators PHP

**Git Bash (Windows):** `run-gates.sh` exporta `MSYS_NO_PATHCONV=1` para preservar paths `/var/www/html/scripts` no contêiner.

## Deploy (HostGator/cPanel)

```powershell
npm run prepare:deploy
```

Gera `outputs/deploy-cpanel/<stamp>/` com `wp-content/` copiável (tema e plugin do worktree, uploads do volume) e `petshop-db.sql`. Remove vendor de desenvolvimento e regenera o Composer no pacote com `dump-autoload --no-dev --optimize`; o script falha se `autoload_*.php` ainda citar `myclabs`, `phpunit/phpunit` ou `deep-copy`. Não rode esse dump no plugin do worktree. O caminho absoluto é impresso ao final e gravado em `WHERE.txt`. Skill: `.cursor/skills/preparar-deploy/`.

## Catálogo por plano

| Script | Plano | Descrição |
|--------|-------|-----------|
| `seed-storefront-placeholders.php` | 004b | Seed idempotente de produtos demo |
| `validate-storefront.php` | 004 | Taxonomia, Home, menus e blocos |
| `audit-storefront-content.php` | Operacional | Qualidade editorial dos produtos publicados |
| `validate-004b.php` | 004b | Manifesto XLSX/JSON e vitrine |
| `validate-storefront.php` | 004 | Smoke geral |
| `validate-005-session-01.php` | 005 S01 | Header comercial |
| `validate-005-session-02.php` | 005 S02 | Hero e benefícios |
| `validate-005-session-01-browser.mjs` | 005 S01 | Browser: header |
| `validate-005-session-02-browser.mjs` | 005 S02 | Browser: hero |
| `validate-005-catalog-layout-browser.mjs` | 005 | Browser: filtro lateral |
| `validate-005-pdp-browser.mjs` | 008 | Browser: PDP (preço, CTA e aviso) |
| `validate-005-cart-browser.mjs` | 008 | Browser: adicionar ao carrinho/minicarrinho |
| `validate-005-session-02-editor.mjs` | 005 S02 | Editor Gutenberg |
| `validate-009-cart-checkout-browser.mjs` | 009 | Browser: cart/checkout tokens e a11y |
| `validate-023-footer.php` | 023 | Rodapé: settings Customizer e render preenchido/vazio |
| `validate-023-footer-browser.mjs` | 023 | Browser: composição do rodapé (4 colunas, redes na marca, ícones, 1440/390) |
| `validate-024-home-campaigns-carousel.php` | 024 | Carrossel promocional: limite 3, duração por imagem, persistência |
| `validate-024-home-campaigns-carousel-browser.mjs` | 024 | Browser: overlay de setas/indicadores, 44px, 1440/1024/768/390 |
| `validate-026-checkout.php` | 026 | Checkout: dados salvos da conta, ponte para campos BR, ViaCEP unico e PDP sem ViaCEP |
| `validate-026-checkout-browser.mjs` | 026 | Browser: prefill PF/PJ, visitante sem vazamento, ViaCEP, Store API, erro e viewports 1440×900/390×844 |
| `validate-027-shipping-hub.php` | 027 | Calculadora PDP como hub WooCommerce: taxas ativas sem filtro, preço sem entidades, prazo e preview sem persistir destino |
| `validate-027-shipping-hub-browser.mjs` | 027 | Browser: uma UI de frete na PDP, widgets extras ocultos em PDP/carrinho/checkout e CEP no checkout |
| `validate-036-versioned-shipping-dependencies.php` | 036 | Melhor Envio e base brasileira versionados: plugins ativos, vendors presentes e aviso de dependencia ausente |
| `validate-039-cart-qty.php` | 039 | Carrinho: JS do plugin brasileiro ausente e CEP proprio presente |
| `validate-039-cart-qty-browser.mjs` | 039 | Browser: quantidade persiste depois do CEP no carrinho |
| `validate-041-shipping-destination.php` | 041 | Cotação por CEP, recarga autenticada, edição parcial/limpeza, conta preservada e autofill único |
| `validate-041-shipping-preview.php` | 041 | Prévia isolada, quantidade e limiar nativo de frete grátis; taxas sintéticas |
| `validate-041-shipping-selection-cache.php` | 041 | Via `wp eval` com require: cache pago preservado após sessão tardia, ofertas grátis antigas descartadas, regras ativas e restauração do callback |
| `validate-041-delivery-performance-browser.mjs` | 041 | Três trocas reais no carrinho/checkout, duração Store API/UI e avisos React; ViaCEP induzido, cotações reais |
| `validate-041-block-persistence.php` | 041 | Migração idempotente de inner block e preservação de texto/remoção pelo cliente |
| `validate-041-checkout-address-browser.mjs` | 041 | Layout da referência, campos únicos, ViaCEP de sessão/digitação/reload, preservação de endereço salvo, cobrança independente e remontagem sem erros React; respostas induzidas em 1440/390 |
| `validate-041-cart-delivery-browser.mjs` | 041 | CEP na coluna de totais, métodos reais iguais à PDP, preços exibidos, teclado, seleção, falha/retry e reload em desktop/mobile |
| `validate-041-cart-concurrency-browser.mjs` | 041 | Uma requisição física, resposta antiga descartada, quantidade/CEP recentes, retorno 1→3→1 e 1→3→1→3; visitante/autenticado em 1440/390, GET financeiro independente |
| `validate-041-cart-request-coordinator.mjs` | 041 | Abort nativo sem liberar escrita anterior, fila abortada descartada, erros/nonce obsoletos, retry antigo, retorno de intenção e checkout fora do escopo |
| `validate-041-cart-estimate.mjs` | 041 | Estimativa em unidades monetárias inteiras, overflow, moeda/precisão, zero e preservação dos totais oficiais |
| `validate-041-cart-performance-browser.mjs` | 041 | Cinco cliques geram uma escrita; operação lenta seguida apenas do desejo final; estimativa em até100ms, preço legível e GET financeiro independente. Teclado sem blur, CTA imediato, foco/loading/reduced-motion, clamp/estoque, sold-individually, cupom,503/retry e remoção; desktop/mobile visitante/autenticado. Baseline controlada via PETSHOP_041_BASELINE=1 |
| `validate-041-cart-consistency-browser.mjs` | 041 | Quantidade/CEP sob latência, duas linhas, visitante/autenticado em desktop/mobile; GET independente |
| `validate-041-address-races-browser.mjs` | 041 | Respostas ViaCEP induzidas fora de ordem, edição manual, erro/retry e CEP incompleto |
| `validate-041-quote-preference.mjs` | 041 | Consumo único, resposta antiga, expiração, logout, subdiretório, storage bloqueado e identificador em HTTP |
| `validate-041-product-quote-browser.mjs` | 041 | Quantidades 1/2/3, resposta antiga, taxa vazia e erros 429/503/nonce/offline/timeout; transporte induzido no harness |
| `validate-041-cart-operations.mjs` | 041 | Contrato isolado do adaptador público: timeout pendente, geração antiga, erro propagado, retry e serialização; não substitui consistência browser |
| `validate-041-editor-browser.mjs` | 041 | Editor sintético: texto, troca de mídia/alt, reordenação, remoção e persistência após salvar/recarregar; cleanup de página, mídia e conta |
| `validate-041-native-commerce.php` | 041 | Cupons fixo/percentual, impostos incl/excl, totais oficiais, dois pacotes com entrega/retirada zero, estoque e vendido individualmente; métodos sintéticos isolados |
| `validate-041-runner-cleanup.mjs` | 041 | Funções reais dos runners: erro original, falhas de cleanup/restauração Bash e provisionamento parcial JS; chamadas externas simuladas |
| `validate-035-menu-dropdown.php` | 035 | Menu comercial: markup de dropdown, dois pais com filhos, persistência e rodapé depth 1 |
| `validate-035-menu-dropdown-browser.mjs` | 035 | Browser: hover 1440, accordion 390, segundo pai e item sem filhos |
| `validate-037-cart-auto-update-browser.mjs` | 037 | Browser: `/carrinho` e mini-cart atualizam quantidade e valores finais sem refresh, com Store API como fonte oficial |
| `validate-030.php` | 030 | Frase da confirmação: setting, filtros WC/bloco, persistência e HTML do pedido recebido |
| `validate-030-order-received-browser.mjs` | 030 | Browser: Checkout Block + página de pedido recebido com a frase do Personalizar |
| `test-004b-persistence.php` | 004b | Persistência editorial |
| `test-005-session-01-persistence.php` | 005 S01 | Persistência header |
| `test-005-session-02-persistence.php` | 005 S02 | Persistência hero |
| `test-005-session-02-migrations.php` | 005 S02 | Migrações Home |

## Seed 004b (manual)

Ordem segura: taxonomia → seed → Home → validação.

```powershell
docker compose --profile tools run --rm --no-deps cli wp eval 'Petshop\Core\StorefrontCatalog::maybeEnsureCategories();'
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/seed-storefront-placeholders.php
docker compose --profile tools run --rm --no-deps cli wp eval 'Petshop\Core\StorefrontExperience::maybeEnsureStorefront();'
npm run validate
```

O seed preserva SKUs existentes. Produtos criados recebem `_petshop_placeholder_004b=1`.

## Variáveis de ambiente

| Variável | Uso |
|----------|-----|
| `PETSHOP_EXPECTED_BLOGNAME` | `validate-storefront.php` — assert opcional do nome da loja |
| `PETSHOP_VALIDATE_DEFAULTS` | `1` — valida defaults iniciais de taxonomia/menu |
| `PETSHOP_BASE_URL` | Browser gates (default `http://localhost:8888`) |

## CI

Pull requests executam `bash scripts/run-gates.sh` e PHPUnit via `.github/workflows/validate.yml`. A auditoria editorial não bloqueia esses testes: execute `npm run validate -- --content-audit --skip-provision` para verificar o cadastro atual sem reprovisioná-lo.
O workflow manual `.github/workflows/browser-gates.yml` executa os gates Playwright
no contêiner e publica evidências quando falhar.

## Legado

`bootstrap-wp-env.mjs` antigo (`wp-env`) — use `npm run bootstrap:legacy` apenas durante migração do Plano 003.
