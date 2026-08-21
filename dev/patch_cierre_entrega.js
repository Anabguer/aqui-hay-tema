/* eslint-disable */
/** Parche cierre entrega: resumen social, hora UI comentada, overflow estructural, lab fixture. */
const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(jsPath, 'utf8');

// 1. Resumen social: Parejas solo si >0 (Vecinos siempre)
s = s.replace(
  /lines\.push\(\{ icon: '[^']*', k: 'Parejas', v: String\(met\.parejas\) \}\);/,
  "if (met.parejas) lines.push({ icon: '\u2665', k: 'Parejas', v: String(met.parejas) });"
);
s = s.replace(
  /lines\.push\(\{ icon: '[^']*', k: 'Vecinos', v: String\(met\.vecinos\) \}\);/,
  "lines.push({ icon: '\u2022', k: 'Vecinos', v: String(met.vecinos) });"
);

// 2. Comentario regla hora: espejo de Reloj::esFuturo (slot entero estrictamente posterior)
if (!s.includes('Reloj::esFuturo')) {
  s = s.replace(
    '    const minH = (diaSel === hoy) ? Math.max(8, horaAhora + 1) : 8;',
    `    /* Primer slot hoy: hora_actual+1 = siguiente hora entera futura (Reloj::esFuturo).
       No hay margen de preparaci\u00f3n; Math.max(8,...) solo respeta apertura m\u00ednima UI. */
    const minH = (diaSel === hoy) ? Math.max(8, horaAhora + 1) : 8;`
  );
}

// 3. Lab fixture: solo visual, nunca mezclar si ya hay 3+ reales
s = s.replace(
  /if \(AGENDA_DEMO && fut\.length < 3\) \{\s*\n\s*demoEncuentrosFuturos\(estado\)\.forEach/,
  `if (AGENDA_DEMO && raw.length < 3) {
      demoEncuentrosFuturos(estado).forEach`
);

fs.writeFileSync(jsPath, s);

const shellCss = path.join(__dirname, '..', 'assets', 'css', 'play-v3-shell-ui.css');
let css = fs.readFileSync(shellCss, 'utf8');

// Aire vecinos → buzón
css = css.replace(
  '.zona-actividad .obj-buzon { margin-top: .15rem; }',
  '.zona-actividad .obj-buzon { margin-top: .85rem; }'
);

// Overflow estructural: stats no desbordan columna
if (!css.includes('vecinos-stat-k { flex: 1')) {
  css = css.replace(
    '.vecinos-stat-k { flex: 1; color: #5a5048; }',
    '.vecinos-stat-k { flex: 1; min-width: 0; color: #5a5048; text-transform: uppercase; font-size: .62rem; letter-spacing: .04em; }'
  );
}
css = css.replace(
  '.vecinos-stat { display: flex; align-items: center; gap: .35rem; padding: .12rem 0; font-size: .68rem; line-height: 1.25; }',
  '.vecinos-stat { display: flex; align-items: center; gap: .35rem; padding: .12rem 0; font-size: .68rem; line-height: 1.25; min-width: 0; max-width: 100%; }'
);
css = css.replace(
  '.obj-vecinos-stats {',
  '.obj-vecinos-stats { max-width: 100%; overflow: hidden; '
);
if (!css.includes('.obj-vecinos-stats { max-width')) {
  css = css.replace(
    '.obj-vecinos-tit { display: block;',
    '.obj-vecinos-stats { max-width: 100%; overflow: hidden; }\n.obj-vecinos-tit { display: block;'
  );
}

// Bloques: no desbordar columna derecha
css = css.replace(
  '.obj-bloques-res { display: flex; flex-direction: column; gap: .55rem; width: 100%; }',
  '.obj-bloques-res { display: flex; flex-direction: column; gap: .55rem; width: 100%; max-width: 100%; min-width: 0; overflow: hidden; }'
);
css = css.replace(
  '.obj-bloque-mini {\n  display: flex; align-items: center; gap: .45rem; width: 100%; padding: .2rem 0;',
  '.obj-bloque-mini {\n  display: flex; align-items: center; gap: .45rem; width: 100%; max-width: 100%; min-width: 0; padding: .2rem 0;'
);
css = css.replace(
  '.bloque-mini-info { font-size: .64rem; line-height: 1.25; }',
  '.bloque-mini-info { font-size: .64rem; line-height: 1.25; min-width: 0; overflow: hidden; text-overflow: ellipsis; }'
);

fs.writeFileSync(shellCss, css);
console.log('patch_cierre_entrega applied');
