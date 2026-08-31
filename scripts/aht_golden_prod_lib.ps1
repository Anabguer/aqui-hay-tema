#Requires -Version 5.1
# Libreria compartida: snapshot GOLDEN PROD y rollback por manifest.
# No modifica el juego; solo infraestructura de seguridad.

function Get-AhtGoldenProdRepoRoot {
    param([string]$ScriptDir = $PSScriptRoot)
    return Split-Path -Parent $ScriptDir
}

function Get-AhtGoldenProdCssFromPlayPhp {
    param([string]$PlayPhpPath)

    if (-not (Test-Path -LiteralPath $PlayPhpPath)) {
        throw "No existe play.php para parsear: $PlayPhpPath"
    }

    $bytes = [IO.File]::ReadAllBytes($PlayPhpPath)
    $text = [Text.Encoding]::UTF8.GetString($bytes)
    $rels = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)

    foreach ($m in [regex]::Matches($text, 'rel\s*=\s*"stylesheet"\s+href\s*=\s*"([^"?]+)')) {
        [void]$rels.Add($m.Groups[1].Value.TrimStart('/'))
    }
    foreach ($m in [regex]::Matches($text, '<link[^>]+href\s*=\s*"([^"?]+)"[^>]*rel\s*=\s*"stylesheet"')) {
        [void]$rels.Add($m.Groups[1].Value.TrimStart('/'))
    }
    foreach ($m in [regex]::Matches($text, 'script\s+src\s*=\s*"([^"?]+)')) {
        [void]$rels.Add($m.Groups[1].Value.TrimStart('/'))
    }

    return @($rels | Sort-Object)
}

function Get-AhtGoldenProdRequiredChecks {
    return @{
        GhostCss = @(
            'assets/css/design-system/cotilleos-scrapbook-v1.css'
            'assets/css/design-system/vecinos-celdas-persona-v1.css'
            'assets/css/design-system/org-plan-scrapbook-v1.css'
            'assets/css/play-v3-consulta-edificio-v2.css'
        )
        DivergentCss = @(
            'assets/css/play-v3-mapa-canonico.css'
            'assets/css/play-v3-bloques-residencias.css'
            'assets/css/design-system/screens/inicio-mobile.css'
            'assets/css/play-v3-ficha.css'
            'assets/css/play-v3-organizar.css'
            'assets/css/play-v3-notas-mapa.css'
            'assets/css/play-v3-tutorial-ds.css'
            'assets/css/play-v3-avisos.css'
            'assets/css/play-v3-desktop-shell.css'
            'assets/css/play-v3-visual-review.css'
            'assets/css/play-v3-visual-replica.css'
            'assets/css/design-system/screens/inicio-desktop-cromatica.css'
            'assets/css/design-system/ficha-neni-ref-v1.css'
            'assets/css/play-v3-tutorial-lavanda.css'
        )
        CoreFiles = @(
            'assets/js/play-v3.js'
            'assets/aht-cache-buster.txt'
            'play.php'
        )
    }
}

function Get-AhtGoldenProdDeployIntegratedHead {
    param([hashtable]$Ctx)

    $wt = $null
    if ($Ctx.Config.canonicalDeploy -and $Ctx.Config.canonicalDeploy.worktreePath) {
        $wt = [string]$Ctx.Config.canonicalDeploy.worktreePath
    }
    if (-not $wt -or -not (Test-Path -LiteralPath (Join-Path $wt '.git'))) {
        $wt = $Ctx.RepoRoot
    }

    $remoteRef = 'origin/deploy/integrated'
    if ($Ctx.Config.canonicalDeploy -and $Ctx.Config.canonicalDeploy.remoteRef) {
        $remoteRef = [string]$Ctx.Config.canonicalDeploy.remoteRef
    }

    try {
        $head = (& git -C $wt rev-parse $remoteRef 2>$null | Select-Object -First 1)
        if ($head) { return [string]$head.Trim() }
    } catch {}

    try {
        $head = (& git -C $wt rev-parse HEAD 2>$null | Select-Object -First 1)
        if ($head) { return [string]$head.Trim() }
    } catch {}

    return $null
}

function New-AhtGoldenProdSnapshotDir {
    param(
        [string]$RepoRoot,
        [string]$Timestamp = (Get-Date -Format 'yyyyMMdd-HHmmss')
    )

    $dir = Join-Path $RepoRoot ("backups/GOLDEN-PROD-$Timestamp")
    $filesDir = Join-Path $dir 'files'
    New-Item -ItemType Directory -Path $filesDir -Force | Out-Null
    return @{
        Root = $dir
        Files = $filesDir
        Timestamp = $Timestamp
    }
}

function Invoke-AhtGoldenProdWinScpGet {
    param(
        [hashtable]$Ctx,
        [string[]]$RelPaths,
        [string]$LocalRoot,
        [string]$LogFile
    )

    if ($RelPaths.Count -eq 0) {
        return @{ Ok = $true; Code = 0; Count = 0 }
    }

    $cfg = Get-Content -LiteralPath $Ctx.CredsPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
    if (-not $winscp) {
        throw 'WinSCP.com no encontrado.'
    }

    $openLine = Get-WinScpOpenLine -Cfg $cfg
    $lines = New-Object 'System.Collections.Generic.List[string]'
    $lines.Add($openLine)

    foreach ($rel in ($RelPaths | Sort-Object -Unique)) {
        $norm = $rel.Replace('\', '/').TrimStart('/')
        $remote = "$($Ctx.RemotePath)/$norm"
        $local = Join-Path $LocalRoot ($norm -replace '/', '\')
        $parent = Split-Path -Parent $local
        if (-not (Test-Path -LiteralPath $parent)) {
            New-Item -ItemType Directory -Path $parent -Force | Out-Null
        }
        $lines.Add("get `"$remote`" `"$local`"")
    }

    $lines.Add('exit')
    $scriptPath = Join-Path $env:TEMP ('aht-golden-get-' + [guid]::NewGuid().ToString('N') + '.txt')
    [IO.File]::WriteAllText($scriptPath, ($lines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))
    $winscpLog = Join-Path (Split-Path -Parent $LogFile) 'winscp-get.log'
    & $winscp /ini=nul "/log=$winscpLog" /script=$scriptPath
    $code = $LASTEXITCODE
    Remove-Item $scriptPath -Force -ErrorAction SilentlyContinue

    return @{ Ok = ($code -eq 0); Code = $code; Count = $RelPaths.Count; WinScpLog = $winscpLog }
}

function Get-AhtFileSha256 {
    param([string]$Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function New-AhtGoldenProdManifest {
    param(
        [string]$SnapshotRoot,
        [string]$FilesRoot,
        [string[]]$RelPaths,
        [hashtable]$Meta
    )

    $entries = @()
    $totalBytes = 0L
    $snapTs = (Get-Item -LiteralPath $SnapshotRoot).LastWriteTimeUtc.ToString('o')

    foreach ($rel in ($RelPaths | Sort-Object -Unique)) {
        $norm = $rel.Replace('\', '/').TrimStart('/')
        $local = Join-Path $FilesRoot ($norm -replace '/', '\')
        if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
            throw "Manifest: falta archivo capturado $norm"
        }
        $fi = Get-Item -LiteralPath $local
        $bytes = [int64]$fi.Length
        $hash = Get-AhtFileSha256 -Path $local
        $totalBytes += $bytes
        $entries += [ordered]@{
            path = $norm
            origin = 'production'
            snapshotAt = $snapTs
            bytes = $bytes
            sha256 = $hash
        }
    }

    $manifest = [ordered]@{
        schema = 'aht-golden-prod-manifest-v1'
        capturedAt = (Get-Date).ToUniversalTime().ToString('o')
        productionUrl = $Meta.ProductionUrl
        originDeployIntegratedHead = $Meta.OriginDeployIntegratedHead
        cacheBusterProd = $Meta.CacheBusterProd
        snapshotDir = (Split-Path -Leaf $SnapshotRoot)
        fileCount = $entries.Count
        totalBytes = $totalBytes
        checks = $Meta.Checks
        files = $entries
    }

    $manifestPath = Join-Path $SnapshotRoot 'manifest.json'
    $json = ($manifest | ConvertTo-Json -Depth 6)
    [IO.File]::WriteAllText($manifestPath, $json + "`n", (New-Object System.Text.UTF8Encoding $false))
    return $manifestPath
}

function Test-AhtGoldenProdManifestIntegrity {
    param(
        [string]$ManifestPath,
        [string]$FilesRoot
    )

    if (-not (Test-Path -LiteralPath $ManifestPath)) {
        throw "Manifest no encontrado: $ManifestPath"
    }

    $manifest = Get-Content -LiteralPath $ManifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $errors = New-Object 'System.Collections.Generic.List[string]'

    foreach ($entry in $manifest.files) {
        $rel = [string]$entry.path
        $local = Join-Path $FilesRoot ($rel -replace '/', '\')
        if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
            $errors.Add("Falta: $rel")
            continue
        }
        $fi = Get-Item -LiteralPath $local
        if ($fi.Length -eq 0) {
            $errors.Add("0 bytes: $rel")
        }
        $hash = Get-AhtFileSha256 -Path $local
        if ($hash -ne [string]$entry.sha256) {
            $errors.Add("SHA256 mismatch: $rel")
        }
        if ([int64]$entry.bytes -ne [int64]$fi.Length) {
            $errors.Add("Bytes mismatch: $rel")
        }
    }

    return @{
        Ok = ($errors.Count -eq 0)
        Errors = @($errors)
        Manifest = $manifest
    }
}

function Initialize-AhtGoldenVisualDirs {
    param([string]$SnapshotRoot)

    $visualRoot = Join-Path $SnapshotRoot 'golden-visual'
    $dirs = @(
        'inicio-mobile'
        'inicio-desktop'
        'nuevo-plan'
        'mensajitos'
        'ficha'
        'diario-cotilleos'
        'tutorial'
        'mentes'
        'misiones'
        'modales-comunes'
    )

    foreach ($d in $dirs) {
        New-Item -ItemType Directory -Path (Join-Path $visualRoot $d) -Force | Out-Null
    }

    $readme = @(
        'Golden Visual — estructura preparada para capturas de referencia.'
        'No modificar partidas reales para generar estados.'
        'Las capturas existentes se copian aqui cuando esten disponibles.'
    ) -join "`r`n"
    [IO.File]::WriteAllText((Join-Path $visualRoot 'README.txt'), $readme + "`r`n", (New-Object System.Text.UTF8Encoding $false))

    return $visualRoot
}

function Copy-AhtExistingGoldenMasters {
    param(
        [string]$RepoRoot,
        [string]$VisualRoot
    )

    $gm = Join-Path $RepoRoot 'golden-master'
    if (-not (Test-Path -LiteralPath $gm)) { return }

    $map = @{
        'GOLDEN_INICIO_MOBILE.png' = 'inicio-mobile'
        'GOLDEN_INICIO_DESKTOP.png' = 'inicio-desktop'
    }

    foreach ($kv in $map.GetEnumerator()) {
        $src = Join-Path $gm $kv.Key
        if (Test-Path -LiteralPath $src) {
            $dst = Join-Path (Join-Path $VisualRoot $kv.Value) $kv.Key
            [IO.File]::Copy($src, $dst, $true)
        }
    }

    $manifestSrc = Join-Path $gm 'manifest.json'
    if (Test-Path -LiteralPath $manifestSrc) {
        [IO.File]::Copy($manifestSrc, (Join-Path $VisualRoot 'manifest-origen.json'), $true)
    }
}

function Write-AhtGoldenProdLog {
    param(
        [string]$LogFile,
        [string]$Message,
        [switch]$ToHost
    )
    $line = "[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    if ($ToHost) { Write-Host $line }
    Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8
}
