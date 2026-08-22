const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8');

const newBlock = `  let vecBuscaTxt = '';
  let vecFiltroActivo = 'todos';
  let resBloqueActivo = 'a';
  let resBuscaTxt = '';

  function diaPuebloInsp() {
    const insp = cacheInsp || {};
    const reloj = insp.reloj || {};
    return Number(reloj.dia_pueblo || (cacheEstado && cacheEstado.dia_pueblo) || 1);
  }

  function esVecinoNuevo(id) {
    const res = (cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id]) || {};
    if (res.flag_nuevo) return true;
    const llegadas = (cacheInsp && cacheInsp.llegadas) || {};
    const diaActual = diaPuebloInsp();
    const ventana = 10;
    const tut = llegadas.tutorial_hechas || [];
    for (let i = 0; i < tut.length; i++) {
      const row = tut[i];
      if (row && row.catalog_id === id) {
        const comp = Number(llegadas.tutorial_completado_dia || 1);
        if (diaActual - comp <= ventana) return true;
      }
    }
    const hist = llegadas.historial || [];
    for (let j = 0; j < hist.length; j++) {
      const h = hist[j];
      if (!h || h.catalog_id !== id) continue;
      if (h.resultado === 'llegado' && diaActual - Number(h.dia || 0) <= ventana) return true;
    }
    return false;
  }

  function renderVecinos() {
    const box = $('[data-vecinos-list]');
    if (!box) return;
    box.classList.add('vecinos-grid');
    box.innerHTML = '';
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const filtroTxt = (vecBuscaTxt || '').trim().toLowerCase();
    const ids = Object.keys(res).filter(function (id) {
      const r = res[id];
      if ((r.presencia || 'residente') !== 'residente') return false;
      const nom = ((r.identidad_publica && r.identidad_publica.nombre) || id).toLowerCase();
      if (filtroTxt && nom.indexOf(filtroTxt) < 0) return false;
      if (vecFiltroActivo === 'nuevos' && !esVecinoNuevo(id)) return false;
      return true;
    });
    ids.sort(function (a, b) {
      const na = (res[a].identidad_publica && res[a].identidad_publica.nombre) || a;
      const nb = (res[b].identidad_publica && res[b].identidad_publica.nombre) || b;
      return String(na).localeCompare(String(nb), 'es');
    });
    const cuenta = $('[data-vecinos-count]');
    if (cuenta) cuenta.textContent = ids.length + ' vecinos';
    if (!ids.length) {
      box.innerHTML = '<p class="lista-vacia vecinos-vacio">' +
        (filtroTxt || vecFiltroActivo === 'nuevos' ? 'Nadie con ese filtro.' : 'Todav\u00eda no hay vecinos en esta partida.') + '</p>';
      return;
    }
    ids.forEach(function (id) {
      const r = res[id];
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'vecino-celda';
      const img = tokenDe(id);
      const nom = (r.identidad_publica && r.identidad_publica.nombre) || id;
      const ini = nom.charAt(0) || '?';
      b.innerHTML = '<div class="vecino-cara">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="vecino-ini">' + esc(ini) + '</span>') +
        '</div><p class="vecino-nom">' + esc(nom) + '</p>';
      b.addEventListener('click', function () { abrirFicha(id); });
      box.appendChild(b);
    });
  }`;

const re = /  let vecBuscaTxt = '';[\s\S]*?  function renderVecinos\(\) \{[\s\S]*?  \}\r?\n\r?\n  var RES_BLOQUES/;
if (!re.test(s)) {
  console.error('pattern fail renderVecinos');
  process.exit(1);
}
s = s.replace(re, newBlock + '\n\n  var RES_BLOQUES');

const filtros = `  $$('[data-vec-filtro]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      vecFiltroActivo = btn.getAttribute('data-vec-filtro') || 'todos';
      $$('[data-vec-filtro]').forEach(function (b) {
        b.classList.toggle('is-on', b === btn);
      });
      renderVecinos();
    });
  });

`;

if (!s.includes('data-vec-filtro')) {
  s = s.replace(
    '  const vecBuscaInp = $(\'[data-vec-busca]\');',
    filtros + '  const vecBuscaInp = $(\'[data-vec-busca]\');'
  );
}

fs.writeFileSync(p, s, 'utf8');
console.log('patch ok', s.includes('vecFiltroActivo'), s.includes('data-vec-filtro'));
