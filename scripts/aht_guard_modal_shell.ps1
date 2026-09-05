#Requires -Version 5.1
param([switch]$ProdCheck,[string]$ProdUrl='https://intocables13.com/juegos/aqui-hay-tema')
$ErrorActionPreference='Stop'
$RepoRoot=Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$fail=0
function Fail($m){Write-Host "FAIL: $m" -ForegroundColor Red;$script:fail++}
function Ok($m){Write-Host "OK: $m" -ForegroundColor Green}
Write-Host '=== AHT GUARD: V4 screen frame ===' -ForegroundColor Cyan
$frame=Join-Path $RepoRoot 'assets/css/v4/screen-frame.css'
$tokens=Join-Path $RepoRoot 'assets/css/v4/tokens-v4.css'
$play=Join-Path $RepoRoot 'play.php'
$css=Get-Content $frame -Raw
if($css -notmatch 'AHT-FRAME-CANON-v4'){Fail 'screen-frame sin AHT-FRAME-CANON-v4'}else{Ok 'marcador frame'}
$t=Get-Content $tokens -Raw
if($t -notmatch '--aht-shell-bg:\s*#FCFBFE'){Fail 'tokens-v4 sin shell'}else{Ok 'token shell'}
$p=Get-Content $play -Raw
@('modals-shell-lavanda-mobile.css','modal-core.css','play-v3-capas.css','modals-secondary-unified.css')|%{if($p -match $_){Fail "play enlaza $_"}}
if($p -notmatch 'v4/screens\.css'){Fail 'falta screens.css'}else{Ok 'play V4'}
if($fail){exit 1};Write-Host 'GUARD OK' -ForegroundColor Green