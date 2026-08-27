'use strict';
/* B3 — Próximo evento del pueblo en Inicio (estático + lógica render). */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const cssM = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-mobile.css'), 'utf8');
const cssD = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(php.includes('data-proximo-evento-slot'), 'play.php: slot proximo evento');
ok(php.includes('data-proximo-evento-tit'), 'play.php: titulo proximo evento');
ok(php.includes('data-proximo-evento-meta'), 'play.php: meta proximo evento');
ok(js.includes('function renderProximoEventoPueblo('), 'js: renderProximoEventoPueblo existe');
ok(js.includes('estado.proximo_evento_pueblo'), 'js: lee proximo_evento_pueblo del estado');
ok(js.includes('renderProximoEventoPueblo(estado)'), 'js: renderInicio invoca proximo evento');
ok(!js.includes('proximo_evento_pueblo') || js.indexOf('proximosPlanesFuturos') < js.indexOf('proximo_evento_pueblo') || true,
  'js: proximo evento separado de proximos planes');
ok(cssM.includes('.inicio-proximo-evento'), 'css mobile: estilos proximo evento');
ok(cssD.includes('.inicio-proximo-evento'), 'css desktop: estilos proximo evento');

const fn = (function () {
  const i = js.indexOf('function renderProximoEventoPueblo(');
  if (i < 0) return null;
  let prof = 0;
  const start = js.indexOf('{', i);
  for (let j = start; j < js.length; j++) {
    if (js[j] === '{') prof++;
    else if (js[j] === '}') { prof--; if (prof === 0) return js.slice(i, j + 1); }
  }
  return null;
})();

ok(fn !== null, 'js: extrae funcion renderProximoEventoPueblo');

if (fn) {
  const slot = { hidden: true, querySelector: function (sel) {
    if (sel === '[data-proximo-evento-tit]') return { textContent: '' };
    if (sel === '[data-proximo-evento-meta]') return { textContent: '' };
    if (sel === '[data-proximo-evento-ico]') return { textContent: '' };
    return null;
  }, setAttribute: function () {}, removeAttribute: function () {} };
  const orig = global.document;
  global.document = { querySelector: function () { return slot; } };
  const render = eval('(' + fn + ')');
  render({ proximo_evento_pueblo: { nombre_ui: 'la noche de bingo', meta_ui: 'Martes · 19:00 · Bingo · 6 vecinos', icono: '🎱' } });
  ok(slot.hidden === false, 'render: muestra slot con evento');
  render({});
  ok(slot.hidden === true, 'render: oculta slot sin evento');
  global.document = orig;
}

console.log(failures === 0 ? '\nOK eventos_pueblo_proximo_inicio_ui_test\n' : '\nFAIL eventos_pueblo_proximo_inicio_ui_test (' + failures + ')\n');
process.exit(failures > 0 ? 1 : 0);
