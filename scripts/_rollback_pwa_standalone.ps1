#Requires -Version 5.1
param([Parameter(Mandatory)][string]$BackupDir)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
if (-not (Test-Path -LiteralPath $BackupDir)) { throw "No existe backup: $BackupDir" }
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "rollback-pwa-standalone-$ts.log"
$files = [System.Collections.Generic.List[object]]::new()
foreach ($rel in @('play.php', '.htaccess')) {
    $local = Join-Path $BackupDir ($rel -replace '/', '\')
    if (Test-Path -LiteralPath $local) {
        [void]$files.Add(@{ Local = $local; Remote = "$($Ctx.RemotePath)/$rel"; Rel = $rel })
    }
}
if ($files.Count -eq 0) { throw 'Backup sin play.php ni .htaccess' }
$winscpLog = Join-Path $Ctx.LogsDir "rollback-pwa-winscp-$ts.log"
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $files -WinScpLogPath $winscpLog
if (-not $upload.Ok) { exit $upload.Code }
Write-DeployLog -LogFile $logFile -Message "Rollback parcial OK ($($upload.Count) archivos). Eliminar manifest.webmanifest y sw.js en servidor si procede." -ToHost
Write-DeployLog -LogFile $logFile -Message "Ver ROLLBACK_MANIFEST.txt en $BackupDir" -ToHost
exit 0
