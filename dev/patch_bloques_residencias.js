const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let js = fs.readFileSync(jsPath, 'utf8');

const helpers = `
  let resBloqueVista = 'a';
  let resBuscaTxt = '';

  function bloquesAbiertosDe(partida) {
    const raw = (partida && partida.celeste && partida.celeste.bloques_abiertos) || ['a'];
    return Array.isArray(raw) ? raw : ['a'];
  }

  function bloquesImgKey(abiertos) {
    const hasB = abiertos.indexOf('b') >= 0;
    const hasC = abiertos.indexOf('c') >= 0;
    if (hasC) return 'abc';
    if (hasB) return 'ab';
    return 'a';
  }

  function residentesIdsBloque(partida, letra) {
    const key = 'bloque_' + letra;
    const blk = partida && partida[key];
    const out = [];
    (blk && blk.viviendas || []).forEach(function (v) {
      if (v && v.ocupante_id) out.push({ id: v.ocupante_id, vivienda: v.id || '' });
    });
    return out;
  }

  function htmlResCara(id) {
    const img = tokenDe(id);
    if (img) return '<img src="' + esc(img) + '" alt=""/>';
    return '<span class="res-cara-ini">' + esc(inicialDe(nombreDe(id))) + '</span>';
  }

  function abrirResidenciasCapa(bloque) {
    const abiertos = bloquesAbiertosDe(cacheInsp || {});
    if (!bloque || abiertos.indexOf(bloque) < 0) bloque = abiertos[0] || 'a';
    resBloqueVista = bloque;
    resBuscaTxt = '';
    const inp = $('[data-res-busca]');
    if (inp) inp.value = '';
    setCapa('residencias', 'right');
    renderResidenciasCapa();
  }

  function renderResidenciasCapa() {
    const partida = cacheInsp || {};
    const abiertos = bloquesAbiertosDe(partida);
    const tabs = $('[data-res-bloque-tabs]');
    const filtro = (resBuscaTxt || '').trim().toLowerCase();

    if (tabs) {
      tabs.innerHTML = '';
      ['a', 'b', 'c'].forEach(function (letra) {
        const abierto = abiertos.indexOf(letra) >= 0;
        if (abierto) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'res-placa' + (resBloqueVista === letra && !filtro ? ' is-on' : '');
          btn.setAttribute('data-res-bloque', letra);
          btn.innerHTML = '<span class="res-placa-medalla">' + letra.toUpperCase() + '</span>' +
            '<span class="res-placa-nom">Bloque ' + letra.toUpperCase() + '</span>';
          btn.addEventListener('click', function () {
            resBloqueVista = letra;
            resBuscaTxt = '';
            const inp = $('[data-res-busca]');
            if (inp) inp.value = '';
            renderResidenciasCapa();
          });
          tabs.appendChild(btn);
        } else {
          const span = document.createElement('span');
          span.className = 'res-placa is-cerrado';
          span.innerHTML = '<span class="res-placa-medalla">' + letra.toUpperCase() + '</span>' +
            '<span class="res-placa-nom">Bloque ' + letra.toUpperCase() + '</span>' +
            '<em class="res-placa-pronto">Pr\u00f3ximamente</em>';
          tabs.appendChild(span);
        }
      });
    }

    const grid = $('[data-res-grid]');
    if (!grid) return;
    grid.innerHTML = '';
    const items = [];
    if (filtro) {
      abiertos.forEach(function (letra) {
        residentesIdsBloque(partida, letra).forEach(function (r) {
          const nom = nombreDe(r.id).toLowerCase();
          if (nom.indexOf(filtro) >= 0) items.push({ id: r.id, vivienda: r.vivienda, bloque: letra });
        });
      });
    } else {
      residentesIdsBloque(partida, resBloqueVista).forEach(function (r) {
        items.push({ id: r.id, vivienda: r.vivienda, bloque: resBloqueVista });
      });
    }

    if (!items.length) {
      grid.innerHTML = '<p class="res-vacio">' + (filtro
        ? 'Nadie con ese nombre en los bloques abiertos.'
        : 'Todav\u00eda no hay nadie en este bloque.') + '</p>';
      return;
    }

    items.forEach(function (item) {
      const cell = document.createElement('div');
      cell.className = 'res-celda';
      const piso = item.vivienda ? '<p class="res-piso">Piso ' + esc(item.vivienda) + '</p>' : '';
      const tag = filtro ? '<p class="res-bloque-tag">Bloque ' + item.bloque.toUpperCase() + '</p>' : '';
      cell.innerHTML = '<div class="res-cara">' + htmlResCara(item.id) + '</div>' +
        '<p class="res-nombre">' + esc(nombreDe(item.id)) + '</p>' + piso + tag;
      grid.appendChild(cell);
    });
  }
`;

if (!js.includes('function bloquesAbiertosDe')) {
  js = js.replace('  function renderShellPanels(estado, buzon, diario) {', helpers + '\n  function renderShellPanels(estado, buzon, diario) {');
}

const oldBloquesRe = /    const bloques = \$\('\[data-bloques-row\]'\);[\s\S]*?bloques\.appendChild\(btn\);\s*\}\);\s*\}/;

const newBloques = `    const bloques = $('[data-bloques-row]');
    if (bloques) {
      const abiertos = bloquesAbiertosDe(partida);
      const imgKey = bloquesImgKey(abiertos);
      bloques.innerHTML = '<button type="button" class="obj-bloques-img-btn" data-open-bloques aria-label="Ver qui\u00e9n vive en cada bloque">' +
        '<img class="obj-bloques-img" src="assets/play-v3/shell/bloques_estado_' + imgKey + '.png" alt="Bloques residenciales" width="280" height="auto"/></button>';
    }`;

if (oldBloquesRe.test(js)) {
  js = js.replace(oldBloquesRe, newBloques);
} else {
  console.error('bloques block not found');
  process.exit(1);
}

if (!js.includes('data-open-bloques')) {
  js = js.replace(
    '    const open = ev.target.closest(\'[data-open]\');',
    '    const bloquesBtn = ev.target.closest(\'[data-open-bloques]\');\n    if (bloquesBtn) {\n      abrirResidenciasCapa(\'a\');\n      return;\n    }\n    const open = ev.target.closest(\'[data-open]\');'
  );
}

if (!js.includes('data-res-busca')) {
  js = js.replace(
    '  $(\'[data-org-dia]\').addEventListener(\'change\', refreshHorasOrganizar);',
    '  const resBuscaInp = $(\'[data-res-busca]\');\n  if (resBuscaInp) {\n    resBuscaInp.addEventListener(\'input\', function () {\n      resBuscaTxt = resBuscaInp.value;\n      renderResidenciasCapa();\n    });\n  }\n  $(\'[data-org-dia]\').addEventListener(\'change\', refreshHorasOrganizar);'
  );
}

if (!js.includes('residencias\')) renderResidenciasCapa')) {
  js = js.replace(
    '    renderVecinos();\n    $(\'[data-taller-msg]\').textContent',
    '    renderVecinos();\n    if ($(\'.play-root\').getAttribute(\'data-capa\') === \'residencias\') renderResidenciasCapa();\n    $(\'[data-taller-msg]\').textContent'
  );
}

fs.writeFileSync(jsPath, js, 'utf8');
console.log('patched play-v3.js OK');
