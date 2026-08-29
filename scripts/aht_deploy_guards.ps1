#Requires -Version 5.1
# Guardas de deploy canónico AHT — ABORTAR ante estado inseguro (sin auto-fix git).

function Get-AhtCanonicalDeployConfig {
    param([hashtable]$Ctx)

    $cfg = $Ctx.Config
  $branch = if ($cfg.canonicalDeploy.branch) { [string]$cfg.canonicalDeploy.branch } else { 'deploy/integrated' }
    $remoteRef = if ($cfg.canonicalDeploy.remoteRef) { [string]$cfg.canonicalDeploy.remoteRef } else { 'origin/deploy/integrated' }
    $worktreePath = if ($cfg.canonicalDeploy.worktreePath) {
        [string]$cfg.canonicalDeploy.worktreePath
    } else {
        Join-Path (Split-Path -Parent $Ctx.RepoRoot) 'aqui-hay-tema-deploy-integrated'
    }

    $resolvedWt = Resolve-Path -LiteralPath $worktreePath -ErrorAction SilentlyContinue
    $worktreePathResolved = if ($resolvedWt) { $resolvedWt.Path } else { $null }

    return @{
        Branch = $branch
        RemoteRef = $remoteRef
        WorktreePath = $worktreePathResolved
        WorktreePathRaw = $worktreePath
    }
}

function Invoke-AhtGitFetch {
    param([string]$RepoRoot, [string]$LogFile)

    Write-DeployLog -LogFile $LogFile -Message "git fetch origin (en $RepoRoot)" -ToHost
    $out = & git -C $RepoRoot fetch origin 2>&1 | Out-String
    if ($out.Trim()) { Write-DeployLog -LogFile $LogFile -Message $out.Trim() }
    if ($LASTEXITCODE -ne 0) {
        throw "git fetch fallo (codigo $LASTEXITCODE)"
    }
}

function Get-AhtGitHead {
    param([string]$RepoRoot, [string]$Ref = 'HEAD')
    return (& git -C $RepoRoot rev-parse $Ref 2>&1 | Out-String).Trim()
}

function Test-AhtWorkingTreeClean {
    param([string]$RepoRoot)
    $dirty = (& git -C $RepoRoot status --porcelain 2>&1 | Out-String).Trim()
    return [string]::IsNullOrWhiteSpace($dirty)
}

function Get-AhtWorkingTreeDirtySummary {
    param([string]$RepoRoot)
    return (& git -C $RepoRoot status --porcelain 2>&1 | Out-String).Trim()
}

function Assert-AhtCanonicalDeployGuards {
    param(
        [hashtable]$Ctx,
        [string]$LogFile,
        [string]$ExpectedHeadAtStart = '',
        [switch]$SkipWorktreePathCheck
    )

    $canon = Get-AhtCanonicalDeployConfig -Ctx $Ctx
    $deployRoot = $Ctx.RepoRoot
    $deployRootResolved = (Resolve-Path -LiteralPath $deployRoot).Path

    if (-not $SkipWorktreePathCheck) {
        if (-not $canon.WorktreePath) {
            Write-DeployLog -LogFile $LogFile -Message "ABORT: worktree canonico no existe: $($canon.WorktreePathRaw)" -ToHost
            Write-DeployLog -LogFile $LogFile -Message 'Crear con: git worktree add <path> origin/deploy/integrated' -ToHost
            throw 'AHT_DEPLOY_ABORT: worktree canonico ausente'
        }
        if ($deployRootResolved -ne $canon.WorktreePath) {
            Write-DeployLog -LogFile $LogFile -Message "ABORT: deploy desde ruta no canonica." -ToHost
            Write-DeployLog -LogFile $LogFile -Message "  Actual:  $deployRootResolved" -ToHost
            Write-DeployLog -LogFile $LogFile -Message "  Canonico: $($canon.WorktreePath)" -ToHost
            throw 'AHT_DEPLOY_ABORT: ruta no canonica'
        }
    }

    Invoke-AhtGitFetch -RepoRoot $deployRoot -LogFile $LogFile

    $head = Get-AhtGitHead -RepoRoot $deployRoot -Ref 'HEAD'
    $remoteHead = Get-AhtGitHead -RepoRoot $deployRoot -Ref $canon.RemoteRef
    $branch = (& git -C $deployRoot branch --show-current 2>&1 | Out-String).Trim()

    Write-DeployLog -LogFile $LogFile -Message "HEAD: $head" -ToHost
    Write-DeployLog -LogFile $LogFile -Message "$($canon.RemoteRef): $remoteHead" -ToHost
    if ($branch) {
        Write-DeployLog -LogFile $LogFile -Message "Rama: $branch" -ToHost
    } else {
        Write-DeployLog -LogFile $LogFile -Message 'Rama: (detached HEAD)' -ToHost
    }

    if ($head -ne $remoteHead) {
        Write-DeployLog -LogFile $LogFile -Message "ABORT: HEAD ($head) != $($canon.RemoteRef) ($remoteHead)" -ToHost
        Write-DeployLog -LogFile $LogFile -Message 'Actualiza el worktree canonico manualmente (sin auto checkout/reset/pull en deploy).' -ToHost
        throw 'AHT_DEPLOY_ABORT: HEAD desalineado con origin'
    }

    if (-not (Test-AhtWorkingTreeClean -RepoRoot $deployRoot)) {
        $dirty = Get-AhtWorkingTreeDirtySummary -RepoRoot $deployRoot
        Write-DeployLog -LogFile $LogFile -Message 'ABORT: working tree sucio en worktree canonico.' -ToHost
        Write-DeployLog -LogFile $LogFile -Message $dirty -ToHost
        throw 'AHT_DEPLOY_ABORT: working tree sucio'
    }

    if ($ExpectedHeadAtStart -and $ExpectedHeadAtStart -ne $remoteHead) {
        Write-DeployLog -LogFile $LogFile -Message "ABORT: origin avanzo durante validacion." -ToHost
        Write-DeployLog -LogFile $LogFile -Message "  Al inicio: $ExpectedHeadAtStart" -ToHost
        Write-DeployLog -LogFile $LogFile -Message "  Ahora:     $remoteHead" -ToHost
        throw 'AHT_DEPLOY_ABORT: origin avanzo'
    }

    return @{
        Head = $head
        RemoteHead = $remoteHead
        WorktreePath = $canon.WorktreePath
        Branch = $branch
        RemoteRef = $canon.RemoteRef
    }
}

function Assert-AhtDeployFileListExplicit {
    param(
        [string[]]$RequestedFiles,
        [System.Collections.Generic.List[object]]$PackedFiles,
        [string]$LogFile
    )

    if ($RequestedFiles.Count -eq 0) {
        Write-DeployLog -LogFile $LogFile -Message 'ABORT: lista de archivos vacia.' -ToHost
        throw 'AHT_DEPLOY_ABORT: sin archivos'
    }

    $packedRels = @($PackedFiles | ForEach-Object { [string]$_.Rel })
    $extras = $packedRels | Where-Object { $_ -notin $RequestedFiles -and $_ -ne 'assets/aht-cache-buster.txt' -and $_ -ne 'play.php' }
    if ($extras.Count -gt 0) {
        Write-DeployLog -LogFile $LogFile -Message 'ABORT: archivos no autorizados en paquete de deploy:' -ToHost
        foreach ($e in $extras) { Write-DeployLog -LogFile $LogFile -Message "  + $e" -ToHost }
        throw 'AHT_DEPLOY_ABORT: archivos no autorizados'
    }

    foreach ($req in $RequestedFiles) {
        if ($req -notin $packedRels) {
            Write-DeployLog -LogFile $LogFile -Message "ABORT: archivo solicitado no empaquetado: $req" -ToHost
            throw 'AHT_DEPLOY_ABORT: archivo faltante'
        }
    }
}

function Invoke-AhtRemoteBackup {
    param(
        [hashtable]$Ctx,
        [string[]]$RelPaths,
        [string]$BackupDir,
        [string]$LogFile,
        [string]$WinScpLogPath
    )

    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
    $cfg = Get-Content -LiteralPath $Ctx.CredsPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
    if (-not $winscp) { throw 'WinSCP.com no encontrado.' }

    $openLine = Get-WinScpOpenLine -Cfg $cfg
    $getLines = New-Object System.Collections.Generic.List[string]
    $getLines.Add($openLine)
    foreach ($rel in $RelPaths) {
        $remote = "$($Ctx.RemotePath)/$rel"
        $localBak = Join-Path $BackupDir ($rel -replace '/', '\')
        $parent = Split-Path -Parent $localBak
        if (-not (Test-Path -LiteralPath $parent)) {
            New-Item -ItemType Directory -Path $parent -Force | Out-Null
        }
        $getLines.Add("get `"$remote`" `"$localBak`"")
    }
    $getLines.Add('exit')
    $getScript = Join-Path $env:TEMP ('aht-backup-' + [guid]::NewGuid().ToString('N') + '.txt')
    [IO.File]::WriteAllText($getScript, ($getLines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))
    Write-DeployLog -LogFile $LogFile -Message "Backup remoto -> $BackupDir" -ToHost
    & $winscp /ini=nul "/log=$WinScpLogPath" /script=$getScript
    $code = $LASTEXITCODE
    Remove-Item $getScript -Force -ErrorAction SilentlyContinue
    if ($code -ne 0) {
        throw "Backup remoto fallo (WinSCP codigo $code)"
    }
}

function Invoke-AhtGitProdCompare {
    param(
        [hashtable]$Ctx,
        [string]$LogFile,
        [switch]$FailOnMismatch
    )

    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) { throw 'node no encontrado para comparacion Git=Prod' }

    $script = Join-Path $PSScriptRoot 'aht_git_prod_compare.js'
    if (-not (Test-Path -LiteralPath $script)) {
        throw "Falta $script"
    }

    $out = & node $script --repo $Ctx.RepoRoot 2>&1 | Out-String
    Write-DeployLog -LogFile $LogFile -Message $out.Trim() -ToHost
    if ($LASTEXITCODE -ne 0) {
        if ($FailOnMismatch) { throw 'Checkpoint Git=Prod FALLIDO' }
        return @{ Ok = $false; Output = $out }
    }
    return @{ Ok = $true; Output = $out }
}

function Invoke-AhtVisualRegression {
    param(
        [hashtable]$Ctx,
        [string]$LogFile,
        [string]$TargetUrl = '',
        [switch]$CaptureOnly,
        [switch]$FailOnMismatch
    )

    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) { throw 'node no encontrado para regresion visual' }

    $script = Join-Path $Ctx.RepoRoot 'dev\aht_visual_regression.js'
    if (-not (Test-Path -LiteralPath $script)) {
        throw "Falta $script"
    }

    $args = @($script)
    if ($CaptureOnly) { $args += '--capture-only' }
    if ($TargetUrl) { $args += @('--url', $TargetUrl) }

    $out = & node @args 2>&1 | Out-String
    Write-DeployLog -LogFile $LogFile -Message $out.Trim() -ToHost
    if ($LASTEXITCODE -ne 0) {
        if ($FailOnMismatch) { throw 'Regresion visual FALLIDA' }
        return @{ Ok = $false; Output = $out }
    }
    return @{ Ok = $true; Output = $out }
}
