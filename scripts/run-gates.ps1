param(
    [switch]$Browser,
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
    Write-Host '==> provisionando taxonomia e storefront'
    Invoke-WpCli eval 'Petshop\Core\StorefrontCatalog::maybeEnsureCategories(); Petshop\Core\StorefrontExperience::maybeEnsureStorefront();'
    Write-Host '==> seed demonstrativo 004b (idempotente)'
    Invoke-EvalFile 'seed-storefront-placeholders.php'
}

Invoke-EvalFile 'validate-storefront.php'
Invoke-EvalFile 'validate-004b.php'
Invoke-EvalFile 'validate-005-session-01.php'
Invoke-EvalFile 'validate-005-session-02.php'

if ($Browser) {
    Write-Host '==> browser gates (host)'
    node (Join-Path $PSScriptRoot 'validate-005-session-01-browser.mjs')
    node (Join-Path $PSScriptRoot 'validate-005-session-02-browser.mjs')
    node (Join-Path $PSScriptRoot 'validate-005-catalog-layout-browser.mjs')
}

Write-Host 'run-gates: all PHP gates passed'
