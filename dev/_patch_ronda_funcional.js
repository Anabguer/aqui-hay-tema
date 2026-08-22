/**
 * Ronda funcional: slots canónicos, misiones tutorial, buzón leído, finale.
 * node dev/_patch_ronda_funcional.js
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

if (!js.includes('function buzonNoLeidos(')) {
  rep(
    '  function renderHud(estado, buzon) {',
    `  function buzonNoLeidos(estado, buzon) {
    if (estado && typeof estado.buzon_no_leidos === 'number') return estado.buzon_no_leidos;
    return (buzon || []).filter(function (m) {
      return (m.estado || '') === 'pendiente' && (m.canal || 'buzon') === 'buzon';
    }).length;
  }

  function enTutorialPrimerosPasos() {
    var tut = cacheEstado && cacheEstado.tutorial;
    return tut && tut.id === 'primeros_pasos' && !tut.finale_visto;
  }

  function bolitaMision(estado) {
    if (estado === 'cumplida') {
      return '<span class="mision-bolita cumplida" aria-hidden="true"><span class="mision-check">✓</span></span>';
    }
    if (estado === 'bloqueada') {
      return '<span class="mision-bolita bloqueada" aria-hidden="true"></span>';
    }
    return '<span class="mision-bolita pendiente" aria-hidden="true"></span>';
  }

  function renderHud(estado, buzon) {`,
    'helpers buzon tutorial'
  );
}

rep(
  `    const cartas = (buzon || []).filter(function (m) {
      return (m.estado || '') === 'pendiente' || (m.estado || '') === 'en_espera';
    });
    const nPend = cartas.length;`,
  `    const nPend = buzonNoLeidos(estado, buzon);`,
  'renderHud badge count'
);

rep(
  `    const pend = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });`,
  `    const pend = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    const nNoLeidos = buzonNoLeidos(cacheEstado, buzon);`,
  'shell pend filter'
);

rep(
  `        '<div class="stat-row"><span>Buzón pendiente</span><strong>' +
        (buzon || []).filter(function (m) { return (m.estado || '') === 'pendiente'; }).length +
        '</strong></div>';`,
  `        '<div class="stat-row"><span>Buzón pendiente</span><strong>' +
        nNoLeidos +
        '</strong></div>';`,
  'shell stats buzon'
);

if (!js.includes('function renderMisionesTutorial(')) {
  rep(
    '  function renderMisiones(misiones) {',
    `  function renderMisionesTutorial(items, list, teaser) {
    var sorted = items.slice().sort(function (a, b) {
      return ((a.orden || 0) - (b.orden || 0)) || String(a.titulo || '').localeCompare(String(b.titulo || ''));
    });
    if (teaser) teaser.textContent = 'Primeros pasos';
    list.innerHTML = '';
    sorted.forEach(function (m) {
      var row = document.createElement('div');
      var est = m.estado || 'pendiente';
      row.className = 'mision-row mision-' + est + ' mision-pp';
      var accBtn = '';
      if (m.accion && est !== 'bloqueada' && est !== 'cumplida') {
        accBtn = '<button type="button" class="mision-accion" data-mision-accion="' + esc(m.id || '') + '">' +
          esc(m.accion_label || 'Ir') + '</button>';
      }
      row.innerHTML =
        '<div class="mision-pp-head">' + bolitaMision(est) +
        '<strong class="mision-pp-tit">' + esc(m.titulo || '') + '</strong></div>' +
        '<p class="mision-pp-texto">' + esc(m.texto || '') + '</p>' + accBtn;
      var btnAcc = row.querySelector('[data-mision-accion]');
      if (btnAcc) {
        btnAcc.addEventListener('click', function (ev) {
          ev.stopPropagation();
          ejecutarAccionMision(m);
        });
      }
      list.appendChild(row);
    });
  }

  function renderMisiones(misiones) {`,
    'renderMisionesTutorial'
  );

  rep(
    `    var hoy = items.filter(function (m) { return !m.dia || (m.dia || 0) === dia; });
    if (teaser) {
      var pend = hoy.filter(function (m) { return (m.estado || '') === 'pendiente'; });
      teaser.textContent = pend.length
        ? (pend.length + ' objetivo' + (pend.length === 1 ? '' : 's') + ' pendiente' + (pend.length === 1 ? '' : 's'))
        : (hoy.length ? 'Nada pendiente hoy.' : 'Sin misiones hoy.');
    }
    if (!list) return;
    list.innerHTML = '';
    if (!hoy.length) {
      list.innerHTML = '<p class="muted">No hay misiones para hoy.</p>';
      return;
    }`,
    `    var hoy = items.filter(function (m) { return !m.dia || (m.dia || 0) === dia; });
    var pp = hoy.filter(function (m) { return (m.familia || '') === 'primeros_pasos'; });
    if (enTutorialPrimerosPasos() && pp.length >= 3) {
      if (list) renderMisionesTutorial(pp, list, teaser);
      return;
    }
    if (teaser) {
      var pend = hoy.filter(function (m) { return (m.estado || '') === 'pendiente'; });
      teaser.textContent = pend.length
        ? (pend.length + ' objetivo' + (pend.length === 1 ? '' : 's') + ' pendiente' + (pend.length === 1 ? '' : 's'))
        : (hoy.length ? 'Nada pendiente hoy.' : 'Sin misiones hoy.');
    }
    if (!list) return;
    list.innerHTML = '';
    if (!hoy.length) {
      list.innerHTML = '<p class="muted">No hay misiones para hoy.</p>';
      return;
    }`,
    'renderMisiones pp branch'
  );
}

rep(
  `    if ((m.estado || '') === 'pendiente') return { cls: 'estado-pendiente', txt: '' };
    if ((m.estado || '') === 'resuelto') return { cls: 'estado-cumplida', txt: 'Ya está' };
    return { cls: '', txt: '' };`,
  `    if ((m.estado || '') === 'pendiente') return { cls: 'estado-pendiente', txt: '' };
    if ((m.estado || '') === 'leido') return { cls: 'estado-leida', txt: '' };
    if ((m.estado || '') === 'en_espera') return { cls: 'estado-espera', txt: 'En espera' };
    if ((m.estado || '') === 'resuelto') return { cls: 'estado-cumplida', txt: 'Ya está' };
    return { cls: '', txt: '' };`,
  'estadoCarta leido'
);

rep(
  `      art.className = 'carta-msg' + (m.clasificacion === 'importante' ? ' importante' : '') +
        ((m.estado || '') === 'pendiente' ? ' no-leida' : '') + (st.cls ? ' ' + st.cls : '');`,
  `      art.className = 'carta-msg' + (m.clasificacion === 'importante' ? ' importante' : '') +
        ((m.estado || '') === 'pendiente' ? ' no-leida' : '') +
        ((m.estado || '') === 'leido' ? ' leida' : '') + (st.cls ? ' ' + st.cls : '');`,
  'renderBuzon leida class'
);

if (!js.includes('async function refreshOrgHoras(')) {
  rep(
    '  function fillOrganizar() {',
    `  async function refreshOrgHoras() {
    var hora = $('[data-org-hora]');
    if (!hora) return;
    hora.innerHTML = '';
    org.a = $('[data-org-a]').value;
    org.b = $('[data-org-b]').value;
    org.lugar = $('[data-org-lugar]').value;
    org.dia = parseInt($('[data-org-dia]').value, 10);
    if (!org.dia && cacheEstado && cacheEstado.reloj) org.dia = cacheEstado.reloj.dia_pueblo;
    var parts = org.modo === 'solo' ? [org.a] : [org.a, org.b].filter(Boolean);
    if (!org.lugar || !org.dia || parts.length < 1 || (org.modo !== 'solo' && parts.length < 2)) {
      var o0 = document.createElement('option');
      o0.value = '';
      o0.textContent = '—';
      hora.appendChild(o0);
      return;
    }
    var tipo = org.modo === 'solo' ? 'individual' : (org.tipo || 'conocerse');
    try {
      var r = await api('agenda.slots_compatibles', {
        participantes: parts,
        tipo: tipo,
        lugar_id: org.lugar,
        desde_dia: org.dia,
        max_dias: 1,
        max_slots: 48
      }, 'GET');
      var slots = (r.slots || []).filter(function (s) {
        return (s.dia || 0) === org.dia;
      });
      slots.sort(function (a, b) { return (a.hora || 0) - (b.hora || 0); });
      slots.forEach(function (s) {
        var h = s.hora;
        var o = document.createElement('option');
        o.value = String(h);
        o.textContent = s.etiqueta_hora || String(h).padStart(2, '0') + ':00';
        hora.appendChild(o);
      });
      if (!hora.options.length) {
        var oEmpty = document.createElement('option');
        oEmpty.value = '';
        oEmpty.textContent = 'Sin huecos hoy';
        hora.appendChild(oEmpty);
      }
      hora.value = hora.options.length ? hora.options[0].value : '';
      org.hora = parseInt(hora.value, 10) || 0;
    } catch (e) {
      var oErr = document.createElement('option');
      oErr.value = '';
      oErr.textContent = 'Sin huecos';
      hora.appendChild(oErr);
    }
  }

  async function fillOrganizar() {`,
    'refreshOrgHoras'
  );

  rep(
    `    const hora = $('[data-org-hora]');
    hora.innerHTML = '';
    const diaSel = org.dia || (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo) || 1;
    const hNow = (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.hora_actual) || 0;
    for (let h = 8; h <= 23; h++) {
      if (diaSel === (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo) && h <= hNow) continue;
      const o = document.createElement('option');
      o.value = String(h);
      o.textContent = String(h).padStart(2, '0') + ':00';
      hora.appendChild(o);
    }
    if (!hora.options.length) {
      const o = document.createElement('option');
      o.value = String(Math.min(23, hNow + 1));
      o.textContent = 'Siguiente hueco';
      hora.appendChild(o);
    }
    hora.value = hora.options.length ? hora.options[0].value : String(org.hora || 17);
    org.hora = parseInt(hora.value, 10);
    pintarOrgCaras();
    refreshTipos();
  }`,
    `    pintarOrgCaras();
    await refreshTipos();
    await refreshOrgHoras();
  }`,
    'fillOrganizar async slots'
  );
}

rep(
  `  if (orgA) orgA.addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });
  if (orgB) orgB.addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });`,
  `  if (orgA) orgA.addEventListener('change', async function () { await refreshTipos(); pintarOrgCaras(); await refreshOrgHoras(); });
  if (orgB) orgB.addEventListener('change', async function () { await refreshTipos(); pintarOrgCaras(); await refreshOrgHoras(); });
  var orgLug = $('[data-org-lugar]');
  var orgDia = $('[data-org-dia]');
  if (orgLug) orgLug.addEventListener('change', function () { refreshOrgHoras(); });
  if (orgDia) orgDia.addEventListener('change', function () { refreshOrgHoras(); });`,
  'org change listeners'
);

rep(
  `      if (r.tutorial) pintarTutorialMotor(r.tutorial);
    } else {`,
  `      if (r.tutorial) pintarTutorialMotor(r.tutorial);
      quizaMostrarTutFinale();
    } else {`,
  'proponer finale'
);

fs.writeFileSync(p, js);
console.log('ronda funcional patched');
