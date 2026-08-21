const fs = require('fs');
const css = `@import url("https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&display=swap");

/* === Fondo liso + ocultar HUD legacy sobre el mapa === */
.play-v3 .play-root { background: #fafaf8 !important; }
.play-v3 .play-root::after { display: none !important; content: none !important; }
.play-v3 .board-scroll, .play-v3 .board-fit, .play-v3 .play-stage, .play-v3 .game-map-wrap { background: #fff; }
.play-v3 .board-fit {
  box-shadow: none !important;
  background: url("../play-v3/mapa_base_rectas.png") center / 100% 100% no-repeat !important;
}
.play-v3:has(.game-shell) .mesa,
.play-v3:has(.game-shell) .play-root.pc .dock { display: none !important; pointer-events: none !important; }

/* Playtest flotante */
body.play-v3[data-lab="1"] .taller, body.play-v3[data-lab="1"] .playtest-cheats { display: none !important; }
.playtest-float { position: fixed; z-index: 90; right: 12px; bottom: 12px; }
.playtest-float-toggle {
  border: 2px solid #2c261f; background: #fff6c8; font-weight: 800; font-size: .78rem;
  padding: .45rem .65rem; border-radius: 999px; cursor: pointer; box-shadow: 3px 4px 0 rgba(44,38,31,.12);
  transform: rotate(-1deg);
}
.playtest-float-panel {
  position: absolute; right: 0; bottom: calc(100% + 8px); width: min(280px, 88vw); padding: .65rem;
  background: #fffdf6; border: 2px solid #2c261f; box-shadow: 6px 8px 0 rgba(44,38,31,.1);
  display: flex; flex-wrap: wrap; gap: .35rem;
}
.playtest-float-panel[hidden] { display: none !important; }
.playtest-float-title { width: 100%; margin: 0 0 .2rem; font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #7a7164; }
.playtest-float-panel button, .playtest-float-panel a {
  border: 2px solid #8a7a66; background: #fff; font: inherit; font-weight: 800; font-size: .72rem;
  padding: .25rem .45rem; cursor: pointer; text-decoration: none; color: inherit;
}

/* === Shell layout === */
.game-shell { display: flex; flex-direction: column; height: calc(100vh - 52px); min-height: 0; background: #fafaf8; }
body.play-v3[data-lab="1"] .game-shell { height: 100vh; }
.game-main {
  flex: 1; min-height: 0; display: grid;
  grid-template-columns: minmax(158px, 198px) minmax(0, 1fr) minmax(158px, 198px);
  gap: 6px; padding: 6px 8px; background: #fafaf8;
}
.game-left, .game-right {
  display: flex; flex-direction: column; gap: .55rem; overflow-y: auto; min-height: 0;
  background: transparent; padding: 0;
}
.game-map-wrap { min-width: 0; min-height: 0; display: flex; background: #fff; border: 1px solid #ece8e0; border-radius: 4px; }
.game-map-wrap .play-stage { flex: 1; min-height: 0; width: 100%; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* === Cabecera compacta === */
.game-top {
  display: flex; align-items: center; gap: .85rem; flex-wrap: nowrap;
  background: #fafaf8; border-bottom: 1px solid #ece8e0; padding: .35rem .75rem .4rem;
}
.brand { margin: 0; display: flex; align-items: center; gap: .35rem; transform: rotate(-1deg); flex: 0 0 auto; }
.brand-text {
  font-family: Caveat, "Segoe Script", cursive; font-size: clamp(1.55rem, 2.2vw, 1.95rem);
  font-weight: 700; line-height: 1; color: #2c261f;
}
.brand-heart {
  display: inline-block; width: 18px; height: 16px; flex: 0 0 18px; margin-left: .12rem;
  background: linear-gradient(180deg, #e56b8a, #c42b4a);
  -webkit-mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
  mask: url("../play-v3/hud/corazon.png") center / contain no-repeat;
  transform: rotate(8deg) translateY(-1px);
}
.top-meta { display: flex; align-items: flex-end; gap: .55rem; flex: 0 1 auto; margin-left: 0; }

/* Día — ticket CSS puro */
.obj-dia {
  display: flex; flex-direction: column; gap: .05rem;
  padding: .32rem .55rem .38rem; min-width: 72px;
  background: linear-gradient(175deg, #fff8c4 0%, #fff0a8 100%);
  border: 2px solid #2c261f; transform: rotate(var(--rot, -2deg));
  clip-path: polygon(1% 2%, 97% 0, 99% 96%, 3% 100%, 0 88%);
  box-shadow: 2px 2px 0 rgba(44,38,31,.09);
}
.obj-dia-num { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .72rem; letter-spacing: .04em; line-height: 1.1; }
.obj-dia-dow { font-size: .68rem; font-weight: 800; color: #5a5048; line-height: 1.1; }
.obj-dia-hora { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .82rem; line-height: 1.1; }

/* Dinero — etiqueta + moneda */
.obj-dinero {
  display: flex; align-items: center; gap: .28rem;
  padding: .22rem .42rem .24rem .32rem; transform: rotate(var(--rot, 1deg));
  background: #fff6b8; border: 2px solid #2c261f;
  clip-path: polygon(0 8%, 96% 0, 100% 92%, 6% 100%);
  box-shadow: 1px 2px 0 rgba(44,38,31,.08);
}
.moneda-icon {
  width: 16px; height: 16px; border-radius: 50%; flex: 0 0 16px;
  border: 2px solid #2c261f; background: radial-gradient(circle at 35% 30%, #fff8d0, #d4bc5c 70%);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
}
.moneda-icon::after { content: "€"; display: block; text-align: center; font-size: .48rem; font-weight: 800; line-height: 12px; color: #5a5048; }
.obj-dinero-val { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .82rem; white-space: nowrap; }

/* Vida — corazón libre, sin caja */
.obj-vida { display: flex; flex-direction: column; align-items: center; gap: .12rem; padding: 0 .15rem; }
.obj-vida-kicker {
  font-size: .52rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #6a5848;
  transform: rotate(-0.5deg);
}
.corazon-svg { display: block; overflow: visible; filter: drop-shadow(1px 1px 0 rgba(44,38,31,.08)); }
.corazon-bg { fill: #f3e8ec; }
.corazon-fill-rect { fill: #e56b8a; transition: y .35s ease, height .35s ease; }
.corazon-stroke { stroke: #2c261f; stroke-width: 2.4; stroke-linejoin: round; }

/* === Objetos laterales — SIN cards uniformes === */

/* Cotilleo — post-it rosa */
.obj-cotilleo {
  position: relative; width: 100%; padding: .55rem .65rem .65rem .7rem; margin: 0;
  border: 0; background: #fde8ef; cursor: pointer; text-align: left; color: inherit; font: inherit;
  transform: rotate(-1.2deg);
  clip-path: polygon(0 0, 96% 2%, 100% 88%, 92% 100%, 3% 97%, 0 12%);
  box-shadow: 3px 3px 0 rgba(196,43,74,.12), inset 0 -8px 0 rgba(255,255,255,.25);
}
.obj-cotilleo::after {
  content: ""; position: absolute; right: 0; bottom: 0; width: 18px; height: 18px;
  background: linear-gradient(135deg, transparent 50%, rgba(212,138,160,.35) 50%);
}
.obj-cotilleo-pin {
  position: absolute; top: -5px; left: 10px; width: 10px; height: 10px; border-radius: 50%;
  background: #c42b4a; border: 1.5px solid #2c261f; box-shadow: 1px 1px 0 rgba(44,38,31,.15);
}
.obj-cotilleo-tit {
  display: block; font-size: .62rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #8a4a58; margin-bottom: .25rem;
}
.obj-cotilleo-txt {
  margin: 0; font-family: Fraunces, Georgia, serif; font-style: italic; font-size: .8rem; line-height: 1.32; color: #2c261f;
}

/* Bloques — tres fichas escalonadas */
.obj-bloques { display: flex; flex-direction: column; gap: .35rem; padding: 0; }
.obj-bloque {
  display: flex; align-items: center; gap: .45rem; width: 100%; padding: .28rem .35rem;
  border: 0; background: transparent; cursor: default; text-align: left; font: inherit; color: inherit;
}
.obj-bloque.bloque-a { transform: rotate(-0.8deg) translateX(0); }
.obj-bloque.bloque-b { transform: rotate(0.6deg) translateX(4px); }
.obj-bloque.bloque-c { transform: rotate(-0.4deg) translateX(2px); }
.obj-bloque.is-cerrado { opacity: .72; }
.bloque-fachada {
  width: 34px; height: 30px; flex: 0 0 34px; position: relative;
  border: 2px solid #2c261f; background: #f3e6cc;
  clip-path: polygon(8% 100%, 8% 35%, 50% 8%, 92% 35%, 92% 100%);
}
.bloque-fachada::before {
  content: ""; position: absolute; left: 22%; top: 52%; width: 18%; height: 16%;
  border: 1.5px solid #2c261f; background: rgba(255,253,246,.5);
}
.bloque-fachada::after {
  content: ""; position: absolute; right: 22%; top: 52%; width: 18%; height: 16%;
  border: 1.5px solid #2c261f; background: rgba(255,253,246,.5);
}
.bloque-letra {
  position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%);
  font-size: .55rem; font-weight: 800; background: #fff6c8; border: 1.5px solid #2c261f; padding: 0 3px; line-height: 1.1;
}
.bloque-a .bloque-fachada { background: #f3d4b8; }
.bloque-b .bloque-fachada { background: #d5e0c8; }
.bloque-c .bloque-fachada { background: #e8ddd0; }
.bloque-info { display: flex; flex-direction: column; font-size: .68rem; line-height: 1.2; }
.bloque-info strong { font-weight: 800; letter-spacing: .04em; }
.bloque-info em { font-style: normal; color: #7a7164; font-weight: 700; }

/* Parejas — pestaña + fotos pegadas */
.obj-parejas { padding: 0; background: transparent; }
.obj-parejas-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .25rem; }
.obj-parejas-tab {
  display: inline-block; padding: .15rem .45rem .2rem;
  background: #fff6c8; border: 2px solid #2c261f; transform: rotate(-1.5deg);
  font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
  clip-path: polygon(0 0, 100% 8%, 96% 100%, 4% 92%);
}
.obj-pestaña {
  border: 0; background: #fffdf6; font: inherit; font-weight: 800; font-size: .62rem;
  padding: .15rem .35rem; cursor: pointer; color: #5a5048;
  border-bottom: 2px solid #2c261f; transform: rotate(1deg); text-decoration: none;
}
.obj-pestaña:disabled { opacity: .4; cursor: not-allowed; }
.obj-parejas-list { display: flex; flex-direction: column; gap: .4rem; }
.obj-pareja-fila { display: flex; flex-direction: column; gap: .12rem; padding-left: .15rem; }
.obj-pareja-fotos { display: flex; align-items: center; }
.obj-pareja-cara {
  width: 26px; height: 26px; border-radius: 50%; object-fit: cover;
  border: 2px solid #fff; box-shadow: 0 1px 2px rgba(44,38,31,.15);
  margin-right: -8px; background: #f3e6cc;
}
.obj-pareja-cara.cara-ini { display: grid; place-items: center; font-size: .65rem; font-weight: 800; }
.obj-pareja-corazon {
  width: 10px; height: 9px; flex: 0 0 10px; margin: 0 -2px; z-index: 1;
  background: #e56b8a; transform: rotate(-8deg);
  clip-path: polygon(50% 100%, 0 35%, 0 15%, 25% 0, 50% 18%, 75% 0, 100% 15%, 100% 35%);
  border: 1px solid #2c261f;
}
.obj-pareja-nombres { font-size: .68rem; line-height: 1.2; padding-left: .2rem; }
.obj-parejas-vacio { font-family: Fraunces, Georgia, serif; font-style: italic; font-size: .72rem; color: #7a7164; margin: 0; line-height: 1.3; }

/* Buzón — sobre suelto */
.obj-buzon {
  position: relative; display: flex; flex-direction: column; align-items: center;
  width: 100%; padding: .35rem 0 .15rem; border: 0; background: transparent;
  cursor: pointer; font: inherit; color: inherit;
}
.obj-buzon-badge {
  position: absolute; top: 0; right: calc(50% - 38px); z-index: 2;
  min-width: 1.1rem; padding: 0 .28rem; font-size: .65rem; font-weight: 800; line-height: 1.35;
  background: #fde8ef; border: 2px solid #c42b4a; border-radius: 999px; color: #2c261f;
}
.obj-buzon-badge:not(.is-on) { opacity: 0; pointer-events: none; }
.obj-buzon-img { display: block; width: 52px; height: auto; transform: rotate(-3deg); filter: drop-shadow(2px 3px 0 rgba(44,38,31,.1)); }
.obj-buzon-txt { font-weight: 800; font-size: .72rem; letter-spacing: .08em; margin-top: .1rem; transform: rotate(0.5deg); }
.obj-buzon-hint { font-size: .65rem; color: #7a7164; text-align: center; line-height: 1.25; max-width: 100%; margin-top: .15rem; }

/* Próximo plan — ficha de cita */
.obj-proximo {
  padding: .55rem .6rem .5rem; background: #fffdf8;
  border: 2px solid #2c261f; transform: rotate(0.4deg);
  clip-path: polygon(2% 0, 98% 3%, 96% 100%, 0 95%);
  box-shadow: 2px 3px 0 rgba(44,38,31,.07);
}
.obj-proximo-tit { display: block; font-size: .6rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: #6a5848; margin-bottom: .3rem; }
.obj-proximo-body { font-size: .75rem; line-height: 1.3; }
.obj-proximo-vacio { font-family: Fraunces, Georgia, serif; font-style: italic; margin: 0; color: #5a5048; font-size: .78rem; }
.prox-faces img, .prox-faces .cara-ini { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; margin-right: .2rem; }
.prox-nombres { margin: .2rem 0 .1rem; font-size: .78rem; }

/* Nuevo plan — pestaña rosa */
.obj-nuevo-plan {
  border: 2px solid #2c261f; background: #fde8ef; color: #2c261f;
  font-family: Fraunces, Georgia, serif; font-style: italic; font-weight: 700; font-size: .8rem;
  padding: .48rem .55rem; cursor: pointer; width: 100%; text-align: center;
  transform: rotate(-2deg);
  clip-path: polygon(1% 10%, 99% 0, 97% 90%, 3% 100%);
  box-shadow: 2px 3px 0 rgba(196,43,74,.15);
}
.obj-nuevo-plan:hover { background: #f9d4e0; }

/* El pueblo — ficha con siluetas */
.obj-pueblo {
  display: flex; flex-direction: column; align-items: center; gap: .2rem;
  width: 100%; padding: .4rem 0 .35rem; border: 0; background: transparent;
  cursor: pointer; font: inherit; color: inherit;
}
.obj-pueblo-siluetas { width: 36px; height: 22px; position: relative; }
.obj-pueblo-siluetas::before, .obj-pueblo-siluetas::after {
  content: ""; position: absolute; bottom: 0; width: 12px; height: 12px;
  border-radius: 50% 50% 42% 42%; border: 2px solid #2c261f; background: #f3d4b8;
}
.obj-pueblo-siluetas::before { left: 2px; }
.obj-pueblo-siluetas::after { right: 2px; background: #fde8ef; }
.obj-pueblo-siluetas { background: radial-gradient(circle at 50% 80%, #2c261f 0 2px, transparent 2px); background-size: 100% 100%; background-repeat: no-repeat; }
.obj-pueblo-txt { font-weight: 800; font-size: .74rem; letter-spacing: .07em; transform: rotate(-0.5deg); }

/* Resumen mínimo */
.obj-resumen { padding: .3rem .4rem; background: #fff6c8; border: 1.5px dashed #c9b59a; font-size: .72rem; transform: rotate(0.3deg); }
.stat-row { display: flex; justify-content: space-between; padding: .1rem 0; }

/* === Edificios EN CONSTRUCCIÓN (no B/N) === */
.play-v3 .edificios-layer .edif.is-on { filter: none; opacity: 1; }
.play-v3 .edificios-layer .edif.is-off {
  opacity: 1 !important;
  filter: brightness(1.42) saturate(0.38) contrast(0.82) sepia(0.12) !important;
}
.play-v3 .edificios-layer .edif.is-off::before {
  content: "EN OBRAS" !important;
  position: absolute; left: 50%; top: 6px; transform: translateX(-50%) rotate(-2deg);
  font: 800 7px/1.15 Nunito, sans-serif; letter-spacing: .08em;
  color: #2c261f; background: #fff6c8; border: 2px solid #2c261f;
  padding: 2px 6px; z-index: 4; pointer-events: none;
  box-shadow: 1px 2px 0 rgba(44,38,31,.12);
}
.play-v3 .edificios-layer .edif.is-off::after {
  content: "" !important;
  position: absolute; inset: 0; pointer-events: none; z-index: 3;
  background:
    linear-gradient(180deg, rgba(255,252,245,.52) 0%, rgba(255,252,245,.38) 55%, rgba(230,220,200,.45) 100%),
    repeating-linear-gradient(90deg, rgba(180,160,130,.22) 0 6px, transparent 6px 14px),
    repeating-linear-gradient(0deg, rgba(140,120,90,.08) 0 18px, transparent 18px 36px);
  border-bottom: 14px solid rgba(160,140,110,.35);
  box-sizing: border-box;
  mask-image: linear-gradient(180deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.95) 100%);
}

/* Buzón modal */
.play-v3 .play-root.pc .capa-buzon { width: min(520px, 94vw) !important; max-width: 520px !important; }
.play-v3 .capa-buzon [data-buzon-list] { max-height: calc(88vh - 120px); overflow-y: auto; scrollbar-width: thin; scrollbar-color: #c9b59a transparent; }
.play-v3 .capa-buzon .carta-msg { display: block !important; grid-template-columns: unset !important; }
.play-v3 .capa-buzon .carta-inner { display: flex !important; gap: .75rem; min-width: 0; width: 100%; }
.play-v3 .capa-buzon .carta-copy { flex: 1 1 auto; min-width: 0; }
`;
fs.writeFileSync('W:/juegos/aqui-hay-tema/assets/css/play-v3-shell-ui.css', css);
console.log('css', css.length);
