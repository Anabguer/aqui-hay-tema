'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const misionesOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel {
    display: block;
    width: 100%;
    padding: 12px 14px 10px;
    background: #f6faf3;
    border: 1.5px solid rgba(109, 155, 118, .42);
    border-radius: 16px 14px 18px 15px / 14px 17px 15px 16px;
    box-shadow: 0 2px 8px rgba(70, 90, 70, .08);
    cursor: pointer;
    text-align: left;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-tit {
    display: block;
    margin-bottom: 8px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #7a7164;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-strip {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }`;

const misionesNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel {
    display: grid;
    grid-template-columns: 1fr auto;
    grid-template-rows: auto auto;
    column-gap: 10px;
    row-gap: 8px;
    width: 100%;
    padding: 12px 14px 10px;
    background: #f6faf3;
    border: 1.5px solid rgba(109, 155, 118, .42);
    border-radius: 16px;
    box-shadow: none;
    filter: none;
    transform: none;
    cursor: pointer;
    text-align: left;
    position: relative;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel .mision-tape {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-tit {
    grid-column: 1;
    grid-row: 1;
    display: block;
    margin: 0;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(109, 155, 118, .22);
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-meta {
    grid-column: 2;
    grid-row: 1;
    align-self: start;
    margin: 0;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(109, 155, 118, .22);
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 600;
    font-size: .78rem;
    line-height: 1.2;
    color: rgba(200, 91, 120, .82);
    white-space: nowrap;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-meta[hidden] {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-strip {
    grid-column: 1 / -1;
    grid-row: 2;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }`;

if (!css.includes(misionesOld)) {
  console.error('patch_titulos_misiones_planes: bloque misiones no encontrado');
  process.exit(1);
}
css = css.replace(misionesOld, misionesNew);

const titleRules = [
  [
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #7a7164;
    margin: 0;
  }`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
    margin: 0;
  }`,
    'planes-libreta-tit',
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
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #2c261f;
  }`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-parejas .zona-tit-parejas {
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
  }`,
    'zona-tit-parejas',
  ],
];

for (const [oldBlock, newBlock, name] of titleRules) {
  const count = css.split(oldBlock).length - 1;
  if (count === 0) {
    console.error('patch_titulos_misiones_planes: no encontrado', name);
    process.exit(1);
  }
  css = css.split(oldBlock).join(newBlock);
  console.log('OK:', name, 'x', count);
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_titulos_misiones_planes OK');
