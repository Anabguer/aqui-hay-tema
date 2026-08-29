#Requires -Version 5.1
# Recuperacion quirurgica regresion visual 2026-08-29: CSS legacy en produccion != Git HEAD
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-visual-regression-recover-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-visual-regression-recover-winscp-$ts.log"
$backupDir = Join-Path $Ctx.LogsDir "rollback-visual-regression-recover-$ts"

Write-DeployLog -LogFile $logFile -Message '=== RECUPERACION QUIRURGICA REGRESION VISUAL CSS ===' -ToHost

Test-AhtPlayV3JsSyntax -Ctx $Ctx
Write-DeployLog -LogFile $logFile -Message 'OK: node --check assets/js/play-v3.js' -ToHost

$deployRels = @(
    'assets/css/play-v3-visual-review.css',
    'assets/css/design-system/screens/inicio-desktop.css',
    'assets/css/play-v3-ficha.css',
    'assets/css/play-v3-app.css',
    'assets/css/play-v3-desktop-shell.css',
    'assets/css/play-v3-organizar.css',
    'assets/css/design-system/legibilidad-global.css',
    'assets/css/play-v3-capas-shell.css',
    'assets/css/play-v3-mensajitos.css',
    'assets/css/play-v3-bloques-residencias.css',
    'assets/css/play-v3-avisos.css',
    'assets/css/play-v3-tutorial-ds.css',
    'assets/css/design-system/screens/inicio-evento-pueblo-mobile.css',
    'assets/css/app.css'
)

$buster = Update-AhtCacheBuster -Ctx $Ctx
Write-DeployLog -LogFile $logFile -Message "Cache-buster: $buster" -ToHost

$files = [System.Collections.Generic.List[object]]::new()
foreach ($rel in $deployRels) {
    if (-not (Add-AhtDeployFile -Files $files -Ctx $Ctx -RelPath $rel)) {
        Write-DeployLog -LogFile $logFile -Message "ERROR: no se pudo empaquetar $rel" -ToHost
        exit 1
    }
}
Enhance-AhtNormalDeployFiles -Files $files -Ctx $Ctx

New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$cfg = Get-Content -LiteralPath $Ctx.CredsPath -Raw -Encoding UTF8 | ConvertFrom-Json
$winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
if (-not $winscp) { throw 'WinSCP.com no encontrado.' }
$openLine = Get-WinScpOpenLine -Cfg $cfg
$getLines = New-Object System.Collections.Generic.List[string]
$getLines.Add($openLine)
$backupRels = $deployRels + @('play.php', 'assets/aht-cache-buster.txt')
foreach ($rel in $backupRels) {
    $remote = "$($Ctx.RemotePath)/$rel"
    $localBak = Join-Path $backupDir ($rel -replace '/', '\')
    $parent = Split-Path -Parent $localBak
    if (-not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
    $getLines.Add("get `"$remote`" `"$localBak`"")
}
$getLines.Add('exit')
$getScript = Join-Path $env:TEMP ("aht-backup-visual-recover-" + [guid]::NewGuid().ToString('N') + '.txt')
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

$base = $Ctx.PublicUrl.TrimEnd('/')
if ($base.EndsWith('/play.php')) { $base = $base.Substring(0, $base.Length - 9) }
$fail = 0
foreach ($rel in $deployRels) {
    $headBlob = git -C $Ctx.RepoRoot rev-parse "HEAD:$rel"
    $url = "$base/$rel"
  try {
        $tmp = Join-Path $env:TEMP "verify-$($rel -replace '[/\\]','_')"
        Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing -TimeoutSec 60 | Out-Null
        $prodBlob = git -C $Ctx.RepoRoot hash-object $tmp
        if ($prodBlob -eq $headBlob) {
            Write-DeployLog -LogFile $logFile -Message "OK hash $rel" -ToHost
        } else {
            Write-DeployLog -LogFile $logFile -Message "FAIL hash $rel HEAD=$headBlob PROD=$prodBlob" -ToHost
            $fail++
        }
        Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    } catch {
        Write-DeployLog -LogFile $logFile -Message "FAIL fetch $rel : $_" -ToHost
        $fail++
    }
}

if ($fail -gt 0) {
    Write-DeployLog -LogFile $logFile -Message "FAIL: $fail archivos sin coincidir con HEAD" -ToHost
    exit 3
}

Write-DeployLog -LogFile $logFile -Message "Backup: $backupDir" -ToHost
Write-DeployLog -LogFile $logFile -Message "OK: regresion visual CSS recuperada | buster=$buster" -ToHost
exit 0
