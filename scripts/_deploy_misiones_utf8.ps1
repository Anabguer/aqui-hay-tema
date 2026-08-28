#Requires -Version 5.1
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-misiones-utf8-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-misiones-utf8-winscp-$ts.log"

Write-DeployLog -LogFile $logFile -Message '=== DEPLOY SELECTIVO MISIONES UTF-8 + ICONOS ===' -ToHost
Test-AhtPlayV3JsSyntax -Ctx $Ctx

$deployRels = @(
    'api/handlers/PartidaHandler.php',
    'src/Engine/TutorialPrimerosPasos.php',
    'assets/js/play-v3.js',
    'assets/aht-cache-buster.txt'
)
$input = Get-AhtFilesFromUserInput -Ctx $Ctx -RelPaths $deployRels
if ($input.Missing.Count -gt 0) {
    throw "Faltan archivos: $($input.Missing -join ', ')"
}
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $input.Files -WinScpLogPath $winscpLog
if (-not $upload.Ok) { exit $upload.Code }
Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
Write-DeployLog -LogFile $logFile -Message 'DEPLOY MISIONES UTF-8 OK' -ToHost
exit 0
