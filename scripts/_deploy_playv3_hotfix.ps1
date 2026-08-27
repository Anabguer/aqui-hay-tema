#Requires -Version 5.1
# HOTFIX: play-v3.js syntax error (brace extra MENTES iter2)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-playv3-hotfix-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-playv3-hotfix-winscp-$ts.log"
$backupDir = Join-Path $Ctx.LogsDir "rollback-playv3-hotfix-$ts"

Write-DeployLog -LogFile $logFile -Message '=== HOTFIX play-v3.js SYNTAX ===' -ToHost

Test-AhtPlayV3JsSyntax -Ctx $Ctx
Write-DeployLog -LogFile $logFile -Message 'OK: node --check assets/js/play-v3.js' -ToHost

$buster = Update-AhtCacheBuster -Ctx $Ctx
Write-DeployLog -LogFile $logFile -Message "Cache-buster: $buster" -ToHost

$deployRels = @(
    'assets/js/play-v3.js',
    'assets/aht-cache-buster.txt'
)

$files = [System.Collections.Generic.List[object]]::new()
foreach ($rel in $deployRels) {
    if (-not (Add-AhtDeployFile -Files $files -Ctx $Ctx -RelPath $rel)) {
        Write-DeployLog -LogFile $logFile -Message "ERROR: no se pudo empaquetar $rel" -ToHost
        exit 1
    }
}

# Backup produccion
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
$getScript = Join-Path $env:TEMP ("aht-backup-playv3-" + [guid]::NewGuid().ToString('N') + '.txt')
[IO.File]::WriteAllText($getScript, ($getLines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))
Write-DeployLog -LogFile $logFile -Message "Backup remoto -> $backupDir" -ToHost
& $winscp /ini=nul "/log=$winscpLog" /script=$getScript
Remove-Item $getScript -Force -ErrorAction SilentlyContinue

$rollback = @"
# Rollback play-v3 hotfix $ts
# Restaurar desde: $backupDir
`$ErrorActionPreference = 'Stop'
. '$($PSScriptRoot -replace "'", "''")\deploy_lib_aht.ps1'
`$Ctx = Initialize-AhtDeployContext -ScriptDir '$($PSScriptRoot -replace "'", "''")'
`$files = [System.Collections.Generic.List[object]]::new()
foreach (`$rel in @('assets/js/play-v3.js','assets/aht-cache-buster.txt')) {
    `$local = Join-Path '$($backupDir -replace "'", "''")' (`$rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath `$local)) { throw "Falta backup `$rel" }
    [void]`$files.Add(@{ Local = `$local; Remote = "`$(`$Ctx.RemotePath)/`$rel"; Rel = `$rel })
}
`$upload = Invoke-AhtWinScpUpload -Ctx `$Ctx -Files `$files -WinScpLogPath '$($winscpLog -replace "'", "''")'
if (-not `$upload.Ok) { exit `$upload.Code }
"@
Set-Content -LiteralPath (Join-Path $backupDir 'ROLLBACK.ps1') -Value $rollback -Encoding UTF8

Write-AhtDeployFileList -LogFile $logFile -Files $files -ToHost
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $files -WinScpLogPath $winscpLog
if (-not $upload.Ok) {
    Write-DeployLog -LogFile $logFile -Message "ERROR: WinSCP $($upload.Code)" -ToHost
    exit $upload.Code
}

Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
$jsUrl = "$($Ctx.PublicUrl)/assets/js/play-v3.js?v=$buster"
try {
    $resp = Invoke-WebRequest -Uri $jsUrl -UseBasicParsing -TimeoutSec 60
    Write-DeployLog -LogFile $logFile -Message "OK play-v3.js HTTP $($resp.StatusCode)" -ToHost
    $tmpJs = Join-Path $env:TEMP "play-v3-prod-$ts.js"
    [IO.File]::WriteAllText($tmpJs, $resp.Content)
    & node --check $tmpJs 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-DeployLog -LogFile $logFile -Message 'OK: play-v3.js servido pasa node --check' -ToHost
    } else {
        Write-DeployLog -LogFile $logFile -Message 'AVISO: play-v3.js servido NO pasa node --check' -ToHost
    }
    Remove-Item $tmpJs -Force -ErrorAction SilentlyContinue
} catch {
    Write-DeployLog -LogFile $logFile -Message "AVISO: no se pudo validar $jsUrl : $_" -ToHost
}

Write-DeployLog -LogFile $logFile -Message "Rollback: $(Join-Path $backupDir 'ROLLBACK.ps1')" -ToHost
Write-DeployLog -LogFile $logFile -Message 'OK: HOTFIX play-v3 desplegado.' -ToHost
exit 0
