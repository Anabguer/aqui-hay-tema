'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

const diaOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto;
    column-gap: 10px;
    row-gap: 2px;
    align-items: center;
    width: auto;
    min-width: 0;
    min-height: 52px;
    padding: 10px 14px;
    box-sizing: border-box;`;

const diaNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto;
    column-gap: 10px;
    row-gap: 2px;
    align-items: center;
    align-content: center;
    width: auto;
    min-width: 0;
    height: 54px;
    min-height: 54px;
    padding: 0 14px;
    box-sizing: border-box;`;

const horaOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    width: auto;
    min-width: 0;
    min-height: 52px;
    padding: 10px 16px;
    box-sizing: border-box;`;

const horaNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    width: auto;
    min-width: 0;
    height: 54px;
    min-height: 54px;
    padding: 0 14px;
    box-sizing: border-box;`;

let ok = true;
if (!css.includes(diaOld)) {
  console.error('patch_reloj_pills_height: bloque obj-dia no encontrado');
  ok = false;
} else {
  css = css.replace(diaOld, diaNew);
}

if (!css.includes(horaOld)) {
  console.error('patch_reloj_pills_height: bloque obj-hora no encontrado');
  ok = false;
} else {
  css = css.replace(horaOld, horaNew);
}

// Quitar autoridad duplicada FIEL sobre obj-dia-meta (compite con CABECERA)
css = css.replace(
  /@media \(min-width: 769px\) \{\n  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.obj-dia-cuerpo \.obj-dia-meta \{\n    display: block;\n    font-size: \.78rem;\n    font-weight: 700;\n    line-height: 1\.15;\n  \}\n\}\n/,
  ''
);

if (!ok) process.exit(1);

fs.writeFileSync(file, css, 'utf8');
console.log('patch_reloj_pills_height OK');
