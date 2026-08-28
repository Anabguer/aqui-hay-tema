#Requires -Version 5.1
# Deploy selectivo: backend Inventario/Regalos (inventario.listar + regalo.entregar)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-inventario-regalos-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-inventario-regalos-winscp-$ts.log"
$backupDir = Join-Path $Ctx.LogsDir "rollback-inventario-regalos-$ts"

Write-DeployLog -LogFile $logFile -Message '=== DEPLOY SELECTIVO INVENTARIO/REGALOS BACKEND ===' -ToHost

$deployRels = @(
    'api/index.php',
    'api/handlers/RegalosHandler.php',
    'src/Engine/InventarioEngine.php',
    'src/Engine/RegaloEngine.php',
    'src/Engine/RegaloHints.php',
    'src/Engine/RegaloRecompensaEngine.php',
    'src/Engine/CatalogStore.php',
    'src/Engine/GameError.php',
    'data/catalogos/regalos.json'
) + @(
    Get-ChildItem -LiteralPath (Join-Path $Ctx.RepoRoot 'assets/play-v3/regalos') -Filter '*.png' |
        ForEach-Object { 'assets/play-v3/regalos/' + $_.Name }
)

$files = [System.Collections.Generic.List[object]]::new()
foreach ($rel in $deployRels) {
    if (-not (Add-AhtDeployFile -Files $files -Ctx $Ctx -RelPath $rel)) {
        Write-DeployLog -LogFile $logFile -Message "ERROR: no se pudo empaquetar $rel" -ToHost
        exit 1
    }
}

# Backup produccion (solo archivos que existen en remoto)
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$cfg = Get-Content -LiteralPath $Ctx.CredsPath -Raw -Encoding UTF8 | ConvertFrom-Json
$winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
if (-not $winscp) { throw 'WinSCP.com no encontrado.' }
$openLine = Get-WinScpOpenLine -Cfg $cfg
$getLines = New-Object System.Collections.Generic.List[string]
$getLines.Add($openLine)
foreach ($rel in $deployRels) {
    $remote = "$($Ctx.RemotePath)/$rel"
    $localBak = Join-Path $backupDir ($rel -replace '/', '\')
    $parent = Split-Path -Parent $localBak
    if (-not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
    $getLines.Add("get `"$remote`" `"$localBak`"")
}
$getLines.Add('exit')
$getScript = Join-Path $env:TEMP ("aht-backup-inventario-regalos-$ts.txt")
[IO.File]::WriteAllText($getScript, ($getLines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))
Write-DeployLog -LogFile $logFile -Message "Backup remoto -> $backupDir" -ToHost
& $winscp /ini=nul "/log=$winscpLog.backup" /script=$getScript
Remove-Item $getScript -Force -ErrorAction SilentlyContinue

Write-AhtDeployFileList -LogFile $logFile -Files $files -ToHost
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $files -WinScpLogPath $winscpLog
if (-not $upload.Ok) {
    Write-DeployLog -LogFile $logFile -Message "ERROR: WinSCP $($upload.Code)" -ToHost
    exit $upload.Code
}

Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile

# Validacion API inventario.listar en produccion (partida lab, no Neni)
$apiBase = "$($Ctx.PublicUrl)/api/index.php"
$testPartida = 'part_d88e5094c565e1db'
$testUrl = "$apiBase?action=inventario.listar&partida_id=$testPartida"
try {
    $resp = Invoke-WebRequest -Uri $testUrl -UseBasicParsing -TimeoutSec 60
    Write-DeployLog -LogFile $logFile -Message "PROD inventario.listar HTTP $($resp.StatusCode)" -ToHost
    $json = $resp.Content | ConvertFrom-Json
    if ($json.ok -eq $true -and $null -ne $json.inventario) {
        Write-DeployLog -LogFile $logFile -Message 'OK: inventario.listar JSON valido en produccion' -ToHost
    } else {
        Write-DeployLog -LogFile $logFile -Message "FAIL: respuesta inesperada: $($resp.Content.Substring(0, [Math]::Min(200, $resp.Content.Length)))" -ToHost
        exit 1
    }
} catch {
    Write-DeployLog -LogFile $logFile -Message "FAIL validacion produccion: $_" -ToHost
    exit 1
}

# partida.refresh sin regresion
$refreshUrl = "$apiBase?action=partida.refresh&partida_id=$testPartida"
try {
    $r2 = Invoke-WebRequest -Uri $refreshUrl -UseBasicParsing -TimeoutSec 90
    Write-DeployLog -LogFile $logFile -Message "PROD partida.refresh HTTP $($r2.StatusCode)" -ToHost
} catch {
    Write-DeployLog -LogFile $logFile -Message "AVISO: partida.refresh check: $_" -ToHost
}

Write-DeployLog -LogFile $logFile -Message "Backup: $backupDir" -ToHost
Write-DeployLog -LogFile $logFile -Message "OK: $($upload.Count) archivo(s) — inventario/regalos backend." -ToHost
exit 0
