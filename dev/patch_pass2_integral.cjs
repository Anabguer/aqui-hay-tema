'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function read(p) { return fs.readFileSync(path.join(root, p), 'utf8'); }
function write(p, c) { fs.writeFileSync(path.join(root, p), c, 'utf8'); }

function upsertMarker(file, marker, block) {
  let css = read(file);
  const re = new RegExp('/\\* ' + marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[\\s\\S]*?(?=\\n/\\*|$)', 'm');
  const trimmed = block.trim() + '\n';
  if (re.test(css)) css = css.replace(re, trimmed);
  else css = css.trimEnd() + '\n\n' + trimmed;
  write(file, css);
}

let shellUi = read('assets/css/play-v3-shell-ui.css');
const shellUiStrip = [
  /\.inicio-desktop \.obj-buzon\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-buzon-badge\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-buzon-img\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-buzon-txt\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-cotilleo\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-cotilleo-tit\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-cotilleo-txt\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-proximo\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-proximo-tit\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-proximo-body\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-proximo-vacio\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-nuevo-plan\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-nuevo-plan-ico\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-nuevo-plan-txt\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-vecinos-resumen:not\(\.celestine-nota\)\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-vecinos-resumen\.celestine-nota\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-vecinos-stats\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-vecinos-tit\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.celestine-nota \.obj-vecinos-tit\s*\{[\s\S]*?\n\}\n\n/,
  /\.play-v3 \.aht-screen\[data-aht-screen="buzon"\]:not\(\)\s*\{[\s\S]*?\n\}\n\n/,
  /\.play-v3 \.aht-screen\[data-aht-screen="buzon"\] > \.cerrar\s*\{[\s\S]*?\n\}\n\n/,
  /\.play-v3 \.aht-screen\[data-aht-screen="buzon"\] h2\s*\{[\s\S]*?\n\}\n\n/,
  /\.inicio-desktop \.obj-vecinos-resumen, \.inicio-desktop \.obj-cotilleo[\s\S]*?\n\}\n\n/,
];
for (const re of shellUiStrip) shellUi = shellUi.replace(re, '');
if (!shellUi.includes('INICIO-SHELL-UI-MIGRATED-20260906')) {
  shellUi += '\n/* INICIO-SHELL-UI-MIGRATED-20260906 — reglas Inicio/desktop y shell buzon retiradas; autoridad: inicio-desktop.css + v4/screen-frame.css */\n';
}
write('assets/css/play-v3-shell-ui.css', shellUi);

let deskShell = read('assets/css/play-v3-desktop-shell.css');
if (!deskShell.includes('INICIO-DESKTOP-SHELL-QUARANTINE-20260906')) {
  deskShell = deskShell.replace(
    /@media \(min-width: 769px\) \{\n  \.play-v3 \.game-main \{/,
    '@media (min-width: 769px) {\n  .play-v3:not(:has(.inicio-desktop.is-inicio-view-active)) .game-main {'
  );
  deskShell = deskShell.replace(
    /\.play-v3 \.celestine-nota\.obj-vecinos-resumen \{/g,
    '.play-v3:not(:has(.inicio-desktop.is-inicio-view-active)) .celestine-nota.obj-vecinos-resumen {'
  );
  deskShell = deskShell.replace(
    /\.play-v3 \.celestine-nota\.obj-vecinos-resumen::/g,
    '.play-v3:not(:has(.inicio-desktop.is-inicio-view-active)) .celestine-nota.obj-vecinos-resumen::'
  );
  deskShell = deskShell.replace(
    /\.play-v3 \.celestine-nota /g,
    '.play-v3:not(:has(.inicio-desktop.is-inicio-view-active)) .celestine-nota '
  );
  deskShell += '\n/* INICIO-DESKTOP-SHELL-QUARANTINE-20260906 — celestine acotado fuera de Inicio canonico */\n';
}
write('assets/css/play-v3-desktop-shell.css', deskShell);

upsertMarker('assets/css/v4/screen-frame.css', 'AHT-VELO-CANON-20260906', `
/* AHT-VELO-CANON-20260906 — unico velo activo: .aht-velo (screen-manager.js) */
.play-v3 .play-root > .velo {
  display: none;
  opacity: 0;
  pointer-events: none;
  visibility: hidden;
}

.play-v3 .play-root[data-capa]:not([data-capa=""]) > .velo,
.play-v3 .play-root[data-consulta]:not([data-consulta=""]) > .velo {
  display: none;
  opacity: 0;
  pointer-events: none;
  visibility: hidden;
}
`);

upsertMarker('assets/css/inicio/inicio-desktop.css', 'INICIO-VISUAL-PASS3-20260906', `
/* INICIO-VISUAL-PASS3-20260906 — desktop derecha + misiones sidebar */
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 0 2px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #7a7164;
    margin: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .proxplanes-movil .pp-mov-card {
    background: #f8f6fa;
    border: 1.5px solid rgba(111, 95, 154, .22);
    border-radius: 14px;
    padding: 10px 12px;
    box-shadow: 0 2px 8px rgba(111, 95, 154, .08);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .obj-nuevo-plan.obj-nuevo-plan-horiz {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 44px;
    margin-top: 4px;
    padding: 10px 14px;
    background: #fff;
    border: 1.5px solid #6f5f9a;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(111, 95, 154, .1);
    cursor: pointer;
    font: inherit;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .obj-nuevo-plan-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .68rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo.shell-grupo-parejas {
    padding: 12px 14px;
    background: #fff9fa;
    border: 1.5px solid rgba(232, 160, 180, .38);
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(232, 136, 168, .1);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-parejas .zona-tit-parejas {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1.5px solid rgba(232, 160, 180, .28);
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #2c261f;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .obj-parejas-vacio,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-parejas .obj-parejas-list > .muted {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .78rem;
    font-weight: 600;
    color: #7a7164;
    text-align: center;
    padding: 8px 4px;
  }
}
`);

let mob = read('assets/css/inicio/inicio-mobile.css');
if (!mob.includes('INICIO-VISUAL-PASS3-20260906')) {
  const pass3Mob = `
  /* INICIO-VISUAL-PASS3-20260906 — movil pulido PNG 01 */
  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin: 0;
    padding: 0;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-buzon,
  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .celestine-nota.obj-vecinos-resumen,
  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-nuevo-plan {
    position: relative;
    min-height: 78px;
    border-radius: 14px;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-nuevo-plan {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    background: linear-gradient(180deg, #f06a8a 0%, #e85a78 100%);
    border: 2px dashed rgba(255, 255, 255, .85);
    box-shadow: 0 2px 8px rgba(196, 43, 74, .2);
    color: #fff;
    cursor: pointer;
    font: inherit;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-nuevo-plan-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .68rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #fff;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-duo {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin: 0;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-card--mis {
    background: linear-gradient(180deg, #e8f5e4 0%, #d9ecd4 100%);
    border-color: rgba(109, 155, 118, .42);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-card--par {
    background: linear-gradient(180deg, #fdeef3 0%, #f8e0ea 100%);
    border-color: rgba(232, 120, 152, .42);
  }
`;
  mob = mob.replace(/\n\}\s*$/, pass3Mob + '\n}\n');
}
write('assets/css/inicio/inicio-mobile.css', mob);

upsertMarker('assets/css/v4/bodies/misc-screens.css', 'MISIONES-VISUAL-PNG-20260906', `
/* MISIONES-VISUAL-PNG-20260906 — filas tipo pill (ref 10_MISIONES) */
.play-v3 .aht-screen[data-aht-screen="misiones"] .mis-body > .pg-card,
.play-v3 .aht-screen[data-aht-screen="misiones"] .misiones-body > .mis-card-wrap,
.play-v3 .aht-screen[data-aht-screen="misiones"] .mis-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 44px;
  padding: 8px 12px;
  margin: 0 0 8px;
  border: 1.5px solid rgba(90, 70, 50, .35);
  border-radius: 999px;
  background: #fff;
  box-shadow: 0 1px 4px rgba(70, 55, 40, .06);
}

.play-v3 .aht-screen[data-aht-screen="misiones"] .mis-item-cumplida {
  background: #e8f3e6;
  border-color: rgba(74, 122, 82, .45);
}

.play-v3 .aht-screen[data-aht-screen="misiones"] .mis-item {
  border-bottom: none;
}

.play-v3 .aht-screen[data-aht-screen="misiones"] .mis-txt {
  flex: 1 1 auto;
  font-family: var(--ds-font-ui, Nunito, sans-serif);
  font-size: .82rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--ds-ink, #2a2218);
}
`);

upsertMarker('assets/css/v4/screens.css', 'INVENTARIO-VISUAL-PNG-20260906', `
/* INVENTARIO-VISUAL-PNG-20260906 */
.aht-screen[data-aht-screen="inventario"] .aht-frame-body {
  background: #f4f2f6;
  border-radius: 12px;
  margin: 0 12px 12px;
  padding: 12px;
}

.aht-screen[data-aht-screen="inventario"] .inv-lista .inv-vacio,
.aht-screen[data-aht-screen="inventario"] .inv-lista > .muted {
  display: block;
  padding: 24px 16px;
  border: 2px dashed rgba(120, 96, 72, .35);
  border-radius: 14px;
  background: #faf8fc;
  font-family: var(--ds-font-hand, Caveat, cursive);
  font-size: 1.15rem;
  font-weight: 600;
  line-height: 1.35;
  color: #6a5848;
  text-align: center;
}
`);

let misc = read('assets/css/v4/bodies/misc-screens.css');
const miscStrips = [
  /\.play-v3 \.play-root\.pc\[data-capa="vida_pueblo"\][\s\S]*?text-align: center;\n\}\n\n/,
  /\.play-v3 \.play-root\[data-capa="vida_pueblo"\][\s\S]*?overflow: hidden;\n\}\n\n/,
  /\.play-v3 \.play-root\.pc\[data-capa="agenda"\][\s\S]*?box-shadow:[\s\S]*?;\n\}\n\n/,
  /\.play-v3 \.play-root\.pc\[data-capa="agenda"\][\s\S]*?::before[\s\S]*?pointer-events: none;\n\}\n\n/,
  /\.play-v3 \.play-root\.pc\[data-capa="agenda"\][\s\S]*?::after[\s\S]*?display: none;\n\}\n\n/,
  /\.play-v3 \.play-root\.phone\[data-capa="agenda"\][\s\S]*?::before[\s\S]*?pointer-events: none;\n\}\n\n/,
  /\.play-v3 \.play-root\.phone\[data-capa="agenda"\][\s\S]*?::after[\s\S]*?display: none;\n\}\n\n/,
];
for (const re of miscStrips) misc = misc.replace(re, '');
if (!misc.includes('MISC-SHELL-MIGRATED-20260906')) {
  misc += '\n/* MISC-SHELL-MIGRATED-20260906 — shell vida_pueblo/agenda retirado; autoridad v4/screens.css + screen-frame.css */\n';
}
write('assets/css/v4/bodies/misc-screens.css', misc);

console.log('patch_pass2_integral OK');
