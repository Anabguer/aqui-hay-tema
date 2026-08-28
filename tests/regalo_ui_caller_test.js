'use strict';
/* Regalos F1+F2+F3 · UI del jugador (cierre).
   Comprueba que el jugador NORMAL tiene un caller cableado de regalo.entregar
   (la auditoria reporto: "cero callers normales de regalo.entregar en assets/js").
   No es un test de navegador: es contrato estatico del cableado de la UI. */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

// 1. Existe la funcion que entrega (autoridad: regalo.entregar).
ok(/async function entregarRegalo\(\)/.test(js), 'js: entregarRegalo() definida');

// 2. Esa funcion llama al endpoint real regalo.entregar (no dev.regalo.otorgar).
ok(/api\('regalo\.entregar'/.test(js), 'js: entregarRegalo llama api regalo.entregar (jugador normal)');

// 3. El boton de entregar [data-inv-entregar] esta cableado por delegacion a entregarRegalo.
ok(/closest\('\[data-inv-entregar\]'\)/.test(js), 'js: hay delegacion click sobre [data-inv-entregar]');
ok(/entregarRegalo\(\);\s*\n\s*return;/.test(js), 'js: la delegacion invoca entregarRegalo() (caller normal presente)');

// 4. Proteccion anti doble envio.
ok(/if \(invEntregando\) return;/.test(js) && /invEntregando = true;/.test(js),
   'js: guarda concurrencia anti doble-submit (invEntregando)');

// 5. Acceso inventario: MOCHILA reutiliza la capa inventario (no se duplica "Mi Rincón").
ok(/data-open="inventario"/.test(js) || /setCapa\('inventario'\)/.test(js),
   'js: acceso a inventario presente (Mochila/capa inventario)');

// 6. REGALAR desde ficha abre el flujo de regalo.
ok(/abrirRegalosDesdeFicha/.test(js) && /\[data-ficha-regalar\]/.test(js),
   'js: REGALAR en ficha abre flujo de regalo');

// 7. Cancelar presente y cableado.
ok(/closest\('\[data-inv-cancelar\]'\)/.test(js), 'js: cancelar regalo cableado');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
