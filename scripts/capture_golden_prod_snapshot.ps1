#Requires -Version 5.1
<#
.SYNOPSIS
  Captura snapshot GOLDEN PROD del frontend real servido en produccion.
  Solo lectura remota; no modifica juego, Git ni produccion.
#>
param(
    [string]$OutputTimestamp = (Get-Date -Format 'yyyyMMdd-HHmmss')
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
. (Join-Path $PSScriptRoot 'aht_golden_prod_lib.ps1')

$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$repoRoot = Get-AhtGoldenProdRepoRoot -ScriptDir $PSScriptRoot
$snap = New-AhtGoldenProdSnapshotDir -RepoRoot $repoRoot -Timestamp $OutputTimestamp
$logFile = Join-Path $snap.Root 'capture.log'

Write-AhtGoldenProdLog -LogFile $logFile -Message '=== CAPTURA GOLDEN PROD (solo lectura) ===' -ToHost
Write-AhtGoldenProdLog -LogFile $logFile -Message "Destino: $($snap.Root)" -ToHost

$checks = Get-AhtGoldenProdRequiredChecks
$mustHave = @($checks.CoreFiles + $checks.GhostCss + $checks.DivergentCss)

# 1) play.php real desde servidor (WinSCP)
Write-AhtGoldenProdLog -LogFile $logFile -Message 'Descargando play.php real via WinSCP...' -ToHost
$getPlay = Invoke-AhtGoldenProdWinScpGet -Ctx $Ctx -RelPaths @('play.php') -LocalRoot $snap.Files -LogFile $logFile
if (-not $getPlay.Ok) {
    Write-AhtGoldenProdLog -LogFile $logFile -Message "ERROR WinSCP play.php code=$($getPlay.Code)" -ToHost
    exit $getPlay.Code
}

$playPath = Join-Path $snap.Files 'play.php'
if (-not (Test-Path -LiteralPath $playPath)) {
    Write-AhtGoldenProdLog -LogFile $logFile -Message 'ERROR: play.php no descargado' -ToHost
    exit 1
}

# 2) Lista de assets referenciados por play.php real
$fromPlay = Get-AhtGoldenProdCssFromPlayPhp -PlayPhpPath $playPath
$allRels = @(@('play.php') + $fromPlay + @('assets/aht-cache-buster.txt') | Sort-Object -Unique)

Write-AhtGoldenProdLog -LogFile $logFile -Message "Referencias en play.php: $($fromPlay.Count) assets (+ cache-buster)" -ToHost

# 3) Descarga masiva via WinSCP (bytes reales de produccion)
$toFetch = @($allRels | Where-Object { $_ -ne 'play.php' })
Write-AhtGoldenProdLog -LogFile $logFile -Message "Descargando $($toFetch.Count) archivos via WinSCP..." -ToHost
$getAll = Invoke-AhtGoldenProdWinScpGet -Ctx $Ctx -RelPaths $toFetch -LocalRoot $snap.Files -LogFile $logFile
if (-not $getAll.Ok) {
    Write-AhtGoldenProdLog -LogFile $logFile -Message "ERROR WinSCP assets code=$($getAll.Code)" -ToHost
    exit $getAll.Code
}

# 4) Metadata
$busterPath = Join-Path $snap.Files 'assets\aht-cache-buster.txt'
$cacheBuster = if (Test-Path -LiteralPath $busterPath) {
    ([IO.File]::ReadAllText($busterPath)).Trim()
} else { $null }

$deployHead = Get-AhtGoldenProdDeployIntegratedHead -Ctx $Ctx
$cssCount = @($fromPlay | Where-Object { $_ -like 'assets/css/*' }).Count
$jsCount = @($fromPlay | Where-Object { $_ -like 'assets/js/*' }).Count

# 5) Golden visual
$visualRoot = Initialize-AhtGoldenVisualDirs -SnapshotRoot $snap.Root
Copy-AhtExistingGoldenMasters -RepoRoot $repoRoot -VisualRoot $visualRoot
Write-AhtGoldenProdLog -LogFile $logFile -Message "Golden visual: $visualRoot" -ToHost

# 6) Manifest
$manifestPath = New-AhtGoldenProdManifest -SnapshotRoot $snap.Root -FilesRoot $snap.Files -RelPaths $allRels -Meta @{
    ProductionUrl = $Ctx.PublicUrl
    OriginDeployIntegratedHead = $deployHead
    CacheBusterProd = $cacheBuster
    Checks = @{
        expectedCssCount = $cssCount
        expectedJsCount = $jsCount
        ghostCss = $checks.GhostCss
        divergentCss = $checks.DivergentCss
        coreFiles = $checks.CoreFiles
    }
}
Write-AhtGoldenProdLog -LogFile $logFile -Message "Manifest: $manifestPath" -ToHost

# 7) Verificacion
$verify = Test-AhtGoldenProdManifestIntegrity -ManifestPath $manifestPath -FilesRoot $snap.Files
$report = New-Object 'System.Collections.Generic.List[string]'

function Test-AhtPresence {
    param([string[]]$Paths, [string]$Label)
    $ok = $true
    foreach ($rel in $Paths) {
        $p = Join-Path $snap.Files ($rel -replace '/', '\')
        if (-not (Test-Path -LiteralPath $p -PathType Leaf)) {
            $report.Add("$Label FAIL: $rel")
            $ok = $false
        } else {
            $report.Add("$Label OK: $rel")
        }
    }
    return $ok
}

$ghostOk = Test-AhtPresence -Paths $checks.GhostCss -Label 'GHOST'
$divergentOk = Test-AhtPresence -Paths $checks.DivergentCss -Label 'DIVERGENT'
$coreOk = Test-AhtPresence -Paths $checks.CoreFiles -Label 'CORE'
$hashOk = $verify.Ok

$complete = $ghostOk -and $divergentOk -and $coreOk -and $hashOk -and ($cssCount -eq 52)

foreach ($line in $report) {
    Write-AhtGoldenProdLog -LogFile $logFile -Message $line -ToHost
}

$summaryPath = Join-Path $snap.Root 'SNAPSHOT_REPORT.txt'
$summary = @(
    "SNAPSHOT COMPLETO: $(if ($complete) { 'SI' } else { 'NO' })"
    "Ruta: $($snap.Root)"
    "Archivos en manifest: $($allRels.Count)"
    "CSS en play.php: $cssCount (esperado 52)"
    "JS en play.php: $jsCount"
    "Cache-buster PROD: $cacheBuster"
    "HEAD origin/deploy/integrated: $deployHead"
    "4 CSS fantasma: $(if ($ghostOk) { 'OK' } else { 'FAIL' })"
    "14 divergentes: $(if ($divergentOk) { 'OK' } else { 'FAIL' })"
    "play-v3.js: $(if (Test-Path (Join-Path $snap.Files 'assets\js\play-v3.js')) { 'OK' } else { 'FAIL' })"
    "play.php real: OK"
    "Hash verification: $(if ($hashOk) { 'OK' } else { 'FAIL' })"
    ""
) + @($verify.Errors) + @(
    ""
    "Produccion modificada: NO"
    "Archivos juego modificados: NO"
)
[IO.File]::WriteAllText($summaryPath, ($summary -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))

Write-AhtGoldenProdLog -LogFile $logFile -Message "Reporte: $summaryPath" -ToHost
Write-AhtGoldenProdLog -LogFile $logFile -Message "SNAPSHOT COMPLETO: $(if ($complete) { 'SI' } else { 'NO' })" -ToHost

if (-not $complete) { exit 2 }
exit 0
