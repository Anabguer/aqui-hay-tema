'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function norm(s) {
  return s.replace(/\r\n/g, '\n');
}

const deskPath = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let desk = norm(fs.readFileSync(deskPath, 'utf8'));

const pass3Start = '/* INICIO-VISUAL-PASS3-20260906 — desktop derecha + misiones sidebar */';
const canonStart = '/* INICIO-PLANES-BLOQUE-CANON-20260906 — desktop derecha + misiones sidebar */';
const wrongCanonStart = '/* INICIO-VISUAL-PLANES-BLOQUE-CANON-20260906-20260906 — desktop derecha + misiones sidebar */';
const pass4Start = '/* INICIO-VISUAL-PASS4-20260906 — remate desktop ref 02 */';


if (/INICIO-PLANES-BLOQUE-CANON-20260906/.test(desk) && !/INICIO-VISUAL-PASS3-20260906/.test(desk)) {
  // ya aplicado
} else if (/INICIO-VISUAL-PLANES-BLOQUE-CANON-20260906-20260906/.test(desk)) {
  desk = desk.replace('INICIO-VISUAL-PLANES-BLOQUE-CANON-20260906-20260906', 'INICIO-PLANES-BLOQUE-CANON-20260906');
} else {
const i3 = desk.indexOf(pass3Start);
const i4 = desk.indexOf(pass4Start);
if (i3 < 0 || i4 < 0 || i4 <= i3) {
  console.error('patch_planes_bloque_desktop: marcadores PASS3/PASS4 no encontrados');
  process.exit(1);
}

const canonBlock = `${canonStart}
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 14px 12px;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: none;
    filter: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin: 0;
    padding: 0 2px 10px;
    border-bottom: 1px solid rgba(111, 95, 154, .18);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
    margin: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-badges {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 600;
    font-size: .76rem;
    line-height: 1.2;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-badge-ico {
    flex: 0 0 auto;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta > .shell-grupo,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .shell-grupo-planes {
    background: transparent;
    border: none;
    box-shadow: none;
    margin: 0;
    padding: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil:not(.is-on) {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil.is-on {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1.5px dashed rgba(111, 95, 154, .24);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .inicio-planes-agenda {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    border: 1.5px dashed rgba(111, 95, 154, .32);
    border-radius: 12px;
    background: transparent;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-cab {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
    padding: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-ico {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-ico {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-ico {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-tit,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-tit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .78rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-rule,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-ver {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-tit .plan-seccion-cnt,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-tit .plan-seccion-cnt {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 999px;
    background: #6f5f9a;
    color: #fff;
    font-size: .72rem;
    font-weight: 800;
    line-height: 1;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil.is-on {
    border: none;
    border-radius: 0;
    box-shadow: none;
    background: transparent;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil .pp-mov-card,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil .enc-mov-card {
    background: #f3f0f8;
    border: 1px solid rgba(111, 95, 154, .16);
    border-radius: 12px;
    box-shadow: none;
    padding: 10px 12px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    min-height: 42px;
    margin: 4px 0 0;
    padding: 10px 14px;
    background: #fff;
    border: 1.5px solid #6f5f9a;
    border-radius: 12px;
    box-shadow: none;
    cursor: pointer;
    font: inherit;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt::before {
    content: "+ ";
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt::after {
    content: " +";
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo.shell-grupo-parejas {
    padding: 12px 14px;
    background: #fff9fa;
    border: 1.5px solid rgba(232, 160, 180, .38);
    border-radius: 16px;
    box-shadow: none;
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
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
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

`;

  desk = desk.slice(0, i3) + canonBlock + '\n' + desk.slice(i4);
}
fs.writeFileSync(deskPath, desk, 'utf8');

const cromaPath = path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css');
let croma = norm(fs.readFileSync(cromaPath, 'utf8'));
croma = croma.replace(
  /\/\* INICIO-PLANES-BLOQUE-DESKTOP-v107[\s\S]*?@media \(min-width: 769px\) \{[\s\S]*?\n\}\n/,
  '/* INICIO-PLANES-BLOQUE-DESKTOP-v107 — migrado a inicio-desktop.css PLANES-BLOQUE-CANON */\n'
);
croma = croma.replace(
  /  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop-right \.proxplanes-movil\.is-on \{[\s\S]*?box-shadow: 0 2px 12px rgba\(111, 95, 154, \.1\);\n  \}\n/,
  ''
);
fs.writeFileSync(cromaPath, croma, 'utf8');

const phpPath = path.join(root, 'play.php');
let php = fs.readFileSync(phpPath, 'utf8');
php = php.replace(
  '<h3 class="enc-mov-tit">PLANES EN CURSO<span class="plan-seccion-cnt"',
  '<h3 class="enc-mov-tit">EN CURSO<span class="plan-seccion-cnt"'
);
fs.writeFileSync(phpPath, php, 'utf8');

const countLibreta = (desk.match(/INICIO-PLANES-BLOQUE-CANON-20260906/g) || []).length;
const countPass3 = (desk.match(/INICIO-VISUAL-PASS3-20260906/g) || []).length;
if (countLibreta !== 1 || countPass3 !== 0) {
  console.error('patch_planes_bloque_desktop: canon', countLibreta, 'pass3 residual', countPass3);
  process.exit(1);
}
console.log('patch_planes_bloque_desktop OK');
