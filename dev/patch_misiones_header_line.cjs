'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const titOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-tit {
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
  }`;

const titNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel::before {
    content: "";
    grid-column: 1 / -1;
    grid-row: 1;
    align-self: end;
    height: 1px;
    background: rgba(109, 155, 118, .22);
    pointer-events: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-misiones-papel-tit {
    grid-column: 1;
    grid-row: 1;
    display: block;
    margin: 0;
    padding-bottom: 8px;
    border-bottom: none;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
  }`;

const metaOld = `    padding-bottom: 8px;
    border-bottom: 1px solid rgba(109, 155, 118, .22);
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 600;
    font-size: .78rem;
    line-height: 1.2;
    color: rgba(200, 91, 120, .82);
    white-space: nowrap;
  }`;

const metaNew = `    padding-bottom: 8px;
    border-bottom: none;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 600;
    font-size: .78rem;
    line-height: 1.2;
    color: rgba(200, 91, 120, .82);
    white-space: nowrap;
  }`;

if (css.includes('.obj-misiones-papel::before') && css.includes('obj-misiones-papel-tit') && !/obj-misiones-papel-tit[\s\S]{0,200}border-bottom:\s*1px/.test(css)) {
  console.log('patch_misiones_header_line: ya aplicado');
  process.exit(0);
}

if (!css.includes(titOld)) {
  console.error('patch_misiones_header_line: bloque tit no encontrado');
  process.exit(1);
}
if (!css.includes(metaOld)) {
  console.error('patch_misiones_header_line: bloque meta no encontrado');
  process.exit(1);
}

css = css.replace(titOld, titNew).replace(metaOld, metaNew);
fs.writeFileSync(file, css, 'utf8');
console.log('patch_misiones_header_line OK');
