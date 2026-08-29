#Requires -Version 5.1
param(
    [string[]]$Files = @(),
    [switch]$DryRun,
    [switch]$SkipVisualCheck,
    [switch]$SkipGitProdCheck
)

$ErrorActionPreference = 'Stop'

$Helpers = 'W:\_Recursos\GestorProyectos\plantillas\deploy_helpers.ps1'
if (-not (Test-Path -LiteralPath $Helpers)) {
    Write-Host "ERROR: Falta infraestructura incremental: $Helpers"
    exit 1
}
. $Helpers

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $ScriptDir 'deploy_lib_aht.ps1')
. (Join-Path $ScriptDir 'aht_deploy_guards.ps1')

if ($Files.Count -eq 0) {
    Write-Host 'ERROR: DEPLOY_CANONICAL requiere lista explicita de archivos (-Files).'
    Write-Host 'Usa DEPLOY_SURGICAL.bat o:'
    Write-Host '  powershell -File scripts\deploy_surgical.ps1 -Files assets/css/...'
    exit 1
}

$forward = @{
    Files = $Files
    DryRun = $DryRun
    SkipVisualCheck = $SkipVisualCheck
    SkipGitProdCheck = $SkipGitProdCheck
}
& (Join-Path $ScriptDir 'deploy_surgical.ps1') @forward
exit $LASTEXITCODE
