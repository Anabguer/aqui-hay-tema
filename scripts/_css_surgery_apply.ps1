$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot '..')
& (Join-Path $PWD "dev\_migrate_capa_css.ps1")
Get-ChildItem assets/css -Recurse -Filter *.css | Where-Object { $_.FullName -notmatch 'capas\.css|capas-shell|modal-core|modals-shell|modals-secondary|visual-|play-v3-ficha|ficha-neni' } | ForEach-Object {
  $c = [IO.File]::ReadAllText($_.FullName); $o = $c
  $c = $c -replace '\.capa:not\(\)', '.aht-screen'
  $c = $c -replace '\.play-v3 \.capa ', '.play-v3 .aht-screen '
  $c = $c -replace '\.play-root\.pc \.capa\b', '.play-root.pc .aht-screen'
  $c = $c -replace '\.play-root\.phone \.capa\b', '.play-root.phone .aht-screen'
  if ($c -ne $o) { [IO.File]::WriteAllText($_.FullName, $c, [Text.UTF8Encoding]::new($false)) }
}
$f = 'assets/css/play-v3-app.css'
$c = [IO.File]::ReadAllText($f)
$c = $c -replace '(?m)^\.capa\s*\{', '.aht-screen {'
$c = $c -replace '(?m)^\.capa h2', '.aht-screen h2'
$c = $c -replace '(?m)^\.capa p', '.aht-screen p'
[IO.File]::WriteAllText($f, $c, [Text.UTF8Encoding]::new($false))
$f = 'assets/css/play-v3-responsive.css'
$c = [IO.File]::ReadAllText($f)
[IO.File]::WriteAllText($f, $c.Replace('.game-map-wrap .play-root .capa', '.game-map-wrap .play-root .aht-screen'), [Text.UTF8Encoding]::new($false))
$f = 'assets/css/play-v3-bloques-residencias.css'
$c = [IO.File]::ReadAllText($f)
$c = $c.Replace('capa-cerrar-pesta?a', 'capa-cerrar-pestaña').Replace('.aht-screen .capa-cerrar-pestaña', '.capa-cerrar-pestaña')
[IO.File]::WriteAllText($f, $c, [Text.UTF8Encoding]::new($false))
$tv = 'assets/css/v4/tokens-v4.css'
$c = [IO.File]::ReadAllText($tv)
if ($c -notmatch 'aht-shell-bg') {
  $c = $c.Replace('--aht-bg: #FDFBFE;', "--aht-bg: #FDFBFE;`n  --aht-shell-bg: #FCFBFE;")
  [IO.File]::WriteAllText($tv, $c, [Text.UTF8Encoding]::new($false))
}
$sf = 'assets/css/v4/screen-frame.css'
$c = [IO.File]::ReadAllText($sf)
if ($c -notmatch 'AHT-FRAME-CANON-v4') {
  $ins = @"

.play-v3:has(.game-shell) .play-root[data-capa]:not([data-capa=""]) .velo,
.play-v3:has(.game-shell) .play-root[data-consulta]:not([data-consulta=""]) .velo {
  background-color: var(--aht-overlay, rgba(38, 31, 47, 0.45));
}
.play-v3:has(.game-shell) .play-root[data-capa] .aht-screen > .ficha-tape,
.play-v3:has(.game-shell) .play-root[data-capa] .aht-screen > .mis-pin,
.play-v3:has(.game-shell) .play-root[data-capa] .aht-screen > .org-pin,
.play-v3:has(.game-shell) .play-root[data-capa] .aht-screen > .agenda-pin { display: none; }

"@
  $c = "/* AHT-FRAME-CANON-v4 */`n" + $ins + $c
  $c = $c.Replace('background-color: var(--aht-surface-raised, #FFFFFF);', 'background-color: var(--aht-shell-bg, #FCFBFE);')
  [IO.File]::WriteAllText($sf, $c, [Text.UTF8Encoding]::new($false))
}
$v = '<?= htmlspecialchars($ahtUi, ENT_QUOTES, ''UTF-8'') ?>'
$new = @(
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/tokens.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/v4/tokens-v4.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-app.css?v=$v`"/>",
  "  <?php /* CANON: inicio-views */ ?>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/screens/inicio-views.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-historia.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-shell-ui.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-shell-art.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-mensajitos.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-mapa-canonico.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-bloques-residencias.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-musica.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-audio.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-regalos.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-lab.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-responsive.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/components.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/v4/screen-frame.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-cotilleos.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-vecinos.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-organizar.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-agenda.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-misiones.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-vida.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-enc-int.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-notas-mapa.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-tutorial-ds.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-avisos.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-desktop-shell.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-inicio-override.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-consulta-edificio-v2.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/play-v3-tutorial-lavanda.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/mensajitos-cartas-persona-v1.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/mensajitos-carta-regalo-v1.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/mensajitos-body.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/vecinos-body.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/v4/screens-secondary.css?v=$v`"/>",
  "  <?php /* V4 ultima autoridad pantallas */ ?>",
  "  <link rel=`"stylesheet`" href=`"assets/css/v4/screens.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/screens/inicio-mobile.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/screens/inicio-evento-pueblo-mobile.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/legibilidad-global.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/screens/inicio-desktop.css?v=$v`"/>",
  "  <?php /* cromatica desktop */ ?>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/screens/inicio-desktop-cromatica.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/screens/inicio-evento-pueblo-desktop.css?v=$v`"/>",
  "  <link rel=`"stylesheet`" href=`"assets/css/design-system/typography-reading.css?v=$v`"/>"
)
$lines = [IO.File]::ReadAllLines("$PWD\play.php")
$si = 0
for ($i = 0; $i -lt $lines.Length; $i++) { if ($lines[$i] -match '^\s*<style>') { $si = $i; break } }
$out = $lines[0..30] + $new + $lines[$si..($lines.Length - 1)]
$text = ($out -join "`n") + "`n"
$text = $text.Replace('.capa-vecinos .vecino img', '.aht-screen[data-aht-screen="vecinos"] .vecino img')
$text = $text.Replace('.capa-ficha .ficha-sabes-ico', '.aht-screen[data-aht-screen="ficha"] .ficha-sabes-ico')
[IO.File]::WriteAllText("$PWD\play.php", $text, [Text.UTF8Encoding]::new($false))
$del = @(
  'assets/css/play-v3-ficha.css','assets/css/design-system/ficha-neni-ref-v1.css',
  'assets/css/play-v3-visual-review.css','assets/css/play-v3-visual-interior.css','assets/css/play-v3-visual-replica.css',
  'assets/css/play-v3-capas.css','assets/css/play-v3-capas-shell.css',
  'assets/css/design-system/screens/capas-ds.css','assets/css/design-system/screens/modals.css',
  'assets/css/design-system/modal-core.css','assets/css/design-system/modal-skin.css',
  'assets/css/design-system/modal-header.css','assets/css/design-system/modal-responsive.css',
  'assets/css/design-system/modal-catalog.css','assets/css/design-system/modal-ds.css',
  'assets/css/design-system/modals-secondary-unified.css','assets/css/design-system/modals-shell-lavanda-mobile.css',
  'assets/css/design-system/modal-titles-aht.css'
)
foreach ($p in $del) { if (Test-Path $p) { Remove-Item $p -Force } }
Write-Host 'CSS surgery apply OK'

