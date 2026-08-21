const fs = require('fs');
const jsPath = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

const newRenderHud = `  function renderHud(estado, buzon) {
    const rv = estado.reloj_vista || {};
    const reloj = estado.reloj || {};
    const diaNum = reloj.dia_pueblo;
    const diaNumEl = $('[data-dia-num]');
    if (diaNumEl) {
      diaNumEl.textContent = diaNum !== undefined && diaNum !== null ? ('DÍA ' + diaNum) : 'DÍA —';
    }
    $$('[data-dow]').forEach(function (el) {
      el.textContent = rv.dia_semana_ui || (diaNum !== undefined ? ('Día ' + diaNum) : '—');
    });
    $$('[data-fecha]').forEach(function (el) {
      el.textContent = rv.fecha_corta || '';
    });
    const h = rv.hora !== undefined ? rv.hora : reloj.hora_actual;
    $$('[data-hora]').forEach(function (el) {
      el.textContent = h === undefined ? '—' : (String(h).padStart(2, '0') + ':00');
    });
    $$('[data-dinero]').forEach(function (el) {
      el.textContent = dineroTxt(cacheInsp, estado);
    });
    const vida = estado.vida_pueblo || null;
    const pct = vida && typeof vida.corazon_pct === 'number' ? vida.corazon_pct : 0;
    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const heartRect = $('.corazon-fill-rect');
    if (heartRect) {
      const hMax = 50;
      const fillH = Math.max(0, Math.min(hMax, (pct / 100) * hMax));
      heartRect.setAttribute('y', String(hMax - fillH));
      heartRect.setAttribute('height', String(fillH));
    }
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';
    const cartas = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    const badge = $('.buzon .badge');
    if (badge) {
      badge.textContent = String(cartas.length);
      badge.classList.toggle('is-on', cartas.length > 0);
    }
    const imp = cartas.some(function (m) { return m.clasificacion === 'importante'; });
    if (imp) $('.play-root').setAttribute('data-importante', '1');
    else $('.play-root').removeAttribute('data-importante');
  }`;

js = js.replace(/  function renderHud\(estado, buzon\) \{[\s\S]*?\n  \}\n\n  function nombreDe\(id\) \{/, newRenderHud + '\n\n  function nombreDe(id) {');

js = js.replace(
  `    if (n === 0) return '0 €';
    return String(Math.round(n)) + ' €';`,
  `    if (n === 0) return '0 €';
    return String(Math.round(n)).replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.') + ' €';`
);

js = js.replace(
  `        btn.innerHTML = '<span class="bloque-mini-casa" aria-hidden="true"></span><span class="bloque-mini-txt">BLOQUE ' + d.label + '<br/><em>CERRADO</em></span>';`,
  `        btn.innerHTML = '<span class="bloque-fachada" aria-hidden="true"><span class="bloque-letra">' + d.label + '</span></span><span class="bloque-info"><strong>BLOQUE ' + d.label + '</strong><em>CERRADO</em></span>';`
);

js = js.replace(
  `          btn.innerHTML = '<span class="bloque-mini-casa" aria-hidden="true"></span><span class="bloque-mini-txt">BLOQUE ' + d.label + '<br/><strong>' + occ + '/' + cap + '</strong></span>';`,
  `          btn.innerHTML = '<span class="bloque-fachada" aria-hidden="true"><span class="bloque-letra">' + d.label + '</span></span><span class="bloque-info"><strong>BLOQUE ' + d.label + '</strong><span>' + occ + '/' + cap + '</span></span>';`
);

js = js.replace(
  `        btn.className = 'bloque-mini bloque-' + d.label.toLowerCase() + ((!blk || !blk.viviendas) ? ' is-cerrado' : '');`,
  `        btn.className = 'obj-bloque bloque-' + d.label.toLowerCase() + ((!blk || !blk.viviendas) ? ' is-cerrado' : '');`
);

js = js.replace(
  `      if (!next) proxBox.innerHTML = '<p class="muted-soft">Nada programado.</p>';`,
  `      if (!next) proxBox.innerHTML = '<p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p>';`
);

js = js.replace(
  `        row.className = 'pareja-row';
        const tok = function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) return '<img src="' + esc(t.url) + '" alt=""/>';
          return '<span class="cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
        };
        row.innerHTML = '<span class="pareja-faces">' + tok(ids[0]) + tok(ids[1]) + '</span>' +
          '<span class="pareja-nombres">' + esc(nombreDe(ids[0])) + ' · ' + esc(nombreDe(ids[1])) + '</span>';`,
  `        row.className = 'obj-pareja-fila';
        const tok = function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) return '<img class="obj-pareja-cara" src="' + esc(t.url) + '" alt=""/>';
          return '<span class="obj-pareja-cara cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
        };
        row.innerHTML = '<span class="obj-pareja-fotos">' + tok(ids[0]) + '<span class="obj-pareja-corazon" aria-hidden="true"></span>' + tok(ids[1]) + '</span>' +
          '<span class="obj-pareja-nombres">' + esc(nombreDe(ids[0])) + ' · ' + esc(nombreDe(ids[1])) + '</span>';`
);

js = js.replace(
  `      if (!parejas.length) strip.innerHTML = '<p class="muted-soft parejas-vacio">Todavía no hay parejas. El pueblo está en fase de mirarse de reojo.</p>';`,
  `      if (!parejas.length) strip.innerHTML = '<p class="obj-parejas-vacio">Todavía no hay parejas. El pueblo está en fase de mirarse de reojo.</p>';`
);

js = js.replace(
  `      if (!pend.length) prev.textContent = 'Sin cartas pendientes';`,
  `      if (!pend.length) prev.textContent = '';`
);

fs.writeFileSync(jsPath, js);
console.log('js ok');
