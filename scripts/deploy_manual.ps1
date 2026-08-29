#Requires -Version 5.1
param(
    [ValidateSet('Normal', 'Files', 'Full')]
    [string]$Mode,
    [string[]]$FilePaths = @(),
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $ScriptDir 'deploy_lib_aht.ps1')

$Ctx = Initialize-AhtDeployContext -ScriptDir $ScriptDir
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $Ctx.LogsDir "deploy-manual-$Mode-$ts.log"
$winscpLog = Join-Path $Ctx.LogsDir "deploy-manual-winscp-$Mode-$ts.log"

Write-DeployLog -LogFile $logFile -Message '=== AHT DEPLOY MANUAL ===' -ToHost
Write-DeployLog -LogFile $logFile -Message "Modalidad: $Mode" -ToHost
Write-DeployLog -LogFile $logFile -Message 'DESTINO: CANONICAL / PRODUCCION' -ToHost
Write-DeployLog -LogFile $logFile -Message "Proyecto: $($Ctx.RepoRoot)" -ToHost
Write-DeployLog -LogFile $logFile -Message "Remoto: $($Ctx.RemotePath)" -ToHost
Write-DeployLog -LogFile $logFile -Message "URL: $($Ctx.PublicUrl)" -ToHost
Write-DeployLog -LogFile $logFile -Message 'Desktop y movil: misma URL /juegos/aqui-hay-tema/play.php' -ToHost

if ($Mode -eq 'Full') {
    Write-DeployLog -LogFile $logFile -Message '' -ToHost
    Write-Host ''
    Write-Host '*** SINCRONIZACION COMPLETA (FULL) ***' -ForegroundColor Yellow
    Write-Host 'Sube todos los archivos locales canonicos necesarios.'
    Write-Host 'NO borra archivos remotos.'
    Write-Host 'NO toca la base de datos.'
    Write-Host 'Excluye: dev/, tests/, logs/, partidas (part_*.json), backups, temporales.'
    Write-Host ''

    if ($DryRun) {
        Write-DeployLog -LogFile $logFile -Message 'DRY-RUN FULL: no se conecta al servidor.' -ToHost
        exit 0
    }

    $confirm = Read-Host 'Escribe SI para confirmar FULL deploy a PRODUCCION'
    if ($confirm.Trim().ToUpperInvariant() -notin @('SI', 'S', 'YES', 'Y')) {
        Write-DeployLog -LogFile $logFile -Message 'Cancelado por el usuario.' -ToHost
        exit 0
    }

    $buster = Update-AhtCacheBuster -Ctx $Ctx
    Write-DeployLog -LogFile $logFile -Message "Cache-buster actualizado: $buster" -ToHost

    if (-not (Test-Path -LiteralPath $Ctx.FullScript)) {
        Write-DeployLog -LogFile $logFile -Message "ERROR: Falta script FULL: $($Ctx.FullScript)" -ToHost
        exit 1
    }

    Write-DeployLog -LogFile $logFile -Message "Ejecutando: $($Ctx.FullScript)" -ToHost
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Ctx.FullScript
    $code = $LASTEXITCODE
    Write-DeployLog -LogFile $logFile -Message "FULL script exit code: $code"

    if ($code -eq 0) {
        # Asegurar cache-buster y play.php tras FULL (el script externo ya sube casi todo).
        $postFiles = [System.Collections.Generic.List[object]]::new()
        Add-AhtDeployFile -Files $postFiles -Ctx $Ctx -RelPath $Ctx.CacheBusterRel
        Add-AhtDeployFile -Files $postFiles -Ctx $Ctx -RelPath 'play.php'
        if ($postFiles.Count -gt 0) {
            Write-DeployLog -LogFile $logFile -Message 'Subiendo play.php + cache-buster...' -ToHost
            $post = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $postFiles -WinScpLogPath $winscpLog
            if (-not $post.Ok) { $code = $post.Code }
        }
        Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
        Write-DeployLog -LogFile $logFile -Message 'OK: FULL completado.' -ToHost
    } else {
        Write-DeployLog -LogFile $logFile -Message "ERROR: FULL fallo (codigo $code)" -ToHost
    }

    Write-DeployLog -LogFile $logFile -Message "Log: $logFile" -ToHost
    Write-DeployLog -LogFile $logFile -Message "Log WinSCP: $winscpLog" -ToHost
    exit $code
}

if ($Mode -eq 'Files') {
    try {
        $guardStart = Assert-AhtCanonicalDeployGuards -Ctx $Ctx -LogFile $logFile
        $headAtStart = $guardStart.RemoteHead
    } catch {
        Write-DeployLog -LogFile $logFile -Message "ABORT: $($_.Exception.Message)" -ToHost
        exit 1
    }
    $paths = @($FilePaths)
    if ($paths.Count -eq 0) {
        Write-Host ''
        Write-Host 'Rutas relativas al proyecto (una por linea). Linea vacia para terminar:'
        Write-Host 'Ejemplo: assets/css/play-v3-responsive.css'
        while ($true) {
            $line = Read-Host 'Archivo'
            if ([string]::IsNullOrWhiteSpace($line)) { break }
            $paths += $line.Trim()
        }
    }

    if ($paths.Count -eq 0) {
        Write-DeployLog -LogFile $logFile -Message 'Sin archivos: cancelado.' -ToHost
        exit 0
    }

    $pack = Get-AhtFilesFromUserInput -Ctx $Ctx -RelPaths $paths
    if ($pack.Missing.Count -gt 0) {
        Write-DeployLog -LogFile $logFile -Message 'AVISO: no encontrados localmente:' -ToHost
        foreach ($m in $pack.Missing) { Write-DeployLog -LogFile $logFile -Message "  $m" -ToHost }
    }
    if ($pack.Skipped.Count -gt 0) {
        Write-DeployLog -LogFile $logFile -Message 'Excluidos por reglas:' -ToHost
        foreach ($s in $pack.Skipped) { Write-DeployLog -LogFile $logFile -Message "  $s" -ToHost }
    }

    Write-DeployLog -LogFile $logFile -Message "--- Archivos a subir ($($pack.Files.Count)) ---" -ToHost
    Write-AhtDeployFileList -LogFile $logFile -Files $pack.Files -ToHost

    if ($DryRun) {
        Write-DeployLog -LogFile $logFile -Message 'DRY-RUN: no se sube nada.' -ToHost
        exit 0
    }

    if (-not (Read-DeployYesNo 'Confirmas subida de estos archivos a PRODUCCION?')) {
        Write-DeployLog -LogFile $logFile -Message 'Cancelado.' -ToHost
        exit 0
    }

    [void](Assert-AhtCanonicalDeployGuards -Ctx $Ctx -LogFile $logFile -ExpectedHeadAtStart $headAtStart)

    $upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $pack.Files -WinScpLogPath $winscpLog
    if ($upload.Ok) {
        Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
        Write-DeployLog -LogFile $logFile -Message "OK: $($upload.Count) archivo(s) subidos." -ToHost
    } else {
        Write-DeployLog -LogFile $logFile -Message "ERROR: WinSCP codigo $($upload.Code)" -ToHost
    }
    Write-DeployLog -LogFile $logFile -Message "Log: $logFile" -ToHost
    Write-DeployLog -LogFile $logFile -Message "Log WinSCP: $winscpLog" -ToHost
    exit $upload.Code
}

# --- NORMAL ---
Write-DeployLog -LogFile $logFile -Message 'Modo NORMAL deshabilitado: requiere lista explicita de archivos.' -ToHost
Write-DeployLog -LogFile $logFile -Message 'Usa DEPLOY_SURGICAL.bat o deploy_manual.ps1 -Mode Files -FilePaths <rutas>' -ToHost
Write-DeployLog -LogFile $logFile -Message 'Motivo: evitar publicar WIP accidental desde working tree sucio.' -ToHost
exit 1

# --- NORMAL (legacy, deshabilitado) ---
$dirty = (& git -C $Ctx.RepoRoot status --porcelain 2>&1 | Out-String).Trim()
if ($dirty) {
    Write-DeployLog -LogFile $logFile -Message 'Cambios locales detectados (sin commit necesario).' -ToHost
} else {
    Write-DeployLog -LogFile $logFile -Message 'AVISO: working tree limpio; solo se subira cache-buster/play.php si aplica.' -ToHost
}

$pack = Get-AhtNormalDeployFiles -Ctx $Ctx
if ($pack.SkippedByRule.Count -gt 0) {
    Write-DeployLog -LogFile $logFile -Message "--- Excluidos por reglas ($($pack.SkippedByRule.Count)) ---" -ToHost
    foreach ($s in $pack.SkippedByRule) { Write-DeployLog -LogFile $logFile -Message "EXCLUIDO: $s" }
}
if ($pack.SkippedDeleted.Count -gt 0) {
    Write-DeployLog -LogFile $logFile -Message "--- Eliminados en Git (no se borran en remoto) ---" -ToHost
    foreach ($s in $pack.SkippedDeleted) { Write-DeployLog -LogFile $logFile -Message "ELIMINADO LOCAL: $s" }
}

Write-DeployLog -LogFile $logFile -Message "--- Archivos a subir ($($pack.Files.Count)) ---" -ToHost
Write-AhtDeployFileList -LogFile $logFile -Files $pack.Files -ToHost

if ($pack.Files.Count -eq 0) {
    Write-DeployLog -LogFile $logFile -Message 'Nada que subir.' -ToHost
    Write-DeployLog -LogFile $logFile -Message 'Si produccion esta incompleta, usa opcion [3] FULL.' -ToHost
    exit 0
}

if ($DryRun) {
    Write-DeployLog -LogFile $logFile -Message 'DRY-RUN: no se sube nada.' -ToHost
    exit 0
}

if (-not (Read-DeployYesNo 'Confirmas DEPLOY NORMAL a PRODUCCION?')) {
    Write-DeployLog -LogFile $logFile -Message 'Cancelado.' -ToHost
    exit 0
}

$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $pack.Files -WinScpLogPath $winscpLog
if ($upload.Ok) {
    Test-AhtPublicUrl -Ctx $Ctx -LogFile $logFile
    Write-DeployLog -LogFile $logFile -Message "OK: $($upload.Count) archivo(s) subidos." -ToHost
    Write-DeployLog -LogFile $logFile -Message 'Cache CSS/JS: assets/aht-cache-buster.txt actualizado (param ?v= en play.php).' -ToHost
} else {
    Write-DeployLog -LogFile $logFile -Message "ERROR: WinSCP codigo $($upload.Code)" -ToHost
}

Write-DeployLog -LogFile $logFile -Message "Log: $logFile" -ToHost
Write-DeployLog -LogFile $logFile -Message "Log WinSCP: $winscpLog" -ToHost
exit $upload.Code
