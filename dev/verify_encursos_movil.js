'use strict';
/* Verificacion funcional movil: bloque PLANES EN CURSO encima de Cotilleos.
   Patron del repo: pagina servida local + API mockeada con page.route,
   con oraculo canonico (misma ventana dia/hora/duracion/estado que
   ResumenDia::encuentroEnCurso y EncuentroLifecycle). Motor real intacto.
   Uso: node dev\verify_encursos_movil.js   (requiere php -S 127.0.0.1:8765) */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8765/play.php';
const OUT = path.join(__dirname, 'screenshots-encursos-movil');
fs.mkdirSync(OUT, { recursive: true });

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

/* ================= estado simulado + oraculo canonico ================= */
const NOMBRES = {
  per_francisco: 'Francisco', per_hortensia: 'Hortensia', per_amelia: 'Amelia',
  per_berto: 'Berto', per_carmen: 'Carmen', per_dolores: 'Dolores',
  per_sergio: 'Sergio', per_sandra: 'Sandra', per_nuria: 'Nuria'
};
const RESIDENTES = {};
Object.keys(NOMBRES).forEach(function (id) {
  RESIDENTES[id] = { presencia: 'residente', identidad_publica: { nombre: NOMBRES[id] } };
});

const S = {
  reloj: { dia_pueblo: 1, hora_actual: 12 },
  encuentros: [],
  intervencionesUsadas: {},
  seq: 0
};

function durDe(lugar) {
  const d = { lug_parque: 1, lug_cafeteria: 2, lug_biblioteca: 3 };
  return d[lugar] || 1;
}
function syncVida() {
  const now = S.reloj.dia_pueblo * 24 + S.reloj.hora_actual;
  S.encuentros.forEach(function (e) {
    const ini = e.dia * 24 + e.hora;
    const fin = ini + e.duracion_horas;
    if ((e.estado === 'programado') && now >= ini && now < fin) e.estado = 'en_curso';
    if (now >= fin && (e.estado === 'programado' || e.estado === 'en_curso')) {
      if (e.estado === 'en_curso') e.estado = 'terminado';
      else e.estado = 'terminado';
    }
  });
}
function vistaEnc(e) {
  return {
    id: e.id, tipo: e.tipo, intencion: e.intencion, estado: e.estado,
    dia: e.dia, hora: e.hora, es_hoy: e.dia === S.reloj.dia_pueblo,
    lugar: e.lugar, lugar_nombre: e.lugar_nombre,
    participantes: e.participantes,
    participantes_nombres: e.participantes.map(function (p) { return NOMBRES[p] || p; }),
    intervencion: vistaIntervencion(e)
  };
}
function vistaIntervencion(e) {
  if (e.estado !== 'en_curso') return { disponible: false, usada: false, acciones: [] };
  if (S.intervencionesUsadas[e.id]) {
    return { disponible: false, usada: true, acciones: [], ultimo: S.intervencionesUsadas[e.id] };
  }
  return {
    disponible: true, usada: false,
    acciones: [
      { id: 'hablar', etiqueta: 'Hablar', disponible: true },
      { id: 'broma', etiqueta: 'Soltar una broma', disponible: true },
      { id: 'coquetear', etiqueta: 'Coquetear', disponible: true },
      {
        id: 'hobby', etiqueta: 'Hablar de un hobby', disponible: true,
        hobbies: [{
          id: 'cine', etiqueta: 'Cine',
          residente_id: e.participantes[0],
          residente_nombre: NOMBRES[e.participantes[0]] || e.participantes[0],
          origen: 'propio'
        }]
      }
    ]
  };
}
function enCursoAhora() {
  const now = S.reloj.dia_pueblo * 24 + S.reloj.hora_actual;
  let best = null;
  let bestIni = null;
  const todos = [];
  S.encuentros.forEach(function (e) {
    const ini = e.dia * 24 + e.hora;
    const fin = ini + e.duracion_horas;
    if (e.estado === 'en_curso' && now >= ini && now < fin) {
      todos.push(e);
      if (bestIni === null || ini < bestIni) { best = e; bestIni = ini; }
    }
  });
  return { uno: best ? vistaEnc(best) : null, todos: todos };
}
function paquete() {
  syncVida();
  const curso = enCursoAhora();
  const futuros = S.encuentros.filter(function (e) {
    return e.estado === 'programado';
  }).sort(function (a, b) { return (a.dia * 24 + a.hora) - (b.dia * 24 + b.hora); });
  return {
    ok: true,
    estado: {
      reloj: { dia_pueblo: S.reloj.dia_pueblo, hora_actual: S.reloj.hora_actual },
      reloj_texto: 'Dia ' + S.reloj.dia_pueblo + ', ' + S.reloj.hora_actual + ':00',
      reloj_vista: { proximos_dias: [{ dia_pueblo: S.reloj.dia_pueblo, etiqueta: 'Hoy' }] },
      encuentro_en_curso: curso.uno,
      encuentros_en_curso: curso.todos.map(vistaEnc),
      proximo_encuentro: futuros.length ? vistaEnc(futuros[0]) : null,
      misiones_hoy: { misiones: [] },
      tutorial: null
    },
    partida: {
      meta: { partida_id: 'mock_aht' },
      residentes: RESIDENTES,
      encuentros: S.encuentros,
      relaciones_romanticas: [],
      misiones_diarias: { misiones: [] }
    },
    mapa: { lugares: [] },
    pueblo: { complejos: [], tokens: {} },
    buzon: { mensajes: [] },
    diario: { cotilleo: { hoy: [], ayer: [], viejos: [] }, entradas: [] }
  };
}

async function mockApi(page) {
  await page.route('**/api/index.php*', async function (route) {
    const url = new URL(route.request().url());
    const action = url.searchParams.get('action') || '';
    let body = {};
    try { body = route.request().postDataJSON() || {}; } catch (e) {}
    const json = function (obj) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(obj) });
    };
    if (action === 'partida.nueva') return json({ ok: true, partida_id: 'mock_aht' });
    if (action === 'partida.listar') return json({ ok: true, partidas: [] });
    if (action === 'partida.refresh') return json(paquete());
    if (action === 'reloj.avanzar') {
      let h = Math.max(1, Math.min(48, Number(body.horas) || 1));
      const lineas = [];
      while (h-- > 0) {
        S.reloj.hora_actual++;
        if (S.reloj.hora_actual > 23) { S.reloj.hora_actual = 0; S.reloj.dia_pueblo++; }
        syncVida();
      }
      return json({ ok: true, resumen_avance: { lineas: lineas } });
    }
    if (action === 'encuentro.programar') {
      const partes = body.participantes || [body.residente_a, body.residente_b];
      const dia = Number(body.dia), hora = Number(body.hora);
      const lugar = body.lugar || 'lug_cafeteria';
      if (!(dia > 0) || !(hora >= 0) || !Array.isArray(partes)) {
        return json({ ok: false, error: 'validacion' });
      }
      if (dia * 24 + hora <= S.reloj.dia_pueblo * 24 + S.reloj.hora_actual) {
        return json({ ok: false, error: 'HORA_PASADA' });
      }
      const dur = durDe(lugar);
      const e = {
        id: 'enc_mock' + (++S.seq),
        tipo: body.tipo || 'conocerse',
        intencion: 'celeste_organizado',
        participantes: partes.slice(0, 2),
        lugar: lugar,
        lugar_nombre: lugar.replace('lug_', ''),
        hora: hora, dia: dia,
        duracion_horas: dur,
        duracion_minutos: dur * 60,
        estado: 'programado',
        resultado: null
      };
      S.encuentros.push(e);
      return json({ ok: true, encuentro: JSON.parse(JSON.stringify(e)) });
    }
    if (action === 'encuentro.cancelar') {
      const e = S.encuentros.find(function (x) { return x.id === body.encuentro_id; });
      if (!e) return json({ ok: false, error: 'no_encontrado' });
      e.estado = 'cancelado';
      return json({ ok: true });
    }
    if (action === 'encuentro.intervencion.ejecutar') {
      const e = S.encuentros.find(function (x) { return x.id === body.encuentro_id; });
      if (!e) return json({ ok: false, error: 'no_encontrado' });
      const parTxt = e.participantes.map(function (p) { return NOMBRES[p] || p; }).join(' y ');
      const res = { accion: body.accion, tono: 'bien', texto: 'Buen rollo de ' + parTxt + '.' };
      S.intervencionesUsadas[e.id] = res;
      const curso = enCursoAhora();
      return json({
        ok: true,
        intervencion: res,
        vista: { disponible: false, usada: true, acciones: [], ultimo: res },
        estado_delta: {
          encuentro_en_curso: curso.uno,
          encuentros_en_curso: curso.todos.map(vistaEnc),
          buzon_pendientes: 0
        }
      });
    }
    return json({ ok: false, error: 'mock_no_action', action: action });
  });
}

async function saltarTutorial(page) {
  for (let i = 0; i < 10; i++) {
    const activo = await page.evaluate(function () {
      return document.body.getAttribute('data-tut-activo') === '1';
    });
    if (!activo) return;
    try { await page.locator('[data-tut-skip]').first().click({ timeout: 1500 }); } catch (e) { return; }
    await page.waitForTimeout(350);
  }
}

async function arrancar(contexto) {
  const page = await contexto.newPage();
  const erroresJs = [];
  page.on('pageerror', function (e) { erroresJs.push(String(e)); });
  await mockApi(page);
  await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  await page.evaluate(function () { try { localStorage.clear(); } catch (e) {} });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  await page.waitForTimeout(2200);
  await saltarTutorial(page);
  return { page, erroresJs };
}

async function avanzar(page, horas) {
  await page.evaluate(function (h) {
    return window.AHT_PLAY.api('reloj.avanzar', { horas: h, paso_a_paso: true }).then(function () {
      return window.AHT_PLAY.refresh();
    });
  }, horas);
  await page.waitForTimeout(700);
}

(async () => {
  const browser = await chromium.launch({ headless: true });

  /* ================= MOVIL 393x852 ================= */
  const ctxM = await browser.newContext({
    viewport: { width: 393, height: 852 }, isMobile: true, hasTouch: true
  });
  const m = await arrancar(ctxM);
  const page = m.page;

  // A) 0 planes en curso -> el bloque no existe (sin hueco)
  const aVis = await page.evaluate(function () {
    const b = document.querySelector('[data-encursos-block]');
    return b ? getComputedStyle(b).display : 'sin-bloque';
  });
  ok(aVis === 'none', 'A) 0 planes: bloque display:none (' + aVis + ')');

  // Sembrar 3 encuentros hoy (duraciones 1h/2h/3h para escalones E)
  const sembrados = [];
  const planSemillas = [
    ['per_francisco', 'per_hortensia', 'lug_parque'],
    ['per_amelia', 'per_berto', 'lug_cafeteria'],
    ['per_carmen', 'per_dolores', 'lug_biblioteca']
  ];
  for (let i = 0; i < planSemillas.length; i++) {
    const s = planSemillas[i];
    const r = await page.evaluate(function (args) {
      return window.AHT_PLAY.api('encuentro.programar', args[0]);
    }, [{ participantes: [s[0], s[1]], dia: 1, hora: 16, tipo: 'conocerse', lugar: s[2] }]);
    if (r.ok && r.encuentro) sembrados.push(r.encuentro);
    else console.log('  siembra fallida: ' + JSON.stringify(r));
  }
  ok(sembrados.length === 3, 'B/D) 3 encuentros sembrados a las 16:00 (duracion 1h/2h/3h)');
  await avanzar(page, 4); // 12 -> 16: los tres arrancan

  const vis = await page.evaluate(function () {
    const b = document.querySelector('[data-encursos-block]');
    const track = b.querySelector('[data-encursos-track]');
    const ind = b.querySelector('[data-encursos-indice]');
    const cards = b.querySelectorAll('[data-enc-mov-card]');
    const right = document.querySelector('.game-right');
    const coti = right.querySelector('.shell-grupo-cotilleo-par').getBoundingClientRect();
    const bRect = b.getBoundingClientRect();
    return {
      on: b.classList.contains('is-on'),
      display: getComputedStyle(b).display,
      n: cards.length,
      scrollable: track.scrollWidth > track.clientWidth + 4,
      indicador: ind.hidden ? '' : ind.textContent.trim(),
      encima: bRect.bottom <= coti.top + 1,
      primerCardTxt: cards[0] ? cards[0].textContent.replace(/\s+/g, ' ').trim().slice(0, 90) : ''
    };
  });
  ok(vis.on && vis.display !== 'none', 'B) bloque visible con planes en curso');
  ok(vis.n === 3, 'D) tres tarjetas simultaneas (' + vis.n + ')');
  ok(vis.scrollable, '4) carrusel desborda horizontal (siguiente asoma)');
  ok(/^\d+ \/ \d+$/.test(vis.indicador) && vis.indicador === '1 / 3', '4) indicador "1 / 3": "' + vis.indicador + '"');
  ok(vis.encima, '9) bloque ENCIMA de Cotilleos');
  console.log('  tarjeta 1: ' + vis.primerCardTxt);

  // B) CTA abre el flujo canonico e interviene
  await page.locator('[data-enc-mov-toggle]').first().click();
  await page.waitForTimeout(250);
  const ctaEstado = await page.evaluate(function () {
    const card = document.querySelector('[data-enc-mov-card]');
    const panel = card.querySelector('[data-enc-mov-panel]');
    return {
      abierto: !!panel && !panel.hidden,
      btns: panel ? panel.querySelectorAll('.enc-int-btn').length : 0,
      aria: card.querySelector('[data-enc-mov-toggle]').getAttribute('aria-expanded')
    };
  });
  ok(ctaEstado.abierto && ctaEstado.aria === 'true', '3) CTA abre el panel del encuentro');
  ok(ctaEstado.btns >= 2, '3) acciones canonicas visibles (' + ctaEstado.btns + ': hablar/broma/coquetear/hobby)');

  // I) panel de temas abre para el encuentro seleccionado (tarjeta A)
  const temasMov = await page.evaluate(function () {
    const card = document.querySelector('[data-enc-mov-card]');
    const tog = card.querySelector('[data-temas-toggle]');
    if (!tog) return { existe: false };
    tog.click();
    const pan = card.querySelector('[data-temas-panel]');
    return { existe: true, abierto: !!pan && !pan.hidden };
  });
  ok(temasMov.existe && temasMov.abierto,
    'I) movil: toggle de temas abre el panel del encuentro seleccionado');

  await page.locator('[data-enc-mov-card]').first()
    .locator('[data-enc-int-accion="hablar"]').first().click();
  let resTxt = '';
  try {
    await page.waitForSelector('[data-enc-mov-card] .enc-int-result-txt', { timeout: 12000 });
    resTxt = await page.evaluate(function () {
      return document.querySelector('[data-enc-mov-card] .enc-int-result-txt').textContent.trim();
    });
  } catch (e) {}
  ok(resTxt.indexOf('Buen rollo') >= 0, '3) intervencion canonica ejecutada desde la tarjeta: "' + resTxt + '"');
  const sigueAbierto = await page.evaluate(function () {
    const card = document.querySelector('[data-enc-mov-card]');
    return !(card.querySelector('[data-enc-mov-panel]') || { hidden: true }).hidden;
  });
  ok(sigueAbierto, '3) panel permanece abierto tras el render automatico');

  // C/D/E) identidad movil: intervenir A NO contamina a B
  const idMov = await page.evaluate(function () {
    return Array.prototype.map.call(document.querySelectorAll('[data-enc-mov-card]'), function (c) {
      return {
        id: c.getAttribute('data-enc-id'),
        txt: c.textContent,
        resultado: !!c.querySelector('.enc-int-result-txt'),
        acciones: c.querySelectorAll('[data-enc-int-accion]').length
      };
    });
  });
  const movA = idMov[0];
  const movB = idMov[1];
  ok(movA && movA.resultado && /Buen rollo de Francisco y Hortensia/.test(movA.txt),
    'C) tarjeta A conserva SU resultado con SU pareja');
  ok(movB && !movB.resultado && movB.acciones > 0 && movB.txt.indexOf('Buen rollo') < 0,
    'D/E) tarjeta B intacta tras intervenir A (acciones=' + (movB ? movB.acciones : 0) + ', sin resultado ajeno)');
  await page.locator('[data-enc-mov-card][data-enc-id="' + movB.id + '"] [data-enc-mov-toggle]').click();
  await page.waitForTimeout(250);
  await page.locator('[data-enc-mov-card][data-enc-id="' + movB.id + '"]')
    .locator('[data-enc-int-accion="broma"]').first().click();
  let resB = '';
  try {
    await page.waitForSelector('[data-enc-mov-card][data-enc-id="' + movB.id + '"] .enc-int-result-txt', { timeout: 12000 });
    resB = await page.evaluate(function (id) {
      return document.querySelector('[data-enc-mov-card][data-enc-id="' + id + '"] .enc-int-result-txt').textContent.trim();
    }, movB.id);
  } catch (e) {}
  ok(/Buen rollo de Amelia y Berto/.test(resB), 'D) intervenir B produce SOLO el resultado de B: "' + resB + '"');
  const movADespues = await page.evaluate(function () {
    const c = document.querySelector('[data-enc-mov-card]');
    return { res: c.textContent, tieneRes: !!c.querySelector('.enc-int-result-txt') };
  });
  ok(movADespues.tieneRes && /Francisco y Hortensia/.test(movADespues.res),
    'E) el estado_delta de B no machaca el resultado de A');

  await page.screenshot({ path: path.join(OUT, 'movil-393-con-bloque.png'), fullPage: false });

  // D) swipe horizontal: solo se desplaza, cero llamadas de red
  await page.evaluate(function () {
    window.__fetches = 0;
    var of = window.fetch;
    window.fetch = function () { window.__fetches++; return of.apply(this, arguments); };
  });
  await page.evaluate(function () {
    var track = document.querySelector('[data-encursos-track]');
    track.scrollLeft = track.scrollWidth; // al fondo
  });
  await page.waitForTimeout(500);
  const sw = await page.evaluate(function () {
    var track = document.querySelector('[data-encursos-track]');
    var ind = document.querySelector('[data-encursos-indice]');
    return {
      movio: track.scrollLeft > 20,
      indicador: ind.hidden ? '' : ind.textContent.trim(),
      fetches: window.__fetches
    };
  });
  ok(sw.movio, 'D) swipe mueve el carrusel hasta la ultima tarjeta');
  ok(sw.indicador === '3 / 3', '5) indicador tras swipe: "' + sw.indicador + '"');
  ok(sw.fetches === 0, '5) swipe NO ejecuta nada (fetches=' + sw.fetches + ')');

  // E) termina el de 1h (parque): quedan 2 sin tocar nada
  await avanzar(page, 1); // 17: parque terminado
  const visE = await page.evaluate(function () {
    var b = document.querySelector('[data-encursos-block]');
    return { on: b.classList.contains('is-on'), n: b.querySelectorAll('[data-enc-mov-card]').length };
  });
  ok(visE.on && visE.n === 2, 'E) tras terminar uno quedan 2 tarjetas (' + visE.n + ') y bloque activo');
  const indE = await page.evaluate(function () {
    var ind = document.querySelector('[data-encursos-indice]');
    return ind.hidden ? '' : ind.textContent.trim();
  });
  ok(/^\d+ \/ 2$/.test(indE),
    'E) indicador coherente con 2 tarjetas (conserva posicion del usuario): "' + indE + '"');

  // F) terminan todos -> desaparece todo el bloque, Cotilleos ocupa su sitio
  await avanzar(page, 2); // 19: cafeteria (2h) termina; biblioteca 16+3=19 tambien
  const visF = await page.evaluate(function () {
    var b = document.querySelector('[data-encursos-block]');
    var coti = document.querySelector('.obj-cotilleo-par');
    return {
      on: b.classList.contains('is-on'),
      display: getComputedStyle(b).display,
      n: b.querySelectorAll('[data-enc-mov-card]').length,
      cotiVisible: !!coti && coti.getBoundingClientRect().height > 10
    };
  });
  ok(!visF.on && visF.display === 'none' && visF.n === 0, 'F) sin planes: bloque fuera del todo');
  ok(visF.cotiVisible, 'F) Cotilleos vuelve a su posicion normal');
  await page.screenshot({ path: path.join(OUT, 'movil-393-sin-bloque.png'), fullPage: false });

  // G) plan futuro NO aparece
  await page.evaluate(function () {
    return window.AHT_PLAY.api('encuentro.programar', {
      participantes: ['per_sergio', 'per_sandra'], dia: 3, hora: 18, tipo: 'conocerse', lugar: 'lug_parque'
    });
  });
  await avanzar(page, 0);
  const visG = await page.evaluate(function () {
    var b = document.querySelector('[data-encursos-block]');
    return { on: b.classList.contains('is-on'), n: b.querySelectorAll('[data-enc-mov-card]').length };
  });
  ok(!visG.on && visG.n === 0, 'G) plan futuro no aparece');

  // H) rechazado/cancelado NO aparece
  await page.evaluate(function () {
    return window.AHT_PLAY.api('encuentro.programar', {
      participantes: ['per_sergio', 'per_sandra'], dia: 1, hora: 21, tipo: 'conocerse', lugar: 'lug_parque'
    }).then(function (r) {
      return window.AHT_PLAY.api('encuentro.cancelar', { encuentro_id: r.encuentro.id });
    });
  });
  await avanzar(page, 0);
  const visH = await page.evaluate(function () {
    var b = document.querySelector('[data-encursos-block]');
    return { on: b.classList.contains('is-on'), n: b.querySelectorAll('[data-enc-mov-card]').length };
  });
  ok(!visH.on && visH.n === 0, 'H) plan cancelado no aparece');
  ok(m.erroresJs.length === 0, 'movil 393: sin errores JS (' + m.erroresJs.join(' | ').slice(0, 160) + ')');
  await ctxM.close();

  /* ================= MOVIL 360x740 ================= */
  S.reloj = { dia_pueblo: 1, hora_actual: 12 };
  S.encuentros = [];
  S.intervencionesUsadas = {};
  S.seq = 0;
  const ctxN = await browser.newContext({ viewport: { width: 360, height: 740 }, isMobile: true, hasTouch: true });
  const n = await arrancar(ctxN);
  const pn = n.page;
  await page_eval_programar(pn, [['per_francisco', 'per_hortensia', 'lug_parque'], ['per_amelia', 'per_berto', 'lug_cafeteria']]);
  await avanzar(pn, 4);
  const visN = await pn.evaluate(function () {
    var b = document.querySelector('[data-encursos-block]');
    return { on: b.classList.contains('is-on'), n: b.querySelectorAll('[data-enc-mov-card]').length };
  });
  ok(visN.on && visN.n === 2, '360x740: bloque visible con 2 tarjetas (' + visN.n + ')');
  await pn.screenshot({ path: path.join(OUT, 'movil-360-con-bloque.png'), fullPage: false });
  await pn.locator('[data-enc-mov-toggle]').first().click();
  await pn.waitForTimeout(300);
  await pn.screenshot({ path: path.join(OUT, 'movil-360-panel-abierto.png'), fullPage: false });
  ok(n.erroresJs.length === 0, 'movil 360: sin errores JS');
  await ctxN.close();

  /* ================= DESKTOP: nav ‹ 1/N › + identidad + auto-reseleccion ================= */
  S.reloj = { dia_pueblo: 1, hora_actual: 12 };
  S.encuentros = [];
  S.intervencionesUsadas = {};
  S.seq = 0;
  const ctxD = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const d = await arrancar(ctxD);
  const pd = d.page;

  const nav0 = await pd.evaluate(function () {
    var nav = document.querySelector('[data-curso-nav]');
    return nav ? nav.hidden : null;
  });
  ok(nav0 === true, 'G) desktop 0 planes: nav oculta');

  await page_eval_programar(pd, [
    ['per_francisco', 'per_hortensia', 'lug_parque'],
    ['per_amelia', 'per_berto', 'lug_cafeteria']
  ]);
  await avanzar(pd, 4); // 16:00: ambos en curso

  const desk2 = await pd.evaluate(function () {
    var nav = document.querySelector('[data-curso-nav]');
    var cont = nav.querySelector('[data-curso-cont]');
    var nombres = document.querySelector('[data-proximo-plan] .prox-nombres');
    var bloque = document.querySelector('[data-encursos-block]');
    return {
      navHidden: nav.hidden,
      cont: cont.textContent.trim(),
      nombres: nombres.textContent.trim(),
      displayBloque: getComputedStyle(bloque).display
    };
  });
  ok(desk2.displayBloque === 'none', 'J) desktop 1440: bloque movil oculto aunque hay 2 en curso');
  ok(!desk2.navHidden && desk2.cont === '1 / 2',
    'G) desktop: nav visible, contador "1 / 2" ("' + desk2.cont + '")');
  ok(desk2.nombres.indexOf('Francisco') >= 0, 'B) seleccion inicial = encuentro A (' + desk2.nombres + ')');
  await pd.screenshot({ path: path.join(OUT, 'desktop-1440-nav-1de2.png'), fullPage: false });

  // navegar a B sin llamar a la API
  await pd.evaluate(function () {
    window.__fetchesNav = 0;
    var of = window.fetch;
    window.fetch = function () { window.__fetchesNav++; return of.apply(this, arguments); };
  });
  await pd.locator('[data-curso-next]').click();
  await pd.waitForTimeout(350);
  const desk3 = await pd.evaluate(function () {
    var nav = document.querySelector('[data-curso-nav]');
    return {
      cont: nav.querySelector('[data-curso-cont]').textContent.trim(),
      nombres: document.querySelector('[data-proximo-plan] .prox-nombres').textContent.trim(),
      fetches: window.__fetchesNav
    };
  });
  ok(desk3.cont === '2 / 2' && desk3.nombres.indexOf('Amelia') >= 0 && desk3.fetches === 0,
    'B/G) siguiente -> "2 / 2" con encuentro B, cero llamadas API (fetches=' + desk3.fetches + ')');

  // I+D) temas abre para B y al elegir tema SOLO B queda intervenido
  await pd.locator('[data-proximo-plan] [data-temas-toggle]').click();
  await pd.waitForTimeout(250);
  const temasDesk = await pd.evaluate(function () {
    var pan = document.querySelector('[data-proximo-plan] [data-temas-panel]');
    return !!pan && !pan.hidden;
  });
  ok(temasDesk, 'I) desktop: toggle de temas abre el panel del encuentro seleccionado (B)');
  await pd.locator('[data-proximo-plan] [data-temas-panel] [data-enc-int-accion="hobby"]').first().click();
  let resBDesk = '';
  try {
    await pd.waitForSelector('[data-proximo-plan] .enc-int-result-txt', { timeout: 12000 });
    resBDesk = await pd.evaluate(function () {
      return document.querySelector('[data-proximo-plan] .enc-int-result-txt').textContent.trim();
    });
  } catch (e) {}
  ok(/Buen rollo de Amelia y Berto/.test(resBDesk), 'D) desktop: intervenir B produce SOLO el resultado de B');

  // E) volver a A: A sigue sin resultado (el delta de B no le llega)
  await pd.locator('[data-curso-prev]').click();
  await pd.waitForTimeout(350);
  const deskA = await pd.evaluate(function () {
    var box = document.querySelector('[data-proximo-plan]');
    return {
      cont: document.querySelector('[data-curso-cont]').textContent.trim(),
      nombres: box.querySelector('.prox-nombres').textContent.trim(),
      tieneRes: !!box.querySelector('.enc-int-result-txt'),
      acciones: box.querySelectorAll('[data-enc-int-accion]').length
    };
  });
  ok(deskA.cont === '1 / 2' && deskA.nombres.indexOf('Francisco') >= 0 && !deskA.tieneRes && deskA.acciones > 0,
    'E) desktop: A intacto tras intervenir B (acciones=' + deskA.acciones + ')');

  // C) intervenir A desde la polaroid
  await pd.locator('[data-proximo-plan] [data-enc-int-accion="hablar"]').first().click();
  let resADesk = '';
  try {
    await pd.waitForSelector('[data-proximo-plan] .enc-int-result-txt', { timeout: 12000 });
    resADesk = await pd.evaluate(function () {
      return document.querySelector('[data-proximo-plan] .enc-int-result-txt').textContent.trim();
    });
  } catch (e) {}
  ok(/Buen rollo de Francisco y Hortensia/.test(resADesk), 'C) desktop: intervenir A produce SOLO el resultado de A');

  // F) termina A (parque 1h) mientras B sigue -> auto-seleccion de B
  await avanzar(pd, 1); // 17:00
  const deskF = await pd.evaluate(function () {
    var nav = document.querySelector('[data-curso-nav]');
    var box = document.querySelector('[data-proximo-plan]');
    return {
      navHidden: nav.hidden,
      cont: nav.querySelector('[data-curso-cont]').textContent.trim(),
      nombres: box.querySelector('.prox-nombres').textContent.trim(),
      resTxt: box.querySelector('.enc-int-result-txt') ? box.querySelector('.enc-int-result-txt').textContent.trim() : ''
    };
  });
  ok(deskF.navHidden && /Amelia/.test(deskF.nombres),
    'F) terminado el seleccionado, auto-seleccion del activo restante (' + deskF.nombres + ')');
  ok(/Amelia y Berto/.test(deskF.resTxt), 'F) el resultado propio de B se conserva tras re-render');
  await pd.screenshot({ path: path.join(OUT, 'desktop-1440-auto-reseleccion.png'), fullPage: false });

  const deskFin = await pd.evaluate(function () {
    var pol = document.querySelector('.obj-proximo-polaroid');
    var tit = pol.querySelector('.obj-proximo-tit').textContent.trim();
    return { polaroidVisible: pol.getBoundingClientRect().height > 10, titulo: tit };
  });
  ok(deskFin.polaroidVisible && deskFin.titulo.toLowerCase().indexOf('curso') >= 0,
    'I) DESKTOP: polaroid en modo Plan en curso ("' + deskFin.titulo + '")');
  await pd.screenshot({ path: path.join(OUT, 'desktop-1440-intacto.png'), fullPage: false });
  ok(d.erroresJs.length === 0, 'desktop: sin errores JS');
  await ctxD.close();

  await browser.close();
  console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
  process.exit(failures === 0 ? 0 : 1);
})().catch(function (e) {
  console.error('ERROR VERIFICACION:', e);
  process.exit(1);
});

async function page_eval_programar(page, semillas) {
  for (let i = 0; i < semillas.length; i++) {
    const s = semillas[i];
    await page.evaluate(function (args) {
      return window.AHT_PLAY.api('encuentro.programar', {
        participantes: [args[0], args[1]], dia: 1, hora: 16, tipo: 'conocerse', lugar: args[2]
      });
    }, s);
  }
}
