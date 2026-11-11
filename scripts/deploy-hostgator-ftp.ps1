param(
    [string] $HostName = 'br700.hostgator.com.br',
    [string] $UserName = 'lucasarcega@viniciusgarciapaladi1786862104000.0330439.meusitehostgator.com.br',
    [string] $RemoteRoot = '/public_html',
    [switch] $NoSsl
)

$ErrorActionPreference = 'Stop'

$repo = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$log = Join-Path $repo "outputs\deploy-cpanel\ftp-upload-$stamp.log"
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $log) | Out-Null

function Write-Log {
    param([string] $Message)
    $line = "[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $Message
    Write-Host $line
    Add-Content -LiteralPath $log -Value $line
}

function Join-FtpPath {
    param([string] $Base, [string] $Child)
    ($Base.TrimEnd('/') + '/' + $Child.TrimStart('/')) -replace '\\', '/'
}

function New-FtpRequest {
    param(
        [string] $Uri,
        [string] $Method
    )

    $request = [System.Net.FtpWebRequest]::Create($Uri)
    $request.Method = $Method
    $request.Credentials = New-Object System.Net.NetworkCredential($UserName, $password)
    $request.EnableSsl = -not $NoSsl
    $request.UseBinary = $true
    $request.UsePassive = $true
    $request.KeepAlive = $false
    return $request
}

function Ensure-RemoteDirectory {
    param([string] $RemotePath)

    $parts = $RemotePath.Trim('/').Split('/', [System.StringSplitOptions]::RemoveEmptyEntries)
    $current = ''
    foreach ($part in $parts) {
        $current = Join-FtpPath $current $part
        $uri = "ftp://$HostName/$current"
        try {
            $request = New-FtpRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)
            $response = $request.GetResponse()
            $response.Close()
            Write-Log "Criado diretorio /$current"
        } catch [System.Net.WebException] {
            $response = $_.Exception.Response
            if ($response) {
                $status = [string] $response.StatusDescription
                $response.Close()
                if ($status -match 'exist|File exists|550') {
                    continue
                }
            }
            throw
        }
    }
}

function Send-File {
    param(
        [string] $LocalFile,
        [string] $RemoteFile
    )

    $remoteDir = Split-Path -Parent ($RemoteFile -replace '/', '\')
    Ensure-RemoteDirectory -RemotePath ($remoteDir -replace '\\', '/')

    $uri = "ftp://$HostName/$($RemoteFile.TrimStart('/'))"
    $request = New-FtpRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::UploadFile)
    $bytes = [System.IO.File]::ReadAllBytes($LocalFile)
    $request.ContentLength = $bytes.Length
    $stream = $request.GetRequestStream()
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()
    $response = $request.GetResponse()
    $response.Close()
}

function Send-Directory {
    param(
        [string] $LocalDirectory,
        [string] $RemoteDirectory
    )

    $rootFull = (Resolve-Path -LiteralPath $LocalDirectory).Path
    $files = Get-ChildItem -LiteralPath $rootFull -Recurse -File
    $index = 0
    foreach ($file in $files) {
        $index++
        $relative = $file.FullName.Substring($rootFull.Length).TrimStart('\', '/')
        $remote = Join-FtpPath $RemoteDirectory $relative
        Send-File -LocalFile $file.FullName -RemoteFile $remote
        Write-Progress -Activity "Enviando $RemoteDirectory" -Status "$index / $($files.Count): $relative" -PercentComplete (($index / [Math]::Max(1, $files.Count)) * 100)
        if (($index % 25) -eq 0 -or $index -eq $files.Count) {
            Write-Log "Enviados $index de $($files.Count) arquivos para $RemoteDirectory"
        }
    }
    Write-Progress -Activity "Enviando $RemoteDirectory" -Completed
}

$theme = Join-Path $repo 'outputs\deploy-cpanel\20260816-225720\stage\petshop-theme'
$plugin = Join-Path $repo 'outputs\deploy-cpanel\20260816-225720\stage\petshop-core'
if (-not (Test-Path -LiteralPath $theme)) { throw "Tema nao encontrado: $theme" }
if (-not (Test-Path -LiteralPath $plugin)) { throw "Plugin nao encontrado: $plugin" }

Write-Host ''
Write-Host 'Digite a senha FTP/SFTP da HostGator nesta janela. Ela nao sera salva no repositorio.'
$secure = Read-Host 'Senha' -AsSecureString
$password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))

Write-Log "Inicio do upload para $HostName como $UserName"
Write-Log "SSL explicito: $(-not $NoSsl)"
Send-Directory -LocalDirectory $theme -RemoteDirectory (Join-FtpPath $RemoteRoot 'wp-content/themes/petshop-theme')
Send-Directory -LocalDirectory $plugin -RemoteDirectory (Join-FtpPath $RemoteRoot 'wp-content/plugins/petshop-core')
Write-Log 'Upload de tema e plugin concluido.'
Write-Log "Log: $log"
