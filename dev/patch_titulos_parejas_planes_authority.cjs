'use strict';
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const cromaPath = path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css');
let croma = fs.readFileSync(cromaPath, 'utf8').replace(/\r\n/g, '\n');

const parejasLegacy = `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo.shell-grupo-parejas .zona-tit-parejas {
  border-bottom: 1.5px solid rgba(232, 160, 180, .32);
  font-family: Nunito, "Segoe UI", sans-serif;
  font-size: .82rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: #2c261f;
  line-height: 1;
}

`;

const planesLegacy = `.play-v3 .inicio-desktop-right .inicio-planes-libreta-tit {
  font-family: Nunito, "Segoe UI", sans-serif;
  font-size: 1.02rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: #6f5f9a;
}

`;

if (!croma.includes(parejasLegacy)) {
  console.error('patch_titulos_parejas_planes_authority: bloque PAREJAS legacy no encontrado');
  process.exit(1);
}
if (!croma.includes(planesLegacy)) {
  console.error('patch_titulos_parejas_planes_authority: bloque PLANES legacy no encontrado');
  process.exit(1);
}

croma = croma.replace(parejasLegacy, '');
croma = croma.replace(planesLegacy, '');

if (/zona-tit-parejas[\s\S]{0,200}font-size:/.test(croma)) {
  console.error('patch_titulos_parejas_planes_authority: PAREJAS font-size residual en cromatica');
  process.exit(1);
}
if (/inicio-planes-libreta-tit[\s\S]{0,200}font-size:/.test(croma)) {
  console.error('patch_titulos_parejas_planes_authority: PLANES font-size residual en cromatica');
  process.exit(1);
}

fs.writeFileSync(cromaPath, croma, 'utf8');
console.log('patch_titulos_parejas_planes_authority OK');
