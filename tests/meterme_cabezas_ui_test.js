'use strict';
// Prueba dirigida UI: "METERME EN SU CABEZA" — intervencion en 2 pasos
// (¿en quien? -> ¿que?) con identidad por encuentro y objetivo por persona.
// - Contratos canonicos preservados: data-enc-int/data-enc-id, temas P03,
//   feedback polaroid por tono real, guardas is-busy y cierre del selector.
// - Sin porcentajes ni datos ocultos del motor: solo lo ya conocido.
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

/* --- 1. Flujo en 2 pasos dentro de la tarjeta de intervencion --- */
ok(js.includes('data-enc-int-paso="persona"') && js.includes("data-enc-int-paso='persona'") === false,
  'js: existe paso 1 (persona) en la tarjeta');
ok(js.includes('\u00bfEn qui\u00E9n quieres meterte?'), 'js: kicker paso 1 jugueton');
ok(js.includes('\u00bfQu\u00E9 quieres meterle en la cabeza?'), 'js: kicker paso 2 jugueton');
ok(js.includes('data-enc-int-persona=') , 'js: cada participante es seleccionable (data-enc-int-persona)');
ok(js.includes('data-enc-int-volver'), 'js: se puede volver a elegir persona');
const fnTarjeta = extraerBloque(js, 'function htmlIntervencionEncuentro(');
ok(fnTarjeta.includes("data-enc-int data-enc-id=\"' + esc(enc.id || '')"),
  'js: contrato canonico preservado (data-enc-int data-enc-id por encuentro)');
ok(fnTarjeta.includes('data-enc-int-paso="accion"') && fnTarjeta.includes('hidden'),
  'js: paso 2 oculto hasta elegir persona');
ok(fnTarjeta.includes('iv.acciones.forEach'), 'js: paso 2 usa las acciones REALES del motor (gates intactos)');
ok(fnTarjeta.includes('data-temas-toggle') && fnTarjeta.includes('data-temas-panel'),
  'js: temas P03 preservados en el flujo nuevo');

/* --- 2. Handler delegado: eleccion, volver, filtro y objetivo en payload --- */
ok(js.includes("closest('[data-enc-int-persona]')"), 'js: handler de eleccion de persona');
ok(js.includes("closest('[data-enc-int-volver]')"), 'js: handler de volver');
ok(js.includes("wrapP.setAttribute('data-enc-int-objetivo'"), 'js: objetivo anclado al wrap del encuentro');
ok(js.includes('hobbyVisibleParaObjetivo(rid, personaId)'),
  'js: temas filtrados por persona elegida (solo lo ya conocido de ELLA)');
ok(js.includes('hobbyVisibleParaObjetivo(rid, personaId)'),
  'js: filtro comparado contra el objetivo del encuentro intervenido');
const fnEjecutar = extraerBloque(js, 'async function ejecutarIntervencionEncuentro(');
ok(fnEjecutar.includes('payload.objetivo = extra.objetivo'), 'js: el request lleva ENCUENTRO + PERSONA OBJETIVO');
ok(fnEjecutar.includes('encuentro_id: encId'), 'js: el request lleva el id del encuentro (identidad)');
ok(/wrap\.getAttribute\('data-enc-int-objetivo'\)/.test(js),
  'js: el objetivo sale del MISMO wrap que el id de encuentro (no cruzado)');

/* --- 3. Feedback narrativo derivado del tono REAL --- */
const fnFeedback = extraerBloque(js, 'function textoFeedbackIntervencion(');
ok(fnFeedback.length > 0, 'js: textoFeedbackIntervencion existe');
ok(fnTarjeta.includes("var tono = iv.ultimo.tono || 'neutral'"), 'js: feedback usa el tono real del motor');
ok(fnFeedback.includes('if (!obj) return t;'), 'js: sin objetivo -> texto canonico intacto (compat)');
ok(!/[+-]?\d+(\.\d+)?\s*(pts|%|pesos)/i.test(fnFeedback), 'js: feedback SIN cifras del motor');

/* --- 4. Backend: objetivo validado como participante y persistido por encuentro --- */
ok(handler.includes("$params['objetivo']"), 'handler API: propaga objetivo');
ok(motor.includes("'detalle' => 'objetivo_no_participante'"), 'motor: rechaza objetivo ajeno al encuentro');
ok(motor.includes("'detalle' => 'hobby_de_otro_residente'"), 'motor: el tema debe ser de la persona elegida');
ok(motor.includes("'objetivo' => $objetivo !== '' ? $objetivo : null,"), 'motor: persiste objetivo (null si no viene)');
ok(/\$prev\['objetivo'\] \?\? null/.test(motor), 'motor: vista play expone ultimo.objetivo');

/* --- 5. CSS contenido y migrable (shell-art, junto al bloque .enc-int canonico) --- */
ok(cssArt.includes('.enc-int-step[hidden]'), 'css: pasos ocultables');
ok(cssArt.includes('.enc-int-pers-cara'), 'css: cara de la persona elegible');
ok(cssArt.includes('.enc-int-btn--hobby[hidden]'), 'css: temas fuera de objetivo ocultos');

/* --- 6. Logica pura (vm): render, filtro y feedback --- */
(function sandbox() {
  const fns = [
    'function textoFeedbackIntervencion(',
    'function hobbyVisibleParaObjetivo(',
    'function caraIntervencionHtml(',
    'function htmlIntervencionEncuentro('
  ].map(function (n) { return extraerBloque(js, n); }).join('\n');
  const nombres = { rA: 'Paula', rB: 'Sergio' };
  const sandbox = {
    console: console,
    nombreDe: function (id) { return nombres[id] || id; },
    tokenDe: function () { return ''; },
    esc: function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); },
    planEsEnCurso: function () { return true; },
    intervencionVistaDe: function (enc) { return enc.__iv; }
  };
  vm.createContext(sandbox);
  vm.runInContext(fns, sandbox);

  /* 6a. Feedback por tono real */
  const fbBien = sandbox.textoFeedbackIntervencion({ ultimo: { tono: 'bien', texto: 'La charla fluye.', objetivo: 'rB' } });
  ok(fbBien.indexOf('Sergio') > -1 && fbBien.indexOf('recibe la idea') > -1 && fbBien.indexOf('La charla fluye.') > -1,
    'vm: feedback BIEN nombra a la persona y conserva el copy real del motor');
  const fbMal = sandbox.textoFeedbackIntervencion({ ultimo: { tono: 'mal', texto: 'La broma no pega.', objetivo: 'rB' } });
  ok(fbMal.indexOf('Sergio') > -1 && fbMal.indexOf('La broma no pega.') > -1,
    'vm: feedback MAL nombra a la persona y conserva el copy real');
  const fbNeu = sandbox.textoFeedbackIntervencion({ ultimo: { tono: 'neutral', texto: 'Charlan sin más.', objetivo: 'rA' } });
  ok(fbNeu.indexOf('Paula') > -1 && fbNeu.indexOf('Charlan sin más.') > -1, 'vm: feedback NEUTRAL compuesto igual');
  const fbSin = sandbox.textoFeedbackIntervencion({ ultimo: { tono: 'bien', texto: 'Texto canonico.' } });
  ok(fbSin === 'Texto canonico.', 'vm: sin objetivo -> texto EXACTO canonico (intervenciones antiguas)');
  ok(sandbox.textoFeedbackIntervencion({ ultimo: {} }) === '', 'vm: sin texto -> vacio');

  /* 6b. Filtro de temas por persona */
  ok(sandbox.hobbyVisibleParaObjetivo('rB', 'rB') === true, 'vm: tema de la persona elegida visible');
  ok(sandbox.hobbyVisibleParaObjetivo('rA', 'rB') === false, 'vm: tema de la OTRA persona oculto');
  ok(sandbox.hobbyVisibleParaObjetivo('', 'rB') === true, 'vm: firma ausente no rompe (defensivo)');
  ok(sandbox.hobbyVisibleParaObjetivo('rB', '') === true, 'vm: sin objetivo todo visible (compat)');

  /* 6c. Render completo: dos personas reales del encuentro, pasos y temas */
  const ivMock = {
    disponible: true,
    usada: false,
    acciones: [
      { id: 'hablar', etiqueta: 'Animar la conversación', disponible: true },
      { id: 'beso', etiqueta: 'Intentar un beso', disponible: false },
      { id: 'hobby', etiqueta: 'Hablar de un hobby', disponible: true, hobbies: [
        { id: 'bingo', etiqueta: 'Bingo', residente_id: 'rB' },
        { id: 'cocina', etiqueta: 'Cocina', residente_id: 'rA' }
      ] }
    ]
  };
  const html = sandbox.htmlIntervencionEncuentro(
    { id: 'ENC_A', participantes: ['rA', 'rB'], __iv: ivMock },
    {}
  );
  ok(html.indexOf('data-enc-id="ENC_A"') > -1, 'vm: tarjeta anclada al encuentro A');
  ok(html.indexOf('data-enc-int-persona="rA"') > -1 && html.indexOf('data-enc-int-persona="rB"') > -1,
    'vm: los DOS participantes del encuentro son elegibles');
  ok(html.indexOf('Paula') > -1 && html.indexOf('Sergio') > -1, 'vm: nombres reales junto a cada cara');
  ok(/data-enc-int-paso="accion"[^>]*hidden/.test(html), 'vm: paso 2 nace oculto');
  ok(html.indexOf('data-enc-int-accion="hablar"') > -1, 'vm: acciones disponibles pintadas en paso 2');
  ok(html.indexOf('data-enc-int-accion="beso"') === -1, 'vm: acciones NO disponibles NO se pintan (gate canonico)');
  ok(html.indexOf('data-hobby-id="bingo"') > -1 && html.indexOf('data-hobby-id="cocina"') > -1,
    'vm: temas de ambos firmados con su dueno (el filtro decide al elegir)');
  ok(html.indexOf('data-temas-toggle') > -1, 'vm: selector de temas preservado');

  /* 6d. Tarjeta usada: polaroid con feedback compuesto */
  const ivUsada = { disponible: false, usada: true, ultimo: { tono: 'bien', texto: 'Risa asegurada.', objetivo: 'rB' } };
  const htmlUsada = sandbox.htmlIntervencionEncuentro({ id: 'ENC_B', participantes: ['rA', 'rB'], __iv: ivUsada }, {});
  ok(htmlUsada.indexOf('enc-int-result-txt--bien') > -1 && htmlUsada.indexOf('Sergio') > -1
    && htmlUsada.indexOf('Risa asegurada.') > -1,
    'vm: polaroid final con tono real + persona + copy del motor');
})();

console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
process.exit(failures === 0 ? 0 : 1);
