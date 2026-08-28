#Requires -Version 5.1
# Reparación regresión producción 2026-08-28: corazón dual-view + partida antes de Pasar el rato
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-regression-repair-$ts.log"
Write-DeployLog -LogFile $logFile -Message '=== DEPLOY: regression repair (corazon dual-view + partida pasar rato) ===' -ToHost

Test-AhtPlayV3JsSyntax -Ctx $Ctx
$buster = Update-AhtCacheBuster -Ctx $Ctx
Write-DeployLog -LogFile $logFile -Message "Cache-buster: $buster" -ToHost

$rels = @(
  'play.php',
  'assets/js/play-v3.js',
  'assets/aht-cache-buster.txt'
)

$files = [System.Collections.Generic.List[object]]::new()
foreach ($rel in $rels) {
  $local = Join-Path $Ctx.RepoRoot ($rel -replace '/', '\')
  if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
    Write-DeployLog -LogFile $logFile -Message "FAIL missing $rel" -ToHost
    exit 2
  }
  $hash = (Get-FileHash -LiteralPath $local -Algorithm SHA256).Hash
  Write-DeployLog -LogFile $logFile -Message "SHA256 $rel : $hash" -ToHost
  [void]$files.Add(@{ Local = $local; Remote = "$($Ctx.RemotePath)/$rel"; Rel = $rel })
}

$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $files -WinScpLogPath ($logFile + '.winscp')
if (-not $upload.Ok) { exit $upload.Code }

$base = $Ctx.PublicUrl.TrimEnd('/')
if ($base.EndsWith('/play.php')) { $base = $base.Substring(0, $base.Length - 9) }
$js = (Invoke-WebRequest -Uri "$base/assets/js/play-v3.js?v=$buster" -UseBasicParsing -TimeoutSec 60).Content
$php = (Invoke-WebRequest -Uri "$base/play.php" -UseBasicParsing -TimeoutSec 60).Content
if ($js -notmatch 'corazonAguaPairs') { Write-DeployLog -LogFile $logFile -Message 'FAIL js corazonAguaPairs' -ToHost; exit 2 }
if ($js -notmatch 'recuperarPartidaIdPerdida') { Write-DeployLog -LogFile $logFile -Message 'FAIL js recuperarPartidaIdPerdida' -ToHost; exit 2 }
if ($js -notmatch 'pasarRatoBtns') { Write-DeployLog -LogFile $logFile -Message 'FAIL js pasarRatoBtns' -ToHost; exit 2 }
if ($php -notmatch 'data-corazon-fill') { Write-DeployLog -LogFile $logFile -Message 'FAIL play.php corazon svg' -ToHost; exit 2 }
if ($php -notmatch 'cotilleos-scrapbook-v1') { Write-DeployLog -LogFile $logFile -Message 'FAIL play.php cotilleos css' -ToHost; exit 2 }

Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
Write-DeployLog -LogFile $logFile -Message "OK regression-repair | buster=$buster" -ToHost
exit 0
