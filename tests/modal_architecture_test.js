'use strict';
/* MODAL-ARCHITECTURE — contrato estático Misiones + Mensajitos (piloto DS) */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const modalCore = fs.readFileSync(path.join(root, 'assets/css/design-system/modal-core.css'), 'utf8');
const capasShell = fs.readFileSync(path.join(root, 'assets/css/play-v3-capas-shell.css'), 'utf8');
const responsive = fs.readFileSync(path.join(root, 'assets/css/play-v3-responsive.css'), 'utf8');
const avisos = fs.readFileSync(path.join(root, 'assets/css/play-v3-avisos.css'), 'utf8');

let failures = 0;
function ok(cond, msg) {
  console.log((cond ? 'OK' : 'FAIL') + ': ' + msg);
  if (!cond) failures++;
}

// 1. Marcado ds-migrada en DOM
ok(/capa-misiones[^>]*ds-migrada/.test(php), 'play.php: capa-misiones con ds-migrada');
ok(/data-open="parejas"/.test(php), 'play.php: data-open parejas en desktop (shell-grupo-parejas-open)');
ok(/capa-parejas[^>]*ds-migrada/.test(php), 'play.php: capa-parejas con ds-migrada');

// 2. Desktop Misiones abre modal (data-open)
ok(/class="inicio-desktop[\s\S]*?data-open="misiones"/.test(php),
  'play.php: data-open misiones en vista desktop');
ok(/button[^>]*obj-misiones-papel[^>]*data-open="misiones"/.test(php),
  'play.php: botón desktop obj-misiones-papel con data-open misiones');
ok((php.match(/data-open="misiones"/g) || []).length >= 2,
  'play.php: data-open misiones en móvil y desktop');

// 3. Autoridad DS: reglas per-capa en modal-core
ok(/data-capa="misiones"\][\s\S]*capa-misiones\.ds-migrada/.test(modalCore),
  'modal-core: show rule misiones.ds-migrada');
ok(/data-capa="vida_pueblo"\][\s\S]*capa-vida-pueblo\.ds-migrada/.test(modalCore),
  'modal-core: show rule vida_pueblo.ds-migrada');
ok(/data-capa="parejas"\][\s\S]*capa-parejas\.ds-migrada/.test(modalCore),
  'modal-core: show rule parejas.ds-migrada');
ok(/data-capa="inventario"\][\s\S]*capa-inventario\.ds-migrada/.test(modalCore),
  'modal-core: show rule inventario.ds-migrada');
ok(/data-capa="organizar"\][\s\S]*capa-organizar\.ds-migrada/.test(modalCore),
  'modal-core: show rule organizar.ds-migrada');
ok(/720px/.test(modalCore) && /z-index: 110/.test(modalCore),
  'modal-core: geometria canonica 720px + z-index 110');
ok(/capa\.ds-migrada \[data-buzon-list\]/.test(modalCore),
  'modal-core: scroll [data-buzon-list] en ds-migrada');

// 4. Legacy excluye ds-migrada (buzon ya no panel lateral en PC)
ok(/pc\[data-capa="buzon"\] \.capa-buzon:not\(\.ds-migrada\)/.test(capasShell),
  'capas-shell: buzon legacy PC excluye ds-migrada');
ok(/pc\[data-capa="buzon"\] \.capa-buzon\.ds-migrada/.test(capasShell),
  'capas-shell: buzon ds-migrada PC visibility');
ok(/phone\[data-capa="buzon"\] \.capa-buzon\.ds-migrada/.test(responsive),
  'responsive: buzon ds-migrada show móvil');
ok(/phone\[data-capa="buzon"\] \.capa-buzon:not\(\.ds-migrada\)/.test(avisos),
  'avisos: shell unificado buzon excluye ds-migrada');

// 5. Móvil: buzon fuera de lista legacy fixed (solo ds-migrada path)
const mobLegacyBlock = responsive.match(/phone\[data-capa="vecinos"\][\s\S]{0,1200}phone\[data-capa="agenda"\]/)?.[0] || '';
ok(!/phone\[data-capa="buzon"\] \.capa-buzon[^.]*\{/.test(mobLegacyBlock),
  'responsive: buzon no en lista legacy móvil fixed (ds-migrada)');

// 6. Cableado modal DS en play.php
ok(/modal-core\.css/.test(php) && /modal-skin\.css/.test(php) &&
  /modal-header\.css/.test(php) && /modal-responsive\.css/.test(php),
  'play.php: hojas modal-core/skin/header/responsive enlazadas');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
