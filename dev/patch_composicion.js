const fs = require('fs');

// === CSS ===
const css = `@import url("https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&display=swap");

.play-v3 .play-root { background: #fafaf8 !important; }
.play-v3 .play-root::after { display: none !important; }
.play-v3 .board-scroll, .play-v3 .board-fit, .play-v3 .play-stage, .play-v3 .game-map-wrap { background: #fff; }
.play-v3 .board-fit {
  box-shadow: none !important;
  background: url("../play-v3/mapa_base_rectas.png") center / 100% 100% no-repeat !important;
}
.play-v3:has(.game-shell) .mesa, .play-v3:has(.game-shell) .mesa *, .play-v3:has(.game-shell) .play-root.pc .dock {
  display: none !important; visibility: hidden !important; pointer-events: none !important;
}

body.play-v3[data-lab="1"] .taller, body.play-v3[data-lab="1"] .playtest-cheats { display: none !important; }
.playtest-float { position: fixed; z-index: 90; right: 12px; bottom: 12px; }
.playtest-float-toggle { border: 2px solid #2c261f; background: #fff6c8; font-weight: 800; font-size: .78rem; padding: .45rem .65rem; border-radius: 999px; cursor: pointer; box-shadow: 3px 4px 0 rgba(44,38,31,.1); transform: rotate(-1deg); }
.playtest-float-panel { position: absolute; right: 0; bottom: calc(100% + 8px); width: min(280px, 88vw); padding: .65rem; background: #fffdf6; border: 2px solid #2c261f; display: flex; flex-wrap: wrap; gap: .35rem; }
.playtest-float-panel[hidden] { display: none !important; }
.playtest-float-title { width: 100%; margin: 0 0 .2rem; font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #7a7164; }
.playtest-float-panel button, .playtest-float-panel a { border: 2px solid #8a7a66; background: #fff; font: inherit; font-weight: 800; font-size: .72rem; padding: .25rem .45rem; cursor: pointer; text-decoration: none; color: inherit; }

/* === Shell grid === */
.game-shell { display: flex; flex-direction: column; height: calc(100vh - 52px); min-height: 0; background: #fafaf8; }
body.play-v3[data-lab="1"] .game-shell { height: 100vh; }
.game-main {
  flex: 1; min-height: 0; display: grid;
  grid-template-columns: minmax(148px, 188px) minmax(0, 1fr) minmax(148px, 188px);
  gap: 10px; padding: 8px 10px; background: #fafaf8;
}
.game-map-wrap { min-width: 0; min-height: 0; display: flex; background: #fff; border: none; border-radius: 0; }
.game-map-wrap .play-stage { flex: 1; min-height: 0; width: 100%; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* === Cabecera: logo | centro | vida === */
.game-top {
  display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .75rem 1.25rem;
  background: #fafaf8; border-bottom: none; padding: .35rem .85rem .45rem;
}
.brand { margin: 0; display: flex; align-items: center; gap: .4rem; transform: rotate(-1deg); }
.brand-text { font-family: Caveat, cursive; font-size: clamp(1.55rem, 2.2vw, 1.95rem); font-weight: 700; line-height: 1; color: #2c261f; }
.brand-heart {
  display: inline-block; width: 18px; height: 16px; margin-left: .18rem;
  background: linear-gradient(180deg, #e56b8a, #c42b4a);
  -webkit-mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
  mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
  transform: rotate(8deg) translateY(-1px);
}
.top-center { display: flex; align-items: flex-end; justify-content: center; gap: 1.35rem; flex-wrap: nowrap; }
.top-vida { display: flex; flex-direction: column; align-items: center; gap: .08rem; justify-self: end; padding-right: .15rem; }
.obj-vida-kicker { font-size: .5rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #8a7a66; }
.corazon-svg { display: block; filter: drop-shadow(1px 2px 0 rgba(44,38,31,.08)); }
.corazon-bg { fill: #f3e8ec; }
.corazon-fill-rect { fill: #e56b8a; transition: y .35s ease, height .35s ease; }
.corazon-stroke { stroke: #2c261f; stroke-width: 2.6; stroke-linejoin: round; }

/* Día — ticket vertical irregular */
.obj-dia {
  display: flex; flex-direction: column; gap: .04rem; padding: .34rem .58rem .4rem;
  background: linear-gradient(168deg, #fff8c4, #ffef9e);
  border: 2px solid #2c261f; transform: rotate(var(--rot, -2deg));
  clip-path: polygon(2% 0, 98% 4%, 96% 100%, 6% 96%, 0 12%);
  box-shadow: 2px 2px 0 rgba(44,38,31,.08);
}
.obj-dia-num { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .74rem; letter-spacing: .05em; }
.obj-dia-dow { font-size: .66rem; font-weight: 800; color: #5a5048; }
.obj-dia-hora { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .84rem; }

/* Dinero — tira horizontal distinta */
.obj-dinero {
  display: flex; flex-direction: column; align-items: flex-start; gap: .02rem;
  padding: .18rem .55rem .22rem .45rem; min-width: 78px;
  background: linear-gradient(90deg, #fff6b8 0%, #fffdf0 100%);
  border: 0; border-bottom: 2.5px solid #2c261f; transform: rotate(var(--rot, .6deg));
  box-shadow: 0 2px 0 rgba(44,38,31,.07);
}
.obj-dinero-lbl { font-size: .5rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #7a7164; }
.obj-dinero-val { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .88rem; line-height: 1.1; }

/* === Zona ACTIVIDAD (izquierda) — proximidad, sin caja === */
.zona-actividad { display: flex; flex-direction: column; gap: .65rem; align-items: stretch; padding-top: .15rem; }
.obj-buzon {
  position: relative; display: flex; flex-direction: column; align-items: center;
  padding: .2rem 0 0; border: 0; background: transparent; cursor: pointer; font: inherit; color: inherit;
}
.obj-buzon-badge {
  position: absolute; top: -2px; right: calc(50% - 42px); z-index: 2;
  min-width: 1.15rem; padding: 0 .28rem; font-size: .65rem; font-weight: 800; line-height: 1.35;
  background: #fde8ef; border: 2px solid #c42b4a; border-radius: 999px; color: #2c261f;
}
.obj-buzon-badge[hidden] { display: none !important; }
.obj-buzon-img { display: block; width: 50px; height: auto; transform: rotate(-2deg); filter: drop-shadow(2px 3px 0 rgba(44,38,31,.1)); }
.obj-buzon-txt { font-weight: 800; font-size: .68rem; letter-spacing: .08em; margin-top: .15rem; }

.obj-cotilleo {
  width: 100%; padding: .45rem .55rem .5rem .6rem; margin: -.15rem 0 0 .08rem;
  border: 0; background: #fde8ef; cursor: pointer; text-align: left; font: inherit; color: inherit;
  transform: rotate(-0.8deg);
  clip-path: polygon(0 0, 94% 3%, 100% 90%, 8% 100%);
  box-shadow: 2px 2px 0 rgba(196,43,74,.1);
}
.obj-cotilleo-tit { display: block; font-size: .58rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #8a4a58; margin-bottom: .2rem; }
.obj-cotilleo-txt { margin: 0; font-family: Fraunces, Georgia, serif; font-style: italic; font-size: .76rem; line-height: 1.32; color: #2c261f; }

.obj-proximo { padding: .15rem 0 .1rem .1rem; background: transparent; border: 0; }
.obj-proximo-tit { display: block; font-size: .58rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: #7a7164; margin-bottom: .25rem; }
.obj-proximo-body { font-size: .76rem; line-height: 1.35; color: #2c261f; }
.obj-proximo-vacio { font-family: Fraunces, Georgia, serif; font-style: italic; margin: 0; color: #6a5848; font-size: .78rem; }
.prox-faces { margin-bottom: .2rem; }
.prox-faces img, .prox-faces .cara-ini { width: 26px; height: 26px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; margin-right: .15rem; vertical-align: middle; }
.prox-nombres { margin: 0 0 .08rem; font-size: .78rem; font-weight: 700; }
.prox-meta { margin: 0; font-size: .72rem; color: #6a5848; }

.obj-nuevo-plan {
  display: flex; align-items: center; gap: .45rem; width: fit-content;
  padding: 0; margin-top: .1rem; border: 0; background: transparent;
  cursor: pointer; font: inherit; color: #2c261f;
}
.obj-nuevo-plan-ico {
  width: 32px; height: 32px; flex: 0 0 32px; border-radius: 50%;
  border: 2px solid #2c261f; background: #fffdf6;
  display: grid; place-items: center; font-size: 1.25rem; font-weight: 700; line-height: 1;
  box-shadow: inset 0 -2px 0 rgba(44,38,31,.08), 2px 2px 0 rgba(44,38,31,.08);
  transform: rotate(-4deg);
}
.obj-nuevo-plan-txt { font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }

.obj-bloques { display: flex; flex-direction: column; gap: .3rem; margin-top: auto; padding-top: .5rem; opacity: .92; }
.obj-bloque { display: flex; align-items: center; gap: .4rem; border: 0; background: transparent; padding: .15rem 0; font: inherit; text-align: left; color: inherit; }
.bloque-fachada { width: 26px; height: 22px; flex: 0 0 26px; border: 1.5px solid #2c261f; background: #f3e6cc; clip-path: polygon(10% 100%, 10% 40%, 50% 12%, 90% 40%, 90% 100%); position: relative; }
.bloque-letra { position: absolute; bottom: -4px; left: 50%; transform: translateX(-50%); font-size: .5rem; font-weight: 800; background: #fff6c8; border: 1px solid #2c261f; padding: 0 2px; }
.bloque-info { font-size: .64rem; line-height: 1.2; }
.bloque-info strong { font-weight: 800; }

/* === Zona PERSONAS (derecha) === */
.zona-personas { display: flex; flex-direction: column; gap: .75rem; align-items: stretch; padding-top: .15rem; }
.obj-pueblo {
  display: flex; flex-direction: column; align-items: center; gap: .25rem;
  padding: 0; border: 0; background: transparent; cursor: pointer; font: inherit; color: inherit;
}
.obj-pueblo-faces { display: flex; align-items: center; justify-content: center; min-height: 32px; padding-left: 8px; }
.obj-pueblo-faces img, .obj-pueblo-faces .cara-ini {
  width: 28px; height: 28px; border-radius: 50%; object-fit: cover;
  border: 2px solid #fff; box-shadow: 0 1px 2px rgba(44,38,31,.12);
  margin-left: -10px; background: #f3e6cc;
}
.obj-pueblo-faces img:first-child, .obj-pueblo-faces .cara-ini:first-child { margin-left: 0; }
.obj-pueblo-faces .cara-ini { display: grid; place-items: center; font-size: .65rem; font-weight: 800; }
.obj-pueblo-txt { font-weight: 800; font-size: .72rem; letter-spacing: .07em; transform: rotate(-0.4deg); }

.obj-parejas { padding: 0; background: transparent; }
.obj-parejas-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .3rem; gap: .25rem; }
.obj-parejas-tab { font-size: .58rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: #7a7164; }
.obj-pestaña { border: 0; background: none; font: inherit; font-size: .58rem; font-weight: 800; color: #8a7a66; cursor: pointer; text-decoration: underline; text-underline-offset: 2px; padding: 0; }
.obj-pestaña:disabled { opacity: .35; cursor: not-allowed; text-decoration: none; }
.obj-parejas-list { display: flex; flex-direction: column; gap: .45rem; }
.obj-pareja-fila { display: flex; flex-direction: column; gap: .1rem; }
.obj-pareja-fotos { display: flex; align-items: center; }
.obj-pareja-cara { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; margin-right: -7px; box-shadow: 0 1px 1px rgba(0,0,0,.1); background: #f3e6cc; }
.obj-pareja-cara.cara-ini { display: grid; place-items: center; font-size: .6rem; font-weight: 800; }
.obj-pareja-corazon { width: 9px; height: 8px; margin: 0 -1px; z-index: 1; background: #e56b8a; clip-path: polygon(50% 100%, 0 38%, 0 18%, 22% 0, 50% 16%, 78% 0, 100% 18%, 100% 38%); border: 1px solid #2c261f; }
.obj-pareja-nombres { font-size: .66rem; line-height: 1.2; padding-left: .1rem; }
.obj-parejas-vacio { font-family: Fraunces, Georgia, serif; font-style: italic; font-size: .7rem; color: #7a7164; margin: 0; line-height: 1.3; }

.obj-resumen { font-size: .68rem; padding: .25rem 0; color: #6a5848; }
.stat-row { display: flex; justify-content: space-between; padding: .08rem 0; }

/* === Edificios bloqueados: apagado + sombra interior (probado en biblioteca) === */
.play-v3 .edificios-layer .edif.is-on {
  filter: none; opacity: 1;
}
/* Prototipo biblioteca → mismo sistema .is-off */
.play-v3 .edificios-layer .edif.b-biblioteca.is-off,
.play-v3 .edificios-layer .edif.is-off {
  opacity: 1 !important;
  filter: saturate(0.78) brightness(0.9) contrast(0.96) !important;
}
.play-v3 .edificios-layer .edif.is-off::after {
  content: "" !important;
  position: absolute; inset: 0; pointer-events: none; z-index: 2;
  background:
    radial-gradient(ellipse 85% 75% at 50% 55%, rgba(25,20,15,0) 0%, rgba(25,20,15,.18) 72%, rgba(15,12,10,.28) 100%);
  box-shadow:
    inset 0 0 24px 6px rgba(30,25,20,.22),
    inset 0 0 48px 16px rgba(40,35,30,.12);
}
.play-v3 .edificios-layer .edif.is-off::before {
  content: "EN OBRAS" !important;
  position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
  font: 700 6px/1 Nunito, sans-serif; letter-spacing: .06em;
  color: rgba(44,38,31,.75); background: rgba(255,246,200,.75);
  padding: 1px 5px; border-radius: 2px; z-index: 3; pointer-events: none;
  border: 1px solid rgba(44,38,31,.35);
}

/* Buzón modal */
.play-v3 .play-root.pc .capa-buzon { width: min(520px, 94vw) !important; max-width: 520px !important; }
.play-v3 .capa-buzon [data-buzon-list] { max-height: calc(88vh - 120px); overflow-y: auto; scrollbar-width: thin; scrollbar-color: #c9b59a transparent; }
.play-v3 .capa-buzon .carta-msg { display: block !important; grid-template-columns: unset !important; }
.play-v3 .capa-buzon .carta-inner { display: flex !important; gap: .75rem; min-width: 0; width: 100%; }
.play-v3 .capa-buzon .carta-copy { flex: 1 1 auto; min-width: 0; }
`;
fs.writeFileSync('W:/juegos/aqui-hay-tema/assets/css/play-v3-shell-ui.css', css);

// === JS patch renderShellPanels ===
const jsPath = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

const puebloPatch = `
    const puebloFaces = $('[data-pueblo-faces]');
    if (puebloFaces) {
      const ids = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; }).slice(0, 3);
      puebloFaces.innerHTML = '';
      if (!ids.length) {
        puebloFaces.innerHTML = '<span class="cara-ini">?</span><span class="cara-ini">?</span><span class="cara-ini">?</span>';
      } else {
        ids.forEach(function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) {
            const img = document.createElement('img');
            img.src = t.url; img.alt = '';
            puebloFaces.appendChild(img);
          } else {
            const sp = document.createElement('span');
            sp.className = 'cara-ini';
            sp.textContent = (nombreDe(id)[0] || '?');
            puebloFaces.appendChild(sp);
          }
        });
      }
    }
`;

if (!js.includes('data-pueblo-faces')) {
  js = js.replace(
    '    const badge = $(\'[data-buzon-badge]\');',
    puebloPatch + '\n    const badge = $(\'[data-buzon-badge]\');'
  );
}

js = js.replace(
  `    if (badge) {
      badge.textContent = String(pend.length);
      badge.classList.toggle('is-on', pend.length > 0);
    }`,
  `    if (badge) {
      badge.textContent = String(pend.length);
      if (pend.length > 0) {
        badge.hidden = false;
        badge.classList.add('is-on');
      } else {
        badge.hidden = true;
        badge.classList.remove('is-on');
      }
    }`
);

js = js.replace(
  `        proxBox.innerHTML = '<div class="prox-faces">' + faces + '</div>' +
          '<p class="prox-nombres"><strong>' + ids.map(function (id) { return esc(nombreDe(id)); }).join(' · ') + '</strong></p>' +
          '<p class="muted-soft">' + esc(next.lugar_nombre || next.lugar || 'Lugar') +
          ' · Día ' + (next.dia || '?') + ' · ' + String(next.hora_inicio || next.hora || '?').padStart(2, '0') + ':00</p>';`,
  `        proxBox.innerHTML = '<div class="prox-faces">' + faces + '</div>' +
          '<p class="prox-nombres">' + ids.map(function (id) { return esc(nombreDe(id)); }).join(' · ') + '</p>' +
          '<p class="prox-meta">' + esc(next.lugar_nombre || next.lugar || 'Lugar') +
          ' · ' + String(next.hora_inicio || next.hora || '?').padStart(2, '0') + ':00</p>';`
);

// Remove buzon preview if still referenced
js = js.replace(/    const prev = \$\('\[data-buzon-preview\]'\);[\s\S]*?    \}\n\n    const proxBox/, '    const proxBox');

fs.writeFileSync(jsPath, js);
console.log('css + js ok');
