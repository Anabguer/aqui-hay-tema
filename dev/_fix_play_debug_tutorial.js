/**
 * DEBUG integrado + tutorial V3 único (sin TUT_PASOS fallback).
 * node dev/_fix_play_debug_tutorial.js
 */
const fs = require('fs');
const playJs = 'assets/js/play-v3.js';
const playPhp = 'play.php';
let js = fs.readFileSync(playJs, 'utf8').replace(/\r\n/g, '\n');
let php = fs.readFileSync(playPhp, 'utf8').replace(/\r\n/g, '\n');

function repJs(from, to, label) {
  if (!js.includes(from)) {
    console.error('JS MISSING:', label);
    process.exit(1);
  }
  js = js.replace(from, to);
}

// --- DEBUG: sustituir IS_LAB ---
repJs(
  `  const CONFIG_LAB = { config_id: 'playtest_01', seed: 'playtest-01' };
  const IS_LAB = (function () {
    if (typeof window !== 'undefined' && window.__AHT_LAB__ === 1) return true;
    var dl = document.body && document.body.getAttribute('data-lab');
    if (dl === '1') return true;
    var lv = qs.get('lab');
    return lv === '1' || lv === 'true';
  })();
  if (IS_LAB) {
    try { sessionStorage.setItem('aht_lab', '1'); } catch (e) {}
    try { console.log('%c[AHT LAB] Modo laboratorio activo (?lab=1)', 'color:#c45;font-weight:bold'); } catch (e2) {}
  }`,
  `  const DEBUG_KEY = 'aht_debug_on';
  let DEBUG_ON = false;
  try { DEBUG_ON = localStorage.getItem(DEBUG_KEY) === '1'; } catch (e) {}
  function setDebugOn(on) {
    DEBUG_ON = !!on;
    try { localStorage.setItem(DEBUG_KEY, DEBUG_ON ? '1' : '0'); } catch (e2) {}
    document.body.setAttribute('data-debug', DEBUG_ON ? '1' : '0');
    if (DEBUG_ON) {
      try { console.log('%c[AHT DEBUG] Instrumentación activa', 'color:#c45;font-weight:bold'); } catch (e3) {}
    }
  }
  function isDebugOn() { return DEBUG_ON; }`,
  'DEBUG system'
);

js = js.replace(/\bIS_LAB\b/g, 'isDebugOn()');

repJs(
  `      if (forceFreshSeed || isDebugOn()) {
        o.seed = 'playtest-' + Date.now().toString(36);
      } else if (qs.get('seed')) {`,
  `      if (qs.get('seed')) {`,
  'configNueva seed'
);

repJs(
  `    if (isDebugOn()) {
      return { config_id: 'juego_v1', seed: 'lab-' + Date.now().toString(36) };
    }
    return CONFIG_JUEGO;`,
  `    return CONFIG_JUEGO;`,
  'configNueva lab config'
);

repJs(
  `  let partidaId = localStorage.getItem(isDebugOn() ? 'aht_partida_id' : 'aht_partida_id_juego');`,
  `  let partidaId = localStorage.getItem('aht_partida_id_juego');`,
  'storage partida'
);

repJs(
  `  function storageKey() { return isDebugOn() ? 'aht_partida_id' : 'aht_partida_id_juego'; }`,
  `  function storageKey() { return 'aht_partida_id_juego'; }`,
  'storageKey'
);

repJs(
  `    if (isDebugOn()) body.lab = 1;`,
  `    if (isDebugOn()) body.debug = 1;`,
  'api body debug'
);

repJs(
  `      if (isDebugOn()) q.set('lab', '1');`,
  `      if (isDebugOn()) q.set('debug', '1');`,
  'api get debug'
);

repJs(
  `      if (isDebugOn()) url += '&lab=1';`,
  `      if (isDebugOn()) url += '&debug=1';`,
  'api post debug'
);

repJs(
  `    if (!isDebugOn() || !payload || !payload.lab_audit || !Array.isArray(payload.lab_audit.eventos)) return;`,
  `    if (!isDebugOn() || !payload || !payload.lab_audit || !Array.isArray(payload.lab_audit.eventos)) return;`,
  'audit guard noop'
);

// --- Tutorial V3: quitar fallback legacy ---
repJs(
  `  const TUT_PASOS = [
    {
      tit: 'Bienvenida',
      txt: 'Este es tu pueblo: vecinos con vida propia, nueve lugares y un reloj que sigue aunque tú no hagas nada. Tú observas y propones planes; ellos deciden.'
    },
    {
      tit: 'Tus vecinos',
      txt: 'Al empezar hay tres vecinos en el pueblo. Puedes verlos en el mapa, en Vecinos y en sus fichas.'
    },
    {
      tit: 'Qué puedes hacer',
      txt: 'Mensajitos trae recados. Vecinos es la libreta del pueblo. Nuevo Plan propone un encuentro. Hoy en el pueblo marca tus objetivos del día.'
    },
    {
      tit: 'Empieza por Hoy en el pueblo',
      txt: 'Las primeras misiones te enseñarán jugando. Cuando cierres esta bienvenida, abriremos Hoy en el pueblo.'
    }
  ];`,
  `  /* Tutorial intro: solo servidor (TutorialPrimerosPasos::vistaPublica). Sin copy legacy en cliente. */`,
  'remove TUT_PASOS'
);

// Fallback variant with mojibake
if (js.includes('const TUT_PASOS = [')) {
  js = js.replace(/const TUT_PASOS = \[[\s\S]*?\];\n/, '  /* TUT_PASOS legacy eliminado */\n');
}

repJs(
  `  function tutPasosActuales() {
    if (cacheEstado && cacheEstado.tutorial && cacheEstado.tutorial.intro && cacheEstado.tutorial.intro.pasos) {
      return cacheEstado.tutorial.intro.pasos;
    }
    return TUT_PASOS;
  }`,
  `  function tutPasosActuales() {
    if (cacheEstado && cacheEstado.tutorial && cacheEstado.tutorial.intro && cacheEstado.tutorial.intro.pasos) {
      return cacheEstado.tutorial.intro.pasos;
    }
    return [];
  }
  function tieneTutorialV3() {
    return !!(cacheEstado && cacheEstado.tutorial && cacheEstado.tutorial.id === 'primeros_pasos'
      && cacheEstado.tutorial.intro && cacheEstado.tutorial.intro.pasos && cacheEstado.tutorial.intro.pasos.length);
  }`,
  'tutPasosActuales'
);

repJs(
  `  function quizaMostrarTutIntro() {
    if (isDebugOn() || tutIntroHecho()) {
      const reopen = $('[data-tut-reopen]');
      if (reopen && tutIntroHecho()) reopen.hidden = false;
      return;
    }
    abrirTutIntro(true);
  }`,
  `  function quizaMostrarTutIntro() {
    const reopen = $('[data-tut-reopen]');
    if (!tieneTutorialV3()) {
      if (reopen) reopen.hidden = true;
      return;
    }
    if (tutIntroHecho()) {
      if (reopen) reopen.hidden = false;
      return;
    }
    abrirTutIntro(true);
  }`,
  'quizaMostrarTutIntro'
);

repJs(
  `  function pintarTutorialMotor(tut) {
    const pista = $('[data-tutorial-pista]');
    if (!pista) return;
    if (!tut || !tut.activo || !tut.pista) {`,
  `  function pintarTutorialMotor(tut) {
    const pista = $('[data-tutorial-pista]');
    if (!pista) return;
    if (tut && tut.id === 'primeros_pasos') {
      pista.hidden = true;
      pista.textContent = '';
      document.body.removeAttribute('data-tutorial-zona');
      return;
    }
    if (!tut || !tut.activo || !tut.pista) {`,
  'pintarTutorialMotor skip V3'
);

repJs(
  `  const tutReopen = $('[data-tut-reopen]');
  if (tutReopen) tutReopen.addEventListener('click', function () { abrirTutIntro(true); });`,
  `  const tutReopen = $('[data-tut-reopen]');
  if (tutReopen) tutReopen.addEventListener('click', function () {
    if (!tieneTutorialV3()) return;
    abrirTutIntro(true);
  });`,
  'tutReopen V3 only'
);

// DEBUG panel toggle
if (!js.includes('function initDebugPanel')) {
  repJs(
    `  const ptToggle = $('[data-playtest-toggle]');
  const ptPanel = document.querySelector('[data-playtest-float] .playtest-float-panel');
  if (ptToggle && ptPanel) {
    ptToggle.addEventListener('click', function () {
      var open = ptPanel.hasAttribute('hidden');
      if (open) ptPanel.removeAttribute('hidden');
      else ptPanel.setAttribute('hidden', 'hidden');
      ptToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }`,
  `  function initDebugPanel() {
    setDebugOn(DEBUG_ON);
    const ptToggle = $('[data-debug-toggle]');
    const ptPanel = document.querySelector('[data-debug-panel]');
    if (ptToggle && ptPanel) {
      ptToggle.addEventListener('click', function () {
        var opening = ptPanel.hasAttribute('hidden');
        if (opening) {
          setDebugOn(true);
          ptPanel.removeAttribute('hidden');
        } else {
          ptPanel.setAttribute('hidden', 'hidden');
        }
        ptToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        ptToggle.textContent = opening ? '🧪 DEBUG ▾' : '🧪 DEBUG';
      });
    }
  }
  initDebugPanel();`,
  'initDebugPanel'
);
}

// Null-safe org listeners
repJs(
  `  $('[data-org-a]').addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });
  $('[data-org-b]').addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });
  $('[data-org-go]').addEventListener('click', proponer);`,
  `  var orgA = $('[data-org-a]');
  var orgB = $('[data-org-b]');
  var orgGo = $('[data-org-go]');
  if (orgA) orgA.addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });
  if (orgB) orgB.addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });
  if (orgGo) orgGo.addEventListener('click', proponer);`,
  'org null guards'
);

// DEBUG controls always bound
repJs(
  `  if (isDebugOn()) {
    const btnGuardar = $('#btn-guardar');
    if (btnGuardar) {
      btnGuardar.addEventListener('click', async function () {
        await api('partida.guardar', {});
        toast('Guardado.');
      });
    }
    const btnNueva = $('#btn-nueva');
    if (btnNueva) {
      btnNueva.addEventListener('click', async function () {
        await nuevaPartidaLimpia();
      });
    }
  }`,
  `  (function bindDebugControls() {
    const btnGuardar = $('#btn-debug-guardar');
    if (btnGuardar) btnGuardar.addEventListener('click', async function () {
      await api('partida.guardar', {});
      toast('Guardado.');
    });
    const btnNueva = $('#btn-debug-nueva');
    if (btnNueva) btnNueva.addEventListener('click', async function () {
      await nuevaPartidaLimpia();
    });
  })();`,
  'debug controls'
);

repJs(
  `  function bindLabHoras() {
    var scope = isDebugOn() ? document.querySelector('[data-playtest-float]') : null;`,
  `  function bindLabHoras() {
    var scope = document.querySelector('[data-debug-panel]');`,
  'bindLabHoras scope'
);

fs.writeFileSync(playJs, js);

// --- play.php ---
php = php.replace(/\$ahtUi = '[^']+';/, "$ahtUi = 'v3-20260822debug';");
php = php.replace(/\$ahtLab = [^;]+;\s*\n\$ahtTaller = [^;]+;[^\n]*\n/, '');
php = php.replace(/<meta name="aht-lab"[^>]+>\s*\n/, '');
php = php.replace(/<\?= \$ahtLab \? 'Playtest · Aquí Hay Tema' : 'Aquí Hay Tema' \?>/, 'Aquí Hay Tema');
php = php.replace(
  /<body class="play-v3" data-ui="v3" data-taller="<\?= \$ahtTaller \? '1' : '0' \?>" data-lab="<\?= \$ahtLab \? '1' : '0' \?>">[\s\S]*?<\?php endif; \?>\s*\n  <p class="tutorial-pista"/,
  `<body class="play-v3" data-ui="v3" data-debug="0">
  <div class="aht-debug-float" data-debug-float>
    <button type="button" class="aht-debug-toggle" data-debug-toggle aria-expanded="false" title="Herramientas DEBUG">🧪 DEBUG</button>
    <div class="aht-debug-panel" data-debug-panel hidden>
      <p class="aht-debug-title">DEBUG</p>
      <button type="button" id="btn-debug-nueva">Nueva partida</button>
      <button type="button" id="btn-debug-guardar">Guardar</button>
      <button type="button" data-horas="1">+1h</button>
      <button type="button" data-horas="8">+8h</button>
      <button type="button" data-horas="24">+1 día</button>
      <button type="button" data-horas="72">+3 días</button>
      <button type="button" id="btn-debug-proximo">Ir al próximo</button>
      <span class="msg" data-debug-msg></span>
    </div>
  </div>
  <p class="tutorial-pista"`
);

php = php.replace(
  /body\.play-v3:not\(\[data-lab="1"\]\)[\s\S]*?display: none !important; \}\s*\n/,
  ''
);
php = php.replace(/body\.play-v3\[data-lab="1"\][^\n]+\n/, '');
php = php.replace(
  /\.playtest-float-toggle/g,
  '.aht-debug-toggle'
);
// Add minimal debug float CSS if not present
if (!php.includes('.aht-debug-float')) {
  php = php.replace(
    '    /* Retratos: rostro visible sin rediseñar capas */',
    `    .aht-debug-float { position: fixed; z-index: 90; left: 10px; bottom: 10px; }
    .aht-debug-toggle { border: 1px solid #8a7a66; background: #fff6c8; font: 700 .72rem Nunito,sans-serif; padding: .35rem .55rem; border-radius: 999px; cursor: pointer; opacity: .92; }
    .aht-debug-panel { position: absolute; left: 0; bottom: calc(100% + 6px); width: min(260px, 88vw); padding: .5rem; background: #fffdf6; border: 1px solid #8a7a66; display: flex; flex-wrap: wrap; gap: .3rem; box-shadow: 2px 3px 8px rgba(0,0,0,.12); }
    .aht-debug-panel[hidden] { display: none !important; }
    .aht-debug-title { width: 100%; margin: 0; font-size: .65rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #7a7164; }
    .aht-debug-panel button { border: 1px solid #8a7a66; background: #fff; font: inherit; font-size: .7rem; font-weight: 700; padding: .2rem .4rem; cursor: pointer; }
    /* Retratos: rostro visible sin rediseñar capas */`
  );
}

php = php.replace(
  /<\?php if \(\$ahtLab\): \?>\s*<script src="assets\/js\/lab-audit\.js[^<]+<\/script>\s*<\?php endif; \?>/,
  '<script src="assets/js/lab-audit.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"></script>'
);

fs.writeFileSync(playPhp, php);
console.log('play debug+tutorial patched');
