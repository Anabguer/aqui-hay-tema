#Requires -Version 5.1
param([switch]$ProdCheck,[string]$ProdUrl='https://intocables13.com/juegos/aqui-hay-tema')
$ErrorActionPreference='Stop'
$RepoRoot=Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$fail=0
function Fail($m){Write-Host "FAIL: $m" -ForegroundColor Red;$script:fail++}
function Ok($m){Write-Host "OK: $m" -ForegroundColor Green}
Write-Host '=== AHT GUARD: CSS architecture V4 + inicio ===' -ForegroundColor Cyan
$play=Join-Path $RepoRoot 'play.php'
$p=Get-Content $play -Raw
$legacy=@(
 'modals-shell-lavanda-mobile.css','modal-core.css','play-v3-capas.css','modals-secondary-unified.css',
 'screens-secondary.css','play-v3-inicio-override.css','inicio-desktop-cromatica.css','design-system/screens/inicio-mobile.css'
)
foreach($f in $legacy){if($p -match $f){Fail "play enlaza legacy $f"}}
$required=@('v4/tokens-v4.css','v4/screen-frame.css','v4/screens.css','inicio/inicio-base.css','v4/bodies/parejas.css')
foreach($f in $required){if($p -notmatch $f){Fail "falta $f"}else{Ok $f}}
$frame=Get-Content (Join-Path $RepoRoot 'assets/css/v4/screen-frame.css') -Raw
if($frame -notmatch 'AHT-FRAME-CANON-v4'){Fail 'sin marcador frame'}else{Ok 'marcador frame'}
$v4imp=0
Get-ChildItem (Join-Path $RepoRoot 'assets/css/v4') -Recurse -Filter *.css | ForEach-Object {
  $c=Get-Content $_.FullName -Raw
  $n=([regex]::Matches($c,'!important')).Count
  $v4imp+=$n
  if($_.Name -eq 'screens.css' -and $n -gt 5){Fail "screens.css >5 !important ($n)"}
  elseif($_.Name -ne 'screens.css' -and $n -gt 0){Fail "$($_.FullName) tiene $n !important"}
}
if($v4imp -le 5){Ok "v4 !important total $v4imp"}
$inicioImp=0
Get-ChildItem (Join-Path $RepoRoot 'assets/css/inicio') -Filter *.css | ForEach-Object {
  $inicioImp+=([regex]::Matches((Get-Content $_.FullName -Raw),'!important')).Count
}
if($inicioImp -gt 100){Fail "inicio !important $inicioImp > 100"}else{Ok "inicio !important $inicioImp"}
$cssFiles=Get-ChildItem (Join-Path $RepoRoot 'assets/css') -Recurse -Filter *.css
foreach($f in $cssFiles){
  $c=Get-Content $f.FullName -Raw
  if($c -match '\.capa-(?!scroll|cerrar)[a-z][a-z0-9_-]*'){Fail "selector .capa- legacy en $($f.Name)"}
}
Ok 'sin .capa- wrapper en CSS'
$node=Get-Command node -ErrorAction SilentlyContinue
if($node){& node (Join-Path $RepoRoot 'tests/css_architecture_test.js');if($LASTEXITCODE){$fail++}}
if($fail){exit 1};Write-Host 'GUARD OK' -ForegroundColor Green
