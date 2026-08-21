/* eslint-disable */
const fs = require('fs');
const path = require('path');

const phpPath = path.join(__dirname, '..', 'play.php');
const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let php = fs.readFileSync(phpPath, 'utf8');
let js = fs.readFileSync(jsPath, 'utf8');

php = php.replace("$ahtUi = 'v3-20260821k';", "$ahtUi = 'v3-20260821l';");

if (!php.includes('play-v3-capas-shell.css')) {
  php = php.replace(
    '  <link rel="stylesheet" href="assets/css/play-v3-shell-art.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n',
    '  <link rel="stylesheet" href="assets/css/play-v3-shell-art.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n' +
      '  <link rel="stylesheet" href="assets/css/play-v3-capas-shell.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n'
  );
}

// Dinero one-line label
php = php.replace(
  `<div class="obj-dinero" style="--rot:0.6deg">
          <span class="obj-dinero-lbl">Dinero</span>
          <span class="obj-dinero-val" data-dinero>…</span>
        </div>`,
  `<div class="obj-dinero" style="--rot:0.6deg">
          <span class="obj-dinero-txt" data-dinero>Dinero: …</span>
        </div>`
);

// Organic heart SVG
const heartOld = `<svg class="corazon-svg" viewBox="0 0 56 50" width="64" height="58" aria-hidden="true">
          <path class="corazon-bg" d="M28 47 C28 47 4 30 4 17 C4 8 11 3 19 3 C24 3 28 7 28 7 C28 7 32 3 37 3 C45 3 52 8 52 17 C52 30 28 47 28 47 Z"/>
          <clipPath id="corazon-clip"><path d="M28 47 C28 47 4 30 4 17 C4 8 11 3 19 3 C24 3 28 7 28 7 C28 7 32 3 37 3 C45 3 52 8 52 17 C52 30 28 47 28 47 Z"/></clipPath>
          <rect class="corazon-fill-rect" clip-path="url(#corazon-clip)" x="0" y="50" width="56" height="0"/>
          <path class="corazon-stroke" fill="none" d="M28 47 C28 47 4 30 4 17 C4 8 11 3 19 3 C24 3 28 7 28 7 C28 7 32 3 37 3 C45 3 52 8 52 17 C52 30 28 47 28 47 Z"/>
        </svg>`;
const heartNew = `<svg class="corazon-svg corazon-org" viewBox="0 0 58 52" width="68" height="62" aria-hidden="true">
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <clipPath id="corazon-clip"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
          <rect class="corazon-fill-rect" clip-path="url(#corazon-clip)" x="0" y="52" width="58" height="0"/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>`;
if (php.includes(heartOld)) php = php.replace(heartOld, heartNew);

// Left panel reorder
const leftOld = `<aside class="game-left zona-actividad">
        <section class="shell-grupo shell-grupo-resumen">
          <button type="button" class="obj-vecinos-resumen celestine-nota" data-open="vecinos" aria-label="Ver vecinos">
            <span class="libreta-kicker">Celestine apunta</span>
            <span class="obj-vecinos-tit">Vecinos</span>
            <div class="obj-vecinos-stats" data-resumen-stats></div>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-paso">
          <span class="shell-grupo-kicker">Lo que está pasando</span>
          <div class="shell-par-buzon-coti">
            <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir buzón">
              <span class="obj-buzon-badge" data-buzon-badge hidden>0</span>
              <img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="52" height="42"/>
              <span class="obj-buzon-txt">BUZÓN</span>
            </button>
            <button type="button" class="obj-cotilleo" data-open="diario" aria-label="Abrir diario">
              <span class="obj-cotilleo-tit">Cotilleo</span>
              <p class="obj-cotilleo-txt" data-cotilleo-teaser>—</p>
            </button>
          </div>
        </section>
        <section class="shell-grupo shell-grupo-planes">
          <span class="shell-grupo-kicker">Planes</span>
          <div class="obj-proximo">
            <span class="obj-proximo-tit">Próximo plan</span>
            <div class="obj-proximo-body" data-proximo-plan><p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p></div>
            <button type="button" class="obj-planes-pend" data-planes-pend hidden>
              <span data-planes-pend-txt></span>
            </button>
          </div>
          <button type="button" class="obj-nuevo-plan obj-nota-rosa" data-open="organizar" aria-label="Nuevo plan">
            <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
            <span class="obj-nuevo-plan-txt">NUEVO PLAN</span>
          </button>
        </section>
      </aside>`;

const leftNew = `<aside class="game-left zona-actividad">
        <section class="shell-grupo shell-grupo-resumen">
          <button type="button" class="obj-vecinos-resumen celestine-nota" data-open="vecinos" aria-label="Ver vecinos">
            <span class="libreta-kicker">Celestine apunta</span>
            <span class="obj-vecinos-tit">Vecinos</span>
            <div class="obj-vecinos-stats" data-resumen-stats></div>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-buzon">
          <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir buzón">
            <span class="obj-buzon-badge" data-buzon-badge hidden>0</span>
            <img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="52" height="42"/>
            <span class="obj-buzon-txt">BUZÓN</span>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-planes">
          <div class="obj-proximo">
            <span class="obj-proximo-tit">Próximo plan</span>
            <div class="obj-proximo-body" data-proximo-plan><p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p></div>
            <button type="button" class="obj-planes-pend" data-planes-pend hidden>
              <span data-planes-pend-txt></span>
            </button>
          </div>
          <button type="button" class="obj-nuevo-plan obj-nota-rosa" data-open="organizar" aria-label="Nuevo plan">
            <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
            <span class="obj-nuevo-plan-txt">NUEVO PLAN</span>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-cotilleo">
          <button type="button" class="obj-cotilleo" data-open="diario" aria-label="Abrir diario">
            <span class="obj-cotilleo-tit">Cotilleo</span>
            <p class="obj-cotilleo-txt" data-cotilleo-teaser>—</p>
          </button>
        </section>
      </aside>`;

if (php.includes(leftOld)) php = php.replace(leftOld, leftNew);
else console.warn('left panel block not found');

// Right panel BLOQUES + parejas tab
php = php.replace(
  '<span class="zona-tit">Residencias</span>',
  '<span class="zona-tit">Bloques</span>'
);
php = php.replace(
  '<span class="zona-tit">Parejas</span>',
  '<span class="zona-tit zona-tit-parejas">Parejas</span>'
);

// Capa close buttons - add class
php = php.replace(/<button type="button" class="cerrar" data-close>cerrar<\/button>/g,
  '<button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>');

fs.writeFileSync(phpPath, php);

// JS: setCapa with origin
if (!js.includes('function capaOriginFrom')) {
  js = js.replace(
    `  function setCapa(name) {
    const root = $('.play-root');
    if (!name) root.removeAttribute('data-capa');
    else root.setAttribute('data-capa', name);
    $$('.dock button').forEach(function (b) {
      const open = b.getAttribute('data-open');
      b.classList.toggle('is-on', name ? open === name : !open);
    });
  }`,
    `  function capaOriginFrom(el) {
    if (!el) return 'right';
    if (el.closest('.game-left')) return 'left';
    if (el.closest('.game-right')) return 'right';
    if (el.closest('.game-map-wrap')) return 'left';
    return 'right';
  }

  function setCapa(name, origin) {
    const root = $('.play-root');
    if (!name) {
      root.removeAttribute('data-capa');
      root.removeAttribute('data-capa-origin');
    } else {
      root.setAttribute('data-capa', name);
      root.setAttribute('data-capa-origin', origin || root.getAttribute('data-capa-origin') || 'right');
    }
    $$('.dock button').forEach(function (b) {
      const open = b.getAttribute('data-open');
      b.classList.toggle('is-on', name ? open === name : !open);
    });
  }`
  );
}

js = js.replace(
  `  function abrirAgendaPlanes(highlightId) {
    renderAgendaPlanes(highlightId || null);
    setCapa('agenda');
  }`,
  `  function abrirAgendaPlanes(highlightId, origin) {
    renderAgendaPlanes(highlightId || null);
    setCapa('agenda', origin || 'left');
  }`
);

js = js.replace(
  `    if (pendPlan) {
      abrirAgendaPlanes(null);
      return;
    }`,
  `    if (pendPlan) {
      abrirAgendaPlanes(null, capaOriginFrom(pendPlan));
      return;
    }`
);

js = js.replace(
  `    if (notifBtn) {
      hidePlanNotif();
      abrirAgendaPlanes(planNotifEncId);
      return;
    }`,
  `    if (notifBtn) {
      hidePlanNotif();
      abrirAgendaPlanes(planNotifEncId, 'left');
      return;
    }`
);

js = js.replace(
  `    const open = ev.target.closest('[data-open]');
    if (open && (open.closest('.play-root') || open.closest('.game-shell'))) {
      const name = open.getAttribute('data-open');
      setCapa(name);`,
  `    const open = ev.target.closest('[data-open]');
    if (open && (open.closest('.play-root') || open.closest('.game-shell'))) {
      const name = open.getAttribute('data-open');
      setCapa(name, capaOriginFrom(open));`
);

// Dinero label in renderHud
js = js.replace(
  `    $$('[data-dinero]').forEach(function (el) {
      el.textContent = dineroTxt(cacheInsp, estado);
    });`,
  `    $$('[data-dinero]').forEach(function (el) {
      const v = dineroTxt(cacheInsp, estado);
      el.textContent = el.classList.contains('obj-dinero-txt') ? ('Dinero: ' + v) : v;
    });`
);

// Heart fill uses new viewBox height 52
js = js.replace(
  `      fill.style.height = pct + '%';
      fill.style.y = (50 - (50 * pct / 100)) + 'px';`,
  `      const h = 52;
      fill.style.height = pct + '%';
      fill.style.y = (h - (h * pct / 100)) + 'px';`
);

fs.writeFileSync(jsPath, js);
console.log('pass2 shell patched');
