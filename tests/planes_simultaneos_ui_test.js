'use strict';
// Prueba dirigida: planes SIMULTANEOS en curso (0/1/N) accesibles para Celestine.
// - Fuente canonica unica desktop + movil (encuentrosEnCursoAhora).
// - Desktop: una sola polaroid activa + navegacion ‹ 1/N › que NO ejecuta acciones.
// - Identidad de intervencion: cada accion lleva el id del encuentro intervenido
//   y la vista solo se escribe en ese encuentro (nunca en el "actual" global).
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const handler = fs.readFileSync(path.join(root, 'api/handlers/EncuentrosHandler.php'), 'utf8');
const service = fs.readFileSync(path.join(root, 'src/Engine/PartidaService.php'), 'utf8');
const resumen = fs.readFileSync(path.join(root, 'src/Engine/ResumenDia.php'), 'utf8');

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

/* --- 1. Backend: coleccion canonica 0..N expuesta y propagada --- */
ok(/function encuentrosEnCurso\s*\(/.test(resumen), 'ResumenDia::encuentrosEnCurso existe (coleccion N)');
ok(/'encuentros_en_curso'\s*=>\s*ResumenDia::encuentrosEnCurso\(\$partida,\s*\$this->catalog\)/.test(service),
  'estadoResumido expone encuentros_en_curso (fuente canonica)');
ok((handler.match(/'encuentros_en_curso'/g) || []).length >= 2,
  'deltas de organizar/intervencion propagan encuentros_en_curso');
ok(/'duracion_minutos'\s*=>/.test(resumen) && /LugarAtributos::horasDeEncuentro\(\$enc\)/.test(resumen),
  'vistaEncuentro expone duracion_minutos (ventana computable en cliente)');
ok(/'encuentro_en_curso'\s*=>\s*ResumenDia::encuentroEnCurso/.test(service),
  'compatibilidad: encuentro_en_curso (singular) se conserva');

/* --- 2. play.php: nav discreta dentro de la polaroid (una sola tarjeta) --- */
const iNav = php.indexOf('data-curso-nav');
ok(iNav > -1, 'play.php: existe nav data-curso-nav');
ok(php.indexOf('data-curso-nav', iNav + 1) === -1, 'play.php: UNA sola instancia de la nav');
ok(iNav > php.indexOf('obj-proximo-polaroid') && iNav < php.indexOf('data-proximo-plan'),
  'play.php: nav vive DENTRO de la polaroid Plan en curso');
ok(php.includes('data-curso-prev') && php.includes('data-curso-next') && php.includes('data-curso-cont'),
  'play.php: nav con ‹ › y contador 1/N');

/* --- 2b. play.php: bloque movil EN CURSO (encima de Cotilleos) --- */
const iMov = php.indexOf('data-encursos-block');
ok(iMov > -1, 'play.php: existe bloque movil data-encursos-block');
ok(php.indexOf('data-encursos-block', iMov + 1) === -1, 'play.php: UN solo bloque movil');
ok(php.includes('data-encursos-track') && php.includes('data-encursos-indice') && php.includes('enc-mov-cab'),
  'play.php: bloque movil con cabecera, track e indicador');
const iCoti = php.indexOf('shell-grupo-cotilleo-par');
ok(iMov < iCoti, 'play.php: carrusel movil ENCIMA de Cotilleos');

/* --- 3. Frontend: seleccion estable por id, sin API en la navigation --- */
ok(js.includes('var cursoSelId = null;'), 'js: seleccion de curso estable por id');
const fnMover = extraerBloque(js, 'function moverCursoSeleccion(');
ok(fnMover.length > 0, 'js: moverCursoSeleccion existe');
ok(!/\bapi\(/.test(fnMover), 'js: cambiar de encuentro NO llama a la API (no ejecuta acciones)');
ok(fnMover.includes('renderShellPanels('), 'js: navegar solo re-renderiza la misma tarjeta');
const fnRender = extraerBloque(js, 'function renderShellPanels(');
ok(fnRender.includes('enCursoLista.length > 0'), 'js: polaroid consume la fuente canonica 0..N');
ok(fnRender.includes("cursoNav.hidden = !(hayEnCurso && n > 1)"),
  'js: nav oculta con 0/1; visible solo con N>1');
ok(fnRender.includes('if (pos < 0) pos = 0;'),
  'js: si el seleccionado termina -> auto-seleccion valida (la UI no se rompe)');
ok(js.includes("closest('[data-curso-prev]')") === false && js.includes("[data-curso-prev]')") &&
  js.includes("addEventListener('click', function () { moverCursoSeleccion(-1); })"),
  'js: botones ‹ › cableados a moverCursoSeleccion');

/* --- 4. Identidad de intervencion: escritura SOLO en el encuentro intervenido --- */
ok(!/cacheEstado\.encuentro_en_curso\.intervencion = r\.vista \|\| \{[^}]*\};\s*\}\s*\n\s*if \(cacheInsp/.test(js.replace(/\r\n/g, '\n')) ||
  js.includes('String(cacheEstado.encuentro_en_curso.id) === String(encId)'),
  'js: la vista de intervencion ya NO se escribe ciegamente sobre encuentro_en_curso');
ok(js.includes('String(e.id) === String(encId)'), 'js: escritura dirigida por id en encuentros_en_curso');
ok(js.includes('String(cacheEstado.encuentro_en_curso.id) === String(encId)'),
  'js: guardia de identidad para el singular encuentro_en_curso');
const fnEjecutar = extraerBloque(js, 'async function ejecutarIntervencionEncuentro(');
ok(fnEjecutar.includes('encuentro_id: encId'), 'js: el request lleva el id del encuentro intervenido');

/* --- 5. Movil reutiliza la MISMA fuente (cero logica duplicada) --- */
const fnMovil = extraerBloque(js, 'function renderEncursosMovil(');
ok(fnMovil.includes('encuentrosEnCursoAhora(cacheInsp, estado)'),
  'js: carrusel movil consume la misma fuente canonica que desktop');

/* --- 5b. Polaroid consume la coleccion + seleccion estable + contador --- */
ok(fnRender.includes('encuentrosEnCursoAhora(partida, estado)'),
  'js: polaroid resuelve la lista con la fuente canonica');
ok(fnRender.includes("String(enCursoLista[ci].id) === String(cursoSelId)"),
  'js: seleccion comparada SIEMPRE por id (nunca por posicion suelta)');
ok(fnRender.includes("(pos + 1) + ' / ' + n"),
  'js: contador de la nav en formato "1 / N"');
const fnTarjeta = extraerBloque(js, 'function htmlIntervencionEncuentro(');
ok(fnTarjeta.includes("data-enc-int data-enc-id=\"' + esc(enc.id || '')"),
  'js: cada tarjeta de intervencion lleva el id del encuentro pintado');

/* --- 5c. Selector de temas: el toggle abre/cierra SU panel --- */
ok(js.includes("closest('[data-temas-toggle]')"), 'js: existe handler para abrir el panel de temas');
ok(js.includes('panelTemas.hidden = !abrirTemas'), 'js: el toggle alterna la visibilidad del panel');
ok(js.includes("temasToggle.setAttribute('aria-expanded', String(abrirTemas))"),
  'js: aria-expanded sincronizado al abrir temas');

/* --- 6. Logica pura: escenarios 0 / 1 / 2 / 2+futuro / coleccion+cur --- */
(function escenario() {
  const fns = ['function horaEnc(', 'function relojAbs(', 'function duracionEncHoras(',
    'function encuentroOcupaAhora(', 'function planEsEnCurso(', 'function encuentrosEnCursoAhora(']
    .map(function (n) { return extraerBloque(js, n); }).join('\n');
  const sandbox = { console: console };
  vm.createContext(sandbox);
  vm.runInContext(fns + '\nfunction __escenarios(encuentros, estado, coleccion) {' +
    '  var estadoBase = Object.assign({}, estado);' +
    '  if (coleccion !== undefined) estadoBase.encuentros_en_curso = coleccion;' +
    '  else delete estadoBase.encuentros_en_curso;' +
    '  var partida = { encuentros: encuentros };' +
    '  return encuentrosEnCursoAhora(partida, estadoBase).map(function (e) { return String(e.id); });' +
    '}', sandbox);
  const esc = sandbox.__escenarios;

  const estado = { reloj: { dia_pueblo: 5, hora_actual: 16 }, encuentro_en_curso: { id: 'A', dia: 5, hora: 16 } };
  const A = { id: 'A', estado: 'en_curso', dia: 5, hora: 16 };
  const B = { id: 'B', estado: 'en_curso', dia: 5, hora: 16 };
  const C = { id: 'C', estado: 'programado', dia: 6, hora: 16 };
  const D = { id: 'D', estado: 'terminado', dia: 5, hora: 10 };

  ok(JSON.stringify(esc([], { reloj: { dia_pueblo: 5, hora_actual: 16 } })) === '[]',
    '0 activos -> lista vacia (fallback ventana)');
  ok(JSON.stringify(esc([A], estado)) === '["A"]', '1 activo -> exactamente ese');
  ok(JSON.stringify(esc([B, A, C, D], estado)) === '["A","B"]',
    '2 activos + futuro + terminado -> solo los 2 activos, orden por inicio/id');
  ok(JSON.stringify(esc([A, B], estado)) === '["A","B"]', 'mismo inicio -> desempate determinista por id');

  /* Coleccion canonica del servidor tiene prioridad; cur siempre entra; sin duplicados */
  const col = [{ id: 'B', estado: 'en_curso', dia: 5, hora: 16 }];
  ok(JSON.stringify(esc([A, B], estado, col)) === '["A","B"]',
    'coleccion servidor + inyeccion de encuentro_en_curso ausente');
  ok(JSON.stringify(esc([A, B], estado, [A, B])) === '["A","B"]',
    'sin duplicados cuando cur ya viene en la coleccion');

  /* Orden por hora real */
  const temprano = { id: 'T', estado: 'en_curso', dia: 5, hora: 15, duracion_horas: 2 };
  ok(JSON.stringify(esc([A, temprano], { reloj: { dia_pueblo: 5, hora_actual: 16 } })) === '["T","A"]',
    'orden cronologico por inicio (temprano primero)');
})();

console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
process.exit(failures === 0 ? 0 : 1);
