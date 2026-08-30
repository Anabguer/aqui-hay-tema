'use strict';
// Prueba dirigida UI: finale del tutorial.
// Regresión cerrada: cerrarTutFinale() aplicaba hidden al <body> porque el modal
// se resolvía con el selector genérico [data-tut-finale], que también casa con
// <body data-tut-finale="1"> (atributo de estado) → pantalla completamente blanca.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const cssApp = fs.readFileSync(path.join(root, 'assets/css/play-v3-app.css'), 'utf8');
const cssDS = fs.readFileSync(path.join(root, 'assets/css/play-v3-tutorial-ds.css'), 'utf8');
const cssResp = fs.readFileSync(path.join(root, 'assets/css/play-v3-responsive.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

// --- Markup: el modal es un <aside> único; el body solo lleva el atributo de estado ---
ok((php.match(/<aside[^>]*data-tut-finale[^>]*>/g) || []).length === 1, 'play.php: un único <aside data-tut-finale>');
ok(/class="tut-finale"/.test(php.match(/<aside[^>]*data-tut-finale[^>]*>/)[0]), 'play.php: el aside lleva la clase tut-finale');

// --- JS: el selector del modal nunca puede resolver a <body> ---
ok(!/\$\('\[data-tut-finale\]'\)/.test(js), 'JS: prohibido el selector genérico $(\'[data-tut-finale]\') (colisiona con el body)');
ok((js.match(/\$\('aside\[data-tut-finale\]'\)/g) || []).length === 2, 'JS: quizaMostrarTutFinale y cerrarTutFinale resuelven aside[data-tut-finale]');
ok(/document\.body\.setAttribute\('data-tut-finale',\s*'1'\)/.test(js), 'JS: estado body[data-tut-finale="1"] se mantiene (setAttribute)');
ok(/document\.body\.removeAttribute\('data-tut-finale'\)/.test(js), 'JS: el estado del body se limpia al cerrar (removeAttribute)');
ok(/syncScrollLock\(\);/.test(js.split('async function cerrarTutFinale')[1].split('\n  }')[0]), 'JS: cerrarTutFinale libera el scroll-lock');

// --- Arnés de ejecución REAL con DOM simulado que REPLICA la colisión original:
// document.querySelector('[data-tut-finale]') devuelve <body> cuando lleva el atributo;
// 'aside[data-tut-finale]' SIEMPRE devuelve el modal. ---
async function arnesReal() {
  function extraerFuncion(nombre) {
    let ini = js.indexOf('function ' + nombre);
    // conservar el modificador async si existe
    if (js.slice(ini - 6, ini) === 'async ') ini -= 6;
    let i = js.indexOf('{', ini), nivel = 0, fin = -1;
    for (; i < js.length; i++) {
      if (js[i] === '{') nivel++;
      else if (js[i] === '}') { nivel--; if (nivel === 0) { fin = i; break; } }
    }
    return js.slice(ini, fin + 1);
  }
  function crearDom() {
    const nodo = (tag) => ({
      tag,
      hidden: false,
      attrs: {},
      textContent: '',
      classList: { add() {}, remove() {}, contains: () => false },
      style: {},
      dataset: {},
      getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
      setAttribute(k, v) { this.attrs[k] = String(v); },
      removeAttribute(k) { delete this.attrs[k]; }
    });
    const dom = { body: nodo('BODY'), aside: nodo('ASIDE'), scrollLockOn: false };
    dom.body.attrs['class'] = 'play-v3';
    dom.$ = (sel) => {
      if (sel === 'aside[data-tut-finale]') return dom.aside;
      if (sel === '[data-tut-finale]') return dom.body.getAttribute('data-tut-finale') !== null ? dom.body : dom.aside;
      return nodo('DIV');
    };
    dom.syncScrollLock = () => {
      const abierta = dom.body.getAttribute('data-capa') || dom.body.getAttribute('data-tut-finale') === '1';
      dom.scrollLockOn = !!abierta;
    };
    dom.apiCalls = 0;
    dom.api = async () => { dom.apiCalls++; return { ok: true }; };
    dom.refresh = async () => {};
    dom.setCapa = (name) => { if (!name) dom.body.removeAttribute('data-capa'); else dom.body.setAttribute('data-capa', name); };
    return dom;
  }
  async function compilar(dom, cacheEstado) {
    const fuente = extraerFuncion('quizaMostrarTutFinale') + '\n' + extraerFuncion('cerrarTutFinale');
    // AsyncFunction: cerrarTutFinale es async y usa await dentro.
    const AsyncFunction = Object.getPrototypeOf(async function () {}).constructor;
    const esc = (s) => s;
    const tutAssetUrl = (name) => 'assets/play-v3/tutorial/' + name;
    const fabrica = await new AsyncFunction('$', 'cacheEstado', 'syncScrollLock', 'api', 'refresh', 'setCapa', 'document', 'esc', 'tutAssetUrl',
      fuente + '\n return { mostrar: quizaMostrarTutFinale, cerrar: cerrarTutFinale };')(
      dom.$, cacheEstado, dom.syncScrollLock, dom.api, dom.refresh, dom.setCapa, { body: dom.body, querySelector: dom.$ }, esc, tutAssetUrl);
    return fabrica;
  }
  const tutViva = () => ({ finale_pendiente: true, finale: { tit: 'T', txt: 'X', boton: 'OK' } });

  // A) primer render: body sin marcar → muestra el aside y marca el body
  const dom = crearDom();
  const fn = await compilar(dom, { tutorial: tutViva() });
  fn.mostrar();
  ok(dom.aside.hidden === false, 'arnés A: el modal (aside) queda visible');
  ok(dom.body.getAttribute('data-tut-finale') === '1', 'arnés A: body[data-tut-finale="1"] presente');
  ok(dom.body.hidden === false, 'arnés A: el body NUNCA queda hidden al mostrar');

  // B) segundo render (refresh/polling) con el body YA marcado: la colisión original
  ok(dom.$('[data-tut-finale]') === dom.body, 'arnés B: colisión original replicada (el selector genérico resolvería <body>)');
  fn.mostrar();
  ok(dom.aside.hidden === false && dom.body.hidden === false, 'arnés B: ni aside ni body resultan ocultados en re-render');

  // C) cierre: hidden SOLO al aside; body visible, sin estado y sin scroll-lock
  await fn.cerrar();
  ok(dom.aside.hidden === true, 'arnés C: cerrarTutFinale oculta el MODAL (aside)');
  ok(dom.body.hidden === false, 'arnés C: CRÍTICO el body jamás recibe hidden (pantalla blanca imposible)');
  ok(dom.body.getAttribute('data-tut-finale') === null, 'arnés C: estado body[data-tut-finale] retirado');
  ok(dom.scrollLockOn === false, 'arnés C: scroll-lock liberado tras cerrar');
  ok(dom.apiCalls === 1, 'arnés C: partida.tutorial_finale llamada exactamente una vez');
}

// --- CSS desktop: reglas base modales (patrón .tut-intro) — autoridad canonical = tutorial-ds.css ---
ok(/\.tut-finale\s*\{[^}]*position:\s*fixed/.test(cssDS), 'CSS base: .tut-finale position:fixed (desktop incluido)');
ok(/\.tut-finale\s*\{[^}]*inset:\s*0/.test(cssDS), 'CSS base: .tut-finale cubre viewport (inset:0)');
ok(/\.tut-finale\s*\{[^}]*z-index:\s*520/.test(cssDS), 'CSS base: .tut-finale z-index 520 (mismo canon que móvil)');
ok(/\.tut-finale\s*\{[^}]*display:\s*grid[^}]*place-items:\s*center/.test(cssDS.replace(/\n/g, ' ')), 'CSS base: .tut-finale centrado (grid place-items:center)');
ok(/\.tut-finale\s*\{[^}]*background:.*rgba\(30,\s*25,\s*2[25]/.test(cssDS.replace(/\n/g, ' ')), 'CSS base: .tut-finale backdrop oscuro');
ok(/\.tut-finale\[hidden\]\s*\{\s*display:\s*none\s*!important;\s*\}/.test(cssDS), 'CSS base: [hidden] oculta de verdad el modal');

arnesReal().then(() => {
  // --- Z-index canonico: 520 overlay, 521 papel (ahora en tutorial-ds.css, aplica mobile+desktop) ---
  ok(/\.tut-finale\s*\{[^}]*z-index:\s*520/.test(cssDS), 'CSS: .tut-finale z-index 520 (canonico)');
  ok(/\.tut-finale \.tut-papel\s*\{[^}]*z-index:\s*521/.test(cssDS), 'CSS: .tut-finale .tut-papel z-index 521 (canonico)');

  console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
  process.exit(failures === 0 ? 0 : 1);
});
