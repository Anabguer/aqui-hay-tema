(function () {
  'use strict';

  const API = 'api/index.php';
  let partidaId = localStorage.getItem('aht_partida_id');
  let selectedResidente = null;
  let cacheInspeccion = null;
  let cacheLugares = [];
  let cacheBloqueA = null;
  let cacheEstado = null;
  let cacheEncuentros = [];
  let slotsCache = [];
  let confirmarTimer = null;

  const DISCOVERY_LABELS = {
    'identidad.nombre': 'Nombre',
    'identidad.edad': 'Edad',
    'vida.ocupacion': 'Ocupación',
    'vida.hobby_principal': 'Hobby principal',
    'vida.hobbies_secundarios': 'Hobbies secundarios',
    'vida.rasgos_publicos': 'Rasgos visibles',
    'vida.rasgos_ocultos': 'Rasgos ocultos',
  };

  async function api(action, body = {}, method = 'POST') {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (method !== 'GET') {
      opts.body = JSON.stringify({ partida_id: partidaId, ...body });
    }
    const qs = new URLSearchParams({ action, partida_id: partidaId || '', ...body });
    const url = method === 'GET' ? `${API}?${qs.toString()}` : `${API}?action=${encodeURIComponent(action)}`;
    return (await fetch(url, opts)).json();
  }

  function $(sel) { return document.querySelector(sel); }

  function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  function emptyState(panel, text) {
    panel.innerHTML = '';
    panel.appendChild(el('p', 'empty', text));
  }

  function nombreResidente(id) {
    return cacheInspeccion?.residentes?.[id]?.identidad_publica?.nombre || id;
  }

  function formatHora(dia, hora) {
    return `D${dia} · ${String(hora).padStart(2, '0')}:00`;
  }

  function estadoLabel(estado) {
    return ({
      programado: 'Programado',
      en_curso: 'En curso',
      terminado: 'Terminado',
      cancelado: 'Cancelado',
    })[estado] || estado;
  }

  function estadoClass(estado) {
    return `estado-${(estado || 'desconocido').replace('_', '-')}`;
  }

  function causaBloqueoLabel(clave) {
    return ({
      durmiendo: 'durmiendo',
      trabajo: 'trabajo',
      otro_compromiso: 'otro compromiso',
      encuentro_programado: 'encuentro ya programado',
      doble_reserva: 'doble reserva',
      ocupado: 'ocupado',
      hora_invalida: 'hora no válida',
    })[clave] || clave;
  }

  function formatRechazo(r) {
    if (!r || r.ok) return '';
    const nombre = r.residente_nombre || (r.residente ? nombreResidente(r.residente) : null);
    if (r.error === 'AGENDA_SLOT_OCUPADO' && r.detalle) {
      const tipo = r.detalle.tipo || r.detalle.motivo || 'ocupado';
      const causa = causaBloqueoLabel(
        tipo === 'sueno' ? 'durmiendo'
          : ['trabajo', 'trabajo_blando', 'trabajo_generico', 'estudio'].includes(tipo) ? 'trabajo'
            : tipo === 'compromiso' ? 'otro_compromiso'
              : tipo === 'encuentro' ? 'encuentro_programado'
                : tipo
      );
      return `${nombre || 'El residente'} no está disponible a esa hora: ${causa}.`;
    }
    if (r.error === 'DOBLE_RESERVA') {
      return 'Ya hay un encuentro programado a esa hora para uno de los participantes.';
    }
    if (r.error === 'LUGAR_NO_OPERATIVO') {
      return `El lugar seleccionado no está operativo${r.lugar ? ` (${r.lugar})` : ''}.`;
    }
    if (r.error === 'LIMITE_INTERVENCIONES') {
      return `Límite de intervenciones de hoy alcanzado${r.limite ? ` (${r.limite})` : ''}.`;
    }
    if (r.error === 'RESIDENTE_NO_ACTIVO' && nombre) {
      return `${nombre} no está activo como residente.`;
    }
    if (r.error === 'PARTICIPANTE_INEXISTENTE' && nombre) {
      return `${nombre} no existe en la partida.`;
    }
    return r.mensaje_ui || r.error || 'No se pudo programar el encuentro.';
  }

  function participantesEncuentro() {
    const a = $('#enc-a')?.value || '';
    const b = $('#enc-b')?.value || '';
    return { a, b, validos: a && b && a !== b };
  }

  function setFeedback(text, type = '') {
    const fb = $('#enc-feedback');
    if (!fb) return;
    fb.textContent = text;
    fb.className = 'feedback' + (type ? ` feedback--${type}` : '');
  }

  async function ensurePartida() {
    if (partidaId) {
      const r = await api('partida.cargar', { partida_id: partidaId });
      if (r.ok) return r;
    }
    const r = await api('partida.nueva', {});
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem('aht_partida_id', partidaId);
    }
    return r;
  }

  async function cargarInspeccion() {
    const r = await api('partida.inspeccionar', {}, 'GET');
    if (!r.ok) return null;
    cacheInspeccion = r.partida;
    return cacheInspeccion;
  }

  function renderResidentesList(residentes) {
    const panel = $('#residentes-panel');
    panel.innerHTML = '';
    const entries = Object.entries(residentes || {});
    if (!entries.length) {
      return emptyState(panel, 'No hay residentes.');
    }
    entries.forEach(([id, r]) => {
      const enEnc = residenteEnEncuentroVisible(id);
      const card = el('button', 'resident-card' + (selectedResidente === id ? ' active' : '') + (enEnc ? ' en-encuentro' : ''), undefined);
      card.type = 'button';
      card.appendChild(el('div', null, r.identidad_publica?.nombre || id));
      const meta = `${id} · ${r.vivienda_id || 'sin vivienda'} · ${r.runtime?.ocupacion || '—'}`;
      card.appendChild(el('div', 'resident-meta', enEnc ? `${meta} · encuentro` : meta));
      card.addEventListener('click', () => seleccionarResidente(id));
      panel.appendChild(card);
    });
  }

  function renderBloqueA(bloque) {
    const grid = $('#grid-bloque-a');
    grid.innerHTML = '';
    (bloque?.viviendas || []).forEach(v => {
      const slot = el('div', 'slot ' + (v.ocupante_id ? 'ocupado' : 'libre'));
      if (v.ocupante_id && selectedResidente === v.ocupante_id) slot.classList.add('selected');
      slot.appendChild(el('div', 'slot-id', v.id));
      slot.appendChild(el('div', 'slot-nombre', v.ocupante_id ? nombreResidente(v.ocupante_id) : '— libre —'));
      if (v.ocupante_id) {
        slot.addEventListener('click', () => seleccionarResidente(v.ocupante_id));
      }
      grid.appendChild(slot);
    });
  }

  async function loadResidentesSelects() {
    const insp = cacheInspeccion || await cargarInspeccion();
    if (!insp) return;
    const selA = $('#enc-a');
    const selB = $('#enc-b');
    const prevA = selA.value;
    const prevB = selB.value;
    selA.innerHTML = '';
    selB.innerHTML = '';
    Object.entries(insp.residentes || {}).forEach(([id, r]) => {
      const label = `${r.identidad_publica?.nombre || id} (${id})`;
      selA.appendChild(new Option(label, id));
      selB.appendChild(new Option(label, id));
    });
    if (prevA && insp.residentes?.[prevA]) selA.value = prevA;
    else if (selectedResidente && insp.residentes?.[selectedResidente]) selA.value = selectedResidente;
    if (prevB && insp.residentes?.[prevB]) selB.value = prevB;
    else {
      const ids = Object.keys(insp.residentes || {});
      const other = ids.find(id => id !== selA.value);
      if (other) selB.value = other;
    }
    validarParticipantes();
  }

  function validarParticipantes() {
    const { a, b, validos } = participantesEncuentro();
    const hint = $('#enc-participantes-hint');
    const btn = $('#btn-programar');
    if (!hint) return validos;
    if (!a || !b) {
      hint.hidden = true;
      if (btn) btn.disabled = true;
      return false;
    }
    if (a === b) {
      hint.hidden = false;
      hint.textContent = 'No puedes elegir al mismo residente dos veces.';
      hint.className = 'status form-hint form-hint--error';
      if (btn) btn.disabled = true;
      return false;
    }
    hint.hidden = false;
    hint.textContent = `${nombreResidente(a)} + ${nombreResidente(b)}`;
    hint.className = 'status form-hint';
    return validos;
  }

  function renderLugaresSelect(lugares) {
    const sel = $('#enc-lugar');
    if (!sel) return;
    cacheLugares = lugares || [];
    sel.innerHTML = '';
    let firstOperativo = null;
    cacheLugares.forEach(lug => {
      const opt = new Option(
        `${lug.nombre}${lug.operativo ? '' : ' (bloqueado)'}`,
        lug.id,
        false,
        lug.operativo
      );
      opt.disabled = !lug.operativo;
      sel.appendChild(opt);
      if (lug.operativo && !firstOperativo) firstOperativo = lug.id;
    });
    if (firstOperativo) sel.value = firstOperativo;
    else sel.appendChild(new Option('Sin lugares operativos', ''));
  }

  async function cargarSlotsCompatibles() {
    const slotSel = $('#enc-slot');
    const hint = $('#enc-slots-hint');
    const btn = $('#btn-programar');
    if (!slotSel || !hint) return;

    if (!validarParticipantes()) {
      slotSel.innerHTML = '<option value="">Elige dos residentes distintos…</option>';
      slotSel.disabled = true;
      if (btn) btn.disabled = true;
      return;
    }

    const { a, b } = participantesEncuentro();
    hint.textContent = 'Calculando horas compatibles…';
    hint.className = 'status form-hint';

    const r = await api('agenda.slots_compatibles', {
      participantes: [a, b],
      tipo: $('#enc-tipo').value,
      max_dias: 7,
      max_slots: 32,
    });

    slotSel.innerHTML = '';
    slotsCache = r.slots || [];

    if (!r.ok) {
      slotSel.appendChild(new Option('Error al calcular horas', ''));
      slotSel.disabled = true;
      hint.textContent = r.error || 'No se pudieron calcular horas compatibles.';
      hint.className = 'status form-hint form-hint--error';
      if (btn) btn.disabled = true;
      return;
    }

    if (!slotsCache.length) {
      slotSel.appendChild(new Option('Sin horas compatibles', ''));
      slotSel.disabled = true;
      hint.textContent = r.diagnostico?.resumen_ui || r.diagnostico?.resumen || 'No hay horas compatibles en los próximos 7 días.';
      hint.className = 'status form-hint form-hint--warn';
      if (btn) btn.disabled = true;
      return;
    }

    slotsCache.forEach((s, i) => {
      const val = `${s.dia}:${s.hora}`;
      slotSel.appendChild(new Option(formatHora(s.dia, s.hora), val, i === 0, i === 0));
    });
    slotSel.disabled = false;
    hint.textContent = `${slotsCache.length} hora(s) compatible(s) encontrada(s).`;
    hint.className = 'status form-hint form-hint--ok';
    if (btn) btn.disabled = !$('#enc-lugar')?.value;
  }

  function slotSeleccionado() {
    const raw = $('#enc-slot')?.value || '';
    const [dia, hora] = raw.split(':').map(x => parseInt(x, 10));
    if (!Number.isFinite(dia) || !Number.isFinite(hora)) return null;
    return { dia, hora };
  }

  function renderSummary(e, buzonCount) {
    $('#status-reloj').textContent = e.reloj_texto;
    $('#status-meta').textContent =
      `${e.residentes_count} residentes · ${e.encuentros_activos ?? 0} activos · schema v${e.meta.schema_version}`;
    $('#sum-reloj').textContent = e.reloj_texto;
    $('#sum-residentes').textContent = String(e.residentes_count || 0);
    $('#sum-encuentros-hoy').textContent = String(e.encuentros_hoy ?? 0);
    const activosEl = $('#sum-encuentros-activos');
    if (activosEl) {
      const n = e.encuentros_activos || 0;
      activosEl.textContent = n ? `${n} activo${n === 1 ? '' : 's'}` : '';
    }
    $('#sum-buzon').textContent = String(e.buzon_pendientes ?? buzonCount ?? 0);
    renderProximoEncuentro(e);
  }

  function residenteEnEncuentroVisible(id) {
    const a = cacheEstado?.encuentro_en_curso;
    const b = cacheEstado?.proximo_encuentro;
    return !!(id && ((a && (a.participantes || []).includes(id)) || (b && (b.participantes || []).includes(id))));
  }

  function encParticipa(enc, id) {
    return (enc.participantes || []).includes(id);
  }

  function nombreLugar(id) {
    if (!id) return '—';
    const lug = cacheLugares.find(l => l.id === id);
    return lug?.nombre || id;
  }

  function lugarCard(lugarId) {
    if (!lugarId) return null;
    const sel = (window.CSS && CSS.escape) ? CSS.escape(lugarId) : String(lugarId).replace(/"/g, '');
    return document.querySelector(`.lugar-card[data-lugar="${sel}"]`);
  }

  function encuentroEsHoy(enc) {
    if (!enc) return false;
    if (enc.es_hoy === true) return true;
    if (enc.es_hoy === false) return false;
    return Number(enc.dia) === Number(cacheEstado?.reloj?.dia_pueblo);
  }

  function nombresEncuentro(enc) {
    if (!enc) return '—';
    if ((enc.participantes_nombres || []).length) return enc.participantes_nombres.join(' + ');
    return (enc.participantes || []).map(nombreResidente).join(' + ') || '—';
  }

  function textoCuandoEncuentro(enc, marca) {
    const tipo = enc?.tipo || 'encuentro';
    if (marca === 'en_curso') {
      return `Ahora · ${tipo} · ${estadoLabel(enc.estado)}`;
    }
    if (!encuentroEsHoy(enc)) {
      return `Programado ${formatHora(enc.dia, enc.hora)} · ${tipo}`;
    }
    return `${formatHora(enc.dia, enc.hora)} · ${tipo}`;
  }

  function rellenarDetalleEncuentro(box, enc, marca) {
    if (!box || !enc) return;
    box.innerHTML = '';
    box.appendChild(el('div', null, nombresEncuentro(enc)));
    box.appendChild(el('div', 'mini-meta', textoCuandoEncuentro(enc, marca)));
  }

  function scrollLugarVisible(card) {
    if (!card) return;
    const header = document.querySelector('.top-bar');
    const offset = (header ? header.getBoundingClientRect().height : 0) + 8;
    const top = card.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top: Math.max(0, top), left: 0, behavior: 'smooth' });
  }

  function enfocarLugarMapa(lugarId) {
    const card = lugarCard(lugarId);
    if (!card) return;
    document.querySelectorAll('.lugar-card.abierto').forEach(c => {
      if (c !== card) {
        c.classList.remove('abierto');
        c.setAttribute('aria-expanded', 'false');
      }
    });
    card.classList.add('abierto');
    card.setAttribute('aria-expanded', 'true');
    scrollLugarVisible(card);
  }

  function elegirLugarFormulario(lugarId) {
    const sel = $('#enc-lugar');
    if (!sel || !lugarId) return;
    if ([...sel.options].some(o => o.value === lugarId && !o.disabled)) {
      sel.value = lugarId;
    }
  }

  function irAEncuentroEnMapa(enc) {
    if (!enc?.lugar) return;
    elegirLugarFormulario(enc.lugar);
    const card = lugarCard(enc.lugar);
    if (card) pintarConfirmacionLugar(card, enc);
    enfocarLugarMapa(enc.lugar);
    const sitio = enc.lugar_nombre || nombreLugar(enc.lugar);
    setFeedback(`${sitio}: ${formatHora(enc.dia, enc.hora)}.`, '');
  }

  function pintarConfirmacionLugar(card, vista) {
    if (!card || !vista) return;
    const lug = cacheLugares.find(l => l.id === vista.lugar);
    const encMapa = lug?.encuentro || null;
    if (encMapa && encMapa.id === vista.id) {
      const box = card.querySelector('.lugar-detalle:not(.lugar-confirmacion)');
      if (box) rellenarDetalleEncuentro(box, encMapa, lug.encuentro_marca);
      return;
    }
    let box = card.querySelector('.lugar-confirmacion');
    if (!box) {
      box = el('div', 'lugar-detalle lugar-confirmacion');
      card.appendChild(box);
    }
    rellenarDetalleEncuentro(box, vista, null);
  }

  function confirmarEncuentroEnMapa(vista) {
    const lugarId = vista?.lugar;
    if (!lugarId) return;
    elegirLugarFormulario(lugarId);
    const card = lugarCard(lugarId);
    if (!card) return;
    pintarConfirmacionLugar(card, vista);
    enfocarLugarMapa(lugarId);
    card.classList.add('lugar-confirmado');
    if (confirmarTimer) clearTimeout(confirmarTimer);
    confirmarTimer = setTimeout(() => {
      card.classList.remove('lugar-confirmado');
      confirmarTimer = null;
    }, 4500);
  }

  function detalleEncuentroLugar(enc, marca) {
    const box = el('div', 'lugar-detalle');
    rellenarDetalleEncuentro(box, enc, marca);
    return box;
  }

  function encuentroVisibleDeResidente(id) {
    if (!id) return null;
    const curso = cacheEstado?.encuentro_en_curso;
    if (curso && encParticipa(curso, id)) return curso;
    const propios = (cacheEncuentros || []).filter(e =>
      encParticipa(e, id) && (e.estado === 'programado' || e.estado === 'en_curso')
    ).sort((a, b) => (Number(a.dia) - Number(b.dia)) || (Number(a.hora) - Number(b.hora)));
    if (propios[0]) return propios[0];
    const prox = cacheEstado?.proximo_encuentro;
    if (prox && encParticipa(prox, id)) return prox;
    return null;
  }

  function renderProximoEncuentro(e) {
    const cuerpo = $('#proximo-cuerpo');
    const btn = $('#btn-proximo-encuentro');
    const actual = e.encuentro_en_curso;
    const prox = e.proximo_encuentro;
    if (cuerpo) {
      cuerpo.innerHTML = '';
      if (actual) {
        cuerpo.appendChild(bloqueEncuentroResumen(actual, 'En curso ahora'));
      }
      if (prox) {
        cuerpo.appendChild(bloqueEncuentroResumen(prox, actual ? 'Siguiente programado' : null));
      }
      if (!actual && !prox) {
        cuerpo.appendChild(el('div', 'empty', 'No hay encuentros programados.'));
      }
    }
    if (btn) btn.disabled = !prox;
  }

  function bloqueEncuentroResumen(enc, etiqueta) {
    const wrap = el('div', 'proximo-item');
    wrap.tabIndex = 0;
    wrap.setAttribute('role', 'button');
    if (etiqueta) wrap.appendChild(el('div', 'mini-meta', etiqueta));
    const nombres = (enc.participantes_nombres || []).join(' + ') || '—';
    wrap.appendChild(el('div', null, nombres));
    const hora = formatHora(enc.dia, enc.hora);
    const lugar = enc.lugar_nombre || enc.lugar || '—';
    wrap.appendChild(el('div', 'mini-meta', `${hora} · ${lugar}`));
    wrap.appendChild(el('div', 'mini-meta', `${enc.tipo || 'encuentro'} · ${estadoLabel(enc.estado)}`));
    const ir = () => {
      irAEncuentroEnMapa(enc);
      setFeedback(`${lugar}: ${etiqueta || estadoLabel(enc.estado)}.`, '');
    };
    wrap.addEventListener('click', ir);
    wrap.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        ir();
      }
    });
    return wrap;
  }

  function mostrarAvanceResumen(resumen) {
    const panel = $('#avance-resumen');
    if (!panel) return;
    const lineas = resumen?.lineas || [];
    if (!lineas.length) {
      panel.hidden = true;
      panel.innerHTML = '';
      return;
    }
    panel.hidden = false;
    panel.innerHTML = '';
    panel.appendChild(el('div', 'label', 'Durante este avance'));
    const ul = el('ul', 'avance-list');
    lineas.forEach(l => {
      ul.appendChild(el('li', null, l.texto || l.tipo));
    });
    panel.appendChild(ul);
  }

  async function renderMapa() {
    const r = await api('mapa.presencia', {}, 'GET');
    const panel = $('#mapa-panel');
    if (!r.ok) {
      panel.textContent = 'Error mapa';
      return;
    }
    panel.innerHTML = '';
    const lugares = r.mapa?.lugares || [];
    renderLugaresSelect(lugares);

    lugares.forEach(lug => {
      const presentes = lug.residentes_presentes || [];
      const marca = lug.encuentro_marca || null;
      const enc = lug.encuentro || null;
      const selectedHere = selectedResidente && presentes.some(p => p.id === selectedResidente);
      const selectedEnc = !!(selectedResidente && enc && (enc.participantes || []).includes(selectedResidente));
      const cls = [
        'lugar-card',
        selectedHere ? 'selected-resident' : '',
        marca === 'en_curso' ? 'marca-en-curso' : '',
        marca === 'proximo' ? 'marca-proximo' : '',
        selectedEnc ? 'marca-seleccion' : '',
      ].filter(Boolean).join(' ');
      const row = el('div', cls);
      row.dataset.lugar = lug.id;
      row.tabIndex = 0;
      row.setAttribute('role', 'button');
      row.setAttribute('aria-expanded', 'false');
      const head = el('div', 'lugar-head');
      const tag = lug.operativo ? 'abierto' : (lug.candado ? 'candado' : 'cerrado');
      head.appendChild(el('div', null, `${lug.nombre} · ${tag}`));
      if (marca === 'en_curso') {
        head.appendChild(el('span', 'lugar-badge badge-ahora', 'Ahora'));
      } else if (marca === 'proximo') {
        head.appendChild(el('span', 'lugar-badge badge-proximo', 'Próximo'));
      }
      row.appendChild(head);
      const pres = presentes.map(p => {
        const name = nombreResidente(p.id);
        return p.id === selectedResidente ? `▸ ${name}` : name;
      }).join(', ') || 'Sin residentes';
      row.appendChild(el('div', 'mini-meta', pres));
      if (enc && marca) {
        row.appendChild(detalleEncuentroLugar(enc, marca));
      }
      const activar = () => {
        const wasOpen = row.classList.contains('abierto');
        document.querySelectorAll('.lugar-card.abierto').forEach(c => {
          if (c !== row) {
            c.classList.remove('abierto');
            c.setAttribute('aria-expanded', 'false');
          }
        });
        row.classList.toggle('abierto', !wasOpen);
        row.setAttribute('aria-expanded', String(!wasOpen));
        if (lug.operativo) {
          const sel = $('#enc-lugar');
          if (sel) sel.value = lug.id;
          setFeedback(`${lug.nombre} seleccionado.`, '');
        }
      };
      row.addEventListener('click', activar);
      row.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          activar();
        }
      });
      panel.appendChild(row);
    });
  }

  async function renderEncuentros() {
    const r = await api('encuentro.listar', {}, 'GET');
    const activos = $('#encuentros-activos');
    const hist = $('#encuentros-historial');
    activos.innerHTML = '';
    hist.innerHTML = '';
    if (!r.ok) {
      activos.textContent = 'Error encuentros';
      return;
    }
    const list = r.encuentros || [];
    cacheEncuentros = list;
    const abiertos = list.filter(e => e.estado === 'programado' || e.estado === 'en_curso');
    const cerrados = list.filter(e => e.estado === 'terminado' || e.estado === 'cancelado').slice(-8).reverse();

    const renderList = (target, items, empty) => {
      if (!items.length) return emptyState(target, empty);
      items.forEach(enc => {
        const selected = selectedResidente && encParticipa(enc, selectedResidente);
        const esMarca = (cacheEstado?.encuentro_en_curso?.id === enc.id) || (cacheEstado?.proximo_encuentro?.id === enc.id);
        const row = el('div', 'enc-row' + (selected ? ' selected-resident' : '') + (esMarca ? ' enc-marca' : ''));
        const header = el('div', 'enc-row-header');
        header.appendChild(el('span', `estado-badge ${estadoClass(enc.estado)}`, estadoLabel(enc.estado)));
        header.appendChild(el('span', null, `${enc.tipo} · ${formatHora(enc.dia, enc.hora)}`));
        row.appendChild(header);
        const lugarNombre = nombreLugar(enc.lugar);
        row.appendChild(el('div', 'mini-meta', `${(enc.participantes || []).map(nombreResidente).join(' · ')} · ${lugarNombre}`));
        if (enc.resultado?.texto_resumen) {
          row.appendChild(el('div', 'mini-meta', enc.resultado.texto_resumen));
        }
        const acciones = el('div', 'enc-acciones');
        if (enc.lugar && (enc.estado === 'programado' || enc.estado === 'en_curso')) {
          const ver = el('button', 'btn-inline', 'Ver en mapa');
          ver.type = 'button';
          ver.addEventListener('click', (ev) => {
            ev.stopPropagation();
            irAEncuentroEnMapa(enc);
          });
          acciones.appendChild(ver);
        }
        if (enc.estado === 'programado') {
          const btn = el('button', 'btn-inline', 'Cancelar');
          btn.type = 'button';
          btn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            cancelarEncuentro(enc.id);
          });
          acciones.appendChild(btn);
        }
        if (acciones.childNodes.length) row.appendChild(acciones);
        target.appendChild(row);
      });
    };

    renderList(activos, abiertos, 'No hay encuentros activos.');
    renderList(hist, cerrados, 'Sin encuentros finalizados todavía.');
  }

  async function renderRelaciones(residenteId) {
    const panel = $('#relaciones-panel');
    if (!residenteId) return emptyState(panel, 'Selecciona un residente.');
    const r = await api('residente.ficha', { residente_id: residenteId }, 'GET');
    if (!r.ok) return emptyState(panel, 'Error relaciones.');
    const rels = Object.entries(r.ficha?.relaciones || {});
    panel.innerHTML = '';
    if (!rels.length) return emptyState(panel, 'Sin relaciones visibles todavía.');
    rels.forEach(([id, rel]) => {
      const row = el('div', 'rel-row' + (selectedResidente === id ? ' selected-resident' : ''));
      row.appendChild(el('div', null, nombreResidente(id)));
      row.appendChild(el('div', 'mini-meta', `Social: ${rel.social?.tipo || '—'} · Romance: ${rel.romance ? 'sí' : '—'}`));
      row.addEventListener('click', () => seleccionarResidente(id));
      panel.appendChild(row);
    });
  }

  function renderDiscovery(campos) {
    const wrap = el('div', 'discovery-block');
    wrap.appendChild(el('div', 'label', 'Información visible (Discovery)'));
    const list = el('div', 'discovery-list');
    const entries = Object.entries(campos || {});
    if (!entries.length) {
      list.appendChild(el('p', 'empty', 'Sin campos de discovery proyectados.'));
      wrap.appendChild(list);
      return wrap;
    }
    entries.forEach(([campo, row]) => {
      if (!row || typeof row !== 'object') return;
      const item = el('div', 'discovery-item');
      const label = DISCOVERY_LABELS[campo] || campo;
      let valor = '—';
      let extraClass = '';
      if (row.visible_jugador === false) {
        if (row.valor === '__PARCIAL__') {
          valor = 'Parcial (aún no revelado)';
          extraClass = 'discovery-parcial';
        } else {
          valor = 'Oculto';
          extraClass = 'discovery-oculto';
        }
      } else if (Array.isArray(row.valor)) {
        valor = row.valor.length ? row.valor.join(', ') : '—';
      } else if (row.valor !== null && row.valor !== undefined && row.valor !== '') {
        valor = String(row.valor);
      }
      item.appendChild(el('span', 'discovery-campo', label));
      item.appendChild(el('span', 'discovery-valor' + (extraClass ? ` ${extraClass}` : ''), valor));
      if (row.politica && row.politica !== 'sin_politica') {
        item.appendChild(el('span', 'discovery-politica', row.politica));
      }
      list.appendChild(item);
    });
    wrap.appendChild(list);
    return wrap;
  }

  async function marcarBuzonLeido(mensajeId) {
    const r = await api('buzon.leer', { mensaje_id: mensajeId });
    if (r.ok) await refreshEstado();
    else setFeedback('No se pudo marcar el mensaje como leído.', 'error');
  }

  async function renderBuzon() {
    const r = await api('buzon.listar', {}, 'GET');
    const panel = $('#buzon-panel');
    panel.innerHTML = '';
    if (!r.ok) {
      panel.textContent = 'Error buzón';
      return 0;
    }
    const mensajes = r.mensajes || [];
    if (!mensajes.length) {
      emptyState(panel, 'Bandeja vacía.');
      return 0;
    }
    mensajes.slice(-8).reverse().forEach(m => {
      const row = el('div', 'msg-row');
      const estado = m.estado || 'pendiente';
      row.appendChild(el('span', `estado-badge estado-${estado}`, estado));
      row.appendChild(el('div', null, `${nombreResidente(m.de_persona || 'sistema')} · ${m.tipo || 'mensaje'}`));
      row.appendChild(el('div', 'mini-meta', `D${m.dia || '—'}`));
      row.appendChild(el('div', 'msg-texto', m.texto || '(sin texto)'));
      if (estado === 'pendiente') {
        const btn = el('button', 'btn-inline', 'Marcar leído');
        btn.type = 'button';
        btn.addEventListener('click', (ev) => {
          ev.stopPropagation();
          marcarBuzonLeido(m.id);
        });
        row.appendChild(btn);
      }
      panel.appendChild(row);
    });
    return mensajes.filter(m => (m.estado || 'pendiente') === 'pendiente').length;
  }

  async function renderDiario() {
    const r = await api('diario.listar', {}, 'GET');
    const panel = $('#diario-panel');
    panel.innerHTML = '';
    if (!r.ok) {
      panel.textContent = 'Error diario';
      return;
    }
    const entradas = r.entradas || [];
    if (!entradas.length) return emptyState(panel, 'Sin entradas hoy.');
    entradas.slice(-8).reverse().forEach(d => {
      const row = el('div', 'dia-row');
      row.appendChild(el('div', null, d.tipo || 'entrada'));
      row.appendChild(el('div', 'mini-meta', `D${d.dia || '—'}`));
      row.appendChild(el('div', null, d.texto || '(sin texto)'));
      panel.appendChild(row);
    });
  }

  function renderPortrait(target, visual, nombre) {
    const frame = el('div', 'portrait-frame');
    const asset = visual?.asset;
    if (asset?.url_relativa && asset?.existe) {
      const img = document.createElement('img');
      img.src = asset.url_relativa;
      img.alt = `${nombre} — ${visual.expression_id || 'neutral'}`;
      frame.appendChild(img);
    } else {
      frame.appendChild(el('div', 'portrait-placeholder', 'Retrato pendiente\npack / neutral'));
    }
    target.appendChild(frame);
  }

  async function abrirFicha(residenteId) {
    const r = await api('residente.ficha', { residente_id: residenteId }, 'GET');
    const panel = $('#ficha-panel');
    if (!r.ok) {
      panel.innerHTML = '<p class="error">Error ficha</p>';
      return;
    }
    const f = r.ficha;
    panel.innerHTML = '';

    const hero = el('div', 'ficha-hero');
    renderPortrait(hero, f.presentacion_visual, f.identidad.nombre);
    const meta = el('div');
    meta.appendChild(el('h3', null, f.identidad.nombre));
    meta.appendChild(el('div', 'mini-meta', `${f.id} · ${f.vivienda_id || 'sin vivienda'} · ${f.placeholder ? 'placeholder' : 'catálogo'}`));
    hero.appendChild(meta);
    panel.appendChild(hero);

    const pills = el('div', 'pill-row');
    pills.appendChild(el('span', 'pill', `Estado: ${f.estado_emocional?.id || 'neutro'}`));
    pills.appendChild(el('span', 'pill', `Expresión: ${f.presentacion_visual?.expression_id || 'neutral'}`));
    pills.appendChild(el('span', 'pill', `Fallback: ${f.presentacion_visual?.fallback ? 'sí' : 'no'}`));
    if (cacheEstado?.encuentro_en_curso && (cacheEstado.encuentro_en_curso.participantes || []).includes(residenteId)) {
      pills.appendChild(el('span', 'pill pill-ahora', 'En encuentro ahora'));
    } else if (cacheEstado?.proximo_encuentro && (cacheEstado.proximo_encuentro.participantes || []).includes(residenteId)) {
      pills.appendChild(el('span', 'pill pill-proximo', 'Próximo encuentro'));
    }
    panel.appendChild(pills);

    panel.appendChild(renderDiscovery(f.discovery?.campos || {}));

    const encPropio = encuentroVisibleDeResidente(residenteId);
    if (encPropio && encPropio.lugar) {
      const row = el('div', 'enc-row enc-marca');
      row.appendChild(el('div', 'label', encPropio.estado === 'en_curso' ? 'Encuentro ahora' : 'Próximo encuentro'));
      row.appendChild(el('div', null, nombresEncuentro(encPropio)));
      const sitio = encPropio.lugar_nombre || nombreLugar(encPropio.lugar);
      row.appendChild(el('div', 'mini-meta', `${formatHora(encPropio.dia, encPropio.hora)} · ${sitio}`));
      const ver = el('button', 'btn-inline', 'Ver en mapa');
      ver.type = 'button';
      ver.addEventListener('click', (ev) => {
        ev.stopPropagation();
        irAEncuentroEnMapa(encPropio);
      });
      row.appendChild(ver);
      panel.appendChild(row);
    }

    const hobbies = (f.hobbies?.conocidos || []).length ? f.hobbies.conocidos.join(', ') : null;
    if (hobbies) {
      panel.appendChild(el('p', 'mini-meta', `Hobbies visibles: ${hobbies}`));
    }
    panel.appendChild(el('p', 'mini-meta', `Descubrimientos: ${(f.descubrimientos || []).length} · Relaciones: ${Object.keys(f.relaciones || {}).length}`));

    if (f.ultimo_encuentro?.resultado) {
      const row = el('div', 'enc-row');
      row.appendChild(el('div', 'label', 'Último encuentro'));
      row.appendChild(el('div', null, f.ultimo_encuentro.resultado.texto_resumen || JSON.stringify(f.ultimo_encuentro.resultado.delta_social || {})));
      panel.appendChild(row);
    }

    const agenda = el('div', 'agenda-mini');
    const slots = (f.agenda_hoy?.slots || []).filter(s => s.hora >= 8 && s.hora <= 22);
    slots.forEach(s => {
      const tipo = s.ocupado ? (s.tipo || s.detalle || 'ocupado') : 'libre';
      agenda.appendChild(el('div', s.ocupado ? 'ocupado' : 'libre', `${String(s.hora).padStart(2, '0')}:00 · ${tipo}`));
    });
    panel.appendChild(el('div', 'label', 'Agenda hoy'));
    panel.appendChild(agenda);
  }

  async function seleccionarResidente(id) {
    selectedResidente = id;
    renderResidentesList(cacheInspeccion?.residentes || {});
    renderBloqueA(cacheBloqueA || {});
    if ($('#enc-a')) $('#enc-a').value = id;
    validarParticipantes();
    await renderEncuentros();
    await Promise.all([
      abrirFicha(id),
      renderRelaciones(id),
      renderMapa(),
      cargarSlotsCompatibles(),
    ]);
  }

  async function refreshEstado() {
    const [estadoResp, insp] = await Promise.all([
      api('partida.estado', {}, 'GET'),
      cargarInspeccion(),
    ]);
    if (!estadoResp.ok || !insp) return null;
    const e = estadoResp.estado;
    cacheEstado = e;
    if (e.bloque_a) cacheBloqueA = e.bloque_a;
    const buzonCount = await renderBuzon();
    renderSummary(e, buzonCount);
    renderResidentesList(insp.residentes || {});
    renderBloqueA(e.bloque_a);
    await loadResidentesSelects();
    await renderMapa();
    await renderEncuentros();
    await renderDiario();
    if (!selectedResidente) {
      const first = Object.keys(insp.residentes || {})[0] || null;
      if (first) selectedResidente = first;
    }
    if (selectedResidente) {
      await abrirFicha(selectedResidente);
      await renderRelaciones(selectedResidente);
    }
    await cargarSlotsCompatibles();
    return e;
  }

  async function cancelarEncuentro(encuentroId) {
    if (!encuentroId) return;
    if (!confirm('¿Cancelar este encuentro programado?')) return;
    const r = await api('encuentro.cancelar', { encuentro_id: encuentroId });
    if (r.ok) {
      setFeedback('Encuentro cancelado. La hora queda libre en la agenda.', 'ok');
    } else {
      setFeedback(r.mensaje_ui || r.error || 'No se pudo cancelar el encuentro.', 'error');
    }
    await refreshEstado();
  }

  async function avanzarRelojUi(horas, etiqueta) {
    const r = await api('reloj.avanzar', { horas, paso_a_paso: true });
    if (!r.ok) {
      setFeedback(r.mensaje_ui || 'No se pudo avanzar el reloj.', 'error');
      return;
    }
    const lineas = r.resumen_avance?.lineas || r.reloj?.resumen_avance?.lineas || [];
    let msg = etiqueta || `Tiempo avanzado ${horas}h.`;
    if (lineas.length) {
      msg += ' ' + lineas.map(l => l.texto).join(' ');
    }
    setFeedback(msg, 'ok');
    mostrarAvanceResumen(r.resumen_avance || r.reloj?.resumen_avance);
    await refreshEstado();
  }

  function bindEncuentroForm() {
    const onChange = () => {
      validarParticipantes();
      cargarSlotsCompatibles();
    };
    $('#enc-a')?.addEventListener('change', onChange);
    $('#enc-b')?.addEventListener('change', onChange);
    $('#enc-tipo')?.addEventListener('change', onChange);
    $('#enc-lugar')?.addEventListener('change', () => {
      const btn = $('#btn-programar');
      if (btn) btn.disabled = !slotSeleccionado() || !$('#enc-lugar')?.value;
    });

    $('#btn-programar')?.addEventListener('click', async () => {
      if (!validarParticipantes()) {
        setFeedback('Elige dos residentes distintos.', 'error');
        return;
      }
      const slot = slotSeleccionado();
      const lugar = $('#enc-lugar')?.value;
      if (!slot) {
        setFeedback('Elige una hora compatible de la lista.', 'error');
        return;
      }
      if (!lugar) {
        setFeedback('Elige un lugar operativo.', 'error');
        return;
      }

      setFeedback('Programando…', '');
      const { a, b } = participantesEncuentro();
      const r = await api('encuentro.programar', {
        participantes: [a, b],
        tipo: $('#enc-tipo').value,
        dia: slot.dia,
        hora: slot.hora,
        lugar,
      });
      if (r.ok) {
        const vista = r.vista || r.encuentro;
        const sitio = vista?.lugar_nombre || nombreLugar(vista?.lugar) || lugar;
        const nombres = nombresEncuentro(vista);
        const cuando = vista && !encuentroEsHoy(vista)
          ? `Programado ${formatHora(vista.dia, vista.hora)}`
          : formatHora(vista?.dia, vista?.hora);
        setFeedback(`${nombres} han quedado en ${sitio}. ${cuando}.`, 'ok');
        await refreshEstado();
        confirmarEncuentroEnMapa(vista);
      } else {
        setFeedback(formatRechazo(r), 'error');
        await refreshEstado();
      }
    });
  }

  async function init() {
    await ensurePartida();
    bindEncuentroForm();
    await refreshEstado();

    $('#btn-avanzar-1h')?.addEventListener('click', () => avanzarRelojUi(1, 'Tiempo avanzado 1h.'));
    $('#btn-avanzar-8h')?.addEventListener('click', () => avanzarRelojUi(8, 'Tiempo avanzado 8h.'));
    $('#btn-proximo-encuentro')?.addEventListener('click', async () => {
      const r = await api('reloj.proximo_encuentro', {});
      if (!r.ok) {
        setFeedback(r.mensaje_ui || 'No hay ningún encuentro programado más adelante.', 'error');
        mostrarAvanceResumen(null);
        return;
      }
      const enc = r.encuentro || {};
      setFeedback(
        `Reloj en el próximo encuentro: ${formatHora(enc.dia, enc.hora)} · ${estadoLabel(enc.estado)} (+${r.horas_avanzadas}h).`,
        'ok'
      );
      mostrarAvanceResumen(r.resumen_avance || r.reloj?.resumen_avance);
      await refreshEstado();
    });

    $('#btn-guardar')?.addEventListener('click', async () => {
      const r = await api('partida.guardar', {});
      setFeedback(r.ok ? 'Partida guardada.' : 'Error al guardar.', r.ok ? 'ok' : 'error');
    });

    $('#btn-nueva')?.addEventListener('click', async () => {
      if (!confirm('¿Nueva partida (nuevo id)?')) return;
      localStorage.removeItem('aht_partida_id');
      partidaId = null;
      selectedResidente = null;
      cacheInspeccion = null;
      await ensurePartida();
      await refreshEstado();
      mostrarAvanceResumen(null);
      setFeedback('Nueva partida creada.', 'ok');
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
