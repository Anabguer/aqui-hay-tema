#Requires -Version 5.1
<#
.SYNOPSIS
  Checkpoint PRODUCCION ESTABLE: Git HEAD = bytes HTTP reales (LF-normalizado).
#>
param(
    [switch]$CaptureGolden,
    [switch]$SkipVisual
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $ScriptDir 'deploy_lib_aht.ps1')
. (Join-Path $ScriptDir 'aht_deploy_guards.ps1')

$Ctx = Initialize-AhtDeployContext -ScriptDir $ScriptDir
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "checkpoint-prod-estable-$ts.log"

Write-DeployLog -LogFile $logFile -Message '=== CHECKPOINT PRODUCCION ESTABLE ===' -ToHost

try {
    $guard = Assert-AhtCanonicalDeployGuards -Ctx $Ctx -LogFile $logFile
} catch {
    Write-DeployLog -LogFile $logFile -Message "CHECKPOINT FALLIDO (guardas): $($_.Exception.Message)" -ToHost
    exit 1
}

$gitProd = Invoke-AhtGitProdCompare -Ctx $Ctx -LogFile $logFile -FailOnMismatch
if (-not $gitProd.Ok) {
    Write-DeployLog -LogFile $logFile -Message 'CHECKPOINT FALLIDO: Git != Produccion' -ToHost
    exit 1
}

if ($CaptureGolden) {
    Write-DeployLog -LogFile $logFile -Message 'Capturando Golden Master desde produccion...' -ToHost
    $cap = Invoke-AhtVisualRegression -Ctx $Ctx -LogFile $logFile -CaptureOnly -FailOnMismatch
} elseif (-not $SkipVisual) {
    Write-DeployLog -LogFile $logFile -Message 'Regresion visual vs Golden Master...' -ToHost
    $vis = Invoke-AhtVisualRegression -Ctx $Ctx -LogFile $logFile -FailOnMismatch
    if (-not $vis.Ok) {
        Write-DeployLog -LogFile $logFile -Message 'CHECKPOINT FALLIDO: regresion visual' -ToHost
        exit 1
    }
}

$checkpointPath = Join-Path $Ctx.LogsDir 'checkpoint-prod-estable-latest.json'
$payload = @{
    checkedAt = (Get-Date).ToUniversalTime().ToString('o')
    head = $guard.Head
    remoteRef = $guard.RemoteRef
    remoteHead = $guard.RemoteHead
    worktree = $guard.WorktreePath
    gitProdOk = $true
    visualOk = (-not $SkipVisual)
    goldenCaptured = [bool]$CaptureGolden
    logFile = $logFile
} | ConvertTo-Json -Depth 4
[IO.File]::WriteAllText($checkpointPath, $payload + "`n", (New-Object System.Text.UTF8Encoding $false))

Write-DeployLog -LogFile $logFile -Message "CHECKPOINT OK -> $checkpointPath" -ToHost
Write-DeployLog -LogFile $logFile -Message 'BLINDAJE: Git = Produccion verificado.' -ToHost
exit 0
