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
  function horaLocalCreacion() {
    const d = new Date();
    const pad = function (n) { return String(n).padStart(2, '0'); };
    return {
      fecha: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()),
      hora: d.getHours()
    };
  }
  function configNueva(forceFreshSeed) {
    const c = qs.get('config');
    if (c) {
      const o = { config_id: c, hora_local: horaLocalCreacion() };
      if (qs.get('seed')) {
        o.seed = qs.get('seed');
      } else if (forceFreshSeed) {
        o.seed = 'ui-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
      }
      return o;
    }
    const o = Object.assign({}, CONFIG_JUEGO, { hora_local: horaLocalCreacion() });
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

  function pausarAudioPorOculto() {
    if (!document.hidden && document.visibilityState !== 'hidden') {
      if (musicaActiva) iniciarMusicaFondo(false);
      return;
    }
    retirarEsperaPrimerGesto();
    try { musicaFondo.pause(); } catch (e) {}
    if (window.AhtAudioFeedback && typeof window.AhtAudioFeedback.pauseAll === 'function') {
      window.AhtAudioFeedback.pauseAll();
    }
  }
  document.addEventListener('visibilitychange', pausarAudioPorOculto);
  window.addEventListener('pagehide', pausarAudioPorOculto);

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
  let cacheDiario = null;
  let vidaCorazonPctPrev = null;
  let vidaCorazonReady = false;
  const ORG_MAX_VECINOS = 3;
  let org = { tipo: '', sel: [], lugar: '', dia: null, hora: 17 };
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
  function inicioViewRoot(view) {
    if (view === 'mobile') return document.querySelector('.inicio-mobile');
    if (view === 'desktop') return document.querySelector('.inicio-desktop');
    return null;
  }
  function inicioAll(sel) {
    return Array.prototype.slice.call(document.querySelectorAll('.inicio-mobile ' + sel + ', .inicio-desktop ' + sel));
  }
  function inicioBlocks(sel) {
    return Array.prototype.slice.call(document.querySelectorAll('.inicio-mobile ' + sel + ', .inicio-desktop ' + sel));
  }
  function setAllText(sel, text) {
    inicioAll(sel).forEach(function (el) { el.textContent = text; });
  }
  function setAllHtml(sel, html) {
    inicioAll(sel).forEach(function (el) { el.innerHTML = html; });
  }
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
        ptToggle.textContent = opening ? '?? DEBUG ?' : '?? DEBUG';
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
        return e.ts + ' | API_ERROR\n' + e.method + ' ' + e.action + ' ? HTTP ' + e.status
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
  var TUT_ASSET_BASE = 'assets/play-v3/tutorial/';
  function tutAssetUrl(name) {
    var link = document.querySelector('link[href*="play-v3-tutorial-ds.css"]');
    var v = '';
    if (link && link.href) {
      var m = link.href.match(/[?&]v=([^&]+)/);
      if (m) v = '?v=' + m[1];
    }
    return TUT_ASSET_BASE + name + v;
  }
  var TUT_CARD_ICONS = ['icon-observa.png', 'icon-propon.png', 'icon-mira.png'];
  var TUT_ROW_ICONS = {
    'MAPA': 'icon-mapa.png',
    'VECINOS': 'icon-vecinos.png',
    'MENSAJITOS': 'icon-mensajitos.png',
    'NUEVO PLAN': 'icon-plan.png'
  };
  var TUT_CARA_ICONS = ['\u2661', '\u2605', '?'];
  var TUT_POL_MARKS = ['\u2605', '\u2661', '\u273F'];
  var TUT_POL_MODS = ['tut-pol--rose', 'tut-pol--sky', 'tut-pol--leaf'];

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
    var pasoNum = tutIntroIdx + 1;
    var papel = $('[data-tut-papel]') || box.querySelector('.tut-papel');
    if (papel) {
      papel.classList.remove('tut-anim');
      papel.className = 'tut-papel tut-paso-' + pasoNum;
      if (paso.bloques_estilo === 'inline') papel.classList.add('tut-paso-vecinos');
      if (paso.tareas) papel.classList.add('tut-paso-misiones');
    }
    var heroEl = $('[data-tut-hero]');
    if (heroEl) {
      if (pasoNum === 1) {
        heroEl.hidden = false;
        heroEl.innerHTML = '<img class="tut-hero-img" src="' + esc(tutAssetUrl('illus-pueblo.png')) + '" alt=""/>';
      } else if (pasoNum === 2) {
        heroEl.hidden = false;
        heroEl.innerHTML = '<img class="tut-hero-img" src="' + esc(tutAssetUrl('icon-vecinos.png')) + '" alt=""/>';
      } else { heroEl.hidden = true; heroEl.innerHTML = ''; }
    }
    var titEl = $('[data-tut-tit]');
    if (titEl) {
      titEl.innerHTML = '<span class="tut-tit-spark" aria-hidden="true"></span><span class="tut-tit-txt">' + esc(paso.tit || '') + '</span><span class="tut-tit-spark tut-tit-spark--r" aria-hidden="true"></span>';
    }
    var oldTitDeco = papel ? papel.querySelector('.tut-tit-deco') : null;
    if (oldTitDeco) oldTitDeco.remove();
    if (pasoNum === 1 && titEl && titEl.parentNode) {
      var titDeco = document.createElement('p');
      titDeco.className = 'tut-tit-deco';
      titDeco.setAttribute('aria-hidden', 'true');
      titDeco.textContent = '\u2661';
      titEl.parentNode.insertBefore(titDeco, titEl.nextSibling);
    }
    var introEl = $('[data-tut-intro-line]');
    if (introEl) { introEl.textContent = paso.intro || ''; introEl.hidden = !paso.intro; }
    var introExtra = $('[data-tut-intro-extra]');
    if (introExtra) { introExtra.textContent = paso.intro_extra || ''; introExtra.hidden = !paso.intro_extra; }
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
          sp.innerHTML = c.token_url ? '<img src="' + esc(c.token_url) + '" alt=""/>' : '<span class="cara-ini">' + esc((c.nombre || '?')[0]) + '</span>';
          wrap.appendChild(sp);
          if (c.nombre) {
            var nmRow = document.createElement('span');
            nmRow.className = 'tut-cara-nombre';
            var nm = document.createElement('span');
            nm.className = 'tut-cara-nombre-txt';
            nm.textContent = c.nombre;
            var ic = document.createElement('span');
            ic.className = 'tut-cara-ico tut-cara-ico--' + (i % 3);
            ic.textContent = TUT_CARA_ICONS[i % TUT_CARA_ICONS.length];
            ic.setAttribute('aria-hidden', 'true');
            nmRow.appendChild(nm); nmRow.appendChild(ic); wrap.appendChild(nmRow);
          }
          carasBox.appendChild(wrap);
        });
      } else { carasBox.hidden = true; }
    }
    var prefijoEl = $('[data-tut-bloques-pref]');
    if (prefijoEl) { prefijoEl.textContent = paso.bloques_prefijo || ''; prefijoEl.hidden = !paso.bloques_prefijo; }
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
          if (pasoNum === 1 && TUT_CARD_ICONS[i]) {
            sym.innerHTML = '<img src="' + esc(tutAssetUrl(TUT_CARD_ICONS[i])) + '" alt=""/>';
            div.classList.add('tut-bloque--card');
          } else if (pasoNum === 3 && b.tit && TUT_ROW_ICONS[b.tit]) {
            sym.innerHTML = '<img src="' + esc(tutAssetUrl(TUT_ROW_ICONS[b.tit])) + '" alt=""/>';
            div.classList.add('tut-bloque--row');
          } else { sym.textContent = b.simbolo || ''; }
          div.appendChild(sym);
          var body = document.createElement('span');
          body.className = 'tut-bloque-body';
          if (b.tit) { var tit = document.createElement('strong'); tit.className = 'tut-bloque-tit'; tit.textContent = b.tit; body.appendChild(tit); }
          if (b.txt) { var txt = document.createElement('span'); txt.className = 'tut-bloque-txt'; txt.textContent = b.txt; body.appendChild(txt); }
          div.appendChild(body);
          bloquesBox.appendChild(div);
        });
      } else { bloquesBox.hidden = true; }
    }
    var tareasBox = $('[data-tut-tareas]');
    if (tareasBox) {
      tareasBox.innerHTML = '';
      if (paso.tareas) {
        tareasBox.hidden = false;
        for (var t = 0; t < 3; t++) {
          var card = document.createElement('div');
          card.className = 'tut-polaroid tut-anim-item tut-anim-pop ' + TUT_POL_MODS[t];
          card.style.setProperty('--tut-delay', String(t * 80) + 'ms');
          var attach = document.createElement('span');
          attach.className = t === 1 ? 'tut-pol-tape' : 'tut-pol-pin';
          attach.setAttribute('aria-hidden', 'true');
          var q = document.createElement('span'); q.className = 'tut-pol-q'; q.textContent = '?';
          var mk = document.createElement('span'); mk.className = 'tut-pol-mark'; mk.textContent = TUT_POL_MARKS[t]; mk.setAttribute('aria-hidden', 'true');
          card.appendChild(attach); card.appendChild(q); card.appendChild(mk);
          tareasBox.appendChild(card);
        }
      } else { tareasBox.hidden = true; }
    }
    var cierreEl = $('[data-tut-cierre]');
    if (cierreEl) { cierreEl.textContent = paso.cierre || ''; cierreEl.hidden = !paso.cierre; }
    const dots = $('[data-tut-pasos]');
    dots.innerHTML = '';
    tutPasosActuales().forEach(function (_, i) { const s = document.createElement('span'); if (i <= tutIntroIdx) s.className = 'is-on'; dots.appendChild(s); });
    const btnAtras = $('[data-tut-atras]');
    const btnSig = $('[data-tut-siguiente]');
    if (btnAtras) btnAtras.hidden = tutIntroIdx === 0;
    const pasosN = tutPasosActuales();
    const ult = pasosN[tutIntroIdx];
    var esFinal = tutIntroIdx >= pasosN.length - 1;
    if (btnSig) {
      btnSig.textContent = esFinal ? (ult && ult.boton_final ? ult.boton_final : 'A ver qu\u00e9 se cuece') : 'Siguiente';
      btnSig.classList.toggle('tut-cta-final', esFinal);
    }
    if (papel) { requestAnimationFrame(function () { papel.classList.add('tut-anim'); }); }
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
    var box = $('aside[data-tut-finale]');
    if (!box) return;
    var finTit = tut.finale.tit || '';
    $('[data-tut-fin-tit]').textContent = finTit.indexOf('. ') > 0 ? finTit.replace('. ', '.\n') : finTit;
    var texto = tut.finale.txt || '';
    var partes = texto.split(/\n\n+/);
    var leadEl = $('[data-tut-fin-lead]');
    var restEl = $('[data-tut-fin-rest]');
    var textoEl = $('[data-tut-fin-texto]');
    if (leadEl) leadEl.textContent = partes[0] || '';
    if (restEl) restEl.textContent = partes.slice(1).join('\n\n');
    if (textoEl) textoEl.textContent = texto;
    var heroFin = $('[data-tut-fin-hero]');
    if (heroFin) heroFin.innerHTML = '<img class="tut-fin-hero-img" src="' + esc(tutAssetUrl('illus-finale.png')) + '" alt=""/>';
    var btn = $('[data-tut-fin-ok]');
    if (btn) btn.textContent = tut.finale.boton || 'Que empiece el tema';
    box.hidden = false;
    document.body.setAttribute('data-tut-finale', '1');
    syncScrollLock();
  }
  async function cerrarTutFinale() {
    /* aside[...]: nunca resuelve a <body>, hidden solo puede aplicar al modal. */
    var box = $('aside[data-tut-finale]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-finale');
    syncScrollLock();
    await api('partida.tutorial_finale', {});
    await refresh();
    setCapa('');
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

  let consultaNav = null;
  let uiHistDepth = 0;
  let uiHistSilent = false;

  function uiHistPush() {
    if (uiHistSilent) return;
    uiHistDepth++;
    try { history.pushState({ ahtUi: uiHistDepth }, ''); } catch (e) {}
  }

  function uiHistReset() {
    uiHistDepth = 0;
    consultaNav = null;
  }

  function uiHistBack() {
    const root = $('.play-root');
    if (!root) return false;
    const consulta = root.getAttribute('data-consulta') || '';
    const capa = root.getAttribute('data-capa') || '';
    if (consulta === 'quien' && consultaNav && consultaNav.fromSel) {
      uiHistSilent = true;
      if (consultaNav.tipo === 'zona') abrirConsultaZona(consultaNav.zonaId, consultaNav.zonaBtn, true);
      else abrirConsulta(consultaNav.complejoId, true);
      uiHistSilent = false;
      return true;
    }
    if (consulta) {
      root.removeAttribute('data-consulta');
      consultaNav = null;
      actualizarNotaAtras();
      return true;
    }
    if (capa) {
      setCapa('');
      return true;
    }
    return false;
  }

  function cerrarUiCompleto() {
    uiHistSilent = true;
    cerrarFichaRelOverlay();
    setCapa('');
    if ($('.play-root')) $('.play-root').removeAttribute('data-consulta');
    uiHistReset();
    actualizarNotaAtras();
    if (uiHistDepth > 0) {
      const n = uiHistDepth;
      uiHistDepth = 0;
      try { history.go(-n); } catch (e) {}
    }
    uiHistSilent = false;
  }


  function syncScrollLock() {
    var body = document.body;
    if (!body || !body.classList.contains('play-v3')) return;
    var root = $('.play-root');
    var open = !!(root && (root.getAttribute('data-capa') || root.getAttribute('data-consulta')))
      || body.getAttribute('data-tut-finale') === '1';
    if (open) {
      if (!body.classList.contains('play-v3--scroll-lock')) {
        var y = window.scrollY || window.pageYOffset || 0;
        var sbw = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
        body.dataset.scrollLockY = String(y);
        body.dataset.scrollLockPad = String(sbw);
        body.style.top = '-' + y + 'px';
        if (sbw > 0) body.style.paddingRight = sbw + 'px';
        body.classList.add('play-v3--scroll-lock');
      }
    } else if (body.classList.contains('play-v3--scroll-lock')) {
      var restore = parseInt(body.dataset.scrollLockY || '0', 10) || 0;
      body.classList.remove('play-v3--scroll-lock');
      body.style.top = '';
      body.style.paddingRight = '';
      delete body.dataset.scrollLockY;
      delete body.dataset.scrollLockPad;
      window.scrollTo(0, restore);
    }
  }

  function renderVidaPuebloModal() {
    const vida = cacheEstado && cacheEstado.vida_pueblo ? cacheEstado.vida_pueblo : null;
    const valor = vida && typeof vida.corazon_pct === 'number' ? Math.round(vida.corazon_pct) : 0;
    const valEl = $('[data-vida-modal-valor]');
    if (valEl) valEl.textContent = valor + ' / 100';
    const estEl = $('[data-vida-modal-estado]');
    if (estEl) {
      let hint = '';
      if (vida && vida.critico) hint = 'La cosa se está poniendo fea.';
      else if (valor >= 80) hint = 'Por ahora el pueblo respira.';
      else if (valor <= 39) hint = 'Aquí pasa algo, y no es bueno.';
      estEl.textContent = hint;
      estEl.hidden = !hint;
      estEl.className = 'vida-estado-pista mini' + (vida && vida.critico ? ' vida-estado--critica' : (valor >= 80 ? ' vida-estado--alta' : (valor <= 39 ? ' vida-estado--baja' : '')));
    }
    const modal = $('.capa-vida-pueblo');
    if (modal) {
      modal.classList.toggle('vida-modal--critica', !!(vida && vida.critico));
      modal.classList.toggle('vida-modal--alta', valor >= 80 && !(vida && vida.critico));
      modal.classList.toggle('vida-modal--baja', valor <= 39 && !(vida && vida.critico));
    }
  }

  function setCapa(name) {
    const root = $('.play-root');
    const prev = (root && root.getAttribute('data-capa')) || '';
    if (!name) root.removeAttribute('data-capa');
    else root.setAttribute('data-capa', name);
    if (name === 'vida_pueblo') renderVidaPuebloModal();
    if (name === 'inventario') renderInventario();
    if (name && name !== prev && !uiHistSilent) uiHistPush();
    $$('.dock button').forEach(function (b) {
      const open = b.getAttribute('data-open');
      b.classList.toggle('is-on', name ? open === name : !open);
    });
    syncScrollLock();
  }

// --- Inventario / Regalos (F1 + F2) ---
  let invSelObjeto = null;
  let invSelVecino = null;
  let invFichaRid = null;
  let invFichaNombre = '';

  function abrirRegalosDesdeFicha(id, nombre) {
    invFichaRid = id || null;
    invFichaNombre = String(nombre || '');
    setCapa('inventario');
  }

  async function renderInventario() {
    const root = $('.play-root');
    if (!root) return;
    const lista = $('[data-inv-lista]', root);
    const caja = $('[data-inv-regalo]', root);
    const feedback = $('[data-inv-feedback]', root);
    if (!lista || !caja) return;
    caja.hidden = true;
    if (feedback) { feedback.hidden = true; feedback.textContent = ''; }
    invSelObjeto = null;
    invSelVecino = null;
    const params = invFichaRid ? { residente_id: invFichaRid } : {};
    const r = await api('inventario.listar', params, 'GET');
    const items = (r && r.ok && Array.isArray(r.inventario)) ? r.inventario : [];
    const sub = $('[data-inv-sub]', root);
    if (sub) {
      sub.textContent = invFichaRid && r && r.residente_nombre
        ? 'Elige un detalle para ' + r.residente_nombre + '.'
        : 'Detalles guardados para regalar a los vecinos.';
    }
    if (!r || !r.ok) {
      lista.innerHTML = '<p class="inv-vacio">' + ((r && r.mensaje_ui) || 'No se pudo abrir el inventario.') + '</p>';
      return;
    }
    if (!items.length) {
      lista.innerHTML = '<p class="inv-vacio">De momento no guardas ning&uacute;n detalle. Llegar&aacute;n.</p>';
      return;
    }
    lista.innerHTML = '';
    items.forEach(function (it) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'inv-objeto' + (it.hint === 'no_le_gusta' ? ' inv-hint-mal' : (it.hint ? ' inv-hint-bien' : ''));
      btn.setAttribute('data-objeto', it.id);
      btn.innerHTML =
        '<img class="inv-objeto-img" src="' + it.url + '" alt="" loading="lazy"/>' +
        '<span class="inv-objeto-nombre">' + it.nombre + '</span>' +
        '<span class="inv-objeto-cant">x' + it.cantidad + '</span>' +
        (it.hint_texto ? '<span class="inv-hint">' + esc(it.hint_texto) + '</span>' : '');
      btn.addEventListener('click', function () { elegirObjetoRegalo(it, btn); });
      lista.appendChild(btn);
    });
  }

  function elegirObjetoRegalo(item, btn) {
    const root = $('.play-root');
    if (!root) return;
    invSelObjeto = item;
    invSelVecino = null;
    $('.inv-objeto', root).forEach(function (b) { b.classList.remove('is-sel'); });
    btn.classList.add('is-sel');
    const caja = $('[data-inv-regalo]', root);
    const nombre = $('[data-inv-objeto-nombre]', root);
    const cont = $('[data-inv-vecinos]', root);
    const btnEntregar = $('[data-inv-entregar]', root);
    const feedback = $('[data-inv-feedback]', root);
    if (feedback) { feedback.hidden = true; feedback.textContent = ''; }
    if (!caja || !cont || !btnEntregar) return;
    if (nombre) nombre.textContent = item.nombre;
    btnEntregar.disabled = true;
    if (invFichaRid) {
      // Modo ficha: vecino preseleccionado, sin paso de chips.
      invSelVecino = { id: invFichaRid, nombre: invFichaNombre || invFichaRid };
      cont.innerHTML = '<p class="inv-vacio">Para ' + esc(invFichaNombre || 'este vecino') + '.</p>';
      btnEntregar.disabled = false;
      caja.hidden = false;
      return;
    }
    const vecinos = [];
    const vistos = cacheInsp && cacheInsp.residentes ? cacheInsp.residentes : {};
    Object.keys(vistos).forEach(function (id) {
      const info = vistos[id] || {};
      const nom = info.identidad_publica && info.identidad_publica.nombre;
      if (nom) vecinos.push({ id: id, nombre: nom });
    });
    vecinos.sort(function (a, b) { return a.nombre.localeCompare(b.nombre, 'es'); });
    cont.innerHTML = '';
    if (!vecinos.length) {
      cont.innerHTML = '<p class="inv-vacio">No hay vecinos disponibles.</p>';
    }
    vecinos.forEach(function (v) {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'inv-vecino';
      chip.textContent = v.nombre;
      chip.addEventListener('click', function () {
        invSelVecino = v;
        $('.inv-vecino', root).forEach(function (c) { c.classList.remove('is-sel'); });
        chip.classList.add('is-sel');
        btnEntregar.disabled = false;
        pintarHintChip(item, v);
      });
      cont.appendChild(chip);
    });
    caja.hidden = false;
  }

  // F3: hint al elegir vecino desde el tile (mismo endpoint, cero conocimiento magico).
  async function pintarHintChip(item, vecino) {
    const root = $('.play-root');
    if (!root || !item || !vecino) return;
    const caja = $('[data-inv-regalo]', root);
    if (!caja) return;
    let cajaHint = caja.querySelector('[data-inv-chip-hint]');
    if (!cajaHint) {
      cajaHint = document.createElement('p');
      cajaHint.className = 'inv-hint inv-chip-hint';
      cajaHint.setAttribute('data-inv-chip-hint', '');
      const titulo = caja.querySelector('.inv-regalo-titulo');
      if (titulo && titulo.nextSibling) caja.parentNode.insertBefore(cajaHint, titulo.nextSibling);
      else caja.insertBefore(cajaHint, caja.firstChild);
    }
    cajaHint.textContent = '';
    cajaHint.hidden = true;
    const r = await api('inventario.listar', { residente_id: vecino.id }, 'GET');
    if (!r || !r.ok || invSelVecino !== vecino || !invSelObjeto || invSelObjeto.id !== item.id) return;
    let hint = null;
    (Array.isArray(r.inventario) ? r.inventario : []).forEach(function (it) {
      if (it.id === item.id && it.hint_texto) hint = it.hint_texto;
    });
    cajaHint.textContent = hint || '';
    cajaHint.hidden = !hint;
  }

  async function entregarRegalo() {
    const root = $('.play-root');
    if (!root || !invSelObjeto || !invSelVecino) return;
    const btnEntregar = $('[data-inv-entregar]', root);
    const feedback = $('[data-inv-feedback]', root);
    if (btnEntregar) btnEntregar.disabled = true;
    const r = await api('regalo.entregar', { objeto_id: invSelObjeto.id, residente_id: invSelVecino.id });
    const texto = (r && (r.texto || r.mensaje_ui)) || '';
    if (r && r.ok) {
      toast(texto || 'Regalo entregado.');
      await renderInventario();
      if (feedback) {
        let extra = '';
        if (Array.isArray(r.descubrimientos)) {
          r.descubrimientos.forEach(function (d) {
            if (d && d.texto) extra += '<span class="inv-descubrimiento">' + esc(d.texto) + '</span> ';
          });
        }
        feedback.hidden = false;
        feedback.innerHTML = esc(texto) + (extra ? ' ' + extra : '');
        feedback.classList.toggle('is-mal', r.reaccion === 'no_le_gusta');
        feedback.classList.remove('is-error');
      }
    } else {
      if (feedback) {
        feedback.hidden = false;
        feedback.textContent = texto || 'No se pudo entregar el regalo.';
        feedback.classList.add('is-error');
        feedback.classList.remove('is-mal');
      }
      toast(texto || 'No se pudo entregar el regalo.');
      if (r && (r.error === 'regalo_sin_unidades' || r.error === 'regalo_objeto_desconocido')) {
        await renderInventario();
      } else if (btnEntregar) {
        btnEntregar.disabled = false;
      }
    }
  }
  // --- fin Inventario / Regalos (F1) ---

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

  function esIdInterno(s) {
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


  function mensajitoTieneAccionReal(m) {
    if (!m || typeof m !== 'object') return false;
    if (m.requiere_decision === true) return true;
    if (Array.isArray(m.acciones_ui) && m.acciones_ui.length > 0) return true;
    if (Array.isArray(m.selector_opciones) && m.selector_opciones.length &&
        m.selector_estado === 'pendiente' &&
        (m.estado_pueblo || 'pendiente') === 'pendiente' &&
        (m.estado || '') === 'pendiente') return true;
    if (m.tipo === 'candidato_llegada' &&
        (m.estado || '') === 'pendiente' &&
        (m.estado_decision || '') !== 'resuelto') return true;
    if (m.preset_organizar && typeof m.preset_organizar === 'object' &&
        (m.estado_pueblo || 'pendiente') === 'pendiente' &&
        (m.estado || '') === 'pendiente') return true;
    return false;
  }

  function mensajitoDestinoFicha(m) {
    if (!m || typeof m !== 'object') return false;
    const soloLectura = ['respuesta_plan', 'peticion_resultado', 'marcha_publica'];
    if (soloLectura.indexOf(String(m.tipo || '')) >= 0) return false;
    const rid = remitenteIdDe(m);
    return rid && !m.candidato_catalog_id;
  }

  function mensajitoRequiereAccion(m) {
    return mensajitoTieneAccionReal(m);
  }

  async function marcarMensajitoLeido(m) {
    if (!m || !m.id || (m.estado || '') !== 'pendiente') return null;
    const lr = await api('buzon.leer', { mensaje_id: m.id });
    return lr.tutorial || null;
  }


  function residenteIdPorNombre(nombre) {
    if (!nombre || typeof nombre !== 'string') return null;
    const clave = nombre.trim().toLowerCase();
    if (!clave) return null;
    if (cacheInsp && cacheInsp.residentes) {
      for (const rid in cacheInsp.residentes) {
        if (!Object.prototype.hasOwnProperty.call(cacheInsp.residentes, rid)) continue;
        const r = cacheInsp.residentes[rid];
        const nm = (r.identidad_publica && r.identidad_publica.nombre) || '';
        if (String(nm).trim().toLowerCase() === clave) return rid;
      }
    }
    return null;
  }

  function remitenteIdDe(m) {
    if (!m || typeof m !== 'object') return null;
    const direct = m.de_persona || m.de;
    if (direct) {
      if (!esIdInterno(direct)) {
        const porNombre = residenteIdPorNombre(direct);
        if (porNombre) return porNombre;
      }
      return direct;
    }
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

  function mensajitoHora(m) {
    const ts = m && m.ts_juego;
    if (ts && typeof ts === 'object' && ts.hora != null) return Number(ts.hora) || 0;
    return 0;
  }

  function mensajitoCuandoLabel(m) {
    const h = mensajitoHora(m);
    const hora = h ? String(h).padStart(2, '0') + ':00' : '';
    const fc = String((m && m.fecha_corta) || '').trim();
    const diaMsg = (m && m.ts_juego && m.ts_juego.dia != null) ? Number(m.ts_juego.dia) : Number((m && m.dia) || 0);
    const diaHoy = (cacheEstado && cacheEstado.reloj) ? Number(cacheEstado.reloj.dia_pueblo) : 0;
    let diaLbl = '';
    if (diaHoy && diaMsg === diaHoy) diaLbl = 'Hoy';
    else if (diaHoy && diaHoy > 1 && diaMsg === diaHoy - 1) diaLbl = 'Ayer';
    else if (fc) diaLbl = fc;
    else if (diaMsg) diaLbl = 'Día ' + diaMsg;
    if (diaLbl && hora) return diaLbl + ' · ' + hora;
    return diaLbl || hora || fc || '';
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
    if (!m || typeof m !== 'object') return true;
    if (typeof m.leido === 'boolean') return m.leido;
    return (m.estado || '') !== 'pendiente';
  }

  function htmlAccionesMensajito(m) {
    if (Array.isArray(m.selector_opciones) && m.selector_opciones.length &&
        m.selector_estado === 'pendiente' &&
        (m.estado_pueblo || 'pendiente') === 'pendiente' && (m.estado || '') === 'pendiente') {
      const optsHtml = m.selector_opciones.map(function (o) {
        if (!o || !o.personaje_id) return '';
        const pista = o.pista ? '<span class="msg-opcion-pista">' + esc(o.pista) + '</span>' : '';
        return '<button type="button" class="carta-cta carta-cta--suave msg-opcion" data-elegir-persona="' + esc(o.personaje_id) + '">' +
          '<span class="msg-opcion-nom">' + esc(o.nombre || '') + '</span>' + pista + '</button>';
      }).join('');
      return '<div class="acciones-msg msg-opciones">' +
        '<span class="msg-opciones-tit">' + esc(m.selector_titulo || '\u00bfA qui\u00e9n presentas?') + '</span>' +
        optsHtml + '</div>';
    }
    const acciones = Array.isArray(m.acciones_ui) ? m.acciones_ui : [];
    if (!acciones.length) return '';
    return '<div class="acciones-msg">' + acciones.map(function (a) {
      const suave = (a.estilo || '') === 'suave';
      const cls = suave ? 'carta-cta carta-cta--suave' : 'carta-cta carta-cta--abrir';
      return '<button type="button" class="' + cls + '" data-accion-id="' + esc(a.id || '') + '">' +
        esc(a.etiqueta || a.id || '') + '</button>';
    }).join('') + '</div>';
  }

  async function resolverAccionMensajito(m, accionId, extra) {
    if (!m || !m.id || !accionId) return false;
    const payload = { mensaje_id: m.id, accion: accionId };
    if (extra && typeof extra === 'object') Object.assign(payload, extra);
    const r = await api('buzon.resolver', payload);
    if (!r.ok) {
      toast(r.mensaje_ui || r.error || 'No se pudo completar la acci\u00f3n.');
      return false;
    }
    if (r.mensaje_ui) toast(r.mensaje_ui);
    return true;
  }

  function wireAccionesMensajito(art, m) {
    art.querySelectorAll('[data-accion-id]').forEach(function (btn) {
      btn.addEventListener('click', async function (ev) {
        ev.stopPropagation();
        const accionId = btn.getAttribute('data-accion-id');
        if (!accionId || btn.disabled) return;
        btn.disabled = true;
        const ok = await resolverAccionMensajito(m, accionId);
        if (ok) await refresh();
        else btn.disabled = false;
      });
    });
    art.querySelectorAll('[data-elegir-persona]').forEach(function (btn) {
      btn.addEventListener('click', async function (ev) {
        ev.stopPropagation();
        if (btn.disabled) return;
        btn.disabled = true;
        const ok = await resolverAccionMensajito(m, 'elegir_persona', {
          personaje_id: btn.getAttribute('data-elegir-persona')
        });
        if (ok) await refresh();
        else btn.disabled = false;
      });
    });
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
      abrirOrganizarConPreset({ modo: 'pareja', a: params.a, b: params.b });
      return;
    }
    if (acc === 'organizar_solo') {
      abrirOrganizarConPreset({ modo: 'solo', a: params.a, lugar: params.lugar });
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
  /* Planes EN CURSO AHORA (fuente canonica unica desktop + movil):
     - preferencia: estado.encuentros_en_curso (coleccion 0..N del servidor,
       ResumenDia::encuentrosEnCurso, mismas vistas con intervencion);
     - fallback: partida.encuentros por ventana de reloj + encuentro_en_curso;
     - el encuentro_en_curso del motor siempre entra si se colo.
     Futuros, terminados, cancelados y rechazados quedan fuera por construccion. */
  function encuentrosEnCursoAhora(partida, estado) {
    var cur = estado && estado.encuentro_en_curso;
    var coleccion = estado && Array.isArray(estado.encuentros_en_curso) ? estado.encuentros_en_curso : null;
    var lista = coleccion
      ? coleccion.filter(function (e) { return e && e.id; })
      : ((partida && partida.encuentros) || []).filter(function (e) {
          if (!e) return false;
          if (cur && cur.id === e.id) return true;
          return encuentroOcupaAhora(e, estado);
        });
    if (cur && cur.id && !lista.some(function (e) { return e.id === cur.id; })) {
      lista = lista.concat([cur]);
    }
    return lista.slice().sort(function (a, b) {
      var d = relojAbs(a.dia, horaEnc(a)) - relojAbs(b.dia, horaEnc(b));
      if (d !== 0) return d;
      return String(a.id).localeCompare(String(b.id));
    });
  }
  /* Seleccion estable del encuentro en curso mostrado en la polaroid.
     Navegar NO ejecuta acciones ni llama a la API: solo re-renderiza. */
  var cursoSelId = null;
  function moverCursoSeleccion(delta) {
    var lista = encuentrosEnCursoAhora(cacheInsp, cacheEstado);
    if (!lista.length) { cursoSelId = null; renderShellPanels(cacheEstado, cacheBuzon, cacheDiario); return; }
    var pos = -1;
    for (var i = 0; i < lista.length; i++) {
      if (String(lista[i].id) === String(cursoSelId)) { pos = i; break; }
    }
    pos = ((pos < 0 ? 0 : pos) + delta + lista.length) % lista.length;
    cursoSelId = String(lista[pos].id);
    renderShellPanels(cacheEstado, cacheBuzon, cacheDiario);
  }
  function htmlProximoPlan(enc, estado) {
    const ids = enc.participantes || [];
    const enCurso = planEsEnCurso(enc, estado);
    return '<div class="prox-faces' + (enCurso ? ' prox-faces--en-curso' : '') + '">' + carasPlanHtml(ids) + '</div>' +
      '<p class="prox-nombres">' + esc(ids.map(function (id) { return nombreDe(id); }).join(' · ')) + '</p>' +
      '<p class="prox-meta' + (enCurso ? ' prox-meta--en-curso' : '') + '"><span class="prox-meta-ico" aria-hidden="true"></span>' +
      esc(formatPlanMeta(enc, estado)) + '</p>' +
      htmlIntervencionEncuentro(enc, estado);
  }
  function intervencionVistaDe(enc, estado) {
    if (!enc) return null;
    if (enc.intervencion) return enc.intervencion;
    var cur = estado && estado.encuentro_en_curso;
    if (cur && cur.id === enc.id && cur.intervencion) return cur.intervencion;
    var col = estado && Array.isArray(estado.encuentros_en_curso) ? estado.encuentros_en_curso : [];
    for (var i = 0; i < col.length; i++) {
      var e = col[i];
      if (e && e.id === enc.id && e.intervencion) return e.intervencion;
    }
    return null;
  }
  function caraIntervencionHtml(id) {
    var rid = String(id || '');
    if (!rid) return '<span class="enc-int-pers-cara enc-int-pers-cara--ini">?</span>';
    return '<span class="enc-int-pers-cara enc-int-pers-cara--ini">' + esc((nombreDe(rid)[0] || '?')) + '</span>';
  }
  function hobbyVisibleParaObjetivo(hobbyResidenteId, objetivoId) {
    if (!objetivoId) return true;
    return !hobbyResidenteId || hobbyResidenteId === objetivoId;
  }
  function textoFeedbackIntervencion(iv) {
    if (!iv || !iv.ultimo) return '';
    var t = iv.ultimo.texto || '';
    var obj = iv.ultimo.objetivo;
    if (!t) return '';
    if (!obj) return t;
    var nombre = obj ? nombreDe(obj) : '';
    if (iv.ultimo.tono === 'bien') {
      return nombre + ' \u00a1recibe la idea! ' + t;
    }
    if (iv.ultimo.tono === 'mal') {
      return nombre + ' no conecta: ' + t;
    }
    return nombre + ': ' + t;
  }
  function htmlIntervencionEncuentro(enc, estado) {
    if (!planEsEnCurso(enc, estado)) return '';
    var iv = intervencionVistaDe(enc, estado);
    if (!iv) return '';
    if (iv.usada && iv.ultimo && iv.ultimo.texto) {
      var tono = iv.ultimo.tono || 'neutral';
      var txt = textoFeedbackIntervencion(iv);
      return '<div class="enc-int-result"><p class="enc-int-result-txt enc-int-result-txt--' + esc(tono) + '">' + esc(txt) + '</p></div>';
    }
    if (!iv.disponible || !iv.acciones || !iv.acciones.length) return '';
    var ids = enc.participantes || [];
    var html = '<div class="enc-int" data-enc-int data-enc-id="' + esc(enc.id || '') + '">' +
      '<div class="enc-int-step" data-enc-int-paso="persona">' +
      '<p class="enc-int-kicker">\u00bfEn qui\u00E9n quieres meterte?</p>' +
      '<div class="enc-int-personas">';
    ids.forEach(function (rid) {
      html += '<button type="button" class="enc-int-persona" data-enc-int-persona="' + esc(rid) + '">' +
        caraIntervencionHtml(rid) + '<span class="enc-int-pers-nombre">' + esc(nombreDe(rid)) + '</span></button>';
    });
    html += '</div></div>';
    html += '<div class="enc-int-step" data-enc-int-paso="accion" hidden>' +
      '<button type="button" class="enc-int-volver" data-enc-int-volver>\u2039 Volver</button>' +
      '<p class="enc-int-kicker">\u00bfQu\u00E9 quieres meterle en la cabeza?</p>' +
      '<div class="enc-int-btns">';
    var temas = null;
    iv.acciones.forEach(function (a) {
      if (!a.disponible) return;
      if (a.id === 'hobby') {
        if (a.hobbies && a.hobbies.length) temas = a.hobbies;
        return;
      }
      html += '<button type="button" class="enc-int-btn" data-enc-int-accion="' + esc(a.id) + '">' + esc(a.etiqueta) + '</button>';
    });
    html += '</div>';
    if (temas && temas.length) {
      html += '<div class="enc-int-temas">' +
        '<button type="button" class="enc-int-btn enc-int-btn--temas" data-temas-toggle aria-haspopup="true" aria-expanded="false">\ud83d\udcac Elegir un tema\u2026</button>' +
        '<div class="enc-int-temas-panel" data-temas-panel hidden role="menu">';
      temas.forEach(function (h) {
        html += '<button type="button" class="enc-int-btn enc-int-btn--hobby enc-int-opt" data-enc-int-accion="hobby" data-hobby-id="' + esc(h.id) + '" data-residente-id="' + esc(h.residente_id) + '" role="menuitem">' +
          esc(h.etiqueta) + '</button>';
      });
      html += '</div></div>';
    }
    html += '</div>';
    html += '<p class="enc-int-feedback" data-enc-int-feedback hidden></p></div>';
    return html;
  }
  function cerrarSelectorTemas() {
    $$('[data-temas-panel]').forEach(function (p) { p.hidden = true; });
    $$('[data-temas-toggle][aria-expanded="true"]').forEach(function (t) {
      t.setAttribute('aria-expanded', 'false');
    });
  }
  async function ejecutarIntervencionEncuentro(encId, accion, extra) {
    var payload = { encuentro_id: encId, accion: accion };
    if (extra && extra.hobby_id) payload.hobby_id = extra.hobby_id;
    if (extra && extra.residente_id) payload.residente_id = extra.residente_id;
    if (extra && extra.objetivo) payload.objetivo = extra.objetivo;
    var r = await api('encuentro.intervencion.ejecutar', payload);
    if (!r.ok) {
      toast(r.mensaje_ui || 'No se pudo intervenir.');
      return r;
    }
    if (r.estado_delta && cacheEstado) {
      Object.keys(r.estado_delta).forEach(function (k) {
        cacheEstado[k] = r.estado_delta[k];
      });
    }
    /* Identidad: la vista de intervencion SOLO se escribe en el encuentro
       intervenido (por id). Nunca sobre un "encuentro actual" global. */
    var vistaIntervencion = r.vista || {
      disponible: false,
      usada: true,
      ultimo: { accion: r.intervencion ? r.intervencion.accion : accion, tono: r.intervencion ? r.intervencion.tono : 'neutral', texto: r.intervencion ? r.intervencion.texto : '' }
    };
    if (r.intervencion && cacheEstado && Array.isArray(cacheEstado.encuentros_en_curso)) {
      cacheEstado.encuentros_en_curso.forEach(function (e) {
        if (e && String(e.id) === String(encId)) e.intervencion = vistaIntervencion;
      });
    }
    if (r.intervencion && cacheEstado && cacheEstado.encuentro_en_curso &&
        String(cacheEstado.encuentro_en_curso.id) === String(encId)) {
      cacheEstado.encuentro_en_curso.intervencion = vistaIntervencion;
    }
    if (cacheInsp && cacheInsp.encuentros) {
      cacheInsp.encuentros.forEach(function (e) {
        if (e.id === encId) {
          e.intervencion_celeste = r.intervencion;
          if (r.vista) e.intervencion = r.vista;
        }
      });
    }
    renderShellPanels(cacheEstado, cacheBuzon, cacheDiario);
    return r;
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
  function resumenCotilleoUi(txt, max) {
    var s = String(txt || '').replace(/\s+/g, ' ').trim();
    var lim = max || 110;
    if (!s) return '';
    if (s.length <= lim) return s;
    return s.slice(0, lim - 1).trim() + '—';
  }

  /* === Planes en curso — carrusel movil (misma fuente canonica que desktop) === */

  function proximosPlanesFuturos(partida, estado) {
    const reloj = (estado && estado.reloj) || {};
    const now = relojAbs(reloj.dia_pueblo, reloj.hora_actual);
    return ((partida && partida.encuentros) || []).filter(function (e) {
      if (!e || !e.id) return false;
      if (String(e.estado || '') !== 'programado') return false;
      if (planEsEnCurso(e, estado)) return false;
      return now < relojAbs(e.dia, horaEnc(e)) + duracionEncHoras(e);
    }).sort(function (a, b) {
      return relojAbs(a.dia, horaEnc(a)) - relojAbs(b.dia, horaEnc(b)) ||
        String(a.id).localeCompare(String(b.id));
    });
  }
  function htmlProximoPlanCardMovil(enc, estado) {
    const ids = enc.participantes || [];
    const diaHoy = Number((estado && estado.reloj && estado.reloj.dia_pueblo));
    const sello = (Number(enc.dia) === diaHoy ? 'HOY' : 'D\u00cdA ' + (enc.dia || '?')) +
      ' \u00b7 ' + String(horaEnc(enc)).padStart(2, '0') + ':00';
    return '<article class="pp-mov-card">' +
      '<div class="pp-mov-top">' +
      '<p class="pp-mov-hora">' + esc(sello) + '</p>' +
      '<span class="pp-mov-star" aria-hidden="true">\u2605</span>' +
      '</div>' +
      '<div class="pp-mov-body">' +
      '<div class="prox-faces">' + carasPlanHtml(ids) + '</div>' +
      '<div class="pp-mov-copy">' +
      '<p class="pp-mov-nombres">' + esc(ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ')) + '</p>' +
      '<p class="pp-mov-lugar">' + esc(nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar)) + '</p>' +
      '</div>' +
      '</div></article>';
  }
  function renderProximosPlanesBlock(block, estado, cardFn) {
    if (!block) return;
    const track = block.querySelector('[data-proxplanes-track]');
    if (!track) return;
    const cntEl = block.querySelector('[data-proxplanes-count]');
    const listaFull = proximosPlanesFuturos(cacheInsp, estado);
    const total = listaFull.length;
    if (cntEl) {
      if (total > 0) { cntEl.textContent = String(total); cntEl.hidden = false; cntEl.removeAttribute('aria-hidden'); }
      else { cntEl.textContent = ''; cntEl.hidden = true; cntEl.setAttribute('aria-hidden', 'true'); }
    }
    const lista = listaFull.slice(0, 6);
    if (!lista.length) { block.classList.remove('is-on'); block.classList.add('is-empty'); track.innerHTML = ''; return; }
    block.classList.remove('is-empty');
    block.classList.add('is-on');
    track.innerHTML = lista.map(function (enc) { return cardFn(enc, estado); }).join('');
  }
  function renderProximosPlanesMovil(estado) {
    inicioBlocks('[data-proxplanes-block]').forEach(function (block) {
      const view = block.closest('.inicio-mobile') ? 'mobile' : 'desktop';
      const cardFn = view === 'mobile' ? htmlProximoPlanCardMovil : htmlProximoPlanCardMovil;
      renderProximosPlanesBlock(block, estado, cardFn);
    });
  }
    var encMovIndice = 0;
    function esInicioLayoutMovil() {
    return typeof window !== 'undefined' && window.matchMedia &&
      window.matchMedia('(max-width: 768px)').matches;
  }
  function familiaTipoEncuentro(enc) {
    const ids = (enc && enc.participantes) || [];
    if (ids.length > 2) return 'grupal';
    const t = String((enc && enc.tipo) || '').toLowerCase();
    if (t === 'individual') return 'individual';
    if (t === 'primera_cita' || t === 'cita' || t === 'romantico') return 'romantico';
    if (t === 'conocerse') return 'conocerse';
    if (t === 'conflicto') return 'conflicto';
    if (t === 'quedar' || t === 'amistad' || t === 'otro') return 'social';
    return 'social';
  }
  function iconoEncuentroCentroHtml(enc) {
    const ids = (enc && enc.participantes) || [];
    if (ids.length < 2) return '';
    const fam = familiaTipoEncuentro(enc);
    const cls = 'enc-mov-tipo-ico enc-mov-tipo-ico--' + fam;
    if (fam === 'romantico') {
      return '<span class="' + cls + '" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 21s-7.2-4.35-9.6-8.1C.6 9.75 2.4 6.6 5.7 6.6c1.8 0 3.15.9 4.05 2.1.9-1.2 2.25-2.1 4.05-2.1 3.3 0 5.1 3.15 3.3 6.3C19.2 16.65 12 21 12 21z" fill="currentColor"/></svg></span>';
    }
    if (fam === 'conocerse') {
      return '<span class="' + cls + '" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="8" cy="9" r="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="9" r="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4.5 19c.8-3 2.8-4.5 4.5-4.5S12.7 16 13.5 19M10.5 19c.8-3 2.8-4.5 4.5-4.5s3.7 1.5 4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>';
    }
    if (fam === 'grupal') {
      return '<span class="' + cls + '" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="8" cy="9" r="2.6" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="16" cy="9" r="2.6" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="7" r="2.2" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M3.5 19c.6-2.4 2.2-3.8 4-3.8M17 19c.6-2.4 2.2-3.8 4-3.8" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></span>';
    }
    if (fam === 'conflicto') {
      return '<span class="' + cls + '" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 3l1.8 6.2L20 11l-6.2 1.8L12 19l-1.8-6.2L4 11l6.2-1.8L12 3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span>';
    }
    return '<span class="' + cls + '" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="8" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="16" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M6 18c.5-2 2-3 2-3M16 18c.5-2 2-3 2-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>';
  }
  function formatEncursoMetaLine(enc, estado) {
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    const hora = String(horaEnc(enc)).padStart(2, '0') + ':00';
    const reloj = (estado && estado.reloj) || {};
    if (planEsEnCurso(enc, estado)) return lugar + ' \u00b7 En curso \u00b7 ' + hora;
    if (Number(enc.dia) === Number(reloj.dia_pueblo)) return lugar + ' \u00b7 Hoy ' + hora;
    return lugar + ' \u00b7 D\u00eda ' + (enc.dia || '?') + ' \u00b7 ' + hora;
  }
  function encCursoFacesHtml(enc) {
    const ids = (enc && enc.participantes) || [];
    const slice = ids.slice(0, 2);
    if (slice.length < 2) return carasPlanHtml(slice);
    return htmlCaraToken(slice[0]) + iconoEncuentroCentroHtml(enc) + htmlCaraToken(slice[1]);
  }
  function resumenEncursoMovil(enc, estado) {
    const iv = intervencionVistaDe(enc, estado);
    if (iv && iv.usada && iv.ultimo && iv.ultimo.texto) {
      const t = textoFeedbackIntervencion(iv);
      if (t.length > 78) return t.slice(0, 75) + '\u2026';
      return t;
    }
    if (iv && iv.disponible && iv.acciones && iv.acciones.length) {
      return 'Puedes intervenir en el encuentro.';
    }
    if (iv && iv.ultimo && iv.ultimo.tono === 'mal') return 'La cosa se ha puesto tensa\u2026';
    return 'Parece que la cosa va bien\u2026';
  }
  function htmlEncursoVistaPanel(enc) {
    const ids = enc.participantes || [];
    const nombres = ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ');
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    return '<div class="enc-mov-vista"><p class="enc-mov-vista-txt">' +
      esc(nombres) + ' \u00b7 ' + esc(lugar) + '</p></div>';
  }
  function htmlEncursoCardDesktop(enc, estado) {
    const ids = enc.participantes || [];
    const iv = intervencionVistaDe(enc, estado);
    const puedeIntervenir = !!(iv && iv.disponible && iv.acciones && iv.acciones.length);
    const hayInt = !!iv && ((iv.usada && iv.ultimo && iv.ultimo.texto) ||
      (iv.disponible && iv.acciones && iv.acciones.length));
    const ctaTxt = puedeIntervenir ? 'Intervenir' : 'Ver encuentro';
    const panelHtml = hayInt ? htmlIntervencionEncuentro(enc, estado) : htmlEncursoVistaPanel(enc);
    let html = '<article class="enc-mov-card enc-mov-card--ref" data-enc-mov-card data-enc-id="' + esc(enc.id || '') + '">' +
      '<p class="enc-mov-card-tit">PLAN EN CURSO</p>' +
      '<div class="enc-mov-body">' +
      '<div class="enc-mov-faces prox-faces">' + encCursoFacesHtml(enc) + '</div>' +
      '<p class="enc-mov-meta">' + esc(formatEncursoMetaLine(enc, estado)) + '</p>' +
      '<p class="enc-mov-resumen">' + esc(resumenEncursoMovil(enc, estado)) + '</p>' +
      '</div>' +
      '<button type="button" class="enc-mov-cta" data-enc-mov-toggle aria-expanded="false">' +
      '<span class="enc-mov-cta-ico" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg></span>' +
      '<span class="enc-mov-cta-txt">' + esc(ctaTxt) + '</span></button>' +
      '<div class="enc-mov-panel" data-enc-mov-panel hidden>' + panelHtml + '</div>';
    return html + '</article>';
  }
  function htmlEncursoCardMovilV14(enc, estado) {
    const ids = enc.participantes || [];
    const iv = intervencionVistaDe(enc, estado);
    const puedeIntervenir = !!(iv && iv.disponible && iv.acciones && iv.acciones.length);
    const hayInt = !!iv && ((iv.usada && iv.ultimo && iv.ultimo.texto) ||
      (iv.disponible && iv.acciones && iv.acciones.length));
    const ctaTxt = puedeIntervenir ? 'Intervenir' : 'Ver encuentro';
    const panelHtml = hayInt ? htmlIntervencionEncuentro(enc, estado) : htmlEncursoVistaPanel(enc);
    let html = '<article class="enc-mov-card enc-mov-card--ref" data-enc-mov-card data-enc-id="' + esc(enc.id || '') + '">' +
      '<p class="enc-mov-card-tit">PLAN EN CURSO</p>' +
      '<div class="enc-mov-body">' +
      '<div class="enc-mov-faces prox-faces">' + encCursoFacesHtml(enc) + '</div>' +
      '<p class="enc-mov-meta">' + esc(formatEncursoMetaLine(enc, estado)) + '</p>' +
      '<p class="enc-mov-resumen">' + esc(resumenEncursoMovil(enc, estado)) + '</p>' +
      '</div>' +
      '<button type="button" class="enc-mov-cta" data-enc-mov-toggle aria-expanded="false">' +
      '<span class="enc-mov-cta-ico" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg></span>' +
      '<span class="enc-mov-cta-txt">' + esc(ctaTxt) + '</span></button>' +
      '<div class="enc-mov-panel" data-enc-mov-panel hidden>' + panelHtml + '</div>';
    return html + '</article>';
  }
  function htmlEncursoCardMovil(enc, estado) {
    return htmlEncursoCardMovilV14(enc, estado);
  }
  function htmlEncursoCardDesktopView(enc, estado) {
    return htmlEncursoCardDesktop(enc, estado);
  }
    function encMovPaso(track) {
    const cards = track.querySelectorAll('[data-enc-mov-card]');
    if (!cards.length) return 0;
    const st = getComputedStyle(track);
    const gap = parseFloat(st.columnGap || st.gap) || 0;
    return cards[0].offsetWidth + gap;
  }
  function encMovIrA(block, idx) {
    const track = block && block.querySelector('[data-encursos-track]');
    if (!block || !track) return;
    const n = track.querySelectorAll('[data-enc-mov-card]').length;
    if (n < 2) return;
    const paso = encMovPaso(track);
    if (paso <= 0) return;
    const i = Math.max(0, Math.min(n - 1, idx));
    block._encMovIndice = i;
    track.scrollTo({ left: i * paso, behavior: 'smooth' });
    renderEncursosMovilNavFor(block);
  }
  function renderEncursosMovilNavFor(block) {
    const track = block && block.querySelector('[data-encursos-track]');
    const shell = block && block.querySelector('[data-encursos-shell]');
    const prev = block && block.querySelector('[data-enc-mov-prev]');
    const next = block && block.querySelector('[data-enc-mov-next]');
    if (!block || !track || !shell || !prev || !next) return;
    const n = track.querySelectorAll('[data-enc-mov-card]').length;
    if (!block.classList.contains('is-on') || n < 1) {
      shell.hidden = true;
      shell.setAttribute('aria-hidden', 'true');
      prev.hidden = true;
      next.hidden = true;
      return;
    }
    const paso = encMovPaso(track);
    const idx = paso > 0 ? Math.min(n - 1, Math.max(0, Math.round(track.scrollLeft / paso))) : 0;
    block._encMovIndice = idx;
    shell.hidden = false;
    shell.removeAttribute('aria-hidden');
    prev.hidden = n < 2 || idx <= 0;
    next.hidden = n < 2 || idx >= n - 1;
  }
  function renderEncursosMovilNav() {
    inicioBlocks('[data-encursos-block]').forEach(renderEncursosMovilNavFor);
  }
  function renderEncursosMovilIndicador() {
    renderEncursosMovilNav();
  }
      function renderEncursosBlock(block, estado, cardFn) {
    if (!block) return;
    const track = block.querySelector('[data-encursos-track]');
    if (!track) return;
    const lista = encuentrosEnCursoAhora(cacheInsp, estado);
    const cntEl = block.querySelector('[data-encursos-count]');
    if (cntEl) {
      if (lista.length > 0) { cntEl.textContent = String(lista.length); cntEl.hidden = false; cntEl.removeAttribute('aria-hidden'); }
      else { cntEl.textContent = ''; cntEl.hidden = true; cntEl.setAttribute('aria-hidden', 'true'); }
    }
    if (!lista.length) {
      block.classList.remove('is-on');
      block.classList.add('is-empty');
      track.innerHTML = '';
      renderEncursosMovilNavFor(block);
      return;
    }
    block.classList.remove('is-empty');
    const abiertos = {};
    track.querySelectorAll('[data-enc-mov-panel]:not([hidden])').forEach(function (p) {
      const card = p.closest('[data-enc-mov-card]');
      if (card) abiertos[card.getAttribute('data-enc-id') || ''] = true;
    });
    block.classList.add('is-on');
    track.innerHTML = lista.map(function (enc) { return cardFn(enc, estado); }).join('');
    requestAnimationFrame(function () {
      const cards = track.querySelectorAll('[data-enc-mov-card]');
      Object.keys(abiertos).forEach(function (id) {
        Array.prototype.forEach.call(cards, function (card) {
          if ((card.getAttribute('data-enc-id') || '') !== id) return;
          const panel = card.querySelector('[data-enc-mov-panel]');
          const cta = card.querySelector('[data-enc-mov-toggle]');
          if (panel) panel.hidden = false;
          if (cta) { cta.setAttribute('aria-expanded', 'true'); cta.classList.add('is-open'); }
        });
      });
      renderEncursosMovilNavFor(block);
    });
  }
    function renderEncursosMovil(estado) {
    inicioBlocks('[data-encursos-block]').forEach(function (block) {
      const view = block.closest('.inicio-mobile') ? 'mobile' : 'desktop';
      const cardFn = view === 'mobile' ? htmlEncursoCardMovil : htmlEncursoCardDesktopView;
      renderEncursosBlock(block, estado, cardFn);
    });
  }

  function buildInicioViewModel(estado, buzon, diario) {
    const partida = cacheInsp || {};
    const met = metricasSociales(partida);
    const parejas = parejasParaUI(partida);
    const hoy = (diario && diario.cotilleo && diario.cotilleo.hoy) || diario.entradas || [];
    const hoyLista = Array.isArray(hoy) ? hoy : [];
    const ultRaw = (hoyLista[0] && (hoyLista[0].texto || hoyLista[0].cuerpo || hoyLista[0].titulo)) || '';
    const pend = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    return {
      statsHtml: htmlResumenCelestine(met),
      vecinosPoblacion: String(met.vecinos) + ' de ' + String(met.cap),
      cotilleoTeaser: ultRaw ? resumenCotilleoUi(ultRaw, 120) : 'Hoy est\u00e1n sospechosamente tranquilos\u2026',
      buzonPreview: !pend.length ? 'Sin mensajes pendientes.' : ((pend[0].remitente_nombre || pend[0].de || 'Mensaje') + ': ' + (pend[0].preview || pend[0].asunto || pend[0].texto || '').slice(0, 80)),
      parejas: parejas,
    };
  }

  function renderParejasStripEl(strip, parejas) {
    if (!strip) return;
    strip.innerHTML = '';
  (parejas || []).forEach(function (rel) {
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
    if (!parejas || !parejas.length) {
      strip.innerHTML = '<p class="muted">A\u00fan no hay parejas registradas.</p>';
    }
  }

  function renderParejasStripIn(scopeSel, parejas) {
    const root = document.querySelector(scopeSel);
    if (!root) return;
    const strip = root.querySelector('[data-parejas-strip]');
    renderParejasStripEl(strip, parejas);
  }

  function renderInicioMobile(vm, estado) {
    setAllHtml('[data-resumen-stats]', vm.statsHtml);
    setAllText('[data-vecinos-poblacion]', vm.vecinosPoblacion);
    setAllText('[data-cotilleo-teaser]', vm.cotilleoTeaser);
    renderVecinosPreviewIn('.inicio-mobile');
    renderParejasStripIn('.inicio-mobile', vm.parejas);
    renderProximosPlanesMovil(estado);
    renderEncursosMovil(estado);
  }

  function renderInicioDesktop(vm, estado) {
    setAllHtml('[data-resumen-stats]', vm.statsHtml);
    setAllText('[data-vecinos-poblacion]', vm.vecinosPoblacion);
    setAllText('[data-cotilleo-teaser]', vm.cotilleoTeaser);
    renderVecinosPreviewIn('.inicio-desktop');
    renderParejasStripIn('.inicio-desktop', vm.parejas);
    renderProximosPlanesMovil(estado);
    renderEncursosMovil(estado);
  }

  function renderInicio(estado, buzon, diario) {
    const vm = buildInicioViewModel(estado, buzon, diario);
    renderInicioMobile(vm, estado);
    renderInicioDesktop(vm, estado);
    actualizarCotiBadgesUI();
  }

  function bootSyncInicioViewVisibility() {
    syncInicioViewVisibility();
    window.addEventListener('resize', syncInicioViewVisibility);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootSyncInicioViewVisibility);
  else bootSyncInicioViewVisibility();

  function syncInicioViewVisibility() {
    const mobileSections = document.querySelectorAll('.inicio-mobile');
    const desktop = document.querySelector('.inicio-desktop');
    const isMob = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
    mobileSections.forEach(function (mobile) {
      mobile.classList.toggle('is-inicio-view-active', isMob);
      mobile.removeAttribute('hidden');
      mobile.toggleAttribute('inert', !isMob);
    });
    if (desktop) {
      desktop.classList.toggle('is-inicio-view-active', !isMob);
      desktop.removeAttribute('hidden');
      desktop.toggleAttribute('inert', isMob);
    }
  }

  function renderShellPanels(estado, buzon, diario) {
    renderInicio(estado, buzon, diario);
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
        var btn = document.createElement('div');
        btn.className = 'mapa-zona-hit';
        btn.setAttribute('role', 'button');
        btn.tabIndex = 0;
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

  function zonaBtnPorId(zonaId) {
    var layer = $('[data-mapa-zonas]');
    return layer ? layer.querySelector('[data-zona="' + zonaId + '"]') : null;
  }

  /** Hit-test por coordenadas sobre la imagen del mapa (más fiable que botones % en móvil). */
  function zonaDesdePuntoMapa(clientX, clientY) {
    if (!cacheMapaZonas || !cacheMapaZonas.zonas) return null;
    var img = document.querySelector('[data-mapa-canonico] .mapa-canonico-bg');
    if (!img) return null;
    var rect = img.getBoundingClientRect();
    if (!rect.width || !rect.height) return null;
    if (clientX < rect.left || clientX > rect.right || clientY < rect.top || clientY > rect.bottom) return null;
    var px = ((clientX - rect.left) / rect.width) * 100;
    var py = ((clientY - rect.top) / rect.height) * 100;
    var zonas = cacheMapaZonas.zonas;
    var found = null;
    Object.keys(zonas).forEach(function (id) {
      var z = zonas[id];
      if (!z || !z.w || !z.h) return;
      if (px >= z.x && px <= z.x + z.w && py >= z.y && py <= z.y + z.h) found = id;
    });
    if (!found) return null;
    return { zonaId: found, zonaBtn: zonaBtnPorId(found) };
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

  function destinoOperativoPorId(lugId) {
    var found = null;
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.destinos_operativos || []).forEach(function (d) {
        if (d.id === lugId) found = d;
      });
    });
    return found;
  }

  function lineaHorarioDestino(d) {
    if (!d || !d.horario) return '';
    var est = d.abierto_ahora ? 'Abierto ahora' : 'Cerrado ahora';
    return est + ' · ' + d.horario;
  }

  function pintarHorarioQuien(destinos) {
    var el = $('[data-q-horario]');
    if (!el) return;
    var list = (destinos || []).filter(function (d) { return d && d.horario; });
    if (!list.length) {
      el.innerHTML = '';
      el.hidden = true;
      return;
    }
    el.innerHTML = list.map(function (d) {
      var estado = '';
      var estadoCls = '';
      if (d.abierto_ahora === true) {
        estado = 'Abierto ahora';
        estadoCls = 'quien-estado--abierto';
      } else if (d.abierto_ahora === false) {
        estado = 'Cerrado ahora';
        estadoCls = 'quien-estado--cerrado';
      }
      if (estado) {
        return '<span class="quien-estado ' + estadoCls + '">' + estado + '</span>' +
          '<span class="quien-horas"> · ' + esc(d.horario) + '</span>';
      }
      return '<span class="quien-horas">Horario: ' + esc(d.horario) + '</span>';
    }).join('<span class="quien-horario-sep"> | </span>');
    el.hidden = false;
  }

  function pintarOrgLugarHorario(lugId) {
    var el = $('[data-org-lugar-horario]');
    if (!el) return;
    var id = lugId || ($('[data-org-lugar]') && $('[data-org-lugar]').value) || org.lugar || '';
    if (!id) {
      el.innerHTML = '';
      el.hidden = true;
      return;
    }
    var d = destinoOperativoPorId(id);
    if (!d || !d.horario) {
      el.innerHTML = '';
      el.hidden = true;
      return;
    }
    var estado = d.abierto_ahora ? 'Abierto ahora' : 'Cerrado ahora';
    var estadoCls = d.abierto_ahora ? 'quien-estado--abierto' : 'quien-estado--cerrado';
    el.innerHTML = '<span class="quien-estado ' + estadoCls + '">' + estado + '</span>' +
      '<span class="quien-horas"> · ' + esc(d.horario) + '</span>';
    el.hidden = false;
  }

  var MAPA_TEMA_PRIORIDAD = { romance: 0, drama: 1, relacion: 2, coincidencias: 3 };
  var MAPA_TEMA_ICONOS = {
    romance: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5 C7 16.5 3.5 13.2 3.5 9.4 C3.5 6.8 5.5 5 7.9 5 C9.6 5 11.1 5.9 12 7.3 C12.9 5.9 14.4 5 16.1 5 C18.5 5 20.5 6.8 20.5 9.4 C20.5 13.2 17 16.5 12 20.5 Z"/></svg>',
    drama: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 L5.5 13 L10.5 13 L9.5 22 L18.5 10 L12.8 10 Z"/></svg>',
    relacion: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 6 H19.5 V15.5 H12.5 L8 19.5 V15.5 H4.5 Z"/></svg>',
    coincidencias: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 6 H19.5 V15.5 H12.5 L8 19.5 V15.5 H4.5 Z"/><circle cx="9" cy="10.7" r="1"/><circle cx="12" cy="10.7" r="1"/><circle cx="15" cy="10.7" r="1"/></svg>'
  };
  function mapaTemaIcono(cat) {
    return MAPA_TEMA_ICONOS[cat] || MAPA_TEMA_ICONOS.coincidencias;
  }
  function pintarHorariosMapa() {
    /* Horario solo en la ventana de consulta / Nuevo plan, no en el mapa. */
    var layer = $('[data-mapa-zonas]');
    if (!layer) return;
    layer.querySelectorAll('.mapa-zona-horario').forEach(function (badge) {
      badge.textContent = '';
      badge.hidden = true;
      badge.removeAttribute('data-abierto');
      badge.removeAttribute('title');
    });
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

  function actualizarNotaAtras() {
    var root = $('.play-root');
    var consulta = root && root.getAttribute('data-consulta');
    $$('[data-consulta-atras]').forEach(function (btn) {
      var show = consulta === 'quien' && consultaNav && consultaNav.fromSel;
      btn.hidden = !show;
    });
    syncScrollLock();
  }

  function abrirConsultaZona(zonaId, zonaBtn, silentHist) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var ops = destinosOperativosZona(zonaId);
    if (ops.length > 1 || silentHist) {
      $('.play-root').setAttribute('data-consulta', 'sel');
      consultaNav = { tipo: 'zona', zonaId: zonaId, zonaBtn: zonaBtn, vista: 'sel', fromSel: false };
      if (!silentHist) uiHistPush();
    marcarConsultaLugar($('.selector'), zonaId);
      $('[data-s-tit]').textContent = meta ? meta.label : zonaId;
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' · ');
      var box = $('[data-s-btns]');
      box.innerHTML = '';
      ops.forEach(function (d) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Ver ' + nombreLugarUi(d.id, d.nombre);
        b.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); abrirQuienZona(zonaId, d.id, zonaBtn); });
        box.appendChild(b);
      });
      var all = document.createElement('button');
      all.type = 'button';
      all.textContent = 'Quién hay aquí';
      all.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); abrirQuienZona(zonaId, null, zonaBtn); });
      box.appendChild(all);
      posicionarNotaMapa($('.selector'), zonaBtn);
      actualizarNotaAtras();
      return;
    }
    abrirQuienZona(zonaId, ops[0] ? ops[0].id : null, zonaBtn, silentHist);
  }


  var quienTemaActivo = null;

  function recolectarTemasQuien(gente) {
    var vistos = {};
    var temas = [];
    (gente || []).forEach(function (p) {
      if (!p || !p.hay_tema || !p.tema_vista) return;
      var id = p.tema_id || (p.tema_vista && p.tema_vista.tema_id) || '';
      if (id && vistos[id]) return;
      if (id) vistos[id] = true;
      temas.push(Object.assign({ tema_id: id }, p.tema_vista));
    });
    return temas;
  }

  function mapaTemaResidenteQuien(gente) {
    var porId = {};
    (gente || []).forEach(function (p) {
      if (!p || !p.hay_tema || !p.tema_vista) return;
      var tid = p.tema_id || (p.tema_vista && p.tema_vista.tema_id) || '';
      if (tid && p.id) porId[p.id] = tid;
    });
    return porId;
  }

  function pintarQuienResidentes(gente, temaActivo, vacioTxt) {
    var list = $('[data-q-list]');
    var vacio = $('[data-q-sum]');
    if (!list) return;
    list.innerHTML = '';
    if (!gente || !gente.length) {
      list.hidden = true;
      if (vacio) {
        vacio.hidden = false;
        vacio.textContent = vacioTxt || 'No hay ni un alma.';
      }
      return;
    }
    list.hidden = false;
    if (vacio) vacio.hidden = true;
    list.className = 'quien-list quien-residentes quien-residentes--inline';
    var temaPorRes = mapaTemaResidenteQuien(gente);
    var temas = recolectarTemasQuien(gente);
    (gente || []).forEach(function (p, idx) {
      if (idx > 0) {
        var sep = document.createElement('span');
        sep.className = 'quien-residente-sep';
        sep.setAttribute('aria-hidden', 'true');
        sep.textContent = '·';
        list.appendChild(sep);
      }
      var row = document.createElement('span');
      row.className = 'quien-residente';
      var nameBtn = document.createElement('button');
      nameBtn.type = 'button';
      nameBtn.className = 'quien-residente-nombre cara-token';
      nameBtn.setAttribute('data-residente', p.id);
      nameBtn.textContent = p.nombre;
      row.appendChild(nameBtn);
      var tid = temaPorRes[p.id];
      if (tid) {
        var tv = null;
        for (var i = 0; i < temas.length; i++) {
          if (temas[i].tema_id === tid) { tv = temas[i]; break; }
        }
        var mark = document.createElement('button');
        mark.type = 'button';
        mark.className = 'quien-tema-mark quien-tema-mark--' + esc((tv && tv.categoria) || 'hecho');
        mark.setAttribute('aria-label', 'Aquí hay tema');
        mark.textContent = (tv && tv.categoria_icono) || '◎';
        (function (temaId) {
          mark.addEventListener('click', function (ev) {
            ev.stopPropagation();
            quienTemaActivo = quienTemaActivo === temaId ? null : temaId;
            pintarQuienTema(gente, quienTemaActivo);
            pintarQuienResidentes(gente, quienTemaActivo, vacioTxt);
          });
        })(tid);
        row.appendChild(mark);
        if (temaActivo === tid) row.classList.add('quien-residente--activo');
      }
      list.appendChild(row);
    });
  }

  function pintarQuienTema(gente, temaActivoId) {
    var box = $('[data-q-tema]');
    if (!box) return;
    var temas = recolectarTemasQuien(gente);
    if (!temas.length) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    var activos = temas;
    if (temaActivoId) {
      activos = temas.filter(function (t) { return t.tema_id === temaActivoId; });
    } else if (temas.length > 1) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    if (!activos.length) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = activos.slice(0, 2).map(function (tv) {
      var cat = tv.categoria_etiqueta || 'Aquí hay tema';
      var ico = tv.categoria_icono || '◎';
      var dest = tv.destacado ? ' quien-tema--destacado' : '';
      return '<section class="quien-tema-card' + dest + '">' +
        '<p class="quien-tema-kicker">Aquí hay tema</p>' +
        '<p class="quien-tema-cat"><span class="quien-tema-ico" aria-hidden="true">' + esc(ico) + '</span> ' + esc(cat) + '</p>' +
        '<p class="quien-tema-txt">' + esc(tv.texto || '') + '</p>' +
        (tv.pista ? '<p class="quien-tema-pista">' + esc(tv.pista) + '</p>' : '') +
        '</section>';
    }).join('');
  }

  function abrirQuienZona(zonaId, destId, zonaBtn, silentHist) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var gente = personasEnZona(zonaId).filter(function (p) {
      return !destId || p.destino_id === destId;
    });
    var fromSel = $('.play-root').getAttribute('data-consulta') === 'sel';
    $('.play-root').setAttribute('data-consulta', 'quien');
    consultaNav = { tipo: 'zona', zonaId: zonaId, zonaBtn: zonaBtn, destId: destId, vista: 'quien', fromSel: fromSel };
    if (!silentHist) uiHistPush();
    marcarConsultaLugar($('.quien'), destId || zonaId);
    $('[data-q-tit]').textContent = meta ? meta.label : zonaId;
    var destinosHorario = destId
      ? [destinoOperativoPorId(destId)].filter(Boolean)
      : destinosOperativosZona(zonaId);
    if (destId) {
      var dnom = destinosHorario[0] && destinosHorario[0].nombre;
      if (dnom) $('[data-q-tit]').textContent = dnom;
    }
    pintarHorarioQuien(destinosHorario);
    quienTemaActivo = null;
    pintarQuienResidentes(gente, null, gente.length ? '' : 'No hay ni un alma.');
    pintarQuienTema(gente, null);
    var box = $('[data-q-btns]');
    box.innerHTML = '';
    destinosOperativosZona(zonaId).forEach(function (d) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Organizar en ' + nombreLugarUi(d.id, d.nombre);
      b.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        abrirOrganizarConPreset({ lugar: d.id });
      });
      box.appendChild(b);
    });
    posicionarNotaMapa($('.quien'), zonaBtn);
    actualizarNotaAtras();
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
    inicioAll('[data-misiones-strip]').forEach(function (strip) { renderMisionesStripEl(strip, items); });
  }
  function renderMisionesStripEl(strip, items) {
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
      if (e.animationName !== 'corazon-vida-sube' && e.animationName !== 'corazon-vida-baja' && e.animationName !== 'corazon-vida-latido') return;
      svg.removeEventListener('animationend', onEnd);
      svg.classList.remove(cls);
      svg.classList.add('corazon-vida--reposo');
    }
    svg.addEventListener('animationend', onEnd);
  }

  function triggerCorazonVidaReaction(svg, dir) {
    const cls = dir === 'sube' ? 'corazon-vida--sube' : 'corazon-vida--baja';
    svg.classList.remove('corazon-vida--reposo', 'corazon-vida--sube', 'corazon-vida--baja', 'corazon-vida--latido');
    svg.classList.add(cls);
    corazonVidaOnReactionEnd(svg, cls);
  }

  function triggerCorazonLatido(svg) {
    svg.classList.remove('corazon-vida--reposo', 'corazon-vida--sube', 'corazon-vida--baja');
    svg.classList.add('corazon-vida--latido');
    corazonVidaOnReactionEnd(svg, 'corazon-vida--latido');
  }

  function renderVidaDerrota(estado) {
    const vida = estado && estado.vida_pueblo ? estado.vida_pueblo : null;
    const box = $('[data-vida-derrota]');
    if (!box) return;
    const activo = !!(vida && vida.game_over_activo);
    box.hidden = !activo;
    document.body.classList.toggle('vida-derrota-activa', activo);
  }

  // Frontera canonica del motor: AgendaTemplates::ventanaSueno() acuesta a las 23:00.
  const HORA_NOCHE_DESDE = 23;
  const HORA_DIA_DESDE = 8;

  function horaActualEstado(estado) {
    const e = estado || cacheEstado;
    const rv = (e && e.reloj_vista) || {};
    const reloj = (e && e.reloj) || {};
    const h = rv.hora !== undefined ? rv.hora : reloj.hora_actual;
    return typeof h === 'number' ? h : null;
  }

  function esHoraNoche(h) {
    return h !== null && (h >= HORA_NOCHE_DESDE || h < HORA_DIA_DESDE);
  }

  function aplicarNocheVisual(esNoche) {
    const shell = document.querySelector('.game-shell');
    if (shell) shell.classList.toggle('noche-activa', !!esNoche);
  }

  function pintarModoReloj(esNoche) {
    aplicarNocheVisual(esNoche);
    $$('[data-es-noche]').forEach(function (el) {
      el.hidden = !esNoche;
    });
    const btn = $('[data-pasar-rato]');
    if (!btn) return;
    btn.classList.toggle('pasar-rato--noche', esNoche);
    btn.title = esNoche ? 'Avanza hasta las 08:00 de la mañana' : 'Avanza el tiempo exactamente 1 hora';
    if (!btn.classList.contains('is-busy')) {
      const txt = btn.querySelector('.pasar-rato-txt');
      if (txt) txt.textContent = esNoche ? 'Pasar la noche' : 'Pasar el rato';
    }
  }

  function renderHud(estado, buzon) {
    const rv = estado.reloj_vista || {};
    const reloj = estado.reloj || {};
    const diaNum = reloj.dia_pueblo;
    const fechaCorta = rv.fecha_corta || '';
    const diaLblHud = (diaNum !== undefined && diaNum !== null) ? ('D\u00eda ' + diaNum) : '';
    inicioAll('[data-dia-num]').forEach(function (diaNumEl) {
      diaNumEl.textContent = diaLblHud || '\u2014';
    });
    const h = rv.hora !== undefined ? rv.hora : reloj.hora_actual;
    const ht = h === undefined ? '\u2014:\u2014' : (String(h).padStart(2, '0') + ':00');
    $$('[data-dow]').forEach(function (el) {
      el.textContent = rv.dia_semana_ui || (diaNum !== undefined ? ('D\u00eda ' + diaNum) : '-');
    });
    $$('[data-fecha]').forEach(function (el) {
      el.textContent = fechaCorta;
    });
    $$('[data-hora]').forEach(function (el) {
      el.textContent = ht || '-';
    });
    pintarModoReloj(esHoraNoche(typeof h === 'number' ? h : null));
    inicioAll('[data-dia-estacion]').forEach(function (estEl) {
      estEl.textContent = '';
      estEl.hidden = true;
    });
    inicioAll('[data-dia-meta]').forEach(function (metaEl) {
      metaEl.textContent = fechaCorta || '\u2014';
    });
    inicioAll('[data-top-meta-mobile]').forEach(function (metaMobEl) {
      const mobDia = diaLblHud || 'D\u00eda \u2014';
      const mobFecha = fechaCorta || '\u2014';
      const min = rv.minuto !== undefined ? rv.minuto : reloj.minuto_actual;
      const mobHora = h === undefined ? '\u2014:\u2014' : (String(h).padStart(2, '0') + ':' + String(min === undefined || min === null ? 0 : min).padStart(2, '0'));
      metaMobEl.innerHTML = '<span class="top-meta-prim">' + mobDia + ' \u00b7 ' + mobFecha + '</span><span class="top-meta-hora">' + mobHora + '</span>';
    });
    const vida = estado.vida_pueblo || null;
    const pct = vida && typeof vida.corazon_pct === 'number' ? vida.corazon_pct : 0;
    const critico = !!(vida && vida.critico);
    const fillEls = inicioAll('[data-corazon-fill]');
    const fillEl = fillEls[0];
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
      if (vida && vida.latido_anim) {
        triggerCorazonLatido(corazonSvg);
      } else if (vidaCorazonReady && vidaCorazonPctPrev !== null && pct !== vidaCorazonPctPrev) {
        triggerCorazonVidaReaction(corazonSvg, pct > vidaCorazonPctPrev ? 'sube' : 'baja');
      } else if (!vidaCorazonReady) {
        corazonSvg.classList.add('corazon-vida--reposo');
      }
      vidaCorazonPctPrev = pct;
      vidaCorazonReady = true;
    }
    renderVidaDerrota(estado);
    const nPend = buzonNoLeidos(estado, buzon);
    const badgeHud = $('.buzon .badge');
    if (badgeHud) {
      badgeHud.textContent = String(nPend);
      badgeHud.classList.toggle('is-on', nPend > 0);
    }
    inicioAll('[data-buzon-badge]').forEach(function (badgeObj) {
      badgeObj.textContent = String(nPend);
      badgeObj.hidden = nPend <= 0;
    });
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
      var catTema = String((p.tema_vista && p.tema_vista.categoria) || 'hecho').toLowerCase();
      el.insertAdjacentHTML('beforeend',
        '<span class=\"tema-hab mapa-tema--' + esc(catTema) + '\" data-tema-hab=\"' + esc(catTema) + '\" title=\"Aqu\u00ed hay tema\">' + mapaTemaIcono(catTema) + '</span>');
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
    pintarHorariosMapa();
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

  function abrirConsulta(id, silentHist) {
    const cx = cxById(id);
    if (!cx) return;
    const ops = cx.destinos_operativos || [];
    if (ops.length > 1 || silentHist) {
      $('.play-root').setAttribute('data-consulta', 'sel');
      consultaNav = { tipo: 'complejo', complejoId: id, vista: 'sel', fromSel: false };
      if (!silentHist) uiHistPush();
    marcarConsultaLugar($('.selector'), id);
      $('[data-s-tit]').textContent = cx.nombre;
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' · ');
      const box = $('[data-s-btns]');
      box.innerHTML = '';
      ops.forEach(function (d) {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Ver ' + nombreLugarUi(d.id, d.nombre);
        b.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); abrirQuien(id, d.id); });
        box.appendChild(b);
      });
      const all = document.createElement('button');
      all.type = 'button';
      all.textContent = 'Quién hay en el complejo';
      all.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); abrirQuien(id, null); });
      box.appendChild(all);
      actualizarNotaAtras();
      return;
    }
    abrirQuien(id, ops[0] ? ops[0].id : null, silentHist);
  }

  function abrirQuien(id, destId, silentHist) {
    const cx = cxById(id);
    const fromSel = $('.play-root').getAttribute('data-consulta') === 'sel';
    $('.play-root').setAttribute('data-consulta', 'quien');
    consultaNav = { tipo: 'complejo', complejoId: id, destId: destId, vista: 'quien', fromSel: fromSel };
    if (!silentHist) uiHistPush();
    marcarConsultaLugar($('.quien'), id);
    $('[data-q-tit]').textContent = cx.nombre;
    const gente = (cx.personas || []).filter(function (p) {
      return !destId || p.destino_id === destId;
    });
    var destinosHorarioCx = destId
      ? [destinoOperativoPorId(destId)].filter(Boolean)
      : ((cx && cx.destinos_operativos) || []);
    if (destId) {
      var dnomCx = destinosHorarioCx[0] && destinosHorarioCx[0].nombre;
      if (dnomCx) $('[data-q-tit]').textContent = dnomCx;
    }
    pintarHorarioQuien(destinosHorarioCx);
    quienTemaActivo = null;
    pintarQuienResidentes(gente, null, gente.length ? '' : copyVacio(id));
    pintarQuienTema(gente, null);
    const box = $('[data-q-btns]');
    box.innerHTML = '';
    (cx.destinos_operativos || []).forEach(function (d) {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Organizar en ' + nombreLugarUi(d.id, d.nombre);
      b.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        abrirOrganizarConPreset({ lugar: d.id });
      });
      box.appendChild(b);
    });
    actualizarNotaAtras();
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
  let vecTabActiva = 'vecinos';

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


  function vecinosIdsOrdenados() {
    const res = (cacheInsp && cacheInsp.residentes) || {};
    return Object.keys(res).filter(function (id) {
      return (res[id].presencia || 'residente') === 'residente';
    }).sort(function (a, b) {
      return String(nombreDe(a)).localeCompare(String(nombreDe(b)), 'es');
    });
  }

  function renderVecinosPreviewIn(scopeSel) {
    var root = document.querySelector(scopeSel);
    if (!root) return;
    var box = root.querySelector('[data-vecinos-preview]');
    renderVecinosPreviewBox(box);
  }
  function renderVecinosPreview() {
    inicioAll('[data-vecinos-preview]').forEach(renderVecinosPreviewBox);
  }
  function renderVecinosPreviewBox(box) {
    if (!box) return;
    var res = (cacheInsp && cacheInsp.residentes) || {};
    var ids = Object.keys(res).filter(function (id) {
      var r = res[id];
      return (r.presencia || 'residente') === 'residente';
    });
    ids.sort(function (a, b) {
      var na = (res[a].identidad_publica && res[a].identidad_publica.nombre) || a;
      var nb = (res[b].identidad_publica && res[b].identidad_publica.nombre) || b;
      return String(na).localeCompare(String(nb), 'es');
    });
    var pick = ids.slice(0, 3);
    if (!pick.length) {
      box.innerHTML = '<span class="obj-vecinos-preview-ini">?</span>';
      return;
    }
    box.innerHTML = pick.map(function (id) {
      var r = res[id];
      var img = tokenDe(id);
      var nom = (r.identidad_publica && r.identidad_publica.nombre) || id;
      var ini = nom.charAt(0) || '?';
      return img
        ? '<img class="obj-vecinos-preview-cara" src="' + esc(img) + '" alt=""/>'
        : '<span class="obj-vecinos-preview-cara obj-vecinos-preview-ini">' + esc(ini) + '</span>';
    }).join('');
  }

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
        (filtroTxt ? 'Nadie con ese nombre.' : 'Todav\u00EDa no hay vecinos en esta partida.') + '</p>';
      return;
    }
    ids.forEach(function (id, idx) {
      const r = res[id];
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'vecino-celda vecino-celda--decor-' + (idx % 6);
      const img = tokenDe(id);
      const nom = (r.identidad_publica && r.identidad_publica.nombre) || id;
      const ini = nom.charAt(0) || '?';
      const emo = emocionDe(id);
      const genero = (r.identidad_publica && r.identidad_publica.genero) || '';
      const eEmo = canonEmoId(emo);
      b.innerHTML =
        '<div class="vecino-celda-top">' +
        '<div class="vecino-cara vecino-cara--' + eEmo + '" data-emocion="' + esc(emo) + '">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="vecino-ini">' + esc(ini) + '</span>') +
        '</div>' +
        '<p class="vecino-nom">' + esc(nom) + '</p>' +
        emoPillVecino(emo, genero) +
        '</div>';
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
  let fichaAnimoExplicacion = null;
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

  var VEC_REL_FILTROS = [
    { id: '', txt: 'Todas', icono: '' },
    { id: 'romance', txt: 'Parejas', icono: '\\uD83D\\uDC8C' },
    { id: 'bien', txt: 'Amistad', icono: '\\uD83E\\uDD1D' },
    { id: 'conocidos', txt: 'Conocidos', icono: '\\uD83D\\uDC4F' },
    { id: 'mal', txt: 'Malas', icono: '\\uD83D\\uDC94' }
  ];
  var vecRelCache = [];
  var vecRelFiltro = '';
  var vecRelPersona = '';
  var vecRelCargado = false;
  var VEC_REL_ICONO_PAR = '<span class="vec-rel-vinculo-ico" aria-hidden="true">\\u2194</span>';

  function aplicarVecTabUI() {
    const isRel = vecTabActiva === 'relaciones';
    $$('[data-vec-tab]').forEach(function (btn) {
      const t = btn.getAttribute('data-vec-tab');
      const on = t === vecTabActiva;
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    const cuentaWrap = $('[data-vec-cuenta-wrap]');
    if (cuentaWrap) cuentaWrap.hidden = isRel;
    $$('[data-vec-panel]').forEach(function (p) {
      p.hidden = p.getAttribute('data-vec-panel') !== vecTabActiva;
    });
    const capa = document.querySelector('.capa-vecinos');
    if (capa) capa.classList.toggle('is-relaciones', isRel);
  }

  function setVecTab(tab) {
    vecTabActiva = tab === 'relaciones' ? 'relaciones' : 'vecinos';
    aplicarVecTabUI();
    if (vecTabActiva === 'relaciones') cargarVecRelaciones();
  }

  async function cargarVecRelaciones() {
    const list = $('[data-vec-rel-list]');
    if (list && !vecRelCargado) {
      list.innerHTML = '<p class="lista-vacia vec-rel-vacio">Mirando el cotilleo del pueblo\\u2026</p>';
    }
    if (vecRelCargado) {
      renderVecRelLista();
      return;
    }
    const r = await api('relacion.vista_pueblo', {}, 'GET');
    if (!r.ok) {
      if (list) list.innerHTML = '<p class="lista-vacia vec-rel-vacio">' + esc(r.mensaje_ui || 'No se pudieron cargar las relaciones.') + '</p>';
      toast(r.mensaje_ui || 'No se pudieron cargar las relaciones.');
      return;
    }
    vecRelCache = Array.isArray(r.relaciones) ? r.relaciones : [];
    vecRelCargado = true;
    vecRelFiltro = '';
    vecRelPersona = '';
    pintarVecRelFiltros();
    pintarVecRelPersonas();
    renderVecRelLista();
  }

  function pintarVecRelFiltros() {
    const box = $('[data-vec-rel-filtros]');
    if (!box) return;
    box.innerHTML = VEC_REL_FILTROS.map(function (f) {
      const activo = vecRelFiltro === f.id;
      if (!f.icono) {
        return '<button type="button" class="vec-rel-chip' + (activo ? ' is-on' : '') +
          '" data-vec-rel-filtro=""><b>T</b><span>' + f.txt + '</span></button>';
      }
      return '<button type="button" class="vec-rel-chip' + (activo ? ' is-on' : '') +
        '" data-vec-rel-filtro="' + f.id + '" aria-label="' + f.txt + '"><b>' + f.icono + '</b><span>' + f.txt + '</span></button>';
    }).join('');
  }

  function pintarVecRelPersonas() {
    const sel = $('[data-vec-rel-persona]');
    if (!sel) return;
    sel.innerHTML = '<option value="">Todo el pueblo</option>' + vecinosIdsOrdenados().map(function (id) {
      const nom = nombreDe(id);
      return '<option value="' + esc(id) + '"' + (vecRelPersona === id ? ' selected' : '') + '>' + esc(nom) + '</option>';
    }).join('');
  }

  function vecRelTextoDir(dir) {
    dir = dir || {};
    const parts = [];
    if (dir.etiqueta_vinculo === 'pareja') parts.push('\\u2764\\uFE0F Pareja');
    else if (dir.etiqueta_vinculo === 'crisis') parts.push('\\uD83D\\uDC94 En crisis');
    else if (dir.etiqueta_vinculo === 'ex_pareja') parts.push('\\uD83D\\uDC94 Ex pareja');
    else if (dir.romance_visible && dir.etiqueta_romance) parts.push((dir.emoji_romance || '\\uD83D\\uDC98') + ' ' + dir.etiqueta_romance);
    if ((dir.conocidos || dir.social_negativo) && dir.etiqueta_social_ui && dir.etiqueta_social !== 'desconocido') {
      parts.push(((dir.emoji_social || '') + ' ' + dir.etiqueta_social_ui).trim());
    }
    return parts.join(' \\u00B7 ');
  }

  function vecRelPillClass(dir) {
    dir = dir || {};
    if (dir.social_negativo || dir.etiqueta_social === 'cae_mal') return 'vec-rel-pill--red';
    if (dir.etiqueta_vinculo === 'pareja' || dir.romance_visible) return 'vec-rel-pill--pink';
    if (dir.etiqueta_social === 'conocido') return 'vec-rel-pill--mustard';
    return 'vec-rel-pill--green';
  }

  function vecRelFlags(row) {
    const ab = row.a_hacia_b || {};
    const ba = row.b_hacia_a || {};
    const f = { romance: false, bien: false, mal: false, conflicto: !!row.conflicto };
    [ab, ba].forEach(function (d) {
      if (d.etiqueta_vinculo || d.romance_visible) f.romance = true;
      const sx = d.etiqueta_social || '';
      if (d.social_negativo || sx === 'cae_mal') f.mal = true;
      else if (sx === 'cae_bien' || sx === 'amigo' || sx === 'buen_amigo' || sx === 'mejor_amigo' || sx === 'buena_amistad' || sx === 'muy_buena_amistad') f.bien = true;
    });
    f.conocidos = !(f.romance || f.bien || f.mal || f.conflicto) && !!(ab.conocidos || ba.conocidos);
    return f;
  }

  function vecRelMatchFiltro(row, filtro) {
    if (!filtro) return true;
    const f = vecRelFlags(row);
    if (filtro === 'romance') return f.romance;
    if (filtro === 'bien') return f.bien;
    if (filtro === 'mal') return f.mal || f.conflicto;
    if (filtro === 'conocidos') return f.conocidos;
    return true;
  }

  function caraVecRel(id, nombre) {
    const img = tokenDe(id);
    const ini = (nombre || '?').charAt(0);
    return img ? '<img src="' + esc(img) + '" alt=""/>' : '<span>' + esc(ini) + '</span>';
  }

  function vecRelBarra(dir, extraCls) {
    dir = dir || {};
    const pct = typeof dir.social_bar_pct === 'number'
      ? Math.max(4, Math.min(100, dir.social_bar_pct))
      : 8;
    const cls = 'vec-rel-barra' + (dir.social_negativo ? ' is-neg' : '') + (extraCls || '');
    return '<span class="' + cls + '" aria-hidden="true"><span style="width:' + pct + '%"></span></span>';
  }

  function vecRelDirCard(nomFrom, nomTo, dir) {
    const txt = vecRelTextoDir(dir);
    if (!txt && !(dir && dir.conocidos)) return '';
    const pill = '<span class="vec-rel-pill ' + vecRelPillClass(dir) + '">' + esc(txt || 'Se conocen de vista') + '</span>';
    const barExtra = dir.etiqueta_social === 'conocido' ? ' vec-rel-barra--mustard' : '';
    return '<div class="vec-rel-dir-card">' +
      '<div class="vec-rel-dir-head">' +
      '<span class="vec-rel-dir-nom">' + esc(nomFrom) + ' \\u2192 ' + esc(nomTo) + '</span>' +
      pill +
      '</div>' +
      vecRelBarra(dir, barExtra) +
      '</div>';
  }

  function htmlVecRelCard(row) {
    const a = row.persona_a || {};
    const b = row.persona_b || {};
    const ab = row.a_hacia_b || {};
    const ba = row.b_hacia_a || {};
    const tAb = vecRelTextoDir(ab);
    const tBa = vecRelTextoDir(ba);
    const f = vecRelFlags(row);
    const cls = 'vec-rel-card' + (f.romance ? ' is-amor' : '') + (f.mal ? ' is-mal' : '') + (f.conflicto ? ' is-conflicto' : '');
    const cardAb = vecRelDirCard(a.nombre || a.id || '', b.nombre || b.id || '', ab);
    const cardBa = vecRelDirCard(b.nombre || b.id || '', a.nombre || a.id || '', ba);
    let dirs = cardAb + cardBa;
    if (!dirs) dirs = '<p class="vec-rel-linea">Se conocen de vista.</p>';
    const asim = tAb !== tBa || (ab.social_bar_pct || 0) !== (ba.social_bar_pct || 0);
    const div = asim && cardAb && cardBa ? '<div class="vec-rel-divider">Diferente en cada sentido</div>' : '';
    const badge = f.conflicto ? '<span class="vec-rel-badge" aria-hidden="true">!</span>' : '';
    return (
      '<article class="' + cls + '">' +
      '<div class="vec-rel-par">' +
      '<button type="button" class="vec-rel-pers" data-vec-rel-open="' + esc(a.id || '') + '">' +
      '<span class="vec-rel-cara">' + caraVecRel(a.id, a.nombre) + '</span>' +
      '<span class="vec-rel-nom">' + esc(a.nombre || a.id || '?') + '</span></button>' +
      VEC_REL_ICONO_PAR +
      '<button type="button" class="vec-rel-pers" data-vec-rel-open="' + esc(b.id || '') + '">' +
      '<span class="vec-rel-cara">' + caraVecRel(b.id, b.nombre) + '</span>' +
      '<span class="vec-rel-nom">' + esc(b.nombre || b.id || '?') + '</span></button>' +
      badge +
      '</div>' +
      '<div class="vec-rel-estados">' + dirs + div + '</div>' +
      '</article>'
    );
  }

  function renderVecRelLista() {
    const list = $('[data-vec-rel-list]');
    if (!list) return;
    const rows = vecRelCache.filter(function (r) {
      if (vecRelPersona && (r.persona_a || {}).id !== vecRelPersona && (r.persona_b || {}).id !== vecRelPersona) return false;
      return vecRelMatchFiltro(r, vecRelFiltro);
    });
    if (!rows.length) {
      const quien = vecRelPersona ? nombreDe(vecRelPersona) : '';
      list.innerHTML = '<p class="lista-vacia vec-rel-vacio">' +
        (quien ? esc(quien) + ' no tiene nada que contar por ahora.' : 'Aqu\\u00ED no hay nada de nada todav\\u00EDa.') + '</p>';
      return;
    }
    list.innerHTML = rows.map(htmlVecRelCard).join('');
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

  
  function emoPillVecino(emo, genero) {
    const e = canonEmoId(emo);
    const label = textoAnimoFichaPill(e, genero);
    const icon = emoEmojiFicha(e);
    return '<span class="vecino-emo-pill vecino-emo-pill--' + e + '">' +
      '<span class="vecino-emo-pill-ico" aria-hidden="true">' + icon + '</span>' +
      '<span class="vecino-emo-pill-txt">' + esc(label) + '</span></span>';
  }

  function textoEmoVecinoSutil(emo, genero) {
    const e = canonEmoId(emo);
    if (e === 'neutro') return '';
    if (e === 'alegre') return 'está feliz';
    if (e === 'triste') return 'está triste';
    if (e === 'enfadado') return genero === 'mujer' ? 'está enfadada' : 'está enfadado';
    return '';
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

  function textoAnimoFichaPill(emo, genero) {
    const e = canonEmoId(emo);
    const labels = {
      neutro: 'NEUTRAL',
      alegre: 'FELIZ',
      triste: 'TRISTE',
      enfadado: genero === 'mujer' ? 'ENFADADA' : 'ENFADADO'
    };
    return labels[e] || String(e).toUpperCase();
  }


  function emoEmojiFicha(emo) {
    const map = { neutro: '😐', alegre: '😊', triste: '😢', enfadado: '😤' };
    return map[canonEmoId(emo)] || map.neutro;
  }

  function pintarAnimoFicha(vista) {
    const emo = canonEmoId(vista.estado_animo);
    const genero = vista && vista.genero;
    const txtEl = $('[data-ficha-animo-text]');
    const icoEl = $('[data-ficha-animo-ico]');
    const pillEl = $('[data-ficha-animo-pill]');
    const ringEl = $('[data-ficha-cara-ring]');
    if (txtEl) txtEl.textContent = textoAnimoFichaPill(emo, genero);
    if (icoEl) {
      icoEl.setAttribute('data-emo', emo);
      icoEl.innerHTML = '<span class="ficha-animo-emoji" aria-hidden="true">' + emoEmojiFicha(emo) + '</span>';
    }
    if (pillEl) {
      pillEl.setAttribute('data-emo', emo);
      pillEl.className = 'ficha-animo-pill ficha-animo-pill--' + emo;
    }
    if (ringEl) ringEl.setAttribute('data-emocion', emo);
    fichaAnimoExplicacion = vista.animo_explicacion || null;
    const showAnimoQ = !!fichaAnimoExplicacion || emo !== 'neutro';
    const qBtn = $('[data-ficha-animo-q]');
    if (qBtn) {
      qBtn.hidden = !showAnimoQ;
      qBtn.onclick = abrirAnimoModal;
    }
  const bindAnimoOpen = showAnimoQ ? abrirAnimoModal : null;
    if (txtEl) {
      txtEl.classList.toggle('is-clickable', showAnimoQ);
      txtEl.onclick = bindAnimoOpen;
      const pill = txtEl.closest('.ficha-animo-pill');
      if (pill) {
        pill.classList.toggle('is-clickable', showAnimoQ);
        pill.onclick = bindAnimoOpen;
      }
    }
    const animoRow = $('[data-ficha-animo-row]');
    if (animoRow) {
      animoRow.classList.toggle('is-clickable', showAnimoQ);
      animoRow.onclick = bindAnimoOpen;
    }
    if (icoEl) {
      icoEl.classList.toggle('is-clickable', showAnimoQ);
      icoEl.onclick = bindAnimoOpen;
    }
    cerrarAnimoOverlay();
  }

  function cerrarAnimoOverlay() {
    const overlay = $('[data-animo-overlay]');
    if (overlay) overlay.hidden = true;
  }

  function emoModalIcono(estadoId) {
    const map = { alegre: '😊', triste: '😢', enfadado: '😤', neutro: '😐' };
    return map[canonEmoId(estadoId)] || '😐';
  }

  function htmlAnimoModal(exp, nom) {
    const estadoTxt = esc(String(exp.texto_estado || '').toUpperCase());
    const causa = esc(exp.explicacion || '');
    const desde = esc(exp.desde_texto || '');
    const emoId = canonEmoId(exp.estado_id || '');
    const img = $('[data-ficha-img]') ? $('[data-ficha-img]').innerHTML : '';
    const consec = Array.isArray(exp.consecuencias) ? exp.consecuencias : [];
    let mientras = '';
    if (consec.length) {
      mientras = '<div class="animo-modal-mientras">' +
        '<span class="animo-modal-mientras-tit"><span class="animo-modal-mientras-ico" aria-hidden="true">!</span> Mientras siga así</span>' +
        '<div class="animo-modal-mientras-list">' +
        consec.map(function (c) {
          return '<span class="animo-modal-badge">' +
            '<span class="animo-modal-badge-ico" aria-hidden="true">' + esc(c.icono || '•') + '</span>' +
            esc(c.texto || '') + '</span>';
        }).join('') +
        '</div></div>';
    }
    const consejo = exp.consejo
      ? '<p class="animo-modal-hint">💡 ' + esc(exp.consejo) + '</p>'
      : '';
    return '<div class="animo-modal-top">' +
      '<div class="animo-modal-avatar">' + img + '</div>' +
      '<h3 class="animo-modal-tit">¿Qué le pasa a ' + esc(nom) + '?</h3>' +
      '</div>' +
      '<div class="animo-modal-pills">' +
      '<span class="animo-modal-estado animo-modal-estado--' + esc(emoId) + '">' +
      '<span class="animo-modal-estado-ico" aria-hidden="true">' + emoModalIcono(emoId) + '</span>' +
      estadoTxt + '</span>' +
      (desde ? '<span class="animo-modal-desde">🕐 ' + desde + '</span>' : '') +
      '</div>' +
      '<span class="animo-modal-ribbon animo-modal-ribbon--lav">¿Qué ha pasado?</span>' +
      '<div class="animo-modal-causa animo-modal-causa--illus">' +
      '<p>' + causa + '</p>' +
      '<span class="animo-modal-conflicto" aria-hidden="true"></span>' +
      '</div>' +
      mientras +
      '<button type="button" class="animo-modal-cta" data-animo-org>📅 Organizar un plan</button>' +
      '<button type="button" class="animo-modal-ghost" data-animo-diario>Ver en su diario</button>' +
      consejo;
  }

  function abrirAnimoModal() {
    const exp = fichaAnimoExplicacion;
    const overlay = $('[data-animo-overlay]');
    const body = $('[data-animo-body]');
    if (!exp || !overlay || !body) return;
    const nom = ($('[data-ficha-nombre]') && $('[data-ficha-nombre]').textContent) || '';
    body.innerHTML = htmlAnimoModal(exp, nom);
    const orgBtn = body.querySelector('[data-animo-org]');
    if (orgBtn) {
      orgBtn.onclick = function () {
        cerrarAnimoOverlay();
        abrirOrganizarConPreset({ a: fichaActualId });
      };
    }
    const diarioBtn = body.querySelector('[data-animo-diario]');
    if (diarioBtn) {
      if (exp.diario_evento_id) {
        diarioBtn.hidden = false;
        diarioBtn.onclick = function () {
          cerrarAnimoOverlay();
          abrirDiarioVecino(fichaActualId, exp.diario_evento_id);
        };
      } else {
        diarioBtn.hidden = true;
        diarioBtn.onclick = null;
      }
    }
    overlay.hidden = false;
  }


  let diarioVecinoCache = [];
  let diarioVecinoFiltro = 'todo';
  let diarioVecinoBusca = '';
  let diarioVecinoOrden = 'reciente';
  let diarioHighlightId = null;
  let diarioVolverCapa = 'ficha';

  function cerrarDiarioVecino() {
    setCapa(diarioVolverCapa || 'ficha');
  }

  function categoriaDiarioMeta(cat) {
    const c = String(cat || '').toLowerCase();
    if (c.indexOf('animo') >= 0 || c.indexOf('emoc') >= 0) {
      return { cls: 'pg-pill--red', txt: 'Ánimo', icon: '😤', grupo: 'cambios' };
    }
    if (c.indexOf('relac') >= 0 || c.indexOf('romance') >= 0 || c.indexOf('hito') >= 0) {
      return { cls: 'pg-pill--mustard', txt: 'Relación', icon: '💔', grupo: 'relaciones' };
    }
    if (c.indexOf('encuentro') >= 0) {
      return { cls: 'pg-pill--mustard', txt: 'Relación', icon: '💔', grupo: 'relaciones' };
    }
    if (c.indexOf('plan') >= 0) {
      return { cls: 'pg-pill--lavender', txt: 'Plan', icon: '💡', grupo: 'planes' };
    }
    if (c.indexOf('llegada') >= 0) {
      return { cls: 'pg-pill--green', txt: 'Historia', icon: '🏢', grupo: 'cambios' };
    }
    return { cls: 'pg-pill--tan', txt: 'Cambio', icon: '✦', grupo: 'cambios' };
  }

  function diarioEntradaMatchFiltro(e, filtro) {
    if (filtro === 'todo') return true;
    const meta = categoriaDiarioMeta(e.categoria || e.tipo || '');
    return meta.grupo === filtro;
  }

  function diarioEntradaMatchBusca(e, q) {
    if (!q) return true;
    const hay = [
      e.titulo || '',
      e.texto || '',
      (Array.isArray(e.consecuencias) ? e.consecuencias.join(' ') : '')
    ].join(' ').toLowerCase();
    return hay.indexOf(q) >= 0;
  }

  function actoresIdDiario(e, rid) {
    const actores = Array.isArray(e.actores) ? e.actores : [];
    const otro = actores.find(function (a) { return a !== rid; });
    return otro || '';
  }

  function nombreActorDiario(e, rid) {
    const otro = actoresIdDiario(e, rid);
    if (!otro) return '';
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const r = res[otro];
    return (r && r.identidad_publica && r.identidad_publica.nombre) || otro;
  }

  function entradaDiarioHtml(e, rid) {
    const meta = categoriaDiarioMeta(e.categoria || e.tipo || '');
    const eventoId = (e.origen && e.origen.evento_id) || e.evento_id || '';
    const actor = nombreActorDiario(e, rid);
    let html = '<article class="ficha-diario-entrada fdi-entrada" data-diario-evento="' + esc(eventoId) + '">' +
      '<span class="fdi-node" aria-hidden="true">' + meta.icon + '</span>' +
      '<div class="ficha-diario-card fdi-card" data-cat="' + esc(meta.txt) + '">' +
      '<div class="fdi-card-main">' +
      '<b class="ficha-diario-titulo">' + esc(e.titulo || '') + '</b>';
    if (actor) {
      const cara = tokenDe(actoresIdDiario(e, rid));
      html += '<span class="fdi-con">' +
        (cara ? '<img class="fdi-con-ava" src="' + esc(cara) + '" alt=""/>' : '') +
        esc(actor) + '</span>';
    }
    html += '</div>' +
      '<span class="ficha-diario-cat ' + meta.cls + '">' + esc(meta.txt) + '</span>' +
      '<span class="fdi-chev" aria-hidden="true">›</span>' +
      '</div></article>';
    return html;
  }

  function fdiDiaTone(idx) {
    return ['pink', 'lav', 'green', 'mustard'][idx % 4];
  }

  function pintarDiarioVecinoLista() {
    const list = $('[data-diario-list]');
    if (!list) return;
    const rid = fichaActualId;
    const q = String(diarioVecinoBusca || '').trim().toLowerCase();
    let entradas = (diarioVecinoCache || []).filter(function (e) {
      return diarioEntradaMatchFiltro(e, diarioVecinoFiltro) && diarioEntradaMatchBusca(e, q);
    });
    if (diarioVecinoOrden === 'antiguo') {
      entradas = entradas.slice().reverse();
    }
    if (!entradas.length) {
      list.innerHTML = '<p class="ficha-vacio ficha-ironico">Aún no ha pasado nada digno de página.</p>';
      return;
    }
    let html = '';
    let diaPrev = null;
    let toneIdx = -1;
    let inTl = false;
    entradas.forEach(function (e) {
      const diaNum = e.dia || '';
      const diaLbl = e.fecha_corta || ('D\u00eda ' + diaNum);
      const diaKey = String(diaNum) + '|' + diaLbl;
      if (diaKey !== diaPrev) {
        if (inTl) html += '</div></div>';
        toneIdx += 1;
        html += '<div class="fdi-dia-grupo" data-tone="' + fdiDiaTone(toneIdx) + '">' +
          '<span class="ficha-diario-dia fdi-dia-lbl">' + esc(diaLbl) + '</span>' +
          '<div class="ficha-diario-tl fdi-tl">';
        diaPrev = diaKey;
        inTl = true;
      }
      html += entradaDiarioHtml(e, rid);
    });
    if (inTl) html += '</div></div>';
    list.innerHTML = html;
    if (diarioHighlightId) {
      const sel = '[data-diario-evento="' + String(diarioHighlightId).replace(/"/g, '\\"') + '"]';
      const dest = list.querySelector(sel);
      if (dest) {
        dest.classList.add('is-destacada');
        try { dest.scrollIntoView({ block: 'center' }); } catch (err) {}
      }
      diarioHighlightId = null;
    }
  }

  function pintarDiarioVecinoHero(nom, img, total) {
    const hero = $('[data-diario-hero]');
    if (!hero) return;
    hero.innerHTML = '<div class="ficha-diario-head-row fdi-head-row">' +
      '<div class="ficha-diario-head-avatar fdi-head-ava">' + img + '</div>' +
      '<div class="fdi-head-txt">' +
      '<b class="ficha-diario-head-tit fdi-head-tit">Diario de ' + esc(nom) + '</b>' +
      '<p class="ficha-diario-head-sub fdi-head-sub">Su historia en el pueblo</p>' +
      '</div>' +
      '<span class="ficha-diario-count fdi-count">📓 ' + total + ' recuerdos</span></div>';
  }

  function syncDiarioVecinoFiltros() {
    $$('[data-diario-filt]').forEach(function (b) {
      const on = (b.getAttribute('data-diario-filt') || '') === diarioVecinoFiltro;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    const ord = $('[data-diario-orden]');
    if (ord) {
      ord.textContent = diarioVecinoOrden === 'antiguo' ? '☰ Más antiguo' : '☰ Más reciente';
    }
    const busca = $('[data-diario-busca]');
    if (busca && busca.value !== diarioVecinoBusca) busca.value = diarioVecinoBusca;
  }

  async function abrirDiarioVecino(rid, highlightEventoId) {
    if (!rid) return;
    const root = $('.play-root');
    diarioVolverCapa = (root && root.getAttribute('data-capa')) || 'ficha';
    const r = await api('residente.diario', { residente_id: rid }, 'GET');
    if (!r.ok) {
      toast(r.mensaje_ui || r.error || 'No se pudo abrir el diario.');
      return;
    }
    const nom = ($('[data-ficha-nombre]') && $('[data-ficha-nombre]').textContent) || '';
    const img = $('[data-ficha-img]') ? $('[data-ficha-img]').innerHTML : '';
    diarioVecinoCache = Array.isArray(r.entradas) ? r.entradas : [];
    diarioHighlightId = highlightEventoId || null;
    pintarDiarioVecinoHero(nom, img, diarioVecinoCache.length);
    syncDiarioVecinoFiltros();
    pintarDiarioVecinoLista();
    setCapa('ficha_diario');
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
    if (typeof window !== 'undefined' && window.AHTHobbyIcons && window.AHTHobbyIcons.has(id)) {
      return window.AHTHobbyIcons.svg(id);
    }
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

  function pintarLoQueSabes(vista) {
    const sec = $('[data-ficha-sabes]');
    const box = $('[data-ficha-sabes-body]');
    if (!sec || !box) return;
    const g = (vista && vista.pistas_grupos) || {};
    const animas = Array.isArray(g.animas) ? g.animas : [];
    const disgustas = Array.isArray(g.disgustas) ? g.disgustas : [];
    if (!animas.length && !disgustas.length) {
      box.innerHTML = '';
      sec.hidden = true;
      return;
    }
    let html = '';
    if (animas.length) {
      html += '<p class="ficha-pref-line"><span class="ficha-sabes-ico">\u2764\uFE0F</span>Le anima: '
        + esc(animas.map(function (a) { return a.etiqueta || a.id || ''; }).filter(Boolean).join(', '))
        + '</p>';
    }
    if (disgustas.length) {
      html += '<p class="ficha-pref-line"><span class="ficha-sabes-ico">\uD83D\uDCA2</span>No le gusta: '
        + esc(disgustas.map(function (d) { return d.etiqueta || d.id || ''; }).filter(Boolean).join(', '))
        + '</p>';
    }
    box.innerHTML = html;
    sec.hidden = false;
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
    if (rel.etiqueta_social_ui) return String(rel.etiqueta_social_ui);
    const map = {
      desconocido: 'Desconocido',
      conocido: 'Conocido',
      amigo: 'Amigo',
      buena_amistad: 'Buena amistad',
      muy_buena_amistad: 'Buena amistad',
      cae_mal: 'Cae mal',
      cae_bien: 'Le cae bien',
      buen_amigo: 'Buen amigo',
      mejor_amigo: 'Mejor amigo'
    };
    return map[rel.etiqueta_social] || String(rel.etiqueta_social || '—').replace(/_/g, ' ');
  }

  function barRelPct(rel) {
    if (!rel) return 8;
    if (typeof rel.social_bar_pct === 'number') return rel.social_bar_pct;
    if (rel.etiqueta_vinculo === 'pareja') return 96;
    if (rel.etiqueta_vinculo === 'crisis') return 52;
    if (rel.etiqueta_vinculo === 'ex_pareja') return 38;
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
    const edadEl = $('[data-ficha-edad]');
    if (edadEl) {
      var edadVal = vista.edad != null ? vista.edad : (f.identidad && f.identidad.edad);
      if (edadVal != null && edadVal !== '') {
        edadEl.textContent = String(edadVal) + ' a\u00f1os';
        edadEl.hidden = false;
      } else {
        edadEl.textContent = '';
        edadEl.hidden = true;
      }
    }
    const trabajoEl = $('[data-ficha-trabajo]');
    if (trabajoEl) {
      var t = vista.trabajo || {};
      var linea = '';
      if (t.desempleado) {
        linea = '\uD83D\uDCBC ' + (t.linea_principal || 'Desempleado/a');
      } else if (t.linea_principal) {
        linea = '\uD83D\uDCBC ' + t.linea_principal;
        if (t.linea_horario) linea += '\n' + t.linea_horario;
      } else if (vista.ocupacion) {
        linea = '\uD83D\uDCBC ' + vista.ocupacion;
      }
      if (linea) {
        trabajoEl.textContent = linea;
        trabajoEl.hidden = false;
      } else {
        trabajoEl.textContent = '';
        trabajoEl.hidden = true;
      }
    }
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
    pintarLoQueSabes(vista);
    const relBox = $('[data-ficha-relaciones]');
    const relMasBtn = $('[data-ficha-rel-mas]');
    fichaRelCache = relacionesConocidas(f);
    pintarRelacionesEn(relBox, fichaRelCache, 2);
    if (relMasBtn) {
      if (fichaRelCache.length > 2) {
        relMasBtn.hidden = false;
        relMasBtn.textContent = 'Ver m\u00e1s relaciones';
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
        planBox.innerHTML = '<p class="ficha-vacio ficha-ironico">\u00abSu agenda est\u00e1 sospechosamente tranquila.\u00bb</p>';
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
        abrirOrganizarConPreset({ a: id });
      };
    }
    // Regalos F2/F3: entrada REGALAR desde la ficha (mismo endpoint que Inventario).
    const regBtn = $('[data-ficha-regalar]');
    if (regBtn) {
      regBtn.onclick = function () {
        abrirRegalosDesdeFicha(id, nom);
      };
    }
    // Regalos F2/F3: aprecio del vecino hacia Celestine como banda de texto.
    const apEl = $('[data-ficha-aprecio]');
    if (apEl) {
      const ap = vista.aprecio_celeste || null;
      const txt = ap && ap.texto ? String(ap.texto) : '';
      apEl.textContent = txt;
      apEl.hidden = !txt;
      apEl.setAttribute('data-aprecio-banda', ap && ap.banda ? ap.banda : '');
    }
    syncFichaNav();
  }

  function fichaNavBotones() {
    const capa = document.querySelector('.capa-ficha');
    if (!capa) return { prev: null, next: null };
    return {
      prev: capa.querySelector('[data-ficha-nav-prev]'),
      next: capa.querySelector('[data-ficha-nav-next]')
    };
  }

  function fichaIndiceEnLista(ids) {
    const cur = String(fichaActualId || '');
    for (let i = 0; i < ids.length; i++) {
      if (String(ids[i]) === cur) return i;
    }
    return -1;
  }

  function vecinoFichaCircular(ids, delta) {
    const n = ids.length;
    if (n < 2) return null;
    const idx = fichaIndiceEnLista(ids);
    const base = idx >= 0 ? idx : (delta > 0 ? 0 : n - 1);
    return ids[(base + delta + n) % n];
  }

  function syncFichaNav() {
    const ids = vecinosIdsOrdenados();
    const ok = ids.length > 1;
    const nav = fichaNavBotones();
    // Navegación circular: con 2+ vecinos ambas flechas siempre activas
    if (nav.prev) nav.prev.disabled = !ok;
    if (nav.next) nav.next.disabled = !ok;
  }

  var fichaNavegando = false;

  async function navegarFicha(delta) {
    if (fichaNavegando) return;
    const ids = vecinosIdsOrdenados();
    const destino = vecinoFichaCircular(ids, delta);
    if (!destino) return;
    fichaNavegando = true;
    try {
      await abrirFicha(destino);
    } finally {
      fichaNavegando = false;
    }
  }

  async function abrirFicha(id, opts) {
    opts = opts || {};
    const rid = String(id || '');
    if (!rid) return;
    let r = await api('residente.ficha', { residente_id: rid }, 'GET');
    if (!r.ok && !opts._noRetry) {
      const err = String(r.error || '');
      if (err === 'excepcion' || /residente/.test(err) || /no_encontrad/i.test(String(r.mensaje || ''))) {
        await refresh();
        renderVecinos();
        r = await api('residente.ficha', { residente_id: rid }, 'GET');
      }
    }
    if (!r.ok) {
      toast(r.mensaje_ui || r.mensaje || r.error || 'No se pudo abrir la ficha de este vecino.');
      return;
    }
    if (r.tutorial) pintarTutorialMotor(r.tutorial);
    const f = r.ficha || {};
    const vista = f.vista_play || f;
    try {
      pintarFicha(rid, f, vista);
      setCapa('ficha');
    } catch (err) {
      console.error('pintarFicha', err);
      toast('No se pudo mostrar la ficha de este vecino.');
    }
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

  function mensajitosPendientesCount(msgs) {
    return mensajitosCartas(msgs).filter(function (m) {
      return (m.estado || '') === 'pendiente';
    }).length;
  }

  function actualizarBuzonLeerTodosBtn(msgs) {
    const btn = $('[data-buzon-leer-todos]');
    if (!btn) return;
    const n = mensajitosPendientesCount(msgs);
    btn.hidden = n === 0;
    btn.disabled = n === 0;
  }

  async function marcarTodosMensajitosLeidos() {
    const btn = $('[data-buzon-leer-todos]');
    if (btn && btn.disabled) return;
    if (btn) btn.disabled = true;
    const popAbierto = mensajitosPopAbierto;
    const r = await api('buzon.leer_todos', {});
    if (!r.ok) {
      toast(r.mensaje_ui || 'No se pudieron marcar los mensajes.');
      actualizarBuzonLeerTodosBtn(cacheBuzon);
      return;
    }
    await refresh();
    if (popAbierto) abrirMensajitosPop();
    if (r.tutorial) pintarTutorialMotor(r.tutorial);
  }


  function renderBuzon(msgs) {
    cacheBuzon = msgs || [];
    const box = $('[data-buzon-list]');
    if (!box) return;
    box.innerHTML = '';
    renderMensajitosPop(msgs);
    actualizarBuzonLeerTodosBtn(msgs);
    const cartasTodas = mensajitosOrdenados(msgs);
    const nuevos = cartasTodas.filter(function (m) { return (m.estado || '') === 'pendiente'; });
        const tabCount = $('[data-buzon-tab-count]');
    if (tabCount) {
      tabCount.textContent = String(nuevos.length);
      tabCount.hidden = nuevos.length === 0;
    }
    let filtro = box.getAttribute('data-buzon-filtro') || 'nuevos';
    document.querySelectorAll('[data-buzon-tab]').forEach(function (tab) {
      tab.onclick = function () {
        filtro = tab.getAttribute('data-buzon-tab') || 'nuevos';
        box.setAttribute('data-buzon-filtro', filtro);
        document.querySelectorAll('[data-buzon-tab]').forEach(function (t) {
          const on = t === tab;
          t.classList.toggle('is-on', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        renderBuzon(cacheBuzon);
        const active = document.querySelector('[data-buzon-tab="' + filtro + '"]');
        if (active) { active.classList.add('is-on'); active.setAttribute('aria-selected', 'true'); }
      };
    });
    const cartas = filtro === 'todos' ? cartasTodas : nuevos;
    if (!cartas.length) {
      box.innerHTML = '<p class="lista-vacia buzon-vacio">No hay mensajes por ahora. Cuando llegue algo, lo verás aquí.</p>';
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
      const plazo = (m.estado_pueblo === 'caducada') ? '' : (m.plazo_humano || '');
      const cuando = mensajitoCuandoLabel(m);
      const flagHtml = !leido
        ? '<span class="carta-flag carta-flag--nuevo" aria-hidden="true">Nuevo</span>'
        : '<span class="carta-flag carta-flag--visto"><span class="carta-flag-ico" aria-hidden="true">\u2606</span><span>Visto</span></span>';
      const accionesDecision = htmlAccionesMensajito(m);
      let accionesHtml = '';
      if (accionesDecision) {
        accionesHtml = accionesDecision;
      } else if (m.preset_organizar && (m.estado_pueblo || 'pendiente') === 'pendiente' && (m.estado || '') === 'pendiente') {
        accionesHtml = '<div class="acciones-msg">' +
          '<button type="button" class="carta-cta carta-cta--abrir" data-carta-organizar="1">Organizar</button>' +
          '</div>';
      } else if (mensajitoDestinoFicha(m)) {
        const ctaLabel = leido ? 'Ver' : 'Abrir';
        const ctaClass = leido ? 'carta-cta carta-cta--ver' : 'carta-cta carta-cta--abrir';
        accionesHtml = '<div class="acciones-msg">' +
          '<button type="button" class="' + ctaClass + '" data-carta-cta="1">' + ctaLabel + '</button>' +
          '</div>';
      }
      const metaBits = [];
      if (st.txt) metaBits.push('<span class="sello-estado">' + esc(st.txt) + '</span>');
      if (plazo) metaBits.push('<span class="plazo">' + esc(plazo) + '</span>');
      art.innerHTML = flagHtml +
        '<div class="carta-inner">' + htmlAvatarMensajito(m, nombre, 'carta-avatar') +
        '<div class="carta-copy">' +
        '<div class="carta-head">' +
        (nombre ? '<div class="de">' + esc(nombre) + '</div>' : '') +
        (cuando ? '<div class="carta-cuando">' + esc(cuando) + '</div>' : '') +
        '</div>' +
        '<p class="cuerpo">' + esc(cuerpo) + '</p>' +
        '</div></div>' +
        '<div class="carta-pie">' +
        '<div class="carta-pie-meta">' + metaBits.join('') + '</div>' +
        '<div class="carta-pie-cta">' + accionesHtml + '</div>' +
        '</div>';
      art.querySelectorAll('[data-carta-cta]').forEach(function (btn) {
        btn.addEventListener('click', async function (ev) {
          ev.stopPropagation();
          if (!mensajitoEstaLeido(m)) {
            await marcarMensajitoLeido(m);
          } else {
            const rid = remitenteIdDe(m);
            if (rid) await abrirFicha(rid);
          }
          await refresh();
        });
      });
      art.querySelectorAll('[data-carta-organizar]').forEach(function (btn) {
        btn.addEventListener('click', async function (ev) {
          ev.stopPropagation();
          if (!mensajitoEstaLeido(m)) await marcarMensajitoLeido(m);
          abrirOrganizarConPreset(m.preset_organizar);
        });
      });
      art.addEventListener('click', async function (ev) {
        if (ev.target.closest('button') || ev.target.closest('.msg-leido-toggle')) return;
        if (!mensajitoEstaLeido(m) && !mensajitoTieneAccionReal(m) && !mensajitoDestinoFicha(m)) {
          await marcarMensajitoLeido(m);
          await refresh();
        }
      });
      wireAccionesMensajito(art, m);
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

  function cotiCatSvg(catId) {
    const k = String(catId || 'encuentro').toLowerCase();
    if (k === 'pueblo') {
      return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><path d="M6 14l10-7 10 7v12H6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 26v-8h8v8" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    if (k === 'encuentro') {
      return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><path d="M9 12h14l-2 10H11z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 12h16" stroke="currentColor" stroke-width="1.5"/><path d="M12 8c0-2 1.5-3 4-3s4 1 4 3" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
    if (k === 'descubrimiento') {
      return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><circle cx="14" cy="14" r="6" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M19 19l6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    }
    if (k === 'romance') {
      return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 25s-8-5.5-8-11.5C8 9.5 11 7 14 9c1.2.9 2 2.1 2 2.1s.8-1.2 2-2.1c3-2 6 0.5 6 4.5C24 19.5 16 25 16 25z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';
    }
    if (k === 'drama') {
      return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><path d="M18 5l-2 9h6l-8 13 2-10h-6z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';
    }
    if (k === 'relacion') {
      return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><path d="M8 16h8M16 16h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 16l3-3M8 16l3 3M24 16l-3-3M24 16l-3 3" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
    }
    if (k === 'coincidencias') {
      return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><path d="M10 16c0-4 2.5-7 6-7s6 3 6 7-2.5 7-6 7-6-3-6-7z" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M22 16c0-4 2.5-7 6-7s6 3 6 7-2.5 7-6 7-6-3-6-7z" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
    return '<svg class="coti-svg" viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
  }

  function htmlCotiCat(catId, etiqueta) {
    const id = String(catId || 'encuentro').toLowerCase();
    const lab = etiqueta || 'Cotilleo';
    return '<span class="coti-cat coti-cat--' + esc(id) + ' coti-cat-sello" title="' + esc(lab) + '" aria-label="' + esc(lab) + '">' +
      cotiCatSvg(id) + '</span>';
  }

  function cotiEtiquetaTiempo(e, bucket) {
    if (e.fecha_corta) return e.fecha_corta;
    if (bucket === 'ayer') return 'Ayer';
    if (bucket === 'hoy') return '';
    return e.dia ? ('día ' + e.dia) : '';
  }

  function htmlCotiAvatares(actores) {
    const ids = (actores && actores.length) ? actores.slice(0, 2) : [];
    if (!ids.length) return '<span class="coti-item-sin-cara" aria-hidden="true">…</span>';
    return ids.map(function (id) {
      const img = tokenDe(id);
      const nom = nombreDe(id);
      const ini = (String(nom).charAt(0) || '?');
      return '<span class="coti-item-cara cara-token" role="button" tabindex="0" data-residente="' + esc(id) +
        '" aria-label="Ver ficha de ' + esc(nom) + '" title="' + esc(nom) + '">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="coti-item-ini">' + esc(ini) + '</span>') +
        '</span>';
    }).join('');
  }

  function htmlCotiItem(e, bucket) {
    const cat = String(e.categoria || 'encuentro').toLowerCase();
    const etiqueta = e.categoria_etiqueta || 'Cotilleo';
    const dest = e.destacado === true ? ' coti-item--destacado' : '';
    const cuando = cotiEtiquetaTiempo(e, bucket);
    return '<article class="coti-item' + dest + '">' +
      '<span class="coti-item-tape coti-item-tape-l" aria-hidden="true"></span>' +
      '<span class="coti-item-tape coti-item-tape-r" aria-hidden="true"></span>' +
      (bucket === 'hoy' ? '<span class="coti-item-hoy">HOY</span>' : '') +
      '<div class="coti-item-col">' +
      '<div class="coti-item-avatares">' + htmlCotiAvatares(e.actores) + '</div>' +
      htmlCotiCat(cat, etiqueta) +
      '</div>' +
      '<div class="coti-item-cuerpo">' +
      '<p class="coti-item-txt">' + esc(e.texto || '') + '</p>' +
      (cuando ? '<p class="coti-item-cuando">' + esc(cuando) + '</p>' : '') +
      '</div></article>';
  }

  let cotiCache = { hoy: [], ayer: [], viejos: [] };
  let cotiFiltroActivo = '';
  let cotiSinVerPrev = null;

  function cotiTodosItems(coti) {
    const items = [];
    ['hoy', 'ayer', 'viejos'].forEach(function (bucket) {
      (coti && coti[bucket] ? coti[bucket] : []).forEach(function (e) {
        items.push(Object.assign({}, e, { _bucket: bucket }));
      });
    });
    return items;
  }


  function cotiSinVerDe(diario) {
    const d = diario || cacheDiario;
    return Math.max(0, Number(d && d.cotilleo && d.cotilleo.importantes_sin_ver) || 0);
  }

  function cotiIdsVisiblesDe(coti) {
    const ids = [];
    ['hoy', 'ayer', 'viejos'].forEach(function (bucket) {
      ((coti && coti[bucket]) || []).forEach(function (e) {
        if (e && e.destacado === true && e.id) ids.push(String(e.id));
      });
    });
    return ids;
  }

  function cotiBadgeNuevosTxt(n) {
    n = Math.max(0, Number(n) || 0);
    if (n <= 0) return '';
    return String(n) + ' nuevo' + (n === 1 ? '' : 's');
  }

  function pulsoCotilleoBadge(badge) {
    badge.classList.remove('is-pulso');
    void badge.offsetWidth;
    badge.classList.add('is-pulso');
    badge.addEventListener('animationend', function () {
      badge.classList.remove('is-pulso');
    }, { once: true });
  }

  function actualizarCotiBadgesUI() {
    const sinVer = cotiSinVerDe(cacheDiario);
    const subio = cotiSinVerPrev !== null && sinVer > cotiSinVerPrev;
    cotiSinVerPrev = sinVer;

    document.querySelectorAll('.obj-cotilleo-par').forEach(function (cotiCard) {
      cotiCard.classList.toggle('is-aviso-importante', sinVer > 0);
    });

    inicioAll('[data-cotilleo-badge]').forEach(function (homeBadge) {
      if (sinVer > 0) {
        homeBadge.textContent = cotiBadgeNuevosTxt(sinVer);
        homeBadge.hidden = false;
        if (subio) pulsoCotilleoBadge(homeBadge);
      } else {
        homeBadge.textContent = '';
        homeBadge.hidden = true;
      }
    });

    const modalBadge = $('[data-coti-count]');
    if (modalBadge) {
      if (sinVer > 0) {
        modalBadge.textContent = cotiBadgeNuevosTxt(sinVer);
        modalBadge.hidden = false;
      } else {
        modalBadge.textContent = '';
        modalBadge.hidden = true;
      }
    }
  }

  async function marcarCotilleoVisto() {
    const sinVerCache = cotiSinVerDe(cacheDiario);
    if (!sinVerCache) return;
    const ids = cotiIdsVisiblesDe(cotiCache);
    try {
      const r = await api('diario.cotilleo_visto', { ids: ids });
      if (!r || !r.ok) return;
      const restan = Math.max(0, Number(r.importantes_sin_ver) || 0);
      if (cacheDiario && cacheDiario.cotilleo) cacheDiario.cotilleo.importantes_sin_ver = restan;
      cotiSinVerPrev = restan;
      actualizarCotiBadgesUI();
    } catch (e) {}
  }

  function renderCotilleoFiltros(items) {
    const box = $('[data-coti-filtros]');
    if (!box) return;
    const cats = {};
    (items || []).forEach(function (e) {
      const id = e.categoria || 'encuentro';
      if (!cats[id]) {
        cats[id] = {
          id: id,
          etiqueta: e.categoria_etiqueta || 'Cotilleo',
        };
      }
    });
    const keys = Object.keys(cats);
    if (keys.length <= 1) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = keys.map(function (id) {
      const c = cats[id];
      const on = cotiFiltroActivo === id ? ' is-on' : '';
      return '<button type="button" class="coti-filtro coti-cat--' + esc(id) + on + '" data-coti-filtro="' + esc(id) + '" aria-label="' + esc(c.etiqueta) + '" title="' + esc(c.etiqueta) + '">' +
        '<span class="coti-cat" aria-hidden="true">' + cotiCatSvg(id) + '</span></button>';
    }).join('');
  }

  function renderCotilleoLista(coti) {
    const box = $('[data-coti-list]');
    if (!box) return;
    const items = cotiTodosItems(coti || cotiCache);
    const filtered = cotiFiltroActivo
      ? items.filter(function (e) { return (e.categoria || 'encuentro') === cotiFiltroActivo; })
      : items;
    box.innerHTML = '';
    if (!filtered.length) {
      box.innerHTML = '<p class="coti-vacio">' + (items.length ? 'Nada de este tipo por ahora.' : 'Hoy el pueblo no ha dado titular.') + '</p>';
      return;
    }
    filtered.forEach(function (e) {
      box.insertAdjacentHTML('beforeend', htmlCotiItem(e, e._bucket));
    });
  }

  function renderCotilleo(coti) {
    cotiCache = coti || { hoy: [], ayer: [], viejos: [] };
    if (cacheDiario && cacheDiario.cotilleo) {
      cacheDiario.cotilleo.hoy = cotiCache.hoy || [];
      cacheDiario.cotilleo.ayer = cotiCache.ayer || [];
      cacheDiario.cotilleo.viejos = cotiCache.viejos || [];
      if (typeof cotiCache.importantes_sin_ver === 'number') {
        cacheDiario.cotilleo.importantes_sin_ver = cotiCache.importantes_sin_ver;
      }
    }
    const items = cotiTodosItems(cotiCache);
    renderCotilleoFiltros(items);
    renderCotilleoLista(cotiCache);
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
    return (org.sel || []).filter(Boolean);
  }

  function orgModo() {
    return orgSeleccionados().length <= 1 ? 'solo' : 'pareja';
  }

  function syncOrgTipoDesdeSeleccion() {
    if (orgModo() === 'solo') {
      org.tipo = 'individual';
    } else if (org.tipo === 'individual') {
      org.tipo = '';
    }
  }

  function orgIdsDesdePreset(preset) {
    const out = [];
    if (preset && preset.a) out.push(preset.a);
    if (preset && preset.b && preset.b !== preset.a) out.push(preset.b);
    if (preset && preset.c && preset.c !== preset.a && preset.c !== preset.b) out.push(preset.c);
    return out.slice(0, ORG_MAX_VECINOS);
  }

  function actualizarOrgPickerHint() {
    const hint = $('[data-org-picker-hint]');
    if (!hint) return;
    const n = orgSeleccionados().length;
    hint.classList.remove('is-limit');
    if (!n) {
      hint.textContent = 'Elige hasta ' + ORG_MAX_VECINOS + ' vecinos.';
      return;
    }
    if (n === 1) hint.textContent = 'Plan en solitario. Puedes a\u00f1adir hasta ' + ORG_MAX_VECINOS + ' vecinos.';
    else if (n < ORG_MAX_VECINOS) hint.textContent = n + ' vecinos elegidos. Puedes a\u00f1adir uno m\u00e1s.';
    else hint.textContent = ORG_MAX_VECINOS + ' vecinos elegidos (m\u00e1ximo).';
  }

  function actualizarOrgModoEstado() {
    const el = $('[data-org-modo-estado]');
    if (!el) return;
    const n = orgSeleccionados().length;
    if (!n) {
      el.hidden = true;
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.textContent = n === 1 ? 'Plan en solitario' : 'Plan acompa\u00f1ado';
  }

  function orgParticipantesListos() {
    var parts = orgSeleccionados();
    if (orgModo() === 'solo') return parts.length >= 1;
    return parts.length >= 2;
  }

  function mensajeOrgParticipantesPendientes() {
    if (orgModo() === 'solo') {
      return orgSeleccionados().length ? '' : 'Elige a qui\u00e9n va el plan.';
    }
    var n = orgSeleccionados().length;
    if (n === 0) return 'Elige al menos dos vecinos.';
    if (n === 1) return 'Elige un acompa\u00f1ante para continuar.';
    return '';
  }

  function actualizarOrgCrearBtn() {
    var btn = $('[data-org-go]');
    if (!btn) return;
    var val = validarOrgForm();
    btn.disabled = !val.ok;
    btn.setAttribute('aria-disabled', val.ok ? 'false' : 'true');
  }

  function setOrgHorasHint(txt, show) {
    var hintEl = document.querySelector('[data-org-horas-hint]');
    if (!hintEl) return;
    if (show && txt) {
      hintEl.textContent = txt;
      hintEl.hidden = false;
    } else {
      hintEl.textContent = '';
      hintEl.hidden = true;
    }
  }

  function mensajeErrorOrgApi(r, fallback) {
    if (!r) return fallback;
    if (r.mensaje_ui) return r.mensaje_ui;
    var err = String(r.error || '');
    if (err === 'participantes_requeridos' || err === 'participantes_insuficientes') {
      if (orgModo() === 'solo') return 'Elige a qui\u00e9n va el plan.';
      var pend = mensajeOrgParticipantesPendientes();
      return pend || 'Elige al menos dos vecinos.';
    }
    if (err === 'lugar_requerido') return 'Elige un lugar.';
    if (err === 'dia_requerido' || err === 'hora_requerida') return 'Elige cuándo quedar.';
    return fallback;
  }

  function validarOrgForm() {
    org.lugar = $('[data-org-lugar]').value;
    org.dia = parseInt($('[data-org-dia]').value, 10);
    org.hora = parseInt($('[data-org-hora]').value, 10);
    const parts = orgSeleccionados();
    if (orgModo() === 'solo') {
      if (!parts.length) return { ok: false, msg: 'Elige a qui\u00e9n va el plan.' };
      if (!org.lugar) return { ok: false, msg: 'Elige un lugar.' };
      if (!org.dia || !org.hora) return { ok: false, msg: 'Elige cu\u00e1ndo quedar.' };
      return { ok: true };
    }
    if (parts.length < 2) return { ok: false, msg: 'Elige al menos dos vecinos.' };
    if (new Set(parts).size !== parts.length) return { ok: false, msg: 'Elige a personas distintas.' };
    if (!org.tipo) return { ok: false, msg: 'Elige qu\u00e9 busc\u00e1is.' };
    if (!org.lugar) return { ok: false, msg: 'Elige un lugar.' };
    if (!org.dia || !org.hora) return { ok: false, msg: 'Elige cu\u00e1ndo quedar.' };
    return { ok: true };
  }
  function feedbackOrgPickerLimite() {
    const hint = $('[data-org-picker-hint]');
    const strip = $('[data-org-picker]');
    if (hint) {
      hint.classList.add('is-limit');
      hint.textContent = 'M\u00e1ximo ' + ORG_MAX_VECINOS + ' vecinos por plan.';
    }
    if (strip) {
      strip.classList.add('is-limit');
      window.setTimeout(function () { strip.classList.remove('is-limit'); }, 420);
    }
  }

  function toggleOrgPicker(id) {
    if (!id) return;
    limpiarOrgAviso();
    const sel = orgSeleccionados();
    const idx = sel.indexOf(id);
    if (idx >= 0) {
      org.sel = sel.filter(function (x) { return x !== id; });
    } else if (sel.length >= ORG_MAX_VECINOS) {
      feedbackOrgPickerLimite();
      return;
    } else {
      org.sel = sel.concat([id]);
    }
    syncOrgTipoDesdeSeleccion();
    pintarOrgPicker();
    actualizarOrgPickerHint();
    actualizarOrgModoEstado();
    refreshTipos();
    refreshOrgHoras();
    actualizarOrgCrearBtn();
  }


  function ajustarOrgDdMenu(box) {
    var menu = box.querySelector('.org-dd-menu');
    var trigger = box.querySelector('.org-dd-trigger');
    var capa = box.closest('.capa-organizar');
    var body = capa && capa.querySelector('.org-body');
    if (!menu || !trigger) return;
    box.classList.remove('org-dd--flip');
    if (capa) capa.classList.add('org-dd-menu-open');
    if (body) body.classList.add('org-dd-menu-open');
    var capaRect = capa ? capa.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
    var tr = trigger.getBoundingClientRect();
    menu.hidden = false;
    var mh = Math.min(menu.scrollHeight || 0, Math.round(window.innerHeight * 0.4));
    if (!mh) mh = menu.offsetHeight || 120;
    var spaceBelow = capaRect.bottom - tr.bottom;
    var spaceAbove = tr.top - capaRect.top;
    if (spaceBelow < mh + 10 && spaceAbove > spaceBelow) box.classList.add('org-dd--flip');
  }

  function cerrarOrgDds() {
    var capa = $('.capa-organizar');
    var body = capa && capa.querySelector('.org-body');
    if (capa) capa.classList.remove('org-dd-menu-open');
    if (body) body.classList.remove('org-dd-menu-open');
    $$('.org-dd.is-open').forEach(function (dd) {
      dd.classList.remove('is-open', 'org-dd--flip');
      var trig = dd.querySelector('.org-dd-trigger');
      if (trig) trig.setAttribute('aria-expanded', 'false');
      var menu = dd.querySelector('.org-dd-menu');
      if (menu) menu.hidden = true;
    });
  }

  function pintarOrgDropdown(kind, options, value, onChange) {
    var map = {
      lugar: { box: '[data-org-dd-lugar]', native: '[data-org-lugar]' },
      dia: { box: '[data-org-dd-dia]', native: '[data-org-dia]' },
      hora: { box: '[data-org-dd-hora]', native: '[data-org-hora]' }
    };
    var cfg = map[kind];
    if (!cfg) return;
    var box = $(cfg.box);
    var native = $(cfg.native);
    if (!box) return;
    var opts = Array.isArray(options) ? options : [];
    var valStr = value === null || value === undefined ? '' : String(value);
    var label = 'Elegir…';
    opts.forEach(function (opt) {
      var optVal = opt.value === null || opt.value === undefined ? '' : String(opt.value);
      if (valStr !== '' && optVal === valStr) label = opt.label || optVal;
    });
    box.innerHTML = '';
    box.className = 'org-dd' + (kind === 'dia' ? ' org-dd--dia' : (kind === 'hora' ? ' org-dd--hora' : ''));
    if (native) {
      native.innerHTML = '';
      opts.forEach(function (opt) {
        var optVal = opt.value === null || opt.value === undefined ? '' : String(opt.value);
        var o = document.createElement('option');
        o.value = optVal;
        o.textContent = opt.label || optVal;
        if (opt.disabled) o.disabled = true;
        if (valStr !== '' && optVal === valStr) o.selected = true;
        native.appendChild(o);
      });
      if (valStr !== '') native.value = valStr;
    }
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'org-dd-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = '<span class="org-dd-label">' + esc(label) + '</span>';
    var menu = document.createElement('ul');
    menu.className = 'org-dd-menu capa-scroll';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    var actionable = opts.filter(function (opt) { return !opt.disabled; });
    var canInteract = Boolean(onChange) && actionable.length > 0;
    if (!canInteract) {
      trigger.disabled = true;
      box.classList.add('is-disabled');
      if (!actionable.length) trigger.querySelector('.org-dd-label').textContent = 'Sin opciones';
    } else {
      box.classList.remove('is-disabled');
      opts.forEach(function (opt) {
        var optVal = opt.value === null || opt.value === undefined ? '' : String(opt.value);
        var li = document.createElement('li');
        li.className = 'org-dd-opt';
        if (opt.disabled) li.className += ' is-disabled';
        else if (valStr !== '' && optVal === valStr) li.className += ' is-on';
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', li.classList.contains('is-on') ? 'true' : 'false');
        li.textContent = opt.label || optVal;
        if (!opt.disabled && onChange) {
          li.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            if (native) {
              native.value = optVal;
              native.dispatchEvent(new Event('change', { bubbles: true }));
            }
            onChange(opt.value);
            cerrarOrgDds();
            pintarOrgDropdown(kind, opts, opt.value, onChange);
          });
        }
        menu.appendChild(li);
      });
      trigger.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var open = box.classList.contains('is-open');
        cerrarOrgDds();
        if (!open) {
          box.classList.add('is-open');
          menu.hidden = false;
          trigger.setAttribute('aria-expanded', 'true');
          ajustarOrgDdMenu(box);
        }
      });
    }
    box.appendChild(trigger);
    box.appendChild(menu);
  }

  function pintarOrgPick(kind, options, value, onChange) {
    var map = {
      hora: { box: '[data-org-pick-hora]', native: '[data-org-hora]' }
    };
    var cfg = map[kind];
    if (!cfg) return;
    var box = $(cfg.box);
    var native = $(cfg.native);
    if (!box && !native) return;
    if (box) box.innerHTML = '';
    if (native) native.innerHTML = '';
    var opts = Array.isArray(options) ? options : [];
    if (!opts.length) {
      if (box) {
        var vacio = document.createElement('p');
        vacio.className = 'mini org-pick-vacio';
        vacio.textContent = 'Sin opciones';
        box.appendChild(vacio);
      }
      return;
    }
    var valStr = value === null || value === undefined ? '' : String(value);
    opts.forEach(function (opt) {
      var optVal = opt.value === null || opt.value === undefined ? '' : String(opt.value);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'org-pick-opt';
      btn.setAttribute('role', 'option');
      if (opt.disabled) {
        btn.className += ' is-disabled';
        btn.disabled = true;
      } else if (valStr !== '' && optVal === valStr) {
        btn.classList.add('is-on');
      }
      btn.setAttribute('aria-selected', btn.classList.contains('is-on') ? 'true' : 'false');
      btn.textContent = opt.label || optVal;
      if (!opt.disabled && onChange) {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          var picked = opt.value;
          onChange(picked);
          if (native) native.value = optVal;
          pintarOrgPick(kind, opts, picked, onChange);
        });
      }
      if (box) box.appendChild(btn);
      if (native) {
        var o = document.createElement('option');
        o.value = optVal;
        o.textContent = opt.label || optVal;
        if (opt.disabled) o.disabled = true;
        if (valStr !== '' && optVal === valStr) o.selected = true;
        native.appendChild(o);
      }
    });
    if (native && valStr !== '') native.value = valStr;
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
      btn.innerHTML = '<span class="org-picker-stack">' +
        '<span class="org-picker-cara">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="org-picker-ini">' + esc(ini) + '</span>') +
        '</span>' +
        (sel.indexOf(id) >= 0 ? '<span class="org-picker-check" aria-hidden="true">?</span>' : '') +
        '</span><span class="org-picker-nom">' + esc(nom) + '</span>';
      btn.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); toggleOrgPicker(id); });
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
    actualizarOrgModoEstado();
  }

  function resetOrgForm(preset) {
    var p = preset || {};
    org.sel = orgIdsDesdePreset(p);
    org.tipo = '';
    org.lugar = p.lugar || '';
    org.dia = null;
    org.hora = 17;
    syncOrgTipoDesdeSeleccion();
    actualizarOrgModoEstado();
  }

  function abrirOrganizarConPreset(preset) {
    resetOrgForm(preset);
    orgPresetNuevo = true;
    limpiarOrgAviso();
    setCapa('organizar');
    if ($('.play-root')) $('.play-root').removeAttribute('data-consulta');
    fillOrganizar();
  }

  function syncOrgModoUi() {
    actualizarOrgPickerHint();
    actualizarOrgModoEstado();
  }
  async function refreshOrgHoras() {
    var hora = $('[data-org-hora]');
    if (!hora) return;
    org.lugar = $('[data-org-lugar]').value;
    org.dia = parseInt($('[data-org-dia]').value, 10);
    if (!org.dia && cacheEstado && cacheEstado.reloj) org.dia = cacheEstado.reloj.dia_pueblo;
    var parts = orgSeleccionados();
    if (!orgParticipantesListos()) {
      pintarOrgDropdown('hora', [{ value: '', label: '—', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('', false);
      return;
    }
    if (!org.lugar) {
      pintarOrgDropdown('hora', [{ value: '', label: '—', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('Elige un lugar para ver horarios.', true);
      return;
    }
    if (!org.dia) {
      pintarOrgDropdown('hora', [{ value: '', label: '—', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('Elige cuándo quedar para ver horarios.', true);
      return;
    }
    var tipo = orgModo() === 'solo' ? 'individual' : (org.tipo || 'conocerse');
    try {
      var r = await api('agenda.slots_compatibles', {
        participantes: parts,
        tipo: tipo,
        lugar_id: org.lugar,
        desde_dia: org.dia,
        max_dias: 1,
        max_slots: 48
      }, 'GET');
      if (!r.ok) {
        pintarOrgDropdown('hora', [{ value: '', label: '—', disabled: true }], '', null);
        org.hora = 0;
        setOrgHorasHint(mensajeErrorOrgApi(r, 'No hay horarios disponibles ahora.'), true);
        return;
      }
      var slots = (r.slots || []).filter(function (s) {
        return (s.dia || 0) === org.dia;
      });
      slots.sort(function (a, b) { return (a.hora || 0) - (b.hora || 0); });
      var horaOpts = slots.map(function (s) {
        return {
          value: s.hora,
          label: s.etiqueta_hora || String(s.hora).padStart(2, '0') + ':00'
        };
      });
      if (!horaOpts.length) {
        pintarOrgDropdown('hora', [{ value: '', label: 'Sin huecos hoy', disabled: true }], '', null);
        org.hora = 0;
      } else {
        var curHora = org.hora && horaOpts.some(function (o) { return String(o.value) === String(org.hora); })
          ? org.hora : horaOpts[0].value;
        pintarOrgDropdown('hora', horaOpts, curHora, function (v) { org.hora = v; });
        org.hora = parseInt(curHora, 10) || 0;
      }
      var hintEl = document.querySelector('[data-org-horas-hint]');
      if (hintEl) {
        if (r.hint_ui) {
          hintEl.textContent = r.hint_ui;
          hintEl.hidden = false;
        } else if (!slots.length && r.primera_compatible && (r.primera_compatible.dia || 0) !== org.dia) {
          hintEl.textContent = 'Primera hora compatible: ' + (r.primera_compatible.etiqueta_ui || r.primera_compatible.etiqueta_hora || '');
          if (r.bloqueo_solicitado) hintEl.textContent += ' (' + r.bloqueo_solicitado + ')';
          hintEl.hidden = false;
        } else if (!slots.length && r.diagnostico && r.diagnostico.resumen_ui) {
          hintEl.textContent = r.diagnostico.resumen_ui;
          hintEl.hidden = false;
        } else {
          hintEl.textContent = '';
          hintEl.hidden = true;
        }
      }
    } catch (e) {
      pintarOrgDropdown('hora', [{ value: '', label: 'Sin huecos', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('No se pudieron cargar los horarios.', true);
    }
    actualizarOrgCrearBtn();
  }

  async function fillOrganizar() {
    syncOrgModoUi();
    const lugares = destinosOperativos();
    const lugOpts = lugares.map(function (d) { return { value: d.id, label: d.nombre }; });
    if (org.lugar && !lugOpts.some(function (o) { return o.value === org.lugar; })) org.lugar = '';
    if (!org.lugar && lugOpts.length) org.lugar = lugOpts[0].value;
    pintarOrgDropdown('lugar', lugOpts, org.lugar, function (v) {
      org.lugar = v;
      pintarOrgLugarHorario(v);
      refreshOrgHoras();
      actualizarOrgCrearBtn();
    });
    pintarOrgLugarHorario(org.lugar);
    const rv = (cacheEstado && cacheEstado.reloj_vista) || {};
    const dias = rv.proximos_dias || [];
    const diaOpts = dias.map(function (d) {
      return { value: d.dia_pueblo, label: d.etiqueta || ('dia ' + d.dia_pueblo) };
    });
    org.dia = org.dia || (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo);
    if (org.dia && !diaOpts.some(function (o) { return String(o.value) === String(org.dia); })) {
      org.dia = diaOpts.length ? diaOpts[0].value : null;
    }
    pintarOrgDropdown('dia', diaOpts, org.dia, function (v) {
      org.dia = v;
      refreshOrgHoras();
      actualizarOrgCrearBtn();
    });
    pintarOrgCaras();
    await refreshTipos();
    await refreshOrgHoras();
    actualizarOrgCrearBtn();
  }

  async function refreshTipos() {
    const box = $('[data-org-tipos]');
    const sel = orgSeleccionados();
    if (orgModo() === 'solo') {
      if (!sel.length) {
        box.innerHTML = '';
        return;
      }
      const rSolo = await api('encuentro.tipos_permitidos', { participantes: [sel[0]], modo: 'solo' }, 'GET');
      org.tipo = 'individual';
      box.innerHTML = orgTipoHtml('individual', 'Por su cuenta', true);
      return;
    }
    if (sel.length < 2) {
      box.innerHTML = '';
      return;
    }
    const r = await api('encuentro.tipos_permitidos', {
      participantes: sel,
      residente_a: sel[0],
      residente_b: sel[1]
    }, 'GET');
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
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
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

  function mostrarOrgAviso(txt) {
    var el = $('[data-org-aviso]');
    if (el) {
      el.textContent = txt;
      el.hidden = false;
    }
    toast(txt);
  }

  function limpiarOrgAviso() {
    var el = $('[data-org-aviso]');
    if (el) {
      el.hidden = true;
      el.textContent = '';
    }
  }

  async function proponer() {
    limpiarOrgAviso();
    var val = validarOrgForm();
    if (!val.ok) {
      mostrarOrgAviso(val.msg);
      return;
    }
    const parts = orgSeleccionados();
    const payload = {
      participantes: parts,
      dia: org.dia,
      hora: org.hora,
      tipo: orgModo() === 'solo' ? 'individual' : (org.tipo || ''),
      lugar: org.lugar,
      modo: orgModo()
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
      var partsUi = orgSeleccionados();
      var na = nombreDe(partsUi[0]);
      var nb = nombreDe(partsUi[1] || '');
      var lugUi = nombreLugarTitulo(org.lugar, org.lugar);
      if (r.rechazada) {
        toast(r.mensaje_ui || ('Plan rechazado: ' + na + ' y ' + nb + ' en ' + lugUi + '.'));
        if (r.contrapropuesta && r.contrapropuesta.dia && r.contrapropuesta.hora) {
          org.dia = r.contrapropuesta.dia;
          org.hora = r.contrapropuesta.hora;
        }
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
      mostrarOrgAviso(mensajeErrorOrgApi(r, 'No se ha podido organizar el plan.'));
      await refresh();
    }
  }
  function persistPartidaId(id) {
    if (!id) return;
    partidaId = id;
    try { localStorage.setItem(storageKey(), id); } catch (e) {}
    try { localStorage.removeItem('aht_partida_id'); } catch (e) {}
  }
  async function adoptSqlPartidaIfAny() {
    const list = await api('partida.listar', {}, 'GET');
    if (!list.ok || !Array.isArray(list.partidas) || list.partidas.length === 0) return false;
    const serverId = list.partidas[0] && list.partidas[0].partida_id;
    if (!serverId) return false;
    if (partidaId !== serverId) persistPartidaId(serverId);
    return true;
  }
  async function ensurePartida() {
    if (await adoptSqlPartidaIfAny()) return true;
    if (partidaId) return true;
    const r = await api('partida.nueva', configNueva(true));
    if (r.ok && r.partida_id) persistPartidaId(r.partida_id);
    return !!r.ok;
  }
  async function refresh() {
    const popMensajitosAbierto = mensajitosPopAbierto;
    let paquete = await api('partida.refresh', {}, 'GET');
    if (!paquete.ok && partidaId) {
      const errRefresh = String(paquete.error || '').toUpperCase();
      const partidaPerdida = errRefresh === 'PARTIDA_NO_ENCONTRADA' || errRefresh === 'SAVE_CORRUPTO';
      if (partidaPerdida) {
        try { localStorage.removeItem(storageKey()); } catch (e) {}
        partidaId = null;
        if (await adoptSqlPartidaIfAny()) {
          paquete = await api('partida.refresh', {}, 'GET');
        } else if (await ensurePartida()) {
          paquete = await api('partida.refresh', {}, 'GET');
        }
      }
    }
    if (!paquete.ok) return;
    cacheEstado = paquete.estado || null;
    cacheInsp = paquete.partida || null;
    const mapa = { mapa: paquete.mapa || {}, pueblo: paquete.pueblo || {} };
    const buzon = paquete.buzon || {};
    const diario = paquete.diario || {};
    cacheDiario = diario;
    renderHud(cacheEstado, buzon.mensajes || []);
    renderMapaMarcas(mapa.mapa || null);
    renderPueblo(mapa.pueblo || { complejos: [] });
    renderShellPanels(cacheEstado, buzon.mensajes || [], diario);
      renderMisiones(cacheEstado.misiones_hoy || (cacheInsp && cacheInsp.misiones_diarias));
    renderBuzon(buzon.mensajes || []);
    renderCotilleo(diario.cotilleo || { hoy: diario.entradas || [], ayer: [], viejos: [] });
    actualizarCotiBadgesUI();
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

  window.AHT_PLAY = {
    api: api,
    isDebugOn: isDebugOn,
    refresh: refresh,
    get partidaId() { return partidaId; }
  };

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
    org = { tipo: '', sel: [], lugar: '', dia: null, hora: 17 };
    playtestLogClient.entries = [];
    setCapa('');
    const r = await api('partida.nueva', configNueva(true));
    if (r.ok && r.partida_id) {
      persistPartidaId(r.partida_id);
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
    return r;
  }
  let pasarRatoEnCurso = false;
  async function pasarElRato() {
    const btn = $('[data-pasar-rato]');
    if (!btn || pasarRatoEnCurso) return;
    const perdida = !!(cacheEstado && cacheEstado.partida_perdida) ||
      !!(cacheEstado && cacheEstado.vida_pueblo && cacheEstado.vida_pueblo.game_over_activo);
    if (perdida) {
      toast('La partida ha terminado. Empieza otra con «Nueva partida».');
      return;
    }
    const h = horaActualEstado();
    const nocturno = esHoraNoche(h);
    const horas = nocturno ? Math.max(1, (HORA_DIA_DESDE - h + 24) % 24) : 1;
    pasarRatoEnCurso = true;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.classList.add('is-busy');
    const txt = btn.querySelector('.pasar-rato-txt');
    if (txt) txt.textContent = 'A ver qué se cuece…';
    try {
      const r = await avanzarHoras(horas);
      if (!r.ok) toast(r.mensaje_ui || 'Ahora no se puede pasar el rato.');
    } finally {
      pasarRatoEnCurso = false;
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
      btn.classList.remove('is-busy');
      pintarModoReloj(esHoraNoche(horaActualEstado()));
    }
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
  (function bindCursoNav() {
    const nav = document.querySelector('[data-curso-nav]');
    if (!nav || nav._ahtCursoBound) return;
    nav._ahtCursoBound = true;
    var prevBtn = nav.querySelector('[data-curso-prev]');
    var nextBtn = nav.querySelector('[data-curso-next]');
    if (prevBtn) prevBtn.addEventListener('click', function () { moverCursoSeleccion(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { moverCursoSeleccion(1); });
  })();
  (function bindEncursosMovil() {
    document.querySelectorAll('[data-encursos-track]').forEach(function (encTrack) {
      if (encTrack._ahtEncMovScroll) return;
      encTrack._ahtEncMovScroll = true;
      encTrack.addEventListener('scroll', function () {
        const b = encTrack.closest('[data-encursos-block]');
        if (b) renderEncursosMovilNavFor(b);
      }, { passive: true });
    });
  })();
  const btnPasarRato = $('[data-pasar-rato]');
  if (btnPasarRato) btnPasarRato.addEventListener('click', pasarElRato);
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
    const atras = ev.target.closest('[data-consulta-atras]');
    if (atras && uiRootFrom(atras)) {
      ev.preventDefault();
      ev.stopPropagation();
      if (uiHistDepth > 0) {
        uiHistDepth--;
        try { history.back(); } catch (e) { uiHistBack(); }
      } else uiHistBack();
      return;
    }
    const t = ev.target.closest('[data-close], .velo');
    if (t && uiRootFrom(t)) {
      cerrarUiCompleto();
      return;
    }
    const open = ev.target.closest('[data-open]');
    if (open && uiRootFrom(open)) {
      const name = open.getAttribute('data-open');
      cerrarMensajitosPop();
      setCapa(name);
      $('.play-root').removeAttribute('data-consulta');
      syncScrollLock();
      if (name === 'organizar') {
        if (!orgPresetNuevo) resetOrgForm();
        orgPresetNuevo = false;
        fillOrganizar();
      }
      if (name === 'agenda') renderAgendaPlanes();
      if (name === 'diario') {
        cotiFiltroActivo = '';
        const d = cacheDiario || {};
        renderCotilleo(d.cotilleo || { hoy: d.entradas || [], ayer: [], viejos: [] });
        marcarCotilleoVisto();
      }
      if (name === 'vecinos') { vecTabActiva = 'vecinos'; aplicarVecTabUI(); renderVecinos(); }
      if (name === 'buzon') renderBuzon(cacheBuzon);
      return;
    }

    const diarioFiltBtn = ev.target.closest('[data-diario-filt]');
    if (diarioFiltBtn) {
      diarioVecinoFiltro = diarioFiltBtn.getAttribute('data-diario-filt') || 'todo';
      syncDiarioVecinoFiltros();
      pintarDiarioVecinoLista();
      return;
    }
    const cotiFiltro = ev.target.closest('[data-coti-filtro]');
    if (cotiFiltro) {
      const id = cotiFiltro.getAttribute('data-coti-filtro') || '';
      cotiFiltroActivo = cotiFiltroActivo === id ? '' : id;
      renderCotilleoFiltros(cotiTodosItems(cotiCache));
      renderCotilleoLista(cotiCache);
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
    /* Selector de temas: abrir/cerrar el panel del encuentro pintado.
       Cada .enc-int-temas vive dentro de SU tarjeta (data-enc-id), asi que
       solo afecta al encuentro seleccionado. */
    if (!ev.target.closest('.enc-int-temas')) cerrarSelectorTemas();
    const temasToggle = ev.target.closest('[data-temas-toggle]');
    if (temasToggle) {
      ev.preventDefault();
      ev.stopPropagation();
      var wrapTemas = temasToggle.closest('.enc-int-temas');
      var panelTemas = wrapTemas && wrapTemas.querySelector('[data-temas-panel]');
      if (panelTemas) {
        const abrirTemas = panelTemas.hidden;
        cerrarSelectorTemas();
        panelTemas.hidden = !abrirTemas;
        temasToggle.setAttribute('aria-expanded', String(abrirTemas));
        temasToggle.classList.toggle('is-open', abrirTemas);
      }
      return;
    }
    const encMovCta = ev.target.closest('[data-enc-mov-toggle]');
    if (encMovCta) {
      ev.preventDefault();
      ev.stopPropagation();
      const movCard = encMovCta.closest('[data-enc-mov-card]');
      const movPanel = movCard && movCard.querySelector('[data-enc-mov-panel]');
      if (movPanel) {
        const abrirMov = movPanel.hidden;
        movPanel.hidden = !abrirMov;
        encMovCta.setAttribute('aria-expanded', String(abrirMov));
        encMovCta.classList.toggle('is-open', abrirMov);
      }
      return;
    }
    const encIntBtn = ev.target.closest('[data-enc-int-accion]');
    if (encIntBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      var wrap = encIntBtn.closest('[data-enc-int]');
      if (!wrap || wrap.classList.contains('is-busy')) return;
      wrap.classList.add('is-busy');
      var encId = wrap.getAttribute('data-enc-id');
      var acc = encIntBtn.getAttribute('data-enc-int-accion');
      var objetivoId = wrap.getAttribute('data-enc-int-objetivo') || '';
      var extra = { objetivo: objetivoId || undefined };
      if (acc === 'hobby') {
        var hobbyResidenteId = encIntBtn.getAttribute('data-residente-id');
        if (objetivoId && !hobbyVisibleParaObjetivo(hobbyResidenteId, objetivoId)) {
          wrap.classList.remove('is-busy');
          return;
        }
        extra.hobby_id = encIntBtn.getAttribute('data-hobby-id');
        extra.residente_id = hobbyResidenteId;
        var optWrap = encIntBtn.closest('.enc-int-temas');
        if (optWrap) {
          cerrarSelectorTemas();
          var togEl = optWrap.querySelector('[data-temas-toggle]');
          if (togEl) {
            togEl.classList.add('is-elegido');
            togEl.textContent = esc(encIntBtn.textContent.trim()) + ' \u25be';
            togEl.setAttribute('aria-expanded', 'false');
          }
        }
      }
      ejecutarIntervencionEncuentro(encId, acc, extra).finally(function () {
        wrap.classList.remove('is-busy');
      });
      return;
    }
    const encPersona = ev.target.closest('[data-enc-int-persona]');
    if (encPersona) {
      ev.preventDefault();
      ev.stopPropagation();
      var wrapP = encPersona.closest('[data-enc-int]');
      if (!wrapP) return;
      var personaId = encPersona.getAttribute('data-enc-int-persona');
      wrapP.setAttribute('data-enc-int-objetivo', personaId);
      var stepPersona = wrapP.querySelector('[data-enc-int-paso="persona"]');
      var stepAccion = wrapP.querySelector('[data-enc-int-paso="accion"]');
      if (stepPersona) stepPersona.hidden = true;
      if (stepAccion) stepAccion.hidden = false;
      wrapP.querySelectorAll('.enc-int-btn--hobby[data-residente-id]').forEach(function (b) {
        var rid = b.getAttribute('data-residente-id');
        b.hidden = !hobbyVisibleParaObjetivo(rid, personaId);
      });
      return;
    }
    const encVolver = ev.target.closest('[data-enc-int-volver]');
    if (encVolver) {
      ev.preventDefault();
      ev.stopPropagation();
      var wrapV = encVolver.closest('[data-enc-int]');
      if (!wrapV) return;
      wrapV.removeAttribute('data-enc-int-objetivo');
      var stepPA = wrapV.querySelector('[data-enc-int-paso="persona"]');
      var stepAC = wrapV.querySelector('[data-enc-int-paso="accion"]');
      if (stepAC) stepAC.hidden = true;
      if (stepPA) stepPA.hidden = false;
      wrapV.querySelectorAll('.enc-int-btn--hobby[data-residente-id]').forEach(function (b) {
        b.hidden = false;
      });
      return;
    }
    const caraTok = ev.target.closest('.cara-token[data-residente]');
    if (caraTok) {
      ev.preventDefault();
      ev.stopPropagation();
      var zonaHit = caraTok.closest('.mapa-zona-hit');
      if (zonaHit && zonaHit.blur) zonaHit.blur();
      abrirFicha(caraTok.getAttribute('data-residente'));
      return;
    }
    var mapHit = null;
    if (ev.target.closest('[data-mapa-canonico]') && !ev.target.closest('.mapa-zona-hit .hab')) {
      mapHit = zonaDesdePuntoMapa(ev.clientX, ev.clientY);
    }
    if (!mapHit) {
      const zona = ev.target.closest('.mapa-zona-hit[data-zona]');
      if (zona) mapHit = { zonaId: zona.getAttribute('data-zona'), zonaBtn: zona };
    }
    if (mapHit) {
      abrirConsultaZona(mapHit.zonaId, mapHit.zonaBtn);
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



  const animoClose = $('[data-animo-close]');
  if (animoClose) animoClose.addEventListener('click', cerrarAnimoOverlay);
  const animoOvEl = $('[data-animo-overlay]');
  if (animoOvEl) {
    animoOvEl.addEventListener('click', function (ev) {
      if (ev.target === animoOvEl) cerrarAnimoOverlay();
    });
  }

  const diarioVecinoClose = $('[data-diario-vecino-close]');
  if (diarioVecinoClose) diarioVecinoClose.addEventListener('click', cerrarDiarioVecino);
  const diarioVolver = $('[data-diario-volver]');
  if (diarioVolver) diarioVolver.addEventListener('click', cerrarDiarioVecino);
  const fichaDiarioBtn = $('[data-ficha-diario-btn]');
  if (fichaDiarioBtn) {
    fichaDiarioBtn.addEventListener('click', function () {
      abrirDiarioVecino(fichaActualId, null);
    });
  }

  (function bindFichaNavCircular() {
    const capa = document.querySelector('.capa-ficha');
    if (!capa || capa._ahtFichaNavBound) return;
    capa._ahtFichaNavBound = true;
    const prev = capa.querySelector('[data-ficha-nav-prev]');
    const next = capa.querySelector('[data-ficha-nav-next]');
    if (prev) {
      prev.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        navegarFicha(-1);
      });
    }
    if (next) {
      next.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        navegarFicha(1);
      });
    }
  })();
  const diarioBusca = $('[data-diario-busca]');
  if (diarioBusca) {
    diarioBusca.addEventListener('input', function () {
      diarioVecinoBusca = diarioBusca.value || '';
      pintarDiarioVecinoLista();
    });
  }
  const diarioOrden = $('[data-diario-orden]');
  if (diarioOrden) {
    diarioOrden.addEventListener('click', function () {
      diarioVecinoOrden = diarioVecinoOrden === 'reciente' ? 'antiguo' : 'reciente';
      syncDiarioVecinoFiltros();
      pintarDiarioVecinoLista();
    });
  }

  const fichaVolver = $('[data-ficha-volver]');
  if (fichaVolver) {
    fichaVolver.addEventListener('click', function () { setCapa('vecinos'); renderVecinos(); });
  }


  const buzonLeerTodos = $('[data-buzon-leer-todos]');
  if (buzonLeerTodos) {
    buzonLeerTodos.addEventListener('click', async function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      await marcarTodosMensajitosLeidos();
    });
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

  $$('[data-vec-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setVecTab(btn.getAttribute('data-vec-tab') || 'vecinos');
    });
  });
  document.addEventListener('click', function (ev) {
    const filtroBtn = ev.target.closest('[data-vec-rel-filtro]');
    if (filtroBtn && ev.target.closest('.capa-vecinos')) {
      vecRelFiltro = filtroBtn.getAttribute('data-vec-rel-filtro') || '';
      pintarVecRelFiltros();
      renderVecRelLista();
      return;
    }
    const pers = ev.target.closest('[data-vec-rel-open]');
    if (pers && pers.getAttribute('data-vec-rel-open') && ev.target.closest('.capa-vecinos')) {
      ev.preventDefault();
      abrirFicha(pers.getAttribute('data-vec-rel-open'));
      return;
    }
  });
  const vecRelSel = $('[data-vec-rel-persona]');
  if (vecRelSel) {
    vecRelSel.addEventListener('change', function () {
      vecRelPersona = vecRelSel.value || '';
      renderVecRelLista();
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
  if (orgLug) orgLug.addEventListener('change', function () {
      pintarOrgLugarHorario();
      refreshOrgHoras();
    });
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('.org-dd')) return;
    cerrarOrgDds();
  });
  if (orgDia) orgDia.addEventListener('change', function () { refreshOrgHoras(); });
var finOk = $('[data-tut-fin-ok]');
  if (finOk) finOk.addEventListener('click', cerrarTutFinale);
  var vidaDerrotaOk = $('[data-vida-derrota-ok]');
  if (vidaDerrotaOk) vidaDerrotaOk.addEventListener('click', function () {
    var box = $('[data-vida-derrota]');
    if (box) box.hidden = true;
    document.body.classList.remove('vida-derrota-activa');
  });
  if (orgGo) orgGo.addEventListener('click', proponer);

  const musicaToggle = $('[data-musica-toggle]');
  actualizarControlMusica();
  if (musicaToggle) {
    musicaToggle.addEventListener('click', function () {
      cambiarMusica(!musicaActiva);
    });
  }
  iniciarMusicaFondo(true);

  window.addEventListener('popstate', function () {
    if (uiHistDepth > 0) uiHistDepth--;
    uiHistBack();
  });

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
