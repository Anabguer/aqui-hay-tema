'use strict';
// Prueba dirigida UI: MENTES iteración 2 — romper el hielo + temas concretos.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const cssArt = fs.readFileSync(path.join(root, 'assets/css/play-v3-shell-art.css'), 'utf8');
const handler = fs.readFileSync(path.join(root, 'api/handlers/EncuentrosHandler.php'), 'utf8');
const motor = fs.readFileSync(path.join(root, 'src/Engine/EncuentroIntervencion.php'), 'utf8');
const mentes = fs.readFileSync(path.join(root, 'src/Engine/MentesTemas.php'), 'utf8');
const encInt = fs.readFileSync(path.join(root, 'assets/css/play-v3-enc-int.css'), 'utf8');

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

ok(js.includes('data-enc-int-paso="persona"'), 'js: existe paso 1 (persona)');
ok(js.includes('\\u00bfQui\\u00E9n va a romper el hielo?'), 'js: kicker paso 1 romper hielo');
ok(!js.includes('\\u00bfA qui\\u00E9n quieres darle un empujoncito?'), 'js: sin kicker empujoncito iter1');
ok(!js.includes('\\u00bfQu\\u00E9 le quieres sugerir?'), 'js: sin kicker sugerir iter1');
ok(js.includes('data-enc-int-kicker-tema'), 'js: kicker dinámico paso 2');
ok(js.includes('kickerRompeHieloJs'), 'js: banco variantes rompe hielo');
ok(js.includes('data-enc-mentes-open'), 'js: CTA abre modal MENTES');
ok(encInt.includes('MENTES-CAPA-HOTFIX-v1'), 'css: capa mentes visible en whitelist');
ok(js.includes('ctaTxtEncuentroMov'), 'js: helper CTA acceso MENTES');
ok(!js.includes('Animar la conversaci\u00f3n'), 'js: sin Animar la conversación en UI');
ok(!js.includes('Sacar un tema que le guste'), 'js: sin dropdown tema abstracto');
ok(js.includes('data-enc-int-persona='), 'js: participantes seleccionables');
ok(js.includes('data-enc-int-volver'), 'js: volver a elegir persona');
const fnTarjeta = extraerBloque(js, 'function htmlIntervencionEncuentro(');
ok(fnTarjeta.includes("data-enc-int data-enc-id=\"' + esc(enc.id || '')"),
  'js: contrato canonico data-enc-int por encuentro');
ok(fnTarjeta.includes('data-enc-int-paso="accion"') && fnTarjeta.includes('hidden'),
  'js: paso 2 oculto hasta elegir persona');
ok(fnTarjeta.includes('data-temas-panel'), 'js: panel temas en paso 2');
ok(!fnTarjeta.includes('data-enc-int-accion="hablar"'), 'js: sin botón hablar');

ok(js.includes("closest('[data-enc-int-persona]')"), 'js: handler eleccion persona');
ok(js.includes('pintarTemasIntervencion(wrapP, ivP, personaId)'),
  'js: temas al elegir rompe hielo');
const fnEjecutar = extraerBloque(js, 'async function ejecutarIntervencionEncuentro(');
ok(fnEjecutar.includes('payload.objetivo = extra.objetivo'), 'js: request lleva objetivo');

ok(handler.includes("$params['objetivo']"), 'handler API: propaga objetivo');
ok(motor.includes('MentesTemas::temasElegibles'), 'motor: temas elegibles iter2');
ok(motor.includes("if ($id === self::HABLAR)") && motor.includes('continue'), 'motor: oculta hablar');
ok(mentes.includes('afin_bien'), 'motor MentesTemas: banco copy');

ok(cssArt.includes('.enc-int-step[hidden]'), 'css: pasos ocultables');

(function sandbox() {
  const fns = [
    'function htmlCaraAvatar(',
    'function htmlIntervencionResultado(',
    'function kickerRompeHieloJs(',
    'function textoFeedbackIntervencion(',
    'function temasIntervencionDe(',
    'function pintarTemasIntervencion(',
    'function htmlIntervencionEncuentro('
  ].map(function (n) { return extraerBloque(js, n); }).join('\n');
  const nombres = { rA: 'Xenia', rB: 'Laura' };
  const sandbox = {
    console: console,
    document: {
      createElement: function (tag) {
        const el = { tagName: tag.toUpperCase(), attributes: {}, children: [], style: {}, classList: { _c: [], add: function () {}, remove: function () {}, toggle: function () {} }, setAttribute: function (k, v) { this.attributes[k] = v; }, getAttribute: function (k) { return this.attributes[k]; }, appendChild: function (c) { this.children.push(c); } };
        return el;
      }
    },
    nombreDe: function (id) { return nombres[id] || id; },
    tokenDe: function () { return ''; },
    emocionDe: function () { return 'neutral'; },
    esc: function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); },
    planEsEnCurso: function () { return true; },
    intervencionVistaDe: function (enc) { return enc.__iv; }
  };
  vm.createContext(sandbox);
  vm.runInContext(fns, sandbox);

  const ivMock = {
    disponible: true,
    usada: false,
    acciones: [
      { id: 'hobby', disponible: true, kickers_rompe: ['A ver, %s… ¿por dónde tiramos?'], temas_por_objetivo: {
        rA: [
          { id: 'correr', etiqueta: '🏃 Salir a correr', interlocutor_id: 'rB' },
          { id: 'cine', etiqueta: '🎬 Cine', interlocutor_id: 'rB' }
        ],
        rB: [{ id: 'bingo', etiqueta: '🎱 Bingo', interlocutor_id: 'rA' }]
      } }
    ]
  };
  const html = sandbox.htmlIntervencionEncuentro({ id: 'ENC_A', participantes: ['rA', 'rB'], __iv: ivMock }, {});
  ok(html.indexOf('romper el hielo') > -1, 'vm: copy romper hielo');
  ok(html.indexOf('data-enc-int-accion="hablar"') === -1, 'vm: sin hablar');

  const panel = { innerHTML: '', children: [], appendChild: function (c) { this.children.push(c); } };
  const kickerEl = { textContent: '' };
  const wrap = {
    querySelector: function (sel) {
      if (sel === '[data-temas-panel]') return panel;
      if (sel === '[data-enc-int-kicker-tema]') return kickerEl;
      return null;
    }
  };
  sandbox.pintarTemasIntervencion(wrap, ivMock, 'rA');
  ok(panel.children.length === 2, 'vm: varios temas pintados');
  const k = sandbox.kickerRompeHieloJs(ivMock, 'rA', ivMock.acciones[0].temas_por_objetivo.rA);
  ok(k.indexOf('Xenia') > -1 || k.indexOf('Laura') > -1, 'vm: kicker usa nombre del encuentro');
})();

console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
process.exit(failures === 0 ? 0 : 1);
