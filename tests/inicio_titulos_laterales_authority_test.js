'use strict';
/* Autoridad única: títulos laterales desktop a 1.05rem sin duplicidad en cromática. */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const desk = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-desktop.css'), 'utf8');
const croma = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

const titulos = [
  { nombre: 'MENSAJITOS', desk: /inicio-desktop-left \.obj-buzon-txt[\s\S]{0,220}font-size:\s*1\.05rem/, croma: /obj-buzon-txt[\s\S]{0,120}font-size:/ },
  { nombre: 'VECINOS', desk: /celestine-nota \.obj-vecinos-tit[\s\S]{0,220}font-size:\s*1\.05rem/, croma: /obj-vecinos-tit[\s\S]{0,120}font-size:/ },
  { nombre: 'COTILLEOS', desk: /obj-cotilleo-tit[\s\S]{0,220}font-size:\s*1\.05rem/, croma: /obj-cotilleo-tit[\s\S]{0,120}font-size:/ },
  { nombre: 'MISIONES', desk: /obj-misiones-papel-tit[\s\S]{0,260}font-size:\s*1\.05rem/, croma: /obj-misiones-papel-tit[\s\S]{0,120}font-size:/ },
  { nombre: 'PLANES', desk: /inicio-planes-libreta-tit[\s\S]{0,220}font-size:\s*1\.05rem/, croma: /inicio-planes-libreta-tit[\s\S]{0,120}font-size:/ },
  { nombre: 'PAREJAS', desk: /shell-grupo-parejas \.zona-tit-parejas[\s\S]{0,420}font-size:\s*1\.05rem/, croma: /zona-tit-parejas[\s\S]{0,120}font-size:/ },
];

titulos.forEach(function (t) {
  ok(t.desk.test(desk), t.nombre + ': autoridad canonica 1.05rem en inicio-desktop.css');
  ok(!t.croma.test(croma), t.nombre + ': sin font-size duplicado en inicio-cromatica-desktop.css');
});

ok(!/zona-tit-parejas[\s\S]{0,260}font-size:\s*\.82rem/.test(croma), 'PAREJAS: eliminado legacy .82rem en cromatica');
ok(!/inicio-planes-libreta-tit[\s\S]{0,260}font-size:\s*1\.02rem/.test(croma), 'PLANES: eliminado legacy 1.02rem en cromatica');
ok(/shell-grupo-parejas \.zona-tit-parejas::before[\s\S]{0,900}linear-gradient/.test(desk), 'PAREJAS: icono ::before solo en inicio-desktop.css');
ok(!/shell-grupo-parejas \.zona-tit-parejas::before/.test(croma), 'PAREJAS: sin ::before duplicado en cromatica');
ok(!/!important/.test(desk + croma), 'titulos laterales: cero !important');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\ninicio_titulos_laterales_authority_test OK');
