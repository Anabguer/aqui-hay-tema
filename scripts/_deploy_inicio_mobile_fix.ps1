#Requires -Version 5.1
# Deploy: fix móvil 5db541a + desktop fa92ff6
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-inicio-mobile-fix-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-inicio-mobile-fix-winscp-$ts.log"

Write-DeployLog -LogFile $logFile -Message '=== DEPLOY: Inicio mobile fix 5db541a ===' -ToHost

$buster = Update-AhtCacheBuster -Ctx $Ctx
Write-DeployLog -LogFile $logFile -Message "Cache-buster: $buster" -ToHost

$deployRels = @(
    'play.php',
    'assets/css/play-v3-desktop-shell.css',
    'assets/css/design-system/screens/inicio-desktop.css',
    'assets/css/play-v3-inicio-override.css',
    'assets/aht-cache-buster.txt'
)

$pick = Get-AhtFilesFromUserInput -Ctx $Ctx -RelPaths $deployRels
if ($pick.Missing.Count -gt 0) {
    foreach ($m in $pick.Missing) { Write-DeployLog -LogFile $logFile -Message "MISSING: $m" -ToHost }
    exit 1
}

Write-AhtDeployFileList -LogFile $logFile -Files $pick.Files -ToHost
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $pick.Files -WinScpLogPath $winscpLog
if (-not $upload.Ok) { exit $upload.Code }

$base = $Ctx.PublicUrl.TrimEnd('/')
$allOk = $true

try {
    $php = (Invoke-WebRequest -Uri "$base/play.php" -UseBasicParsing -TimeoutSec 60).Content
    if ($php -match '\$ahtUi') { Write-DeployLog -LogFile $logFile -Message 'OK: play.php PHP buster' -ToHost }
    else { Write-DeployLog -LogFile $logFile -Message 'FAIL: play.php sin PHP buster' -ToHost; $allOk = $false }
    $enc = $php.IndexOf('data-encursos-block')
    $leftEnd = $php.IndexOf('</aside>', $php.IndexOf('game-left'))
    if ($enc -gt $leftEnd) { Write-DeployLog -LogFile $logFile -Message 'OK: enc/prox en game-right' -ToHost }
    else { Write-DeployLog -LogFile $logFile -Message 'FAIL: enc en game-left' -ToHost; $allOk = $false }
} catch {
    Write-DeployLog -LogFile $logFile -Message "FAIL play.php: $($_.Exception.Message)" -ToHost
    $allOk = $false
}

$css = (Invoke-WebRequest -Uri "$base/assets/css/play-v3-inicio-override.css?v=$buster" -UseBasicParsing -TimeoutSec 60).Content
if ($css.Contains('INICIO-MOBILE-TILES-RESTORE-v2')) {
    Write-DeployLog -LogFile $logFile -Message 'OK: TILES-RESTORE en override' -ToHost
} else {
    Write-DeployLog -LogFile $logFile -Message 'FAIL: TILES-RESTORE' -ToHost
    $allOk = $false
}

$desk = (Invoke-WebRequest -Uri "$base/assets/css/design-system/screens/inicio-desktop.css?v=$buster" -UseBasicParsing -TimeoutSec 60).Content
if ($desk.Contains('grid-column: 1 / 3 !important') -and $desk.Contains('Mensajitos+Vecinos apilados')) {
    Write-DeployLog -LogFile $logFile -Message 'OK: desktop apilado' -ToHost
} else {
    Write-DeployLog -LogFile $logFile -Message 'FAIL: desktop layout markers' -ToHost
    $allOk = $false
}

Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
if ($allOk) {
    Write-DeployLog -LogFile $logFile -Message "DEPLOY OK | buster=$buster" -ToHost
    exit 0
}
Write-DeployLog -LogFile $logFile -Message 'DEPLOY subido; validacion fallos' -ToHost
exit 2
