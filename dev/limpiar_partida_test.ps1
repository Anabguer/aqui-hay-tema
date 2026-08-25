#Requires -Version 5.1
# Limpieza: elimina la partida TEST de produccion.
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
$id = (Get-Content 'W:\juegos\aqui-hay-tema\dev\screenshots-ds-piloto-inicio\partida_test_id.txt' -Raw).Trim()
$partFile = '/juegos/aqui-hay-tema/data/partidas/' + $id + '.json'
$lines = New-Object System.Collections.Generic.List[string]
$lines.Add($openLine)
$lines.Add('rm "' + $partFile + '"')
$lines.Add('exit')
$scriptFile = Join-Path $env:TEMP 'winscp-limpieza.script'
$log = Join-Path $env:TEMP 'winscp-limpieza.log'
[System.IO.File]::WriteAllLines($scriptFile, $lines)
& $winscp /ini=nop /log=$log /loglevel=0 /script=$scriptFile | Out-Null
$code = $LASTEXITCODE
Remove-Item -LiteralPath $scriptFile -Force -ErrorAction SilentlyContinue
if ($code -ne 0) { throw "WinSCP exit $code" }
Write-Output "PARTIDA TEST ELIMINADA: $partFile"
