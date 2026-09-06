'use strict';
const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, '..', 'assets/js/play-v3.js');
const cssFile = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');

const jsRaw = fs.readFileSync(jsFile, 'utf8');
const jsEol = jsRaw.includes('\r\n') ? '\r\n' : '\n';
let js = jsRaw.replace(/\r\n/g, '\n');

const jsOld = `    /* celeste-necesitan-algo: oculto hasta pantalla necesidades_global lista */

`;

const jsNew = `    if (met.conNecesidad > 0) {
      bits.push('<div class="vecinos-stat celeste-necesitan-algo" role="presentation" data-celestine-necesitan="1">' +
        '<span class="vecinos-stat-ico" aria-hidden="true">\\ud83e\\ude77</span>' +
        '<span class="vecinos-stat-k">Necesitan algo</span>' +
        '<strong class="vecinos-stat-v">' + esc(String(met.conNecesidad)) + '</strong></div>');
    }
`;

if (!js.includes(jsOld)) {
  if (js.includes('celeste-necesitan-algo')) {
    console.log('patch_necesitan_algo_visual: JS ya restaurado');
  } else {
    console.error('patch_necesitan_algo_visual: bloque JS no encontrado');
    process.exit(1);
  }
} else {
  js = js.replace(jsOld, jsNew);
  fs.writeFileSync(jsFile, js.replace(/\n/g, jsEol), 'utf8');
  console.log('patch_necesitan_algo_visual: JS OK');
}

let css = fs.readFileSync(cssFile, 'utf8').replace(/\r\n/g, '\n');

const cssMarker = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .celeste-cuenta-vecinos {
    display: none;
  }
`;

const cssInsert = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .celeste-necesitan-algo {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 6px;
    width: 100%;
    margin-top: 6px;
    padding: 8px 0 2px;
    border-top: 1px solid rgba(198, 176, 210, .30);
    text-align: left;
    cursor: pointer;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .celeste-necesitan-algo .vecinos-stat-ico {
    flex: 0 0 auto;
    width: auto;
    font-size: 1.05rem;
    line-height: 1;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .celeste-necesitan-algo .vecinos-stat-k {
    flex: 1 1 auto;
    min-width: 0;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 700;
    font-size: .86rem;
    letter-spacing: 0;
    text-transform: none;
    color: #2a2218;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .celeste-necesitan-algo .vecinos-stat-v {
    flex: 0 0 auto;
    margin-left: auto;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .86rem;
    color: #2a2218;
  }

`;

if (css.includes('.celeste-necesitan-algo {')) {
  console.log('patch_necesitan_algo_visual: CSS ya presente');
} else if (!css.includes(cssMarker)) {
  console.error('patch_necesitan_algo_visual: marcador CSS no encontrado');
  process.exit(1);
} else {
  css = css.replace(cssMarker, cssInsert + cssMarker);
  fs.writeFileSync(cssFile, css, 'utf8');
  console.log('patch_necesitan_algo_visual: CSS OK');
}

console.log('patch_necesitan_algo_visual OK');
