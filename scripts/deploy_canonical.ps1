#Requires -Version 5.1
param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$Helpers = 'W:\_Recursos\GestorProyectos\plantillas\deploy_helpers.ps1'
if (-not (Test-Path -LiteralPath $Helpers)) {
    Write-Host "ERROR: Falta infraestructura incremental: $Helpers"
    exit 1
}
. $Helpers

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir
$ConfigPath = Join-Path $ScriptDir 'deploy.config.json'
$LogsDir = Join-Path $RepoRoot 'logs'
if (-not (Test-Path -LiteralPath $LogsDir)) {
    New-Item -ItemType Directory -Path $LogsDir -Force | Out-Null
}

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$LogFile = Join-Path $LogsDir "deploy-canonical-incremental-$ts.log"

Write-DeployLog -LogFile $LogFile -Message '=== DEPLOY CANONICAL INCREMENTAL ===' -ToHost
Write-DeployLog -LogFile $LogFile -Message 'DESTINO: CANONICAL / PRODUCCION' -ToHost
Write-DeployLog -LogFile $LogFile -Message "Proyecto: $RepoRoot" -ToHost

if (-not (Test-Path -LiteralPath $ConfigPath)) {
    Write-DeployLog -LogFile $LogFile -Message "ERROR: Falta $ConfigPath" -ToHost
    exit 1
}

$config = Get-Content -LiteralPath $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
$target = $config.deployTargets.canonical
if (-not $target) {
    Write-DeployLog -LogFile $LogFile -Message 'ERROR: Falta deployTargets.canonical en deploy.config.json' -ToHost
    exit 1
}

$projectRoot = if ($config.projectRoot) { [string]$config.projectRoot } else { $RepoRoot }
$remotePath = [string]$target.remotePath
$publicUrl = [string]$target.publicUrl
$checkUrl = if ($target.checkUrl) { [string]$target.checkUrl } else { $publicUrl }
$credsPath = [string]$config.credentialsConfig
$exclusions = Merge-DeployExclusions -Config $config -Target $target
$postCommands = @()
if ($target.postDeployWinScpCommands) {
    $postCommands = @($target.postDeployWinScpCommands | ForEach-Object { [string]$_ })
}

$branch = (& git -C $RepoRoot branch --show-current 2>&1 | Out-String).Trim()
if ($LASTEXITCODE -eq 0 -and $branch) {
    Write-DeployLog -LogFile $LogFile -Message "Rama actual: $branch (destino forzado: canonical)" -ToHost
}

$dirty = (& git -C $RepoRoot status --porcelain 2>&1 | Out-String).Trim()
if ($dirty) {
    Write-DeployLog -LogFile $LogFile -Message 'AVISO: hay cambios locales sin commit:' -ToHost
    Write-Host $dirty
    Write-DeployLog -LogFile $LogFile -Message $dirty
    Write-DeployLog -LogFile $LogFile -Message 'Puedes continuar; el deploy sube archivos locales actuales.' -ToHost
} else {
    Write-DeployLog -LogFile $LogFile -Message 'Working tree limpio (sin cambios sin commit).' -ToHost
}

Write-DeployLog -LogFile $LogFile -Message "Remoto: $remotePath" -ToHost
Write-DeployLog -LogFile $LogFile -Message "URL: $publicUrl" -ToHost
Write-DeployLog -LogFile $LogFile -Message 'Modo: incremental (GestorProyectos; put explicito; sin borrar remotos)' -ToHost

$result = Get-IncrementalDeployFiles -RepoRoot $projectRoot -RemoteRoot $remotePath -Exclusions $exclusions

Write-DeployLog -LogFile $LogFile -Message '' -ToHost
Write-DeployLog -LogFile $LogFile -Message "--- Candidatos Git: $($result.Candidates.Count) ---" -ToHost
Write-DeployLog -LogFile $LogFile -Message "--- Excluidos por reglas: $($result.SkippedByRule.Count) ---" -ToHost
if ($result.SkippedByRule.Count -gt 0) {
    foreach ($s in $result.SkippedByRule) {
        Write-Host "  EXCLUIDO: $s"
        Write-DeployLog -LogFile $LogFile -Message "EXCLUIDO: $s"
    }
}
if ($result.SkippedDeleted.Count -gt 0) {
    Write-DeployLog -LogFile $LogFile -Message "--- Eliminados en Git (no se borran en remoto): $($result.SkippedDeleted.Count) ---" -ToHost
    foreach ($s in $result.SkippedDeleted) {
        Write-Host "  ELIMINADO LOCAL: $s (no se borra en servidor)"
        Write-DeployLog -LogFile $LogFile -Message "ELIMINADO LOCAL: $s"
    }
}

Write-DeployLog -LogFile $LogFile -Message '' -ToHost
Write-DeployLog -LogFile $LogFile -Message "--- Archivos a subir ($($result.Files.Count)) ---" -ToHost
if ($result.Files.Count -eq 0) {
    Write-DeployLog -LogFile $LogFile -Message 'No hay archivos publicables para subir.' -ToHost
    Write-DeployLog -LogFile $LogFile -Message "Log: $LogFile" -ToHost
    exit 0
}

foreach ($f in $result.Files) {
    $line = "$($f.Rel) -> $($f.Remote)"
    Write-Host $line
    Write-DeployLog -LogFile $LogFile -Message $line
}

$hasPlayV3 = $false
foreach ($f in $result.Files) {
    if ([string]$f.Rel -eq 'assets/js/play-v3.js') { $hasPlayV3 = $true; break }
}
if ($hasPlayV3) {
    $jsPath = Join-Path $projectRoot 'assets\js\play-v3.js'
    Write-DeployLog -LogFile $LogFile -Message 'Preflight: node --check assets/js/play-v3.js' -ToHost
    & node --check $jsPath 2>&1 | ForEach-Object { Write-DeployLog -LogFile $LogFile -Message $_ -ToHost }
    if ($LASTEXITCODE -ne 0) {
        Write-DeployLog -LogFile $LogFile -Message 'ERROR: play-v3.js falla node --check; abortando deploy.' -ToHost
        exit 1
    }
}

if ($DryRun) {
    Write-DeployLog -LogFile $LogFile -Message 'DRY-RUN: no se ejecutara WinSCP ni conexion a Hostalia.' -ToHost
    Write-DeployLog -LogFile $LogFile -Message "Log: $LogFile" -ToHost
    exit 0
}

if (-not (Read-DeployYesNo 'Confirmas deploy INCREMENTAL a CANONICAL / PRODUCCION?')) {
    Write-DeployLog -LogFile $LogFile -Message 'Cancelado por el usuario.' -ToHost
    exit 0
}

if (-not (Test-Path -LiteralPath $credsPath)) {
    Write-DeployLog -LogFile $LogFile -Message 'ERROR: Falta configuracion de credenciales (no copiada al repo).' -ToHost
    exit 1
}

$cfg = Get-Content -LiteralPath $credsPath -Raw -Encoding UTF8 | ConvertFrom-Json
$winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
if (-not $winscp) {
    Write-DeployLog -LogFile $LogFile -Message 'ERROR: WinSCP.com no encontrado.' -ToHost
    exit 1
}

$openLine = Get-WinScpOpenLine -Cfg $cfg
$winscpLines = Build-WinScpPutScriptLines -OpenLine $openLine -Files $result.Files -PostCommands $postCommands
$winscpLog = Join-Path $LogsDir "deploy-canonical-incremental-winscp-$ts.log"
$scriptPath = Join-Path $env:TEMP ('aht-canonical-inc-' + [guid]::NewGuid().ToString('N') + '.txt')
[IO.File]::WriteAllText($scriptPath, ($winscpLines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))

Write-DeployLog -LogFile $LogFile -Message 'Ejecutando WinSCP incremental...' -ToHost
& $winscp /ini=nul "/log=$winscpLog" /script=$scriptPath
$code = $LASTEXITCODE
Remove-Item $scriptPath -Force -ErrorAction SilentlyContinue
Write-DeployLog -LogFile $LogFile -Message "WinSCP exit code: $code"

if ($code -ne 0) {
    Write-DeployLog -LogFile $LogFile -Message "ERROR: deploy incremental fallo (codigo $code)" -ToHost
    exit $code
}

if ($checkUrl) {
    Write-DeployLog -LogFile $LogFile -Message 'Comprobando URL publica...' -ToHost
    Start-Sleep -Seconds 2
    $check = Test-PublicUrl -Url $checkUrl
    if ($check.Ok) {
        Write-DeployLog -LogFile $LogFile -Message "OK URL: $checkUrl (HTTP $($check.Status))" -ToHost
    } else {
        $statusText = if ($null -ne $check.Status) { "HTTP $($check.Status)" } else { 'sin respuesta' }
        Write-DeployLog -LogFile $LogFile -Message "AVISO: $checkUrl -> $statusText" -ToHost
    }
}

Write-DeployLog -LogFile $LogFile -Message 'OK: deploy canonical incremental completado.' -ToHost
Write-DeployLog -LogFile $LogFile -Message "Log: $LogFile" -ToHost
exit 0
