/* eslint-disable */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const jsPath = path.join(root, 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(jsPath, 'utf8');

function ensureOnce(marker, insertBefore, block) {
  if (s.includes(marker)) return;
  const idx = s.indexOf(insertBefore);
  if (idx < 0) throw new Error('anchor not found: ' + insertBefore);
  s = s.slice(0, idx) + block + s.slice(idx);
}

const AGENDA_HELPERS = `
  const AGENDA_DEMO = IS_LAB && qs.get('agenda_demo') === '1';

  function relojAbs(dia, hora) {
    return (Number(dia) || 0) * 24 + (Number(hora) || 0);
  }

  function horaEnc(enc) {
    if (!enc) return 0;
    if (enc.hora_inicio != null) return Number(enc.hora_inicio);
    return Number(enc.hora || 0);
  }

  function esEncuentroFuturo(enc, estado) {
    if (!enc || enc.demo) return false;
    const st = String(enc.estado || '');
    if (st !== 'programado' && st !== 'en_curso') return false;
    const reloj = (estado && estado.reloj) || (cacheEstado && cacheEstado.reloj) || {};
    const now = relojAbs(reloj.dia_pueblo, reloj.hora_actual);
    return relojAbs(enc.dia, horaEnc(enc)) > now;
  }

  function idsResidentesActivos() {
    const res = (cacheInsp && cacheInsp.residentes) || {};
    return Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; });
  }

  function demoEncuentrosFuturos(estado) {
    if (!AGENDA_DEMO) return [];
    const ids = idsResidentesActivos();
    if (ids.length < 2) return [];
    const reloj = (estado && estado.reloj) || { dia_pueblo: 3, hora_actual: 14 };
    const d0 = Number(reloj.dia_pueblo) || 1;
    const h0 = Math.max(Number(reloj.hora_actual) + 1, 16);
    const a = ids[0];
    const b = ids[1];
    const c = ids[2] || ids[0];
    return [
      { id: 'lab_demo_enc_1', demo: true, participantes: [a, b], lugar: 'lug_biblioteca', dia: d0, hora: h0, estado: 'programado' },
      { id: 'lab_demo_enc_2', demo: true, participantes: [a, c], lugar: 'lug_cafeteria', dia: d0, hora: Math.min(h0 + 2, 22), estado: 'programado' },
      { id: 'lab_demo_enc_3', demo: true, participantes: [b, c], lugar: 'lug_parque', dia: d0 + 1, hora: 11, estado: 'programado' },
    ];
  }

  function encuentrosFuturos(partida, estado) {
    const raw = ((partida && partida.encuentros) || []).filter(function (e) {
      return esEncuentroFuturo(e, estado);
    });
    let fut = raw.slice();
    if (AGENDA_DEMO && fut.length < 3) {
      demoEncuentrosFuturos(estado).forEach(function (d) {
        if (fut.length >= 3) return;
        if (!fut.some(function (x) { return x.id === d.id; })) fut.push(d);
      });
    }
    fut.sort(function (a, b) {
      return relojAbs(a.dia, horaEnc(a)) - relojAbs(b.dia, horaEnc(b));
    });
    return fut;
  }

  function diaCortoPlan(enc, estado) {
    const reloj = (estado && estado.reloj) || {};
    if (Number(enc.dia) === Number(reloj.dia_pueblo)) return null;
    const dias = (estado && estado.reloj_vista && estado.reloj_vista.proximos_dias) || [];
    for (let i = 0; i < dias.length; i++) {
      const d = dias[i];
      if (Number(d.dia_pueblo) === Number(enc.dia)) {
        const sem = String(d.dia_semana_ui || '').slice(0, 3);
        const num = String(d.fecha_corta || '').split('/')[0];
        return (sem + ' ' + num).trim();
      }
    }
    return 'D\u00eda ' + enc.dia;
  }

  function formatPlanMeta(enc, estado) {
    const lugar = nombreLugar(enc.lugar_nombre || enc.lugar);
    const hora = String(horaEnc(enc)).padStart(2, '0') + ':00';
    const dc = diaCortoPlan(enc, estado);
    if (!dc) return lugar + ' \u00b7 Hoy ' + hora;
    return lugar + ' \u00b7 ' + dc + ' \u00b7 ' + hora;
  }

  function carasPlanHtml(ids) {
    return ids.slice(0, 2).map(function (id) {
      const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
      if (t && t.url) return '<img class="plan-cara" src="' + esc(t.url) + '" alt=""/>';
      return '<span class="plan-cara cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
    }).join('');
  }

  function nombresPlanTxt(ids) {
    return ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ');
  }

  function htmlProximoPlan(enc, estado) {
    const ids = enc.participantes || [];
    return '<div class="prox-faces">' + carasPlanHtml(ids) + '</div>' +
      '<p class="prox-nombres">' + esc(nombresPlanTxt(ids)) + '</p>' +
      '<p class="prox-meta">' + esc(formatPlanMeta(enc, estado)) + '</p>';
  }

  function htmlAgendaFila(enc, estado, focus) {
    const ids = enc.participantes || [];
    return '<span class="agenda-fila-fotos">' + carasPlanHtml(ids) + '</span>' +
      '<span class="agenda-fila-cuerpo">' +
      '<span class="agenda-fila-nombres">' + esc(nombresPlanTxt(ids)) + '</span>' +
      '<span class="agenda-fila-meta">' + esc(formatPlanMeta(enc, estado)) + '</span>' +
      '</span>';
  }

  let planNotifTimer = null;
  let planNotifEncId = null;

  function clearPlanNotifTimer() {
    if (planNotifTimer) clearTimeout(planNotifTimer);
    planNotifTimer = null;
  }

  function hidePlanNotif() {
    const h = $('[data-plan-notif]');
    if (h) {
      h.classList.remove('is-on');
      h.hidden = true;
    }
    clearPlanNotifTimer();
  }

  function schedulePlanNotifHide(ms) {
    clearPlanNotifTimer();
    planNotifTimer = setTimeout(function () {
      const h = $('[data-plan-notif]');
      if (h && !h.matches(':hover')) hidePlanNotif();
    }, ms);
  }

  function notificarPlanConfirmado(enc) {
    if (!enc) return;
    planNotifEncId = enc.id || null;
    const host = $('[data-plan-notif]');
    if (!host) return;
    const ids = enc.participantes || [];
    const nomEl = $('[data-plan-notif-nombres]');
    const metaEl = $('[data-plan-notif-meta]');
    if (nomEl) nomEl.textContent = ids.map(function (id) { return nombreDe(id); }).join(' + ');
    if (metaEl) metaEl.textContent = formatPlanMeta(enc, cacheEstado);
    host.hidden = false;
    host.classList.add('is-on');
    schedulePlanNotifHide(6200);
  }

  function renderAgendaPlanes(highlightId) {
    const box = $('[data-agenda-list]');
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
      btn.className = 'agenda-fila' + (highlightId && enc.id === highlightId ? ' is-focus' : '');
      if (enc.id) btn.setAttribute('data-enc-id', enc.id);
      btn.innerHTML = htmlAgendaFila(enc, cacheEstado, highlightId && enc.id === highlightId);
      box.appendChild(btn);
    });
    if (highlightId) {
      const el = box.querySelector('[data-enc-id="' + highlightId + '"]');
      if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }

  function abrirAgendaPlanes(highlightId) {
    renderAgendaPlanes(highlightId || null);
    setCapa('agenda');
  }

`;

ensureOnce('function relojAbs(dia, hora)', '  function findDestinoMeta(destId)', AGENDA_HELPERS);

// Replace proximo block in renderShellPanels
const proxOld = `    const encs = (partida.encuentros || []).filter(function (e) {
      return e && (e.estado === 'programado' || e.estado === 'en_curso');
    });`;

const proxNew = `    const futuros = encuentrosFuturos(partida, estado);`;

if (s.includes(proxOld)) {
  s = s.replace(proxOld, proxNew);
} else if (!s.includes('const futuros = encuentrosFuturos')) {
  throw new Error('proximo filter block not found');
}

const proxBoxOld = `    const proxBox = $('[data-proximo-plan]');
    if (proxBox) {
      encs.sort(function (a, b) {
        const da = (a.dia || 0) * 100 + (a.hora_inicio || a.hora || 0);
        const db = (b.dia || 0) * 100 + (b.hora_inicio || b.hora || 0);
        return da - db;
      });
      let next = encs[0];
      if (!next) proxBox.innerHTML = '<p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p>';
      else {
        const ids = next.participantes || [];
        const faces = ids.slice(0, 2).map(function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) return '<img src="' + esc(t.url) + '" alt=""/>';
          return '<span class="cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
        }).join('');
        proxBox.innerHTML = '<div class="prox-faces">' + faces + '</div>' +
          '<p class="prox-nombres">' + ids.map(function (id) { return esc(nombreDe(id)); }).join(' · ') + '</p>' +
          '<p class="prox-meta">' + esc(nombreLugar(next.lugar_nombre || next.lugar)) +
          ' · ' + String(next.hora_inicio || next.hora || '?').padStart(2, '0') + ':00</p>';
      }
    }`;

const proxBoxNew = `    const proxBox = $('[data-proximo-plan]');
    const pendBtn = $('[data-planes-pend]');
    const pendTxt = $('[data-planes-pend-txt]');
    if (proxBox) {
      const next = futuros[0];
      if (!next) {
        proxBox.innerHTML = '<p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p>';
      } else {
        proxBox.innerHTML = htmlProximoPlan(next, estado);
      }
    }
    if (pendBtn) {
      const extra = Math.max(0, futuros.length - 1);
      if (extra > 0) {
        pendBtn.hidden = false;
        if (pendTxt) {
          pendTxt.textContent = extra === 1 ? '1 plan pendiente' : (extra + ' planes pendientes');
        }
      } else {
        pendBtn.hidden = true;
      }
    }`;

if (s.indexOf("const proxBox = $('[data-proximo-plan]');") >= 0 && s.indexOf('htmlProximoPlan(next, estado)') < 0) {
  s = s.replace(/const proxBox = \$\('\[data-proximo-plan\]'\);\s*if \(proxBox\) \{[\s\S]*?\n    \}\n\n    const strip = \$\('\[data-parejas-strip\]'\);/,
    proxBoxNew + "\n\n    const strip = $('[data-parejas-strip]');");
}

// proponer: use plan notification instead of long toast
const proponerOld = `    if (r.ok) {
      toast(r.rechazada
        ? (r.mensaje_ui || 'No han quedado. Mira el registro tecnico.')
        : (r.mensaje_ui || 'Propuesto. Ellas siguen a lo suyo.'));
      setCapa('');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);
    }`;

const proponerNew = `    if (r.ok) {
      setCapa('');
      await refresh();
      if (r.rechazada) {
        toast(r.mensaje_ui || 'No han quedado.');
      } else if (r.programado) {
        const enc = r.encuentro || null;
        if (enc) notificarPlanConfirmado(enc);
        else {
          const fut = encuentrosFuturos(cacheInsp, cacheEstado);
          if (fut.length) notificarPlanConfirmado(fut[fut.length - 1]);
        }
      } else {
        toast('Propuesto. Ellas siguen a lo suyo.');
      }
      if (r.tutorial) pintarTutorialMotor(r.tutorial);
    }`;

if (s.includes(proponerOld)) {
  s = s.replace(proponerOld, proponerNew);
} else if (!s.includes('notificarPlanConfirmado(enc)')) {
  throw new Error('proponer block not found');
}

// Event handlers
const handlerAnchor = `      if (name === 'organizar') fillOrganizar();`;
const handlerAdd = `      if (name === 'agenda') renderAgendaPlanes(null);
`;

if (!s.includes("name === 'agenda'")) {
  s = s.replace(handlerAnchor, handlerAdd + handlerAnchor);
}

const clickAnchor = `    const open = ev.target.closest('[data-open]');`;
const clickAdd = `    const pend = ev.target.closest('[data-planes-pend]');
    if (pend) {
      abrirAgendaPlanes(null);
      return;
    }
    const notif = ev.target.closest('[data-plan-notif-btn]');
    if (notif) {
      hidePlanNotif();
      abrirAgendaPlanes(planNotifEncId);
      return;
    }
`;

if (!s.includes('[data-planes-pend]')) {
  s = s.replace(clickAnchor, clickAdd + clickAnchor);
}

// Plan notif hover pause
const initAnchor = `  window.addEventListener('resize', layout);`;
const initAdd = `  const planNotifHost = $('[data-plan-notif]');
  if (planNotifHost) {
    planNotifHost.addEventListener('mouseenter', function () { clearPlanNotifTimer(); });
    planNotifHost.addEventListener('mouseleave', function () { schedulePlanNotifHide(2800); });
  }
`;

if (!s.includes('planNotifHost')) {
  s = s.replace(initAnchor, initAdd + initAnchor);
}

fs.writeFileSync(jsPath, s);
console.log('patched agenda/planes in play-v3.js');
