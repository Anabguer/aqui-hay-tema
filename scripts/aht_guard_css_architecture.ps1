#Requires -Version 5.1
param([switch]$ProdCheck,[string]$ProdUrl='https://intocables13.com/juegos/aqui-hay-tema')
$ErrorActionPreference='Stop'
$RepoRoot=Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$fail=0
function Fail($m){Write-Host "FAIL: $m" -ForegroundColor Red;$script:fail++}
function Ok($m){Write-Host "OK: $m" -ForegroundColor Green}
Write-Host '=== AHT GUARD: CSS architecture (zero !important) ===' -ForegroundColor Cyan
$play=Join-Path $RepoRoot 'play.php'
$p=Get-Content $play -Raw
$legacy=@(
 'modals-shell-lavanda-mobile.css','modal-core.css','play-v3-capas.css','modals-secondary-unified.css',
 'screens-secondary.css','play-v3-inicio-override.css','design-system/screens/inicio-mobile.css',
 'mensajitos-cartas-persona-v1.css','mensajitos-carta-regalo-v1.css'
)
foreach($f in $legacy){if($p -match [regex]::Escape($f)){Fail "play enlaza legacy $f"}}
$required=@('v4/tokens-v4.css','v4/screen-frame.css','v4/screens.css','inicio/inicio-base.css','v4/bodies/parejas.css','design-system/mensajitos-body.css')
foreach($f in $required){if($p -notmatch $f){Fail "falta $f"}else{Ok $f}}
$frame=Get-Content (Join-Path $RepoRoot 'assets/css/v4/screen-frame.css') -Raw
if($frame -notmatch 'AHT-FRAME-CANON-v4'){Fail 'sin marcador frame'}else{Ok 'marcador frame'}
$cssImp=0
$impFiles=@()
Get-ChildItem (Join-Path $RepoRoot 'assets/css') -Recurse -Filter *.css | ForEach-Object {
  $n=([regex]::Matches((Get-Content $_.FullName -Raw),'!important')).Count
  if($n -gt 0){$cssImp+=$n;$impFiles+=("$($_.FullName.Replace('\','/')):$n")}
}
if($cssImp -eq 0){Ok 'assets/css !important total 0'}else{Fail "assets/css !important $cssImp ($($impFiles -join ', '))"}
$inlineImp=([regex]::Matches($p,'!important')).Count
if($inlineImp -eq 0){Ok 'play.php inline !important 0'}else{Fail "play.php inline !important $inlineImp"}
$cssFiles=Get-ChildItem (Join-Path $RepoRoot 'assets/css') -Recurse -Filter *.css
foreach($f in $cssFiles){
  $c=Get-Content $f.FullName -Raw
  if($c -match '\.capa-(?!scroll|cerrar)[a-z][a-z0-9_-]*'){Fail "selector .capa- legacy en $($f.Name)"}
}
Ok 'sin .capa- wrapper en CSS'
$node=Get-Command node -ErrorAction SilentlyContinue
if($node){& node (Join-Path $RepoRoot 'tests/css_architecture_test.js');if($LASTEXITCODE){$fail++}}
if($fail){exit 1};Write-Host 'GUARD OK' -ForegroundColor Green
