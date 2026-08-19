param(
    [switch]$Browser,
    [switch]$Pdp,
    [switch]$Cart,
    [switch]$ContentAudit,
    [switch]$SkipProvision
)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not (Test-Path '.env')) {
    Write-Error 'Arquivo .env ausente. Copie .env.example para .env.'
}

node scripts/validate-014-docs-and-tokens.mjs
if ($LASTEXITCODE -ne 0) { throw 'validate-014-docs-and-tokens falhou' }

function Invoke-WpCli {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Args)
    docker compose --profile tools run --rm --no-deps cli wp @Args
    if ($LASTEXITCODE -ne 0) { throw "wp $($Args -join ' ') falhou com código $LASTEXITCODE" }
}

function Invoke-EvalFile {
    param([string]$Script)
    Write-Host "==> wp eval-file $Script"
    docker compose --profile tools run --rm --no-deps cli wp eval-file "/var/www/html/scripts/$Script"
    if ($LASTEXITCODE -ne 0) { throw "eval-file $Script falhou" }
}

if (-not $SkipProvision) {
    Write-Host '==> provisionando taxonomia'
    Invoke-WpCli eval 'Petshop\Core\StorefrontCatalog::maybeEnsureCategories();'
    Write-Host '==> seed demonstrativo 004b (idempotente)'
    Invoke-EvalFile 'seed-storefront-placeholders.php'
    Write-Host '==> provisionando storefront'
    Invoke-WpCli eval 'Petshop\Core\StorefrontExperience::maybeEnsureStorefront();'
    Write-Host '==> fixtures administraveis do Plano 013'
    Invoke-EvalFile 'seed-013-catalog-samples.php'
    Write-Host '==> fixtures personalizáveis do Plano 012'
    Invoke-EvalFile 'seed-012-personalizable-products.php'
    Write-Host '==> produtos Animal Republik autorizados'
    Invoke-EvalFile 'seed-animal-republik-launches.php'
    Write-Host '==> vitrines comerciais com Ver tudo'
    Invoke-EvalFile 'sync-commercial-page-catalog-links.php'
}

Invoke-EvalFile 'validate-storefront.php'
Invoke-EvalFile 'validate-005-session-01.php'
Invoke-EvalFile 'validate-005-session-02.php'
Invoke-EvalFile 'test-004b-persistence.php'
Invoke-EvalFile 'test-005-session-01-persistence.php'
Invoke-EvalFile 'test-005-session-02-persistence.php'
Invoke-EvalFile 'test-013-persistence.php'
Invoke-EvalFile 'validate-013-hpos.php'
Invoke-EvalFile 'validate-013-security.php'
Invoke-EvalFile 'validate-014-identity-campaigns.php'
Invoke-EvalFile 'validate-015-support-section.php'
Invoke-EvalFile 'validate-016-product-grid.php'
Invoke-EvalFile 'validate-018-commercial-pages.php'
Invoke-EvalFile 'validate-animal-republik-products.php'
Invoke-EvalFile 'validate-012-personalization.php'
Invoke-EvalFile 'smoke-012-order-flow.php'

if ($ContentAudit) {
    Invoke-EvalFile 'validate-004b.php'
    Invoke-EvalFile 'audit-storefront-content.php'
}

if ($Browser -or $Pdp -or $Cart) {
    $wordpressUrlLine = Get-Content '.env' | Where-Object { $_ -match '^WORDPRESS_URL=' } | Select-Object -First 1
    $expectedPublicUrl = if ($wordpressUrlLine) { ($wordpressUrlLine -split '=', 2)[1].Trim() } else { 'http://localhost:8888' }
    $expectedPublicUri = [Uri]$expectedPublicUrl
    if (-not $expectedPublicUri.IsLoopback) {
        throw 'WORDPRESS_URL deve ser loopback para executar os gates browser locais.'
    }
    $originalHome = (docker compose --profile tools run --rm --no-deps cli wp option get home).Trim()
    if ($originalHome -eq 'http://wordpress') {
        Write-Host '==> recuperando URL publica deixada por gate browser interrompido'
        Invoke-WpCli option update home $expectedPublicUrl
        Invoke-WpCli option update siteurl $expectedPublicUrl
        Invoke-WpCli cache flush
        $originalHome = $expectedPublicUrl
    }
    $publicUri = [Uri]$originalHome
    if (-not $publicUri.IsLoopback) {
        throw 'Os gates browser que isolam a URL do Compose so podem alterar uma instalacao local.'
    }
    $originalSiteUrl = (docker compose --profile tools run --rm --no-deps cli wp option get siteurl).Trim()
    try {
        Invoke-WpCli option update home 'http://wordpress'
        Invoke-WpCli option update siteurl 'http://wordpress'
        Invoke-WpCli cache flush

        if ($Browser) {
            Write-Host '==> browser gates (container)'
            foreach ($script in @('validate-005-session-01-browser.mjs', 'validate-005-session-02-browser.mjs', 'validate-005-catalog-layout-browser.mjs', 'validate-013-browser.mjs', 'validate-016-product-grid-browser.mjs', 'validate-018-commercial-pages-browser.mjs', 'validate-012-personalizer-browser.mjs', 'validate-no-theme-hero-browser.mjs')) {
                docker compose --profile tools run --rm node node "/workspace/scripts/$script"
                if ($LASTEXITCODE -ne 0) { throw "browser gate $script falhou" }
            }
            docker compose --profile tools run --rm node node /workspace/scripts/validate-016-product-grid-editor.mjs
            if ($LASTEXITCODE -ne 0) { throw 'editor gate Plano 016 falhou' }
        }

        if ($Pdp -or $Browser) {
            docker compose --profile tools run --rm node node /workspace/scripts/validate-005-pdp-browser.mjs
            if ($LASTEXITCODE -ne 0) { throw 'browser gate PDP falhou' }
        }

        if ($Cart -or $Browser) {
            docker compose --profile tools run --rm node node /workspace/scripts/validate-005-cart-browser.mjs
            if ($LASTEXITCODE -ne 0) { throw 'browser gate carrinho falhou' }
        }
    } finally {
        Invoke-WpCli option update home $originalHome
        Invoke-WpCli option update siteurl $originalSiteUrl
        Invoke-WpCli cache flush
    }
}

Write-Host 'run-gates: all PHP gates passed'
