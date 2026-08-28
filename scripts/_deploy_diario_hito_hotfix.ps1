#Requires -Version 5.1
# Hotfix: DiarioHitoEngine.php requerido por SchemaFields desplegado con regalos
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-diario-hito-hotfix-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-diario-hito-hotfix-winscp-$ts.log"
$rel = 'src/Engine/DiarioHitoEngine.php'
$files = [System.Collections.Generic.List[object]]::new()
if (-not (Add-AhtDeployFile -Files $files -Ctx $Ctx -RelPath $rel)) {
    Write-DeployLog -LogFile $logFile -Message "ERROR: no se pudo empaquetar $rel" -ToHost
    exit 1
}
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $files -WinScpLogPath $winscpLog
if (-not $upload.Ok) {
    Write-DeployLog -LogFile $logFile -Message "ERROR: WinSCP $($upload.Code)" -ToHost
    exit $upload.Code
}
$apiBase = (($Ctx.PublicUrl -replace '/play\.php$', '') -replace '/$', '') + '/api/index.php'
$testPartida = 'part_d88e5094c565e1db'
$invUri = '{0}?action=inventario.listar&partida_id={1}' -f $apiBase, $testPartida
try {
    $resp = Invoke-WebRequest -Uri $invUri -UseBasicParsing -TimeoutSec 60
    Write-DeployLog -LogFile $logFile -Message "PROD inventario.listar HTTP $($resp.StatusCode) $($resp.Content.Substring(0, [Math]::Min(120, $resp.Content.Length)))" -ToHost
    $json = $resp.Content | ConvertFrom-Json
    if ($json.ok -ne $true) { exit 1 }
} catch {
    Write-DeployLog -LogFile $logFile -Message "FAIL prod validation: $_" -ToHost
    exit 1
}
Write-DeployLog -LogFile $logFile -Message 'OK: DiarioHitoEngine hotfix desplegado.' -ToHost
exit 0
