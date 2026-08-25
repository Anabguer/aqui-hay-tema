#Requires -Version 5.1
# Deploy rapido: inicio.css + buster nuevo.
$ErrorActionPreference = 'Stop'
$AnabelDeploy = 'W:\anabel\deploy'
$cfg = Get-Content (Join-Path $AnabelDeploy 'hostalia.publish.local.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$winscp = if ($cfg.HOSTALIA_WINSCP_PATH -and (Test-Path $cfg.HOSTALIA_WINSCP_PATH)) { $cfg.HOSTALIA_WINSCP_PATH }
          elseif (Test-Path "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com") { "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com" }
          elseif (Test-Path "${env:ProgramFiles(x86)}\WinSCP\WinSCP.com") { "${env:ProgramFiles(x86)}\WinSCP\WinSCP.com" }
          elseif (Test-Path "$env:ProgramFiles\WinSCP\WinSCP.com") { "$env:ProgramFiles\WinSCP\WinSCP.com" }
          else { throw 'WinSCP.com no encontrado' }
$user = [string]$cfg.HOSTALIA_USER
$pass = [string]$cfg.HOSTALIA_PASSWORD
$hostName = [string]$cfg.HOSTALIA_HOST
$port = if ($cfg.HOSTALIA_PORT) { [int]$cfg.HOSTALIA_PORT } else { 21 }
$ftpSecureOpt = ''
if ($cfg.PSObject.Properties.Name -contains 'HOSTALIA_FTP_SECURE') { $ftpSecureOpt = [string]$cfg.HOSTALIA_FTP_SECURE.Trim().ToLowerInvariant() }
$protocol = [string]$cfg.HOSTALIA_PROTOCOL.Trim().ToLowerInvariant()
$scheme = if ($protocol -eq 'sftp') { 'sftp' } elseif ($protocol -eq 'ftpes' -or ($protocol -eq 'ftp' -and $ftpSecureOpt -in @('explicit','explicitssl','tls'))) { 'ftpes' } else { 'ftp' }
$sb = New-Object System.Text.StringBuilder
[void]$sb.Append('open ').Append($scheme).Append('://').Append($user).Append(':').Append($pass).Append('@').Append($hostName).Append(':').Append($port).Append('/')
if ($scheme -eq 'ftpes') { [void]$sb.Append(' -certificate=*') }
$openLine = $sb.ToString()

$r = 'W:\juegos\aqui-hay-tema'
$base = '/juegos/aqui-hay-tema'
$busterNew = 'v3-20260825-' + (Get-Date -Format 'HHmmss')
$busterFile = Join-Path $env:TEMP 'aht-cache-buster.txt'
[System.IO.File]::WriteAllText($busterFile, $busterNew + [Environment]::NewLine, (New-Object System.Text.UTF8Encoding($false)))

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add($openLine)
$lines.Add('put -nopermissions "' + (Join-Path $r 'assets\css\design-system\screens\inicio.css') + '" ' + "$base/assets/css/design-system/screens/inicio.css")
$lines.Add('put -nopermissions "' + $busterFile + '" ' + "$base/aht-cache-buster.txt")
$lines.Add('exit')
foreach ($l in $lines) { if ($l -match "`r|`n") { throw 'Linea partida' } }
$scriptFile = Join-Path $env:TEMP 'winscp-rapido.script'
$log = Join-Path $env:TEMP 'winscp-rapido.log'
[System.IO.File]::WriteAllLines($scriptFile, $lines)
& $winscp /ini=nop /log=$log /loglevel=0 /script=$scriptFile | Out-Null
$code = $LASTEXITCODE
Remove-Item -LiteralPath $scriptFile -Force -ErrorAction SilentlyContinue
if ($code -ne 0) { throw "WinSCP exit $code" }
Write-Output "DEPLOY RAPIDO OK - buster $busterNew"
