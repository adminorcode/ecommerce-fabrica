param(
    [switch]$Browser,
    [switch]$Pdp,
    [switch]$Cart,
    [switch]$SkipProvision
)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not (Test-Path '.env')) {
    Write-Error 'Arquivo .env ausente. Copie .env.example para .env.'
}

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
}

Invoke-EvalFile 'validate-storefront.php'
Invoke-EvalFile 'validate-004b.php'
Invoke-EvalFile 'validate-005-session-01.php'
Invoke-EvalFile 'validate-005-session-02.php'

if ($Browser) {
    Write-Host '==> browser gates (container)'
    foreach ($script in @('validate-005-session-01-browser.mjs', 'validate-005-session-02-browser.mjs', 'validate-005-catalog-layout-browser.mjs')) {
        docker compose --profile tools run --rm node node "/workspace/scripts/$script"
        if ($LASTEXITCODE -ne 0) { throw "browser gate $script falhou" }
    }
}

if ($Pdp -or $Browser) {
    docker compose --profile tools run --rm node node /workspace/scripts/validate-005-pdp-browser.mjs
    if ($LASTEXITCODE -ne 0) { throw 'browser gate PDP falhou' }
}

if ($Cart -or $Browser) {
    docker compose --profile tools run --rm node node /workspace/scripts/validate-005-cart-browser.mjs
    if ($LASTEXITCODE -ne 0) { throw 'browser gate carrinho falhou' }
}

Write-Host 'run-gates: all PHP gates passed'
