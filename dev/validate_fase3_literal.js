/** Compara edificios_composicion.json con export FASE 3 del calibrador (valores literales usuario). */
const fs = require('fs');
const path = require('path');

const EXPORT_FASE3 = {
  bar: { x: 791, y: 753, ancho: 215, alto: 232 },
  discoteca: { x: 326, y: 2, ancho: 235, alto: 251 },
  karaoke: { x: 547, y: 3, ancho: 235, alto: 256 },
  cine: { x: 996, y: 0, ancho: 265, alto: 265 },
  recreativo: { x: 1217, y: 11, ancho: 235, alto: 235 },
  restaurante: { x: 79, y: 351, ancho: 358, alto: 358 },
  bingo: { x: 111, y: 8, ancho: 225, alto: 240 },
  cafeteria: { x: 108, y: 749, ancho: 255, alto: 255 },
  biblioteca: { x: 320, y: 742, ancho: 275, alto: 275 },
  tienda: { x: 553, y: 743, ancho: 255, alto: 255 },
  gimnasio: { x: 1013, y: 767, ancho: 215, alto: 222 },
  spa: { x: 1223, y: 766, ancho: 195, alto: 219 },
  picnic: { x: 1052, y: 455, ancho: 245, alto: 245 },
  mirador: { x: 1246, y: 296, ancho: 270, alto: 270 },
};

const comp = JSON.parse(fs.readFileSync(path.join(__dirname, '../assets/play-v3/edificios_composicion.json'), 'utf8'));
let fails = 0;

Object.keys(EXPORT_FASE3).forEach(function (id) {
  const exp = EXPORT_FASE3[id];
  const got = comp.edificios[id];
  ['x', 'y', 'ancho', 'alto'].forEach(function (k) {
    if (!got || got[k] !== exp[k]) {
      console.error('FAIL', id, k, 'got', got && got[k], 'exp', exp[k]);
      fails++;
    }
  });
});

if (fails) {
  console.error('Total fails:', fails);
  process.exit(1);
}
console.log('OK: edificios_composicion.json coincide literalmente con export FASE 3 (14 edificios)');
