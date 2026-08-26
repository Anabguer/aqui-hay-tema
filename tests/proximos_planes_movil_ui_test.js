'use strict';
/* Prueba dirigida: bloque movil PROXIMOS PLANES (debajo de Cotilleos, antes
   de EN CURSO) + restauracion de la cascada final de las cards superiores
   y del tamano de dia/fecha/hora en cabecera movil.
   Patron del repo: comprobaciones estaticas + logica pura con vm. */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/play-v3-responsive.css'), 'utf8');
const cssOv = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const cssM = css + '\n' + cssOv;

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

function extraerBloque(src, needle) {
  const i = src.indexOf(needle);
  if (i < 0) return '';
  let prof = 0;
  const start = src.indexOf('{', i);
  for (let j = start; j < src.length; j++) {
    if (src[j] === '{') prof++;
    else if (src[j] === '}') { prof--; if (prof === 0) return src.slice(i, j + 1); }
  }
  return '';
}
/* Ultimo bloque que menciona un selector y fija una propiedad (gana la cascada) */
function ultimaRegla(src, needle, prop) {
  const esc = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const re = new RegExp('[^{}]*' + esc + '[^{}]*\\{[^{}]*\\}', 'g');
  let last = ''; let m;
  while ((m = re.exec(src)) !== null) {
    if (!prop || m[0].includes(prop)) last = m[0];
  }
  return last || '';
}

/* --- 1. play.php: bloque unico, orden Cotilleo -> EN CURSO -> Proximos --- */
ok(php.includes('data-proxplanes-block') && php.includes('data-proxplanes-track'),
  'play.php: existe el bloque Proximos planes (cabecera + track)');
ok((php.match(/data-proxplanes-block/g) || []).length === 1,
  'play.php: UNA sola instancia del bloque');
const iCoti = php.indexOf('shell-grupo-cotilleo-par');
const iPP = php.indexOf('data-proxplanes-block');
const iEnc = php.indexOf('data-encursos-block');
ok(iCoti < iEnc && iEnc < iPP,
  'play.php: orden DOM Cotilleos -> EN CURSO -> Proximos planes');
ok(php.includes('data-encursos-count') && php.includes('data-proxplanes-count'),
  'play.php: contadores dinamicos en cabeceras');
ok(php.includes('plan-seccion-badge') && !php.includes('plan-seccion-ver'),
  'play.php: badge de seccion y sin VER TODOS');
ok(!php.includes('data-proxplanes-int') && !/pp-mov-(cta|panel)/.test(css),
  'sin acciones de intervencion en Proximos planes');

/* --- 2. JS: fuente canonica reutilizada, sin duplicar logica --- */
ok(js.includes('function proximosPlanesFuturos('), 'js: proximosPlanesFuturos existe');
ok(js.includes('function renderProximosPlanesMovil('), 'js: renderProximosPlanesMovil existe');
const fnRenderPP = extraerBloque(js, 'function renderShellPanels(');
ok(fnRenderPP.includes('renderProximosPlanesMovil(estado)'),
  'js: render de Proximos planes cableado junto al de EN CURSO');
ok(js.includes('[data-proxplanes-count]') && js.includes('String(total)'),
  'js: contador Proximos planes desde total real (proximosPlanesFuturos)');
ok(js.includes('[data-encursos-count]') && js.includes('encuentrosEnCursoAhora'),
  'js: contador EN CURSO desde encuentrosEnCursoAhora');
const fnFuturos = extraerBloque(js, 'function proximosPlanesFuturos(');
ok(fnFuturos.includes("planEsEnCurso(e, estado)") && fnFuturos.includes("'programado'"),
  'js: solo PROGRAMADOS que no estan en curso (excluye cancelados/rechazados por estado)');
ok(fnFuturos.includes('relojAbs(a.dia, horaEnc(a)) - relojAbs(b.dia, horaEnc(b))'),
  'js: orden cronologico estricto');

/* --- 3. Logica pura: futuros != en curso, cronologico, oculto si vacio --- */
(function escenario() {
  const fns = ['function horaEnc(', 'function relojAbs(', 'function duracionEncHoras(',
    'function encuentroOcupaAhora(', 'function planEsEnCurso(', 'function proximosPlanesFuturos(']
    .map(function (n) { return extraerBloque(js, n); }).join('\n');
  const sandbox = { console: console };
  vm.createContext(sandbox);
  vm.runInContext(fns + '\nfunction __futuros(encuentros, estado) {' +
    '  return proximosPlanesFuturos({ encuentros: encuentros }, estado).map(function (e) { return String(e.id); });' +
    '}', sandbox);
  const f = sandbox.__futuros;
  const estado = { reloj: { dia_pueblo: 5, hora_actual: 16 } };
  const curso = { id: 'A', estado: 'en_curso', dia: 5, hora: 16 };
  const futuroHoyTarde = { id: 'B', estado: 'programado', dia: 5, hora: 20 };
  const futuroManana = { id: 'C', estado: 'programado', dia: 6, hora: 10 };
  const ocupandoAhora = { id: 'D', estado: 'programado', dia: 5, hora: 15, duracion_horas: 2 };
  const cancelado = { id: 'E', estado: 'cancelado', dia: 7, hora: 12 };
  const rechazado = { id: 'F', estado: 'rechazado', dia: 7, hora: 14 };
  const terminado = { id: 'G', estado: 'terminado', dia: 4, hora: 9 };

  ok(JSON.stringify(f([curso], estado)) === '[]',
    'solo en_curso -> vacio (los en curso NO son proximos planes)');
  ok(JSON.stringify(f([curso, ocupandoAhora], estado)) === '[]',
    'programado que ocupa AHORA -> excluido (es EN CURSO)');
  ok(JSON.stringify(f([cancelado, rechazado, terminado], estado)) === '[]',
    'cancelados/rechazados/terminados -> excluidos');
  ok(JSON.stringify(f([futuroManana, curso, futuroHoyTarde], estado)) === '["B","C"]',
    'futuros -> incluidos en orden cronologico');
})();

/* --- 4. CSS cards superiores (<=768px): cascada FINAL corregida --- */
ok(/\.game-left \.shell-grupo-buzon > \.mensajitos-wrap[\s\S]*?overflow:\s*visible\s*!important/.test(cssM),
  'css: wrap interior sin overflow hidden (sobre no recortado)');
const ds = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');
const reglaSlot = ultimaRegla(cssM, '.game-left-tile-ico', 'height');
ok(!/height:\s*32px\s*!important/.test(reglaSlot || '') &&
   /\.game-left-tile-ico\s*,?\s*[^{]*\{[^}]*background:\s*none/.test(ds),
  'ds: slot de icono sin caja fija 32px (transparente, escala por contenido)');
const reglaSobre = ultimaRegla(cssM, '.zona-actividad .obj-buzon-img', 'width');
ok(/\.obj-buzon-img\s*\{[^}]*width:\s*42px/.test(ds) &&
   !/\.obj-buzon-img[^{]*\{[^}]*width:\s*32px\s*!important/.test(cssM),
  'ds: sobre de Mensajitos 42px (escala DS, responsive sin width !important)');
const reglaCaras = ultimaRegla(cssM, '.obj-vecinos-preview-cara', 'width');
ok(/width:\s*28px\s*!important/.test(reglaCaras),
  'css: caritas de Vecinos 28px');
ok(/\.obj-nuevo-plan-ico\s*\{[^}]*font-size:\s*(2(?:\.\d+)?|3(?:\.\d+)?)r?e?m/.test(ds),
  'ds: + de Nuevo Plan con presencia (>=2rem, screens/inicio.css)');
const reglaLabels = ultimaRegla(cssM, '.game-left-tile-label', 'font-size');
ok(!/font-size:\s*\.6[68]rem\s*!important/.test(reglaLabels || '') &&
   /\.game-left-tile-label[^{]*\{[^}]*font-size:\s*1\.125rem/.test(ds),
  'ds: labels de tiles 18px (manuscrita legible, fin del microtexto .66rem)');
const reglaWrap17 = extraerBloque(
  cssM.slice(cssM.indexOf('Fix batch 17')),
  '.play-v3:has(.game-shell) .game-left .shell-grupo-buzon > .mensajitos-wrap'
);
ok(reglaWrap17.includes('gap: .18rem !important'),
  'css: separacion icono<->texto ~.18rem donde manda la cascada (batch 17)');
ok(/height:\s*90px\s*!important[\s\S]*?max-height:\s*90px/.test(
  extraerBloque(cssM.slice(cssM.indexOf('Fix batch 17')), '.shell-grupo-buzon,')),
  'css: las tres cards mantienen la misma altura (90px)');

/* --- 5. CSS cabecera: clamp restaurado en la regla final --- */
const reglaMeta = ultimaRegla(cssM, '.top-meta-line', 'font-size');
ok(/font-size:\s*clamp\(\.72rem,\s*3\.5vw,\s*\.84rem\)\s*!important/.test(reglaMeta),
  'css: dia/fecha/hora clamp(.72rem, 3.5vw, .84rem) efectivo');

/* --- 6. CSS bloque Proximos planes: patron EN CURSO, diferenciado --- */
ok(cssM.includes('.play-v3 .proxplanes-movil:not(.is-on) { display: none; }'),
  'css: bloque oculto si no hay planes futuros');
ok(/@media \(min-width: 769px\)\s*\{\s*\.play-v3 \.proxplanes-movil\s*\{\s*display:\s*none !important;\s*\}\s*\}/.test(cssM),
  'css: gate desktop del bloque (solo <=768px)');
ok(/shell-grupo-cotilleo-par[^}]*order:\s*1/.test(cssOv),
  'css: order 1 Cotilleo en feed movil');
ok(/encursos-movil\.is-on[^}]*order:\s*2/.test(cssOv),
  'css: order 2 EN CURSO en feed movil');
ok(/proxplanes-movil\.is-on[^}]*order:\s*3/.test(cssOv),
  'css: order 3 Proximos planes en feed movil');
ok(/\.proxplanes-movil \.pp-mov-track[\s\S]*overflow-x:\s*auto/.test(cssOv),
  'css: carrusel horizontal Proximos planes');
ok(/\.encursos-movil \.enc-mov-track[\s\S]*overflow-x:\s*auto/.test(cssOv),
  'css: carrusel horizontal EN CURSO');

/* --- 7. EN CURSO intacto --- */
ok(js.includes('function encuentrosEnCursoAhora(') && js.includes('renderEncursosMovil(estado);'),
  'js: renderEncursosMovil sigue consumiendo su fuente canonica');
ok(cssM.includes('.play-v3 .encursos-movil:not(.is-on) { display: none; }'),
  'css: bloque EN CURSO intacto');
ok(php.includes('data-encursos-track') && php.includes('enc-mov-tit'),
  'play.php: bloque EN CURSO intacto');

console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
process.exit(failures === 0 ? 0 : 1);
