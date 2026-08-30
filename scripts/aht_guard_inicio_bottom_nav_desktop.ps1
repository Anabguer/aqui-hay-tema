#Requires -Version 5.1
# Guardia: bottom nav desktop visible (dentro de .inicio-map-host).
param(
    [string]$RepoRoot = ''
)

$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($RepoRoot)) {
    $RepoRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
}

$fail = 0
function Fail([string]$Msg) {
    Write-Host "FAIL: $Msg" -ForegroundColor Red
    $script:fail++
}
function Ok([string]$Msg) {
    Write-Host "OK: $Msg" -ForegroundColor Green
}

$desktopRel = 'assets/css/design-system/screens/inicio-desktop.css'
$cromaticaRel = 'assets/css/design-system/screens/inicio-desktop-cromatica.css'
$playRel = 'play.php'
$desktopPath = Join-Path $RepoRoot ($desktopRel -replace '/', '\')
$cromaticaPath = Join-Path $RepoRoot ($cromaticaRel -replace '/', '\')
$playPath = Join-Path $RepoRoot $playRel

Write-Host '=== AHT GUARD: inicio bottom nav desktop ===' -ForegroundColor Cyan

foreach ($pair in @(
    @{ Rel = $desktopRel; Path = $desktopPath },
    @{ Rel = $cromaticaRel; Path = $cromaticaPath }
)) {
    if (-not (Test-Path -LiteralPath $pair.Path)) {
        Fail "Falta $($pair.Rel)"
        continue
    }
}

if ($fail -gt 0) { exit 1 }

$desktop = Get-Content -LiteralPath $desktopPath -Raw -Encoding UTF8
$cromatica = Get-Content -LiteralPath $cromaticaPath -Raw -Encoding UTF8
$play = Get-Content -LiteralPath $playPath -Raw -Encoding UTF8

if ($desktop -match '\.inicio-map-host \.play-bottom-nav,\s*\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-stage > \.play-bottom-nav::before') {
    Fail "$desktopRel tiene selector roto (::before aplicado al nav en map-host)"
} else {
    Ok 'Selector ::before de nav no aplica al elemento map-host'
}

$canonNav = '.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-map-host .play-bottom-nav'
if ($desktop -notmatch [regex]::Escape($canonNav)) {
    Fail "$desktopRel no referencia $canonNav"
} else {
    Ok 'Selector canonico map-host-nav presente en inicio-desktop.css'
}

$mapHostIdx = $play.IndexOf('class="inicio-map-host')
$navIdx = $play.IndexOf('class="play-bottom-nav"')
$desktopIdx = $play.IndexOf('class="inicio-desktop"')
if ($mapHostIdx -lt 0 -or $navIdx -lt 0 -or $desktopIdx -lt 0) {
    Fail 'play.php: no se localizan map-host, nav o inicio-desktop'
} elseif ($navIdx -le $mapHostIdx -or $navIdx -ge $desktopIdx) {
    Fail "${playRel}: .play-bottom-nav debe estar dentro de .inicio-map-host (antes de inicio-desktop)"
} else {
    Ok 'play.php: nav dentro de inicio-map-host'
}

if ($cromatica -notmatch 'INICIO-BOTTOM-NAV-DESKTOP-v134') {
    Fail "$cromaticaRel sin marcador INICIO-BOTTOM-NAV-DESKTOP-v134"
} else {
    Ok 'Marcador INICIO-BOTTOM-NAV-DESKTOP-v134 presente'
}

if ($cromatica -notmatch 'display:\s*flex\s*!important') {
    Fail "$cromaticaRel sin display:flex blindado para bottom nav"
} else {
    Ok 'display:flex blindado en cromatica'
}

if ($play -notmatch 'class="play-bottom-nav"') {
    Fail "$playRel sin nav .play-bottom-nav"
} else {
    Ok 'play.php contiene .play-bottom-nav'
}

if ($play -notmatch 'inicio-desktop-cromatica\.css') {
    Fail "$playRel no enlaza inicio-desktop-cromatica.css"
} else {
    Ok 'play.php enlaza cromatica desktop'
}

if ($fail -gt 0) {
    Write-Host "GUARD FAIL ($fail)" -ForegroundColor Red
    exit 1
}

Write-Host 'GUARD OK' -ForegroundColor Green
exit 0
