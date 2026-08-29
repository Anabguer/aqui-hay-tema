#Requires -Version 5.1
# Guardia: piel modal lavanda canonica (evitar regresiones vainilla/scrapbook).
param(
    [switch]$ProdCheck,
    [string]$ProdUrl = 'https://intocables13.com/juegos/aqui-hay-tema'
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir
$fail = 0

function Fail([string]$Msg) {
    Write-Host "FAIL: $Msg" -ForegroundColor Red
    $script:fail++
}

function Ok([string]$Msg) {
    Write-Host "OK: $Msg" -ForegroundColor Green
}

$shellRel = 'assets/css/design-system/modals-shell-lavanda-mobile.css'
$shellPath = Join-Path $RepoRoot ($shellRel -replace '/', '\')
$tokensPath = Join-Path $RepoRoot 'assets\css\design-system\tokens.css'
$playPath = Join-Path $RepoRoot 'play.php'

Write-Host '=== AHT GUARD: modal shell lavanda ===' -ForegroundColor Cyan

if (-not (Test-Path -LiteralPath $shellPath)) {
    Fail "Falta $shellRel"
} else {
    $css = Get-Content -LiteralPath $shellPath -Raw -Encoding UTF8
    if ($css -notmatch 'MODALS-SHELL-LAVANDA-CANON-v3') {
        Fail "$shellRel sin marcador MODALS-SHELL-LAVANDA-CANON-v3"
    } else {
        Ok 'Marcador CANON-v3 presente'
    }
    if ($css -match '@media \(max-width: 768px\)\s*\{[^}]*play-root\.phone\[data-capa="buzon"\]' -and
        $css -notmatch '\.play-root\[data-capa="buzon"\]') {
        Fail "$shellRel parece solo-movil (.phone dentro de media query, sin desktop)"
    } else {
        Ok 'Selectores incluyen desktop (.play-root[data-capa])'
    }
}

if (-not (Test-Path -LiteralPath $tokensPath)) {
    Fail 'Falta tokens.css'
} else {
    $tokens = Get-Content -LiteralPath $tokensPath -Raw -Encoding UTF8
    if ($tokens -notmatch '--modal-shell-bg:\s*#FCFBFE') {
        Fail 'tokens.css sin --modal-shell-bg: #FCFBFE'
    } else {
        Ok 'Variable --modal-shell-bg en tokens'
    }
}

if (-not (Test-Path -LiteralPath $playPath)) {
    Fail 'Falta play.php'
} else {
    $play = Get-Content -LiteralPath $playPath -Raw -Encoding UTF8
    if ($play -notmatch 'modals-shell-lavanda-mobile\.css') {
        Fail 'play.php no enlaza modals-shell-lavanda-mobile.css'
    } else {
        Ok 'play.php enlaza hoja lavanda'
    }
    $sec = [regex]::Match($play, 'modals-secondary-unified\.css[^>]+/>').Index
    $lav = [regex]::Match($play, 'modals-shell-lavanda-mobile\.css[^>]+/>').Index
    if ($sec -ge 0 -and $lav -ge 0 -and $lav -lt $sec) {
        Fail 'Orden incorrecto: lavanda debe ir DESPUES de modals-secondary-unified'
    } else {
        Ok 'Orden de carga correcto (secondary -> lavanda)'
    }
}

if ($ProdCheck) {
    try {
        $buster = (Invoke-WebRequest -Uri "$ProdUrl/assets/aht-cache-buster.txt" -UseBasicParsing -TimeoutSec 30).Content.Trim()
        $url = "${ProdUrl}/${shellRel}?v=${buster}"
        $prodCss = (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 30).Content
        if ($prodCss -notmatch 'MODALS-SHELL-LAVANDA-CANON-v3') {
            Fail "Produccion sin CANON-v3 en $url"
        } else {
            Ok 'Produccion tiene CANON-v3'
        }
    } catch {
        Fail "No se pudo leer CSS en produccion: $($_.Exception.Message)"
    }
}

if ($fail -gt 0) {
    Write-Host "`nGUARDIA FALLIDA ($fail error/es). Repara antes de deploy." -ForegroundColor Red
    exit 1
}

Write-Host "`nGUARDIA OK" -ForegroundColor Green
exit 0
