const fs = require('fs');

// --- CSS: reemplazar bloque modal vecinos ---
const cssPath = 'assets/css/play-v3-bloques-residencias.css';
let css = fs.readFileSync(cssPath, 'utf8');
const cssStart = css.indexOf('/* === Vecinos: modal centrado');
if (cssStart < 0) {
  console.error('CSS block not found');
  process.exit(1);
}
const newCss = `/* === Vecinos: modal centrado (rejilla de caras) === */
.play-v3 .velo {
  position: absolute;
  inset: 0;
  z-index: 10;
  background: rgba(44, 38, 31, .28);
  opacity: 0;
  pointer-events: none;
  transition: opacity .25s ease;
}

.play-v3 .play-root[data-capa="vecinos"] .velo {
  opacity: 1;
  pointer-events: auto;
}

.play-v3 .play-root.pc[data-capa="vecinos"] .capa-vecinos {
  position: absolute;
  left: 50% !important;
  top: 50% !important;
  right: auto !important;
  bottom: auto !important;
  width: min(520px, 90vw) !important;
  max-width: min(520px, 90vw) !important;
  max-height: min(68vh, 520px) !important;
  transform: translate(-50%, -50%) !important;
  z-index: 12;
  padding: .82rem .95rem .72rem !important;
  border-radius: 2px 8px 5px 4px !important;
  border: 1.5px solid rgba(44, 38, 31, .14) !important;
  box-shadow:
    2px 4px 14px rgba(40, 28, 16, .14),
    inset 0 0 0 1px rgba(180, 150, 110, .28) !important;
  background-color: #fdf9f4 !important;
  background-image:
    linear-gradient(180deg, rgba(255, 253, 249, .88) 0%, rgba(255, 253, 249, .58) 100%),
    url("../play-v3/capas/libreta_hoja.png") !important;
  background-position: center, center !important;
  background-size: cover, cover !important;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: .45rem;
}

.play-v3 .play-root.pc[data-capa="vecinos"] .capa-vecinos::before,
.play-v3 .play-root.pc[data-capa="vecinos"] .capa-vecinos::after {
  display: none !important;
}

.play-v3 .capa-vecinos .vecinos-pin {
  position: absolute;
  top: -6px;
  width: 18px;
  height: 18px;
  background: url("../play-v3/capas/chincheta.png") center / contain no-repeat;
  pointer-events: none;
  z-index: 3;
}

.play-v3 .capa-vecinos .vecinos-pin-l { left: 16px; transform: rotate(-8deg); }
.play-v3 .capa-vecinos .vecinos-pin-r { right: 16px; transform: rotate(10deg); }

.play-v3 .capa-vecinos .libreta-kicker,
.play-v3 .capa-vecinos .mini { display: none !important; }

.play-v3 .capa-vecinos .vecinos-cerrar {
  position: absolute;
  top: .35rem;
  right: .45rem;
  z-index: 4;
  width: 1.75rem;
  height: 1.75rem;
  border: 0;
  background: transparent;
  font-size: 1.25rem;
  line-height: 1;
  font-weight: 700;
  color: #5a5048;
  cursor: pointer;
  padding: 0;
  box-shadow: none;
}

.play-v3 .capa-vecinos .vecinos-cerrar:hover { color: #2c261f; }

.play-v3 .capa-vecinos .capa-cerrar-pestaña { display: none !important; }

.play-v3 .capa-vecinos .vecinos-cab {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: .5rem;
  padding: .15rem 1.65rem .2rem .15rem;
  margin-bottom: .05rem;
}

.play-v3 .capa-vecinos .vecinos-cab h2 {
  margin: 0;
  font-family: Caveat, cursive;
  font-size: 1.28rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: #2c261f;
  line-height: 1.05;
}

.play-v3 .capa-vecinos .vecinos-cuenta {
  font-family: Caveat, cursive;
  font-size: .92rem;
  font-weight: 700;
  color: var(--pink-deep);
  white-space: nowrap;
}

.play-v3 .capa-vecinos .vec-busca-tira {
  margin-bottom: .25rem;
}

.play-v3 .capa-vecinos .vec-busca-wrap {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .34rem .58rem;
  border: 1.5px solid rgba(140, 110, 80, .32);
  border-radius: 999px;
  background: rgba(255, 253, 246, .9);
}

.play-v3 .capa-vecinos .vec-busca-ico {
  font-size: .92rem;
  line-height: 1;
  color: #8a7a66;
  opacity: .85;
}

.play-v3 .capa-vecinos .vec-busca-inp {
  flex: 1;
  min-width: 0;
  border: 0;
  background: transparent;
  font: inherit;
  font-size: .82rem;
  color: #2c261f;
  outline: none;
}

.play-v3 .capa-vecinos .vec-busca-inp::placeholder { color: #9a8a78; }

.play-v3 .play-root.pc[data-capa="vecinos"] .capa-vecinos > [data-vecinos-list] {
  flex: 1 1 auto;
  min-height: 0;
  max-height: none;
  overflow-x: clip;
  overflow-y: auto;
}

.play-v3 .play-root.pc[data-capa="vecinos"] .vecinos-grid,
.play-v3 .play-root.pc[data-capa="vecinos"] [data-vecinos-list].vecinos-grid {
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: .45rem .35rem;
  padding-bottom: .15rem;
  max-height: calc(68vh - 168px);
}

.play-v3 .capa-vecinos .vecino-celda {
  border: 0;
  background: transparent;
  transform: none;
  padding: .2rem .1rem .3rem;
  box-shadow: none;
}

.play-v3 .capa-vecinos .vecino-celda:hover,
.play-v3 .capa-vecinos .vecino-celda:focus-visible {
  background: rgba(255, 246, 220, .55);
  outline: none;
}

.play-v3 .capa-vecinos .vecino-celda:hover .vecino-cara,
.play-v3 .capa-vecinos .vecino-celda:focus-visible .vecino-cara {
  border-color: var(--pink-deep);
  box-shadow: 0 0 0 2px rgba(243, 177, 195, .65);
}

.play-v3 .capa-vecinos .vecino-cara {
  width: 3.15rem;
  height: 3.15rem;
  border-width: 2px;
  margin-bottom: .28rem;
}

.play-v3 .capa-vecinos .vecino-nom {
  font-family: Nunito, "Segoe UI", sans-serif;
  font-size: .68rem;
  font-weight: 800;
  line-height: 1.2;
}

.play-v3 .capa-vecinos .vecinos-pie {
  margin: .05rem 0 0;
  text-align: center;
  font-family: Caveat, cursive;
  font-size: .78rem;
  font-weight: 700;
  font-style: normal;
  color: #6a5848;
  flex-shrink: 0;
}

@media (max-width: 560px) {
  .play-v3 .play-root.pc[data-capa="vecinos"] .vecinos-grid,
  .play-v3 .play-root.pc[data-capa="vecinos"] [data-vecinos-list].vecinos-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 520px) {
  .play-v3 .play-root.phone[data-capa="vecinos"] .capa-vecinos {
    left: 8px !important;
    right: 8px !important;
    bottom: 74px !important;
    max-height: 72% !important;
    transform: none !important;
    width: auto !important;
    padding: .85rem .75rem .7rem !important;
  }

  .play-v3 .play-root.phone[data-capa="vecinos"] .vecinos-grid,
  .play-v3 .play-root.phone[data-capa="vecinos"] [data-vecinos-list].vecinos-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    max-height: calc(72vh - 150px);
  }
}
`;
css = css.slice(0, cssStart) + newCss;
fs.writeFileSync(cssPath, css, 'utf8');
console.log('CSS ok');

// --- JS: quitar filtro nuevos ---
const jsPath = 'assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

js = js.replace(/\n  let vecFiltroActivo = 'todos';\n/, '\n');
js = js.replace(/\n  function diaPuebloInsp\(\) \{[\s\S]*?\n  \}\n\n  function esVecinoNuevo\(id\) \{[\s\S]*?\n  \}\n\n/, '\n');

js = js.replace(
  "      if (vecFiltroActivo === 'nuevos' && !esVecinoNuevo(id)) return false;\n",
  ''
);
js = js.replace(
  "(filtroTxt || vecFiltroActivo === 'nuevos' ? 'Nadie con ese filtro.' : 'Todav",
  "(filtroTxt ? 'Nadie con ese nombre.' : 'Todav"
);

const filtBlock = /\n  \$\$\('\[data-vec-filtro\]'\)\.forEach\(function \(btn\) \{[\s\S]*?\n  \}\);\n\n/;
js = js.replace(filtBlock, '\n');

fs.writeFileSync(jsPath, js, 'utf8');
console.log('JS ok', !js.includes('vecFiltroActivo'), !js.includes('esVecinoNuevo'));
