#Requires -Version 5.1
# Funciones compartidas para deploy incremental/completo (GestorProyectos).
# Sin ejecucion directa. Dot-source desde deploy.ps1 o deploy_incremental.ps1.

function Write-DeployLog {
    param(
        [string]$LogFile,
        [string]$Message,
        [switch]$ToHost
    )
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    if ($ToHost) { Write-Host $line }
    if ($LogFile) { Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8 }
}

function Read-DeployYesNo {
    param([string]$Prompt, [string]$Default = 'N')
    $suffix = if ($Default -eq 'S') { '[S/n]' } else { '[s/N]' }
    $answer = Read-Host "$Prompt $suffix"
    if ([string]::IsNullOrWhiteSpace($answer)) { return ($Default -eq 'S') }
    return ($answer.Trim().ToUpperInvariant() -in @('S', 'SI', 'Y', 'YES'))
}

function Get-DeployTargetFromConfig {
    param([object]$Config, [string]$Branch)
    $branchLower = $Branch.ToLowerInvariant()
    $playtestPattern = [string]$Config.deployTargets.playtest.branchPattern
    if ($playtestPattern -and $branchLower -match $playtestPattern) {
        return $Config.deployTargets.playtest
    }
    if ($Config.deployTargets.canonical) { return $Config.deployTargets.canonical }
    if ($Config.deployTargets.production) { return $Config.deployTargets.production }
    throw 'No hay destino deploy configurado para esta rama.'
}

function Merge-DeployExclusions {
    param([object]$Config, [object]$Target)
    $list = New-Object System.Collections.Generic.List[string]
    if ($Config.deployExclusions) {
        foreach ($e in $Config.deployExclusions) { [void]$list.Add([string]$e) }
    }
    if ($Target.deployExclusions) {
        foreach ($e in $Target.deployExclusions) { [void]$list.Add([string]$e) }
    }
    return $list
}

function Test-DeployExcluded {
    param(
        [string]$RelPath,
        [string[]]$Exclusions
    )
    if ([string]::IsNullOrWhiteSpace($RelPath)) { return $true }
    $rel = $RelPath.Replace('\', '/').TrimStart('/')
    $name = Split-Path -Leaf $rel

    foreach ($raw in $Exclusions) {
        $ex = ([string]$raw).Trim()
        if ([string]::IsNullOrWhiteSpace($ex)) { continue }

        if ($ex.EndsWith('/')) {
            $prefix = $ex.TrimEnd('/')
            if ($rel -eq $prefix -or $rel.StartsWith("$prefix/")) { return $true }
            if ($rel -match "(^|/)$([regex]::Escape($prefix))(/|$)") { return $true }
            continue
        }

        if ($ex -notmatch '[\*\?/\\]') {
            if ($name -eq $ex) { return $true }
            continue
        }

        $regex = '^' + ($ex -replace '\.', '\.' -replace '\*', '.*' -replace '\?', '.') + '$'
        if ($name -match $regex) { return $true }
        if ($rel -match $regex) { return $true }
    }
    return $false
}

function Split-GitOutputLines {
    param([object]$Output)
    if ($null -eq $Output) { return @() }
    $text = ($Output | Out-String).Trim()
    if ([string]::IsNullOrWhiteSpace($text)) { return @() }
    return @($text -split "`r?`n" | ForEach-Object { $_.TrimEnd("`r") } | Where-Object { $_.Length -gt 0 })
}

function Unquote-GitPath {
    param([string]$Raw)
    $s = $Raw.Trim()
    if ($s.Length -ge 2 -and $s[0] -eq '"' -and $s[-1] -eq '"') {
        $inner = $s.Substring(1, $s.Length - 2)
        return $inner -replace '\\(["\\])', '$1'
    }
    return $s
}

function Parse-GitPorcelainEntry {
    param([string]$Line)
    if ($Line.Length -lt 3) { return $null }
    $status = $Line.Substring(0, 2)
    $rest = $Line.Substring(3).TrimEnd("`r").Trim()
    if ([string]::IsNullOrWhiteSpace($rest)) { return $null }

    if ($rest -match ' -> ') {
        $rhs = ($rest -split ' -> ', 2)[1].Trim()
        return @{ Status = $status; Path = (Unquote-GitPath $rhs) }
    }
    return @{ Status = $status; Path = (Unquote-GitPath $rest) }
}

function Test-GitDeletedPorcelainStatus {
    param([string]$Status)
    if ($Status -eq '??' -or $Status -eq '!!') { return $false }
    if ($Status -eq 'AD' -or $Status -eq 'DD') { return $true }
    return ($Status[0] -eq 'D' -or $Status[1] -eq 'D')
}

function Add-GitCandidatePath {
    param(
        [System.Collections.Generic.HashSet[string]]$Paths,
        [string]$Path
    )
    if ([string]::IsNullOrWhiteSpace($Path)) { return }
    [void]$Paths.Add($Path.Replace('\', '/'))
}

function Expand-GitDirectoryCandidate {
    param(
        [string]$RepoRoot,
        [string]$RelDir
    )
    $expanded = New-Object System.Collections.Generic.List[string]
    $dirRel = $RelDir.Replace('\', '/').TrimEnd('/')
    if ([string]::IsNullOrWhiteSpace($dirRel)) { return @() }

    $ls = Invoke-GitQuiet -RepoRoot $RepoRoot -GitArgs @('ls-files', '-o', '--exclude-standard', '-z', '--', $dirRel)
    if ($ls.Code -eq 0) {
        $raw = ($ls.Output | Out-String)
        if ($raw -match "`0") {
            foreach ($fp in ($raw -split "`0")) {
                $fp = $fp.Trim()
                if ($fp) { [void]$expanded.Add($fp.Replace('\', '/')) }
            }
        } else {
            foreach ($line in (Split-GitOutputLines $ls.Output)) {
                [void]$expanded.Add($line.Replace('\', '/'))
            }
        }
    }

    if ($expanded.Count -eq 0) {
        $localDir = Join-Path $RepoRoot ($dirRel -replace '/', '\')
        if (Test-Path -LiteralPath $localDir -PathType Container) {
            Get-ChildItem -LiteralPath $localDir -Recurse -File | ForEach-Object {
                $rel = $_.FullName.Substring($RepoRoot.Length).TrimStart('\').Replace('\', '/')
                $ignored = Invoke-GitQuiet -RepoRoot $RepoRoot -GitArgs @('check-ignore', '-q', '--', $rel)
                if ($ignored.Code -ne 0) {
                    [void]$expanded.Add($rel)
                }
            }
        }
    }

    return @($expanded)
}

function Invoke-GitQuiet {
    param(
        [string]$RepoRoot,
        [string[]]$GitArgs
    )
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'SilentlyContinue'
    try {
        $output = & git -C $RepoRoot @GitArgs 2>$null
        return @{ Output = $output; Code = $LASTEXITCODE }
    } finally {
        $ErrorActionPreference = $prev
    }
}

function Get-GitCandidatePaths {
    param([string]$RepoRoot)
    $paths = New-Object System.Collections.Generic.HashSet[string]
    $deleted = New-Object System.Collections.Generic.HashSet[string]

    # Porcelain -uall: lista cada archivo untracked (no solo la carpeta padre).
    $porcelain = Invoke-GitQuiet -RepoRoot $RepoRoot -GitArgs @('status', '--porcelain', '-uall')
    if ($porcelain.Code -eq 0) {
        foreach ($line in (Split-GitOutputLines $porcelain.Output)) {
            $entry = Parse-GitPorcelainEntry -Line $line
            if (-not $entry -or [string]::IsNullOrWhiteSpace($entry.Path)) { continue }
            if ($entry.Status -eq '!!') { continue }
            if (Test-GitDeletedPorcelainStatus -Status $entry.Status) {
                [void]$deleted.Add($entry.Path.Replace('\', '/'))
                continue
            }
            if ($entry.Status -eq '??' -or $entry.Status -match '[AMRC]') {
                Add-GitCandidatePath -Paths $paths -Path $entry.Path
            }
        }
    }

    $diffOut = Invoke-GitQuiet -RepoRoot $RepoRoot -GitArgs @('diff', '--name-only', 'HEAD')
    if ($diffOut.Code -eq 0) {
        foreach ($line in (Split-GitOutputLines $diffOut.Output)) {
            Add-GitCandidatePath -Paths $paths -Path $line
        }
    }

    $untrackedOut = Invoke-GitQuiet -RepoRoot $RepoRoot -GitArgs @('ls-files', '-o', '--exclude-standard', '-z')
    if ($untrackedOut.Code -eq 0) {
        $raw = ($untrackedOut.Output | Out-String)
        if ($raw -match "`0") {
            foreach ($fp in ($raw -split "`0")) {
                Add-GitCandidatePath -Paths $paths -Path $fp
            }
        } else {
            foreach ($line in (Split-GitOutputLines $untrackedOut.Output)) {
                Add-GitCandidatePath -Paths $paths -Path $line
            }
        }
    }

  # Si porcelain sin -uall dejo solo el directorio, expandir a archivos hijos.
    $dirsOnly = New-Object System.Collections.Generic.List[string]
    foreach ($p in @($paths)) {
        $local = Join-Path $RepoRoot ($p -replace '/', '\')
        if (Test-Path -LiteralPath $local -PathType Container) {
            [void]$dirsOnly.Add($p)
            [void]$paths.Remove($p)
        }
    }
    foreach ($dirRel in $dirsOnly) {
        foreach ($fp in (Expand-GitDirectoryCandidate -RepoRoot $RepoRoot -RelDir $dirRel)) {
            Add-GitCandidatePath -Paths $paths -Path $fp
        }
    }

    foreach ($d in $deleted) { [void]$paths.Remove($d) }

    return @($paths)
}

function Get-IncrementalDeployFiles {
    param(
        [string]$RepoRoot,
        [string]$RemoteRoot,
        [string[]]$Exclusions
    )
    $repoRootFull = (Resolve-Path -LiteralPath $RepoRoot).Path.TrimEnd('\')
    $candidates = Get-GitCandidatePaths -RepoRoot $repoRootFull

    $publishable = New-Object System.Collections.Generic.List[object]
    $skippedGit = New-Object System.Collections.Generic.List[string]
    $skippedMissing = New-Object System.Collections.Generic.List[string]
    $skippedDeleted = New-Object System.Collections.Generic.List[string]

    foreach ($rel in $candidates) {
        $relNorm = $rel.Replace('\', '/')
        if (Test-DeployExcluded -RelPath $relNorm -Exclusions $Exclusions) {
            [void]$skippedGit.Add($relNorm)
            continue
        }
        $local = Join-Path $repoRootFull ($relNorm -replace '/', '\')
        if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
            if (Test-Path -LiteralPath $local -PathType Container) {
                foreach ($fp in (Expand-GitDirectoryCandidate -RepoRoot $repoRootFull -RelDir $relNorm)) {
                    if (Test-DeployExcluded -RelPath $fp -Exclusions $Exclusions) {
                        [void]$skippedGit.Add($fp)
                        continue
                    }
                    $childLocal = Join-Path $repoRootFull ($fp -replace '/', '\')
                    if (Test-Path -LiteralPath $childLocal -PathType Leaf) {
                        [void]$publishable.Add(@{
                            Local = $childLocal
                            Remote = "$RemoteRoot/$fp"
                            Rel = $fp
                        })
                    }
                }
            } else {
                [void]$skippedDeleted.Add($relNorm)
            }
            continue
        }
        $remote = "$RemoteRoot/$relNorm"
        [void]$publishable.Add(@{ Local = $local; Remote = $remote; Rel = $relNorm })
    }

    return @{
        Files = $publishable
        SkippedByRule = $skippedGit
        SkippedMissing = $skippedMissing
        SkippedDeleted = $skippedDeleted
        Candidates = $candidates
    }
}

function Find-WinScpCom {
    param([string]$JsonExplicitPath)
    if (-not [string]::IsNullOrWhiteSpace($JsonExplicitPath) -and (Test-Path -LiteralPath $JsonExplicitPath)) {
        return $JsonExplicitPath
    }
    foreach ($c in @(
        "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com",
        "${env:ProgramFiles(x86)}\WinSCP\WinSCP.com",
        "$env:ProgramFiles\WinSCP\WinSCP.com"
    )) {
        if (Test-Path -LiteralPath $c) { return $c }
    }
    return $null
}

function Get-WinScpOpenLine {
    param([object]$Cfg)
    $encUser = [Uri]::EscapeDataString([string]$Cfg.HOSTALIA_USER)
    $encPass = [Uri]::EscapeDataString([string]$Cfg.HOSTALIA_PASSWORD)
    $hostName = [string]$Cfg.HOSTALIA_HOST
    $port = if ($Cfg.HOSTALIA_PORT) { [int]$Cfg.HOSTALIA_PORT } else { 21 }
    $ftpSecureOpt = ''
    if ($Cfg.PSObject.Properties.Name -contains 'HOSTALIA_FTP_SECURE') {
        $ftpSecureOpt = [string]$Cfg.HOSTALIA_FTP_SECURE.Trim().ToLowerInvariant()
    }
    $protocol = [string]$Cfg.HOSTALIA_PROTOCOL.Trim().ToLowerInvariant()
    $scheme = if ($protocol -eq 'sftp') { 'sftp' }
              elseif ($protocol -eq 'ftpes' -or ($protocol -eq 'ftp' -and $ftpSecureOpt -in @('explicit','explicitssl','tls'))) { 'ftpes' }
              else { 'ftp' }
    $openLine = "open ${scheme}://${encUser}:${encPass}@${hostName}:${port}/"
    if ($scheme -eq 'ftpes') { $openLine += ' -certificate=*' }
    return $openLine
}

function Build-WinScpPutScriptLines {
    param(
        [string]$OpenLine,
        [System.Collections.Generic.List[object]]$Files,
        [string[]]$PostCommands
    )
    $dirs = New-Object 'System.Collections.Generic.HashSet[string]'
    foreach ($f in $Files) {
        $remote = [string]$f.Remote
        $slash = $remote.LastIndexOf('/')
        if ($slash -gt 0) {
            $d = $remote.Substring(0, $slash)
            while ($d.Length -gt 0) {
                [void]$dirs.Add($d)
                $slash = $d.LastIndexOf('/')
                if ($slash -lt 0) { break }
                $d = $d.Substring(0, $slash)
            }
        }
    }
    $sortedDirs = $dirs | Sort-Object { $_.Length }

    $lines = New-Object System.Collections.Generic.List[string]
    $lines.Add('option batch abort')
    $lines.Add('option confirm off')
    $lines.Add('option transfer binary')
    $lines.Add('option reconnecttime 20')
    $lines.Add($OpenLine)
    foreach ($d in $sortedDirs) {
        $lines.Add(('mkdir "' + ($d -replace '"','""') + '"'))
    }
    foreach ($f in $Files) {
        $local = [string]$f.Local
        $remote = [string]$f.Remote
        $lines.Add('put "' + ($local -replace '"','""') + '" "' + ($remote -replace '"','""') + '"')
    }
    if ($PostCommands) {
        foreach ($cmd in $PostCommands) {
            if ($cmd) { $lines.Add([string]$cmd) }
        }
    }
    $lines.Add('exit')
    return $lines
}

function Test-PublicUrl {
    param([string]$Url)
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
