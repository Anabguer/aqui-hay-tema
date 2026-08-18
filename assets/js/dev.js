(function () {
  'use strict';

  const API = 'api/index.php';
  let partidaId = localStorage.getItem('aht_dev_partida_id') || localStorage.getItem('aht_partida_id');
  let lastEncuentroId = null;

  async function api(action, body = {}) {
    const res = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ partida_id: partidaId, ...body }),
    });
    return res.json();
  }

  function $(sel) { return document.querySelector(sel); }

  function log(msg, data) {
    const pre = $('#log');
    const line = typeof data !== 'undefined'
      ? `[${new Date().toLocaleTimeString()}] ${msg}\n${JSON.stringify(data, null, 2)}\n\n`
      : `[${new Date().toLocaleTimeString()}] ${msg}\n\n`;
    pre.textContent = line + pre.textContent;
  }

  function showUiError(r) {
    return r.mensaje_ui || r.error || 'Error desconocido';
  }

  async function inspectFull() {
    const r = await api('partida.inspeccionar', {});
    if (r.ok) {
      $('#inspect-panel').textContent = JSON.stringify(r.partida, null, 2);
      return r.partida;
    }
    log('Error inspección', r);
    return null;
  }

  async function refreshPartidaList() {
    const r = await api('partida.listar', {});
    const sel = $('#sel-partida');
    sel.innerHTML = '';
    if (!r.ok) return;
    (r.partidas || []).forEach(p => {
      const rel = p.reloj ? ` · día ${p.reloj.dia_pueblo} h${p.reloj.hora_actual}` : '';
      sel.appendChild(new Option(`${p.partida_id}${rel}`, p.partida_id));
    });
    if (partidaId) sel.value = partidaId;
  }

  async function refreshResidentes(partida) {
    if (!partida) {
      const r = await api('partida.inspeccionar', {});
      if (!r.ok) return;
      partida = r.partida;
    }
    const ids = ['sel-a', 'sel-b', 'sel-residente'];
    ids.forEach(id => {
      const sel = $('#' + id);
      sel.innerHTML = '';
      Object.entries(partida.residentes || {}).forEach(([rid, res]) => {
        const label = `${res.identidad_publica?.nombre || rid} (${rid})`;
        sel.appendChild(new Option(label, rid));
      });
    });
  }

  function persistPartidaId(id) {
    partidaId = id;
    localStorage.setItem('aht_dev_partida_id', id);
    localStorage.setItem('aht_partida_id', id);
    $('#partida-id').textContent = id;
  }

  async function ensurePartida() {
    if (partidaId) {
      const r = await api('partida.cargar', { partida_id: partidaId });
      if (r.ok) return;
    }
    await nuevaPartida();
  }

  async function nuevaPartida() {
    const seed = $('#inp-seed').value.trim() || undefined;
    const r = await api('partida.nueva', seed ? { seed } : {});
    if (r.ok) {
      persistPartidaId(r.partida_id);
      log('Nueva partida dev', { partida_id: r.partida_id, seed: seed || '(auto)' });
    } else {
      log('Error nueva partida', r);
    }
    await refreshPartidaList();
    await inspectFull();
    await refreshResidentes();
  }

  async function loadSelectedPartida() {
    const id = $('#sel-partida').value;
    if (!id) return;
    const r = await api('partida.cargar', { partida_id: id });
    if (r.ok) {
      persistPartidaId(id);
      log('Partida cargada', r.estado);
      await inspectFull();
      await refreshResidentes();
    } else {
      log('Error cargar', r);
    }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    await ensurePartida();
    $('#partida-id').textContent = partidaId || '—';
    await refreshPartidaList();
    await inspectFull();
    await refreshResidentes();

    $('#btn-cargar-partida').addEventListener('click', loadSelectedPartida);
    $('#btn-nueva').addEventListener('click', nuevaPartida);

    $('#btn-borrar-partida').addEventListener('click', async () => {
      if (!partidaId || !confirm('¿Borrar partida ' + partidaId + '?')) return;
      const r = await api('dev.partida.eliminar', { partida_id: partidaId });
      log('Borrar partida', r);
      localStorage.removeItem('aht_dev_partida_id');
      localStorage.removeItem('aht_partida_id');
      partidaId = null;
      await nuevaPartida();
    });

    async function avanzar(horas) {
      const r = await api('reloj.avanzar', { horas });
      log('+' + horas + 'h', r);
      await inspectFull();
    }
    $('#btn-1h').addEventListener('click', () => avanzar(1));
    $('#btn-4h').addEventListener('click', () => avanzar(4));
    $('#btn-12h').addEventListener('click', () => avanzar(12));
    $('#btn-1d').addEventListener('click', () => avanzar(24));

    $('#btn-ir-a').addEventListener('click', async () => {
      const r = await api('reloj.ir_a', {
        dia: parseInt($('#inp-dia').value, 10),
        hora: parseInt($('#inp-hora').value, 10),
      });
      log('Time travel', r.ok ? r : showUiError(r));
      await inspectFull();
    });

    $('#btn-sincronizar-enc').addEventListener('click', async () => {
      const r = await api('encuentro.sincronizar', {});
      log('Sincronizar encuentros', r);
      await inspectFull();
    });

    $('#btn-placeholder').addEventListener('click', async () => {
      const r = await api('residente.placeholder', {});
      log('Placeholder', r);
      const p = await inspectFull();
      await refreshResidentes(p);
    });

    $('#btn-eliminar-ph').addEventListener('click', async () => {
      const rid = $('#sel-residente').value;
      const r = await api('dev.placeholder.eliminar', { residente_id: rid });
      log('Eliminar placeholder', r.ok ? r : showUiError(r));
      const p = await inspectFull();
      await refreshResidentes(p);
    });

    $('#btn-liberar').addEventListener('click', async () => {
      const r = await api('vivienda.liberar', { vivienda_id: $('#sel-vivienda').value });
      log('Liberar vivienda', r);
      await inspectFull();
    });

    $('#btn-vivienda-inspect').addEventListener('click', async () => {
      const r = await api('vivienda.resumen', {});
      log('Bloque A', r);
    });

    $('#btn-enc-programar').addEventListener('click', async () => {
      const r = await api('encuentro.programar', {
        participantes: [$('#sel-a').value, $('#sel-b').value],
        tipo: $('#sel-tipo-enc').value,
        hora: parseInt($('#sel-hora-enc').value, 10),
        lugar: 'lug_cafeteria',
      });
      if (r.ok && r.encuentro) lastEncuentroId = r.encuentro.id;
      log('Programar encuentro', r.ok ? r : showUiError(r));
      await inspectFull();
    });

    $('#btn-enc-cancelar').addEventListener('click', async () => {
      const p = await inspectFull();
      const activos = (p?.encuentros || []).filter(e => e.estado !== 'terminado' && e.estado !== 'cancelado');
      const id = lastEncuentroId || activos[activos.length - 1]?.id;
      if (!id) { log('Sin encuentro activo'); return; }
      const r = await api('encuentro.cancelar', { encuentro_id: id });
      log('Cancelar ' + id, r);
      await inspectFull();
    });

    $('#btn-enc-forzar').addEventListener('click', async () => {
      const p = await inspectFull();
      const activos = (p?.encuentros || []).filter(e => e.estado !== 'terminado');
      const id = lastEncuentroId || activos[0]?.id;
      if (!id) { log('Sin encuentro para resolver'); return; }
      const r = await api('dev.encuentro.forzar_resolver', { encuentro_id: id });
      log('Forzar resolver', r);
      await inspectFull();
    });

    $('#btn-rel-social').addEventListener('click', async () => {
      const r = await api('relacion.social', {
        persona_a: $('#sel-a').value,
        persona_b: $('#sel-b').value,
        tipo: 'conocidos',
        intensidad: 2,
        se_soportan: true,
      });
      log('Rel. social DEV', r);
      await inspectFull();
    });

    $('#btn-rel-romance').addEventListener('click', async () => {
      const r = await api('relacion.romance', {
        persona_a: $('#sel-a').value,
        persona_b: $('#sel-b').value,
        valores: { atraccion_a_hacia_b: 3, atraccion_b_hacia_a: 2, vinculo: 1 },
      });
      log('Rel. romance DEV', r);
      await inspectFull();
    });

    $('#btn-snap-guardar').addEventListener('click', async () => {
      const r = await api('dev.snapshot.guardar', { nombre: $('#inp-snapshot').value });
      log('Snapshot guardado', r);
    });

    $('#btn-snap-restaurar').addEventListener('click', async () => {
      const r = await api('dev.snapshot.restaurar', { nombre: $('#inp-snapshot').value });
      log('Snapshot restaurado', r);
      await inspectFull();
      await refreshResidentes();
    });

    $('#btn-snap-listar').addEventListener('click', async () => {
      const r = await api('dev.snapshot.listar', {});
      log('Snapshots', r);
    });

    $('#btn-reset-enc').addEventListener('click', async () => {
      const r = await api('dev.reset.encuentros', {});
      log('Reset encuentros', r);
      await inspectFull();
    });

    $('#btn-reset-rel').addEventListener('click', async () => {
      const r = await api('dev.reset.relaciones', {});
      log('Reset relaciones', r);
      await inspectFull();
    });

    $('#btn-reset-buzon').addEventListener('click', async () => {
      const r = await api('dev.reset.buzon_diario', {});
      log('Reset buzón/diario', r);
      await inspectFull();
    });

    $('#btn-stress100').addEventListener('click', async () => {
      const r = await api('dev.stress100', { count: 100 });
      log('Stress 100 residentes', r);
    });

    $('#btn-guardar').addEventListener('click', async () => {
      log('Guardar', await api('partida.guardar', {}));
    });

    $('#btn-recargar').addEventListener('click', loadSelectedPartida);

    $('#btn-copiar-json').addEventListener('click', async () => {
      const r = await api('dev.diagnostico.export', {});
      if (r.ok) {
        await navigator.clipboard.writeText(JSON.stringify(r, null, 2));
        log('Diagnóstico copiado al portapapeles');
      }
    });

    $('#btn-calendario').addEventListener('click', async () => {
      const dia = parseInt($('#inp-cal-dia').value, 10);
      const r = await api('dev.calendario', { dia });
      $('#cal-panel').textContent = JSON.stringify(r, null, 2);
      log('Calendario día ' + dia, { conflictos: r.conflictos?.length ?? 0 });
    });

    $('#btn-eventos').addEventListener('click', async () => {
      const r = await api('dev.eventos', { filtros: { limit: 50 } });
      $('#cal-panel').textContent = JSON.stringify(r, null, 2);
      log('Inspector eventos', { total: r.total });
    });

    $('#btn-diagnostico').addEventListener('click', async () => {
      const r = await api('dev.diagnostico.export', {});
      log('Diagnóstico', r);
      $('#cal-panel').textContent = JSON.stringify(r, null, 2);
    });

    $('#btn-simular-30').addEventListener('click', async () => {
      const r = await api('dev.simular', { days: 30, seed: 'dev-ui' });
      log('Simulación 30d', r);
    });

    $('#btn-ins-residente').addEventListener('click', async () => {
      const r = await api('residente.ficha', { residente_id: $('#sel-residente').value });
      log('Ficha residente', r);
    });

    $('#btn-ins-agenda').addEventListener('click', async () => {
      const r = await api('agenda.dia', { residente_id: $('#sel-residente').value });
      log('Agenda', r);
    });

    $('#btn-ins-rel').addEventListener('click', async () => {
      log('Relaciones', await api('relacion.listar', {}));
    });

    $('#btn-ins-enc').addEventListener('click', async () => {
      log('Encuentros', await api('encuentro.listar', {}));
    });

    $('#btn-ins-rng').addEventListener('click', async () => {
      log('RNG', await api('dev.rng', {}));
    });

    $('#btn-ins-audit').addEventListener('click', async () => {
      log('Audit', await api('dev.audit', {}));
    });

    $('#btn-ins-buzon').addEventListener('click', async () => {
      log('Buzón', await api('buzon.listar', {}));
    });

    $('#btn-ins-diario').addEventListener('click', async () => {
      log('Diario', await api('diario.listar', {}));
    });

    $('#btn-ins-mapa').addEventListener('click', async () => {
      log('Mapa', await api('mapa.presencia', {}));
    });

    $('#btn-buzon-dev').addEventListener('click', async () => {
      const r = await api('buzon.crear_dev', {});
      log('Mensaje buzón dev', r);
      await inspectFull();
    });
  });
})();
