#Requires -Version 5.1
# Guardas cromatica desktop blindada — abortar deploy si falta hoja o enlace en play.php

$script:AhtInicioDesktopCromaticaRel = 'assets/css/design-system/screens/inicio-desktop-cromatica.css'
$script:AhtInicioDesktopCromaticaMarkers = @(
    'INICIO-CROMATICA-DESKTOP-v1',
    'INICIO-CROMATICA-DESKTOP-v2',
    'INICIO-BOTTOM-NAV-DESKTOP-v106',
    'INICIO-BOTTOM-NAV-DESKTOP-v134',
    'INICIO-PLANES-BLOQUE-DESKTOP-v107',
    'INICIO-TOP-RELOJ-CROMATICA-DESKTOP-v108',
    '#F3EFF7'
)

function Test-AhtPlayPhpHasBom {
    param([string]$PlayPhpPath)
    if (-not (Test-Path -LiteralPath $PlayPhpPath)) { return $false }
    $bytes = [IO.File]::ReadAllBytes($PlayPhpPath)
    return ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF)
}

function Assert-AhtInicioDesktopCromaticaLocal {
    param(
        [hashtable]$Ctx,
        [string]$LogFile
    )

    $cssPath = Join-Path $Ctx.RepoRoot ($AhtInicioDesktopCromaticaRel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $cssPath)) {
        Write-DeployLog -LogFile $LogFile -Message "ABORT: falta $AhtInicioDesktopCromaticaRel" -ToHost
        throw 'AHT_DEPLOY_ABORT: inicio-desktop-cromatica.css ausente'
    }

    $css = Get-Content -LiteralPath $cssPath -Raw -Encoding UTF8
    foreach ($marker in $AhtInicioDesktopCromaticaMarkers) {
        if ($css -notmatch [regex]::Escape($marker)) {
            Write-DeployLog -LogFile $LogFile -Message "ABORT: $AhtInicioDesktopCromaticaRel sin marcador $marker" -ToHost
            throw "AHT_DEPLOY_ABORT: marcador ausente ($marker)"
        }
    }

    $playPath = Join-Path $Ctx.RepoRoot 'play.php'
    if (Test-AhtPlayPhpHasBom -PlayPhpPath $playPath) {
        Write-DeployLog -LogFile $LogFile -Message 'ABORT: play.php tiene BOM UTF-8 (provoca error 500)' -ToHost
        throw 'AHT_DEPLOY_ABORT: play.php con BOM'
    }

    $play = Get-Content -LiteralPath $playPath -Raw -Encoding UTF8
    if ($play -notmatch 'inicio-desktop-cromatica\.css') {
        Write-DeployLog -LogFile $LogFile -Message 'ABORT: play.php no enlaza inicio-desktop-cromatica.css' -ToHost
        throw 'AHT_DEPLOY_ABORT: play.php sin link cromatica'
    }
    if ($play -notmatch 'data-inicio-planes-bloque') {
        Write-DeployLog -LogFile $LogFile -Message 'ABORT: play.php sin wrapper inicio-planes-libreta' -ToHost
        throw 'AHT_DEPLOY_ABORT: play.php sin bloque planes unificado'
    }

    Write-DeployLog -LogFile $LogFile -Message 'OK: guardas cromatica desktop local' -ToHost
}

function Test-AhtInicioDesktopCromaticaRemote {
    param(
        [hashtable]$Ctx,
        [string]$Buster,
        [string]$LogFile
    )

    $base = $Ctx.PublicUrl.TrimEnd('/')
    $url = "$base/assets/css/design-system/screens/inicio-desktop-cromatica.css?v=$Buster"
    try {
        $css = (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 60).Content
    } catch {
        Write-DeployLog -LogFile $LogFile -Message "FAIL remoto cromatica: $($_.Exception.Message)" -ToHost
        return $false
    }

    $ok = $true
    foreach ($marker in $AhtInicioDesktopCromaticaMarkers) {
        if ($css -notmatch [regex]::Escape($marker)) {
            Write-DeployLog -LogFile $LogFile -Message "FAIL produccion sin marcador $marker" -ToHost
            $ok = $false
        }
    }
    if ($ok) {
        Write-DeployLog -LogFile $LogFile -Message 'OK: inicio-desktop-cromatica.css en produccion' -ToHost
    }
    return $ok
}

function Add-AhtInicioDesktopCromaticaDeployFiles {
    param(
        [System.Collections.Generic.List[object]]$Files,
        [hashtable]$Ctx
    )

    $needsCromatica = $false
    foreach ($f in $Files) {
        $rel = [string]$f.Rel
        if ($rel -eq 'assets/css/design-system/screens/inicio-desktop.css' -or
            $rel -eq 'assets/css/design-system/screens/inicio-desktop-cromatica.css' -or
            $rel -eq 'play.php') {
            $needsCromatica = $true
            break
        }
    }
    if ($needsCromatica) {
        Add-AhtDeployFile -Files $Files -Ctx $Ctx -RelPath $AhtInicioDesktopCromaticaRel | Out-Null
        Add-AhtDeployFile -Files $Files -Ctx $Ctx -RelPath 'play.php' | Out-Null
    }
}
