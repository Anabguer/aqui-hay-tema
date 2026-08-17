(function () {
  'use strict';

  const API = 'api/index.php';
  let partidaId = localStorage.getItem('aht_partida_id');

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
      ? `${msg}\n${JSON.stringify(data, null, 2)}\n\n`
      : `${msg}\n\n`;
    pre.textContent = line + pre.textContent;
  }

  async function ensurePartida() {
    if (partidaId) {
      const r = await api('partida.cargar', { partida_id: partidaId });
      if (r.ok) return;
    }
    const r = await api('partida.nueva', {});
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem('aht_partida_id', partidaId);
    }
  }

  async function inspect() {
    const r = await api('partida.inspeccionar', {});
    if (r.ok) {
      $('#inspect').textContent = JSON.stringify(r.partida, null, 2);
    }
  }

  async function refreshResidentes() {
    const r = await api('partida.inspeccionar', {});
    const selA = $('#sel-a');
    const selB = $('#sel-b');
    selA.innerHTML = '';
    selB.innerHTML = '';
    if (!r.ok) return;
    Object.entries(r.partida.residentes || {}).forEach(([id, res]) => {
      const label = `${res.identidad_publica?.nombre || id} (${id})`;
      selA.appendChild(new Option(label, id));
      selB.appendChild(new Option(label, id));
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    await ensurePartida();
    $('#partida-id').textContent = partidaId;
    await inspect();
    await refreshResidentes();

    $('#btn-1h').addEventListener('click', async () => {
      const r = await api('reloj.avanzar', { horas: 1 });
      log('+1 hora', r);
      await inspect();
    });
    $('#btn-4h').addEventListener('click', async () => {
      const r = await api('reloj.avanzar', { horas: 4 });
      log('+4 horas', r);
      await inspect();
    });
    $('#btn-1d').addEventListener('click', async () => {
      const r = await api('reloj.avanzar', { horas: 24 });
      log('+1 día', r);
      await inspect();
    });

    $('#btn-placeholder').addEventListener('click', async () => {
      const r = await api('residente.placeholder', {});
      log('Placeholder dev', r);
      await inspect();
      await refreshResidentes();
    });

    $('#btn-liberar').addEventListener('click', async () => {
      const vid = $('#sel-vivienda').value;
      const r = await api('vivienda.liberar', { vivienda_id: vid });
      log('Liberar ' + vid, r);
      await inspect();
      await refreshResidentes();
    });

    $('#btn-cita').addEventListener('click', async () => {
      const r = await api('cita.programar', {
        residente_a: $('#sel-a').value,
        residente_b: $('#sel-b').value,
        hora: parseInt($('#sel-hora').value, 10),
        lugar: 'lug_cafeteria',
      });
      log('Programar cita', r);
      await inspect();
    });

    $('#btn-rel-social').addEventListener('click', async () => {
      const r = await api('relacion.social', {
        persona_a: $('#sel-a').value,
        persona_b: $('#sel-b').value,
        tipo: 'conocidos',
        intensidad: 2,
        se_soportan: true,
      });
      log('Relación social', r);
      await inspect();
    });

    $('#btn-rel-romance').addEventListener('click', async () => {
      const r = await api('relacion.romance', {
        persona_a: $('#sel-a').value,
        persona_b: $('#sel-b').value,
        valores: { atraccion_a_hacia_b: 3, atraccion_b_hacia_a: 2, vinculo: 1 },
      });
      log('Relación romance (valores dev)', r);
      await inspect();
    });

    $('#btn-guardar').addEventListener('click', async () => {
      const r = await api('partida.guardar', {});
      log('Guardar', r);
    });

    $('#btn-recargar').addEventListener('click', async () => {
      const r = await api('partida.cargar', { partida_id: partidaId });
      log('Recargar', r);
      await inspect();
      await refreshResidentes();
    });

    $('#btn-nueva').addEventListener('click', async () => {
      localStorage.removeItem('aht_partida_id');
      partidaId = null;
      await ensurePartida();
      $('#partida-id').textContent = partidaId;
      log('Nueva partida', { partida_id: partidaId });
      await inspect();
      await refreshResidentes();
    });
  });
})();
