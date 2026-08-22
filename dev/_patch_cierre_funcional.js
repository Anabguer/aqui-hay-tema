/**
 * Cierre funcional: LAB inequívoco, retratos, org solo.
 * node dev/_patch_cierre_funcional.js
 */
const fs = require('fs');
const p = 'assets/js/play-v3.js';
let js = fs.readFileSync(p, 'utf8').replace(/\r\n/g, '\n');

function rep(from, to, label) {
  if (!js.includes(from)) {
    console.error('MISSING:', label);
    process.exit(1);
  }
  js = js.replace(from, to);
}

rep(
  `  const IS_LAB = qs.get('lab') === '1';`,
  `  const IS_LAB = (function () {
    if (typeof window !== 'undefined' && window.__AHT_LAB__ === 1) return true;
    var dl = document.body && document.body.getAttribute('data-lab');
    if (dl === '1') return true;
    var lv = qs.get('lab');
    return lv === '1' || lv === 'true';
  })();
  if (IS_LAB) {
    try { sessionStorage.setItem('aht_lab', '1'); } catch (e) {}
    try { console.log('%c[AHT LAB] Modo laboratorio activo (?lab=1)', 'color:#c45;font-weight:bold'); } catch (e2) {}
  }`,
  'IS_LAB sync'
);

rep(
  `      url = API + '?' + q.toString();
    } else {
      opts.headers = { 'Content-Type': 'application/json' };
      opts.body = JSON.stringify(Object.assign({ partida_id: partidaId }, body));
      url = API + '?action=' + encodeURIComponent(action);
    }`,
  `      url = API + '?' + q.toString();
    } else {
      opts.headers = { 'Content-Type': 'application/json' };
      opts.body = JSON.stringify(Object.assign({ partida_id: partidaId }, body));
      url = API + '?action=' + encodeURIComponent(action);
      if (IS_LAB) url += '&lab=1';
    }`,
  'POST lab query'
);

rep(
  `    var a = (selA && selA.value) || org.a;
    var b = (selB && selB.value) || org.b;
    if (!a || !b || a === b) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = '';
    [a, b].forEach(function (id) {`,
  `    var a = (selA && selA.value) || org.a;
    var b = (selB && selB.value) || org.b;
    var ids = [];
    if (org.modo === 'solo') {
      if (!a) { box.hidden = true; box.innerHTML = ''; return; }
      ids = [a];
    } else {
      if (!a || !b || a === b) { box.hidden = true; box.innerHTML = ''; return; }
      ids = [a, b];
    }
    box.hidden = false;
    box.innerHTML = '';
    ids.forEach(function (id) {`,
  'pintarOrgCaras solo'
);

rep(
  `    org = { tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };`,
  `    org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };`,
  'nuevaPartida org modo'
);

if (!js.includes('function renderProximoCaras')) {
  rep(
    `  function renderHud(estado, buzon) {`,
    `  function renderProximoCaras(enc) {
    if (!enc || !enc.participantes || !enc.participantes.length) return '';
    return enc.participantes.map(function (id) {
      var img = tokenDe(id);
      var nom = nombreDe(id);
      if (img) return '<span class="prox-cara"><img src="' + esc(img) + '" alt=""/></span>';
      return '<span class="prox-cara prox-cara-ini">' + esc((nom.charAt(0) || '?')) + '</span>';
    }).join('');
  }
  function renderHud(estado, buzon) {`,
    'renderProximoCaras fn'
  );
}

rep(
  `        proxBox.innerHTML = '<p><strong>' + esc(parts) + '</strong></p>' +
          '<p class="muted">' + esc(lug) +
          ' · Día ' + (next.dia || '?') + ' ' + String(horaN !== undefined ? horaN : '?').padStart(2, '0') + ':00</p>';`,
  `        proxBox.innerHTML = '<div class="prox-caras">' + renderProximoCaras(next) + '</div>' +
          '<p><strong>' + esc(parts) + '</strong></p>' +
          '<p class="muted">' + esc(lug) +
          ' · Día ' + (next.dia || '?') + ' ' + String(horaN !== undefined ? horaN : '?').padStart(2, '0') + ':00</p>';`,
  'proximo caras'
);

fs.writeFileSync(p, js);
console.log('cierre funcional patched');
