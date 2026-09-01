#Requires -Version 5.1
<#
.SYNOPSIS
  Deploy quirurgico AHT con guardas canonicas y lista explicita de archivos.

.DESCRIPTION
  Flujo: fetch -> guardas -> diff -> backup -> revalidar origin -> publicar
         -> regresion visual -> Git=Prod.

  NO hace checkout/reset/clean/pull automaticos.
#>
param(
    [Parameter(Mandatory = $true)]
    [string[]]$Files,
    [switch]$DryRun,
    [switch]$SkipVisualCheck,
    [switch]$SkipGitProdCheck, [switch]$AutoConfirm
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $ScriptDir 'deploy_lib_aht.ps1')
. (Join-Path $ScriptDir 'aht_deploy_guards.ps1')

$Ctx = Initialize-AhtDeployContext -ScriptDir $ScriptDir
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-surgical-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-surgical-winscp-$ts.log"
$backupDir = Join-Path $Ctx.LogsDir "rollback-surgical-$ts"

Write-DeployLog -LogFile $logFile -Message '=== AHT DEPLOY QUIRURGICO ===' -ToHost
Write-DeployLog -LogFile $logFile -Message "Worktree: $($Ctx.RepoRoot)" -ToHost

$guardStart = Assert-AhtCanonicalDeployGuards -Ctx $Ctx -LogFile $logFile
$headAtStart = $guardStart.RemoteHead

$deployRels = @($Files | ForEach-Object { $_.Trim().Replace('\', '/').TrimStart('/') } | Where-Object { $_ })
if ($deployRels.Count -eq 0) {
    Write-DeployLog -LogFile $logFile -Message 'ABORT: sin archivos en -Files' -ToHost
    exit 1
}

Write-DeployLog -LogFile $logFile -Message '--- Expandiendo dependencias engine ---' -ToHost
$deployRels = Expand-AhtDeployEngineDependencies -RequestedFiles $deployRels -RepoRoot $Ctx.RepoRoot -LogFile $logFile

Write-DeployLog -LogFile $logFile -Message '--- Lista explicita solicitada ---' -ToHost
foreach ($rel in $deployRels) {
    Write-DeployLog -LogFile $logFile -Message "  $rel" -ToHost
    $local = Join-Path $Ctx.RepoRoot ($rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
        Write-DeployLog -LogFile $logFile -Message "ABORT: no existe localmente: $rel" -ToHost
        exit 1
    }
}

$hasPlayV3 = $deployRels -contains 'assets/js/play-v3.js'
if ($hasPlayV3) {
    Test-AhtPlayV3JsSyntax -Ctx $Ctx
    Write-DeployLog -LogFile $logFile -Message 'OK: node --check play-v3.js' -ToHost
}

$needsPlayPhp = $false
foreach ($rel in $deployRels) {
    if ($rel -match '^(assets/|api/|src/)') { $needsPlayPhp = $true; break }
}

if (-not $DryRun) {
    $buster = Update-AhtCacheBuster -Ctx $Ctx
    Write-DeployLog -LogFile $logFile -Message "Cache-buster: $buster" -ToHost
} else {
    Write-DeployLog -LogFile $logFile -Message 'DRY-RUN: cache-buster no se modifica.' -ToHost
}

$packed = [System.Collections.Generic.List[object]]::new()
foreach ($rel in $deployRels) {
    if (-not (Add-AhtDeployFile -Files $packed -Ctx $Ctx -RelPath $rel)) {
        Write-DeployLog -LogFile $logFile -Message "ABORT: no se pudo empaquetar $rel" -ToHost
        exit 1
    }
}
if ($needsPlayPhp) {
    Add-AhtDeployFile -Files $packed -Ctx $Ctx -RelPath 'play.php'
}
if (-not $DryRun) {
    Add-AhtDeployFile -Files $packed -Ctx $Ctx -RelPath $Ctx.CacheBusterRel
}

Assert-AhtDeployFileListExplicit -RequestedFiles $deployRels -PackedFiles $packed -LogFile $logFile

Write-DeployLog -LogFile $logFile -Message "--- Archivos a subir ($($packed.Count)) ---" -ToHost
Write-AhtDeployFileList -LogFile $logFile -Files $packed -ToHost

if ($DryRun) {
    Write-DeployLog -LogFile $logFile -Message 'DRY-RUN: no se conecta al servidor.' -ToHost
    exit 0
}

if (-not $AutoConfirm -and -not (Read-DeployYesNo 'Confirmas DEPLOY QUIRURGICO a PRODUCCION?')) {
    Write-DeployLog -LogFile $logFile -Message 'Cancelado.' -ToHost
    exit 0
}

[void](Assert-AhtCanonicalDeployGuards -Ctx $Ctx -LogFile $logFile -ExpectedHeadAtStart $headAtStart)

$backupRels = @($packed | ForEach-Object { [string]$_.Rel })
Invoke-AhtRemoteBackup -Ctx $Ctx -RelPaths $backupRels -BackupDir $backupDir -LogFile $logFile -WinScpLogPath $winscpLog

$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $packed -WinScpLogPath $winscpLog
if (-not $upload.Ok) {
    Write-DeployLog -LogFile $logFile -Message "ERROR: WinSCP codigo $($upload.Code)" -ToHost
    exit $upload.Code
}

Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile

if (-not $SkipVisualCheck) {
    Write-DeployLog -LogFile $logFile -Message 'Regresion visual post-deploy...' -ToHost
    $vis = Invoke-AhtVisualRegression -Ctx $Ctx -LogFile $logFile -FailOnMismatch
}

if (-not $SkipGitProdCheck) {
    Write-DeployLog -LogFile $logFile -Message 'Checkpoint Git=Prod post-deploy...' -ToHost
    [void](Invoke-AhtGitProdCompare -Ctx $Ctx -LogFile $logFile -FailOnMismatch)
}

Write-DeployLog -LogFile $logFile -Message 'OK: deploy quirurgico completado.' -ToHost
Write-DeployLog -LogFile $logFile -Message "Backup: $backupDir" -ToHost
Write-DeployLog -LogFile $logFile -Message "Log: $logFile" -ToHost
exit 0
