/**
 * Integra mapa canónico (imagen única + mapa_zonas.json) en play.php y play-v3.js
 */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

const MAP_HTML = `          <div class="mapa-canonico" data-mapa-canonico>
            <img class="mapa-canonico-bg" src="assets/play-v3/mapa_canonico.jpg" alt="Mapa del pueblo" width="1024" height="682"/>
            <div class="mapa-zonas-layer" data-mapa-zonas></div>
          </div>`;

const JS_BLOCK = `
  var cacheMapaZonas = null;
  var LUG_TO_ZONA = {
    lug_cafeteria: 'cafeteria', lug_biblioteca: 'biblioteca', lug_gimnasio: 'gimnasio',
    lug_restaurante: 'restaurante', lug_parque: 'parque', lug_picnic: 'parque', lug_mirador: 'parque',
    lug_bar: 'bar', lug_cine: 'cine', lug_discoteca: 'discoteca', lug_bingo: 'bingo'
  };
  var ZONA_TO_LUGS = {
    cafeteria: ['lug_cafeteria'], biblioteca: ['lug_biblioteca'], gimnasio: ['lug_gimnasio'],
    restaurante: ['lug_restaurante'], parque: ['lug_parque', 'lug_picnic', 'lug_mirador'],
    bar: ['lug_bar'], cine: ['lug_cine'], discoteca: ['lug_discoteca'], bingo: ['lug_bingo']
  };

  function initMapaCanonico() {
    var layer = $('[data-mapa-zonas]');
    if (!layer) return Promise.resolve(null);
    var v = (document.querySelector('meta[name="aht-ui"]') && document.querySelector('script[src*="play-v3.js"]')) ?
      (document.querySelector('script[src*="play-v3.js"]').src.split('v=')[1] || '') : '';
    return fetch('assets/play-v3/mapa_zonas.json?v=' + encodeURIComponent(v)).then(function (r) { return r.json(); }).then(function (cfg) {
      cacheMapaZonas = cfg;
      layer.innerHTML = '';
      var zonas = cfg.zonas || {};
      Object.keys(zonas).forEach(function (id) {
        var z = zonas[id];
        if (!z || !z.w || !z.h) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mapa-zona-hit';
        btn.setAttribute('data-zona', id);
        btn.setAttribute('aria-label', z.label || id);
        btn.style.left = z.x + '%';
        btn.style.top = z.y + '%';
        btn.style.width = z.w + '%';
        btn.style.height = z.h + '%';
        btn.innerHTML = '<span class="habs"></span>';
        layer.appendChild(btn);
      });
      return cfg;
    }).catch(function () { return null; });
  }

  function personasEnZona(zonaId) {
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.personas || []).forEach(function (p) {
        if (lugs.indexOf(p.destino_id) >= 0) out.push(p);
      });
    });
    return out;
  }

  function destinosOperativosZona(zonaId) {
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.destinos_operativos || []).forEach(function (d) {
        if (lugs.indexOf(d.id) >= 0) out.push(d);
      });
    });
    return out;
  }

  function placeHabEnZona(box, p, i) {
    var el = document.createElement('span');
    el.className = 'hab';
    el.setAttribute('data-residente', p.id);
    el.setAttribute('data-destino', p.destino_id);
    el.setAttribute('data-fase', p.fase || 'en_destino');
    el.setAttribute('data-emocion', p.emocion || 'neutro');
    if (p.hay_tema) el.setAttribute('data-hay-tema', '1');
    var cols = 3;
    var col = i % cols;
    var row = Math.floor(i / cols);
    el.style.left = (14 + col * 26) + '%';
    el.style.top = (18 + row * 24) + '%';
    if (p.token_url) {
      el.innerHTML = '<span class="cara"><img src="' + p.token_url + '" alt=""/></span>';
    } else {
      el.innerHTML = '<span class="cara cara-ini">' + (p.iniciales || '?') + '</span>';
    }
    if (p.hay_tema) {
      el.insertAdjacentHTML('beforeend', '<img class="sello-tema" src="assets/play-v3/marcas/sello_hay_tema.png" alt=""/>');
    }
    box.appendChild(el);
  }

  function abrirConsultaZona(zonaId) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var ops = destinosOperativosZona(zonaId);
    if (ops.length > 1) {
      $('.play-root').setAttribute('data-consulta', 'sel');
      $('[data-s-tit]').textContent = meta ? meta.label : zonaId;
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' · ');
      var box = $('[data-s-btns]');
      box.innerHTML = '';
      ops.forEach(function (d) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Ver ' + d.nombre.toLowerCase();
        b.addEventListener('click', function () { abrirQuienZona(zonaId, d.id); });
        box.appendChild(b);
      });
      var all = document.createElement('button');
      all.type = 'button';
      all.textContent = 'Quién hay aquí';
      all.addEventListener('click', function () { abrirQuienZona(zonaId, null); });
      box.appendChild(all);
      return;
    }
    abrirQuienZona(zonaId, ops[0] ? ops[0].id : null);
  }

  function abrirQuienZona(zonaId, destId) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var gente = personasEnZona(zonaId).filter(function (p) {
      return !destId || p.destino_id === destId;
    });
    $('.play-root').setAttribute('data-consulta', 'quien');
    $('[data-q-tit]').textContent = meta ? meta.label : zonaId;
    $('[data-q-sum]').textContent = gente.length
      ? (gente.length === 1 ? 'Hay alguien.' : ('Hay ' + gente.length + '.'))
      : 'No hay ni un alma.';
    var list = $('[data-q-list]');
    list.innerHTML = '';
    var groups = {};
    gente.forEach(function (p) {
      if (!groups[p.destino_nombre]) groups[p.destino_nombre] = [];
      groups[p.destino_nombre].push(p);
    });
    Object.keys(groups).forEach(function (dest) {
      var h = document.createElement('p');
      h.className = 'quien-dest';
      h.textContent = dest;
      list.appendChild(h);
      var ul = document.createElement('ul');
      groups[dest].forEach(function (p) {
        var li = document.createElement('li');
        li.textContent = p.nombre;
        ul.appendChild(li);
      });
      list.appendChild(ul);
    });
    var box = $('[data-q-btns]');
    box.innerHTML = '';
    destinosOperativosZona(zonaId).forEach(function (d) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Organizar en ' + d.nombre.toLowerCase();
      b.addEventListener('click', function () {
        org.lugar = d.id;
        setCapa('organizar');
        $('.play-root').removeAttribute('data-consulta');
        fillOrganizar();
      });
      box.appendChild(b);
    });
  }
`;

// --- play.php ---
let php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');

if (!php.includes('play-v3-mapa-canonico.css')) {
  php = php.replace(
    "  <link rel=\"stylesheet\" href=\"assets/css/play-v3-bloques-residencias.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>\"/>\n",
    "  <link rel=\"stylesheet\" href=\"assets/css/play-v3-bloques-residencias.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>\"/>\n  <link rel=\"stylesheet\" href=\"assets/css/play-v3-mapa-canonico.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>\"/>\n"
  );
}

php = php.replace(/\$ahtUi = 'v3-20260821[a-z]+';/, "$ahtUi = 'v3-20260821ab';");

if (!php.includes('data-mapa-canonico')) {
  php = php.replace(/          <div class="mapa-complejos">[\s\S]*?          <\/div>\n          <div class="edificios-layer"/,
    MAP_HTML + '\n          <div class="edificios-layer"');
}

fs.writeFileSync(path.join(root, 'play.php'), php, 'utf8');
console.log('play.php ok', php.includes('data-mapa-canonico'));

// --- play-v3.js ---
let js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');

if (!js.includes('function initMapaCanonico')) {
  js = js.replace('  function renderHud(estado, buzon) {', JS_BLOCK + '\n  function renderHud(estado, buzon) {');
}

const OLD_RENDER = `  function renderPueblo(pueblo) {
    cachePueblo = pueblo;
    const root = $('.play-root');
    (pueblo.complejos || []).forEach(function (cx) {
      const btn = $('[data-complejo="' + cx.id + '"]');
      if (!btn) return;
      btn.setAttribute('data-fase', cx.fase);
      btn.setAttribute('aria-label', cx.nombre || cx.id);
      const eti = btn.querySelector('.eti-mapa');
      if (eti) eti.textContent = cx.nombre || '';
      const etiP = btn.querySelector('.eti-pleno');
      const etiT = btn.querySelector('.eti-temp');
      if (etiP && etiT) {
        etiP.style.display = cx.fase === 'pleno' ? '' : 'none';
        etiT.style.display = cx.fase === 'pleno' ? 'none' : '';
      }
      if (cx.fase === 'pleno') root.setAttribute('data-pueblo-' + cx.id, 'pleno');
      else root.removeAttribute('data-pueblo-' + cx.id);
      pintarEdificios(btn, cx);
      const box = btn.querySelector('.habs');
      box.innerHTML = '';
      const used = {};
      (cx.visibles || []).forEach(function (p) {
        const i = used[p.destino_id] || 0;
        used[p.destino_id] = i + 1;
        placeHab(box, p, i, cx.id);
      });
      if (cx.extra > 0) {
        const mas = document.createElement('button');
        mas.type = 'button';
        mas.className = 'aforo-mas';
        mas.textContent = '+' + cx.extra;
        mas.setAttribute('aria-label', 'Ver las ' + cx.total + ' personas de ' + cx.nombre);
        mas.addEventListener('click', function (ev) {
          ev.stopPropagation();
          abrirQuien(cx.id, null);
        });
        box.appendChild(mas);
      }
    });
    applyFases(pueblo);
  }`;

const NEW_RENDER = `  function renderPueblo(pueblo) {
    cachePueblo = pueblo;
    var layer = $('[data-mapa-zonas]');
    if (!layer) return;
    $$('.mapa-zona-hit .habs').forEach(function (b) { b.innerHTML = ''; });
    var porZona = {};
    var extraZona = {};
    (pueblo.complejos || []).forEach(function (cx) {
      (cx.visibles || []).forEach(function (p) {
        var zid = LUG_TO_ZONA[p.destino_id];
        if (!zid) return;
        if (!porZona[zid]) porZona[zid] = [];
        porZona[zid].push(p);
      });
      (cx.personas || []).forEach(function (p) {
        var zid = LUG_TO_ZONA[p.destino_id];
        if (!zid) return;
        if (!extraZona[zid]) extraZona[zid] = 0;
      });
    });
    Object.keys(porZona).forEach(function (zid) {
      var btn = layer.querySelector('[data-zona="' + zid + '"]');
      if (!btn) return;
      var box = btn.querySelector('.habs');
      if (!box) return;
      porZona[zid].forEach(function (p, i) { placeHabEnZona(box, p, i); });
    });
  }`;

if (js.includes("const btn = $('[data-complejo=\"' + cx.id + \"']\");")) {
  js = js.replace(OLD_RENDER, NEW_RENDER);
  console.log('renderPueblo replaced');
} else if (js.includes('function renderPueblo(pueblo)')) {
  js = js.replace(/  function renderPueblo\(pueblo\) \{[\s\S]*?\n  \}\n\n  function applyFases/, NEW_RENDER + '\n\n  function applyFases');
  console.log('renderPueblo regex replaced');
}

if (!js.includes('abrirConsultaZona(zona.getAttribute')) {
  js = js.replace(
    `    const cx = ev.target.closest('.complejo[data-complejo]');
    if (cx) {
      abrirConsulta(cx.getAttribute('data-complejo'));
    }`,
    `    const zona = ev.target.closest('.mapa-zona-hit[data-zona]');
    if (zona) {
      abrirConsultaZona(zona.getAttribute('data-zona'));
      return;
    }
    const cx = ev.target.closest('.complejo[data-complejo]');
    if (cx) {
      abrirConsulta(cx.getAttribute('data-complejo'));
    }`
  );
}

if (!js.includes('initMapaCanonico().then')) {
  js = js.replace(
    '  ensurePartida().then(function () {\n    return refresh().then(function () { quizaMostrarTutIntro(); });\n  });',
    '  initMapaCanonico().then(function () {\n    return ensurePartida().then(function () {\n      return refresh().then(function () { quizaMostrarTutIntro(); });\n    });\n  });'
  );
}

fs.writeFileSync(path.join(root, 'assets/js/play-v3.js'), js, 'utf8');
console.log('js ok', js.includes('initMapaCanonico'), js.includes('abrirConsultaZona'));
