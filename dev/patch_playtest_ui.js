/* eslint-disable */
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(file, 'utf8');

if (!s.includes('function nombreLugar(')) {
  s = s.replace(
    '  function findDestinoMeta(destId) {',
    `  function nombreLugar(lugId) {
    if (!lugId) return 'Lugar';
    const id = String(lugId);
    let found = null;
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.destinos || []).concat(cx.destinos_operativos || []).forEach(function (d) {
        if (d.id === id) found = d;
      });
    });
    const nom = found && found.nombre ? String(found.nombre) : '';
    if (nom && nom.indexOf('lug_') !== 0) return nom;
    return DESTINO_NOMBRE[id] || (id.indexOf('lug_') === 0 ? id.slice(4).replace(/_/g, ' ').replace(/\\b\\w/g, function (c) { return c.toUpperCase(); }) : id);
  }

  function findDestinoMeta(destId) {`
  );
}

s = s.replace(/function destinosOperativos\(pueblo\)/g, 'function mapDestinosOperativos(pueblo)');
s = s.replace(/destinosOperativos\(pueblo\)/g, 'mapDestinosOperativos(pueblo)');

s = s.replace(
  /lines\.push\(\{ icon: '[^']*', k: 'Vecinos', v: String\(met\.vecinos\) \}\);\s*\n\s*if \(met\.parejas\) lines\.push\(\{ icon: '[^']*', k: 'Parejas', v: String\(met\.parejas\) \}\);/,
  "lines.push({ icon: '👥', k: 'Vecinos', v: String(met.vecinos) });\n      lines.push({ icon: '♥', k: 'Parejas', v: String(met.parejas) });"
);

s = s.replace(
  /if \(!next && IS_LAB\) next = LAB_DEMO_PROXIMO;\s*\n\s*if \(!next\)/,
  'if (!next)'
);

s = s.replace(
  /esc\(next\.lugar_nombre \|\| next\.lugar \|\| 'Lugar'\)/,
  "esc(nombreLugar(next.lugar_nombre || next.lugar))"
);

s = s.replace(
  /o\.textContent = d\.nombre;/,
  'o.textContent = nombreLugar(d.id) || d.nombre;'
);

if (s.includes('const LAB_DEMO_PROXIMO')) {
  s = s.replace(/\s*const LAB_DEMO_PROXIMO = [\s\S]*?\};\s*\n/, '\n');
}

fs.writeFileSync(file, s);
console.log('patched play-v3.js');
