#Requires -Version 5.1
param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$Helpers = 'W:\_Recursos\GestorProyectos\plantillas\deploy_helpers.ps1'
if (-not (Test-Path -LiteralPath $Helpers)) {
    Write-Host "ERROR: Falta infraestructura: $Helpers"
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

$DeployScript = 'W:\juegos\deploy\winscp_put_aqui_hay_tema_canonical.ps1'
$RemotePath = '/juegos/aqui-hay-tema'
$PublicUrl = 'https://intocables13.com/juegos/aqui-hay-tema/play.php'
$CredsPath = 'W:\anabel\deploy\hostalia.publish.local.json'

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$LogFile = Join-Path $LogsDir "deploy-canonical-full-$ts.log"

Write-DeployLog -LogFile $LogFile -Message '=== DEPLOY CANONICAL COMPLETO ===' -ToHost
Write-DeployLog -LogFile $LogFile -Message 'DESTINO: CANONICAL / PRODUCCION (FULL)' -ToHost
Write-DeployLog -LogFile $LogFile -Message "Proyecto: $RepoRoot" -ToHost
Write-DeployLog -LogFile $LogFile -Message "Remoto: $RemotePath" -ToHost
Write-DeployLog -LogFile $LogFile -Message "URL: $PublicUrl" -ToHost

$dirty = (& git -C $RepoRoot status --porcelain 2>&1 | Out-String).Trim()
if ($dirty) {
    Write-DeployLog -LogFile $LogFile -Message 'AVISO: hay cambios locales sin commit:' -ToHost
    Write-Host $dirty
    Write-DeployLog -LogFile $LogFile -Message $dirty
    Write-DeployLog -LogFile $LogFile -Message 'Puedes continuar; el deploy sube archivos locales actuales.' -ToHost
} else {
    Write-DeployLog -LogFile $LogFile -Message 'Working tree limpio (sin cambios sin commit).' -ToHost
}

if (-not (Test-Path -LiteralPath $DeployScript)) {
    Write-DeployLog -LogFile $LogFile -Message "ERROR: No existe script FULL: $DeployScript" -ToHost
    exit 1
}
if (-not (Test-Path -LiteralPath $CredsPath)) {
    Write-DeployLog -LogFile $LogFile -Message 'ERROR: Falta configuracion de credenciales (no copiada al repo).' -ToHost
    exit 1
}

Write-DeployLog -LogFile $LogFile -Message '' -ToHost
Write-DeployLog -LogFile $LogFile -Message '--- Que se va a ejecutar ---' -ToHost
Write-Host "Script:   $DeployScript"
Write-Host "Remoto:   $RemotePath"
Write-Host "URL:      $PublicUrl"

if ($DryRun) {
    Write-DeployLog -LogFile $LogFile -Message 'DRY-RUN: no se ejecutara deploy FULL ni conexion a Hostalia.' -ToHost
    Write-DeployLog -LogFile $LogFile -Message "Log: $LogFile" -ToHost
    exit 0
}

if (-not (Read-DeployYesNo 'Confirmas deploy COMPLETO a CANONICAL / PRODUCCION?')) {
    Write-DeployLog -LogFile $LogFile -Message 'Cancelado por el usuario.' -ToHost
    exit 0
}

Write-DeployLog -LogFile $LogFile -Message 'Ejecutando script canonical FULL...' -ToHost
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $DeployScript
$code = $LASTEXITCODE
Write-DeployLog -LogFile $LogFile -Message "Script FULL exit code: $code"

if ($code -ne 0) {
    Write-DeployLog -LogFile $LogFile -Message "ERROR: deploy FULL fallo con codigo $code" -ToHost
    exit $code
}

Write-DeployLog -LogFile $LogFile -Message 'Comprobando URL publica...' -ToHost
Start-Sleep -Seconds 2
$check = Test-PublicUrl -Url $PublicUrl
if ($check.Ok) {
    Write-DeployLog -LogFile $LogFile -Message "OK URL: $PublicUrl (HTTP $($check.Status))" -ToHost
} else {
    $statusText = if ($null -ne $check.Status) { "HTTP $($check.Status)" } else { 'sin respuesta' }
    Write-DeployLog -LogFile $LogFile -Message "AVISO: $PublicUrl -> $statusText" -ToHost
}

Write-DeployLog -LogFile $LogFile -Message 'OK: deploy canonical FULL completado.' -ToHost
Write-DeployLog -LogFile $LogFile -Message "Log: $LogFile" -ToHost
exit 0
