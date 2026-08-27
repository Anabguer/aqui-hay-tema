#Requires -Version 5.1
# Libreria compartida para DEPLOY.bat (Aquí Hay Tema → producción canónica).
# Dot-source desde deploy_manual.ps1. No ejecutar directamente.

$__ahtDeployHelpers = 'W:\_Recursos\GestorProyectos\plantillas\deploy_helpers.ps1'
if (-not (Test-Path -LiteralPath $__ahtDeployHelpers)) {
    throw "Falta infraestructura de deploy: $__ahtDeployHelpers"
}
. $__ahtDeployHelpers

function Initialize-AhtDeployContext {
    param([string]$ScriptDir)

    $repoRoot = Split-Path -Parent $ScriptDir
    $configPath = Join-Path $ScriptDir 'deploy.config.json'
    if (-not (Test-Path -LiteralPath $configPath)) {
        throw "Falta $configPath"
    }
    $config = Get-Content -LiteralPath $configPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $target = $config.deployTargets.canonical
    if (-not $target) {
        throw 'Falta deployTargets.canonical en deploy.config.json'
    }

    $projectRoot = if ($config.projectRoot) { [string]$config.projectRoot } else { $repoRoot }
    $logsDir = Join-Path $projectRoot 'logs'
    if (-not (Test-Path -LiteralPath $logsDir)) {
        New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
    }

    $credsPath = [string]$config.credentialsConfig
    if (-not (Test-Path -LiteralPath $credsPath)) {
        throw 'Falta configuracion de credenciales (hostalia.publish.local.json).'
    }

    $exclusions = Merge-DeployExclusions -Config $config -Target $target
  # Herramientas y auditorias locales nunca a produccion.
    foreach ($extra in @('dev/', 'tests/', 'scripts/', 'Thumbs.db', 'desktop.ini')) {
        if ($exclusions -notcontains $extra) {
            $exclusions += $extra
        }
    }

    return @{
        RepoRoot = $projectRoot
        Config = $config
        Target = $target
        RemotePath = [string]$target.remotePath
        PublicUrl = [string]$target.publicUrl
        CheckUrl = if ($target.checkUrl) { [string]$target.checkUrl } else { [string]$target.publicUrl }
        CredsPath = $credsPath
        Exclusions = $exclusions
        PostCommands = @($target.postDeployWinScpCommands | ForEach-Object { [string]$_ })
        LogsDir = $logsDir
        CacheBusterRel = 'assets/aht-cache-buster.txt'
        FullScript = 'W:\juegos\deploy\winscp_put_aqui_hay_tema_canonical.ps1'
    }
}

function Get-AhtCacheBusterPath {
    param([hashtable]$Ctx)
    return Join-Path $Ctx.RepoRoot ($Ctx.CacheBusterRel -replace '/', '\')
}

function Update-AhtCacheBuster {
    param([hashtable]$Ctx)

    $path = Get-AhtCacheBusterPath -Ctx $Ctx
    $dir = Split-Path -Parent $path
    if (-not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $value = 'v3-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
    [IO.File]::WriteAllText($path, $value + "`r`n", (New-Object System.Text.UTF8Encoding $false))
    return $value
}

function Test-AhtPlayV3JsSyntax {
    param([hashtable]$Ctx)

    $jsPath = Join-Path $Ctx.RepoRoot 'assets\js\play-v3.js'
    if (-not (Test-Path -LiteralPath $jsPath)) {
        throw 'Falta assets/js/play-v3.js para comprobar sintaxis.'
    }
    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) {
        throw 'node no encontrado: no se puede ejecutar node --check en play-v3.js'
    }
    & node --check $jsPath 2>&1 | Out-String | ForEach-Object { $_.Trim() } | ForEach-Object {
        if ($_ -and $_ -notmatch '^$') { Write-Host $_ }
    }
    if ($LASTEXITCODE -ne 0) {
        throw 'play-v3.js falla node --check; abortando deploy.'
    }
}

function Add-AhtDeployFile {
    param(
        [System.Collections.Generic.List[object]]$Files,
        [hashtable]$Ctx,
        [string]$RelPath
    )
    $rel = $RelPath.Replace('\', '/').TrimStart('/')
    if (Test-DeployExcluded -RelPath $rel -Exclusions $Ctx.Exclusions) { return $false }
    $local = Join-Path $Ctx.RepoRoot ($rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local -PathType Leaf)) { return $false }
    foreach ($existing in $Files) {
        if ($existing.Rel -eq $rel) { return $false }
    }
    [void]$Files.Add(@{
        Local = $local
        Remote = "$($Ctx.RemotePath)/$rel"
        Rel = $rel
    })
    return $true
}

function Enhance-AhtNormalDeployFiles {
    param(
        [System.Collections.Generic.List[object]]$Files,
        [hashtable]$Ctx
    )

    $needsPlay = $false
    foreach ($f in $Files) {
        $rel = [string]$f.Rel
        if ($rel -match '^(assets/|api/|src/)') {
            $needsPlay = $true
            break
        }
    }
    if ($needsPlay) {
        Add-AhtDeployFile -Files $Files -Ctx $Ctx -RelPath 'play.php'
    }
    Add-AhtDeployFile -Files $Files -Ctx $Ctx -RelPath $Ctx.CacheBusterRel
}

function Get-AhtNormalDeployFiles {
    param([hashtable]$Ctx)

    Update-AhtCacheBuster -Ctx $Ctx | Out-Null
    $result = Get-IncrementalDeployFiles -RepoRoot $Ctx.RepoRoot -RemoteRoot $Ctx.RemotePath -Exclusions $Ctx.Exclusions
    $files = [System.Collections.Generic.List[object]]::new()
    foreach ($f in $result.Files) {
        [void]$files.Add($f)
    }
    Enhance-AhtNormalDeployFiles -Files $files -Ctx $Ctx

    return @{
        Files = $files
        SkippedByRule = $result.SkippedByRule
        SkippedDeleted = $result.SkippedDeleted
        Candidates = $result.Candidates
    }
}

function Get-AhtFilesFromUserInput {
    param(
        [hashtable]$Ctx,
        [string[]]$RelPaths
    )

    Update-AhtCacheBuster -Ctx $Ctx | Out-Null
    $files = [System.Collections.Generic.List[object]]::new()
    $missing = New-Object System.Collections.Generic.List[string]
    $skipped = New-Object System.Collections.Generic.List[string]

    foreach ($raw in $RelPaths) {
        $rel = $raw.Trim().Replace('\', '/').TrimStart('/')
        if ([string]::IsNullOrWhiteSpace($rel)) { continue }
        if (Test-DeployExcluded -RelPath $rel -Exclusions $Ctx.Exclusions) {
            [void]$skipped.Add($rel)
            continue
        }
        if (-not (Add-AhtDeployFile -Files $files -Ctx $Ctx -RelPath $rel)) {
            $local = Join-Path $Ctx.RepoRoot ($rel -replace '/', '\')
            if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
                [void]$missing.Add($rel)
            }
        }
    }

    Enhance-AhtNormalDeployFiles -Files $files -Ctx $Ctx

    return @{
        Files = $files
        Missing = $missing
        Skipped = $skipped
    }
}

function Invoke-AhtWinScpUpload {
    param(
        [hashtable]$Ctx,
        [System.Collections.Generic.List[object]]$Files,
        [string]$WinScpLogPath
    )

    if ($Files.Count -eq 0) {
        return @{ Ok = $true; Code = 0; Count = 0 }
    }

    foreach ($f in $Files) {
        if ([string]$f.Rel -eq 'assets/js/play-v3.js') {
            Test-AhtPlayV3JsSyntax -Ctx $Ctx
            break
        }
    }

    $cfg = Get-Content -LiteralPath $Ctx.CredsPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
    if (-not $winscp) {
        throw 'WinSCP.com no encontrado.'
    }

    $openLine = Get-WinScpOpenLine -Cfg $cfg
    $winscpLines = Build-WinScpPutScriptLines -OpenLine $openLine -Files $Files -PostCommands $Ctx.PostCommands
    $scriptPath = Join-Path $env:TEMP ('aht-deploy-' + [guid]::NewGuid().ToString('N') + '.txt')
    [IO.File]::WriteAllText($scriptPath, ($winscpLines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))

    & $winscp /ini=nul "/log=$WinScpLogPath" /script=$scriptPath
    $code = $LASTEXITCODE
    Remove-Item $scriptPath -Force -ErrorAction SilentlyContinue

    return @{ Ok = ($code -eq 0); Code = $code; Count = $Files.Count }
}

function Write-AhtDeployFileList {
    param(
        [string]$LogFile,
        [System.Collections.Generic.List[object]]$Files,
        [switch]$ToHost
    )
    foreach ($f in $Files) {
        $line = "$($f.Rel) -> $($f.Remote)"
        Write-DeployLog -LogFile $LogFile -Message $line -ToHost:$ToHost
    }
}

function Test-AhtPublicUrl {
    param([hashtable]$Ctx, [string]$LogFile)
    if (-not $Ctx.CheckUrl) { return }
    Write-DeployLog -LogFile $LogFile -Message 'Comprobando URL publica...' -ToHost
    Start-Sleep -Seconds 2
    $check = Test-PublicUrl -Url $Ctx.CheckUrl
    if ($check.Ok) {
        Write-DeployLog -LogFile $LogFile -Message "OK URL: $($Ctx.CheckUrl) (HTTP $($check.Status))" -ToHost
    } else {
        $statusText = if ($null -ne $check.Status) { "HTTP $($check.Status)" } else { 'sin respuesta' }
        Write-DeployLog -LogFile $LogFile -Message "AVISO: $($Ctx.CheckUrl) -> $statusText" -ToHost
    }
}
