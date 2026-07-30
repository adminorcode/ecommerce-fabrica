$requiredVersion = [version]'2.32.2'
$versionOutput = docker compose version --short 2>$null

if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($versionOutput)) {
  throw 'Docker Compose nao esta disponivel. Instale ou inicie o Docker Desktop.'
}

$versionMatch = [regex]::Match($versionOutput, '(\d+\.\d+\.\d+)')

if (-not $versionMatch.Success) {
  throw "Nao foi possivel interpretar a versao do Docker Compose: $versionOutput"
}

$installedVersion = [version]$versionMatch.Groups[1].Value

if ($installedVersion -lt $requiredVersion) {
  throw "Docker Compose $requiredVersion ou superior e obrigatorio para Compose Watch (sync+exec). Versao encontrada: $installedVersion. Atualize o Docker Desktop antes de iniciar a stack."
}

Write-Output "Docker Compose ${installedVersion}: OK"
