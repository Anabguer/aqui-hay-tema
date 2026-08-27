'use strict';
/* Contrato estático: badge real de Cotilleos (importantes_sin_ver + marcar visto). */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const api = fs.readFileSync(path.join(root, 'api/index.php'), 'utf8');
const diario = fs.readFileSync(path.join(root, 'api/handlers/DiarioHandler.php'), 'utf8');
const vista = fs.readFileSync(path.join(root, 'src/Engine/VistaCotilleoV3.php'), 'utf8');
const dsMobile = fs.readFileSync(
  path.join(root, 'assets/css/design-system/screens/inicio.css'),
  'utf8'
);
const dsDesktop = fs.readFileSync(
  path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'),
  'utf8'
);

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(/function cotiSinVerDe\(/.test(js), 'js: helper cotiSinVerDe');
ok(/importantes_sin_ver/.test(js), 'js: lee importantes_sin_ver del diario');
ok(/function actualizarCotiBadgesUI\(/.test(js), 'js: actualizarCotiBadgesUI centraliza badge');
ok(/function cotiBadgeNuevosTxt\(/.test(js), 'js: texto badge N nuevo(s)');
ok(/async function marcarCotilleoVisto\(/.test(js), 'js: marcarCotilleoVisto persiste visto');
ok(/api\('diario\.cotilleo_visto'/.test(js), 'js: llama API diario.cotilleo_visto');
ok(/actualizarCotiBadgesUI\(\);/.test(js), 'js: refresh actualiza badge tras paquete');
ok(/renderCotilleo\(d\.cotilleo[\s\S]{0,120}marcarCotilleoVisto\(\)/.test(js),
  'js: abrir capa diario marca cotilleos vistos');

ok(!/hoyLista\.length === 1 \? 'nuevo'/.test(js),
  'js: sin badge ficticio basado en hoyLista.length');
ok(!/hoyLista\.length \+ ' nuevos'/.test(js),
  'js: sin contador hardcodeado de entradas de hoy');

ok(/diario\.cotilleo_visto/.test(api), 'api: ruta diario.cotilleo_visto registrada');
ok(/function cotilleoVisto\(/.test(diario), 'api: DiarioHandler::cotilleoVisto');
ok(/function marcarVistas\(/.test(vista), 'backend: VistaCotilleoV3::marcarVistas');
ok(/importantes_sin_ver/.test(vista), 'backend: expone importantes_sin_ver');

ok(/\.obj-cotilleo-badge/.test(dsMobile), 'css movil: estilos badge DS');
ok(/@media \(min-width: 769px\)[\s\S]*\.obj-cotilleo-badge/.test(dsDesktop),
  'css desktop: badge visible en inicio-desktop');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
