'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const pairs = [
  [
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-txt {
    flex: 1 1 auto;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .98rem;`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-txt {
    flex: 1 1 auto;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1.05rem;`,
    'obj-buzon-txt',
  ],
  [
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1.05rem;`,
    'obj-vecinos-tit',
  ],
  [
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1.05rem;`,
    'obj-cotilleo-tit',
  ],
  [
    `    font-weight: 800;
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-meta {`,
    `    font-weight: 800;
    font-size: 1.05rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-meta {`,
    'obj-misiones-papel-tit',
  ],
  [
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1.05rem;`,
    'inicio-planes-libreta-tit',
  ],
  [
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-parejas .zona-tit-parejas {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1.5px solid rgba(232, 160, 180, .28);
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-parejas .zona-tit-parejas {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1.5px solid rgba(232, 160, 180, .28);
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1.05rem;`,
    'zona-tit-parejas',
  ],
];

for (const [oldBlock, newBlock, name] of pairs) {
  if (!css.includes(oldBlock)) {
    if (css.includes(name) && css.includes('font-size: 1.05rem')) {
      console.log('patch_titulos_inicio_105: ya', name);
      continue;
    }
    console.error('patch_titulos_inicio_105: no encontrado', name);
    process.exit(1);
  }
  css = css.replace(oldBlock, newBlock);
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_titulos_inicio_105 OK');
