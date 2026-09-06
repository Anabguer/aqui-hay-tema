'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const oldBlock = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota.obj-vecinos-resumen::before {
    content: "";
    position: absolute;
    top: 12px;
    right: 14px;
    left: auto;
    width: 24px;
    height: 24px;
    background: url("../play-v3/capas/chincheta.png") center / contain no-repeat;
    border: none;
    border-radius: 0;
    transform: none;
    box-shadow: none;
    pointer-events: none;
  }`;

const newBlock = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota.obj-vecinos-resumen::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    pointer-events: none;
    z-index: 0;
    background: linear-gradient(115deg, rgba(255, 253, 250, .35) 0%, transparent 55%, rgba(255, 253, 250, .2) 100%);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota.obj-vecinos-resumen::after {
    content: "";
    display: block;
    position: absolute;
    top: 8px;
    right: 12px;
    width: 28px;
    height: 32px;
    background: url("../../play-v3/capas/chincheta.png") center / contain no-repeat;
    border: none;
    border-radius: 0;
    transform: rotate(10deg);
    box-shadow: none;
    filter: drop-shadow(1px 2px 1px rgba(60, 50, 40, .12));
    pointer-events: none;
    z-index: 3;
  }`;

if (!css.includes(oldBlock)) {
  if (css.includes('../../play-v3/capas/chincheta.png') && css.includes('celestine-nota.obj-vecinos-resumen::after')) {
    console.log('patch_chincheta_celestine: ya aplicado');
    process.exit(0);
  }
  console.error('patch_chincheta_celestine: bloque no encontrado');
  process.exit(1);
}

css = css.replace(oldBlock, newBlock);
fs.writeFileSync(file, css, 'utf8');
console.log('patch_chincheta_celestine OK');
