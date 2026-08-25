#Requires -Version 5.1
# Sube las capturas de revision del piloto DS a Hostalia (playtest).
# Uso: powershell -File dev\subir_capturas.ps1
# Las credenciales se leen de W:\anabel\deploy\hostalia.publish.local.json (nunca inline).
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

$srcDir = Join-Path $PSScriptRoot 'screenshots-ds-piloto-inicio'
$remoteDir = '/juegos/aqui-hay-tema-playtest/dev/capturas-ds'
$files = 'inicio-mobile-393-v3.1.png','inicio-mobile-393-v3.1-estados.png','inicio-desktop-smoke-1440.png'

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add($openLine)
$lines.Add('mkdir ' + $remoteDir)
foreach ($f in $files) {
  $local = Join-Path $srcDir $f
  if (-not (Test-Path -LiteralPath $local)) { throw "Falta $local (genera capturas con dev\propuestas\.. o ds_piloto_capturas.js)" }
  $lines.Add('put -nopermissions "' + $local + '" ' + $remoteDir + '/' + $f)
}
$lines.Add('exit')
foreach ($l in $lines) { if ($l -match "`r|`n") { throw 'Linea partida' } }
$scriptFile = Join-Path $env:TEMP 'winscp-capturas-ds.script'
$log = Join-Path $env:TEMP 'winscp-capturas-ds.log'
[System.IO.File]::WriteAllLines($scriptFile, $lines)
& $winscp /ini=nop /log=$log /loglevel=0 /script=$scriptFile | Out-Null
$code = $LASTEXITCODE
Remove-Item -LiteralPath $scriptFile -Force -ErrorAction SilentlyContinue
if ($code -ne 0) { throw "WinSCP exit $code (revisa $log)" }
'UPLOAD OK -> ' + $remoteDir
