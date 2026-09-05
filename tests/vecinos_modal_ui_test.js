'use strict';
/**
 * Modal Vecinos — contrato estático V4 (shell + body + JS).
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const vecBody = fs.readFileSync(path.join(root, 'assets/css/design-system/vecinos-body.css'), 'utf8');
const screens = fs.readFileSync(path.join(root, 'assets/css/v4/screens.css'), 'utf8');
const frame = fs.readFileSync(path.join(root, 'assets/css/v4/screen-frame.css'), 'utf8');
const css = vecBody + '\n' + screens + '\n' + frame;

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(/data-aht-screen="vecinos"/.test(php), 'play.php: screen V4 vecinos');
ok(/class="aht-frame-tab/.test(php) && /data-vec-tab="vecinos"/.test(php), 'play.php: tabs Vecinos/Relaciones V4');
ok(/data-vec-panel="vecinos"/.test(php) && /data-vec-panel="relaciones"/.test(php), 'play.php: paneles vecinos/relaciones');
ok(/data-vec-busca/.test(php), 'play.php: buscador vecinos');
ok(/data-vecinos-list/.test(php), 'play.php: grid data-vecinos-list');
ok(/data-aht-screen="vecinos"[\s\S]{0,1200}aht-frame-close/.test(php), 'play.php: X canónica en vecinos');

ok(/function emoPillVecino/.test(js), 'js: emoPillVecino para pills reales');
ok(/vecino-celda--decor-/.test(js), 'js: decoración variada por índice');
ok(/function setVecTab/.test(js) && /function cargarVecRelaciones/.test(js), 'js: pestaña Relaciones operativa');
ok(/textoAnimoFichaPill/.test(js), 'js: labels de ánimo del sistema');

ok(/grid-template-columns:\s*1fr\s+1fr|repeat\(2,\s*minmax/.test(css), 'css: grid 2 columnas móvil');
ok(/\.vecino-celda--decor-0/.test(css), 'css: decoraciones tarjeta variadas');
ok(/\.vecino-cara/.test(css), 'css: avatar en tarjeta');
ok(/\.vecino-emo-pill|emo-pill/.test(css), 'css: pill emo en tarjeta');
ok(/\.aht-frame-tab\.is-active|\.vec-tab\.is-on/.test(css), 'css: tab activo');
ok(/dashed/.test(css), 'css: tab inactivo borde discontinuo');
ok(/repeat\(3,\s*minmax|repeat\(3,\s*1fr\)/.test(css), 'css desktop: 3 columnas');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\nvecinos_modal_ui_test OK');
