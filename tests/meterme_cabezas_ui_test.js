'use strict';
// Prueba dirigida UI: MENTES — intervencion en 2 pasos con temas del interlocutor.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const cssArt = fs.readFileSync(path.join(root, 'assets/css/play-v3-shell-art.css'), 'utf8');
const handler = fs.readFileSync(path.join(root, 'api/handlers/EncuentrosHandler.php'), 'utf8');
const motor = fs.readFileSync(path.join(root, 'src/Engine/EncuentroIntervencion.php'), 'utf8');

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

/* --- 1. Flujo en 2 pasos y copy AHT --- */
ok(js.includes('data-enc-int-paso="persona"'), 'js: existe paso 1 (persona)');
ok(js.includes('\\u00bfA qui\\u00E9n quieres darle un empujoncito?'), 'js: kicker paso 1 AHT');
ok(js.includes('\\u00bfQu\\u00E9 le quieres sugerir?'), 'js: kicker paso 2 AHT');
ok(js.includes('\u00bfQu\u00E9 se cuece ah\u00ED?'), 'js: CTA acceso MENTES');
ok(!js.includes('recibe la idea'), 'js: sin copy tecnico recibe la idea');
ok(!js.includes('\u00bfEn qui\u00E9n quieres meterte?'), 'js: sin kicker antiguo meterte');
ok(js.includes('data-enc-int-persona='), 'js: participantes seleccionables');
ok(js.includes('data-enc-int-volver'), 'js: volver a elegir persona');
const fnTarjeta = extraerBloque(js, 'function htmlIntervencionEncuentro(');
ok(fnTarjeta.includes("data-enc-int data-enc-id=\"' + esc(enc.id || '')"),
  'js: contrato canonico data-enc-int por encuentro');
ok(fnTarjeta.includes('data-enc-int-paso="accion"') && fnTarjeta.includes('hidden'),
  'js: paso 2 oculto hasta elegir persona');
ok(fnTarjeta.includes('temas_por_objetivo'), 'js: temas por persona influida (backend)');
ok(fnTarjeta.includes('data-temas-toggle') && fnTarjeta.includes('data-temas-panel'),
  'js: selector de temas preservado');

/* --- 2. Handler: objetivo, pintar temas y payload --- */
ok(js.includes("closest('[data-enc-int-persona]')"), 'js: handler eleccion persona');
ok(js.includes("closest('[data-enc-int-volver]')"), 'js: handler volver');
ok(js.includes("wrapP.setAttribute('data-enc-int-objetivo'"), 'js: objetivo anclado al wrap');
ok(js.includes('pintarTemasIntervencion(wrapP, ivP, personaId)'),
  'js: temas del interlocutor al elegir influida');
ok(!js.includes('hobbyVisibleParaObjetivo'), 'js: sin filtro invertido antiguo');
const fnEjecutar = extraerBloque(js, 'async function ejecutarIntervencionEncuentro(');
ok(fnEjecutar.includes('payload.objetivo = extra.objetivo'), 'js: request lleva objetivo');
ok(fnEjecutar.includes('encuentro_id: encId'), 'js: request lleva id encuentro');

/* --- 3. Feedback = copy del motor (sin prefijo UI) --- */
const fnFeedback = extraerBloque(js, 'function textoFeedbackIntervencion(');
ok(fnFeedback.includes('return iv.ultimo.texto'), 'js: feedback = texto backend directo');
ok(!fnFeedback.includes('recibe la idea'), 'js: feedback sin recibe la idea');
ok(fnTarjeta.includes("var tono = iv.ultimo.tono || 'neutral'"), 'js: polaroid usa tono real');

/* --- 4. Backend --- */
ok(handler.includes("$params['objetivo']"), 'handler API: propaga objetivo');
ok(motor.includes('hobbiesTemaConocidos'), 'motor: temas conocidos por influida');
ok(motor.includes("'detalle' => 'hobby_de_otro_residente'"), 'motor: rechaza hobby del influido');
ok(motor.includes("'beneficiario'"), 'motor: persiste beneficiario');
ok(motor.includes('Sacar un tema que le guste'), 'motor: etiqueta hobby AHT');

/* --- 5. CSS --- */
ok(cssArt.includes('.enc-int-step[hidden]'), 'css: pasos ocultables');
ok(cssArt.includes('.enc-int-pers-cara'), 'css: cara persona');

/* --- 6. Sandbox: render y feedback --- */
(function sandbox() {
  const fns = [
    'function textoFeedbackIntervencion(',
    'function temasIntervencionDe(',
    'function pintarTemasIntervencion(',
    'function caraIntervencionHtml(',
    'function htmlIntervencionEncuentro('
  ].map(function (n) { return extraerBloque(js, n); }).join('\n');
  const nombres = { rA: 'Paula', rB: 'Sergio' };
  const sandbox = {
    console: console,
    document: {
      createElement: function (tag) {
        const el = { tagName: tag.toUpperCase(), attributes: {}, children: [], style: {}, classList: { _c: [], add: function () {}, remove: function () {}, toggle: function () {} }, setAttribute: function (k, v) { this.attributes[k] = v; }, getAttribute: function (k) { return this.attributes[k]; }, appendChild: function (c) { this.children.push(c); } };
        return el;
      }
    },
    nombreDe: function (id) { return nombres[id] || id; },
    esc: function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); },
    planEsEnCurso: function () { return true; },
    intervencionVistaDe: function (enc) { return enc.__iv; }
  };
  vm.createContext(sandbox);
  vm.runInContext(fns, sandbox);

  const fb = sandbox.textoFeedbackIntervencion({ ultimo: { tono: 'bien', texto: 'Le has sugerido a Sergio que saque el tema.', objetivo: 'rB' } });
  ok(fb === 'Le has sugerido a Sergio que saque el tema.', 'vm: feedback = copy motor sin prefijo');
  ok(sandbox.textoFeedbackIntervencion({ ultimo: {} }) === '', 'vm: sin texto -> vacio');

  const ivMock = {
    disponible: true,
    usada: false,
    acciones: [
      { id: 'hablar', etiqueta: 'Animar la conversación', disponible: true },
      { id: 'hobby', etiqueta: 'Sacar un tema que le guste', disponible: true, temas_por_objetivo: {
        rA: [{ id: 'viajes', etiqueta: 'Viajes', residente_id: 'rB' }],
        rB: [{ id: 'bingo', etiqueta: 'Bingo', residente_id: 'rA' }]
      } }
    ]
  };
  const html = sandbox.htmlIntervencionEncuentro({ id: 'ENC_A', participantes: ['rA', 'rB'], __iv: ivMock }, {});
  ok(html.indexOf('data-enc-id="ENC_A"') > -1, 'vm: tarjeta anclada al encuentro');
  ok(html.indexOf('data-enc-int-persona="rA"') > -1 && html.indexOf('data-enc-int-persona="rB"') > -1,
    'vm: ambos participantes elegibles');
  ok(html.indexOf('data-enc-int-accion="hablar"') > -1, 'vm: animar conversacion pintada');
  ok(html.indexOf('Sacar un tema que le guste') > -1, 'vm: boton tema AHT');
  ok(html.indexOf('data-hobby-id="viajes"') === -1, 'vm: temas no pre-pintados (dinamicos al elegir persona)');
  ok(html.indexOf('data-temas-panel') > -1, 'vm: panel temas vacio inicial');

  const map = sandbox.temasIntervencionDe(ivMock);
  ok(map && map.rA && map.rA[0].residente_id === 'rB', 'vm: temas de rA son hobbies de rB (interlocutor)');
})();

console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
process.exit(failures === 0 ? 0 : 1);
