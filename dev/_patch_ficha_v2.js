const fs = require('fs');

const jsPath = 'assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

// Remove unused animoLinea
js = js.replace(/\n  function animoLinea\(emo\) \{[\s\S]*?\n  \}\n/, '\n');

// Update barRelPct
js = js.replace(
  /function barRelPct\(rel\) \{[\s\S]*?\n  \}/,
  `function barRelPct(rel) {
    if (rel.etiqueta_vinculo === 'pareja') return 96;
    if (rel.etiqueta_vinculo === 'crisis') return 52;
    if (rel.etiqueta_vinculo === 'ex_pareja') return 38;
    const s = rel.etiqueta_social || '';
    if (s === 'muy_buena_amistad') return 92;
    if (s === 'buena_amistad') return 86;
    if (s === 'amigo') return 76;
    if (s === 'conocido') return 62;
    if (s === 'cae_mal') return 18;
    if (s === 'desconocido') return 34;
    return 48;
  }`
);

const helpers = `
  let fichaRelCache = [];

  function relacionesConocidas(f) {
    const rels = [];
    const raw = (f && f.relaciones) || {};
    Object.keys(raw).forEach(function (oid) {
      const r = raw[oid];
      if (r && r.conocidos) rels.push(r);
    });
    rels.sort(function (a, b) {
      const pa = barRelPct(a);
      const pb = barRelPct(b);
      if (pb !== pa) return pb - pa;
      if (a.etiqueta_vinculo === 'crisis') return -1;
      if (b.etiqueta_vinculo === 'crisis') return 1;
      return String(a.nombre).localeCompare(String(b.nombre), 'es');
    });
    return rels;
  }

  function htmlRelRow(rel) {
    const cara = tokenDe(rel.id);
    const ini = (rel.nombre || '?').charAt(0);
    const pct = barRelPct(rel);
    const lbl = etiquetaRelText(rel);
    const crisis = rel.etiqueta_vinculo === 'crisis';
    return (
      '<div class="ficha-rel-row' + (crisis ? ' is-crisis' : '') + '">' +
      '<div class="ficha-rel-cara">' +
      (cara ? '<img src="' + esc(cara) + '" alt=""/>' : '<span>' + esc(ini) + '</span>') +
      '</div>' +
      '<div class="ficha-rel-main">' +
      '<div class="ficha-rel-nom">' + esc(rel.nombre || rel.id) + '</div>' +
      '<div class="ficha-rel-bar"><span style="width:' + pct + '%"></span></div>' +
      '</div>' +
      '<span class="ficha-rel-etiq">' + esc(lbl) + '</span>' +
      '</div>'
    );
  }

  function pintarRelacionesEn(box, rels, limit) {
    if (!box) return;
    box.innerHTML = '';
    if (!rels.length) {
      box.innerHTML = '<p class="ficha-vacio">De momento, solo le conoces a ti. O eso dice el pueblo.</p>';
      return;
    }
    rels.slice(0, limit).forEach(function (rel) {
      box.insertAdjacentHTML('beforeend', htmlRelRow(rel));
    });
  }

  function cerrarFichaRelOverlay() {
    const overlay = $('[data-ficha-rel-overlay]');
    if (overlay) overlay.hidden = true;
  }

  function abrirFichaRelOverlay(nombre) {
    const overlay = $('[data-ficha-rel-overlay]');
    const list = $('[data-ficha-rel-list]');
    const tit = $('[data-ficha-rel-modal-tit]');
    if (!overlay || !list) return;
    if (tit) tit.textContent = 'Relaciones de ' + (nombre || 'vecino');
    pintarRelacionesEn(list, fichaRelCache, fichaRelCache.length);
    overlay.hidden = false;
  }

  function textoPlanesVacios(id) {
    const frases = [
      'Ni un café en el horizonte. O eso cree.',
      'Agenda libre. Demasiado libre.',
      'Hoy no tiene nada apuntado. Ni mañana, según parece.',
      'Cero planes. Cero prisas. Cero drama… por ahora.'
    ];
    let h = 0;
    const s = String(id || '');
    for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % frases.length;
    return frases[h];
  }
`;

if (!js.includes('function relacionesConocidas(')) {
  js = js.replace('  let fichaActualId = \'\';', '  let fichaActualId = \'\';\n' + helpers.trim());
}

// Replace relaciones block in pintarFicha
const relBlockRe = /const relBox = \$\('\[data-ficha-relaciones\]'\);[\s\S]*?relBox\.appendChild\(row\);\s*\}\);\s*\}\s*\}/;
const newRelBlock = `const relBox = $('[data-ficha-relaciones]');
    const relMasBtn = $('[data-ficha-rel-mas]');
    fichaRelCache = relacionesConocidas(f);
    pintarRelacionesEn(relBox, fichaRelCache, 3);
    if (relMasBtn) {
      if (fichaRelCache.length > 3) {
        relMasBtn.hidden = false;
        relMasBtn.textContent = 'Ver más relaciones (' + (fichaRelCache.length - 3) + ' más)';
        relMasBtn.onclick = function () { abrirFichaRelOverlay(nom); };
      } else {
        relMasBtn.hidden = true;
        relMasBtn.onclick = null;
      }
    }
    cerrarFichaRelOverlay();`;

if (!relBlockRe.test(js)) {
  console.error('rel block not found');
  process.exit(1);
}
js = js.replace(relBlockRe, newRelBlock);

// Replace plans block
const planBlockRe = /const planBox = \$\('\[data-ficha-planes\]'\);[\s\S]*?planBox\.appendChild\(p\);\s*\}\);\s*\}\s*\}/;
const newPlanBlock = `const planBox = $('[data-ficha-planes]');
    if (planBox) {
      planBox.innerHTML = '';
      const planes = planesDeVecino(id);
      if (!planes.length) {
        planBox.innerHTML = '<p class="ficha-vacio ficha-ironico">' + esc(textoPlanesVacios(id)) + '</p>';
      } else {
        planes.forEach(function (enc) {
          const p = document.createElement('p');
          p.className = 'ficha-plan-item';
          p.textContent = formatPlanMeta(enc, cacheEstado);
          planBox.appendChild(p);
        });
      }
    }`;

if (!planBlockRe.test(js)) {
  console.error('plan block not found');
  process.exit(1);
}
js = js.replace(planBlockRe, newPlanBlock);

// Listeners for rel overlay close
if (!js.includes('data-ficha-rel-close')) {
  js = js.replace(
    '  const fichaVolver = $(\'[data-ficha-volver]\');',
    `  const fichaRelClose = $('[data-ficha-rel-close]');
  if (fichaRelClose) fichaRelClose.addEventListener('click', cerrarFichaRelOverlay);
  const fichaRelOverlay = $('[data-ficha-rel-overlay]');
  if (fichaRelOverlay) {
    fichaRelOverlay.addEventListener('click', function (ev) {
      if (ev.target === fichaRelOverlay) cerrarFichaRelOverlay();
    });
  }

  const fichaVolver = $('[data-ficha-volver]');`
  );
}

// Close rel overlay when closing ficha capa
if (!js.includes('cerrarFichaRelOverlay();')) {
  js = js.replace(
    '    const t = ev.target.closest(\'[data-close], .velo\');',
    '    if (ev.target.closest(\'[data-ficha-rel-close]\')) return;\n    const t = ev.target.closest(\'[data-close], .velo\');'
  );
}

fs.writeFileSync(jsPath, js, 'utf8');
console.log('JS v2 ok');
