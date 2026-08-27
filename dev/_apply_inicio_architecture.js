'use strict';
/**
 * Reestructuración arquitectónica Inicio: vistas móvil/desktop separadas,
 * estado compartido (view model), CSS namespaced.
 *
 * Ejecutar: node dev/_apply_inicio_architecture.js
 */
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const root = path.join(__dirname, '..');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function write(rel, content) {
  fs.writeFileSync(path.join(root, rel), content, 'utf8');
}

function gitShow(rev, file) {
  return execSync(`git show ${rev}:${file}`, {
    cwd: root,
    encoding: 'utf8',
    maxBuffer: 20 * 1024 * 1024,
  });
}

function extractBetween(text, start, end) {
  const i = text.indexOf(start);
  if (i < 0) throw new Error(`start marker not found: ${start}`);
  const j = end ? text.indexOf(end, i + start.length) : -1;
  if (end && j < 0) throw new Error(`end marker not found: ${end}`);
  return end ? text.slice(i, j) : text.slice(i);
}

function extractBlock(css, startMarker, endMarker) {
  const i = css.indexOf(startMarker);
  if (i < 0) return '';
  const j = endMarker ? css.indexOf(endMarker, i + startMarker.length) : -1;
  return j > i ? css.slice(i, j) : css.slice(i);
}

function unwrapMedia(css, type) {
  if (type === 'mobile') {
    const m = css.match(/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{([\s\S]*)\}\s*$/);
    return m ? m[1].trim() : css;
  }
  if (type === 'desktop') {
    const m = css.match(/@media\s*\(\s*min-width:\s*769px\s*\)\s*\{([\s\S]*)\}\s*$/);
    return m ? m[1].trim() : css;
  }
  return css;
}

function namespaceInicioCss(css, scope) {
  return css
    .replace(/\.play-v3:has\(\.game-shell\)/g, scope)
    .replace(/body\.play-v3(?![\w-])/g, `body.play-v3 ${scope}`)
    .replace(/\.game-left/g, `${scope} .inicio-chrome-left`)
    .replace(/\.game-right/g, `${scope} .inicio-chrome-right`)
    .replace(/\.game-main/g, `${scope} .inicio-layout`)
    .replace(/\.game-map-wrap/g, '.inicio-map-host')
    .replace(/\.game-top/g, `${scope} .game-top`);
}

function extractGrupo(html, marker) {
  const escaped = marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const patterns = [
    new RegExp(`<section\\s+class="shell-grupo[^"]*${escaped}[^"]*"[^>]*>[\\s\\S]*?</section>`, 'i'),
    new RegExp(`<section\\s+[^>]*${escaped}[^>]*>[\\s\\S]*?</section>`, 'i'),
  ];
  for (const re of patterns) {
    const m = html.match(re);
    if (m) return m[0];
  }
  throw new Error(`shell-grupo not found for marker: ${marker}`);
}

function fixInicioIds(section) {
  return section
    .replace(/\s+id="mob-misiones"/gi, ' data-inicio-misiones')
    .replace(/\s+id="mob-parejas"/gi, ' data-inicio-parejas');
}

function dupHeader(header, suffix) {
  return header
    .replace(/id="corazon-clip"/g, `id="corazon-clip-${suffix}"`)
    .replace(/url\(#corazon-clip\)/g, `url(#corazon-clip-${suffix})`)
    .replace(/id="corazon-agua-grad"/g, `id="corazon-agua-grad-${suffix}"`)
    .replace(/url\(#corazon-agua-grad\)/g, `url(#corazon-agua-grad-${suffix})`);
}

function mobilePlanTile(shellHtml) {
  const grupo = extractGrupo(shellHtml, 'shell-grupo-planes');
  const btn = grupo.match(/<button[^>]*class="[^"]*obj-nuevo-plan[^"]*"[\s\S]*?<\/button>/i);
  if (!btn) throw new Error('obj-nuevo-plan button not found in shell-grupo-planes');
  return `<section class="shell-grupo shell-grupo-planes">\n${btn[0]}\n</section>`;
}

function patchPlayPhp(php) {
  if (php.includes('class="inicio-stage"')) {
    console.log('play.php already has inicio-stage — skipping shell rewrite');
    return php.replace(
      /<link rel="stylesheet" href="assets\/css\/design-system\/screens\/inicio\.css[^"]*"\/>/,
      '<link rel="stylesheet" href="assets/css/design-system/screens/inicio-views.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n' +
        '  <link rel="stylesheet" href="assets/css/design-system/screens/inicio-mobile.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>'
    );
  }

  const shellStart = php.indexOf('<div class="game-shell">');
  const shellEnd = php.indexOf('\n  <script src="assets/js/lab-audit.js');
  if (shellStart < 0 || shellEnd < 0) throw new Error('game-shell bounds not found in play.php');

  const head = php.slice(0, shellStart);
  const tail = php.slice(shellEnd);
  const origShell = php.slice(shellStart, shellEnd);

  const headPatched = head.replace(
    /<link rel="stylesheet" href="assets\/css\/design-system\/screens\/inicio\.css[^"]*"\/>/,
    '<link rel="stylesheet" href="assets/css/design-system/screens/inicio-views.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n' +
      '  <link rel="stylesheet" href="assets/css/design-system/screens/inicio-mobile.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>'
  );

  const origHeader = extractBetween(origShell, '<header class="game-top">', '</header>') + '</header>';
  const headerMob = dupHeader(origHeader, 'mob');
  const headerDesk = dupHeader(origHeader, 'desk');

  const mapHost = extractBetween(origShell, '<div class="game-map-wrap">', '<aside class="game-right')
    .replace(/^<div class="game-map-wrap">/, '<div class="inicio-map-host game-map-wrap">');

  const cotilleo = fixInicioIds(extractGrupo(origShell, 'cotilleo-par'));
  const encursos = fixInicioIds(extractGrupo(origShell, 'data-encursos-block'));
  const proxplanes = fixInicioIds(extractGrupo(origShell, 'data-proxplanes-block'));
  const misionesMob = fixInicioIds(extractGrupo(origShell, 'misiones-par'));
  const parejas = fixInicioIds(extractGrupo(origShell, 'parejas'));
  const buzon = fixInicioIds(extractGrupo(origShell, 'shell-grupo-buzon'));
  const vecinos = fixInicioIds(extractGrupo(origShell, 'shell-grupo-resumen'));
  const planTile = mobilePlanTile(origShell);
  const misionesDesk = misionesMob;

  const mobileFeed = [cotilleo, encursos, proxplanes, misionesMob, parejas].join('\n            ');

  const newShell = `  <div class="game-shell">
    <div class="inicio-stage">
      <section class="inicio-mobile" data-inicio-view="mobile" aria-label="Inicio móvil">
        ${headerMob}
        <div class="inicio-layout inicio-mobile-layout">
          <div class="inicio-chrome-left inicio-mobile-tiles">
            ${buzon}
            ${vecinos}
            ${planTile}
          </div>
        </div>
      </section>

      <section class="inicio-desktop" data-inicio-view="desktop" hidden aria-label="Inicio escritorio">
        ${headerDesk}
        <div class="inicio-layout inicio-desktop-layout">
          <aside class="inicio-chrome-left inicio-desktop-left">
            ${buzon}
            ${vecinos}
            ${misionesDesk}
            ${planTile}
          </aside>
          <aside class="inicio-chrome-right inicio-desktop-right">
            ${cotilleo}
            ${encursos}
            ${proxplanes}
            ${parejas}
          </aside>
        </div>
      </section>

      ${mapHost}

      <section class="inicio-mobile inicio-mobile-feed" data-inicio-view="mobile" aria-label="Inicio móvil feed">
        <div class="inicio-chrome-right inicio-mobile-feed-inner">
            ${mobileFeed}
        </div>
      </section>
    </div>
  </div>`;

  return headPatched + newShell + tail;
}

function buildInicioViewsCss() {
  return `/* INICIO-VIEWS — solo visibilidad por breakpoint; sin layout compartido */
@media (max-width: 768px) {
  .inicio-desktop {
    display: none !important;
  }
  .inicio-mobile {
    display: block;
  }
}

@media (min-width: 769px) {
  .inicio-mobile {
    display: none !important;
  }
  .inicio-desktop {
    display: block;
  }
}

.inicio-mobile[hidden],
.inicio-desktop[hidden] {
  display: none !important;
}

.inicio-mobile[inert],
.inicio-desktop[inert] {
  pointer-events: none !important;
  user-select: none;
}

.inicio-stage {
  width: 100%;
  box-sizing: border-box;
}
`;
}

function buildMobileCss(inicioCss, overrideCss, v13, v14) {
  let mobCore = namespaceInicioCss(unwrapMedia(inicioCss, 'mobile'), '.inicio-mobile');

  const mobOverride = extractBlock(
    overrideCss,
    '/* === Tres bloques Inicio movil',
    '/* INICIO-ENCURSO-NAV'
  );
  let mobExtra = mobOverride ? namespaceInicioCss(mobOverride, '.inicio-mobile') : '';

  const unwrapV13 = v13 ? unwrapMedia(v13, 'mobile') : '';
  const unwrapV14 = v14 ? unwrapMedia(v14, 'mobile') : '';
  const v13Scoped = unwrapV13 ? namespaceInicioCss(unwrapV13, '.inicio-mobile') : '';
  const v14Scoped = unwrapV14 ? namespaceInicioCss(unwrapV14, '.inicio-mobile') : '';

  return `/* INICIO-MOBILE — presentación móvil namespaced (.inicio-mobile) */

.inicio-mobile .inicio-mobile-layout {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 0 13px 12px;
  box-sizing: border-box;
}

.inicio-mobile .inicio-mobile-tiles {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  width: 100%;
}

.inicio-mobile.inicio-mobile-feed .inicio-mobile-feed-inner {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 0 13px 12px;
  width: 100%;
  box-sizing: border-box;
}

.inicio-stage > .inicio-map-host {
  width: 100%;
  box-sizing: border-box;
}

@media (max-width: 768px) {
  .inicio-stage {
    display: flex;
    flex-direction: column;
  }
  .inicio-mobile .inicio-mobile-tiles { order: 1; }
  .inicio-stage > .inicio-map-host { order: 2; padding: 0 13px; }
  .inicio-mobile.inicio-mobile-feed { order: 3; }
}

${mobCore}

${mobExtra}

/* === INICIO-PROXPLANES-REF-v13 (móvil) === */
${v13Scoped}

/* === INICIO-ENCURSO-REF-v14 (móvil) === */
${v14Scoped}
`;
}

function buildDesktopCss(desktopCss) {
  let desk = namespaceInicioCss(unwrapMedia(desktopCss, 'desktop'), '.inicio-desktop');
  desk = desk.replace(/display:\s*contents\s*;?/g, 'display: flex; flex-direction: column; gap: 12px;');

  return `/* INICIO-DESKTOP — presentación desktop namespaced (.inicio-desktop) */

.inicio-desktop .inicio-desktop-layout {
  display: grid;
  grid-template-columns: minmax(328px, 400px) minmax(0, 1fr) minmax(282px, 362px);
  gap: 14px;
  align-items: start;
  padding: 0 14px 14px;
  box-sizing: border-box;
}

.inicio-desktop .inicio-desktop-left {
  grid-column: 1;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.inicio-desktop .inicio-desktop-right {
  grid-column: 3;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

@media (min-width: 769px) {
  .inicio-stage {
    display: grid;
    grid-template-columns: minmax(328px, 400px) minmax(0, 1fr) minmax(282px, 362px);
    grid-template-rows: auto auto;
    gap: 14px;
    padding: 0 14px 14px;
    align-items: start;
    box-sizing: border-box;
  }

  .inicio-desktop {
    grid-column: 1 / -1;
    grid-row: 1 / span 2;
    display: grid;
    grid-template-columns: subgrid;
    grid-template-rows: subgrid;
    gap: 14px;
  }

  .inicio-desktop > .game-top {
    grid-column: 1 / -1;
    grid-row: 1;
  }

  .inicio-desktop-left {
    grid-column: 1;
    grid-row: 2;
  }

  .inicio-desktop-right {
    grid-column: 3;
    grid-row: 2;
  }

  .inicio-stage > .inicio-map-host {
    grid-column: 2;
    grid-row: 2;
  }
}

@supports not (grid-template-columns: subgrid) {
  @media (min-width: 769px) {
    .inicio-desktop {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .inicio-desktop-layout {
      display: grid;
      grid-template-columns: minmax(328px, 400px) minmax(0, 1fr) minmax(282px, 362px);
      gap: 14px;
    }
    .inicio-stage > .inicio-map-host {
      margin-top: -100%;
      visibility: hidden;
    }
  }
}

${desk}
`;
}

function replaceBetween(js, start, end, replacement) {
  const i = js.indexOf(start);
  const j = js.indexOf(end, i + start.length);
  if (i < 0 || j < 0) throw new Error(`replaceBetween failed: ${start.slice(0, 40)} … ${end.slice(0, 40)}`);
  return js.slice(0, i) + replacement + js.slice(j);
}

function patchPlayV3Js(js) {
  const useCrlf = js.includes('\r\n');
  js = js.replace(/\r\n/g, '\n');

  if (!js.includes('function inicioAll(')) {
    const anchor = '  const $ = (sel, root) => (root || document).querySelector(sel);';
    const helpers =
      anchor +
      '\n  function inicioViewRoot(view) {\n' +
      '    if (view === \'mobile\') return document.querySelector(\'.inicio-mobile\');\n' +
      '    if (view === \'desktop\') return document.querySelector(\'.inicio-desktop\');\n' +
      '    return null;\n' +
      '  }\n' +
      '  function inicioAll(sel) {\n' +
      '    return Array.prototype.slice.call(document.querySelectorAll(\'.inicio-mobile \' + sel + \', .inicio-desktop \' + sel));\n' +
      '  }\n' +
      '  function inicioBlocks(sel) {\n' +
      '    return Array.prototype.slice.call(document.querySelectorAll(\'.inicio-mobile \' + sel + \', .inicio-desktop \' + sel));\n' +
      '  }\n' +
      '  function setAllText(sel, text) {\n' +
      '    inicioAll(sel).forEach(function (el) { el.textContent = text; });\n' +
      '  }\n' +
      '  function setAllHtml(sel, html) {\n' +
      '    inicioAll(sel).forEach(function (el) { el.innerHTML = html; });\n' +
      '  }';
    if (!js.includes(anchor)) throw new Error('$ anchor not found in play-v3.js');
    js = js.replace(anchor, helpers);
  }

  if (js.includes('if (esInicioLayoutMovil()) return htmlEncursoCardMovilV14')) {
    js = js.replace(
      '  function htmlEncursoCardMovil(enc, estado) {\n    if (esInicioLayoutMovil()) return htmlEncursoCardMovilV14(enc, estado);\n    return htmlEncursoCardDesktop(enc, estado);\n  }',
      '  function htmlEncursoCardMovil(enc, estado) {\n    return htmlEncursoCardMovilV14(enc, estado);\n  }\n  function htmlEncursoCardDesktopView(enc, estado) {\n    return htmlEncursoCardDesktop(enc, estado);\n  }'
    );
  }

  if (!js.includes('function renderProximosPlanesBlock(')) {
    js = replaceBetween(
      js,
      '  function renderProximosPlanesMovil(estado) {',
      '  var encMovIndice = 0;',
      `  function renderProximosPlanesBlock(block, estado, cardFn) {
    if (!block) return;
    const track = block.querySelector('[data-proxplanes-track]');
    if (!track) return;
    const cntEl = block.querySelector('[data-proxplanes-count]');
    const listaFull = proximosPlanesFuturos(cacheInsp, estado);
    const total = listaFull.length;
    if (cntEl) {
      if (total > 0) { cntEl.textContent = String(total); cntEl.hidden = false; cntEl.removeAttribute('aria-hidden'); }
      else { cntEl.textContent = ''; cntEl.hidden = true; cntEl.setAttribute('aria-hidden', 'true'); }
    }
    const lista = listaFull.slice(0, 6);
    if (!lista.length) { block.classList.remove('is-on'); track.innerHTML = ''; return; }
    block.classList.add('is-on');
    track.innerHTML = lista.map(function (enc) { return cardFn(enc, estado); }).join('');
  }
  function renderProximosPlanesMovil(estado) {
    inicioBlocks('[data-proxplanes-block]').forEach(function (block) {
      const view = block.closest('.inicio-mobile') ? 'mobile' : 'desktop';
      const cardFn = view === 'mobile' ? htmlProximoPlanCardMovil : htmlProximoPlanCardMovil;
      renderProximosPlanesBlock(block, estado, cardFn);
    });
  }
  `
    );
  }

  if (!js.includes('function renderEncursosBlock(')) {
    js = replaceBetween(
      js,
      '    function renderEncursosMovil(estado) {',
      '  function renderShellPanels(estado, buzon, diario) {',
      `    function renderEncursosBlock(block, estado, cardFn) {
    if (!block) return;
    const track = block.querySelector('[data-encursos-track]');
    if (!track) return;
    const lista = encuentrosEnCursoAhora(cacheInsp, estado);
    const cntEl = block.querySelector('[data-encursos-count]');
    if (cntEl) {
      if (lista.length > 0) { cntEl.textContent = String(lista.length); cntEl.hidden = false; cntEl.removeAttribute('aria-hidden'); }
      else { cntEl.textContent = ''; cntEl.hidden = true; cntEl.setAttribute('aria-hidden', 'true'); }
    }
    if (!lista.length) {
      block.classList.remove('is-on');
      track.innerHTML = '';
      renderEncursosMovilNavFor(block);
      return;
    }
    const abiertos = {};
    track.querySelectorAll('[data-enc-mov-panel]:not([hidden])').forEach(function (p) {
      const card = p.closest('[data-enc-mov-card]');
      if (card) abiertos[card.getAttribute('data-enc-id') || ''] = true;
    });
    block.classList.add('is-on');
    track.innerHTML = lista.map(function (enc) { return cardFn(enc, estado); }).join('');
    requestAnimationFrame(function () {
      const cards = track.querySelectorAll('[data-enc-mov-card]');
      Object.keys(abiertos).forEach(function (id) {
        Array.prototype.forEach.call(cards, function (card) {
          if ((card.getAttribute('data-enc-id') || '') !== id) return;
          const panel = card.querySelector('[data-enc-mov-panel]');
          const cta = card.querySelector('[data-enc-mov-toggle]');
          if (panel) panel.hidden = false;
          if (cta) { cta.setAttribute('aria-expanded', 'true'); cta.classList.add('is-open'); }
        });
      });
      renderEncursosMovilNavFor(block);
    });
  }
    function renderEncursosMovil(estado) {
    inicioBlocks('[data-encursos-block]').forEach(function (block) {
      const view = block.closest('.inicio-mobile') ? 'mobile' : 'desktop';
      const cardFn = view === 'mobile' ? htmlEncursoCardMovil : htmlEncursoCardDesktopView;
      renderEncursosBlock(block, estado, cardFn);
    });
  }

  `
    );
  }

  if (js.includes('function encMovIrA(idx) {') && js.includes('document.querySelector(\'[data-encursos-block]\')')) {
    js = replaceBetween(
      js,
      '  function encMovIrA(idx) {',
      '    function renderEncursosMovil(estado) {',
      `  function encMovIrA(block, idx) {
    const track = block && block.querySelector('[data-encursos-track]');
    if (!block || !track) return;
    const n = track.querySelectorAll('[data-enc-mov-card]').length;
    if (n < 2) return;
    const paso = encMovPaso(track);
    if (paso <= 0) return;
    const i = Math.max(0, Math.min(n - 1, idx));
    block._encMovIndice = i;
    track.scrollTo({ left: i * paso, behavior: 'smooth' });
    renderEncursosMovilNavFor(block);
  }
  function renderEncursosMovilNavFor(block) {
    const track = block && block.querySelector('[data-encursos-track]');
    const shell = block && block.querySelector('[data-encursos-shell]');
    const prev = block && block.querySelector('[data-enc-mov-prev]');
    const next = block && block.querySelector('[data-enc-mov-next]');
    if (!block || !track || !shell || !prev || !next) return;
    const n = track.querySelectorAll('[data-enc-mov-card]').length;
    if (!block.classList.contains('is-on') || n < 1) {
      shell.hidden = true;
      shell.setAttribute('aria-hidden', 'true');
      prev.hidden = true;
      next.hidden = true;
      return;
    }
    const paso = encMovPaso(track);
    const idx = paso > 0 ? Math.min(n - 1, Math.max(0, Math.round(track.scrollLeft / paso))) : 0;
    block._encMovIndice = idx;
    shell.hidden = false;
    shell.removeAttribute('aria-hidden');
    prev.hidden = n < 2 || idx <= 0;
    next.hidden = n < 2 || idx >= n - 1;
  }
  function renderEncursosMovilNav() {
    inicioBlocks('[data-encursos-block]').forEach(renderEncursosMovilNavFor);
  }
  function renderEncursosMovilIndicador() {
    renderEncursosMovilNav();
  }
  `
    );
  }

  if (!js.includes('function buildInicioViewModel(')) {
    const vmCode =
      `  function buildInicioViewModel(estado, buzon, diario) {
    const partida = cacheInsp || {};
    const met = metricasSociales(partida);
    const parejas = parejasParaUI(partida);
    const hoy = (diario && diario.cotilleo && diario.cotilleo.hoy) || diario.entradas || [];
    const hoyLista = Array.isArray(hoy) ? hoy : [];
    const ultRaw = (hoyLista[0] && (hoyLista[0].texto || hoyLista[0].cuerpo || hoyLista[0].titulo)) || '';
    const pend = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    return {
      statsHtml: htmlResumenCelestine(met),
      vecinosPoblacion: String(met.vecinos) + ' de ' + String(met.cap),
      cotilleoTeaser: ultRaw ? resumenCotilleoUi(ultRaw, 120) : 'Hoy están sospechosamente tranquilos…',
      buzonPreview: !pend.length ? 'Sin mensajes pendientes.' : ((pend[0].remitente_nombre || pend[0].de || 'Mensaje') + ': ' + (pend[0].preview || pend[0].asunto || pend[0].texto || '').slice(0, 80)),
      parejas: parejas,
    };
  }

  function renderParejasStripEl(strip, parejas) {
    if (!strip) return;
    strip.innerHTML = '';
  (parejas || []).forEach(function (rel) {
      const ids = idsPareja(rel);
      if (!ids || ids.length < 2) return;
      const crisis = esCrisisPareja(rel);
      const row = document.createElement('div');
      row.className = 'obj-pareja-piece' + (crisis ? ' is-crisis' : '');
      const tok = function (id) {
        return htmlCaraToken(id, { imgClass: 'obj-pareja-cara' });
      };
      row.innerHTML = '<span class="obj-pareja-fotos">' + tok(ids[0]) +
        '<span class="obj-pareja-enlace" aria-hidden="true"></span>' + tok(ids[1]) + '</span>' +
        '<span class="obj-pareja-nombres">' + esc(nombreDe(ids[0])) + ' \\u00b7 ' + esc(nombreDe(ids[1])) + '</span>' +
        (crisis ? '<span class="pareja-crisis-sello">EN CRISIS</span>' : '');
      strip.appendChild(row);
    });
    if (!parejas || !parejas.length) {
      strip.innerHTML = '<p class="muted">A\\u00fan no hay parejas registradas.</p>';
    }
  }

  function renderParejasStripIn(scopeSel, parejas) {
    const root = document.querySelector(scopeSel);
    if (!root) return;
    const strip = root.querySelector('[data-parejas-strip]');
    renderParejasStripEl(strip, parejas);
  }

  function renderInicioMobile(vm, estado) {
    setAllHtml('[data-resumen-stats]', vm.statsHtml);
    setAllText('[data-vecinos-poblacion]', vm.vecinosPoblacion);
    setAllText('[data-cotilleo-teaser]', vm.cotilleoTeaser);
    renderVecinosPreviewIn('.inicio-mobile');
    renderParejasStripIn('.inicio-mobile', vm.parejas);
    renderProximosPlanesMovil(estado);
    renderEncursosMovil(estado);
  }

  function renderInicioDesktop(vm, estado) {
    setAllHtml('[data-resumen-stats]', vm.statsHtml);
    setAllText('[data-vecinos-poblacion]', vm.vecinosPoblacion);
    setAllText('[data-cotilleo-teaser]', vm.cotilleoTeaser);
    renderVecinosPreviewIn('.inicio-desktop');
    renderParejasStripIn('.inicio-desktop', vm.parejas);
    renderProximosPlanesMovil(estado);
    renderEncursosMovil(estado);
  }

  function renderInicio(estado, buzon, diario) {
    const vm = buildInicioViewModel(estado, buzon, diario);
    renderInicioMobile(vm, estado);
    renderInicioDesktop(vm, estado);
    actualizarCotiBadgesUI();
  }

  function syncInicioViewVisibility() {
    const mobileSections = document.querySelectorAll('.inicio-mobile');
    const desktop = document.querySelector('.inicio-desktop');
    const isMob = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
    mobileSections.forEach(function (mobile) {
      mobile.hidden = !isMob;
      mobile.toggleAttribute('inert', !isMob);
    });
    if (desktop) {
      desktop.hidden = isMob;
      desktop.toggleAttribute('inert', isMob);
    }
  }

`;
    js = js.replace('  function renderShellPanels(estado, buzon, diario) {', vmCode + '  function renderShellPanels(estado, buzon, diario) {');
  }

  if (js.includes('const partida = cacheInsp') && js.includes('function renderShellPanels')) {
    js = replaceBetween(
      js,
      '  function renderShellPanels(estado, buzon, diario) {',
      '  var cacheMapaZonas = null;',
      '  function renderShellPanels(estado, buzon, diario) {\n    renderInicio(estado, buzon, diario);\n  }\n\n'
    );
  }

  if (js.includes('function renderVecinosPreview() {\n    var box = $(\'[data-vecinos-preview]\');')) {
    js = js.replace(
      '  function renderVecinosPreview() {\n    var box = $(\'[data-vecinos-preview]\');\n    if (!box) return;',
      '  function renderVecinosPreviewBox(box) {\n    if (!box) return;'
    );
    js = js.replace(
      '  function renderVecinosPreviewBox(box) {\n    if (!box) return;',
      '  function renderVecinosPreview() {\n    inicioAll(\'[data-vecinos-preview]\').forEach(renderVecinosPreviewBox);\n  }\n  function renderVecinosPreviewBox(box) {\n    if (!box) return;'
    );
    js = js.replace(
      '  function renderVecinosPreviewIn(scopeSel) {\n    var root = document.querySelector(scopeSel);\n    if (!root) return;\n    var box = root.querySelector(\'[data-vecinos-preview]\');\n    if (!box) return;\n    renderVecinosPreviewBox(box);\n  }',
      '  function renderVecinosPreviewIn(scopeSel) {\n    var root = document.querySelector(scopeSel);\n    if (!root) return;\n    var box = root.querySelector(\'[data-vecinos-preview]\');\n    renderVecinosPreviewBox(box);\n  }'
    );
    if (!js.includes('function renderVecinosPreviewIn(')) {
      js = js.replace(
        '  function renderVecinosPreview() {',
        '  function renderVecinosPreviewIn(scopeSel) {\n    var root = document.querySelector(scopeSel);\n    if (!root) return;\n    var box = root.querySelector(\'[data-vecinos-preview]\');\n    renderVecinosPreviewBox(box);\n  }\n  function renderVecinosPreview() {'
      );
    }
  }

  if (js.includes('function renderMisionesStrip(items) {\n    var strip = $(\'[data-misiones-strip]\');')) {
    js = js.replace(
      '  function renderMisionesStrip(items) {\n    var strip = $(\'[data-misiones-strip]\');\n    if (!strip) return;',
      '  function renderMisionesStripEl(strip, items) {\n    if (!strip) return;'
    );
    js = js.replace(
      '  function renderMisionesStripEl(strip, items) {\n    if (!strip) return;',
      '  function renderMisionesStrip(items) {\n    inicioAll(\'[data-misiones-strip]\').forEach(function (strip) { renderMisionesStripEl(strip, items); });\n  }\n  function renderMisionesStripEl(strip, items) {\n    if (!strip) return;'
    );
  }

  if (js.includes('const badgeObj = $(\'[data-buzon-badge]\');')) {
    js = js.replace(
      `    const badgeObj = $('[data-buzon-badge]');
    if (badgeObj) {
      badgeObj.textContent = String(nPend);
      badgeObj.hidden = nPend <= 0;
    }`,
      `    inicioAll('[data-buzon-badge]').forEach(function (badgeObj) {
      badgeObj.textContent = String(nPend);
      badgeObj.hidden = nPend <= 0;
    });`
    );
  }

  if (js.includes('const homeBadge = $(\'[data-cotilleo-badge]\');')) {
    js = js.replace(
      `    const homeBadge = $('[data-cotilleo-badge]');
    if (homeBadge) {
      if (sinVer > 0) {
        homeBadge.textContent = cotiBadgeNuevosTxt(sinVer);
        homeBadge.hidden = false;
        if (subio) pulsoCotilleoBadge(homeBadge);
      } else {
        homeBadge.textContent = '';
        homeBadge.hidden = true;
      }
    }`,
      `    inicioAll('[data-cotilleo-badge]').forEach(function (homeBadge) {
      if (sinVer > 0) {
        homeBadge.textContent = cotiBadgeNuevosTxt(sinVer);
        homeBadge.hidden = false;
        if (subio) pulsoCotilleoBadge(homeBadge);
      } else {
        homeBadge.textContent = '';
        homeBadge.hidden = true;
      }
    });`
    );
  }

  if (js.includes('const cotiCard = $(\'.obj-cotilleo-par\');')) {
    js = js.replace(
      `    const cotiCard = $('.obj-cotilleo-par');
    if (cotiCard) cotiCard.classList.toggle('is-aviso-importante', sinVer > 0);`,
      `    document.querySelectorAll('.obj-cotilleo-par').forEach(function (cotiCard) {
      cotiCard.classList.toggle('is-aviso-importante', sinVer > 0);
    });`
    );
  }

  const toggleNeedle = "const encMovCta = ev.target.closest('[data-enc-mov-toggle]');";
  if (js.includes(toggleNeedle) && !js.includes('data-enc-mov-prev')) {
    js = js.replace(
      toggleNeedle,
      `const encMovPrev = ev.target.closest('[data-enc-mov-prev]');
    if (encMovPrev) {
      ev.preventDefault();
      ev.stopPropagation();
      const encBlock = encMovPrev.closest('[data-encursos-block]');
      const idx = (encBlock && encBlock._encMovIndice) || 0;
      encMovIrA(encBlock, idx - 1);
      return;
    }
    const encMovNext = ev.target.closest('[data-enc-mov-next]');
    if (encMovNext) {
      ev.preventDefault();
      ev.stopPropagation();
      const encBlock = encMovNext.closest('[data-encursos-block]');
      const idx = (encBlock && encBlock._encMovIndice) || 0;
      encMovIrA(encBlock, idx + 1);
      return;
    }
    ${toggleNeedle}`
    );
  }

  const scrollNeedle = "encTrack.addEventListener('scroll', renderEncursosMovilNav, { passive: true });";
  if (js.includes(scrollNeedle)) {
    js = js.replace(
      scrollNeedle,
      "encTrack.addEventListener('scroll', function () { const b = encTrack.closest('[data-encursos-block]'); if (b) renderEncursosMovilNavFor(b); }, { passive: true });"
    );
  }

  if (!js.includes('addEventListener(\'resize\', syncInicioViewVisibility)')) {
    js = js.replace(
      '  function syncInicioViewVisibility() {',
      '  function syncInicioViewVisibility() {'
    );
    js = js.replace(
      '  function renderInicio(estado, buzon, diario) {',
      '  function renderInicio(estado, buzon, diario) {'
    );
    if (js.includes('function syncInicioViewVisibility')) {
      js = js.replace(
        '  function syncInicioViewVisibility() {',
        '  function bootSyncInicioViewVisibility() {\n    syncInicioViewVisibility();\n    window.addEventListener(\'resize\', syncInicioViewVisibility);\n  }\n  if (document.readyState === \'loading\') document.addEventListener(\'DOMContentLoaded\', bootSyncInicioViewVisibility);\n  else bootSyncInicioViewVisibility();\n\n  function syncInicioViewVisibility() {'
      );
    }
  }

  const bindEnc = js.indexOf('(function bindEncursosMovil() {');
  if (bindEnc >= 0) {
    js = replaceBetween(
      js,
      '(function bindEncursosMovil() {',
      '})();',
      `(function bindEncursosMovil() {
    document.querySelectorAll('[data-encursos-track]').forEach(function (encTrack) {
      if (encTrack._ahtEncMovScroll) return;
      encTrack._ahtEncMovScroll = true;
      encTrack.addEventListener('scroll', function () {
        const b = encTrack.closest('[data-encursos-block]');
        if (b) renderEncursosMovilNavFor(b);
      }, { passive: true });
    });
  `
    );
  }

  if (useCrlf) js = js.replace(/\n/g, '\r\n');
  return js;
}

/** Parches finales que dependen de EOL normalizado (CRLF en play-v3.js). */
function finalizePlayV3Patches(js) {
  const useCrlf = js.includes('\r\n');
  let work = js.replace(/\r\n/g, '\n');

  function rep(old, neu) {
    if (work.includes(old)) work = work.replace(old, neu);
  }

  rep(
    '  function htmlEncursoCardMovil(enc, estado) {\n    if (esInicioLayoutMovil()) return htmlEncursoCardMovilV14(enc, estado);\n    return htmlEncursoCardDesktop(enc, estado);\n  }',
    '  function htmlEncursoCardMovil(enc, estado) {\n    return htmlEncursoCardMovilV14(enc, estado);\n  }\n  function htmlEncursoCardDesktopView(enc, estado) {\n    return htmlEncursoCardDesktop(enc, estado);\n  }'
  );

  rep(
    `    const badgeObj = $('[data-buzon-badge]');
    if (badgeObj) {
      badgeObj.textContent = String(nPend);
      badgeObj.hidden = nPend <= 0;
    }`,
    `    inicioAll('[data-buzon-badge]').forEach(function (badgeObj) {
      badgeObj.textContent = String(nPend);
      badgeObj.hidden = nPend <= 0;
    });`
  );

  rep(
    `    const homeBadge = $('[data-cotilleo-badge]');
    if (homeBadge) {
      if (sinVer > 0) {
        homeBadge.textContent = cotiBadgeNuevosTxt(sinVer);
        homeBadge.hidden = false;
        if (subio) pulsoCotilleoBadge(homeBadge);
      } else {
        homeBadge.textContent = '';
        homeBadge.hidden = true;
      }
    }`,
    `    inicioAll('[data-cotilleo-badge]').forEach(function (homeBadge) {
      if (sinVer > 0) {
        homeBadge.textContent = cotiBadgeNuevosTxt(sinVer);
        homeBadge.hidden = false;
        if (subio) pulsoCotilleoBadge(homeBadge);
      } else {
        homeBadge.textContent = '';
        homeBadge.hidden = true;
      }
    });`
  );

  rep(
    `    const cotiCard = $('.obj-cotilleo-par');
    if (cotiCard) cotiCard.classList.toggle('is-aviso-importante', sinVer > 0);`,
    `    document.querySelectorAll('.obj-cotilleo-par').forEach(function (cotiCard) {
      cotiCard.classList.toggle('is-aviso-importante', sinVer > 0);
    });`
  );

  if (work.includes("function renderVecinosPreview() {\n    var box = $('[data-vecinos-preview]');")) {
    work = work.replace(
      "  function renderVecinosPreview() {\n    var box = $('[data-vecinos-preview]');\n    if (!box) return;",
      "  function renderVecinosPreviewBox(box) {\n    if (!box) return;"
    );
    if (!work.includes('function renderVecinosPreviewIn(')) {
      work = work.replace(
        '  function renderVecinosPreviewBox(box) {',
        "  function renderVecinosPreviewIn(scopeSel) {\n    var root = document.querySelector(scopeSel);\n    if (!root) return;\n    var box = root.querySelector('[data-vecinos-preview]');\n    renderVecinosPreviewBox(box);\n  }\n  function renderVecinosPreview() {\n    inicioAll('[data-vecinos-preview]').forEach(renderVecinosPreviewBox);\n  }\n  function renderVecinosPreviewBox(box) {"
      );
    }
  }

  if (work.includes("function renderMisionesStrip(items) {\n    var strip = $('[data-misiones-strip]');")) {
    work = work.replace(
      "  function renderMisionesStrip(items) {\n    var strip = $('[data-misiones-strip]');\n    if (!strip) return;",
      "  function renderMisionesStrip(items) {\n    inicioAll('[data-misiones-strip]').forEach(function (strip) { renderMisionesStripEl(strip, items); });\n  }\n  function renderMisionesStripEl(strip, items) {\n    if (!strip) return;"
    );
  }

  work = work.replace('    function buildInicioViewModel', '  function buildInicioViewModel');

  if (useCrlf) work = work.replace(/\n/g, '\r\n');
  return work;
}

function buildOverrideMinimal() {
  return `/* INICIO-OVERRIDE — legacy mínimo post-arquitectura dual-view */
/* Presentación migrada a inicio-mobile.css / inicio-desktop.css */

.inicio-mobile .enc-mov-nav-btn[hidden],
.inicio-desktop .enc-mov-nav-btn[hidden] {
  display: none !important;
  width: 0 !important;
  min-width: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  border: none !important;
  visibility: hidden !important;
  pointer-events: none !important;
  opacity: 0 !important;
}
`;
}

function writeArchitectureTest() {
  const test = `'use strict';
/* INICIO-ARCHITECTURE — 7 tests de independencia móvil/desktop */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const mobCss = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-mobile.css'), 'utf8');
const deskCss = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');
const viewsCss = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-views.css'), 'utf8');

let failures = 0;
function ok(cond, msg) {
  console.log((cond ? 'OK' : 'FAIL') + ': ' + msg);
  if (!cond) failures++;
}

// 1. CSS móvil no selecciona .inicio-desktop
ok(!/\\.inicio-desktop[\\s,{.:]/.test(mobCss), 'mobile CSS no referencia .inicio-desktop');

// 2. CSS desktop no selecciona .inicio-mobile
ok(!/\\.inicio-mobile[\\s,{.:]/.test(deskCss), 'desktop CSS no referencia .inicio-mobile');

// 3. Contador mensajitos compartido (inicioAll)
ok(/function inicioAll/.test(js) && /inicioAll\\('\\[data-buzon-badge\\]'\\)/.test(js),
  'JS: badges buzón vía inicioAll (misma fuente)');

// 4. Estado vecinos compartido
ok(/setAllText\\('\\[data-vecinos-poblacion\\]'/.test(js) && /buildInicioViewModel/.test(js),
  'JS: población vecinos vía view model + setAllText');

// 5. Acciones compartidas data-open en ambas vistas
const openBuzonMob = (php.match(/class="inicio-mobile[\\s\\S]*?data-open="buzon"/g) || []).length;
const openBuzonDesk = (php.match(/class="inicio-desktop[\\s\\S]*?data-open="buzon"/g) || []).length;
ok(openBuzonMob >= 1 && openBuzonDesk >= 1, 'PHP: data-open buzón en móvil y desktop');

// 6. Sin IDs duplicados problemáticos
ok(!/id="mob-misiones"/.test(php) && !/id="mob-parejas"/.test(php),
  'PHP: sin id mob-misiones / mob-parejas');
ok(/data-inicio-misiones/.test(php) && /data-inicio-parejas/.test(php),
  'PHP: data-inicio-misiones y data-inicio-parejas presentes');

// 7. Sin reparenting DOM por viewport
ok(!/appendChild\\([^)]*game-(left|right)/.test(js) &&
  !/esInicioLayoutMovil\\(\\)[\\s\\S]{0,80}appendChild/.test(js),
  'JS: sin reparenting game-left/right por viewport');

// Estructura mínima
ok(/class="inicio-stage"/.test(php), 'PHP: inicio-stage');
ok((php.match(/class="inicio-map-host/g) || []).length === 1, 'PHP: una sola inicio-map-host');
ok(/inicio-views\\.css/.test(php) && /inicio-mobile\\.css/.test(php) &&
  !/screens\\/inicio\\.css/.test(php), 'PHP: CSS views+mobile (sin inicio.css)');
ok(/@media \\(max-width: 768px\\)/.test(viewsCss) && /@media \\(min-width: 769px\\)/.test(viewsCss),
  'views.css: toggles 768/769');
ok(!/display:\\s*contents/.test(deskCss), 'desktop CSS: sin display:contents');

console.log(failures ? '\\n' + failures + ' FAIL' : '\\nTODO OK 7/7 + estructura');
process.exit(failures ? 1 : 0);
`;
  write('tests/inicio_architecture_test.js', test);
}

function main() {
  console.log('Applying inicio architecture...');

  const php = read('play.php');
  write('play.php', patchPlayPhp(php));
  console.log('OK play.php');

  const inicioCss = read('assets/css/design-system/screens/inicio.css');
  const overrideCss = read('assets/css/play-v3-inicio-override.css');
  const desktopCss = read('assets/css/design-system/screens/inicio-desktop.css');

  let v13 = '';
  let v14 = '';
  try {
    const ov9893 = gitShow('9893edf', 'assets/css/play-v3-inicio-override.css');
    v13 = extractBlock(ov9893, '/* === INICIO-PROXPLANES-REF-v13', '/* === INICIO-ENCURSO-REF-v14');
    const ov4b74 = gitShow('4b74d07', 'assets/css/play-v3-inicio-override.css');
    v14 = extractBlock(ov4b74, '/* === INICIO-ENCURSO-REF-v14', '/* === INICIO-ENCURSO-FIX-v15');
  } catch (e) {
    console.warn('Git refs v13/v14:', e.message);
  }

  write('assets/css/design-system/screens/inicio-views.css', buildInicioViewsCss());
  write('assets/css/design-system/screens/inicio-mobile.css', buildMobileCss(inicioCss, overrideCss, v13, v14));
  write('assets/css/design-system/screens/inicio-desktop.css', buildDesktopCss(desktopCss));
  write('assets/css/play-v3-inicio-override.css', buildOverrideMinimal());
  console.log('OK CSS');

  const js = finalizePlayV3Patches(patchPlayV3Js(read('assets/js/play-v3.js')));
  write('assets/js/play-v3.js', js);
  console.log('OK play-v3.js');

  writeArchitectureTest();
  console.log('OK tests/inicio_architecture_test.js');

  console.log('Done. Run: node tests/inicio_architecture_test.js');
}

main();
