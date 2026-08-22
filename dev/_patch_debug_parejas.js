/**
 * Parche play-v3.js: herramienta DEBUG parejas + render canónico estado_pareja/crisis.
 */
const fs = require('fs');
const p = require('path').join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(p, 'utf8');

const helpers = `  function parejasParaUI(partida) {
    return (partida.relaciones_romanticas || []).filter(function (r) {
      if (!r) return false;
      const est = String(r.estado_pareja || r.estado || '');
      return est === 'pareja' || est === 'crisis';
    });
  }

  function esCrisisPareja(rel) {
    return String(rel && (rel.estado_pareja || rel.estado) || '') === 'crisis';
  }

  function idsPareja(rel) {
    if (rel.persona_a && rel.persona_b) return [rel.persona_a, rel.persona_b];
    if (rel.pareja && rel.pareja.length >= 2) return rel.pareja;
    if (rel.participantes && rel.participantes.length >= 2) return rel.participantes;
    return [];
  }

`;

if (!s.includes('function parejasParaUI')) {
  if (!s.includes('function renderShellPanels(estado, buzon, diario)')) {
    console.error('renderShellPanels not found');
    process.exit(1);
  }
  s = s.replace('  function renderShellPanels(estado, buzon, diario) {', helpers + '  function renderShellPanels(estado, buzon, diario) {');
}

if (s.includes("r.estado === 'pareja'")) {
  s = s.replace(
    /const parejas = \(partida\.relaciones_romanticas \|\| \[\]\)\.filter\(function \(r\) \{\s*return r && r\.estado === 'pareja';\s*\}\);/,
    'const parejas = parejasParaUI(partida);'
  );
}

if (s.includes("card.className = 'pareja-card'")) {
  s = s.replace(
    /const strip = \$\('\[data-parejas-strip\]'\);\s*if \(strip\) \{[\s\S]*?strip\.appendChild\(card\);\s*\}\);\s*if \(!parejas\.length\) \{[\s\S]*?\}\s*\}/,
    `const strip = $('[data-parejas-strip]');
    if (strip) {
      strip.innerHTML = '';
      parejas.forEach(function (rel) {
        const ids = idsPareja(rel);
        if (!ids || ids.length < 2) return;
        const crisis = esCrisisPareja(rel);
        const row = document.createElement('div');
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
          (crisis ? '<span class="pareja-crisis-sello">EN CRISIS</span>' : '');
        strip.appendChild(row);
      });
      if (!parejas.length) {
        strip.innerHTML = '<p class="muted">A\\u00fan no hay parejas registradas.</p>';
      }
    }`
  );
}

if (!s.includes('crearParejasPruebaDebug')) {
  const anchor = "if (btnCopyEstado) btnCopyEstado.addEventListener('click', function () { copiarDebugExport(true); });\r\n  })();";
  const anchorLf = anchor.replace(/\r\n/g, '\n');
  const insert = `if (btnCopyEstado) btnCopyEstado.addEventListener('click', function () { copiarDebugExport(true); });
    const btnParejasCrear = $('#btn-debug-parejas-crear');
    if (btnParejasCrear) btnParejasCrear.addEventListener('click', crearParejasPruebaDebug);
    const btnParejasQuitar = $('#btn-debug-parejas-quitar');
    if (btnParejasQuitar) btnParejasQuitar.addEventListener('click', quitarParejasPruebaDebug);
  })();

  async function crearParejasPruebaDebug() {
    if (!isDebugOn()) {
      toast('Activa DEBUG primero.');
      return;
    }
    const r = await api('partida.debug_parejas_crear', {});
    if (!r.ok) {
      toast(r.mensaje_ui || 'No se pudieron crear las parejas de prueba.');
      return;
    }
    try {
      console.log('%c[AHT DEBUG PAREJAS]', 'color:#c45;font-weight:bold', r.debug_parejas || r);
      console.log('[AHT DEBUG PAREJAS] JSON', JSON.stringify(r.debug_parejas || r, null, 2));
    } catch (e) {}
    toast('Parejas de prueba creadas.');
    await refresh();
  }

  async function quitarParejasPruebaDebug() {
    if (!isDebugOn()) {
      toast('Activa DEBUG primero.');
      return;
    }
    const r = await api('partida.debug_parejas_quitar', {});
    if (!r.ok) {
      toast(r.mensaje_ui || 'No se pudieron quitar las parejas de prueba.');
      return;
    }
    try {
      console.log('%c[AHT DEBUG PAREJAS]', 'color:#c45;font-weight:bold', r.debug_parejas || r);
      console.log('[AHT DEBUG PAREJAS] JSON', JSON.stringify(r.debug_parejas || r, null, 2));
    } catch (e) {}
    toast((r.n || 0) > 0 ? 'Parejas de prueba eliminadas.' : 'No hab\\u00eda parejas de prueba.');
    await refresh();
  }`;

  if (s.includes(anchor)) {
    s = s.replace(anchor, insert.replace(/\n/g, '\r\n'));
  } else if (s.includes(anchorLf)) {
    s = s.replace(anchorLf, insert);
  } else {
    console.error('debug anchor not found');
    process.exit(1);
  }
}

fs.writeFileSync(p, s);
console.log('play-v3.js patched (debug parejas)');
