'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');
css = css.replace(/\r\n/g, '\n');

css = css.replace(
  '    align-items: stretch;',
  '    align-items: center;'
);

const addFilterNone = (selectorBlock, propsBeforeClosing) => {
  if (css.includes(propsBeforeClosing + '\n    filter: none;')) return true;
  const needle = propsBeforeClosing + '\n  }';
  const replacement = propsBeforeClosing + '\n    filter: none;\n  }';
  if (!css.includes(needle)) {
    console.error('patch_reloj_pasar_shadow_fix: no encontrado', selectorBlock);
    return false;
  }
  css = css.replace(needle, replacement);
  return true;
};

if (!addFilterNone('obj-dia', `    box-shadow: none;
    margin: 0;
    align-self: center;`)) process.exit(1);

if (!addFilterNone('obj-hora', `    box-shadow: none;
    margin: 0;
    align-self: center;`)) process.exit(1);

const pasarOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .pasar-rato {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    margin: 0;
    background: #ebe3f8;
    border: 1.5px solid #b8a4d8;
    border-radius: 999px;
    box-shadow: none;
    color: #5e3e90;
    transform: none;
    cursor: pointer;
    font: inherit;
  }`;

const pasarNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .pasar-rato {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: auto;
    min-height: 0;
    align-self: center;
    padding: 10px 16px;
    margin: 0;
    background: #ebe3f8;
    border: 1.5px solid #b8a4d8;
    border-radius: 999px;
    box-shadow: none;
    color: #5e3e90;
    transform: none;
    filter: none;
    cursor: pointer;
    font: inherit;
  }`;

if (!css.includes(pasarOld)) {
  console.error('patch_reloj_pasar_shadow_fix: bloque pasar-rato no encontrado');
  process.exit(1);
}
css = css.replace(pasarOld, pasarNew);

const horaIcoOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora-ico {
    position: static;
    top: auto;
    right: auto;
    width: 28px;
    height: 28px;
    margin: 0;
    flex-shrink: 0;`;

const horaIcoNew = `${horaIcoOld}
    filter: none;`;

if (!css.includes(horaIcoOld)) {
  console.error('patch_reloj_pasar_shadow_fix: bloque obj-hora-ico no encontrado');
  process.exit(1);
}
css = css.replace(horaIcoOld, horaIcoNew);

fs.writeFileSync(file, css.replace(/\n/g, '\r\n'), 'utf8');
console.log('patch_reloj_pasar_shadow_fix OK');
