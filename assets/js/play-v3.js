(function () {
  'use strict';

  const API = 'api/index.php';
  const qs = new URLSearchParams(location.search);
  const CONFIG_JUEGO = { config_id: 'juego_v1' };
  const DEBUG_KEY = 'aht_debug_on';
  let DEBUG_ON = false;
  try { DEBUG_ON = localStorage.getItem(DEBUG_KEY) === '1'; } catch (e) {}
  function setDebugOn(on) {
    DEBUG_ON = !!on;
    try { localStorage.setItem(DEBUG_KEY, DEBUG_ON ? '1' : '0'); } catch (e2) {}
    document.body.setAttribute('data-debug', DEBUG_ON ? '1' : '0');
    if (DEBUG_ON) {
      try { console.log('%c[AHT DEBUG] Instrumentación activa', 'color:#c45;font-weight:bold'); } catch (e3) {}
    }
  }
  function isDebugOn() { return DEBUG_ON; }
  function configNueva(forceFreshSeed) {
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
  }
  const MUSICA_STORAGE_KEY = 'aht_musica_fondo';
  let musicaActiva = true;
  try { musicaActiva = localStorage.getItem(MUSICA_STORAGE_KEY) !== '0'; } catch (e) {}
  const musicaFondo = new Audio('assets/audio/musica-fondo.mp3');
  musicaFondo.loop = true;
  musicaFondo.volume = 0.22;
  musicaFondo.preload = 'auto';
  let musicaPlayEnCurso = null;
  let musicaPrimerGestoPendiente = false;

  function actualizarControlMusica() {
    const control = $('[data-musica-toggle]');
    if (!control) return;
    control.dataset.musica = musicaActiva ? 'on' : 'off';
    control.setAttribute('aria-pressed', musicaActiva ? 'true' : 'false');
    const etiqueta = musicaActiva ? 'Desactivar m?sica' : 'Activar m?sica';
    control.setAttribute('aria-label', etiqueta);
    control.setAttribute('title', etiqueta);
  }

  function retirarEsperaPrimerGesto() {
    if (!musicaPrimerGestoPendiente) return;
    musicaPrimerGestoPendiente = false;
    document.removeEventListener('pointerdown', musicaPrimerGesto, true);
    document.removeEventListener('click', musicaPrimerGesto, true);
    document.removeEventListener('keydown', musicaPrimerGesto, true);
    document.removeEventListener('touchstart', musicaPrimerGesto, true);
  }

  function registrarEsperaPrimerGesto() {
    if (!musicaActiva || musicaPrimerGestoPendiente) return;
    musicaPrimerGestoPendiente = true;
    document.addEventListener('pointerdown', musicaPrimerGesto, true);
    document.addEventListener('click', musicaPrimerGesto, true);
    document.addEventListener('keydown', musicaPrimerGesto, true);
    document.addEventListener('touchstart', musicaPrimerGesto, true);
  }

  function iniciarMusicaFondo(esperarInteraccion) {
    if (!musicaActiva || !musicaFondo.paused || musicaPlayEnCurso) return;
    try {
      musicaPlayEnCurso = Promise.resolve(musicaFondo.play()).then(function () {
        musicaPlayEnCurso = null;
      }, function () {
        musicaPlayEnCurso = null;
        if (esperarInteraccion) registrarEsperaPrimerGesto();
      });
    } catch (e) {
      musicaPlayEnCurso = null;
      if (esperarInteraccion) registrarEsperaPrimerGesto();
    }
  }

  function musicaPrimerGesto(ev) {
    if (ev && ev.target && ev.target.closest && ev.target.closest('[data-musica-toggle]')) return;
    retirarEsperaPrimerGesto();
    iniciarMusicaFondo(false);
  }

  function cambiarMusica(activa) {
    musicaActiva = !!activa;
    try { localStorage.setItem(MUSICA_STORAGE_KEY, musicaActiva ? '1' : '0'); } catch (e) {}
    if (musicaActiva) iniciarMusicaFondo(false);
    else {
      retirarEsperaPrimerGesto();
      musicaFondo.pause();
    }
    actualizarControlMusica();
  }

  let partidaId = localStorage.getItem('aht_partida_id_juego');
  let cacheEstado = null;
  let cacheInsp = null;
  let cachePueblo = null;
  let cacheBuzon = [];
  let vidaCorazonPctPrev = null;
  let vidaCorazonReady = false;
  let org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };
  let orgPresetNuevo = false;
  const playtestLogClient = { entries: [] };
  const ahtDebugSessionLog = [];
  playtestLogClient.push = function (e) {
    this.entries.push(e);
    if (this.entries.length > 300) this.entries = this.entries.slice(-300);
  };
  function storageKey() { return 'aht_partida_id_juego'; }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
  function initDebugPanel() {
    setDebugOn(DEBUG_ON);
    const ptToggle = $('[data-debug-toggle]');
    const ptPanel = document.querySelector('[data-debug-panel]');
    if (ptToggle && ptPanel) {
      ptToggle.addEventListener('click', function () {
        var opening = ptPanel.hasAttribute('hidden');
        if (opening) {
          setDebugOn(true);
          ptPanel.removeAttribute('hidden');
        } else {
          ptPanel.setAttribute('hidden', 'hidden');
        }
        ptToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        ptToggle.textContent = opening ? '🧪 DEBUG ▾' : '🧪 DEBUG';
      });
    }
  }
  initDebugPanel();

  function pintarPlaytestDiag(fromServer) {
    const pre = $('[data-playtest-diag-log]');
    if (!pre) return;
    const serverText = fromServer && fromServer.texto ? String(fromServer.texto) : '';
    const clientBits = playtestLogClient.entries.map(function (e) {
      if (e.tipo === 'API_ERROR') {
        return e.ts + ' | API_ERROR\n' + e.method + ' ' + e.action + ' → HTTP ' + e.status
          + '\nreq: ' + JSON.stringify(e.payload)
          + '\nresp: ' + JSON.stringify(e.respuesta).slice(0, 500)
          + '\ncausa: ' + e.causa;
      }
      return e.ts + ' | ' + (e.tipo || 'CLIENT') + '\n' + JSON.stringify(e);
    });
    const all = [];
    if (serverText) all.push(serverText);
    if (clientBits.length) all.push('--- CLIENT / API ---\n' + clientBits.join('\n\n'));
    pre.textContent = all.length ? all.join('\n\n') : '(aún no hay eventos)';
    try { if (fromServer && fromServer.texto) console.log('[AHT playtest_diag]\n' + fromServer.texto); } catch (e) {}
  }


  function ahtLabAuditLog(payload) {
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
  }

  async function api(action, body, method) {
    body = body || {};
    method = method || 'POST';
    const opts = { method: method, cache: 'no-store' };
    let url;
    if (isDebugOn()) body.debug = 1;
    if (method === 'GET') {
      const q = new URLSearchParams();
      q.set('action', action);
      if (isDebugOn()) q.set('debug', '1');
      if (partidaId) q.set('partida_id', partidaId);
      Object.keys(body).forEach(function (k) {
        const v = body[k];
        if (v === undefined || v === null) return;
        if (Array.isArray(v)) {
          v.forEach(function (item) { q.append(k + '[]', String(item)); });
        } else if (typeof v !== 'object') {
          q.set(k, String(v));
        }
      });
      url = API + '?' + q.toString();
    } else {
      opts.headers = { 'Content-Type': 'application/json' };
      opts.body = JSON.stringify(Object.assign({ partida_id: partidaId }, body));
      url = API + '?action=' + encodeURIComponent(action);
      if (isDebugOn()) url += '&debug=1';
    }
    let resp;
    let raw = '';
    try {
      resp = await fetch(url, opts);
      raw = await resp.text();
    } catch (err) {
      const fail = { ok: false, error: 'network_error', mensaje_ui: 'No se pudo contactar con la API.', detalle: String(err && err.message || err) };
      logApiError(action, method, body, 0, fail, 'network');
      return fail;
    }
    let data;
    try {
      data = raw ? JSON.parse(raw) : {};
    } catch (err) {
      const fail = { ok: false, error: 'respuesta_no_json', status: resp.status, mensaje_ui: 'La API no devolvió JSON.', raw: raw.slice(0, 400) };
      logApiError(action, method, body, resp.status, fail, 'json_parse');
      return fail;
    }
    if (!resp.ok || data.ok === false) {
      logApiError(action, method, body, resp.status, data, data.error || ('http_' + resp.status));
    }
    ahtLabAuditLog(data);
    return data;
  }

  function logApiError(action, method, payload, status, data, causa) {
    const entry = {
      ts: new Date().toISOString().slice(11, 19),
      tipo: 'API_ERROR',
      method: method,
      action: action,
      status: status,
      payload: payload,
      respuesta: data,
      causa: causa
    };
    playtestLogClient.push(entry);
    try { console.warn('[AHT playtest API]', method, action, status, causa, data); } catch (e) {}
    pintarPlaytestDiag();
  }


    const TUT_INTRO_KEY_PREFIX = 'aht_intro_v1_';
  /* Tutorial intro: solo servidor (TutorialPrimerosPasos::vistaPublica). Sin copy legacy en cliente. */
  let tutIntroIdx = 0;

  function tutIntroKey() {
    return TUT_INTRO_KEY_PREFIX + (partidaId || 'sin_partida');
  }
  function tutIntroHecho() {
    try { return localStorage.getItem(tutIntroKey()) === '1'; } catch (e) { return false; }
  }
  function marcarTutIntroHecho() {
    try { localStorage.setItem(tutIntroKey(), '1'); } catch (e) {}
  }
  function tutPasosActuales() {
    if (cacheEstado && cacheEstado.tutorial && cacheEstado.tutorial.intro && cacheEstado.tutorial.intro.pasos) {
      return cacheEstado.tutorial.intro.pasos;
    }
    return [];
  }
  function tieneTutorialV3() {
    return !!(cacheEstado && cacheEstado.tutorial && cacheEstado.tutorial.id === 'primeros_pasos'
      && cacheEstado.tutorial.intro && cacheEstado.tutorial.intro.pasos && cacheEstado.tutorial.intro.pasos.length);
  }
  function pintarTutIntro() {
    const box = $('[data-tut-intro]');
    if (!box) return;
    const pasos = tutPasosActuales();
    const paso = pasos[tutIntroIdx];
    if (!paso) return;

    var papel = $('[data-tut-papel]') || box.querySelector('.tut-papel');
    if (papel) papel.classList.remove('tut-anim');

    $('[data-tut-tit]').textContent = paso.tit || '';

    var introEl = $('[data-tut-intro-line]');
    if (introEl) {
      introEl.textContent = paso.intro || '';
      introEl.hidden = !paso.intro;
    }
    var introExtra = $('[data-tut-intro-extra]');
    if (introExtra) {
      introExtra.textContent = paso.intro_extra || '';
      introExtra.hidden = !paso.intro_extra;
    }

    var carasBox = $('[data-tut-caras]');
    if (carasBox) {
      carasBox.innerHTML = '';
      if (paso.caras && paso.caras.length) {
        carasBox.hidden = false;
        paso.caras.forEach(function (c, i) {
          var wrap = document.createElement('div');
          wrap.className = 'tut-cara-wrap tut-anim-item tut-anim-pop';
          wrap.style.setProperty('--tut-delay', String(100 + i * 90) + 'ms');
          var sp = document.createElement('span');
          sp.className = 'tut-cara';
          sp.innerHTML = c.token_url
            ? '<img src="' + esc(c.token_url) + '" alt=""/>'
            : '<span class="cara-ini">' + esc((c.nombre || '?')[0]) + '</span>';
          wrap.appendChild(sp);
          if (c.nombre) {
            var nm = document.createElement('span');
            nm.className = 'tut-cara-nombre';
            nm.textContent = c.nombre;
            wrap.appendChild(nm);
          }
          carasBox.appendChild(wrap);
        });
      } else {
        carasBox.hidden = true;
      }
    }

    var prefijoEl = $('[data-tut-bloques-pref]');
    if (prefijoEl) {
      prefijoEl.textContent = paso.bloques_prefijo || '';
      prefijoEl.hidden = !paso.bloques_prefijo;
    }

    var bloquesBox = $('[data-tut-bloques]');
    if (bloquesBox) {
      bloquesBox.innerHTML = '';
      bloquesBox.className = 'tut-bloques';
      if (paso.bloques_estilo === 'inline') bloquesBox.classList.add('is-inline');
      if (paso.bloques && paso.bloques.length) {
        bloquesBox.hidden = false;
        paso.bloques.forEach(function (b, i) {
          var div = document.createElement('div');
          div.className = 'tut-bloque tut-anim-item';
          div.style.setProperty('--tut-delay', String(60 + i * 80) + 'ms');
          var sym = document.createElement('span');
          sym.className = 'tut-bloque-sym';
          sym.textContent = b.simbolo || '';
          div.appendChild(sym);
          var body = document.createElement('span');
          body.className = 'tut-bloque-body';
          if (b.tit) {
            var tit = document.createElement('strong');
            tit.className = 'tut-bloque-tit';
            tit.textContent = b.tit;
            body.appendChild(tit);
          }
          if (b.txt) {
            var txt = document.createElement('span');
            txt.className = 'tut-bloque-txt';
            txt.textContent = b.txt;
            body.appendChild(txt);
          }
          div.appendChild(body);
          bloquesBox.appendChild(div);
        });
      } else {
        bloquesBox.hidden = true;
      }
    }

    var tareasBox = $('[data-tut-tareas]');
    if (tareasBox) {
      tareasBox.innerHTML = '';
      if (paso.tareas) {
        tareasBox.hidden = false;
        var nums = ['①', '②', '③'];
        for (var t = 0; t < 3; t++) {
          var mark = document.createElement('span');
          mark.className = 'tut-tarea-mark tut-anim-item tut-anim-pop';
          mark.style.setProperty('--tut-delay', String(t * 80) + 'ms');
          mark.textContent = nums[t];
          tareasBox.appendChild(mark);
        }
      } else {
        tareasBox.hidden = true;
      }
    }

    var cierreEl = $('[data-tut-cierre]');
    if (cierreEl) {
      cierreEl.textContent = paso.cierre || '';
      cierreEl.hidden = !paso.cierre;
    }

    const dots = $('[data-tut-pasos]');
    dots.innerHTML = '';
    tutPasosActuales().forEach(function (_, i) {
      const s = document.createElement('span');
      if (i <= tutIntroIdx) s.className = 'is-on';
      dots.appendChild(s);
    });

    const btnAtras = $('[data-tut-atras]');
    const btnSig = $('[data-tut-siguiente]');
    if (btnAtras) btnAtras.hidden = tutIntroIdx === 0;
    const pasosN = tutPasosActuales();
    const ult = pasosN[tutIntroIdx];
    var esFinal = tutIntroIdx >= pasosN.length - 1;
    if (btnSig) {
      btnSig.textContent = esFinal
        ? (ult && ult.boton_final ? ult.boton_final : 'A ver qué se cuece')
        : 'Siguiente';
      btnSig.classList.toggle('tut-cta-final', esFinal);
    }

    if (papel) {
      requestAnimationFrame(function () {
        papel.classList.add('tut-anim');
      });
    }
  }
  function abrirTutIntro(desdeCero) {
    if (desdeCero) tutIntroIdx = 0;
    const box = $('[data-tut-intro]');
    if (!box) return;
    box.hidden = false;
    document.body.setAttribute('data-tut-activo', '1');
    pintarTutIntro();
  }
  function cerrarTutIntro(marcar, irMisiones) {
    const box = $('[data-tut-intro]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-activo');
    if (marcar) marcarTutIntroHecho();
    if (irMisiones !== false && marcar) {
      setCapa('misiones');
    }
    const reopen = $('[data-tut-reopen]');
    if (reopen) reopen.hidden = false;
  }
  function quizaMostrarTutFinale() {
    var tut = cacheEstado && cacheEstado.tutorial;
    if (!tut || !tut.finale_pendiente || !tut.finale) return;
    var box = $('[data-tut-finale]');
    if (!box) return;
    $('[data-tut-fin-tit]').textContent = tut.finale.tit || '';
    $('[data-tut-fin-texto]').textContent = tut.finale.txt || '';
    var btn = $('[data-tut-fin-ok]');
    if (btn) btn.textContent = tut.finale.boton || 'Que empiece el tema';
    box.hidden = false;
    document.body.setAttribute('data-tut-finale', '1');
  }
  async function cerrarTutFinale() {
    var box = $('[data-tut-finale]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-finale');
    await api('partida.tutorial_finale', {});
    await refresh();
    setCapa('misiones');
  }
  function quizaMostrarTutIntro() {
    const reopen = $('[data-tut-reopen]');
    if (!tieneTutorialV3()) {
      if (reopen) reopen.hidden = true;
      return;
    }
    if (tutIntroHecho()) {
      if (reopen) reopen.hidden = false;
      return;
    }
    abrirTutIntro(true);
  }
  function pintarTutorialMotor(tut) {
    const pista = $('[data-tutorial-pista]');
    if (!pista) return;
    if (tut && tut.id === 'primeros_pasos') {
      pista.hidden = true;
      pista.textContent = '';
      document.body.removeAttribute('data-tutorial-zona');
      return;
    }
    if (!tut || !tut.activo || !tut.pista) {
      pista.hidden = true;
      pista.textContent = '';
      document.body.removeAttribute('data-tutorial-zona');
      return;
    }
    pista.hidden = false;
    pista.textContent = tut.pista;
    if (tut.zona) document.body.setAttribute('data-tutorial-zona', tut.zona);
    else document.body.removeAttribute('data-tutorial-zona');
  }

  function layout() {
    const root = $('.play-root');
    const phone = window.innerWidth < 720;
    root.classList.toggle('phone', phone);
    root.classList.toggle('pc', !phone);
  }

  function toast(txt) {
    const n = $('[data-toast]');
    n.textContent = txt;
    n.classList.add('is-on');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { n.classList.remove('is-on'); }, 2800);
  }

  function uiRootFrom(el) {
    return el && (el.closest('.play-root') || el.closest('.game-shell'));
  }

  function setCapa(name) {
    const root = $('.play-root');
    if (!name) root.removeAttribute('data-capa');
    else root.setAttribute('data-capa', name);
    $$('.dock button').forEach(function (b) {
      const open = b.getAttribute('data-open');
      b.classList.toggle('is-on', name ? open === name : !open);
    });
  }

  function dineroTxt(insp, estado) {
    const eco = (insp && insp.economia && insp.economia.dinero) ? insp.economia.dinero.balance : null;
    const cel = estado && estado.celeste ? estado.celeste.dinero : null;
    const v = eco !== null && eco !== undefined ? eco : cel;
    if (v === null || v === undefined || v === '') return '—';
    return String(Math.round(Number(v))) + ' €';
  }


  function nombreDe(id) {
    const r = (cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id]) || {};
    return (r.identidad_publica && r.identidad_publica.nombre) || id;
  }

﻿  function esIdInterno(s) {
    if (typeof s !== 'string' || !s) return false;
    return /^per_[a-z0-9_]+$/i.test(s) || /^lug_[a-z0-9_]+$/i.test(s) || /^msg_/.test(s);
  }

  function nombrePublicoDe(m) {
    if (!m || typeof m !== 'object') return '';
    if (m.tipo === 'candidato_llegada' && m.texto) {
      const mt = String(m.texto).match(/^([^\n.:]{2,32}?)\s+quiere\s+/i);
      if (mt) return mt[1].trim();
    }
    const remitente = String(m.remitente_nombre || '').trim();
    if (remitente && !esIdInterno(remitente)) return remitente;
    const id = remitenteIdDe(m);
    if (id) {
      const n = nombreDe(id);
      if (n && !esIdInterno(n)) return n;
    }
    // Compatibilidad de lectura: mensajes response_plan antiguos no guardaban remitente.
    if (m.tipo === 'respuesta_plan') {
      const legacy = String(m.texto || '').match(/^(.+?)\s+(?:no han quedado\.?|ha rechazado la propuesta\s*:)/i);
      if (legacy && legacy[1].trim()) return legacy[1].trim();
    }
    const t = String(m.texto || '');
    const ci = t.indexOf(':');
    if (ci > 0 && ci < 28) {
      const pref = t.slice(0, ci).trim();
      if (pref && !esIdInterno(pref)) return pref;
    }
    return 'Alguien';
  }

  function cuerpoMensajito(m, nombre) {
    let t = String(m.texto || '').trim();
    if (m.tipo === 'respuesta_plan' && nombre && t.indexOf(nombre + ' ') === 0) {
      t = t.slice(nombre.length).trim();
    }
    if (nombre && t.indexOf(nombre + ':') === 0) {
      t = t.slice(nombre.length + 1).trim();
    } else if (nombre && t.indexOf(nombre + ' ') === 0) {
      const rest = t.slice(nombre.length).trim();
      if (/^quiere\s+/i.test(rest)) t = rest;
    }
    if (m.plazo_humano) {
      const ph = String(m.plazo_humano).trim();
      if (ph && t.endsWith(ph)) t = t.slice(0, t.length - ph.length).trim();
      t = t.replace(/\s*Te quedan\s+\d+\s*h\s*$/i, '').trim();
    }
    return t;
  }


  function mensajitoRequiereAccion(m) {
    if (!m || typeof m !== 'object') return false;
    if (m.tipo === 'candidato_llegada' && (m.estado || '') === 'pendiente') return true;
    if ((m.clasificacion || '') === 'peticion' && m.peticion_id && (m.estado || '') === 'pendiente') return true;
    return false;
  }

  async function marcarMensajitoLeido(m) {
    if (!m || !m.id || (m.estado || '') !== 'pendiente') return null;
    const lr = await api('buzon.leer', { mensaje_id: m.id });
    return lr.tutorial || null;
  }


  function remitenteIdDe(m) {
    if (!m || typeof m !== 'object') return null;
    const direct = m.de_persona || m.de;
    if (direct) return direct;
    if (m.candidato_catalog_id) return m.candidato_catalog_id;
    const act = m.actores;
    if (Array.isArray(act)) {
      for (let i = 0; i < act.length; i++) {
        if (act[i]) return act[i];
      }
    }
    return null;
  }

  function inicialDe(nombre) {
    if (typeof nombre !== 'string' || !nombre.length) return '?';
    return nombre.charAt(0);
  }

  function htmlAvatarMensajito(m, nombre, cls) {
    const rid = remitenteIdDe(m);
    const tok = rid && !m.candidato_catalog_id ? tokenDe(rid) : null;
    const base = cls || 'msg-item-avatar';
    if (tok) return '<img class="' + base + '" src="' + esc(tok) + '" alt=""/>';
    return '<span class="' + base + ' cara-ini">' + esc(inicialDe(nombre || '?')) + '</span>';
  }

  function mensajitosCartas(msgs) {
    return (msgs || []).filter(function (m) {
      return m && (m.canal || 'buzon') !== 'cotilleo' && String(m.texto || '').trim() !== '';
    });
  }

  function mensajitosOrdenados(msgs) {
    return mensajitosCartas(msgs).slice().sort(function (a, b) {
      const pa = (a.estado || '') === 'pendiente' ? 0 : 1;
      const pb = (b.estado || '') === 'pendiente' ? 0 : 1;
      if (pa !== pb) return pa - pb;
      return (b.dia || 0) - (a.dia || 0);
    });
  }

  function mensajitoEstaLeido(m) {
    return (m.estado || '') !== 'pendiente';
  }

  async function toggleMensajitoLeido(m, leido) {
    if (!m || !m.id) { toast('No se puede marcar este mensaje.'); return false; }
    if (leido) {
      if (mensajitoEstaLeido(m)) return true;
      const lr = await api('buzon.leer', { mensaje_id: m.id });
      if (!lr.ok) { toast(lr.mensaje_ui || 'No se pudo marcar como leido.'); return false; } return lr.tutorial || true;
    }
    if (!mensajitoEstaLeido(m)) return true;
    const nr = await api('buzon.no_leer', { mensaje_id: m.id });
    if (!nr.ok) { toast(nr.mensaje_ui || 'No se pudo desmarcar.'); return false; } return true;
  }

  function crearMsgLeidoToggle(m) {
    const btn = document.createElement('button');
    btn.type = 'button';
    wireMsgLeidoToggle(btn, m);
    return btn;
  }

  function wireMsgLeidoToggle(btn, m) {
    const leido = mensajitoEstaLeido(m);
    btn.className = 'msg-leido-toggle' + (leido ? ' is-leido' : ' is-pendiente');
    btn.textContent = leido ? 'le\u00eddo' : 'marcar';
    btn.setAttribute('aria-pressed', leido ? 'true' : 'false');
    btn.setAttribute('aria-label', leido ? 'Marcar como no le\u00eddo' : 'Marcar como le\u00eddo');
    btn.onclick = null;
    btn.addEventListener('click', async function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      const quiereLeido = btn.getAttribute('aria-pressed') !== 'true';
      const popAbierto = mensajitosPopAbierto;
      const tr = await toggleMensajitoLeido(m, quiereLeido);
      if (tr === false) return;
      
      await refresh();
      if (popAbierto) abrirMensajitosPop();
      if (tr && tr !== true) pintarTutorialMotor(tr);
    });
  }

  function crearMsgItem(m, compact) {
    const row = document.createElement('div');
    const leido = (m.estado || '') !== 'pendiente';
    row.className = 'msg-item' + (leido ? ' leida' : ' no-leida');
    const nombre = nombrePublicoDe(m);
    const cuerpo = cuerpoMensajito(m, nombre);
    row.innerHTML = htmlAvatarMensajito(m, nombre) +
      '<div class="msg-item-copy"><span class="msg-item-nom">' + esc(nombre) + '</span>' +
      (compact ? ' — ' : '<br/>') +
      '<span class="msg-item-txt">' + esc(cuerpo) + '</span></div>';
    row.appendChild(crearMsgLeidoToggle(m));
    return row;
  }

  let mensajitosPopAbierto = false;

  function cerrarMensajitosPop() {
    mensajitosPopAbierto = false;
    const pop = $('[data-mensajitos-pop]');
    const trig = $('[data-mensajitos-trigger]');
    if (pop) pop.hidden = true;
    if (trig) trig.setAttribute('aria-expanded', 'false');
  }

  function abrirMensajitosPop() {
    mensajitosPopAbierto = true;
    const pop = $('[data-mensajitos-pop]');
    const trig = $('[data-mensajitos-trigger]');
    if (pop) pop.hidden = false;
    if (trig) trig.setAttribute('aria-expanded', 'true');
  }

  function toggleMensajitosPop() {
    if (mensajitosPopAbierto) cerrarMensajitosPop();
    else abrirMensajitosPop();
  }

  function renderMensajitosPop(msgs) {
    const box = $('[data-mensajitos-preview]');
    const verMas = $('[data-mensajitos-ver-mas]');
    if (!box) return;
    const cartas = mensajitosOrdenados(msgs);
    box.innerHTML = '';
    if (!cartas.length) {
      box.innerHTML = '<p class="lista-vacia">Sin mensajitos. De momento, silencio.</p>';
      if (verMas) verMas.hidden = true;
      return;
    }
    cartas.slice(0, 3).forEach(function (m) {
      box.appendChild(crearMsgItem(m, true));
    });
    if (verMas) verMas.hidden = cartas.length <= 3;
  }

  function estadoMisionLabel(estado) {
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
      resetOrgForm({ modo: 'pareja', a: params.a, b: params.b });
      orgPresetNuevo = true;
      setCapa('organizar');
      fillOrganizar();
      return;
    }
    if (acc === 'organizar_solo') {
      resetOrgForm({ modo: 'solo', a: params.a, lugar: params.lugar });
      orgPresetNuevo = true;
      setCapa('organizar');
      fillOrganizar();
      return;
    }
    setCapa('misiones');
  }

  function renderMisionesTutorial(items, list, teaser) {
    var sorted = items.slice().sort(function (a, b) {
      return ((a.orden || 0) - (b.orden || 0)) || String(a.titulo || '').localeCompare(String(b.titulo || ''));
    });
    if (teaser) teaser.textContent = 'Primeros pasos';
    list.innerHTML = '';
    sorted.forEach(function (m) {
      list.insertAdjacentHTML('beforeend', htmlMisionItem(m, { primerosPasos: true }));
    });
    enlazarAccionesMision(list, sorted);
    renderMisionesStrip(sorted);
  }

  function renderMisiones(misiones) {
    var teaser = $('[data-misiones-teaser]');
    var list = $('[data-misiones-list]');
    var items = [];
    if (misiones && Array.isArray(misiones.misiones)) items = misiones.misiones;
    else if (misiones && Array.isArray(misiones.items)) items = misiones.items;
    else if (misiones && misiones.misiones_hoy && Array.isArray(misiones.misiones_hoy.misiones)) items = misiones.misiones_hoy.misiones;
    else if (Array.isArray(misiones)) items = misiones;
    var dia = (misiones && misiones.dia) ? misiones.dia : (cacheEstado && cacheEstado.reloj ? cacheEstado.reloj.dia_pueblo : 0);
    if (!dia && cacheEstado && cacheEstado.reloj) dia = cacheEstado.reloj.dia_pueblo;
    var hoy = items.filter(function (m) { return !m.dia || (m.dia || 0) === dia; });
    var pp = hoy.filter(function (m) { return (m.familia || '') === 'primeros_pasos'; });
    if (enTutorialPrimerosPasos() && pp.length >= 3) {
      if (list) renderMisionesTutorial(pp, list, teaser);
      renderMisionesStrip(pp);
      return;
    }
    renderMisionesStrip(hoy);
    if (teaser) {
      var pend = hoy.filter(function (m) { return (m.estado || '') === 'pendiente'; });
      teaser.textContent = pend.length
        ? (pend.length + ' objetivo' + (pend.length === 1 ? '' : 's') + ' pendiente' + (pend.length === 1 ? '' : 's'))
        : (hoy.length ? 'Nada pendiente hoy.' : 'Sin misiones hoy.');
    }
    if (!list) return;
    list.innerHTML = '';
    if (!hoy.length) {
      list.innerHTML = '<p class="mis-vacio">No hay misiones para hoy.</p>';
      return;
    }
    hoy.forEach(function (m) {
      list.insertAdjacentHTML('beforeend', htmlMisionItem(m));
    });
    enlazarAccionesMision(list, hoy);
  }


  const CELESTINE_CAP_VECINOS = 46;
  const CELESTINE_EMO_RESUMEN = [
    { id: 'alegre', icon: '\ud83d\ude0a', label: 'Felices' },
    { id: 'triste', icon: '\ud83d\ude22', label: 'Tristes' },
    { id: 'enfadado', icon: '\ud83d\ude21', label: 'Enfadados' },
  ];

  function metricasSociales(partida) {
    const res = (partida && partida.residentes) || {};
    const ids = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; });
    const emo = { alegre: 0, triste: 0, enfadado: 0 };
    ids.forEach(function (id) {
      const rt = res[id].runtime && res[id].runtime.estado_emocional;
      let eid = rt && rt.id ? String(rt.id) : 'neutro';
      if (eid === 'neutral') eid = 'neutro';
      if (eid === 'alegre') emo.alegre++;
      else if (eid === 'triste') emo.triste++;
      else if (eid === 'enfadado') emo.enfadado++;
    });
    const parejasList = parejasParaUI(partida || {});
    let crisis = 0;
    parejasList.forEach(function (r) {
      if (esCrisisPareja(r)) crisis++;
    });
    const capRaw = partida && partida.celeste && partida.celeste.vivienda_capacidad_max;
    const cap = Number(capRaw) > 0 ? Number(capRaw) : CELESTINE_CAP_VECINOS;
    return { vecinos: ids.length, cap: cap, parejas: parejasList.length, crisis: crisis, emo: emo };
  }

  function htmlFilaCelestineFiltro(opts) {
    return '<div class="vecinos-stat celeste-filtro-pendiente" role="presentation" data-celestine-filtro="' +
      esc(opts.filtro) + '" data-filtro-panel="' + esc(opts.panel) + '">' +
      '<span class="vecinos-stat-ico" aria-hidden="true">' + opts.icon + '</span>' +
      '<span class="vecinos-stat-k">' + esc(opts.label) + '</span>' +
      '<strong class="vecinos-stat-v">' + esc(String(opts.valor)) + '</strong></div>';
  }

  function htmlResumenCelestine(met) {
    const bits = [];
    bits.push('<div class="stat-row celeste-cuenta-vecinos"><span>En el pueblo</span><strong>' +
      esc(String(met.vecinos)) + ' / ' + esc(String(met.cap)) + '</strong></div>');
    CELESTINE_EMO_RESUMEN.forEach(function (def) {
      const n = met.emo[def.id] || 0;
      if (!n) return;
      bits.push(htmlFilaCelestineFiltro({
        filtro: 'vecinos:emo:' + def.id,
        panel: 'vecinos',
        icon: def.icon,
        label: def.label,
        valor: n,
      }));
    });
    if (met.parejas > 0) {
      bits.push('<span class="obj-vecinos-tit celeste-seccion-parejas">Parejas</span>');
      bits.push('<div class="stat-row celeste-cuenta-parejas"><span>Parejas</span><strong>' +
        esc(String(met.parejas)) + '</strong></div>');
      if (met.crisis > 0) {
        bits.push(htmlFilaCelestineFiltro({
          filtro: 'parejas:estado:crisis',
          panel: 'parejas',
          icon: '\ud83d\udc94',
          label: 'En crisis',
          valor: met.crisis,
        }));
      }
    }
    return bits.join('');
  }

  function parejasParaUI(partida) {
    return (partida.relaciones_romanticas || []).filter(function (r) {
      if (!r) return false;
      const est = String(r.estado_pareja || r.estado || '');
      return est === 'pareja' || est === 'crisis';
    });
  }

  function esCrisisPareja(rel) {
    return String(rel && (rel.estado_pareja || rel.estado) || '') === 'crisis';
  }

  function idsPareja(rel) {
    if (rel.persona_a && rel.persona_b) return [rel.persona_a, rel.persona_b];
    if (rel.pareja && rel.pareja.length >= 2) return rel.pareja;
    if (rel.participantes && rel.participantes.length >= 2) return rel.participantes;
    return [];
  }


  function horaEnc(enc) {
    if (!enc) return 0;
    if (enc.hora_inicio != null) return Number(enc.hora_inicio);
    return Number(enc.hora || 0);
  }
  function relojAbs(dia, hora) { return (Number(dia) || 0) * 24 + (Number(hora) || 0); }
  function duracionEncHoras(enc) {
    if (!enc) return 1;
    if (enc.duracion_horas != null) return Math.max(1, Number(enc.duracion_horas));
    if (enc.duracion_minutos != null) return Math.max(1, Math.ceil(Number(enc.duracion_minutos) / 60));
    return 1;
  }
  function esEncuentroFuturo(enc, estado) {
    if (!enc) return false;
    const st = String(enc.estado || '');
    if (st === 'en_curso') return true;
    if (st !== 'programado') return false;
    const reloj = (estado && estado.reloj) || {};
    const now = relojAbs(reloj.dia_pueblo, reloj.hora_actual);
    const start = relojAbs(enc.dia, horaEnc(enc));
    const end = start + duracionEncHoras(enc);
    return now < end;
  }
  function encuentrosFuturos(partida, estado) {
    return ((partida && partida.encuentros) || []).filter(function (e) { return esEncuentroFuturo(e, estado); })
      .sort(function (a, b) {
        const aCurso = String(a.estado || '') === 'en_curso' ? 0 : 1;
        const bCurso = String(b.estado || '') === 'en_curso' ? 0 : 1;
        if (aCurso !== bCurso) return aCurso - bCurso;
        return relojAbs(a.dia, horaEnc(a)) - relojAbs(b.dia, horaEnc(b));
      });
  }
  function formatPlanMeta(enc, estado) {
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    const hora = String(horaEnc(enc)).padStart(2, '0') + ':00';
    const reloj = (estado && estado.reloj) || {};
    const enCurso = planEsEnCurso(enc, estado);

    if (enCurso) return lugar + ' · En curso · ' + hora;
    if (Number(enc.dia) === Number(reloj.dia_pueblo)) return lugar + ' · Hoy ' + hora;
    return lugar + ' · Día ' + (enc.dia || '?') + ' · ' + hora;
  }
  function emocionDe(id) {
    var r = cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id];
    var emo = (r && r.runtime && r.runtime.estado_emocional && r.runtime.estado_emocional.id) || 'neutro';
    if (['neutro', 'alegre', 'triste', 'enfadado'].indexOf(emo) < 0) emo = 'neutro';
    return emo;
  }

  function htmlCaraToken(id, opts) {
    opts = opts || {};
    var emo = opts.emocion || emocionDe(id);
    var img = tokenDe(id);
    var extra = opts.wrapClass ? ' ' + opts.wrapClass : '';
    var imgCls = opts.imgClass ? ' class="' + esc(opts.imgClass) + '"' : '';
    var inner = img
      ? '<img' + imgCls + ' src="' + esc(img) + '" alt=""/>'
      : '<span class="cara-ini' + (opts.imgClass ? ' ' + esc(opts.imgClass) : '') + '">' +
        esc((nombreDe(id)[0] || '?')) + '</span>';
    return '<span class="cara-token' + extra + '" role="button" tabindex="0" data-residente="' + esc(id) +
      '" data-emocion="' + esc(emo) + '"><span class="cara" data-emocion="' + esc(emo) + '">' + inner + '</span></span>';
  }

  function carasPlanHtml(ids) {
    return ids.slice(0, 2).map(function (id) { return htmlCaraToken(id); }).join('');
  }
  function encuentroOcupaAhora(enc, estado) {
    if (!enc) return false;
    var st = String(enc.estado || '');
    if (st !== 'programado' && st !== 'en_curso') return false;
    var reloj = (estado && estado.reloj) || {};
    var now = relojAbs(reloj.dia_pueblo, reloj.hora_actual);
    var start = relojAbs(enc.dia, horaEnc(enc));
    var end = start + duracionEncHoras(enc);
    return now >= start && now < end;
  }  function planEsEnCurso(enc, estado) {
    if (!enc) return false;
    if (String(enc.estado || '') === 'en_curso') return true;
    if (encuentroOcupaAhora(enc, estado)) return true;
    return !!(estado && estado.encuentro_en_curso && estado.encuentro_en_curso.id === enc.id);
  }
  function htmlProximoPlan(enc, estado) {
    const ids = enc.participantes || [];
    const enCurso = planEsEnCurso(enc, estado);
    return '<div class="prox-faces' + (enCurso ? ' prox-faces--en-curso' : '') + '">' + carasPlanHtml(ids) + '</div>' +
      '<p class="prox-nombres">' + esc(ids.map(function (id) { return nombreDe(id); }).join(' · ')) + '</p>' +
      '<p class="prox-meta' + (enCurso ? ' prox-meta--en-curso' : '') + '"><span class="prox-meta-ico" aria-hidden="true"></span>' +
      esc(formatPlanMeta(enc, estado)) + '</p>';
  }
  function renderAgendaPlanes() {
    const box = document.querySelector('[data-agenda-list]');
    if (!box) return;
    const fut = encuentrosFuturos(cacheInsp, cacheEstado);
    box.innerHTML = '';
    if (!fut.length) { box.innerHTML = '<p class="lista-vacia">Nada en agenda.</p>'; return; }
    fut.forEach(function (enc) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'agenda-fila' + (planEsEnCurso(enc, cacheEstado) ? ' agenda-fila--en-curso' : '');
      const ids = enc.participantes || [];
      btn.innerHTML = '<span class="agenda-fila-fotos">' + carasPlanHtml(ids) + '</span>' +
        '<span class="agenda-fila-cuerpo"><span class="agenda-fila-nombres">' +
        esc(ids.map(function (id) { return nombreDe(id); }).join(' · ')) + '</span>' +
        '<span class="agenda-fila-meta">' + esc(formatPlanMeta(enc, cacheEstado)) + '</span></span>';
      box.appendChild(btn);
    });
  }
  function renderShellPanels(estado, buzon, diario) {
    const partida = cacheInsp || {};
    const parejas = parejasParaUI(partida);
        const met = metricasSociales(partida);
    const stats = $('[data-resumen-stats]');
    if (stats) stats.innerHTML = htmlResumenCelestine(met);


    const teaser = $('[data-cotilleo-teaser]');
    const hoy = (diario && diario.cotilleo && diario.cotilleo.hoy) || diario.entradas || [];
    const ult = (hoy[0] && (hoy[0].texto || hoy[0].cuerpo || hoy[0].titulo)) || '';
    if (teaser) teaser.textContent = ult || 'Todavía no hay cotilleo hoy.';

    const prev = $('[data-buzon-preview]');
    const pend = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    if (prev) {
      if (!pend.length) prev.textContent = 'Sin mensajes pendientes.';
      else {
        const m = pend[0];
        prev.textContent = (m.remitente_nombre || m.de || 'Mensaje') + ': ' + (m.preview || m.asunto || m.texto || '').slice(0, 80);
      }
    }

    const proxBox = $('[data-proximo-plan]');
    const verPlanes = $('.obj-ver-planes');
    const polaroid = $('.obj-proximo-polaroid');
    const proxTit = $('.obj-proximo-tit');
    const enCurso = estado.encuentro_en_curso || null;
    const futuros = encuentrosFuturos(partida, estado);
    const ocupandoAhora = futuros.filter(function (e) { return encuentroOcupaAhora(e, estado); })[0] || null;
    const next = enCurso || ocupandoAhora || futuros[0] || null;
    const hayEnCurso = !!(enCurso || (next && planEsEnCurso(next, estado)));
    if (polaroid) {
      polaroid.classList.toggle('is-en-curso', hayEnCurso);
      polaroid.classList.toggle('is-proximo', !!(next && !hayEnCurso));
    }
    if (proxTit) proxTit.textContent = hayEnCurso ? 'Plan en curso' : 'Próximo plan';
    if (proxBox) {
      if (!next) proxBox.innerHTML = '<p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p>';
      else proxBox.innerHTML = htmlProximoPlan(next, estado);
    }
    if (verPlanes) verPlanes.hidden = futuros.length <= 1;

    const strip = $('[data-parejas-strip]');
    if (strip) {
      strip.innerHTML = '';
      parejas.forEach(function (rel) {
        const ids = idsPareja(rel);
        if (!ids || ids.length < 2) return;
        const crisis = esCrisisPareja(rel);
        const row = document.createElement('div');
        row.className = 'obj-pareja-piece' + (crisis ? ' is-crisis' : '');
        const tok = function (id) {
          return htmlCaraToken(id, { imgClass: 'obj-pareja-cara' });
        };
        row.innerHTML = '<span class="obj-pareja-fotos">' + tok(ids[0]) +
          '<span class="obj-pareja-enlace" aria-hidden="true"></span>' + tok(ids[1]) + '</span>' +
          '<span class="obj-pareja-nombres">' + esc(nombreDe(ids[0])) + ' \u00b7 ' + esc(nombreDe(ids[1])) + '</span>' +
          (crisis ? '<span class="pareja-crisis-sello">EN CRISIS</span>' : '');
        strip.appendChild(row);
      });
      if (!parejas.length) {
        strip.innerHTML = '<p class="muted">A\u00fan no hay parejas registradas.</p>';
      }
    }
  }


  var cacheMapaZonas = null;
  var cacheMapaPresencia = null;
  const LUGAR_TITULO_UI = {
    lug_cafeteria: 'Cafetería', lug_biblioteca: 'Biblioteca', lug_gimnasio: 'Gimnasio',
    lug_restaurante: 'Restaurante', lug_parque: 'Parque', lug_bar: 'Bar',
    lug_cine: 'Cine', lug_discoteca: 'Discoteca', lug_bingo: 'Bingo'
  };
  function nombreLugarTitulo(id, fb) {
    if (id && LUGAR_TITULO_UI[id]) return LUGAR_TITULO_UI[id];
    return nombreLugarUi(id, fb);
  }
  const LUGAR_NOMBRE_UI = {
    lug_cafeteria: 'la cafetería', lug_biblioteca: 'la biblioteca', lug_gimnasio: 'el gimnasio',
    lug_restaurante: 'el restaurante', lug_parque: 'el parque', lug_bar: 'el bar',
    lug_cine: 'el cine', lug_discoteca: 'la discoteca', lug_bingo: 'el bingo'
  };
  function nombreLugarUi(id, fallback) {
    if (!id) return fallback || 'ese sitio';
    if (LUGAR_NOMBRE_UI[id]) return LUGAR_NOMBRE_UI[id];
    var fb = fallback || id;
    if (typeof fb === 'string' && fb.indexOf('lug_') === 0) return fb.replace('lug_', '').replace(/_/g, ' ');
    return fb;
  }
  var LUG_TO_ZONA = {
    lug_cafeteria: 'cafeteria', lug_biblioteca: 'biblioteca', lug_gimnasio: 'gimnasio',
    lug_restaurante: 'restaurante', lug_parque: 'parque', lug_bar: 'bar',
    lug_cine: 'cine', lug_discoteca: 'discoteca', lug_bingo: 'bingo'
  };
  var ZONA_TO_LUGS = {
    cafeteria: ['lug_cafeteria'], biblioteca: ['lug_biblioteca'], gimnasio: ['lug_gimnasio'],
    restaurante: ['lug_restaurante'], parque: ['lug_parque'], bar: ['lug_bar'],
    cine: ['lug_cine'], discoteca: ['lug_discoteca'], bingo: ['lug_bingo']
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

  function habPosSeed(rid) {
    var s = 0;
    var str = String(rid || '');
    for (var k = 0; k < str.length; k++) {
      s = ((s << 5) - s + str.charCodeAt(k)) | 0;
    }
    return Math.abs(s);
  }

  /** Slots en franja inferior del hotspot (% left/top). Índice estable por cantidad. */
  function slotsTokensZona(total) {
    var n = Math.max(1, Math.min(5, total || 1));
    if (n === 1) return [{ left: 50, top: 82 }];
    if (n === 2) return [{ left: 34, top: 83 }, { left: 66, top: 83 }];
    if (n === 3) return [{ left: 26, top: 82 }, { left: 50, top: 84 }, { left: 74, top: 82 }];
    if (n === 4) {
      return [
        { left: 22, top: 80 }, { left: 38, top: 86 }, { left: 62, top: 86 }, { left: 78, top: 80 }
      ];
    }
    return [
      { left: 16, top: 81 }, { left: 30, top: 86 }, { left: 50, top: 78 },
      { left: 70, top: 86 }, { left: 84, top: 81 }
    ];
  }

  function placeHabEnZona(box, p, i, total) {
    var el = document.createElement('span');
    el.className = 'hab cara-token';
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    el.setAttribute('data-residente', p.id);
    el.setAttribute('data-destino', p.destino_id);
    el.setAttribute('data-fase', p.fase || 'en_destino');
    el.setAttribute('data-emocion', p.emocion || 'neutro');
    if (p.hay_tema) el.setAttribute('data-hay-tema', '1');
    var slots = slotsTokensZona(total);
    var slot = slots[i] || slots[slots.length - 1];
    var seed = habPosSeed(p.id);
    var jx = ((seed % 5) - 2) * 0.35;
    var jy = (((seed >> 4) % 5) - 2) * 0.25;
    el.style.left = (slot.left + jx).toFixed(2) + '%';
    el.style.top = (slot.top + jy).toFixed(2) + '%';
    var idleKind = ['a', 'b', 'c'][seed % 3];
    el.classList.add('hab-idle-' + idleKind);
    el.style.setProperty('--hab-idle-delay', (-(seed % 800) / 100).toFixed(2) + 's');
    var emo = p.emocion || 'neutro';
    if (['neutro', 'alegre', 'triste', 'enfadado'].indexOf(emo) < 0) emo = 'neutro';
    if (p.token_url) {
      el.innerHTML = '<span class="cara" data-emocion="' + emo + '"><img src="' + p.token_url + '" alt=""/></span>';
    } else {
      el.innerHTML = '<span class="cara cara-ini" data-emocion="' + emo + '">' + (p.iniciales || '?') + '</span>';
    }
    el.setAttribute('data-emocion', emo);
    if (p.hay_tema) {
      el.insertAdjacentHTML('beforeend', '<img class="sello-tema" src="assets/play-v3/marcas/sello_hay_tema.png" alt=""/>');
    }
    box.appendChild(el);
  }

  function marcarConsultaLugar(el, lugarId) {
    if (!el) return;
    el.setAttribute('data-consulta-lugar', String(lugarId || '').replace(/^lug_/, ''));
  }
  function posicionarNotaMapa(el, zonaBtn) {
    if (!el || !zonaBtn) return;
    var board = $('.board-fit');
    if (!board) return;
    var boardRect = board.getBoundingClientRect();
    var zonaRect = zonaBtn.getBoundingClientRect();
    var margen = 10;
    var pw = el.offsetWidth || 240;
    var ph = el.offsetHeight || 160;
    var relX = zonaRect.left - boardRect.left;
    var relY = zonaRect.top - boardRect.top;
    var bw = boardRect.width;
    var bh = boardRect.height;
    var left = relX + zonaRect.width + margen;
    if (left + pw > bw - margen) {
      left = relX - pw - margen;
    }
    if (left < margen) left = margen;
    if (left + pw > bw - margen) left = Math.max(margen, bw - pw - margen);
    var top = relY;
    if (top + ph > bh - margen) top = Math.max(margen, bh - ph - margen);
    if (top < margen) top = margen;
    el.style.right = 'auto';
    el.style.left = left + 'px';
    el.style.top = top + 'px';
  }

  function abrirConsultaZona(zonaId, zonaBtn) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var ops = destinosOperativosZona(zonaId);
    if (ops.length > 1) {
      $('.play-root').setAttribute('data-consulta', 'sel');
    marcarConsultaLugar($('.selector'), zonaId);
      $('[data-s-tit]').textContent = meta ? meta.label : zonaId;
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' · ');
      var box = $('[data-s-btns]');
      box.innerHTML = '';
      ops.forEach(function (d) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Ver ' + nombreLugarUi(d.id, d.nombre);
        b.addEventListener('click', function () { abrirQuienZona(zonaId, d.id, zonaBtn); });
        box.appendChild(b);
      });
      var all = document.createElement('button');
      all.type = 'button';
      all.textContent = 'Quién hay aquí';
      all.addEventListener('click', function () { abrirQuienZona(zonaId, null, zonaBtn); });
      box.appendChild(all);
      posicionarNotaMapa($('.selector'), zonaBtn);
      return;
    }
    abrirQuienZona(zonaId, ops[0] ? ops[0].id : null, zonaBtn);
  }

  function abrirQuienZona(zonaId, destId, zonaBtn) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var gente = personasEnZona(zonaId).filter(function (p) {
      return !destId || p.destino_id === destId;
    });
    $('.play-root').setAttribute('data-consulta', 'quien');
    marcarConsultaLugar($('.quien'), destId || zonaId);
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
      b.textContent = 'Organizar en ' + nombreLugarUi(d.id, d.nombre);
      b.addEventListener('click', function () {
        org.lugar = d.id;
        setCapa('organizar');
        $('.play-root').removeAttribute('data-consulta');
        fillOrganizar();
      });
      box.appendChild(b);
    });
    posicionarNotaMapa($('.quien'), zonaBtn);
  }

  function renderProximoCaras(enc) {
    if (!enc || !enc.participantes || !enc.participantes.length) return '';
    return enc.participantes.map(function (id) {
      var img = tokenDe(id);
      var nom = nombreDe(id);
      if (img) return '<span class="prox-cara"><img src="' + esc(img) + '" alt=""/></span>';
      return '<span class="prox-cara prox-cara-ini">' + esc((nom.charAt(0) || '?')) + '</span>';
    }).join('');
  }
  function buzonNoLeidos(estado, buzon) {
    if (estado && typeof estado.buzon_no_leidos === 'number') return estado.buzon_no_leidos;
    return (buzon || []).filter(function (m) {
      return (m.estado || '') === 'pendiente' && (m.canal || 'buzon') === 'buzon';
    }).length;
  }

  function enTutorialPrimerosPasos() {
    var tut = cacheEstado && cacheEstado.tutorial;
    return tut && tut.id === 'primeros_pasos' && !tut.finale_visto;
  }


  function iconoMision(m) {
    var fam = (m && m.familia) || '';
    var id = (m && m.id) || '';
    if (id === 'pp_plan_solo_cine' || fam === 'cita' || fam === 'primera_cita') {
      return '<svg class="mision-ico-svg" viewBox="0 0 32 28" aria-hidden="true"><path d="M4 6h18l4 4v14H4z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 6l9 0 4 4" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 14h12M10 18h8" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
    if (fam === 'conocerse' || fam === 'quedar' || id === 'pp_romper_hielo') {
      return '<svg class="mision-ico-svg" viewBox="0 0 32 28" aria-hidden="true"><circle cx="11" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="21" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M5 24c1-4 4-6 6-6s5 2 6 6M17 24c1-4 4-6 6-6s5 2 6 6" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
    if (id === 'pp_mensajito' || fam === 'tema') {
      return '<svg class="mision-ico-svg" viewBox="0 0 32 28" aria-hidden="true"><rect x="5" y="7" width="22" height="15" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M5 7l11 9 11-9" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    if (fam === 'lugar') {
      return '<svg class="mision-ico-svg" viewBox="0 0 32 28" aria-hidden="true"><path d="M16 4C11 4 8 8 8 12c0 6 8 12 8 12s8-6 8-12c0-4-3-8-8-8z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
  return '<svg class="mision-ico-svg" viewBox="0 0 32 28" aria-hidden="true"><rect x="7" y="5" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M11 11h10M11 15h7" stroke="currentColor" stroke-width="1.4"/><path d="M20 19l3 3" stroke="currentColor" stroke-width="1.6"/><circle cx="21" cy="17" r="3" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
  }

  function textoMisionStrip(m) {
    var t = (m && m.texto) || '';
    if (t.length > 72) t = t.slice(0, 69) + '…';
    if (!t) t = (m && m.titulo) || 'Objetivo';
    return t;
  }

  function renderMisionesStrip(items) {
    var strip = $('[data-misiones-strip]');
    if (!strip) return;
    var sorted = (items || []).slice().sort(function (a, b) {
      return ((a.orden || 0) - (b.orden || 0)) || String(a.titulo || '').localeCompare(String(b.titulo || ''));
    });
    strip.innerHTML = '';
    if (!sorted.length) {
      strip.innerHTML = '<p class="obj-misiones-vacio">Sin misiones hoy.</p>';
      return;
    }
    sorted.forEach(function (m) {
      var est = m.estado || 'pendiente';
      var row = document.createElement('div');
      row.className = 'mision-strip-row mision-' + est;
      row.innerHTML =
        '<span class="mision-strip-ico">' + iconoMision(m) + '</span>' +
        '<span class="mision-strip-txt">' + esc(textoMisionStrip(m)) + '</span>' +
        bolitaMision(est);
      strip.appendChild(row);
    });
  }

  function htmlMisionItem(m, opts) {
    opts = opts || {};
    var est = m.estado || 'pendiente';
    var accBtn = '';
    if (m.accion && est !== 'bloqueada' && est !== 'cumplida') {
      accBtn = '<button type="button" class="mis-accion mision-accion" data-mision-accion="' + esc(m.id || '') + '">' +
        esc(m.accion_label || 'Ir') + '</button>';
    }
    var titulo = m.titulo
      ? '<strong class="mis-item-tit">' + esc(m.titulo) + '</strong>'
      : '';
    var estado = opts.primerosPasos
      ? ''
      : '<span class="mis-item-estado">' + esc(estadoMisionLabel(est)) + '</span>';
    return '<article class="mis-item mis-item-' + est + (opts.primerosPasos ? ' mis-item-pp' : '') + '">' +
      '<span class="mis-item-tape mis-item-tape-l" aria-hidden="true"></span>' +
      '<span class="mis-item-tape mis-item-tape-r" aria-hidden="true"></span>' +
      '<div class="mis-item-head">' + bolitaMision(est) + titulo + '</div>' +
      '<p class="mis-item-txt">' + esc(m.texto || m.hecho || 'Objetivo') + '</p>' +
      estado + accBtn + '</article>';
  }

  function enlazarAccionesMision(container, items) {
    if (!container) return;
    var byId = {};
    (items || []).forEach(function (m) {
      if (m && m.id) byId[m.id] = m;
    });
    $$('[data-mision-accion]', container).forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var id = btn.getAttribute('data-mision-accion');
        ejecutarAccionMision(byId[id] || null);
      });
    });
  }

  function bolitaMision(estado) {
    if (estado === 'cumplida') {
      return '<span class="mision-bolita cumplida" aria-label="Hecha"><span class="mision-check">✓</span></span>';
    }
    if (estado === 'bloqueada') {
      return '<span class="mision-bolita bloqueada" aria-hidden="true"></span>';
    }
    return '<span class="mision-bolita pendiente" aria-hidden="true"></span>';
  }

  let corazonOlaPhase = 0;
  let corazonOlaFillY = 52;
  let corazonOlaFillH = 0;
  let corazonOlaTimer = null;

  function corazonOlaY(x, fillY, phase) {
    return fillY + Math.sin((x * 0.54) + phase) * 3.6;
  }

  function corazonFillPathD(fillY, phase) {
    var bottom = 52;
    var parts = [];
    for (var x = 0; x <= 58; x += 1.1) {
      var y = corazonOlaY(x, fillY, phase);
      parts.push((parts.length ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1));
    }
    return parts.join(' ') + ' L58 ' + bottom + ' L0 ' + bottom + ' Z';
  }

  function corazonSurfacePathD(fillY, phase) {
    var parts = [];
    for (var x = 0; x <= 58; x += 1.1) {
      var y = corazonOlaY(x, fillY, phase);
      parts.push((parts.length ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1));
    }
    return parts.join(' ');
  }

  function aplicarCorazonAgua(fillEl, surfaceEl, fillY, fillH, phase) {
    if (!fillEl) return;
    if (fillH <= 0.5) {
      fillEl.setAttribute('d', '');
      if (surfaceEl) surfaceEl.setAttribute('d', '');
      return;
    }
    fillEl.setAttribute('d', corazonFillPathD(fillY, phase));
    if (surfaceEl) surfaceEl.setAttribute('d', corazonSurfacePathD(fillY, phase));
  }

  function animarCorazonAgua() {
    if (corazonOlaTimer) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    corazonOlaTimer = setInterval(function () {
      if (corazonOlaFillH <= 0.5) return;
      corazonOlaPhase += 0.24;
      aplicarCorazonAgua(
        document.querySelector('[data-corazon-fill]'),
        document.querySelector('[data-corazon-surface]'),
        corazonOlaFillY,
        corazonOlaFillH,
        corazonOlaPhase
      );
    }, 130);
  }

  function corazonVidaSvg() {
    var el = $('.top-vida .corazon-svg');
    if (el) return el;
    return $('.corazon-svg');
  }

  function corazonVidaOnReactionEnd(svg, cls) {
    function onEnd(e) {
      if (e.target !== svg) return;
      if (e.animationName !== 'corazon-vida-sube' && e.animationName !== 'corazon-vida-baja') return;
      svg.removeEventListener('animationend', onEnd);
      svg.classList.remove(cls);
      svg.classList.add('corazon-vida--reposo');
    }
    svg.addEventListener('animationend', onEnd);
  }

  function triggerCorazonVidaReaction(svg, dir) {
    const cls = dir === 'sube' ? 'corazon-vida--sube' : 'corazon-vida--baja';
    svg.classList.remove('corazon-vida--reposo', 'corazon-vida--sube', 'corazon-vida--baja');
    svg.classList.add(cls);
    corazonVidaOnReactionEnd(svg, cls);
  }

  function renderHud(estado, buzon) {
    const rv = estado.reloj_vista || {};
    const reloj = estado.reloj || {};
    const diaNum = reloj.dia_pueblo;
    const fechaCorta = rv.fecha_corta || '';
    const diaNumEl = $('[data-dia-num]');
    if (diaNumEl) {
      if (diaNum !== undefined && diaNum !== null) {
        const placa = 'Día ' + diaNum;
        diaNumEl.textContent = fechaCorta ? (placa + ' · ' + fechaCorta) : placa;
      } else {
        diaNumEl.textContent = 'DÍA —';
      }
    }
    const h = rv.hora !== undefined ? rv.hora : reloj.hora_actual;
    const ht = h === undefined ? '' : (String(h).padStart(2, '0') + ':00');
    $$('[data-dow]').forEach(function (el) {
      el.textContent = rv.dia_semana_ui || (diaNum !== undefined ? ('Día ' + diaNum) : '—');
    });
    $$('[data-fecha]').forEach(function (el) {
      el.textContent = fechaCorta;
    });
    $$('[data-hora]').forEach(function (el) {
      el.textContent = ht || '—';
    });
    const estEl = $('[data-dia-estacion]');
    if (estEl) {
      const tempMap = { temp_01: 'Primavera' };
      const tempId = reloj.temporada_id || 'temp_01';
      estEl.textContent = tempMap[tempId] || 'Primavera';
    }
    const metaEl = $('[data-dia-meta]');
    if (metaEl) {
      const diaTemp = reloj.dia_en_temporada;
      const diasTemp = 24;
      metaEl.textContent = (diaTemp !== undefined && diaTemp !== null)
        ? (diaTemp + '/' + diasTemp)
        : '—';
    }
    const vida = estado.vida_pueblo || null;
    const pct = vida && typeof vida.corazon_pct === 'number' ? vida.corazon_pct : 0;
    const critico = !!(vida && vida.critico);
    const fillEl = $('[data-corazon-fill]');
    const surfaceEl = $('[data-corazon-surface]');
    if (fillEl) {
      var fillH = 52 * (pct / 100);
      var fillY = 52 - fillH;
      corazonOlaFillY = fillY;
      corazonOlaFillH = fillH;
      aplicarCorazonAgua(fillEl, surfaceEl, fillY, fillH, corazonOlaPhase);
      animarCorazonAgua();
    }
    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';
    const corazonSvg = corazonVidaSvg();
    if (corazonSvg) {
      corazonSvg.classList.toggle('corazon-vida--critico', critico);
      if (vidaCorazonReady && vidaCorazonPctPrev !== null && pct !== vidaCorazonPctPrev) {
        triggerCorazonVidaReaction(corazonSvg, pct > vidaCorazonPctPrev ? 'sube' : 'baja');
      } else if (!vidaCorazonReady) {
        corazonSvg.classList.add('corazon-vida--reposo');
      }
      vidaCorazonPctPrev = pct;
      vidaCorazonReady = true;
    }
    const nPend = buzonNoLeidos(estado, buzon);
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
    const cartas = (buzon || []).filter(function (m) {
      return (m.estado || '') === 'pendiente' || (m.estado || '') === 'en_espera';
    });
    const imp = cartas.some(function (m) { return m.clasificacion === 'importante'; });
    if (imp) $('.play-root').setAttribute('data-importante', '1');
    else $('.play-root').removeAttribute('data-importante');
  }

  function placeHab(box, p, i, cid) {
    const el = document.createElement('span');
    el.className = 'hab';
    el.setAttribute('data-residente', p.id);
    el.setAttribute('data-complejo', cid);
    el.setAttribute('data-destino', p.destino_id);
    el.setAttribute('data-fase', p.fase || 'en_destino');
    el.setAttribute('data-emocion', p.emocion || 'neutro');
    if (p.hay_tema) el.setAttribute('data-hay-tema', '1');
    const slots = (SLOTS[cid] && SLOTS[cid][p.destino_id]) || [[22 + i * 12, 50]];
    const xy = slots[Math.min(i, slots.length - 1)];
    el.style.left = xy[0] + '%';
    el.style.top = xy[1] + '%';
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


  function renderMapaMarcas(mapa) {
    cacheMapaPresencia = mapa || null;
    var layer = $('[data-mapa-zonas]');
    if (!layer) return;
    layer.querySelectorAll('.mapa-zona-hit').forEach(function (btn) {
      btn.classList.remove('mapa-zona--proximo', 'mapa-zona--en-curso');
      btn.removeAttribute('data-encuentro-marca');
    });
    (mapa && mapa.lugares || []).forEach(function (lug) {
      var marca = lug.encuentro_marca;
      if (!marca) return;
      var zid = LUG_TO_ZONA[lug.id];
      if (!zid) return;
      var btn = layer.querySelector('[data-zona="' + zid + '"]');
      if (!btn) return;
      btn.setAttribute('data-encuentro-marca', marca);
      btn.classList.add(marca === 'en_curso' ? 'mapa-zona--en-curso' : 'mapa-zona--proximo');
    });
  }

  function renderPueblo(pueblo) {
    cachePueblo = pueblo;
    var layer = $('[data-mapa-zonas]');
    if (!layer) return;
    $$('.mapa-zona-hit .habs').forEach(function (b) { b.innerHTML = ''; });
    var porZona = {};
    (pueblo.complejos || []).forEach(function (cx) {
      ((cx.visibles && cx.visibles.length) ? cx.visibles : (cx.personas || [])).forEach(function (p) {
        var zid = LUG_TO_ZONA[p.destino_id];
        if (!zid) return;
        if (!porZona[zid]) porZona[zid] = [];
        if (porZona[zid].length >= 5) return;
        porZona[zid].push(p);
      });
    });
    Object.keys(porZona).forEach(function (zid) {
      var btn = layer.querySelector('[data-zona="' + zid + '"]');
      if (!btn) return;
      var box = btn.querySelector('.habs');
      if (!box) return;
      var enZona = porZona[zid].slice().sort(function (a, b) {
        return String(a.id).localeCompare(String(b.id));
      });
      var marcaZona = btn.getAttribute('data-encuentro-marca') || '';
      enZona.forEach(function (p, i) {
        placeHabEnZona(box, p, i, enZona.length);
      });
    });
  }

  function applyFases(pueblo) {
    $$('.complejo').forEach(function (el) {
      const id = el.getAttribute('data-complejo');
      const cx = (pueblo.complejos || []).filter(function (c) { return c.id === id; })[0];
      el.classList.toggle('is-pleno', !!(cx && cx.fase === 'pleno'));
    });
  }

  function pintarEdificios(btn, cx) {
    const ops = {};
    (cx.destinos || []).forEach(function (d, i) {
      if (i === 0 || d.operativo) ops[d.id] = true;
    });
    const edifs = btn.querySelectorAll('.edif');
    let alguna = false;
    for (let j = 0; j < edifs.length; j++) {
      const img = edifs[j];
      const show = !!ops[img.getAttribute('data-destino')];
      img.classList.toggle('is-on', show);
      if (show) alguna = true;
    }
    btn.classList.toggle('tiene-edifs', alguna);
  }

  function cxById(id) {
    return (cachePueblo && cachePueblo.complejos || []).filter(function (c) { return c.id === id; })[0];
  }

  function abrirConsulta(id) {
    const cx = cxById(id);
    if (!cx) return;
    const ops = cx.destinos_operativos || [];
    if (ops.length > 1) {
      $('.play-root').setAttribute('data-consulta', 'sel');
    marcarConsultaLugar($('.selector'), id);
      $('[data-s-tit]').textContent = cx.nombre;
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' · ');
      const box = $('[data-s-btns]');
      box.innerHTML = '';
      ops.forEach(function (d) {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Ver ' + nombreLugarUi(d.id, d.nombre);
        b.addEventListener('click', function () { abrirQuien(id, d.id); });
        box.appendChild(b);
      });
      const all = document.createElement('button');
      all.type = 'button';
      all.textContent = 'Quién hay en el complejo';
      all.addEventListener('click', function () { abrirQuien(id, null); });
      box.appendChild(all);
      return;
    }
    abrirQuien(id, ops[0] ? ops[0].id : null);
  }

  function abrirQuien(id, destId) {
    const cx = cxById(id);
    $('.play-root').setAttribute('data-consulta', 'quien');
    marcarConsultaLugar($('.quien'), id);
    $('[data-q-tit]').textContent = cx.nombre;
    const gente = (cx.personas || []).filter(function (p) {
      return !destId || p.destino_id === destId;
    });
    $('[data-q-sum]').textContent = gente.length
      ? (gente.length === 1 ? 'Hay alguien.' : ('Hay ' + gente.length + '.'))
      : copyVacio(id);
    const list = $('[data-q-list]');
    list.innerHTML = '';
    const groups = {};
    gente.forEach(function (p) {
      if (!groups[p.destino_nombre]) groups[p.destino_nombre] = [];
      groups[p.destino_nombre].push(p);
    });
    Object.keys(groups).forEach(function (dest) {
      const h = document.createElement('p');
      h.className = 'quien-dest';
      h.textContent = dest;
      list.appendChild(h);
      const ul = document.createElement('ul');
      groups[dest].forEach(function (p) {
        const li = document.createElement('li');
        li.textContent = p.nombre;
        ul.appendChild(li);
      });
      list.appendChild(ul);
    });
    const box = $('[data-q-btns]');
    box.innerHTML = '';
    (cx.destinos_operativos || []).forEach(function (d) {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Organizar en ' + nombreLugarUi(d.id, d.nombre);
      b.addEventListener('click', function () {
        org.lugar = d.id;
        setCapa('organizar');
        $('.play-root').removeAttribute('data-consulta');
        fillOrganizar();
      });
      box.appendChild(b);
    });
  }

  function copyVacio(cid) {
    const t = {
      cafe_libros: 'Ni el café humea. No hay ni un alma.',
      rincon_lola: 'Hoy Lola no tendría a quién servir.',
      cine_game: 'Pantalla en negro. No hay ni un alma.',
      mala_idea: 'Hasta el bar está en silencio.',
      parque: 'Solo el banco, esperando.',
      gimnasio_spa: 'Máquinas quietas. No hay ni un alma.'
    };
    return t[cid] || 'No hay ni un alma.';
  }

  function tokenDe(rid) {
    if (!rid) return null;
    var tokens = cachePueblo && cachePueblo.tokens;
    if (tokens && tokens[rid] && tokens[rid].url) return tokens[rid].url;
    var res = cacheInsp && cacheInsp.residentes && cacheInsp.residentes[rid];
    if (res && res.retrato_url) return res.retrato_url;
    var hitUrl = null;
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.personas || []).forEach(function (p) {
        if (p.id === rid) hitUrl = p.token_url || hitUrl;
      });
      if (!hitUrl) {
        (c.visibles || []).forEach(function (p) {
          if (p.id === rid) hitUrl = p.token_url || hitUrl;
        });
      }
    });
    return hitUrl || null;
  }

  function retratoDe(rid, ficha) {
    var pres = ficha && ficha.presentacion_visual;
    var asset = pres && pres.asset;
    if (asset && asset.url_relativa) return asset.url_relativa;
    return tokenDe(rid);
  }

  let vecBuscaTxt = '';

  function txtBuscaNorm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();
  }
  let orgBuscaTxt = '';
  let resBloqueActivo = 'a';
  let resBuscaTxt = '';

  function renderVecinos() {
    const box = $('[data-vecinos-list]');
    if (!box) return;
    box.classList.add('vecinos-grid');
    box.innerHTML = '';
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const filtroTxt = txtBuscaNorm(vecBuscaTxt);
    const ids = Object.keys(res).filter(function (id) {
      const r = res[id];
      if ((r.presencia || 'residente') !== 'residente') return false;
      const nom = txtBuscaNorm((r.identidad_publica && r.identidad_publica.nombre) || id);
      if (filtroTxt && nom.indexOf(filtroTxt) < 0) return false;
      return true;
    });
    ids.sort(function (a, b) {
      const na = (res[a].identidad_publica && res[a].identidad_publica.nombre) || a;
      const nb = (res[b].identidad_publica && res[b].identidad_publica.nombre) || b;
      return String(na).localeCompare(String(nb), 'es');
    });
    const metVec = metricasSociales(cacheInsp || {});
    const cuenta = $('[data-vecinos-count]');
    if (cuenta) cuenta.textContent = String(metVec.vecinos) + ' / ' + String(metVec.cap);
    if (!ids.length) {
      box.innerHTML = '<p class="lista-vacia vecinos-vacio">' +
        (filtroTxt ? 'Nadie con ese nombre.' : 'Todavía no hay vecinos en esta partida.') + '</p>';
      return;
    }
    ids.forEach(function (id) {
      const r = res[id];
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'vecino-celda';
      const img = tokenDe(id);
      const nom = (r.identidad_publica && r.identidad_publica.nombre) || id;
      const ini = nom.charAt(0) || '?';
      b.innerHTML = '<div class="vecino-cara">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="vecino-ini">' + esc(ini) + '</span>') +
        '</div><p class="vecino-nom">' + esc(nom) + '</p>';
      b.addEventListener('click', function () { abrirFicha(id); });
      box.appendChild(b);
    });
  }

  var RES_BLOQUES = [
    { letra: 'a', key: 'bloque_a' },
    { letra: 'b', key: 'bloque_b' },
    { letra: 'c', key: 'bloque_c' }
  ];

  function bloqueInfo(letra) {
    var partida = cacheInsp || {};
    var def = RES_BLOQUES.filter(function (b) { return b.letra === letra; })[0];
    var blk = def ? partida[def.key] : null;
    var viviendas = (blk && Array.isArray(blk.viviendas)) ? blk.viviendas : [];
    return { viviendas: viviendas, abierto: viviendas.length > 0 };
  }


  let fichaActualId = '';
  let fichaRelCache = [];

  function relacionesConocidas(f) {
    const rels = [];
    const raw = (f && f.relaciones) || {};
    Object.keys(raw).forEach(function (oid) {
      const r = raw[oid];
      if (r && r.conocidos) rels.push(r);
    });
    rels.sort(function (a, b) {
      const pa = barRelPct(a);
      const pb = barRelPct(b);
      if (pb !== pa) return pb - pa;
      if (a.etiqueta_vinculo === 'crisis') return -1;
      if (b.etiqueta_vinculo === 'crisis') return 1;
      return String(a.nombre).localeCompare(String(b.nombre), 'es');
    });
    return rels;
  }

  function emojiRel(rel) {
    if (!rel) return '';
    if (rel.etiqueta_vinculo === 'crisis') return '💔 ';
    if (rel.etiqueta_vinculo === 'pareja') return '❤️ ';
    if (rel.etiqueta_vinculo === 'ex_pareja') return '💔 ';
    const s = rel.etiqueta_social || '';
    if (s === 'cae_mal') return '😒 ';
    if (s === 'buena_amistad' || s === 'muy_buena_amistad') return '🤝 ';
    if (s === 'amigo') return '🙂 ';
    if (s === 'conocido') return '👋 ';
    return '';
  }

  function htmlRelRow(rel) {
    const cara = tokenDe(rel.id);
    const ini = (rel.nombre || '?').charAt(0);
    const pct = barRelPct(rel);
    const lbl = etiquetaRelText(rel);
    const crisis = rel.etiqueta_vinculo === 'crisis';
    return (
      '<div class="ficha-rel-row' + (crisis ? ' is-crisis' : '') + '">' +
      '<div class="ficha-rel-cara">' +
      (cara ? '<img src="' + esc(cara) + '" alt=""/>' : '<span>' + esc(ini) + '</span>') +
      '</div>' +
      '<div class="ficha-rel-main">' +
      '<div class="ficha-rel-nom">' + esc(rel.nombre || rel.id) + '</div>' +
      '<div class="ficha-rel-bar"><span style="width:' + pct + '%"></span></div>' +
      '</div>' +
      '<span class="ficha-rel-etiq">' + esc(emojiRel(rel) + lbl) + '</span>' +
      '</div>'
    );
  }

  function pintarRelacionesEn(box, rels, limit) {
    if (!box) return;
    box.innerHTML = '';
    if (!rels.length) {
      box.innerHTML = '<p class="ficha-vacio">De momento, solo le conoces a ti. O eso dice el pueblo.</p>';
      return;
    }
    rels.slice(0, limit).forEach(function (rel) {
      box.insertAdjacentHTML('beforeend', htmlRelRow(rel));
    });
  }

  function cerrarFichaRelOverlay() {
    const overlay = $('[data-ficha-rel-overlay]');
    if (overlay) overlay.hidden = true;
  }

  function abrirFichaRelOverlay(nombre) {
    const overlay = $('[data-ficha-rel-overlay]');
    const list = $('[data-ficha-rel-list]');
    const tit = $('[data-ficha-rel-modal-tit]');
    if (!overlay || !list) return;
    if (tit) tit.textContent = 'Relaciones de ' + (nombre || 'vecino');
    pintarRelacionesEn(list, fichaRelCache, fichaRelCache.length);
    overlay.hidden = false;
  }

function canonEmoId(id) {
    const e = String(id || 'neutro').toLowerCase();
    if (e === 'neutral' || e === 'neutro') return 'neutro';
    if (e === 'alegre' || e === 'triste' || e === 'enfadado') return e;
    return 'neutro';
  }

  function etiquetaVecinoDesde(vista, dia) {
    const g = vista && vista.genero;
    const rol = g === 'mujer' ? 'Vecina' : (g === 'hombre' ? 'Vecino' : 'Vecino');
    return rol + ' desde el día ' + dia;
  }

  function textoAnimoDisplay(emo) {
    const map = {
      neutro: 'neutral',
      alegre: 'alegre',
      triste: 'triste',
      enfadado: 'enfadada'
    };
    return map[emo] || emo.replace(/_/g, ' ');
  }

  function svgAnimoBadge(emo) {
    const tint = { neutro: '#9a8a78', alegre: '#7a9e6a', triste: '#8a9eb8', enfadado: '#c45' }[emo] || '#9a8a78';
    const face = '<circle cx="16" cy="16" r="10" fill="#fffdf8" stroke="' + tint + '" stroke-width="1.3"/>';
    const eyes = '<circle cx="12" cy="14" r="1.1" fill="#3a3028"/><circle cx="20" cy="14" r="1.1" fill="#3a3028"/>';
    const doodles = {
      neutro: face + eyes + '<line x1="11" y1="19" x2="21" y2="19" stroke="#3a3028" stroke-width="1.3" stroke-linecap="round"/>',
      alegre: face + eyes + '<path d="M11 18.5q5 4.5 10 0" stroke="#3a3028" stroke-width="1.3" fill="none" stroke-linecap="round"/>',
      triste: face + eyes + '<path d="M11 21q5-3.5 10 0" stroke="#3a3028" stroke-width="1.3" fill="none" stroke-linecap="round"/><path d="M21 12l1.5 2.5" stroke="' + tint + '" stroke-width="1.1" stroke-linecap="round"/>',
      enfadado: face + '<path d="M10 12.5l3 1.5M22 12.5l-3 1.5" stroke="#3a3028" stroke-width="1.2" stroke-linecap="round"/><circle cx="12" cy="15" r="1.1" fill="#3a3028"/><circle cx="20" cy="15" r="1.1" fill="#3a3028"/><path d="M11 20.5q5-3 10 0" stroke="#3a3028" stroke-width="1.3" fill="none" stroke-linecap="round"/>'
    };
    const body = doodles[emo] || doodles.neutro;
    return '<svg class="ficha-animo-svg" viewBox="0 0 32 32" width="32" height="32" aria-hidden="true">' + body + '</svg>';
  }

  function pintarAnimoFicha(vista) {
    const emo = canonEmoId(vista.estado_animo);
    const txtEl = $('[data-ficha-animo-text]');
    const icoEl = $('[data-ficha-animo-ico]');
    if (txtEl) txtEl.textContent = textoAnimoDisplay(emo);
    if (icoEl) {
      icoEl.setAttribute('data-emo', emo);
      icoEl.innerHTML = svgAnimoBadge(emo);
    }
  }

function slotsDesdeLista(lista) {
    const l = (lista || []).slice(0, 3);
    const out = [];
    for (let i = 0; i < 3; i++) {
      out.push({ descubierto: !!l[i], texto: l[i] || null });
    }
    return out;
  }

function hobbyIconKey(id, texto) {
    if (id) return String(id);
    const t = String(texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const map = [
      ['cafe', 'cafe_social'],
      ['leer', 'leer'], ['biblioteca', 'leer'],
      ['escribir', 'escribir'],
      ['pasear', 'pasear'], ['parque', 'pasear'],
      ['correr', 'correr'],
      ['manualidades', 'manualidades'],
      ['cocina', 'cocina'],
      ['musica', 'musica'],
      ['cine', 'cine'],
      ['videojuegos', 'videojuegos'],
      ['copas', 'copas'],
      ['baile', 'baile'],
      ['bingo', 'bingo'],
      ['deporte', 'deporte'],
      ['senderismo', 'senderismo'],
      ['plantas', 'plantas'],
      ['costura', 'costura']
    ];
    for (let i = 0; i < map.length; i++) {
      if (t.indexOf(map[i][0]) >= 0) return map[i][1];
    }
    return '';
  }

  function svgHobbyPaths(key) {
    const k = hobbyIconKey(key, key);
    const paths = {
      leer: '<path d="M9 8h6.5c1 0 1.5.5 1.5 1.5V24H9z"/><path d="M15 8H21.5c1 0 1.5.5 1.5 1.5V24H15z"/><path d="M9 8c0-1.5 2-2.5 4.5-2.5S18 6.5 18 8"/><path d="M15 8c0-1.5 2-2.5 4.5-2.5S24 6.5 24 8"/>',
      escribir: '<path d="M9 23l9-9 3 3-9 9H9z"/><path d="M20 11l3-3 2 2-3 3"/><path d="M7 25h18"/>',
      pasear: '<path d="M8 24c2-5 4-7 8-7s6 2 8 7"/><circle cx="11" cy="12" r="2.5"/><path d="M11 14.5V20"/><path d="M21 20c0-3 1-5.5 3-7"/><path d="M24 24h-6"/>',
      correr: '<path d="M10 24l2.5-6 4 1.5 3.5 4.5"/><path d="M9 15l4.5-1.5 5 3"/><circle cx="13" cy="9" r="2.2"/>',
      cafe_social: '<path d="M10 12h9v7c0 2-1.2 3.5-4.5 3.5S10 21 10 19z"/><path d="M19 14h2.5c1 0 1.8.8 1.8 1.8s-.8 1.7-1.8 1.7"/><path d="M12 9.5c0-.8.6-1.2 1.2-1.2"/><path d="M16 8.8c0-.8.6-1.2 1.2-1.2"/><path d="M20 9.5c0-.8.6-1.2 1.2-1.2"/>',
      manualidades: '<circle cx="11" cy="11" r="3.5"/><circle cx="21" cy="21" r="3.5"/><path d="M13.5 13.5l5.5 5.5"/>',
      cocina: '<path d="M9 14h14v9H9z"/><path d="M11 14V10"/><path d="M16 14V9"/><path d="M21 14V10"/><path d="M9 18h14"/>',
      musica: '<path d="M13 9v12"/><path d="M13 9l8-2v9"/><ellipse cx="10" cy="21" rx="2.8" ry="2.5"/><ellipse cx="21" cy="19" rx="2.8" ry="2.5"/>',
      cine: '<rect x="7" y="11" width="18" height="11" rx="1.2"/><path d="M7 15h18"/><path d="M11 11v4"/><path d="M15 11v4"/><path d="M19 11v4"/><path d="M23 11v4"/>',
      videojuegos: '<rect x="6" y="13" width="20" height="9" rx="2.5"/><path d="M12 16.5v5"/><path d="M9.5 19h5"/><circle cx="22" cy="16.5" r="1"/><circle cx="24.5" cy="19" r="1"/>',
      copas: '<path d="M11 11h8v5c0 2-1.5 3.2-4 3.2s-4-1.2-4-3.2z"/><path d="M15 19.5v3"/><path d="M11 24.5h8"/>',
      baile: '<circle cx="16" cy="9" r="2.2"/><path d="M11 24l4-8 5 2.5 4 5.5"/><path d="M8 15l6-2"/>',
      bingo: '<rect x="8" y="8" width="16" height="16" rx="2"/><path d="M8 14h16"/><path d="M8 20h16"/><path d="M14 8v16"/><path d="M20 8v16"/>',
      deporte: '<circle cx="16" cy="16" r="7.5"/><path d="M16 8.5v15"/><path d="M8.5 16h15"/><path d="M10 10.5c3 2 9 2 12 0"/><path d="M10 21.5c3-2 9-2 12 0"/>',
      senderismo: '<path d="M6 24l7-12 3.5 5 5.5-7 4 7"/><path d="M6 24h20"/>',
      plantas: '<path d="M16 24V13"/><path d="M16 15c-4.5-2-8.5 0-8.5 6"/><path d="M16 17c4.5-2 8.5 0 8.5 6"/><path d="M12 24h8"/>',
      costura: '<path d="M10 24l6-13 6 13"/><path d="M13 18.5h6"/><circle cx="16" cy="9" r="2"/>'
    };
    return paths[k] || paths.leer;
  }

  function svgHobbyIcon(id, texto) {
    const key = hobbyIconKey(id, texto);
    const body = svgHobbyPaths(key);
    return '<svg class="ficha-hobby-svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false">' +
      '<g fill="none" stroke="#2c261f" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round">' +
      body + '</g></svg>';
  }

  function emojiHobby(txt) {
    const t = String(txt || '').toLowerCase();
    if (t.indexOf('café') >= 0 || t.indexOf('cafe') >= 0) return '☕ ';
    if (t.indexOf('biblioteca') >= 0 || t.indexOf('leer') >= 0) return '📖 ';
    if (t.indexOf('pasear') >= 0 || t.indexOf('parque') >= 0) return '🌳 ';
    return '';
  }

  function pintarSlotsRasgos(box, slots) {
    if (!box) return;
    box.innerHTML = '';
    const items = (slots && slots.length) ? slots : slotsDesdeLista([]);
    items.forEach(function (sl) {
      const sp = document.createElement('span');
      sp.className = 'ficha-rasgo-tag' + (sl.descubierto ? '' : ' is-desconocido');
      sp.textContent = sl.descubierto ? String(sl.texto).toUpperCase() : '?';
      box.appendChild(sp);
    });
  }

  function pintarSlotsHobbies(box, slots) {
    if (!box) return;
    box.innerHTML = '';
    const items = (slots && slots.length) ? slots : slotsDesdeLista([]);
    items.forEach(function (sl) {
      const card = document.createElement('div');
      card.className = 'ficha-hobby-card' + (sl.descubierto ? '' : ' is-desconocido');
      const ico = document.createElement('span');
      ico.className = 'ficha-hobby-ico';
      const lab = document.createElement('span');
      lab.className = 'ficha-hobby-lab';
      if (sl.descubierto) {
        ico.innerHTML = svgHobbyIcon(sl.id, sl.texto);
        lab.textContent = sl.texto || '';
      } else {
        ico.innerHTML = '<span class="ficha-hobby-q">?</span>';
        lab.textContent = '?';
        lab.className += ' is-desconocido';
      }
      card.appendChild(ico);
      card.appendChild(lab);
      box.appendChild(card);
    });
  }

  function slotGenteTxt(sl) {
    if (sl && sl.descubierto && sl.texto) return String(sl.texto);
    return '?';
  }

  function lineaGente(slots) {
    const items = (slots && slots.length >= 2) ? slots.slice(0, 2) : [null, null];
    return 'Gente: ' + slotGenteTxt(items[0]) + ' · ' + slotGenteTxt(items[1]);
  }

  function textoPlanesVacios(id) {
    const frases = [
      'Ni un café en el horizonte. O eso cree.',
      'Agenda libre. Demasiado libre.',
      'Hoy no tiene nada apuntado. Ni mañana, según parece.',
      'Cero planes. Cero prisas. Cero drama… por ahora.'
    ];
    let h = 0;
    const s = String(id || '');
    for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % frases.length;
    return frases[h];
  }

  function diaLlegadaVecino(id) {
    const insp = cacheInsp || {};
    const llegadas = insp.llegadas || {};
    const hist = llegadas.historial || [];
    for (let i = hist.length - 1; i >= 0; i--) {
      const h = hist[i];
      if (h && h.catalog_id === id && h.resultado === 'llegado') return Number(h.dia || 1);
    }
    const tut = llegadas.tutorial_hechas || [];
    for (let j = 0; j < tut.length; j++) {
      if (tut[j].catalog_id === id) return Number(llegadas.tutorial_completado_dia || 1);
    }
    return 1;
  }

  function etiquetaRelText(rel) {
    if (!rel) return '—';
    if (rel.etiqueta_vinculo === 'crisis') return 'En crisis';
    if (rel.etiqueta_vinculo === 'pareja') return 'Pareja';
    if (rel.etiqueta_vinculo === 'ex_pareja') return 'Ex pareja';
    const map = {
      desconocido: 'Desconocido',
      conocido: 'Conocido',
      amigo: 'Amigo',
      buena_amistad: 'Buena amistad',
      muy_buena_amistad: 'Buena amistad',
      cae_mal: 'Cae mal'
    };
    return map[rel.etiqueta_social] || String(rel.etiqueta_social || '—').replace(/_/g, ' ');
  }

  function barRelPct(rel) {
    if (rel.etiqueta_vinculo === 'pareja') return 96;
    if (rel.etiqueta_vinculo === 'crisis') return 52;
    if (rel.etiqueta_vinculo === 'ex_pareja') return 38;
    const s = rel.etiqueta_social || '';
    if (s === 'muy_buena_amistad') return 92;
    if (s === 'buena_amistad') return 86;
    if (s === 'amigo') return 76;
    if (s === 'conocido') return 62;
    if (s === 'cae_mal') return 18;
    if (s === 'desconocido') return 34;
    return 48;
  }


  function planesDeVecino(id) {
    return encuentrosFuturos(cacheInsp, cacheEstado).filter(function (e) {
      return (e.participantes || []).indexOf(id) >= 0;
    }).slice(0, 4);
  }

  function pintarFicha(id, f, vista) {
    fichaActualId = id;
    const nom = vista.nombre || (f.identidad && f.identidad.nombre) || id;
    const img = retratoDe(id, f);
    const caraBox = $('[data-ficha-img]');
    if (caraBox) {
      caraBox.innerHTML = img
        ? '<img src="' + esc(img) + '" alt=""/>'
        : '<span class="ficha-ini">' + esc((nom.charAt(0) || '?')) + '</span>';
    }
    const nomEl = $('[data-ficha-nombre]');
    if (nomEl) nomEl.textContent = nom;
    const desdeEl = $('[data-ficha-desde]');
    if (desdeEl) desdeEl.textContent = etiquetaVecinoDesde(vista, diaLlegadaVecino(id));
    pintarAnimoFicha(vista);
    const rasgosBox = $('[data-ficha-rasgos]');
    pintarSlotsRasgos(rasgosBox, vista.rasgos_slots || slotsDesdeLista(vista.manera_de_ser));
    const hobbiesBox = $('[data-ficha-hobbies]');
    pintarSlotsHobbies(hobbiesBox, vista.hobbies_slots || slotsDesdeLista(vista.gusta));
    const gustaGenteEl = $('[data-ficha-gusta-gente]');
    if (gustaGenteEl) gustaGenteEl.textContent = lineaGente(vista.gusta_en_gente);
    const noGustaGenteEl = $('[data-ficha-nogusta-gente]');
    if (noGustaGenteEl) noGustaGenteEl.textContent = lineaGente(vista.no_gusta_en_gente);
    const relBox = $('[data-ficha-relaciones]');
    const relMasBtn = $('[data-ficha-rel-mas]');
    fichaRelCache = relacionesConocidas(f);
    pintarRelacionesEn(relBox, fichaRelCache, 2);
    if (relMasBtn) {
      if (fichaRelCache.length > 2) {
        relMasBtn.hidden = false;
        relMasBtn.textContent = 'Ver más relaciones';
        relMasBtn.onclick = function () { abrirFichaRelOverlay(nom); };
      } else {
        relMasBtn.hidden = true;
        relMasBtn.onclick = null;
      }
    }
    cerrarFichaRelOverlay();
    const planBox = $('[data-ficha-planes]');
    if (planBox) {
      planBox.innerHTML = '';
      const planes = planesDeVecino(id);
      if (!planes.length) {
        planBox.innerHTML = '<p class="ficha-vacio ficha-ironico">«Su agenda está sospechosamente tranquila.»</p>';
      } else {
        planes.forEach(function (enc) {
          const p = document.createElement('p');
          p.className = 'ficha-plan-item';
          p.textContent = formatPlanMeta(enc, cacheEstado);
          planBox.appendChild(p);
        });
      }
    }
    const orgBtn = $('[data-ficha-org]');
    if (orgBtn) {
      orgBtn.onclick = function () {
        org.a = id;
        setCapa('organizar');
        fillOrganizar();
      };
    }
  }

  async function abrirFicha(id) {
    const r = await api('residente.ficha', { residente_id: id }, 'GET');
    if (!r.ok) return;
    if (r.tutorial) pintarTutorialMotor(r.tutorial);
    const f = r.ficha || {};
    const vista = f.vista_play || f;
    pintarFicha(id, f, vista);
    setCapa('ficha');
  }

  function estadoCarta(m) {
    const pueblo = m.estado_pueblo || '';
    if (pueblo === 'cumplida') return { cls: 'estado-cumplida', txt: 'Hecho' };
    if (pueblo === 'caducada') return { cls: 'estado-caducada', txt: 'Se le pasó' };
    if ((m.estado || '') === 'pendiente') return { cls: 'estado-pendiente', txt: '' };
    if ((m.estado || '') === 'leido') return { cls: 'estado-leida', txt: '' };
    if ((m.estado || '') === 'en_espera') return { cls: 'estado-espera', txt: 'En espera' };
    if ((m.estado || '') === 'resuelto') return { cls: 'estado-cumplida', txt: 'Ya está' };
    return { cls: '', txt: '' };
  }

  function cuerpoCarta(m, de) {
    let t = String(m.texto || '').trim();
    if (de && t.indexOf(de + ':') === 0) t = t.slice(de.length + 1).trim();
    if (de && t.indexOf(de + ' ') === 0) {
      /* deja el resto */
    }
    return t;
  }

  function renderBuzon(msgs) {
    cacheBuzon = msgs || [];
    const box = $('[data-buzon-list]');
    if (!box) return;
    box.innerHTML = '';
    renderMensajitosPop(msgs);
    const cartas = mensajitosOrdenados(msgs);
    if (!cartas.length) {
      box.innerHTML = '<p class="lista-vacia">Nada por aquí. Celestine respira.</p>';
      return;
    }
    const accion = cartas.filter(mensajitoRequiereAccion);
    const info = cartas.filter(function (m) { return !mensajitoRequiereAccion(m); });

    function pintarCarta(m, esAccion) {
      const art = document.createElement('article');
      const st = estadoCarta(m);
      const leido = (m.estado || '') !== 'pendiente';
      art.className = 'carta-msg' +
        (esAccion ? ' carta-accion' : ' carta-info') +
        (leido ? ' leida' : ' no-leida') +
        (st.cls ? ' ' + st.cls : '');
      const nombre = nombrePublicoDe(m);
      const cuerpo = cuerpoMensajito(m, nombre);
      const plazo = m.plazo_humano || '';
      let accionesHtml = '';
      if (m.tipo === 'candidato_llegada' && (m.estado || '') === 'pendiente') {
        accionesHtml = '<div class="acciones-msg">' +
          '<button type="button" data-accion="aceptar">Dejarle hueco</button>' +
          '<button type="button" class="btn-suave" data-accion="rechazar">Ahora no</button>' +
          '</div>';
      }
      art.innerHTML = '<div class="carta-inner">' + htmlAvatarMensajito(m, nombre, 'carta-avatar') +
        '<div class="carta-copy">' +
        (st.txt ? '<div class="sello-estado">' + esc(st.txt) + '</div>' : '') +
        (nombre ? '<div class="de">' + esc(nombre) + '</div>' : '') +
        '<p class="cuerpo">' + esc(cuerpo) + '</p>' +
        (plazo ? '<p class="plazo">' + esc(plazo) + '</p>' : '') +
        accionesHtml +
        '</div></div>';
      art.appendChild(crearMsgLeidoToggle(m));

      art.querySelectorAll('[data-accion]').forEach(function (btn) {
        btn.addEventListener('click', async function (ev) {
          ev.stopPropagation();
          const acc = btn.getAttribute('data-accion');
          if (acc === 'aceptar') await api('llegada.aceptar', { mensaje_id: m.id });
          else if (acc === 'rechazar') await api('llegada.rechazar', { mensaje_id: m.id });
          await refresh();
        });
      });
      return art;
    }

    function pintarSeccion(titulo, items, esAccion) {
      if (!items.length) return;
      const sec = document.createElement('section');
      sec.className = 'mensajitos-seccion';
      sec.innerHTML = '<h3 class="mensajitos-seccion-tit">' + titulo + '</h3>';
      items.forEach(function (m) { sec.appendChild(pintarCarta(m, esAccion)); });
      box.appendChild(sec);
    }

    pintarSeccion('Piden algo', accion, true);
    pintarSeccion('Lo que circula', info, false);
  }

  function cotiEtiquetaTiempo(e, bucket) {
    if (bucket === 'ayer') return 'Ayer';
    if (e.fecha_corta) return e.fecha_corta;
    if (bucket === 'hoy') return 'Hoy';
    return e.dia ? ('día ' + e.dia) : '';
  }

  function htmlCotiAvatares(actores) {
    const ids = (actores && actores.length) ? actores.slice(0, 2) : [];
    if (!ids.length) return '<span class="coti-item-sin-cara" aria-hidden="true">…</span>';
    return ids.map(function (id) {
      const img = tokenDe(id);
      const nom = nombreDe(id);
      const ini = (String(nom).charAt(0) || '?');
      return '<span class="coti-item-cara">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="coti-item-ini">' + esc(ini) + '</span>') +
        '</span>';
    }).join('');
  }

  function htmlCotiItem(e, bucket) {
    const nuevo = e.nuevo === true;
    return '<article class="coti-item">' +
      '<span class="coti-item-tape coti-item-tape-l" aria-hidden="true"></span>' +
      '<span class="coti-item-tape coti-item-tape-r" aria-hidden="true"></span>' +
      (nuevo ? '<span class="coti-item-nuevo">Nuevo</span>' : '') +
      '<div class="coti-item-avatares">' + htmlCotiAvatares(e.actores) + '</div>' +
      '<div class="coti-item-cuerpo">' +
      '<p class="coti-item-txt">' + esc(e.texto || '') + '</p>' +
      '<p class="coti-item-cuando">' + esc(cotiEtiquetaTiempo(e, bucket)) + '</p>' +
      '</div></article>';
  }

  function renderCotilleo(coti) {
    function fill(sel, items, bucket) {
      const box = $(sel);
      if (!box) return;
      box.innerHTML = '';
      if (!items || !items.length) {
        box.innerHTML = '<p class="coti-vacio">Hoy el pueblo no ha dado titular.</p>';
        return;
      }
      items.forEach(function (e) {
        box.insertAdjacentHTML('beforeend', htmlCotiItem(e, bucket));
      });
    }
    fill('[data-coti-hoy]', coti && coti.hoy, 'hoy');
    fill('[data-coti-ayer]', coti && coti.ayer, 'ayer');
    fill('[data-coti-viejos]', coti && coti.viejos, 'viejos');
  }

  function idsResidentes() {
    return Object.keys((cacheInsp && cacheInsp.residentes) || {});
  }

  function orgTipoIco(id) {
    const k = String(id || '').toLowerCase();
    if (k === 'romance') return '❤️';
    if (k === 'amistad') return '🤝';
    if (k === 'conocerse') return '👋';
    if (k === 'individual') return '🚶';
    return '';
  }

  function orgTipoHtml(id, label, on) {
    const ico = orgTipoIco(id);
    const cls = 'org-tipo-chip' + (on ? ' is-on' : '');
    return '<button type="button" class="' + cls + '" data-org-tipo="' + esc(id) + '">' +
      (ico ? '<span class="org-tipo-ico" aria-hidden="true">' + ico + '</span>' : '') +
      esc(String(label || '').toUpperCase()) + '</button>';
  }

  function orgIdsFiltrados() {
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const filtroTxt = txtBuscaNorm(orgBuscaTxt);
    return Object.keys(res).filter(function (id) {
      const r = res[id];
      if ((r.presencia || 'residente') !== 'residente') return false;
      const nom = txtBuscaNorm((r.identidad_publica && r.identidad_publica.nombre) || id);
      if (filtroTxt && nom.indexOf(filtroTxt) < 0) return false;
      return true;
    }).sort(function (a, b) {
      const na = (res[a].identidad_publica && res[a].identidad_publica.nombre) || a;
      const nb = (res[b].identidad_publica && res[b].identidad_publica.nombre) || b;
      return String(na).localeCompare(String(nb), 'es');
    });
  }

  function orgSeleccionados() {
    if (org.modo === 'solo') return org.a ? [org.a] : [];
    const out = [];
    if (org.a) out.push(org.a);
    if (org.b && org.b !== org.a) out.push(org.b);
    return out;
  }

  function actualizarOrgPickerHint() {
    const hint = $('[data-org-picker-hint]');
    if (!hint) return;
    const n = orgSeleccionados().length;
    if (org.modo === 'solo') {
      hint.textContent = n ? '1 vecino elegido.' : 'Elige 1 vecino.';
      return;
    }
    if (!n) hint.textContent = 'Elige hasta 2 vecinos.';
    else if (n === 1) hint.textContent = 'Elige un vecino más.';
    else hint.textContent = '2 vecinos elegidos.';
  }

  function toggleOrgPicker(id) {
    if (!id) return;
    if (org.modo === 'solo') {
      org.a = org.a === id ? '' : id;
      org.b = '';
    } else if (org.a === id) {
      org.a = org.b || '';
      org.b = '';
    } else if (org.b === id) {
      org.b = '';
    } else if (!org.a) {
      org.a = id;
    } else if (!org.b) {
      org.b = id;
    } else {
      toast('Ya elegiste a dos vecinos.');
      return;
    }
    pintarOrgPicker();
    actualizarOrgPickerHint();
    refreshTipos();
    refreshOrgHoras();
  }

  function pintarOrgPicker() {
    const box = $('[data-org-picker]');
    if (!box) return;
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const ids = orgIdsFiltrados();
    const sel = orgSeleccionados();
    box.innerHTML = '';
    if (!ids.length) {
      box.innerHTML = '<p class="mini">Nadie con ese nombre.</p>';
      return;
    }
    ids.forEach(function (id) {
      const r = res[id] || {};
      const nom = (r.identidad_publica && r.identidad_publica.nombre) || nombreDe(id) || id;
      const ini = String(nom).charAt(0) || '?';
      const img = tokenDe(id);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'org-picker-celda' + (sel.indexOf(id) >= 0 ? ' is-on' : '');
      btn.title = nom;
      btn.setAttribute('aria-label', nom);
      btn.innerHTML = '<span class="org-picker-cara">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="org-picker-ini">' + esc(ini) + '</span>') +
        '</span>' + (sel.indexOf(id) >= 0 ? '<span class="org-picker-check" aria-hidden="true">✓</span>' : '');
      btn.addEventListener('click', function () { toggleOrgPicker(id); });
      box.appendChild(btn);
    });
  }

  function fillSelect(sel, value, excludeId) {
    sel.innerHTML = '<option value="">—</option>';
    idsResidentes().forEach(function (id) {
      if (excludeId && id === excludeId) return;
      const o = document.createElement('option');
      o.value = id;
      o.textContent = nombreDe(id);
      sel.appendChild(o);
    });
    if (value && value !== excludeId) sel.value = value;
    else if (value && value === excludeId) sel.value = '';
  }

  function destinosOperativos() {
    const out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.destinos_operativos || []).forEach(function (d) { out.push(d); });
    });
    return out;
  }

  function pintarOrgCaras() {
    pintarOrgPicker();
    actualizarOrgPickerHint();
  }

  function resetOrgForm(preset) {
    var p = preset || {};
    org.modo = p.modo === 'solo' ? 'solo' : 'pareja';
    org.tipo = org.modo === 'solo' ? 'individual' : '';
    org.a = p.a || '';
    org.b = org.modo === 'solo' ? '' : (p.b || '');
    org.lugar = p.lugar || '';
    org.dia = null;
    org.hora = 17;
  }

  function setOrgModo(modo) {
    org.modo = modo === 'solo' ? 'solo' : 'pareja';
    if (org.modo === 'solo') {
      org.b = '';
      org.tipo = 'individual';
    } else {
      org.tipo = '';
      if (org.a && org.b && org.a === org.b) org.b = '';
    }
    var rowB = $('[data-org-row-b]');
    if (rowB) rowB.hidden = org.modo === 'solo';
    $$('[data-org-modo]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-org-modo') === org.modo);
    });
    fillOrganizar();
  }
  async function refreshOrgHoras() {
    var hora = $('[data-org-hora]');
    if (!hora) return;
    hora.innerHTML = '';
    org.lugar = $('[data-org-lugar]').value;
    org.dia = parseInt($('[data-org-dia]').value, 10);
    if (!org.dia && cacheEstado && cacheEstado.reloj) org.dia = cacheEstado.reloj.dia_pueblo;
    var parts = (org.modo === 'solo' ? [org.a] : [org.a, org.b]).filter(Boolean);
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

  async function fillOrganizar() {
    const lug = $('[data-org-lugar]');
    lug.innerHTML = '<option value="">—</option>';
    destinosOperativos().forEach(function (d) {
      const o = document.createElement('option');
      o.value = d.id;
      o.textContent = d.nombre;
      lug.appendChild(o);
    });
    if (org.lugar) lug.value = org.lugar;
    const rv = (cacheEstado && cacheEstado.reloj_vista) || {};
    const dias = rv.proximos_dias || [];
    const fd = $('[data-org-dia]');
    fd.innerHTML = '';
    dias.forEach(function (d) {
      const o = document.createElement('option');
      o.value = String(d.dia_pueblo);
      o.textContent = d.etiqueta || ('día ' + d.dia_pueblo);
      fd.appendChild(o);
    });
    org.dia = org.dia || (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo);
    if (org.dia) fd.value = String(org.dia);
    pintarOrgCaras();
    await refreshTipos();
    await refreshOrgHoras();
  }

  async function refreshTipos() {
    const box = $('[data-org-tipos]');
    if (org.modo === 'solo') {
      if (!org.a) {
        box.innerHTML = '<p class="mini org-tipos-vacio">Elige a quién organizar el plan.</p>';
        return;
      }
      const rSolo = await api('encuentro.tipos_permitidos', { participantes: [org.a], modo: 'solo' }, 'GET');
      org.tipo = 'individual';
      box.innerHTML = orgTipoHtml('individual', 'Por su cuenta', true);
      return;
    }
    if (!org.a || !org.b || org.a === org.b) {
      box.innerHTML = '<p class="mini org-tipos-vacio">Elige a dos personas distintas.</p>';
      return;
    }
    const r = await api('encuentro.tipos_permitidos', { residente_a: org.a, residente_b: org.b }, 'GET');
    box.innerHTML = '';
    const ops = r.opciones || [];
    const ids = ops.map(function (op) { return op.id; });
    if (!org.tipo || ids.indexOf(org.tipo) < 0) {
      org.tipo = r.tipo_sugerido || (ops[0] && ops[0].id) || '';
    }
    box.innerHTML = ops.map(function (op) {
      return orgTipoHtml(op.id, op.label, org.tipo === op.id);
    }).join('');
    $$('[data-org-tipo]', box).forEach(function (btn) {
      btn.addEventListener('click', function () {
        org.tipo = btn.getAttribute('data-org-tipo') || '';
        $$('[data-org-tipo]', box).forEach(function (c) {
          c.classList.toggle('is-on', c === btn);
        });
        refreshOrgHoras();
      });
    });
    if (!ops.length) {
      box.innerHTML = '<p class="mini">' + (r.mensaje_ui || 'Entre estas dos, ahora no sale un plan.') + '</p>';
    }
  }

  async function proponer() {
    org.lugar = $('[data-org-lugar]').value;
    org.dia = parseInt($('[data-org-dia]').value, 10);
    org.hora = parseInt($('[data-org-hora]').value, 10);
    if (org.modo === 'solo') {
      if (!org.a || !org.lugar || !org.dia) {
        toast('Falta quién, dónde o cuándo.');
        return;
      }
    } else if (!org.a || !org.b || org.a === org.b || !org.lugar || !org.dia) {
      toast(org.a && org.a === org.b ? 'Elige a dos personas distintas.' : 'Falta quién, dónde o cuándo.');
      return;
    }
    const payload = {
      participantes: org.modo === 'solo' ? [org.a] : [org.a, org.b],
      dia: org.dia,
      hora: org.hora,
      tipo: org.modo === 'solo' ? 'individual' : (org.tipo || ''),
      lugar: org.lugar,
      modo: org.modo
    };
    const r = await api('encuentro.proponer', payload);
    if (r.playtest_diag) pintarPlaytestDiag(r.playtest_diag);
    try {
      console.log('[AHT plan]', payload, {
        ok: r.ok,
        rechazada: r.rechazada,
        rechazo_clase: r.rechazo_clase,
        error: r.error,
        mensaje_ui: r.mensaje_ui,
        reacciones: r.propuesta && r.propuesta.reacciones
      });
    } catch (e) {}
    if (r.ok) {
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
      if (r.nuevo_mensajito) {
        toast(r.mensajito_aviso_ui || 'Tienes un nuevo Mensajito.');
        $('.play-root').setAttribute('data-importante', '1');
      }
      setCapa('');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);
      quizaMostrarTutFinale();
    } else {
      toast(r.mensaje_ui || 'No se ha podido organizar el plan.');
      await refresh();
    }
  }
  async function ensurePartida() {
    if (partidaId) return true;
    const r = await api('partida.nueva', configNueva(true));
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem(storageKey(), partidaId);
    }
    return !!r.ok;
  }
  async function refresh() {
    const popMensajitosAbierto = mensajitosPopAbierto;
    let paquete = await api('partida.refresh', {}, 'GET');
    if (!paquete.ok && partidaId) {
      try { localStorage.removeItem(storageKey()); } catch (e) {}
      partidaId = null;
      if (await ensurePartida()) {
        paquete = await api('partida.refresh', {}, 'GET');
      }
    }
    if (!paquete.ok) return;
    cacheEstado = paquete.estado || null;
    cacheInsp = paquete.partida || null;
    const mapa = { mapa: paquete.mapa || {}, pueblo: paquete.pueblo || {} };
    const buzon = paquete.buzon || {};
    const diario = paquete.diario || {};
    renderHud(cacheEstado, buzon.mensajes || []);
    renderMapaMarcas(mapa.mapa || null);
    renderPueblo(mapa.pueblo || { complejos: [] });
    renderShellPanels(cacheEstado, buzon.mensajes || [], diario);
      renderMisiones(cacheEstado.misiones_hoy || (cacheInsp && cacheInsp.misiones_diarias));
    renderBuzon(buzon.mensajes || []);
    renderCotilleo(diario.cotilleo || { hoy: diario.entradas || [], ayer: [], viejos: [] });
    renderVecinos();
    if (isDebugOn()) {
      const tm = $('[data-taller-msg]');
      if (tm) tm.textContent = cacheEstado.reloj_texto || '';
    }
    pintarTutorialMotor(cacheEstado.tutorial);
    quizaMostrarTutFinale();
    if ($('.play-root') && $('.play-root').getAttribute('data-capa') === 'agenda') renderAgendaPlanes();
    if (popMensajitosAbierto) abrirMensajitosPop();
  }

  async function nuevaPartidaLimpia() {
    try { localStorage.removeItem(tutIntroKey()); } catch (e) {}
    localStorage.removeItem(storageKey());
    partidaId = null;
    cacheEstado = null;
    cacheInsp = null;
    cachePueblo = null;
    cacheBuzon = [];
    vidaCorazonPctPrev = null;
    vidaCorazonReady = false;
    org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };
    playtestLogClient.entries = [];
    setCapa('');
    const r = await api('partida.nueva', configNueva(true));
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem(storageKey(), partidaId);
      playtestLogClient.push({
        ts: new Date().toISOString().slice(11, 19),
        tipo: 'NUEVA_PARTIDA',
        partida_id: partidaId,
        seed: (r.partida && r.partida.meta && r.partida.meta.seed) || null
      });
      toast('Partida nueva (seed limpia).');
    } else {
      toast(r.mensaje_ui || 'No se pudo crear la partida.');
    }
    await refresh();
    quizaMostrarTutIntro();
  }

  (function bindDebugControls() {
    const btnGuardar = $('#btn-debug-guardar');
    if (btnGuardar) btnGuardar.addEventListener('click', async function () {
      await api('partida.guardar', {});
      toast('Guardado.');
    });
    const btnNueva = $('#btn-debug-nueva');
    if (btnNueva) btnNueva.addEventListener('click', async function () {
      ahtDebugSessionLog.length = 0;
      await nuevaPartidaLimpia();
    });
    const btnCopy = $('#btn-debug-copy');
    if (btnCopy) btnCopy.addEventListener('click', function () { copiarDebugExport(false); });
    const btnCopyEstado = $('#btn-debug-copy-estado');
    if (btnCopyEstado) btnCopyEstado.addEventListener('click', function () { copiarDebugExport(true); });
    const btnParejasCrear = $('#btn-debug-parejas-crear');
    if (btnParejasCrear) btnParejasCrear.addEventListener('click', crearParejasPruebaDebug);
    const btnParejasQuitar = $('#btn-debug-parejas-quitar');
    if (btnParejasQuitar) btnParejasQuitar.addEventListener('click', quitarParejasPruebaDebug);
  })();

  async function crearParejasPruebaDebug() {
    if (!isDebugOn()) {
      toast('Activa DEBUG primero.');
      return;
    }
    const r = await api('partida.debug_parejas_crear', {});
    if (!r.ok) {
      toast(r.mensaje_ui || 'No se pudieron crear las parejas de prueba.');
      return;
    }
    try {
      console.log('%c[AHT DEBUG PAREJAS]', 'color:#c45;font-weight:bold', r.debug_parejas || r);
      console.log('[AHT DEBUG PAREJAS] JSON', JSON.stringify(r.debug_parejas || r, null, 2));
    } catch (e) {}
    toast('Parejas de prueba creadas.');
    await refresh();
  }

  async function quitarParejasPruebaDebug() {
    if (!isDebugOn()) {
      toast('Activa DEBUG primero.');
      return;
    }
    const r = await api('partida.debug_parejas_quitar', {});
    if (!r.ok) {
      toast(r.mensaje_ui || 'No se pudieron quitar las parejas de prueba.');
      return;
    }
    try {
      console.log('%c[AHT DEBUG PAREJAS]', 'color:#c45;font-weight:bold', r.debug_parejas || r);
      console.log('[AHT DEBUG PAREJAS] JSON', JSON.stringify(r.debug_parejas || r, null, 2));
    } catch (e) {}
    toast((r.n || 0) > 0 ? 'Parejas de prueba eliminadas.' : 'No hab\u00eda parejas de prueba.');
    await refresh();
  }

  function pintarPlaytestGuia(guia, evento) {
    const box = $('[data-playtest-guia]');
    if (!box) return;
    if (!guia || !guia.activo) {
      box.hidden = true;
      return;
    }
    box.hidden = false;
    const tit = $('[data-pg-titulo]');
    if (tit) tit.textContent = guia.titulo || 'PRUEBA DEL PUEBLO';
    const rel = $('[data-pg-reloj]');
    if (rel) rel.textContent = guia.reloj_humano || '';
    const ahora = $('[data-pg-ahora]');
    if (ahora) {
      ahora.innerHTML = '';
      (guia.ahora_mismo || []).forEach(function (l) {
        const li = document.createElement('li');
        li.textContent = l;
        ahora.appendChild(li);
      });
    }
    const hacer = $('[data-pg-hacer]');
    if (hacer) {
      hacer.innerHTML = '';
      (guia.que_hacer_ahora || []).forEach(function (l) {
        const li = document.createElement('li');
        li.textContent = l;
        hacer.appendChild(li);
      });
    }
    const ev = $('[data-pg-evento]');
    const ultimo = evento || guia.ultimo;
    if (ev) {
      if (ultimo && (ultimo.titulo || (ultimo.lineas && ultimo.lineas.length))) {
        ev.hidden = false;
        const lines = (ultimo.lineas || []).map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('');
        ev.innerHTML = '<strong>' + esc(ultimo.titulo || 'HA PASADO ALGO') + '</strong><ul>' + lines + '</ul>';
      } else {
        ev.hidden = true;
        ev.innerHTML = '';
      }
    }
    const pist = $('[data-pg-pistas]');
    if (pist) {
      pist.innerHTML = '';
      (guia.pistas || []).forEach(function (p) {
        const d = document.createElement('div');
        d.className = 'pista ' + (p.tipo === 'ojo' ? 'ojo' : 'puedes');
        d.innerHTML = '<strong>' + esc(p.titulo || '') + '</strong><div>' + esc(p.texto || '') + '</div>';
        pist.appendChild(d);
      });
    }
    const objs = $('[data-pg-objs]');
    if (objs) {
      objs.innerHTML = '';
      (guia.objetivos || []).forEach(function (o) {
        const li = document.createElement('li');
        if (o.hecho) li.className = 'hecho';
        li.textContent = o.label || o.id;
        objs.appendChild(li);
      });
    }
  }

  function pintarResumenAvance(resumen) {
    const el = $('[data-taller-debug]');
    if (!el) return;
    const lineas = (resumen && resumen.lineas) || [];
    if (!lineas.length) {
      el.hidden = true;
      el.classList.remove('is-on');
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.classList.add('is-on');
    el.textContent = lineas.map(function (l) { return '· ' + (l.texto || l.tipo || ''); }).join('\n');
  }

  async function avanzarHoras(horas) {
    const r = await api('reloj.avanzar', { horas: horas, paso_a_paso: true });
    await refresh();
    if (r.playtest_guia) pintarPlaytestGuia(r.playtest_guia, r.playtest_guia_evento);
    if (r.playtest_diag) pintarPlaytestDiag(r.playtest_diag);
    pintarResumenAvance(r.resumen_avance);
  }
  async function irProximo() {
    const r = await api('reloj.proximo_encuentro', {});
    if (!r.ok) toast(r.mensaje_ui || 'No hay proximo encuentro.');
    await refresh();
    if (r.playtest_guia) pintarPlaytestGuia(r.playtest_guia, r.playtest_guia_evento);
    if (r.playtest_diag) pintarPlaytestDiag(r.playtest_diag);
    pintarResumenAvance(r.resumen_avance);
  }
  function bindLabHoras() {
    var scope = document.querySelector('[data-debug-panel]');
    if (!scope) return;
    $$('[data-horas]', scope).forEach(function (btn) {
      if (btn._ahtHorasBound) return;
      btn._ahtHorasBound = true;
      btn.addEventListener('click', function () {
        avanzarHoras(parseInt(btn.getAttribute('data-horas'), 10));
      });
    });
  }
  bindLabHoras();
  const btnProx = $('#btn-debug-proximo');
  if (btnProx) btnProx.addEventListener('click', irProximo);
  const btnProxLab = $('#btn-proximo-lab');
  if (btnProxLab) btnProxLab.addEventListener('click', irProximo);
  const btnCopy = $('[data-diag-copy]');
  if (btnCopy) {
    btnCopy.addEventListener('click', async function () {
      const pre = $('[data-playtest-diag-log]');
      const txt = pre ? pre.textContent : '';
      try {
        await navigator.clipboard.writeText(txt);
        toast('Registro copiado.');
      } catch (e) {
        toast('No se pudo copiar. Selecciona el texto a mano.');
      }
    });
  }
  const btnClear = $('[data-diag-clear-ui]');
  if (btnClear) {
    btnClear.addEventListener('click', function () {
      playtestLogClient.entries = [];
      pintarPlaytestDiag(cacheEstado && cacheEstado.playtest_diag);
      toast('Vista de cliente limpiada (el log del servidor sigue en la partida).');
    });
  }

  document.body.addEventListener('click', function (ev) {
    const relOverlay = $('[data-ficha-rel-overlay]');
    if (relOverlay && !relOverlay.hidden) {
      if (ev.target.closest('[data-ficha-rel-close]') || ev.target === relOverlay) {
        cerrarFichaRelOverlay();
        return;
      }
      if (ev.target.closest('.velo')) {
        cerrarFichaRelOverlay();
        return;
      }
    }
    const t = ev.target.closest('[data-close], .velo');
    if (t && uiRootFrom(t)) {
      cerrarFichaRelOverlay();
      setCapa('');
      $('.play-root').removeAttribute('data-consulta');
      return;
    }
    const open = ev.target.closest('[data-open]');
    if (open && uiRootFrom(open)) {
      const name = open.getAttribute('data-open');
      cerrarMensajitosPop();
      setCapa(name);
      $('.play-root').removeAttribute('data-consulta');
      if (name === 'organizar') {
        if (!orgPresetNuevo) resetOrgForm();
        orgPresetNuevo = false;
        fillOrganizar();
      }
      if (name === 'agenda') renderAgendaPlanes();
      if (name === 'diario') $('[data-diario-tab="hoy"]').click();
      if (name === 'vecinos') renderVecinos();
      return;
    }
    const tab = ev.target.closest('[data-diario-tab]');
    if (tab) {
      $('.play-root').setAttribute('data-diario', tab.getAttribute('data-diario-tab'));
      $$('[data-diario-tab]').forEach(function (b) {
        b.classList.toggle('is-on', b === tab);
      });
      return;
    }
    const caraTok = ev.target.closest('.cara-token[data-residente]');
    if (caraTok) {
      ev.preventDefault();
      ev.stopPropagation();
      abrirFicha(caraTok.getAttribute('data-residente'));
      return;
    }
    const zona = ev.target.closest('.mapa-zona-hit[data-zona]');
    if (zona) {
      abrirConsultaZona(zona.getAttribute('data-zona'), zona);
      return;
    }
    const cx = ev.target.closest('.complejo[data-complejo]');
    if (cx) {
      abrirConsulta(cx.getAttribute('data-complejo'));
    }
  });

  const fichaRelClose = $('[data-ficha-rel-close]');
  if (fichaRelClose) fichaRelClose.addEventListener('click', cerrarFichaRelOverlay);
  const fichaRelOverlay = $('[data-ficha-rel-overlay]');
  if (fichaRelOverlay) {
    fichaRelOverlay.addEventListener('click', function (ev) {
      if (ev.target === fichaRelOverlay) cerrarFichaRelOverlay();
    });
  }

  const fichaVolver = $('[data-ficha-volver]');
  if (fichaVolver) {
    fichaVolver.addEventListener('click', function () { setCapa('vecinos'); renderVecinos(); });
  }


  const mensajitosTrig = $('[data-mensajitos-trigger]');
  if (mensajitosTrig) {
    mensajitosTrig.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      toggleMensajitosPop();
    });
  }
  const mensajitosPopCerrar = $('[data-mensajitos-cerrar]');
  if (mensajitosPopCerrar) {
    mensajitosPopCerrar.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      cerrarMensajitosPop();
    });
  }
  const mensajitosVerMas = $('[data-mensajitos-ver-mas]');
  if (mensajitosVerMas) {
    mensajitosVerMas.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      cerrarMensajitosPop();
      setCapa('buzon');
    });
  }
  document.addEventListener('click', function (ev) {
    if (!mensajitosPopAbierto) return;
    if (ev.target.closest('[data-mensajitos-pop]') || ev.target.closest('[data-mensajitos-trigger]')) return;
    cerrarMensajitosPop();
  });

  const vecBuscaInp = $('[data-vec-busca]');
  if (vecBuscaInp) {
    vecBuscaInp.addEventListener('input', function () {
      vecBuscaTxt = vecBuscaInp.value;
      renderVecinos();
    });
  }

  const resBuscaInp = $('[data-res-busca]');
  if (resBuscaInp) {
    resBuscaInp.addEventListener('input', function () {
      resBuscaTxt = resBuscaInp.value;
    });
  }

  var orgGo = $('[data-org-go]');
  var orgBusca = $('[data-org-busca]');
  if (orgBusca) {
    orgBusca.addEventListener('input', function () {
      orgBuscaTxt = orgBusca.value || '';
      pintarOrgPicker();
    });
  }
  var orgLug = $('[data-org-lugar]');
  var orgDia = $('[data-org-dia]');
  if (orgLug) orgLug.addEventListener('change', function () { refreshOrgHoras(); });
  if (orgDia) orgDia.addEventListener('change', function () { refreshOrgHoras(); });
  $$('[data-org-modo]').forEach(function (btn) {
    btn.addEventListener('click', function () { setOrgModo(btn.getAttribute('data-org-modo')); });
  });
  var finOk = $('[data-tut-fin-ok]');
  if (finOk) finOk.addEventListener('click', cerrarTutFinale);
  if (orgGo) orgGo.addEventListener('click', proponer);

  const musicaToggle = $('[data-musica-toggle]');
  actualizarControlMusica();
  if (musicaToggle) {
    musicaToggle.addEventListener('click', function () {
      cambiarMusica(!musicaActiva);
    });
  }
  iniciarMusicaFondo(true);

  window.addEventListener('resize', layout);
  layout();
  const btnNuevaMesa = $('#btn-nueva-mesa');
  if (btnNuevaMesa) btnNuevaMesa.addEventListener('click', nuevaPartidaLimpia);
  const tutSkip = $('[data-tut-skip]');
  if (tutSkip) tutSkip.addEventListener('click', function () { cerrarTutIntro(true, false); });
  const tutSig = $('[data-tut-siguiente]');
  if (tutSig) tutSig.addEventListener('click', function () {
    const pasosN2 = tutPasosActuales();
    if (tutIntroIdx >= pasosN2.length - 1) cerrarTutIntro(true, true);
    else { tutIntroIdx++; pintarTutIntro(); }
  });
  const tutAtras = $('[data-tut-atras]');
  if (tutAtras) tutAtras.addEventListener('click', function () {
    if (tutIntroIdx > 0) { tutIntroIdx--; pintarTutIntro(); }
  });
  const tutReopen = $('[data-tut-reopen]');
  if (tutReopen) tutReopen.addEventListener('click', function () {
    if (!tieneTutorialV3()) return;
    abrirTutIntro(true);
  });

  initMapaCanonico().then(function () {
    return ensurePartida().then(function () {
      return refresh().then(function () { quizaMostrarTutIntro(); });
    });
  });
})();
