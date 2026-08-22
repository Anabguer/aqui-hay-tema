const fs = require('fs');
const path = require('path');
const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(jsPath, 'utf8');

const helpers = `
  function horaEnc(enc) {
    if (!enc) return 0;
    if (enc.hora_inicio != null) return Number(enc.hora_inicio);
    return Number(enc.hora || 0);
  }

  function relojAbs(dia, hora) {
    return (Number(dia) || 0) * 24 + (Number(hora) || 0);
  }

  function esEncuentroFuturo(enc, estado) {
    if (!enc) return false;
    const st = String(enc.estado || '');
    if (st !== 'programado' && st !== 'en_curso') return false;
    const reloj = (estado && estado.reloj) || {};
    const now = relojAbs(reloj.dia_pueblo, reloj.hora_actual);
    return relojAbs(enc.dia, horaEnc(enc)) >= now;
  }

  function encuentrosFuturos(partida, estado) {
    return ((partida && partida.encuentros) || []).filter(function (e) {
      return esEncuentroFuturo(e, estado);
    }).sort(function (a, b) {
      return relojAbs(a.dia, horaEnc(a)) - relojAbs(b.dia, horaEnc(b));
    });
  }

  function formatPlanMeta(enc, estado) {
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    const hora = String(horaEnc(enc)).padStart(2, '0') + ':00';
    const reloj = (estado && estado.reloj) || {};
    if (Number(enc.dia) === Number(reloj.dia_pueblo)) return lugar + ' \u00b7 Hoy ' + hora;
    return lugar + ' \u00b7 D\u00eda ' + (enc.dia || '?') + ' \u00b7 ' + hora;
  }

  function carasPlanHtml(ids) {
    return ids.slice(0, 2).map(function (id) {
      const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
      if (t && t.url) return '<img src="' + esc(t.url) + '" alt=""/>';
      return '<span class="cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
    }).join('');
  }

  function htmlProximoPlan(enc, estado) {
    const ids = enc.participantes || [];
    return '<div class="prox-faces">' + carasPlanHtml(ids) + '</div>' +
      '<p class="prox-nombres">' + esc(ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ')) + '</p>' +
      '<p class="prox-meta"><span class="prox-meta-ico" aria-hidden="true"></span>' + esc(formatPlanMeta(enc, estado)) + '</p>';
  }

  function renderAgendaPlanes() {
    const box = document.querySelector('[data-agenda-list]');
    if (!box) return;
    const fut = encuentrosFuturos(cacheInsp, cacheEstado);
    box.innerHTML = '';
    if (!fut.length) {
      box.innerHTML = '<p class="lista-vacia">Nada en agenda.</p>';
      return;
    }
    fut.forEach(function (enc) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'agenda-fila';
      const ids = enc.participantes || [];
      btn.innerHTML = '<span class="agenda-fila-fotos">' + carasPlanHtml(ids) + '</span>' +
        '<span class="agenda-fila-cuerpo"><span class="agenda-fila-nombres">' +
        esc(ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ')) + '</span>' +
        '<span class="agenda-fila-meta">' + esc(formatPlanMeta(enc, cacheEstado)) + '</span></span>';
      box.appendChild(btn);
    });
  }

`;

if (!s.includes('function htmlProximoPlan')) {
  s = s.replace('  function renderShellPanels(estado, buzon, diario) {', helpers + '  function renderShellPanels(estado, buzon, diario) {');
}

const proxRe = /const proxBox = \$\('\[data-proximo-plan\]'\);[\s\S]*?const strip = \$\('\[data-parejas-strip\]'\);/;
const proxNew = `const proxBox = $('[data-proximo-plan]');
    const verPlanes = $('.obj-ver-planes');
    const futuros = encuentrosFuturos(partida, estado);
    const next = futuros[0] || null;
    if (proxBox) {
      if (!next) proxBox.innerHTML = '<p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p>';
      else proxBox.innerHTML = htmlProximoPlan(next, estado);
    }
    if (verPlanes) verPlanes.hidden = futuros.length <= 1;

    const strip = $('[data-parejas-strip]');`;

if (!proxRe.test(s)) throw new Error('proxBox block not found');
s = s.replace(proxRe, proxNew);

if (!s.includes("name === 'agenda'")) {
  s = s.replace(
    "if (name === 'diario') $('[data-diario-tab=\"hoy\"]').click();",
  "if (name === 'agenda') renderAgendaPlanes();\n      if (name === 'diario') $('[data-diario-tab=\"hoy\"]').click();"
  );
}

fs.writeFileSync(jsPath, s, 'utf8');
console.log('patched proximo polaroid js');
