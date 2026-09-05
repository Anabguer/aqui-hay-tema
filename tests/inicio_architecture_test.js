'use strict';
/* INICIO-ARCHITECTURE — 7 tests de independencia móvil/desktop */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const mobCss = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mobile.css'), 'utf8');
const deskCss = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-desktop.css'), 'utf8');
const viewsCss = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-views.css'), 'utf8');

let failures = 0;
function ok(cond, msg) {
  console.log((cond ? 'OK' : 'FAIL') + ': ' + msg);
  if (!cond) failures++;
}

// 1. CSS móvil no selecciona .inicio-desktop (salvo orden/visibilidad en .inicio-stage)
const mobDeskRefs = mobCss.match(/\.inicio-desktop[\s,{.:]/g) || [];
const mobDeskBad = mobDeskRefs.filter(function (_, i, arr) {
  return !/\.inicio-stage\s*>\s*\.inicio-desktop/.test(mobCss);
});
ok(mobDeskBad.length === 0 || /\.inicio-stage\s*>\s*\.inicio-desktop\s*\{\s*order:/.test(mobCss),
  'mobile CSS no referencia .inicio-desktop (excepto order stage)');

// 2. CSS desktop no selecciona .inicio-mobile
ok(!/\.inicio-mobile[\s,{.:]/.test(deskCss), 'desktop CSS no referencia .inicio-mobile');

// 3. Contador mensajitos compartido (inicioAll)
ok(/function inicioAll/.test(js) && /inicioAll\('\[data-buzon-badge\]'\)/.test(js),
  'JS: badges buzón vía inicioAll (misma fuente)');

// 4. Estado vecinos compartido
ok(/setAllText\('\[data-vecinos-poblacion\]'/.test(js) && /buildInicioViewModel/.test(js),
  'JS: población vecinos vía view model + setAllText');

// 5. Acciones compartidas data-open en ambas vistas
const openBuzonMob = (php.match(/class="inicio-mobile[\s\S]*?data-open="buzon"/g) || []).length;
const openBuzonDesk = (php.match(/class="inicio-desktop[\s\S]*?data-open="buzon"/g) || []).length;
const openMisionesMob = (php.match(/class="inicio-mobile[\s\S]*?data-open="misiones"/g) || []).length;
const openMisionesDesk = (php.match(/class="inicio-desktop[\s\S]*?data-open="misiones"/g) || []).length;
ok(openBuzonMob >= 1 && openBuzonDesk >= 1, 'PHP: data-open buzón en móvil y desktop');
ok(openMisionesMob >= 1 && openMisionesDesk >= 1, 'PHP: data-open misiones en móvil y desktop');

// 6. Sin IDs duplicados problemáticos
ok(!/id="mob-misiones"/.test(php) && !/id="mob-parejas"/.test(php),
  'PHP: sin id mob-misiones / mob-parejas');
ok(/data-inicio-misiones/.test(php) && /data-inicio-parejas/.test(php),
  'PHP: data-inicio-misiones y data-inicio-parejas presentes');

// 7. Sin reparenting DOM por viewport
ok(!/appendChild\([^)]*game-(left|right)/.test(js) &&
  !/esInicioLayoutMovil\(\)[\s\S]{0,80}appendChild/.test(js),
  'JS: sin reparenting game-left/right por viewport');

// Estructura mínima
ok(/class="inicio-stage"/.test(php), 'PHP: inicio-stage');
ok((php.match(/class="inicio-map-host/g) || []).length === 1, 'PHP: una sola inicio-map-host');
ok(/inicio-views\.css/.test(php) && /inicio-mobile\.css/.test(php) &&
  !/screens\/inicio\.css/.test(php), 'PHP: CSS views+mobile (sin inicio.css)');
ok(/@media \(max-width: 768px\)/.test(viewsCss) && /@media \(min-width: 769px\)/.test(viewsCss),
  'inicio-views.css: toggles 768/769');
// 2b. display:contents permitido solo en cotilleo-cuerpo y wrappers de stage (layout fix)
let deskCssNoContents = deskCss.replace(/\/\* INICIO-DESKTOP-LAYOUT-FIX[\s\S]*$/, '');
deskCssNoContents = deskCssNoContents.replace(/\.obj-cotilleo-cuerpo[\s\S]{0,160}?display:\s*contents[^;]*;?/g, '');
ok(!/display:\s*contents/.test(deskCssNoContents),
  'desktop CSS: sin display:contents en layout');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK 7/7 + estructura');
process.exit(failures ? 1 : 0);
