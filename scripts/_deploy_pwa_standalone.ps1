#Requires -Version 5.1
# Deploy selectivo PWA standalone — manifest + sw + play.php + .htaccess
param(
    [string]$TargetCommit = 'HEAD',
    [switch]$Deploy,
    [switch]$ReportOnly
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-pwa-standalone-$ts.log"
$prodDir = Join-Path $Ctx.LogsDir "prod-snapshot-pwa-$ts"
$staging = Join-Path $env:TEMP "aht-head-pwa-$ts"

$candidates = @(
    'manifest.webmanifest',
    'sw.js',
    'play.php',
    '.htaccess',
    'assets/brand/pwa-icon-192.png',
    'assets/brand/pwa-icon-512.png'
)

function Export-GitFile([string]$Repo, [string]$Commit, [string]$Rel, [string]$Dest) {
    $parent = Split-Path -Parent $Dest
    if (-not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = 'git'
    $psi.Arguments = "-C `"$Repo`" show `"${Commit}:${Rel}`""
    $psi.RedirectStandardOutput = $true
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true
    $psi.StandardOutputEncoding = [System.Text.Encoding]::UTF8
    $proc = [System.Diagnostics.Process]::Start($psi)
    $content = $proc.StandardOutput.ReadToEnd()
    $proc.WaitForExit()
    if ($proc.ExitCode -ne 0) { return $false }
    [IO.File]::WriteAllText($Dest, $content, (New-Object System.Text.UTF8Encoding $false))
    return $true
}

$resolvedHead = (& git -C $Ctx.RepoRoot rev-parse $TargetCommit 2>&1 | Out-String).Trim()
Write-DeployLog -LogFile $logFile -Message "=== PWA STANDALONE DELTA PROD vs $resolvedHead ===" -ToHost

if (Test-Path -LiteralPath $staging) { Remove-Item -LiteralPath $staging -Recurse -Force }
New-Item -ItemType Directory -Path $staging -Force | Out-Null
$headFiles = @()
foreach ($rel in $candidates) {
    $dest = Join-Path $staging ($rel -replace '/', '\')
    if (Export-GitFile -Repo $Ctx.RepoRoot -Commit $resolvedHead -Rel $rel -Dest $dest) {
        $headFiles += $rel
    } else {
        Write-DeployLog -LogFile $logFile -Message "ERROR: no HEAD $rel" -ToHost
        exit 1
    }
}

New-Item -ItemType Directory -Path $prodDir -Force | Out-Null
$cfg = Get-Content -LiteralPath $Ctx.CredsPath -Raw -Encoding UTF8 | ConvertFrom-Json
$winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
if (-not $winscp) { throw 'WinSCP.com no encontrado.' }
$openLine = Get-WinScpOpenLine -Cfg $cfg
$getLines = New-Object System.Collections.Generic.List[string]
$getLines.Add($openLine)
foreach ($rel in @('manifest.webmanifest', 'sw.js', 'play.php', '.htaccess')) {
    $remote = "$($Ctx.RemotePath)/$rel"
    $local = Join-Path $prodDir ($rel -replace '/', '\')
    $parent = Split-Path -Parent $local
    if (-not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
    $getLines.Add("get `"$remote`" `"$local`"")
}
$getLines.Add('exit')
$getScript = Join-Path $env:TEMP ("aht-prod-pwa-" + [guid]::NewGuid().ToString('N') + '.txt')
[IO.File]::WriteAllText($getScript, ($getLines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))
& $winscp /ini=nul /script=$getScript 2>&1 | Out-Null
Remove-Item $getScript -Force -ErrorAction SilentlyContinue

$rows = New-Object System.Collections.Generic.List[object]
$toDeploy = New-Object System.Collections.Generic.List[string]
foreach ($rel in $headFiles) {
    $headPath = Join-Path $staging ($rel -replace '/', '\')
    $prodPath = Join-Path $prodDir ($rel -replace '/', '\')
    $headHash = (Get-FileHash -LiteralPath $headPath -Algorithm SHA256).Hash
    $hadProd = Test-Path -LiteralPath $prodPath -PathType Leaf
    $prodHash = if ($hadProd) { (Get-FileHash -LiteralPath $prodPath -Algorithm SHA256).Hash } else { $null }
    $needs = (-not $hadProd) -or ($prodHash -ne $headHash)
    if ($needs) { [void]$toDeploy.Add($rel) }
    $rows.Add([pscustomobject]@{
        archivo = $rel
        prod = if ($hadProd) { 'SI' } else { 'NO' }
        hash_prod = if ($hadProd) { $prodHash.Substring(0, 16) } else { 'AUSENTE' }
        hash_head = $headHash.Substring(0, 16)
        subir = if ($needs) { 'SI' } else { 'NO' }
    })
}

$manifestReport = Join-Path $Ctx.LogsDir "pwa-standalone-delta-$ts.txt"
@(
    "HEAD objetivo: $resolvedHead",
    "Prod snapshot: $prodDir",
    "",
    ($rows | Format-Table -AutoSize | Out-String),
    "SUBIR ($($toDeploy.Count)):",
    ($toDeploy -join "`n")
) | Set-Content -LiteralPath $manifestReport -Encoding UTF8
Write-DeployLog -LogFile $logFile -Message (Get-Content -LiteralPath $manifestReport -Raw) -ToHost

if ($ReportOnly -or -not $Deploy) {
    Write-DeployLog -LogFile $logFile -Message "Manifiesto: $manifestReport (use -Deploy para publicar)" -ToHost
    exit 0
}

if ($toDeploy.Count -eq 0) {
    Write-DeployLog -LogFile $logFile -Message 'OK: prod ya coincide con HEAD en archivos PWA.' -ToHost
    exit 0
}

$backupDir = Join-Path $Ctx.LogsDir "rollback-pwa-standalone-$ts"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$bakManifest = New-Object System.Collections.Generic.List[string]
$bakManifest.Add('ROLLBACK PWA STANDALONE')
$bakManifest.Add("Fecha: $ts")
$bakManifest.Add("Backup dir: $backupDir")
$bakManifest.Add('Restaurar: WinSCP put selectivo desde este directorio a /juegos/aqui-hay-tema/')
$bakManifest.Add('Eliminar en prod si no existian: manifest.webmanifest, sw.js')
$bakManifest.Add('')
foreach ($rel in @('manifest.webmanifest', 'sw.js', 'play.php', '.htaccess')) {
    $prodPath = Join-Path $prodDir ($rel -replace '/', '\')
    $hadProd = Test-Path -LiteralPath $prodPath -PathType Leaf
    $prodHash = if ($hadProd) { (Get-FileHash -LiteralPath $prodPath -Algorithm SHA256).Hash } else { 'N/A' }
    $bakManifest.Add("$rel | existia=$hadProd | sha256=$prodHash")
    if ($hadProd) {
        $bak = Join-Path $backupDir ($rel -replace '/', '\')
        $parent = Split-Path -Parent $bak
        if (-not (Test-Path -LiteralPath $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
        Copy-Item -LiteralPath $prodPath -Destination $bak -Force
    }
}
$bakManifestPath = Join-Path $backupDir 'ROLLBACK_MANIFEST.txt'
$bakManifest -join "`n" | Set-Content -LiteralPath $bakManifestPath -Encoding UTF8
Write-DeployLog -LogFile $logFile -Message "Backup: $backupDir" -ToHost

$phpLint = & php -l (Join-Path $staging 'play.php') 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-DeployLog -LogFile $logFile -Message "LINT FAIL play.php: $phpLint" -ToHost
    exit 1
}

$buster = Update-AhtCacheBuster -Ctx $Ctx
[void]$toDeploy.Add('assets/aht-cache-buster.txt')
$busterStaging = Join-Path $staging 'assets\aht-cache-buster.txt'
$busterDir = Split-Path -Parent $busterStaging
if (-not (Test-Path -LiteralPath $busterDir)) { New-Item -ItemType Directory -Path $busterDir -Force | Out-Null }
Copy-Item -LiteralPath (Get-AhtCacheBusterPath -Ctx $Ctx) -Destination $busterStaging -Force
Write-DeployLog -LogFile $logFile -Message "Cache-buster actualizado: $buster" -ToHost

$files = [System.Collections.Generic.List[object]]::new()
foreach ($rel in ($toDeploy | Select-Object -Unique)) {
    $local = if ($rel -eq 'assets/aht-cache-buster.txt') {
        Get-AhtCacheBusterPath -Ctx $Ctx
    } else {
        Join-Path $staging ($rel -replace '/', '\')
    }
    if (-not (Test-Path -LiteralPath $local)) { continue }
    [void]$files.Add(@{ Local = $local; Remote = "$($Ctx.RemotePath)/$rel"; Rel = $rel })
}

$winscpLog = Join-Path $Ctx.LogsDir "deploy-pwa-standalone-winscp-$ts.log"
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $files -WinScpLogPath $winscpLog
if (-not $upload.Ok) { exit $upload.Code }
Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
Write-DeployLog -LogFile $logFile -Message "OK deploy $($upload.Count) archivo(s). Backup: $backupDir" -ToHost
Write-DeployLog -LogFile $logFile -Message "ROLLBACK: powershell -File scripts\_rollback_pwa_standalone.ps1 -BackupDir `"$backupDir`"" -ToHost
Write-DeployLog -LogFile $logFile -Message "Log: $logFile" -ToHost
exit 0
