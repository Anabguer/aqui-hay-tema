/**
 * Parche funcional post-prueba Neni.
 * Ejecutar: node dev/_patch_neni_funcional.js
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
  "      return { config_id: 'playtest_01', seed: 'playtest-' + Date.now().toString(36) };",
  "      return { config_id: 'juego_v1', seed: 'lab-' + Date.now().toString(36) };",
  'configNueva lab'
);

if (!js.includes('function ahtLabAuditLog(')) {
  rep(
    '  async function api(action, body, method) {',
    `  function ahtLabAuditLog(payload) {
    if (!IS_LAB || !payload || !payload.lab_audit || !Array.isArray(payload.lab_audit.eventos)) return;
    if (typeof AhtLabAudit !== 'undefined' && AhtLabAudit.log) {
      try { AhtLabAudit.log(payload); return; } catch (e) {}
    }
    payload.lab_audit.eventos.forEach(function (ev) {
      var pref = ev.prefijo || '[AHT DEBUG]';
      console.log(pref, ev.datos);
      try { console.log(pref + ' JSON', JSON.stringify(ev.datos, null, 2)); } catch (e2) {}
    });
  }

  async function api(action, body, method) {`,
    'ahtLabAuditLog'
  );
  rep(
    `    if (IS_LAB && typeof AhtLabAudit !== 'undefined' && AhtLabAudit.log) {
      try { AhtLabAudit.log(data); } catch (e) {}
    }`,
    '    ahtLabAuditLog(data);',
    'api audit'
  );
}

if (!js.includes('LUGAR_TITULO_UI')) {
  rep(
    '  const LUGAR_NOMBRE_UI = {',
    `  const LUGAR_TITULO_UI = {
    lug_cafeteria: 'Cafetería', lug_biblioteca: 'Biblioteca', lug_gimnasio: 'Gimnasio',
    lug_restaurante: 'Restaurante', lug_parque: 'Parque', lug_bar: 'Bar',
    lug_cine: 'Cine', lug_discoteca: 'Discoteca', lug_bingo: 'Bingo'
  };
  function nombreLugarTitulo(id, fb) {
    if (id && LUGAR_TITULO_UI[id]) return LUGAR_TITULO_UI[id];
    return nombreLugarUi(id, fb);
  }
  const LUGAR_NOMBRE_UI = {`,
    'LUGAR_TITULO_UI'
  );
}

js = js.replace(/const TUT_PASOS = \[[\s\S]*?\];/, `const TUT_PASOS = [
    {
      tit: 'Bienvenida',
      txt: 'Este es tu pueblo: vecinos con vida propia, nueve lugares y un reloj que sigue aunque tú no hagas nada. Tú observas y propones planes; ellos deciden.'
    },
    {
      tit: 'Tus vecinos',
      txt: 'Al empezar hay tres vecinos en el pueblo. Puedes verlos en el mapa, en Vecinos y en sus fichas.'
    },
    {
      tit: 'Qué puedes hacer',
      txt: 'Mensajitos trae recados. Vecinos es la libreta del pueblo. Nuevo Plan propone un encuentro. Hoy en el pueblo marca tus objetivos del día.'
    },
    {
      tit: 'Empieza por Hoy en el pueblo',
      txt: 'Las primeras misiones te enseñarán jugando. Cuando cierres esta bienvenida, abriremos Hoy en el pueblo.'
    }
  ];`);

rep(
  '  function cerrarTutIntro(marcar) {',
  '  function cerrarTutIntro(marcar, irMisiones) {',
  'cerrarTutIntro sig'
);
rep(
  `    if (marcar) marcarTutIntroHecho();
    const reopen = $('[data-tut-reopen]');
    if (reopen) reopen.hidden = false;
  }`,
  `    if (marcar) marcarTutIntroHecho();
    if (irMisiones !== false && marcar) {
      setCapa('diario');
      var tab = $('[data-diario-tab="hoy"]');
      if (tab) tab.click();
    }
    const reopen = $('[data-tut-reopen]');
    if (reopen) reopen.hidden = false;
  }`,
  'cerrarTutIntro body'
);
rep(
  '    if (tutIntroIdx >= TUT_PASOS.length - 1) cerrarTutIntro(true);',
  '    if (tutIntroIdx >= TUT_PASOS.length - 1) cerrarTutIntro(true, true);',
  'tut fin'
);
rep(
  "  if (tutSkip) tutSkip.addEventListener('click', function () { cerrarTutIntro(true); });",
  "  if (tutSkip) tutSkip.addEventListener('click', function () { cerrarTutIntro(true, false); });",
  'tut skip'
);

const hudStart = js.indexOf('  function renderHud(estado, buzon) {');
const hudEnd = js.indexOf('  function placeHab(', hudStart);
if (hudStart < 0 || hudEnd < 0) {
  console.error('renderHud not found');
  process.exit(1);
}
const newHud = `  function renderHud(estado, buzon) {
    const rv = estado.reloj_vista || {};
    const reloj = estado.reloj || {};
    const diaNum = reloj.dia_pueblo;
    const diaNumEl = $('[data-dia-num]');
    if (diaNumEl) {
      diaNumEl.textContent = diaNum !== undefined && diaNum !== null ? ('DÍA ' + diaNum) : 'DÍA —';
    }
    const h = rv.hora !== undefined ? rv.hora : reloj.hora_actual;
    const ht = h === undefined ? '' : (String(h).padStart(2, '0') + ':00');
    $$('[data-dow]').forEach(function (el) {
      el.textContent = rv.dia_semana_ui || (diaNum !== undefined ? ('Día ' + diaNum) : '—');
    });
    $$('[data-fecha]').forEach(function (el) {
      el.textContent = rv.fecha_corta || '';
    });
    $$('[data-hora]').forEach(function (el) {
      el.textContent = ht || '—';
    });
    const metaEl = $('[data-dia-meta]');
    if (metaEl) {
      const bits = [rv.dia_semana_ui || '', rv.fecha_corta || '', ht].filter(function (x) { return x; });
      metaEl.textContent = bits.length ? bits.join(' · ') : '—';
    }
    const vida = estado.vida_pueblo || null;
    const pct = vida && typeof vida.corazon_pct === 'number' ? vida.corazon_pct : 0;
    const fillRect = $('[data-corazon-fill]');
    if (fillRect) {
      var fillH = 52 * (pct / 100);
      fillRect.setAttribute('y', String(52 - fillH));
      fillRect.setAttribute('height', String(fillH));
    }
    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';
    const cartas = (buzon || []).filter(function (m) {
      return (m.estado || '') === 'pendiente' || (m.estado || '') === 'en_espera';
    });
    const nPend = cartas.length;
    const badgeHud = $('.buzon .badge');
    if (badgeHud) {
      badgeHud.textContent = String(nPend);
      badgeHud.classList.toggle('is-on', nPend > 0);
    }
    const badgeObj = $('[data-buzon-badge]');
    if (badgeObj) {
      badgeObj.textContent = String(nPend);
      badgeObj.hidden = nPend <= 0;
    }
    const imp = cartas.some(function (m) { return m.clasificacion === 'importante'; });
    if (imp) $('.play-root').setAttribute('data-importante', '1');
    else $('.play-root').removeAttribute('data-importante');
  }

`;
js = js.slice(0, hudStart) + newHud + js.slice(hudEnd);

rep(
  `      const encs = (partida.encuentros || []).filter(function (e) {
        return e && (e.estado === 'programado' || e.estado === 'en_curso');
      });
      encs.sort(function (a, b) {
        const da = (a.dia || 0) * 100 + (a.hora_inicio || a.hora || 0);
        const db = (b.dia || 0) * 100 + (b.hora_inicio || b.hora || 0);
        return da - db;
      });
      const next = encs[0];
      if (!next) proxBox.innerHTML = '<p class="muted">Nada programado.</p>';
      else {
        const parts = (next.participantes || []).map(function (id) { return nombreDe(id); }).join(' · ');
        proxBox.innerHTML = '<p><strong>' + parts + '</strong></p>' +
          '<p class="muted">' + (next.lugar_nombre || next.lugar || 'Lugar') +
          ' · Día ' + (next.dia || '?') + ' ' + String(next.hora_inicio || next.hora || '?').padStart(2, '0') + ':00</p>';
      }`,
  `      var next = estado.proximo_encuentro || null;
      if (!next) {
        const encs = (partida.encuentros || []).filter(function (e) {
          return e && (e.estado === 'programado' || e.estado === 'en_curso');
        });
        encs.sort(function (a, b) {
          const da = (a.dia || 0) * 100 + (a.hora_inicio || a.hora || 0);
          const db = (b.dia || 0) * 100 + (b.hora_inicio || b.hora || 0);
          return da - db;
        });
        next = encs[0] || null;
      }
      if (!next) proxBox.innerHTML = '<p class="muted">Nada programado.</p>';
      else {
        const parts = (next.participantes_nombres || (next.participantes || []).map(function (id) { return nombreDe(id); })).join(' · ');
        const lug = next.lugar_nombre || nombreLugarTitulo(next.lugar, next.lugar);
        const horaN = next.hora_inicio !== undefined ? next.hora_inicio : next.hora;
        proxBox.innerHTML = '<p><strong>' + esc(parts) + '</strong></p>' +
          '<p class="muted">' + esc(lug) +
          ' · Día ' + (next.dia || '?') + ' ' + String(horaN !== undefined ? horaN : '?').padStart(2, '0') + ':00</p>';
      }`,
  'proximo'
);

rep(
  `    const hora = $('[data-org-hora]');
    hora.innerHTML = '';
    for (let h = 8; h <= 23; h++) {
      const o = document.createElement('option');
      o.value = String(h);
      o.textContent = String(h).padStart(2, '0') + ':00';
      hora.appendChild(o);
    }
    hora.value = String(org.hora || 17);
    refreshTipos();`,
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
    refreshTipos();`,
  'horas'
);

rep(
  `    if (r.ok) {
      toast(r.rechazada
        ? (r.mensaje_ui || 'No han quedado. Mira el registro tecnico.')
        : (r.mensaje_ui || 'Propuesto. Ellas siguen a lo suyo.'));
      setCapa('');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);
    } else {
      toast(r.mensaje_ui || 'Asi no se ha podido organizar.');
      await refresh();
    }`,
  `    if (r.ok) {
      var na = nombreDe(org.a);
      var nb = nombreDe(org.b);
      var lugUi = nombreLugarTitulo(org.lugar, org.lugar);
      if (r.rechazada) {
        toast(r.mensaje_ui || ('Plan rechazado: ' + na + ' y ' + nb + ' en ' + lugUi + '.'));
      } else {
        var horaUi = String(org.hora).padStart(2, '0') + ':00';
        var msg = r.mensaje_ui || ('Plan aceptado: ' + na + ' y ' + nb + ' en ' + lugUi + ', día ' + org.dia + ' a las ' + horaUi + '.');
        if (r.hora_ajustada || (r.propuesta && r.propuesta.hora_ajustada)) {
          msg += ' (El motor ajustó la hora al hueco disponible.)';
        }
        toast(msg);
      }
      setCapa('');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);
    } else {
      toast(r.mensaje_ui || 'No se ha podido organizar el plan.');
      await refresh();
    }`,
  'proponer'
);

rep(
  '    else if (Array.isArray(misiones)) items = misiones;',
  `    else if (misiones && misiones.misiones_hoy && Array.isArray(misiones.misiones_hoy.misiones)) items = misiones.misiones_hoy.misiones;
    else if (Array.isArray(misiones)) items = misiones;`,
  'misiones'
);

fs.writeFileSync(p, js);
console.log('OK');
