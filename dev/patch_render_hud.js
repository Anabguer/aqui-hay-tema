const fs = require('fs');
const p = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(p, 'utf8');

const start = js.indexOf('  function renderHud(estado, buzon) {');
const end = js.indexOf('  function placeHab(', start);
if (start < 0 || end < 0) {
  console.error('markers not found', start, end);
  process.exit(1);
}

const neu = `  function renderHud(estado, buzon) {
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
  }

`;

js = js.slice(0, start) + neu + js.slice(end);
if (!js.includes('.replace(/\\B(?=(\\d{3})+(?!\\d))/g')) {
  js = js.replace(
    "return String(Math.round(n)) + ' €';",
    "return String(Math.round(n)).replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.') + ' €';"
  );
}
fs.writeFileSync(p, js);
console.log('renderHud patched');
