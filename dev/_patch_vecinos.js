const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8');

const newRender = `  let vecBuscaTxt = '';

  function renderVecinos() {
    const box = $('[data-vecinos-list]');
    box.innerHTML = '';
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const filtro = (vecBuscaTxt || '').trim().toLowerCase();
    const ids = Object.keys(res).filter(function (id) {
      const r = res[id];
      const nom = ((r.identidad_publica && r.identidad_publica.nombre) || id).toLowerCase();
      return !filtro || nom.indexOf(filtro) >= 0;
    });
    ids.sort(function (a, b) {
      const na = (res[a].identidad_publica && res[a].identidad_publica.nombre) || a;
      const nb = (res[b].identidad_publica && res[b].identidad_publica.nombre) || b;
      return String(na).localeCompare(String(nb), 'es');
    });
    if (!ids.length) {
      box.innerHTML = '<p class="lista-vacia vecinos-vacio">' +
        (filtro ? 'Nadie con ese nombre.' : 'Todav\u00eda no hay vecinos en esta partida.') + '</p>';
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

const re = /  function renderVecinos\(\) \{[\s\S]*?  \}\r?\n\r?\n  async function abrirFicha/;
if (!s.includes('vecBuscaTxt')) {
  if (!re.test(s)) { console.error('pattern fail'); process.exit(1); }
  s = s.replace(re, newRender + '\n\n  async function abrirFicha');
}

const listener = `  const vecBuscaInp = $('[data-vec-busca]');
  if (vecBuscaInp) {
    vecBuscaInp.addEventListener('input', function () {
      vecBuscaTxt = vecBuscaInp.value;
      renderVecinos();
    });
  }
`;

if (!s.includes('data-vec-busca')) {
  s = s.replace("  $('[data-org-a]').addEventListener('change', refreshTipos);", listener + "\n  $('[data-org-a]').addEventListener('change', refreshTipos);");
}

fs.writeFileSync(p, s, 'utf8');
console.log('js ok', s.includes('vecino-celda'));
