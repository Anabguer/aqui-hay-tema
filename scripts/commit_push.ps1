#Requires -Version 5.1
param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir
$LogsDir = Join-Path $RepoRoot 'logs'

if (-not (Test-Path -LiteralPath $LogsDir)) {
    New-Item -ItemType Directory -Path $LogsDir -Force | Out-Null
}

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$LogFile = Join-Path $LogsDir "commit-push-$ts.log"

function Write-Log([string]$Message, [switch]$ToHost) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"

    if ($ToHost) {
        Write-Host $line
    }

    Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8
}

function Invoke-Git {
    param([string[]]$GitArgs)

    $previousErrorActionPreference = $ErrorActionPreference

    try {
        # Git puede escribir warnings inocuos por stderr
        # (por ejemplo LF -> CRLF).
        # No deben convertirse en una excepción de PowerShell.
        $ErrorActionPreference = 'Continue'
        $output = & git -C $RepoRoot @GitArgs 2>&1
        $code = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    return @{
        Output = ($output | Out-String).TrimEnd()
        Code   = $code
    }
}

function Read-YesNo([string]$Prompt, [string]$Default = 'N') {
    $suffix = if ($Default -eq 'S') { '[S/n]' } else { '[s/N]' }
    $answer = Read-Host "$Prompt $suffix"

    if ([string]::IsNullOrWhiteSpace($answer)) {
        return ($Default -eq 'S')
    }

    return ($answer.Trim().ToUpperInvariant() -in @('S', 'SI', 'Y', 'YES'))
}

Write-Log '=== COMMIT + PUSH (Aqui Hay Tema) ===' -ToHost
Write-Log "Repo: $RepoRoot" -ToHost

if (-not (Test-Path -LiteralPath (Join-Path $RepoRoot '.git'))) {
    Write-Log 'ERROR: No es un repositorio Git.' -ToHost
    exit 1
}

$branchResult = Invoke-Git @('branch', '--show-current')

if ($branchResult.Code -ne 0) {
    Write-Log "ERROR obteniendo rama: $($branchResult.Output)" -ToHost
    exit $branchResult.Code
}

$branch = $branchResult.Output

$upstreamResult = Invoke-Git @(
    'rev-parse',
    '--abbrev-ref',
    '--symbolic-full-name',
    '@{u}'
)

if ($upstreamResult.Code -ne 0) {
    Write-Log "ERROR obteniendo upstream: $($upstreamResult.Output)" -ToHost
    exit $upstreamResult.Code
}

$upstream = $upstreamResult.Output
$remote = 'origin'

if ($upstream -match '^([^/]+)/') {
    $remote = $Matches[1]
}

Write-Log "Rama: $branch" -ToHost
Write-Log "Upstream: $upstream" -ToHost
Write-Log "Remote push: $remote" -ToHost

$status = Invoke-Git @('status', '--short')

if ($status.Code -ne 0) {
    Write-Log "ERROR git status: $($status.Output)" -ToHost
    exit $status.Code
}

Write-Log '' -ToHost
Write-Log '--- Cambios (git status --short) ---' -ToHost

if ([string]::IsNullOrWhiteSpace($status.Output)) {
    Write-Log 'No hay cambios que commitear.' -ToHost
    exit 0
}

Write-Host $status.Output
Write-Log $status.Output

$diffStat = Invoke-Git @('diff', '--stat')
$diffCached = Invoke-Git @('diff', '--cached', '--stat')

Write-Log '' -ToHost
Write-Log '--- Diff sin stage (stat) ---' -ToHost

if ($diffStat.Output) {
    Write-Host $diffStat.Output
    Write-Log $diffStat.Output
}

Write-Log '--- Diff en stage (stat) ---' -ToHost

if ($diffCached.Output) {
    Write-Host $diffCached.Output
    Write-Log $diffCached.Output
}

$dryAdd = Invoke-Git @('add', '-n', '-A', '--', '.')

if ($dryAdd.Code -ne 0) {
    Write-Log "ERROR comprobando archivos para stage: $($dryAdd.Output)" -ToHost
    exit $dryAdd.Code
}

Write-Log '' -ToHost
Write-Log '--- Archivos que entrarian (respeta .gitignore) ---' -ToHost

if ([string]::IsNullOrWhiteSpace($dryAdd.Output)) {
    Write-Log 'Nada nuevo que anadir tras aplicar .gitignore.' -ToHost
}
else {
    Write-Host $dryAdd.Output
    Write-Log $dryAdd.Output
}

if ($DryRun) {
    Write-Log 'DRY-RUN: no se hara commit ni push.' -ToHost
    Write-Log "Log: $LogFile" -ToHost
    exit 0
}

if (-not (Read-YesNo 'Continuar con commit y push?')) {
    Write-Log 'Cancelado por el usuario.' -ToHost
    exit 0
}

$message = Read-Host 'Mensaje de commit'

while ([string]::IsNullOrWhiteSpace($message)) {
    Write-Host 'El mensaje no puede estar vacio.'
    $message = Read-Host 'Mensaje de commit'
}

$message = $message.Trim()
Write-Log "Mensaje: $message"

$add = Invoke-Git @('add', '-A', '--', '.')

if ($add.Code -ne 0) {
    Write-Log "ERROR git add: $($add.Output)" -ToHost
    exit $add.Code
}

$staged = Invoke-Git @('diff', '--cached', '--name-status')

Write-Log '' -ToHost
Write-Log '--- Staged final ---' -ToHost

Write-Host $staged.Output
Write-Log $staged.Output

if ([string]::IsNullOrWhiteSpace($staged.Output)) {
    Write-Log 'No hay cambios staged. Abortando.' -ToHost
    exit 1
}

if (-not (Read-YesNo 'Confirmar commit y push ahora?')) {
    Write-Log 'Cancelado antes del commit.' -ToHost
    exit 0
}

$commit = Invoke-Git @('commit', '-m', $message)

Write-Host $commit.Output
Write-Log $commit.Output

if ($commit.Code -ne 0) {
    Write-Log "ERROR git commit (codigo $($commit.Code))" -ToHost
    exit $commit.Code
}

$hashResult = Invoke-Git @('rev-parse', '--short', 'HEAD')

if ($hashResult.Code -ne 0) {
    Write-Log "ERROR obteniendo SHA: $($hashResult.Output)" -ToHost
    exit $hashResult.Code
}

$hash = $hashResult.Output
Write-Log "Commit: $hash" -ToHost

$push = Invoke-Git @('push', $remote, 'HEAD')

Write-Host $push.Output
Write-Log $push.Output

if ($push.Code -ne 0) {
    Write-Log "ERROR git push (codigo $($push.Code))" -ToHost
    exit $push.Code
}

Write-Log 'OK: commit y push completados.' -ToHost
Write-Log "Log: $LogFile" -ToHost

exit 0