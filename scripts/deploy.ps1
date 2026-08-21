#Requires -Version 5.1
param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir
$ConfigPath = Join-Path $ScriptDir 'deploy.config.json'
$LogsDir = Join-Path $RepoRoot 'logs'
if (-not (Test-Path -LiteralPath $LogsDir)) {
    New-Item -ItemType Directory -Path $LogsDir -Force | Out-Null
}

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$LogFile = Join-Path $LogsDir "deploy-launcher-$ts.log"

function Write-Log([string]$Message, [switch]$ToHost) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    if ($ToHost) { Write-Host $line }
    Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8
}

function Read-YesNo([string]$Prompt, [string]$Default = 'N') {
    $suffix = if ($Default -eq 'S') { '[S/n]' } else { '[s/N]' }
    $answer = Read-Host "$Prompt $suffix"
    if ([string]::IsNullOrWhiteSpace($answer)) { return ($Default -eq 'S') }
    return ($answer.Trim().ToUpperInvariant() -in @('S', 'SI', 'Y', 'YES'))
}

function Get-DeployTarget([object]$Config, [string]$Branch) {
    $branchLower = $Branch.ToLowerInvariant()
    if ($branchLower -match 'playtest') {
        return $Config.deployTargets.playtest
    }
    return $Config.deployTargets.canonical
}

function Test-PublicUrl([string]$Url) {
    try {
        $resp = Invoke-WebRequest -Uri $Url -Method Head -TimeoutSec 30 -UseBasicParsing
        return @{ Ok = $true; Status = [int]$resp.StatusCode; Error = $null }
    } catch {
        $status = $null
        if ($_.Exception.Response) {
            try { $status = [int]$_.Exception.Response.StatusCode } catch {}
        }
        return @{ Ok = $false; Status = $status; Error = $_.Exception.Message }
    }
}

Write-Log '=== DEPLOY (Aquí Hay Tema) ===' -ToHost
Write-Log "Repo: $RepoRoot" -ToHost

if (-not (Test-Path -LiteralPath $ConfigPath)) {
    Write-Log "ERROR: Falta $ConfigPath" -ToHost
    exit 1
}

$config = Get-Content -LiteralPath $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json

$branch = (& git -C $RepoRoot branch --show-current 2>&1 | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($branch)) {
    Write-Log 'ERROR: no se pudo detectar la rama Git.' -ToHost
    exit 1
}

$target = Get-DeployTarget -Config $config -Branch $branch
$deployScript = [string]$target.script
$remotePath = [string]$target.remotePath
$publicUrl = [string]$target.publicUrl
$checkUrl = [string]$target.checkUrl
$credsPath = [string]$config.credentialsConfig

Write-Log "Rama: $branch" -ToHost
Write-Log "Destino remoto: $remotePath" -ToHost
Write-Log "URL publica: $publicUrl" -ToHost

$dirty = (& git -C $RepoRoot status --porcelain 2>&1 | Out-String).Trim()
if ($dirty) {
    Write-Log '' -ToHost
    Write-Log 'AVISO: hay cambios sin commit en el repositorio:' -ToHost
    Write-Host $dirty
    Write-Log $dirty
    Write-Log 'Puedes continuar; el deploy sube archivos locales actuales.' -ToHost
} else {
    Write-Log 'Working tree limpio (sin cambios sin commit).' -ToHost
}

if (-not (Test-Path -LiteralPath $deployScript)) {
    Write-Log "ERROR: No existe el script de deploy: $deployScript" -ToHost
    exit 1
}
if (-not (Test-Path -LiteralPath $credsPath)) {
    Write-Log "ERROR: Falta configuracion de credenciales: $credsPath" -ToHost
    exit 1
}

Write-Log '' -ToHost
Write-Log '--- Que se va a ejecutar ---' -ToHost
Write-Host "Script:   $deployScript"
Write-Host "Remoto:   $remotePath"
Write-Host "Credenciales: $credsPath (no se copian ni se registran)"
Write-Host "Exclusiones deploy: $($config.deployExclusions -join ', ')"
Write-Host "Comprobar URL: $checkUrl"
Write-Log "Script: $deployScript"
Write-Log "Remoto: $remotePath"
Write-Log "Credenciales: $credsPath"
Write-Log "Exclusiones: $($config.deployExclusions -join ', ')"

if ($DryRun) {
    Write-Log 'DRY-RUN: no se ejecutara deploy.' -ToHost
    $pre = Test-PublicUrl -Url $checkUrl
    if ($pre.Ok) {
        Write-Log "URL actual responde HTTP $($pre.Status)" -ToHost
    } else {
        $code = if ($null -ne $pre.Status) { "HTTP $($pre.Status)" } else { 'sin respuesta' }
        Write-Log "URL actual: $code ($($pre.Error))" -ToHost
    }
    Write-Log "Log: $LogFile" -ToHost
    exit 0
}

if (-not (Read-YesNo '¿Ejecutar deploy ahora?')) {
    Write-Log 'Cancelado por el usuario.' -ToHost
    exit 0
}

Write-Log 'Ejecutando deploy...' -ToHost
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $deployScript
$code = $LASTEXITCODE
Write-Log "Deploy script exit code: $code"

if ($code -ne 0) {
    Write-Log "ERROR: deploy fallo con codigo $code" -ToHost
    Write-Log "Log launcher: $LogFile" -ToHost
    Write-Log 'Revisa tambien W:\juegos\deploy\logs\deploy-aht-*.log' -ToHost
    exit $code
}

Write-Log 'Comprobando URL publica...' -ToHost
Start-Sleep -Seconds 2
$check = Test-PublicUrl -Url $checkUrl
if ($check.Ok) {
    Write-Log "OK URL: $checkUrl (HTTP $($check.Status))" -ToHost
} else {
    $statusText = if ($null -ne $check.Status) { "HTTP $($check.Status)" } else { 'sin respuesta' }
    Write-Log "AVISO: $checkUrl -> $statusText" -ToHost
    if ($check.Error) { Write-Log $check.Error }
}

Write-Log 'OK: deploy completado.' -ToHost
Write-Log "Log: $LogFile" -ToHost
exit 0
