const fs = require('fs');
const css = `
/* === Playtest flotante (lab) === */
body.play-v3[data-lab="1"] .taller,
body.play-v3[data-lab="1"] .playtest-cheats { display: none !important; }
.playtest-float {
  position: fixed; z-index: 90; right: 12px; bottom: 12px;
  font-family: Nunito, "Segoe UI", sans-serif;
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

.game-shell { background: #fafaf8; }
.game-main { background: #fafaf8; }
.game-left, .game-right { background: transparent; }
.ui-card.paper, .buzon-compact, .pueblo-card, .cotilleo-card {
  border: 2px solid #2c261f; border-radius: 10px;
  box-shadow: 3px 4px 0 rgba(44,38,31,.08);
  text-align: left; cursor: default;
}
.ui-card.paper { background: #f7f0de; padding: .65rem .7rem; transform: rotate(var(--rot, -0.3deg)); }
.cotilleo-card, .pueblo-card, .buzon-compact { width: 100%; font: inherit; color: inherit; transform: rotate(var(--rot, 0.4deg)); }
.cotilleo-card { position: relative; padding-right: 2rem; cursor: pointer; }
.cotilleo-card:hover, .pueblo-card:hover, .buzon-compact:hover { filter: brightness(1.02); }
.card-flecha { position: absolute; right: .55rem; bottom: .45rem; font-size: 1.1rem; opacity: .85; }
.ui-card h3, .cotilleo-card h3 {
  margin: 0 0 .35rem; font-size: .68rem; font-weight: 800;
  letter-spacing: .08em; text-transform: uppercase; color: #6a5848;
}
.cotilleo-teaser { font-family: Fraunces, Georgia, serif; font-style: italic; font-size: .86rem; line-height: 1.35; margin: 0; color: #2c261f; }
.muted-soft { color: #7a7164; font-size: .78rem; margin: 0; line-height: 1.35; }
.game-top { background: #fafaf8; border-bottom: 2px solid #ebe6dc; padding: .5rem .85rem; }
.top-meta { margin-left: auto; display: flex; flex-wrap: wrap; gap: .45rem; }
.nota {
  display: inline-block; min-width: 108px; padding: .35rem .55rem .4rem;
  border: 2px solid #2c261f; border-radius: 6px; box-shadow: 2px 3px 0 rgba(44,38,31,.1);
  transform: rotate(var(--rot, 0deg));
}
.nota-dia, .nota-dinero { background: #fff6c8; }
.nota-vida { background: #fde8ef; border-color: #d48aa0; }
.nota-kicker { display: block; font-size: .58rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #6a5848; margin-bottom: .12rem; }
.nota-body { margin: 0; font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .82rem; line-height: 1.25; }
.vida-row { display: flex; align-items: center; gap: .35rem; }
.corazon-hud { display: inline-block; width: 52px; height: 12px; border: 2px solid #2c261f; background: #fff; border-radius: 999px; overflow: hidden; }
.corazon-hud-fill { display: block; height: 100%; width: 0%; background: linear-gradient(90deg, #e56b8a, #c42b4a); }
.bloques-grid { display: flex; flex-direction: column; gap: .35rem; }
.bloque-mini { display: flex; align-items: center; gap: .45rem; border: 2px solid #2c261f; background: #fffdf6; border-radius: 8px; padding: .35rem .45rem; width: 100%; box-shadow: 2px 2px 0 rgba(44,38,31,.06); }
.bloque-mini.is-cerrado { opacity: .72; filter: saturate(.7); }
.bloque-mini-casa { width: 28px; height: 24px; flex: 0 0 28px; position: relative; background: #e8d5b3; border: 2px solid #2c261f; border-radius: 2px 2px 0 0; }
.bloque-mini-casa::before { content: ""; position: absolute; left: -3px; right: -3px; top: -10px; height: 12px; background: #c45; clip-path: polygon(50% 0, 100% 100%, 0 100%); border: 2px solid #2c261f; border-bottom: 0; }
.bloque-mini-txt { font-size: .72rem; font-weight: 800; line-height: 1.25; }
.bloque-mini-txt em { font-style: normal; color: #7a7164; font-weight: 700; }
.buzon-compact { display: block; padding: .55rem .65rem; background: #f7f0de; border: 2px solid #2c261f; border-radius: 10px; cursor: pointer; box-shadow: 3px 4px 0 rgba(44,38,31,.08); transform: rotate(-0.6deg); }
.buzon-compact-top { display: flex; justify-content: space-between; align-items: center; }
.buzon-tit { font-weight: 800; font-size: .82rem; letter-spacing: .04em; }
.buzon-badge { min-width: 1.4rem; text-align: center; font-weight: 800; font-size: .75rem; background: #fff; border: 2px solid #2c261f; border-radius: 999px; padding: 0 .35rem; }
.buzon-badge.is-on { background: #fde8ef; border-color: #c42b4a; }
.buzon-hint { display: block; margin-top: .3rem; font-size: .72rem; color: #6a5848; line-height: 1.3; }
.pueblo-card { display: flex; flex-direction: column; align-items: flex-start; gap: .15rem; padding: .65rem .7rem; background: #fffdf8; cursor: pointer; }
.pueblo-icon { font-size: 1.35rem; }
.pueblo-tit { font-weight: 800; font-size: .88rem; letter-spacing: .06em; }
.pueblo-sub { font-size: .72rem; color: #7a7164; }
.btn-papel-sm { margin-top: .4rem; border: 2px solid #8a7a66; background: #fffdf6; font: inherit; font-weight: 800; font-size: .68rem; padding: .22rem .45rem; cursor: pointer; border-radius: 4px; box-shadow: 2px 2px 0 rgba(44,38,31,.08); text-decoration: none; color: inherit; }
.btn-papel-sm:disabled { opacity: .45; cursor: not-allowed; }
.btn-nuevo-plan { border: 2px solid #c42b4a; background: #e56b8a; color: #fff; font-weight: 800; font-size: .78rem; padding: .5rem .65rem; border-radius: 8px; cursor: pointer; box-shadow: 3px 3px 0 rgba(44,38,31,.12); transform: rotate(-0.5deg); width: 100%; }
.parejas-panel { transform: rotate(0.2deg); }
.parejas-head { display: flex; justify-content: space-between; align-items: center; gap: .35rem; }
.parejas-head h3 { margin: 0; }
.parejas-compact { display: flex; flex-direction: column; gap: .35rem; margin-top: .35rem; max-height: 140px; overflow-y: auto; }
.pareja-row { display: flex; align-items: center; gap: .4rem; font-size: .72rem; }
.pareja-faces img, .pareja-faces .cara-ini { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; border: 1.5px solid #fff; box-shadow: 0 1px 1px rgba(0,0,0,.12); margin-right: -6px; }
.prox-faces img, .prox-faces .cara-ini { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1); margin-right: .25rem; }
.play-v3 .edificios-layer .edif.is-off { opacity: 1; filter: saturate(.45) brightness(.95) contrast(.95); }
.play-v3 .edificios-layer .edif.is-off::before {
  content: "EN OBRAS"; position: absolute; left: 50%; top: 8px; transform: translateX(-50%) rotate(-2deg);
  font: 800 9px/1 Nunito, sans-serif; letter-spacing: .06em; color: #2c261f; background: #fff6c8;
  border: 2px solid #2c261f; padding: 2px 6px; border-radius: 3px; pointer-events: none; z-index: 2;
}
.play-v3 .edificios-layer .edif.is-off::after {
  content: ""; position: absolute; inset: 0; pointer-events: none;
  background: repeating-linear-gradient(-45deg, transparent 0 8px, rgba(196,43,74,.12) 8px 16px);
  border: 2px dashed rgba(122,113,100,.55); box-sizing: border-box;
}
.play-v3 .play-root.pc .capa-buzon { width: min(520px, 94vw) !important; max-width: 520px !important; }
.play-v3 .capa-buzon [data-buzon-list] { max-height: calc(88vh - 120px); overflow-y: auto; scrollbar-width: thin; scrollbar-color: #c9b59a transparent; }
.play-v3 .capa-buzon [data-buzon-list]::-webkit-scrollbar { width: 6px; }
.play-v3 .capa-buzon [data-buzon-list]::-webkit-scrollbar-thumb { background: #c9b59a; border-radius: 999px; }
.play-v3 .capa-buzon .carta-msg { display: block !important; grid-template-columns: unset !important; padding: .85rem .65rem !important; }
.play-v3 .capa-buzon .carta-inner { display: flex !important; gap: .75rem; align-items: flex-start; min-width: 0; width: 100%; }
.play-v3 .capa-buzon .carta-avatar { flex: 0 0 48px; width: 48px; height: 48px; border-radius: 50%; border: 2px solid #2c261f; object-fit: cover; background: #fff; display: grid; place-items: center; font-weight: 800; }
.play-v3 .capa-buzon .carta-copy { flex: 1 1 auto; min-width: 0; max-width: none; }
.play-v3 .capa-buzon .carta-msg .cuerpo { white-space: normal; overflow-wrap: anywhere; max-width: none; width: auto; }
.game-map-wrap { background: #fff; border: 2px solid #ebe6dc; border-radius: 12px; }
.game-shell { display: flex; flex-direction: column; height: calc(100vh - 52px); min-height: 520px; }
body.play-v3[data-lab="1"] .game-shell { height: 100vh; min-height: 0; }
.game-main { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(168px, 210px) minmax(0, 1fr) minmax(168px, 210px); gap: 8px; padding: 8px; }
.game-left, .game-right { display: flex; flex-direction: column; gap: .45rem; overflow-y: auto; min-height: 0; }
.game-map-wrap { min-width: 0; min-height: 0; position: relative; display: flex; }
.game-map-wrap .play-stage { flex: 1; min-height: 0; width: 100%; }
.game-shell .mesa .dia, .game-shell .mesa .dinero, .game-shell .mesa .corazon { display: none !important; }
.stat-row { display: flex; justify-content: space-between; font-size: .78rem; padding: .15rem 0; }
`;
fs.writeFileSync('W:/juegos/aqui-hay-tema/assets/css/play-v3-shell-ui.css', css.trim());
console.log('written', css.length);
