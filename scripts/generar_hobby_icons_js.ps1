$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$iconsDir = Join-Path $root 'assets\icons\hobbies'
$outFile = Join-Path $root 'assets\js\hobby-icons.js'
$catalogoPath = Join-Path $root 'data\catalogos\aficiones.json'

$cat = Get-Content -Raw -LiteralPath $catalogoPath | ConvertFrom-Json
$ids = @($cat.items | ForEach-Object { [string]$_.id })
if ($ids.Count -eq 0) { throw 'Catalogo aficiones vacio' }

$entries = New-Object System.Collections.Generic.List[string]
$vistos = @()

foreach ($id in $ids) {
  $file = Join-Path $iconsDir ("hobby-{0}.svg" -f $id)
  if (-not (Test-Path -LiteralPath $file)) { throw ("Falta SVG para id canonico '{0}': {1}" -f $id, $file) }
  $raw = (Get-Content -Raw -LiteralPath $file).Trim()
  if ($raw -notmatch '(?s)^<svg\b[^>]*>(.*)</svg>$') { throw ("SVG con formato inesperado: " + $file) }
  $inner = $Matches[1].Trim()
  if ($inner -match '<text|<image|href=|font-family') { throw ("Contenido no permitido en: " + $file) }
  $json = ConvertTo-Json -InputObject $inner -Compress
  [void]$entries.Add('    ' + (ConvertTo-Json -InputObject $id -Compress) + ': ' + $json)
  $vistos += $id
}

$sobran = Get-ChildItem -LiteralPath $iconsDir -Filter 'hobby-*.svg' | Where-Object {
  $_.BaseName -replace '^hobby-', '' | Where-Object { $ids -notcontains $_ }
}
if ($sobran) { throw ("SVGs fuera de catalogo en assets/icons/hobbies: " + (($sobran | ForEach-Object Name) -join ', ')) }

$body = $entries -join ",`r`n"

$js = @"
/* ============================================================
   GENERADO AUTOMATICAMENTE - NO EDITAR A MANO.
   Fuente canonica: assets/icons/hobbies/hobby-<id>.svg
   Ids: data/catalogos/aficiones.json
   Regenerar: powershell -NoProfile -ExecutionPolicy Bypass -File scripts/generar_hobby_icons_js.ps1
   Lote 17 aprobado (estilo tinta + max. 1 pastel; tipografia SIEMPRE
   fuera del SVG: la ficha pinta el nombre como HTML/Caveat).
   Consumidor actual: play-v3.js svgHobbyIcon() con fallback legacy.
   Pendiente: consumo definitivo por Ficha de Vecino del Design System.
   ============================================================ */
(function (global) {
  'use strict';
  var ICONS = {
$body
  };

  global.AHTHobbyIcons = {
    ids: function () { return Object.keys(ICONS); },
    has: function (id) {
      return Object.prototype.hasOwnProperty.call(ICONS, String(id == null ? '' : id));
    },
    get: function (id) {
      return this.has(id) ? ICONS[String(id)] : null;
    },
    svg: function (id) {
      if (!this.has(id)) return null;
      return '<svg class="ficha-hobby-svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false">' +
        ICONS[String(id)] + '</svg>';
    }
  };
})(typeof window !== 'undefined' ? window : this);
"@

[System.IO.File]::WriteAllText($outFile, $js, (New-Object System.Text.UTF8Encoding($false)))
Write-Output ("Generado " + $outFile + " con " + $vistos.Count + " iconos")
