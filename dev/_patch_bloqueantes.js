/**
 * Parche bloqueantes: misiones, debug copy, configNueva seed.
 * node dev/_patch_bloqueantes.js
 */
const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8').replace(/\r\n/g, '\n');

function rep(from, to, label) {
  if (!s.includes(from)) {
    console.error('MISSING:', label);
    process.exit(1);
  }
  s = s.replace(from, to);
}

rep(
  `  function configNueva(forceFreshSeed) {
    const c = qs.get('config');
    if (c) {
      const o = { config_id: c };
      if (qs.get('seed')) {
        o.seed = qs.get('seed');
      }
      return o;
    }
    return CONFIG_JUEGO;
  }`,
  `  function configNueva(forceFreshSeed) {
    const c = qs.get('config');
    if (c) {
      const o = { config_id: c };
      if (qs.get('seed')) {
        o.seed = qs.get('seed');
      } else if (forceFreshSeed) {
        o.seed = 'ui-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
      }
      return o;
    }
    const o = Object.assign({}, CONFIG_JUEGO);
    if (forceFreshSeed) {
      o.seed = 'ui-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }
    return o;
  }`,
  'configNueva seed'
);

if (!s.includes('ahtDebugSessionLog')) {
  rep(
    `  const playtestLogClient = { entries: [] };`,
    `  const playtestLogClient = { entries: [] };
  const ahtDebugSessionLog = [];`,
    'debug session log'
  );

  rep(
    `  function ahtLabAuditLog(payload) {
    if (!isDebugOn() || !payload || !payload.lab_audit || !Array.isArray(payload.lab_audit.eventos)) return;
    if (typeof AhtLabAudit !== 'undefined' && AhtLabAudit.log) {
      try { AhtLabAudit.log(payload); return; } catch (e) {}
    }
    payload.lab_audit.eventos.forEach(function (ev) {
      var pref = ev.prefijo || '[AHT DEBUG]';
      console.log(pref, ev.datos);
      try { console.log(pref + ' JSON', JSON.stringify(ev.datos, null, 2)); } catch (e2) {}
    });
  }`,
    `  function ahtLabAuditLog(payload) {
    if (!isDebugOn() || !payload || !payload.lab_audit || !Array.isArray(payload.lab_audit.eventos)) return;
    payload.lab_audit.eventos.forEach(function (ev) {
      ahtDebugSessionLog.push(ev);
      if (ahtDebugSessionLog.length > 500) ahtDebugSessionLog.splice(0, ahtDebugSessionLog.length - 500);
    });
    if (typeof AhtLabAudit !== 'undefined' && AhtLabAudit.log) {
      try { AhtLabAudit.log(payload); return; } catch (e) {}
    }
    payload.lab_audit.eventos.forEach(function (ev) {
      var pref = ev.prefijo || '[AHT DEBUG]';
      console.log(pref, ev.datos);
      try { console.log(pref + ' JSON', JSON.stringify(ev.datos, null, 2)); } catch (e2) {}
    });
  }

  async function copiarDebugExport(soloEstado) {
    if (!isDebugOn()) {
      toast('Activa DEBUG primero.');
      return;
    }
    const body = { historial: soloEstado ? [] : ahtDebugSessionLog.slice() };
    const r = await api('partida.debug_export', body);
    if (!r.ok || !r.debug_export) {
      toast(r.mensaje_ui || 'No se pudo exportar debug.');
      return;
    }
    const txt = r.debug_export.texto || '';
    try {
      await navigator.clipboard.writeText(txt);
      toast('Debug copiado.');
    } catch (e) {
      toast('No se pudo copiar al portapapeles.');
    }
  }`,
    'ahtLabAuditLog buffer'
  );
}

if (!s.includes('function ejecutarAccionMision')) {
  rep(
    `  function renderMisiones(misiones) {`,
    `  function estadoMisionLabel(estado) {
    if (estado === 'cumplida') return 'Completada';
    if (estado === 'bloqueada') return 'Bloqueada';
    if (estado === 'pendiente') return 'Pendiente';
    return estado || '';
  }

  function ejecutarAccionMision(m) {
    if (!m || m.estado === 'bloqueada' || m.estado === 'cumplida') return;
    const acc = m.accion;
    const params = m.accion_params || {};
    if (acc === 'buzon') {
      setCapa('buzon');
      return;
    }
    if (acc === 'organizar_pareja') {
      org.modo = 'pareja';
      if (params.a) org.a = params.a;
      if (params.b) org.b = params.b;
      setCapa('organizar');
      fillOrganizar();
      return;
    }
    if (acc === 'organizar_solo') {
      org.modo = 'solo';
      if (params.a) org.a = params.a;
      if (params.lugar) org.lugar = params.lugar;
      setCapa('organizar');
      fillOrganizar();
      return;
    }
    setCapa('misiones');
  }

  function renderMisiones(misiones) {`,
    'mision actions'
  );

  rep(
    `      row.innerHTML = tit + '<p>' + esc(m.texto || m.hecho || 'Objetivo') + '</p>' +
        '<span class="mision-estado">' + esc(m.estado || '') + '</span>';
      list.appendChild(row);`,
    `      var accBtn = '';
      if (m.accion && m.estado !== 'bloqueada' && m.estado !== 'cumplida') {
        accBtn = '<button type="button" class="mision-accion" data-mision-accion="' + esc(m.id || '') + '">' +
          esc(m.accion_label || 'Ir') + '</button>';
      }
      row.innerHTML = tit + '<p>' + esc(m.texto || m.hecho || 'Objetivo') + '</p>' +
        '<span class="mision-estado">' + esc(estadoMisionLabel(m.estado || '')) + '</span>' + accBtn;
      var btnAcc = row.querySelector('[data-mision-accion]');
      if (btnAcc) {
        btnAcc.addEventListener('click', function (ev) {
          ev.stopPropagation();
          ejecutarAccionMision(m);
        });
      }
      list.appendChild(row);`,
    'mision row html'
  );
}

if (!s.includes('btn-debug-copy')) {
  rep(
    `    const btnNueva = $('#btn-debug-nueva');
    if (btnNueva) btnNueva.addEventListener('click', async function () {
      await nuevaPartidaLimpia();
    });
  })();`,
    `    const btnNueva = $('#btn-debug-nueva');
    if (btnNueva) btnNueva.addEventListener('click', async function () {
      ahtDebugSessionLog.length = 0;
      await nuevaPartidaLimpia();
    });
    const btnCopy = $('#btn-debug-copy');
    if (btnCopy) btnCopy.addEventListener('click', function () { copiarDebugExport(false); });
    const btnCopyEstado = $('#btn-debug-copy-estado');
    if (btnCopyEstado) btnCopyEstado.addEventListener('click', function () { copiarDebugExport(true); });
  })();`,
    'debug copy buttons'
  );
}

fs.writeFileSync(p, s);
console.log('bloqueantes patched');
