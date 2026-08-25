// Test UI iconos de hobbies: lote 17 aprobado + resolver AHTHobbyIcons + fallback legacy.
// Verifica: cobertura del catalogo canonico, nombres de archivo, validez basica de SVG,
// sin <text> (la tipografia Caveat vive en HTML), sincronia del JS generado,
// fallback ante ID desconocido y que slots bloqueados/descubrimiento quedan intactos.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
let failures = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) failures++; }

const catalogo = JSON.parse(fs.readFileSync(path.join(root, 'data', 'catalogos', 'aficiones.json'), 'utf8'));
const idsCatalogo = catalogo.items.map(function (i) { return i.id; });
ok(idsCatalogo.length === 17, 'catalogo aficiones tiene 17 items');
ok(new Set(idsCatalogo).size === idsCatalogo.length, 'sin IDs duplicados en catalogo');

// 1. Cobertura 1:1 catalogo -> archivo canonico
const iconsDir = path.join(root, 'assets', 'icons', 'hobbies');
const files = fs.readdirSync(iconsDir).filter(function (f) { return /^hobby-[a-z_]+\.svg$/.test(f); });
ok(files.length === idsCatalogo.length, 'assets/icons/hobbies tiene exactamente 17 SVG (' + files.length + ')');
idsCatalogo.forEach(function (id) {
  ok(fs.existsSync(path.join(iconsDir, 'hobby-' + id + '.svg')), 'SVG presente para id canonico: ' + id);
});
files.forEach(function (f) {
  const id = f.replace(/^hobby-/, '').replace(/\.svg$/, '');
  ok(idsCatalogo.indexOf(id) >= 0, 'archivo ' + f + ' corresponde a ID canonico');
});

// 2. Validez basica de cada SVG + sin texto/raster/fuentes/refs externas
files.forEach(function (f) {
  const raw = fs.readFileSync(path.join(iconsDir, f), 'utf8').trim();
  const etiqueta = f;
  ok(/^<svg\s/.test(raw) && /<\/svg>$/.test(raw), etiqueta + ': raiz svg unica y cerrada');
  ok(/viewBox="0 0 32 32"/.test(raw.slice(0, 200)), etiqueta + ': viewBox 0 0 32 32');
  ok(!/<text/i.test(raw), etiqueta + ': sin <text>');
  ok(!/<image/i.test(raw), etiqueta + ': sin imagenes raster');
  ok(!/font-family|@font-face/i.test(raw), etiqueta + ': sin fuentes');
  ok(!/href\s*=|<script/i.test(raw), etiqueta + ': sin referencias externas ni scripts');
});

function innerOf(raw) { return raw.trim().replace(/^<svg\b[^>]*>/, '').replace(/<\/svg>\s*$/, '').trim(); }

// 3. hobby-icons.js generado y sincrono con los archivos canonicos
const resolverPath = path.join(root, 'assets', 'js', 'hobby-icons.js');
ok(fs.existsSync(resolverPath), 'existe assets/js/hobby-icons.js');
const sandbox = { window: {} };
vm.runInNewContext(fs.readFileSync(resolverPath, 'utf8'), sandbox, { filename: 'hobby-icons.js' });
const R = sandbox.window.AHTHobbyIcons;
ok(!!R && Array.isArray(R.ids()) && R.ids().length === 17, 'resolver expone 17 iconos');
idsCatalogo.forEach(function (id) {
  ok(R.has(id), 'resuelve ID canonico: ' + id);
  const fileInner = innerOf(fs.readFileSync(path.join(iconsDir, 'hobby-' + id + '.svg'), 'utf8'));
  ok(R.get(id) === fileInner, 'mapping sincrono con SVG canonico: ' + id);
  const out = R.svg(id);
  ok(out.indexOf('<svg class="ficha-hobby-svg" viewBox="0 0 32 32"') === 0, 'svg(' + id + ') conserva clase y viewBox');
});
ok(!R.has('jardineria'), 'ID legacy no funcional (jardineria) NO resuelto');
ok(R.svg('id_inexistente') === null, 'fallback: svg() devuelve null para ID desconocido');
ok(R.has('') === false && R.has(null) === false, 'fallback: vacio/null no resuelven');

// 4. play-v3.js: resolver primero, fallback legacy intacto
const js = fs.readFileSync(path.join(root, 'assets', 'js', 'play-v3.js'), 'utf8');
const fnIdx = js.indexOf('function svgHobbyIcon(');
ok(fnIdx > 0, 'svgHobbyIcon sigue definida en play-v3.js');
const fnBody = js.slice(fnIdx, js.indexOf('}', js.indexOf('svgHobbyPaths(key)', fnIdx)) + 1);
ok(fnBody.indexOf('AHTHobbyIcons') >= 0 && fnBody.indexOf('AHTHobbyIcons') < fnBody.indexOf('hobbyIconKey(id'), 'resolver por ID canonico consultado ANTES del fallback');
ok(/window\.AHTHobbyIcons\s*&&\s*window\.AHTHobbyIcons\.has\(id\)/.test(fnBody), 'consulta con guard (no rompe si el script no cargo)');
ok(fnBody.indexOf('svgHobbyPaths(key)') >= 0, 'fallback legacy (iconos actuales) intacto');

// 5. Labels HTML/Caveat fuera del SVG + slots bloqueados intactos + descubrimiento sin cambios
ok(js.indexOf("lab.textContent = sl.texto || '';") > 0, 'nombre del hobby se pinta como HTML (label del slot), no en el SVG');
ok(js.indexOf("ico.innerHTML = '<span class=\"ficha-hobby-q\">?</span>';") > 0, 'slot bloqueado mantiene interrogacion ficha-hobby-q');
ok(js.indexOf("card.className = 'ficha-hobby-card' + (sl.descubierto ? '' : ' is-desconocido');") > 0, 'clase is-desconocido segun descubrimiento intacta');
ok(js.indexOf('vista.hobbies_slots || slotsDesdeLista(vista.gusta)') > 0, 'fuente de slots de hobbies sin cambios');
ok(js.indexOf('function hobbyIconKey(') > 0, 'mapa legacy hobbyIconKey conservado como fallback');

// 6. Cableado en play.php: hobby-icons.js cargado antes de play-v3.js
const playHtml = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const idxIcons = playHtml.indexOf('assets/js/hobby-icons.js');
const idxV3 = playHtml.indexOf('assets/js/play-v3.js');
ok(idxIcons > 0 && idxV3 > idxIcons, 'play.php carga hobby-icons.js antes de play-v3.js');
ok(playHtml.indexOf('data-ficha-hobbies') > 0, 'seccion Hobbies presente en play.php');

console.log(failures === 0 ? '\nhobby_icons_test OK' : '\nhobby_icons_test FAIL (' + failures + ')');
process.exit(failures > 0 ? 1 : 0);
