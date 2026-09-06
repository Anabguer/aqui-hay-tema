'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/js/play-v3.js');
let js = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const oldBlock = `    if (met.conNecesidad > 0) {
      bits.push('<div class="vecinos-stat celeste-necesitan-algo" role="presentation" data-celestine-necesitan="1">' +
        '<span class="vecinos-stat-ico" aria-hidden="true">\\ud83e\\ude77</span>' +
        '<span class="vecinos-stat-k">Necesitan algo</span>' +
        '<strong class="vecinos-stat-v">' + esc(String(met.conNecesidad)) + '</strong></div>');
    }
`;

if (!js.includes(oldBlock)) {
  if (!js.includes('celeste-necesitan-algo')) {
    console.log('patch_hide_necesitan_algo_resumen: ya oculto');
    process.exit(0);
  }
  console.error('patch_hide_necesitan_algo_resumen: bloque no encontrado');
  process.exit(1);
}

const newBlock = `    /* celeste-necesitan-algo: oculto hasta pantalla necesidades_global lista */

`;

js = js.replace(oldBlock, newBlock);
fs.writeFileSync(file, js, 'utf8');
console.log('patch_hide_necesitan_algo_resumen OK');
