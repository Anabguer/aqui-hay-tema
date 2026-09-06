'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const oldBlock = `    border-radius: 16px;
    box-shadow: none;
    margin: 0;
    align-self: center;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::before,`;

const newBlock = `    border-radius: 16px;
    box-shadow: none;
    filter: none;
    margin: 0;
    align-self: center;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::before,`;

if (!css.includes(oldBlock)) {
  // maybe filter already there or different order
  if (/\.game-top \.obj-hora \{[\s\S]{0,400}filter:\s*none/.test(css)) {
    console.log('patch_obj_hora_filter: ya tiene filter none');
    process.exit(0);
  }
  console.error('patch_obj_hora_filter: bloque obj-hora no encontrado');
  process.exit(1);
}

css = css.replace(oldBlock, newBlock);

css = css.replace(
  `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::before,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::after {
    display: none;
    content: none;
  }`,
  `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::before,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::after {
    display: none;
    content: none;
    box-shadow: none;
    filter: none;
  }`
);

if (!/\.game-top \.obj-hora \{[\s\S]{0,500}filter:\s*none/.test(css)) {
  console.error('patch_obj_hora_filter: filter none no aplicado');
  process.exit(1);
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_obj_hora_filter OK');
