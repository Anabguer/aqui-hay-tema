const fs = require('fs');
const css = `@import url("https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&display=swap");

/* === Fondo limpio: eliminar marrón/rayas del play-root legacy === */
.play-v3 .play-root {
  background: #fafaf8 !important;
}
.play-v3 .play-root::after {
  display: none !important;
  content: none !important;
}
.play-v3 .board-scroll,
.play-v3 .board-fit,
.play-v3 .game-map-wrap,
.play-v3 .play-stage {
  background-color: #fff;
}
.play-v3 .board-fit {
  box-shadow: none !important;
  background: url("../play-v3/mapa_base_rectas.png") center / 100% 100% no-repeat !important;
}

/* === Playtest flotante === */
body.play-v3[data-lab="1"] .taller,
body.play-v3[data-lab="1"] .playtest-cheats { display: none !important; }
.playtest-float {
  position: fixed; z-index: 90; right: 12px; bottom: 12px;
}
.playtest-float-toggle {
  border: 2px solid #2c261f; background: #fff6c8; color: #2c261f;
  font-weight: 800; font-size: .78rem; padding: .45rem .65rem;
  border-radius: 999px; cursor: pointer; box-shadow: 3px 4px 0 rgba(44,38,31,.15);
  transform: rotate(-1deg);
}
.playtest-float-panel {
  position: absolute; right: 0; bottom: calc(100% + 8px);
  width: min(280px, 88vw); padding: .65rem;
  background: #fffdf6; border: 2px solid #2c261f;
  box-shadow: 6px 8px 0 rgba(44,38,31,.12); border-radius: 8px;
  display: flex; flex-wrap: wrap; gap: .35rem;
}
.playtest-float-panel[hidden] { display: none !important; }
.playtest-float-title {
  width: 100%; margin: 0 0 .2rem; font-size: .68rem; font-weight: 800;
  letter-spacing: .08em; text-transform: uppercase; color: #7a7164;
}
.playtest-float-panel button, .playtest-float-panel a {
  border: 2px solid #8a7a66; background: #fff; font: inherit; font-weight: 800;
  font-size: .72rem; padding: .25rem .45rem; cursor: pointer; text-decoration: none; color: inherit;
}

/* === Layout shell === */
.game-shell { display: flex; flex-direction: column; height: calc(100vh - 52px); min-height: 520px; background: #fafaf8; }
body.play-v3[data-lab="1"] .game-shell { height: 100vh; min-height: 0; }
.game-main {
  flex: 1; min-height: 0; display: grid;
  grid-template-columns: minmax(168px, 210px) minmax(0, 1fr) minmax(168px, 210px);
  gap: 8px; padding: 8px; background: #fafaf8;
}
.game-left, .game-right { display: flex; flex-direction: column; gap: .45rem; overflow-y: auto; min-height: 0; background: transparent; }
.game-map-wrap {
  min-width: 0; min-height: 0; position: relative; display: flex;
  background: #fff; border: 2px solid #ebe6dc; border-radius: 12px;
}
.game-map-wrap .play-stage { flex: 1; min-height: 0; width: 100%; }
.game-shell .mesa .dia, .game-shell .mesa .dinero, .game-shell .mesa .corazon { display: none !important; }
.stat-row { display: flex; justify-content: space-between; font-size: .78rem; padding: .15rem 0; }
.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
  overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}

/* === Logotipo manuscrito === */
.game-top {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  background: #fafaf8; border-bottom: 2px solid #ebe6dc; padding: .45rem .85rem .55rem;
}
.brand {
  margin: 0; display: flex; align-items: center; gap: .15rem;
  transform: rotate(-1deg);
}
.brand-text {
  font-family: Caveat, "Segoe Script", cursive;
  font-size: clamp(1.65rem, 2.4vw, 2.05rem);
  font-weight: 700; line-height: 1;
  color: #2c261f; letter-spacing: .02em;
  text-shadow: 1px 1.5px 0 rgba(255,255,255,.85);
}
.brand-heart {
  display: inline-block; width: 20px; height: 18px; flex: 0 0 20px;
  background: linear-gradient(180deg, #e56b8a, #c42b4a);
  -webkit-mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
  mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
  transform: rotate(8deg) translateY(-2px);
  filter: drop-shadow(1px 1px 0 rgba(44,38,31,.15));
}
.top-meta { margin-left: auto; display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-start; }

/* === Tickets amarillos (día / dinero) === */
.ticket {
  display: inline-block; vertical-align: top;
  border: 0; background: transparent; padding: 0;
  transform: rotate(var(--rot, 0deg));
  filter: drop-shadow(2px 3px 0 rgba(44,38,31,.11));
}
.ticket-dia, .ticket-dinero {
  min-width: 96px; padding: .42rem .72rem .48rem;
  background: url("../play-v3/hud/ticket_dia.png") center / 100% 100% no-repeat;
}
.ticket-dinero {
  background-image: url("../play-v3/hud/ticket_dinero.png");
  min-width: 82px;
}
.ticket-vida {
  padding: .35rem .5rem .4rem;
  background: #fde8ef;
  border: 2px solid #2c261f;
  border-radius: 8px 6px 9px 7px;
  box-shadow: 2px 3px 0 rgba(44,38,31,.08);
}
.ticket-kicker {
  display: block; font-size: .56rem; font-weight: 800; letter-spacing: .11em;
  text-transform: uppercase; color: #6a5848; margin-bottom: .1rem;
}
.ticket-body {
  margin: 0; font-family: Fraunces, Georgia, serif; font-weight: 700;
  font-size: .8rem; line-height: 1.22; color: #2c261f;
}
.ticket-dow { font-size: .88rem; }

/* === Corazón rellenable (vida del pueblo) === */
.vida-row { display: flex; align-items: center; justify-content: center; min-height: 44px; }
.corazon-shell {
  display: block; width: 52px; height: 48px; position: relative;
}
.corazon-shell-fill {
  display: block; width: 100%; height: 100%;
  background: linear-gradient(to top, #c42b4a 0%, #e56b8a var(--fill, 0%), #f3e8ec var(--fill, 0%));
  -webkit-mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
  mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
}

/* === Tarjetas papel lateral === */
.ui-card.paper, .buzon-compact, .pueblo-card, .cotilleo-card {
  border: 2px solid #2c261f; border-radius: 9px 11px 8px 10px;
  box-shadow: 3px 4px 0 rgba(44,38,31,.07);
  text-align: left;
}
.ui-card.paper { background: #f7f0de; padding: .65rem .7rem; transform: rotate(var(--rot, -0.35deg)); }
.cotilleo-card {
  background: #fde8ef; border-color: #d48aa0; width: 100%; font: inherit; color: inherit;
  position: relative; padding: .65rem 2rem .7rem .7rem; cursor: pointer;
  transform: rotate(-0.6deg);
}
.cotilleo-card:hover { filter: brightness(1.02); }
.ui-card h3, .cotilleo-card h3 {
  margin: 0 0 .35rem; font-size: .68rem; font-weight: 800;
  letter-spacing: .08em; text-transform: uppercase; color: #6a5848;
}
.cotilleo-teaser {
  font-family: Fraunces, Georgia, serif; font-style: italic;
  font-size: .86rem; line-height: 1.35; margin: 0; color: #2c261f;
}
.muted-soft { color: #7a7164; font-size: .78rem; margin: 0; line-height: 1.35; }

/* Icono diario (pestaña dibujada) */
.icon-diario {
  position: absolute; right: .5rem; bottom: .45rem;
  width: 20px; height: 16px; border: 2px solid #2c261f; background: #fffdf6;
  transform: rotate(7deg); border-radius: 1px 4px 1px 1px;
  box-shadow: 1px 2px 0 rgba(44,38,31,.08);
}
.icon-diario::before, .icon-diario::after {
  content: ""; position: absolute; left: 3px; right: 3px; height: 1.5px; background: #2c261f; opacity: .55;
}
.icon-diario::before { top: 4px; box-shadow: 0 4px 0 #2c261f; }
.icon-diario::after { top: 12px; width: 60%; }

/* Bloques A/B/C mini-fachadas */
.bloques-grid { display: flex; flex-direction: column; gap: .35rem; }
.bloque-mini {
  display: flex; align-items: center; gap: .5rem;
  border: 2px solid #2c261f; background: #fffdf6; border-radius: 8px 10px 7px 9px;
  padding: .38rem .5rem; width: 100%; text-align: left;
  box-shadow: 2px 2px 0 rgba(44,38,31,.06); transform: rotate(var(--bloque-rot, 0deg));
}
.bloque-a { --bloque-rot: -0.5deg; }
.bloque-b { --bloque-rot: 0.7deg; }
.bloque-c { --bloque-rot: -0.3deg; }
.bloque-mini.is-cerrado { opacity: .78; filter: saturate(.65); }
.bloque-mini-casa {
  width: 30px; height: 26px; flex: 0 0 30px; position: relative;
  border: 2px solid #2c261f; border-radius: 2px 2px 0 0;
}
.bloque-a .bloque-mini-casa { background: #f3d4b8; }
.bloque-b .bloque-mini-casa { background: #d5e0c8; }
.bloque-c .bloque-mini-casa { background: #e8d5b3; }
.bloque-mini-casa::before {
  content: ""; position: absolute; left: -4px; right: -4px; top: -11px; height: 13px;
  clip-path: polygon(50% 0, 100% 100%, 0 100%);
  border: 2px solid #2c261f; border-bottom: 0;
}
.bloque-a .bloque-mini-casa::before { background: #c45; }
.bloque-b .bloque-mini-casa::before { background: #7a9e6a; }
.bloque-c .bloque-mini-casa::before { background: #b08968; }
.bloque-mini-casa::after {
  content: ""; position: absolute; left: 50%; bottom: 3px; transform: translateX(-50%);
  width: 8px; height: 10px; border: 1.5px solid #2c261f; border-top: 0; background: rgba(255,253,246,.6);
}
.bloque-mini-txt { font-size: .7rem; font-weight: 800; line-height: 1.25; }
.bloque-mini-txt em { font-style: normal; color: #7a7164; font-weight: 700; }

/* Buzón con sobre ilustrado */
.buzon-compact {
  display: block; padding: .55rem .65rem; background: #f7f0de;
  border: 2px solid #2c261f; border-radius: 10px 8px 11px 9px; cursor: pointer;
  box-shadow: 3px 4px 0 rgba(44,38,31,.08); transform: rotate(-0.55deg); width: 100%;
  font: inherit; color: inherit; text-align: left;
}
.buzon-compact:hover { filter: brightness(1.02); }
.buzon-compact-top { display: flex; justify-content: space-between; align-items: center; gap: .35rem; }
.buzon-tit { font-weight: 800; font-size: .82rem; letter-spacing: .06em; }
.buzon-sobre-wrap { position: relative; display: inline-flex; align-items: center; }
.buzon-sobre { display: block; width: 44px; height: auto; pointer-events: none; }
.buzon-badge {
  position: absolute; top: -6px; right: -8px;
  min-width: 1.25rem; text-align: center; font-weight: 800; font-size: .68rem;
  background: #fde8ef; border: 2px solid #c42b4a; border-radius: 999px; padding: 0 .3rem;
  color: #2c261f; line-height: 1.35;
}
.buzon-badge:not(.is-on) { opacity: .55; background: #fff; border-color: #2c261f; }
.buzon-hint { display: block; margin-top: .3rem; font-size: .72rem; color: #6a5848; line-height: 1.3; }

/* Nuevo plan — pestaña rosa tipo Organizar */
.btn-nuevo-plan {
  border: 2px solid #2c261f; background: #fde8ef;
  color: #2c261f; font-family: Fraunces, Georgia, serif; font-style: italic; font-weight: 700;
  font-size: .82rem; padding: .52rem .7rem; border-radius: 6px 8px 5px 7px;
  cursor: pointer; box-shadow: 3px 3px 0 rgba(44,38,31,.1);
  transform: rotate(-1.8deg); width: 100%;
  clip-path: polygon(1% 6%, 99% 0, 97% 96%, 2% 100%);
}
.btn-nuevo-plan:hover { background: #f9d4e0; }
.btn-nuevo-plan-txt { display: block; transform: rotate(0.4deg); }

/* El pueblo */
.pueblo-card {
  display: flex; flex-direction: column; align-items: flex-start; gap: .2rem;
  padding: .65rem .7rem; background: #fffdf8; cursor: pointer; width: 100%;
  transform: rotate(0.5deg);
}
.pueblo-card:hover { filter: brightness(1.02); }
.icon-pueblo {
  width: 28px; height: 22px; position: relative;
}
.icon-pueblo::before, .icon-pueblo::after {
  content: ""; position: absolute; bottom: 0;
  width: 11px; height: 11px; border-radius: 50% 50% 45% 45%;
  border: 2px solid #2c261f; background: #f3d4b8;
}
.icon-pueblo::before { left: 0; }
.icon-pueblo::after { right: 0; background: #fde8ef; }
.pueblo-tit { font-weight: 800; font-size: .88rem; letter-spacing: .06em; }
.pueblo-sub { font-size: .72rem; color: #7a7164; }

/* Parejas */
.parejas-panel { transform: rotate(0.15deg); background: #fffdf8; }
.parejas-head { display: flex; justify-content: space-between; align-items: center; gap: .35rem; }
.parejas-head h3 { margin: 0; }
.parejas-compact { display: flex; flex-direction: column; gap: .35rem; margin-top: .35rem; max-height: 140px; overflow-y: auto; }
.pareja-row {
  display: flex; align-items: center; gap: .4rem; font-size: .72rem;
  padding: .28rem .35rem; background: rgba(255,253,246,.85);
  border: 1.5px dashed #d5c9b4; border-radius: 6px;
}
.pareja-faces img, .pareja-faces .cara-ini {
  width: 22px; height: 22px; border-radius: 50%; object-fit: cover;
  border: 1.5px solid #fff; box-shadow: 0 1px 1px rgba(0,0,0,.12); margin-right: -6px;
}
.pareja-nombres { line-height: 1.2; }
.parejas-vacio { font-family: Fraunces, Georgia, serif; font-style: italic; }
.btn-papel-sm {
  border: 2px solid #8a7a66; background: #fffdf6; font: inherit; font-weight: 800;
  font-size: .68rem; padding: .22rem .45rem; cursor: pointer; border-radius: 4px;
  box-shadow: 2px 2px 0 rgba(44,38,31,.08); text-decoration: none; color: inherit;
}
.btn-papel-sm:disabled { opacity: .45; cursor: not-allowed; }

/* Próximo plan */
.prox-faces img, .prox-faces .cara-ini {
  width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
  border: 2px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1); margin-right: .25rem;
}
.prox-nombres { margin: .25rem 0 .1rem; font-size: .82rem; }

/* === Edificios EN OBRAS (gris claro, sin candado) === */
.play-v3 .edificios-layer .edif.is-on {
  filter: none; opacity: 1;
}
.play-v3 .edificios-layer .edif.is-off {
  opacity: 1 !important;
  filter: grayscale(0.95) saturate(0.06) brightness(1.08) contrast(0.92) !important;
}
.play-v3 .edificios-layer .edif.is-off::before {
  content: "EN OBRAS" !important;
  position: absolute; left: 50%; top: 10px; transform: translateX(-50%) rotate(-2.5deg);
  font: 800 8px/1.1 Nunito, "Segoe UI", sans-serif; letter-spacing: .07em;
  color: #2c261f; background: #fff6c8; border: 2px solid #2c261f;
  padding: 2px 7px; border-radius: 3px; box-shadow: 2px 2px 0 rgba(44,38,31,.12);
  pointer-events: none; z-index: 3;
}
.play-v3 .edificios-layer .edif.is-off::after {
  content: "" !important;
  position: absolute; inset: 0; pointer-events: none; z-index: 2;
  background:
    repeating-linear-gradient(-45deg, transparent 0 7px, rgba(196,43,74,.14) 7px 14px),
    linear-gradient(180deg, rgba(255,255,255,.08), rgba(120,120,120,.06));
  border: 2px dashed rgba(80,74,66,.5);
  box-sizing: border-box;
}

/* Buzón modal */
.play-v3 .play-root.pc .capa-buzon { width: min(520px, 94vw) !important; max-width: 520px !important; }
.play-v3 .capa-buzon [data-buzon-list] {
  max-height: calc(88vh - 120px); overflow-y: auto; scrollbar-width: thin;
  scrollbar-color: #c9b59a transparent;
}
.play-v3 .capa-buzon [data-buzon-list]::-webkit-scrollbar { width: 6px; }
.play-v3 .capa-buzon [data-buzon-list]::-webkit-scrollbar-thumb { background: #c9b59a; border-radius: 999px; }
.play-v3 .capa-buzon .carta-msg { display: block !important; grid-template-columns: unset !important; }
.play-v3 .capa-buzon .carta-inner { display: flex !important; gap: .75rem; min-width: 0; width: 100%; }
.play-v3 .capa-buzon .carta-copy { flex: 1 1 auto; min-width: 0; }
`;
fs.writeFileSync('W:/juegos/aqui-hay-tema/assets/css/play-v3-shell-ui.css', css);
console.log('css written', css.length);
