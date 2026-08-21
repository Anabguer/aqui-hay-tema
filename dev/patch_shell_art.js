/* eslint-disable */
const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(jsPath, 'utf8');

if (!s.includes('ART_DEMO')) {
  s = s.replace(
    '  const AGENDA_DEMO = IS_LAB && qs.get(\'agenda_demo\') === \'1\';',
    `  const AGENDA_DEMO = IS_LAB && qs.get('agenda_demo') === '1';
  const ART_DEMO = IS_LAB && qs.get('art_demo') === '1';`
  );
}

if (!s.includes('function htmlResumenCelestine')) {
  const fn = `
  function metricasParaUI(partida) {
    const met = metricasSociales(partida);
    if (!ART_DEMO) return met;
    return {
      vecinos: met.vecinos || 8,
      parejas: met.parejas || 2,
      crisis: met.crisis || 1,
      emo: {
        alegre: met.emo.alegre || 1,
        triste: met.emo.triste || 0,
        enfadado: met.emo.enfadado || 1,
      },
    };
  }

  function htmlResumenCelestine(met) {
    const bits = [];
    bits.push('<p class="celeste-lead"><strong>' + String(met.vecinos) +
      '</strong> <span>por aqu\\u00ed\\u2026</span></p>');
    const rows = [];
    if (met.parejas) {
      rows.push({ cls: 'm-pareja', t: met.parejas + (met.parejas === 1 ? ' pareja' : ' parejas') });
    }
    if (met.crisis) {
      rows.push({ cls: 'm-crisis', t: (met.crisis === 1 ? '1 en crisis' : met.crisis + ' en crisis') });
    }
    if (met.emo.alegre) {
      rows.push({ cls: 'm-alegre', t: met.emo.alegre + (met.emo.alegre === 1 ? ' alegre' : ' alegres') });
    }
    if (met.emo.triste) {
      rows.push({ cls: 'm-triste', t: met.emo.triste + (met.emo.triste === 1 ? ' triste' : ' tristes') });
    }
    if (met.emo.enfadado) {
      rows.push({ cls: 'm-enfadado', t: met.emo.enfadado + (met.emo.enfadado === 1 ? ' enfadado' : ' enfadados') });
    }
    if (rows.length) {
      bits.push('<div class="celeste-metricas">' + rows.map(function (r) {
        return '<span class="celeste-m ' + r.cls + '">' + esc(r.t) + '</span>';
      }).join('') + '</div>');
    }
    return bits.join('');
  }

  function parejasParaUI(partida) {
    const reales = (partida.relaciones_romanticas || []).filter(function (r) {
      const est = String(r.estado_pareja || r.estado || '');
      return est === 'pareja' || est === 'crisis';
    });
    if (reales.length) return reales;
    if (IS_LAB && (ART_DEMO || !reales.length)) return LAB_DEMO_PAREJAS;
    return [];
  }

  function esCrisisPareja(rel) {
    if (rel && rel.demo && rel.crisis) return true;
    return String(rel.estado_pareja || rel.estado || '') === 'crisis';
  }

`;
  s = s.replace('  function relojAbs(dia, hora) {', fn + '  function relojAbs(dia, hora) {');
}

// LAB demo parejas con crisis
s = s.replace(
  /const LAB_DEMO_PAREJAS = \[[\s\S]*?\];/,
  `const LAB_DEMO_PAREJAS = [
    { demo: true, ids: ['per_p002', 'per_p003'] },
    { demo: true, ids: ['per_p004', 'per_p001'] },
    { demo: true, ids: ['per_p001', 'per_p002'], crisis: true },
  ];`
);

// Resumen social HTML
s = s.replace(
  /const met = metricasSociales\(partida\);\s*\n\s*const stats = \$\('\[data-resumen-stats\]'\);[\s\S]*?\n    \}\n\n    const teaser = \$\('\[data-cotilleo-teaser\]'\);/,
  `const met = metricasParaUI(partida);
    const stats = $('[data-resumen-stats]');
    if (stats) stats.innerHTML = htmlResumenCelestine(met);

    const teaser = $('[data-cotilleo-teaser]');`
);

// Parejas list
s = s.replace(
  /const parejasReales = \(partida\.relaciones_romanticas \|\| \[\]\)\.filter\(function \(r\) \{\s*\n\s*return r && String\(r\.estado_pareja \|\| r\.estado \|\| ''\) === 'pareja';\s*\n\s*\}\);\s*\n\s*const parejas = parejasReales\.length \? parejasReales : \(IS_LAB \? LAB_DEMO_PAREJAS : \[\]\);/,
  `const parejasReales = parejasParaUI(partida);
    const parejas = parejasReales;`
);

// Bloques A/B/C compactos horizontales
s = s.replace(
  /btn\.className = 'obj-bloque-mini bloque-' \+ letra \+ \(abierto \? ' is-activo' : ' is-cerrado'\);[\s\S]*?bloques\.appendChild\(btn\);/,
  `btn.className = 'obj-residencia-mini res-' + letra + (abierto ? ' is-activo' : ' is-cerrado');
        if (!abierto) {
          btn.disabled = true;
          btn.title = 'Pr\\u00f3ximamente';
          btn.innerHTML = '<span class="res-fachada" aria-hidden="true"><span class="res-letra">' + d.label + '</span></span>' +
            '<span class="res-meta">Pr\\u00f3ximamente</span>';
        } else {
          let occ = 0;
          (blk && blk.viviendas || []).forEach(function (v) { if (v.ocupante_id) occ++; });
          const cap = (blk && blk.capacidad) || 16;
          btn.innerHTML = '<span class="res-fachada" aria-hidden="true"><span class="res-letra">' + d.label + '</span></span>' +
            '<span class="res-meta">' + occ + '/' + cap + '</span>';
        }
        bloques.appendChild(btn);`
);

// Parejas render piece
s = s.replace(
  /row\.className = 'obj-pareja-fila';[\s\S]*?row\.innerHTML = '<span class="obj-pareja-fotos">' \+ tok\(ids\[0\]\) \+ '<span class="obj-pareja-corazon" aria-hidden="true"><\/span>' \+ tok\(ids\[1\]\) \+ '<\/span>' \+\s*\n\s*'<span class="obj-pareja-nombres">' \+ esc\(nombreDe\(ids\[0\]\)\) \+ ' [^']* ' \+ esc\(nombreDe\(ids\[1\]\)\) \+ '<\/span>';/,
  `const crisis = esCrisisPareja(rel);
        row.className = 'obj-pareja-piece' + (crisis ? ' is-crisis' : '');
        const tok = function (id, rot) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          const st = rot ? ' style="transform:rotate(' + rot + 'deg)"' : '';
          if (t && t.url) return '<img class="obj-pareja-cara" src="' + esc(t.url) + '" alt=""' + st + '/>';
          return '<span class="obj-pareja-cara cara-ini"' + st + '>' + esc((nombreDe(id)[0] || '?')) + '</span>';
        };
        row.innerHTML = '<span class="obj-pareja-fotos">' + tok(ids[0], -6) +
          '<span class="obj-pareja-enlace" aria-hidden="true"></span>' + tok(ids[1], 5) + '</span>' +
          '<span class="obj-pareja-nombres">' + esc(nombreDe(ids[0])) + ' \\u00b7 ' + esc(nombreDe(ids[1])) + '</span>' +
          (crisis ? '<span class="pareja-crisis-sello">EN CRISIS</span>' : '');`
);

// pendientes text with arrow
s = s.replace(
  /pendTxt\.textContent = extra === 1 \? '1 plan pendiente' : \(extra \+ ' planes pendientes'\);/,
  `pendTxt.textContent = (extra === 1 ? '1 plan pendiente' : (extra + ' planes pendientes')) + ' \\u2192';`
);

fs.writeFileSync(jsPath, s);
console.log('patch_shell_art applied');
