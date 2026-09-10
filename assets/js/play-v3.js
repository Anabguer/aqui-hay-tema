(function () {
  'use strict';

  function ctaEncuentroMovVisible(enc, iv) {
    if (!enc || enc.estado !== 'en_curso') return false;
    if (enc.intencion !== 'celeste_organizado') return false;
    if (enc.tipo === 'individual') return false;
    var ids = enc.participantes || [];
    if (ids.length !== 2) return false;
    return !!(iv && iv.disponible && iv.acciones && iv.acciones.length);
  }
  function ctaTxtEncuentroMov(enc, iv) {
    if (!ctaEncuentroMovVisible(enc, iv)) return '';
    return '\u00bfQu\u00e9 se cuece ah\u00ed? \ud83d\udc40';
  }
  var mentesEncIdActivo = null;

  var mentesFeedbackCardFlash = {};
  var MENTES_FEEDBACK_CARD_MS = 8000;
  function activarMentesFeedbackCard(encId, iv) {
    var id = String(encId || '');
    if (!id || !iv || !iv.ultimo || !iv.ultimo.texto) return;
    var prev = mentesFeedbackCardFlash[id];
    if (prev && prev.timer) clearTimeout(prev.timer);
    mentesFeedbackCardFlash[id] = {
      tono: iv.ultimo.tono || 'neutral',
      txt: textoFeedbackIntervencion(iv),
      timer: setTimeout(function () {
        delete mentesFeedbackCardFlash[id];
        if (typeof renderShellPanels === 'function' && cacheEstado) {
          renderShellPanels(cacheEstado, cacheBuzon, cacheDiario);
        }
      }, MENTES_FEEDBACK_CARD_MS)
    };
  }
  function mentesFeedbackCardActivo(encId) {
    return mentesFeedbackCardFlash[String(encId || '')] || null;
  }
  function resumenEncursoSinMentes(enc, iv) {
    if (iv && iv.usada) return '';
    if (enc && enc.intencion === 'celeste_organizado') {
      var m = iv && iv.motivo_no_disponible;
      if (m === 'intervencion_ya_usada') return 'Ya has metido mano en este encuentro.';
      if (m === 'fuera_de_franja') return 'El encuentro sigue, pero ahora no puedes intervenir.';
      if (m === 'no_organizado_por_celestine') return 'Es un encuentro espont\u00e1neo: solo puedes mirar.';
    }
    return 'Parece que la cosa va bien\u2026';
  }

  const API = 'api/index.php';
  const qs = new URLSearchParams(location.search);
  const CONFIG_JUEGO = { config_id: 'juego_v1' };
  const DEBUG_KEY = 'aht_debug_on';
  const DEBUG_ENV = qs.get('debug') === '1' || qs.get('lab') === '1'
    || /localhost|127\.0\.0\.1/i.test(location.hostname);
  let DEBUG_ON = false;
  if (DEBUG_ENV) {
    try { DEBUG_ON = localStorage.getItem(DEBUG_KEY) === '1'; } catch (e) {}
  }
  function syncDebugFloatVisibility() {
    const el = document.querySelector('[data-debug-float]');
    if (el) el.hidden = !DEBUG_ON;
  }
  function setDebugOn(on) {
    DEBUG_ON = !!on;
    try { localStorage.setItem(DEBUG_KEY, DEBUG_ON ? '1' : '0'); } catch (e2) {}
    document.body.setAttribute('data-debug', DEBUG_ON ? '1' : '0');
    syncDebugFloatVisibility();
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
  const MUSICA_VOL_KEY = 'aht_musica_vol';
  const MUSICA_VOL_DEFAULT = 0.22;
  let musicaActiva = true;
  try { musicaActiva = localStorage.getItem(MUSICA_STORAGE_KEY) !== '0'; } catch (e) {}
  let musicaVolumen = MUSICA_VOL_DEFAULT;
  try {
    const storedVol = parseFloat(localStorage.getItem(MUSICA_VOL_KEY));
    if (isFinite(storedVol) && storedVol >= 0 && storedVol <= 1) musicaVolumen = storedVol;
  } catch (e) {}
  const musicaFondo = new Audio('assets/audio/musica-fondo.mp3');
  musicaFondo.loop = true;
  musicaFondo.volume = musicaVolumen;
  musicaFondo.preload = 'auto';
  let musicaPlayEnCurso = null;
  let musicaPrimerGestoPendiente = false;

  function actualizarControlMusica() {
    const etiqueta = musicaActiva ? 'Desactivar música' : 'Activar música';
    $$('[data-musica-toggle]').forEach(function (control) {
      control.dataset.musica = musicaActiva ? 'on' : 'off';
      control.setAttribute('aria-pressed', musicaActiva ? 'true' : 'false');
      control.setAttribute('aria-label', etiqueta);
      control.setAttribute('title', etiqueta);
    });
  }

  function setMusicaVolumen(pct) {
    const v = Math.max(0, Math.min(100, pct)) / 100;
    musicaVolumen = v;
    musicaFondo.volume = v;
    try { localStorage.setItem(MUSICA_VOL_KEY, String(v)); } catch (e) {}
    $$('[data-musica-vol]').forEach(function (input) {
      const val = String(Math.round(v * 100));
      if (input.value !== val) input.value = val;
    });
  }

  function syncBottomNav(name) {
    $$('.play-bottom-nav-btn').forEach(function (b) {
      const open = b.getAttribute('data-open');
      let on = false;
      if (open === 'relaciones') on = name === 'vecinos' && vecTabActiva === 'relaciones';
      else if (open === 'vecinos') on = name === 'vecinos' && vecTabActiva === 'vecinos';
      else if (open === 'necesidades_global') on = name === 'vecinos' && vecTabActiva === 'cuidados';
      else on = open === name;
      b.classList.toggle('is-on', !!on);
      b.setAttribute('aria-current', on ? 'page' : 'false');
    });
  }

  function syncAjustesUI() {
    $$('[data-musica-vol]').forEach(function (input) {
      input.value = String(Math.round(musicaVolumen * 100));
    });
    if (window.AhtAudioFeedback && typeof window.AhtAudioFeedback.getVolume === 'function') {
      const v = Math.round(window.AhtAudioFeedback.getVolume() * 100);
      $$('[data-sfx-vol]').forEach(function (input) {
        if (input.value !== String(v)) input.value = String(v);
      });
    }
  }

  function actualizarInvNavBadge(items) {
    const badge = $('[data-inv-nav-badge]');
    if (!badge) return;
    let n = 0;
    (items || []).forEach(function (it) { n += (it && it.cantidad) ? it.cantidad : 0; });
    if (n > 0) {
      badge.textContent = String(n);
      badge.hidden = false;
    } else {
      badge.textContent = '0';
      badge.hidden = true;
    }
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
  let cacheMisionesStripItems = null;
  let vidaCorazonPctPrev = null;
  let vidaCorazonReady = false;
  const ORG_MAX_VECINOS = 2;
  let org = { tipo: '', sel: [], lugar: '', dia: null, hora: 17, peticion_id: null, modo: null, evento_pueblo_id: null, evento_ctx: null };
  let orgPresetNuevo = false;
  let orgProponiendo = false;
  const ORG_BTN_LABEL = 'Crear plan';
  const ORG_BTN_LABEL_EVENTO = 'Apuntar vecinos';
  const ORG_BTN_BUSY = 'Organizando\u2026';
  const ORG_BTN_BUSY_EVENTO = 'Apuntando\u2026';
  const playtestLogClient = { entries: [] };
  const ahtDebugSessionLog = [];
  const ahtJsErrorLog = [];
  (function bindAhtJsErrorCapture() {
    function pushJsErr(entry) {
      ahtJsErrorLog.push(entry);
      if (ahtJsErrorLog.length > 100) ahtJsErrorLog.splice(0, ahtJsErrorLog.length - 100);
    }
    window.addEventListener('error', function (ev) {
      pushJsErr({
        ts: new Date().toISOString(),
        tipo: 'JS_ERROR',
        mensaje: ev && ev.message ? String(ev.message) : 'error',
        archivo: ev && ev.filename ? String(ev.filename) : null,
        linea: ev && ev.lineno != null ? ev.lineno : null,
        columna: ev && ev.colno != null ? ev.colno : null
      });
    });
    window.addEventListener('unhandledrejection', function (ev) {
      var reason = ev && ev.reason;
      pushJsErr({
        ts: new Date().toISOString(),
        tipo: 'JS_UNHANDLED_REJECTION',
        mensaje: reason && reason.message ? String(reason.message) : String(reason)
      });
    });
  })();
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

  async function obtenerDebugExport(soloEstado) {
    if (!isDebugOn()) {
      toast('Activa DEBUG primero.');
      return null;
    }
    const body = { historial: soloEstado ? [] : ahtDebugSessionLog.slice() };
    const r = await api('partida.debug_export', body);
    if (!r.ok || !r.debug_export) {
      toast(r.mensaje_ui || 'No se pudo exportar debug.');
      return null;
    }
    return r.debug_export;
  }

  async function copiarDebugExport(soloEstado) {
    const exportData = await obtenerDebugExport(soloEstado);
    if (!exportData) return;
    const txt = exportData.texto || '';
    try {
      await navigator.clipboard.writeText(txt);
      toast('Debug copiado.');
    } catch (e) {
      toast('No se pudo copiar al portapapeles.');
    }
  }

  async function descargarDebugExport(soloEstado) {
    const exportData = await obtenerDebugExport(soloEstado);
    if (!exportData) return;
    let datos = exportData.json;
    if (!datos || typeof datos !== 'object') {
      datos = { texto: exportData.texto || '' };
    }
    datos = ahtSanitizarDiagnostico(datos);
    const contenido = ahtTextoDiagnosticoLegible(datos);
    const nombre = ahtNombreArchivoDiagnostico();
    const blob = new Blob([contenido], { type: 'application/json;charset=utf-8' });
    try {
      if (navigator.share && navigator.canShare) {
        const file = new File([blob], nombre, { type: 'application/json' });
        if (navigator.canShare({ files: [file] })) {
          await navigator.share({ files: [file], title: 'AHT debug', text: 'Diagnóstico Aquí Hay Tema' });
          toast('Diagnóstico compartido.');
          return;
        }
      }
    } catch (e) {}
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    toast('Debug descargado: ' + nombre);
  }

  const AHT_DIAG_SENSITIVE_KEYS = /^(password|passwd|pass|token|cookie|cookies|authorization|auth|secret|credentials|api[_-]?key)$/i;

  function ahtFrontendVersion() {
    var v = '';
    var script = document.querySelector('script[src*="play-v3.js"]');
    if (script && script.src) {
      var m = script.src.match(/[?&]v=([^&]+)/);
      if (m) v = decodeURIComponent(m[1]);
    }
    if (!v) {
      var link = document.querySelector('link[href*="play-v3"]');
      if (link && link.href) {
        var m2 = link.href.match(/[?&]v=([^&]+)/);
        if (m2) v = decodeURIComponent(m2[1]);
      }
    }
    return v || null;
  }

  function ahtSanitizarDiagnostico(value, depth) {
    depth = depth || 0;
    if (depth > 12) return '[truncado]';
    if (value == null || typeof value === 'number' || typeof value === 'boolean') return value;
    if (typeof value === 'string') {
      if (/^Bearer\s+/i.test(value)) return '[redactado:authorization]';
      return value;
    }
    if (Array.isArray(value)) {
      return value.map(function (item) { return ahtSanitizarDiagnostico(item, depth + 1); });
    }
    if (typeof value === 'object') {
      var out = {};
      Object.keys(value).forEach(function (key) {
        if (AHT_DIAG_SENSITIVE_KEYS.test(key)) {
          out[key] = '[redactado]';
          return;
        }
        out[key] = ahtSanitizarDiagnostico(value[key], depth + 1);
      });
      return out;
    }
    return String(value);
  }

  function ahtResumenClienteDiagnostico() {
    var estado = cacheEstado || {};
    var insp = cacheInsp || {};
    var encuentros = Array.isArray(insp.encuentros) ? insp.encuentros : [];
    var activos = encuentros.filter(function (e) {
      var st = e && e.estado ? String(e.estado) : '';
      return st === 'programado' || st === 'en_curso';
    });
    return {
      partida_id: partidaId || estado.partida_id || null,
      config_id: estado.config_id || (insp.meta && insp.meta.config_id) || null,
      reloj: estado.reloj || (insp.reloj || null),
      vecinos_activos: estado.pueblo_residentes_activos != null ? estado.pueblo_residentes_activos : null,
      encuentros_activos: activos.length,
      debug_ui_activo: isDebugOn(),
      capa_actual: ($('.play-root') && $('.play-root').getAttribute('data-capa')) || null
    };
  }

  function ahtNombreArchivoDiagnostico() {
    var d = new Date();
    function pad(n) { return String(n).padStart(2, '0'); }
    return 'AHT-debug-' + d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
      + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.json';
  }

  function ahtTextoDiagnosticoLegible(obj) {
    try { return JSON.stringify(obj, null, 2); } catch (e) { return String(obj); }
  }

  async function recolectarDiagnosticoAht() {
    var historial = ahtDebugSessionLog.slice();
    var motor = null;
    var motorError = null;
    try {
      var r = await api('dev.diagnostico.export', { historial: historial });
      if (r.ok && r.diagnostico_export) motor = r.diagnostico_export.json || null;
      else motorError = r.mensaje_ui || r.error || 'diagnostico_export_fallido';
    } catch (e) {
      motorError = (e && e.message) ? String(e.message) : 'diagnostico_export_excepcion';
    }
    var diag = {
      meta: {
        generado: new Date().toISOString(),
        url: location.href.split('#')[0],
        user_agent: navigator.userAgent || null,
        frontend_version: ahtFrontendVersion(),
        viewport: { width: window.innerWidth || null, height: window.innerHeight || null }
      },
      resumen: ahtResumenClienteDiagnostico(),
      cliente: {
        errores_api: playtestLogClient.entries.slice(),
        errores_js: ahtJsErrorLog.slice(),
        historial_lab_sesion: historial,
        acciones_recientes: playtestLogClient.entries.slice(-40)
      },
      motor: motor,
      motor_error: motorError
    };
    if (!motor && cacheInsp) {
      diag.cliente.cache_partida = {
        meta: cacheInsp.meta || null,
        reloj: cacheInsp.reloj || null,
        residentes_activos: cacheInsp.residentes ? Object.keys(cacheInsp.residentes).length : null,
        encuentros: cacheInsp.encuentros || null
      };
    }
    return ahtSanitizarDiagnostico(diag);
  }

  function ahtMostrarFeedbackDiagnostico(msg) {
    var el = $('[data-ajustes-debug-feedback]');
    if (!el) { toast(msg); return; }
    el.textContent = msg;
    el.hidden = false;
    clearTimeout(ahtMostrarFeedbackDiagnostico._t);
    ahtMostrarFeedbackDiagnostico._t = setTimeout(function () { el.hidden = true; }, 5000);
  }

  async function copiarDiagnosticoAht() {
    var diag;
    try { diag = await recolectarDiagnosticoAht(); }
    catch (e) { ahtMostrarFeedbackDiagnostico('No se pudo generar el diagn\u00f3stico.'); return; }
    var txt = ahtTextoDiagnosticoLegible(diag);
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(txt);
        ahtMostrarFeedbackDiagnostico('Debug copiado al portapapeles.');
        return;
      }
    } catch (e) {}
    ahtMostrarFeedbackDiagnostico('No se pudo copiar. Usa Descargar debug.');
  }

  async function descargarDiagnosticoAht() {
    var diag;
    try { diag = await recolectarDiagnosticoAht(); }
    catch (e) { ahtMostrarFeedbackDiagnostico('No se pudo generar el diagn\u00f3stico.'); return; }
    var txt = ahtTextoDiagnosticoLegible(diag);
    var nombre = ahtNombreArchivoDiagnostico();
    var blob = new Blob([txt], { type: 'application/json;charset=utf-8' });
    try {
      if (navigator.share && navigator.canShare) {
        var file = new File([blob], nombre, { type: 'application/json' });
        if (navigator.canShare({ files: [file] })) {
          await navigator.share({ files: [file], title: 'AHT debug', text: 'Diagn\u00f3stico Aqu\u00ed Hay Tema' });
          ahtMostrarFeedbackDiagnostico('Diagn\u00f3stico compartido.');
          return;
        }
      }
    } catch (e) {}
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    ahtMostrarFeedbackDiagnostico('Archivo descargado: ' + nombre);
  }

  async function api(action, body, method) {
    body = body || {};
    method = method || 'POST';
    const opts = { method: method, cache: 'no-store', credentials: 'same-origin' };
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
  var TUT_ICON_SVG = {
    observa: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="13" cy="13" r="7" stroke="#3b3028" stroke-width="1.8"/><line x1="18.2" y1="18.2" x2="25" y2="25" stroke="#3b3028" stroke-width="1.8" stroke-linecap="round"/><circle cx="13" cy="13" r="2.6" fill="#e9b4c0"/></svg>',
    propon: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 5c-3.4 4-7 6-7 11a7 7 0 0 0 14 0c0-5-3.6-7-7-11z" stroke="#3b3028" stroke-width="1.7" fill="#fff0cf"/><path d="M16 23v4M12.5 27h7" stroke="#3b3028" stroke-width="1.6" stroke-linecap="round"/><path d="M11 11.5c1.6-1.3 4.4-1.3 6 0" stroke="#3b3028" stroke-width="1.4" stroke-linecap="round"/></svg>',
    mira: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="11" cy="15" rx="6" ry="4.4" stroke="#3b3028" stroke-width="1.7"/><ellipse cx="21" cy="15" rx="6" ry="4.4" stroke="#3b3028" stroke-width="1.7"/><circle cx="11" cy="15" r="1.8" fill="#3b3028"/><circle cx="21" cy="15" r="1.8" fill="#3b3028"/><path d="M4 22c4-4.5 19-4.5 24 0" stroke="#3b3028" stroke-width="1.6" stroke-linecap="round"/></svg>',
    mapa: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 8l8-3 6 3 8-3v20l-8 3-6-3-8 3z" stroke="#3b3028" stroke-width="1.7" fill="#e7f0ff"/><path d="M14 5v20M20 8v20" stroke="#3b3028" stroke-width="1.4"/></svg>',
    vecinos: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11.5" cy="11" r="4" stroke="#3b3028" stroke-width="1.7" fill="#f6e2ef"/><circle cx="21" cy="12.5" r="3.4" stroke="#3b3028" stroke-width="1.7" fill="#e2f0e6"/><path d="M4.5 25c0-4.5 3.6-7.4 7-7.4S19 20.5 19 25" stroke="#3b3028" stroke-width="1.6" stroke-linecap="round"/><path d="M16 25c0-3.6 2.4-6.2 5.5-6.2S27.5 21.4 27.5 25" stroke="#3b3028" stroke-width="1.6" stroke-linecap="round"/></svg>',
    mensajitos: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="8" width="24" height="15" rx="3" stroke="#3b3028" stroke-width="1.7" fill="#fff6e8"/><path d="M4 10.5l12 8 12-8" stroke="#3b3028" stroke-width="1.6" stroke-linejoin="round"/><path d="M11 23v3l4-3" stroke="#3b3028" stroke-width="1.6" stroke-linejoin="round" fill="#fff6e8"/></svg>',
    plan: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="6" y="7" width="20" height="19" rx="3" stroke="#3b3028" stroke-width="1.7" fill="#fff6e8"/><path d="M6 12h20" stroke="#3b3028" stroke-width="1.5"/><path d="M11 5v4M21 5v4" stroke="#3b3028" stroke-width="1.6" stroke-linecap="round"/><path d="M16 16v6M13 19h6" stroke="#3b3028" stroke-width="1.7" stroke-linecap="round"/></svg>'
  };

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
    var papel = box.querySelector('[data-tut-papel]') || box.querySelector('.tut-papel');
    if (papel) {
      papel.classList.remove('tut-anim');
      papel.className = 'tut-papel tut-paso-' + pasoNum;
      if (paso.bloques_estilo === 'inline') papel.classList.add('tut-paso-vecinos');
      if (paso.tareas) papel.classList.add('tut-paso-misiones');
    }
    var heroEl = $('[data-tut-hero]');
    if (heroEl) {
      if (pasoNum === 1 || pasoNum === 3 || pasoNum === 4) {
        heroEl.hidden = false;
        heroEl.innerHTML = '<img class="tut-hero-img" src="' + esc(tutAssetUrl('illus-pueblo.png')) + '" alt=""/>';
      } else if (pasoNum === 2) {
        heroEl.hidden = false;
        heroEl.innerHTML = '<img class="tut-hero-img" src="' + esc(tutAssetUrl('icon-vecinos.png')) + '" alt=""/>';
      } else { heroEl.hidden = true; heroEl.innerHTML = ''; }
    }
    var oldBadge = papel ? papel.querySelector('.tut-badge') : null;
    if (oldBadge) oldBadge.remove();
    if (papel) {
      var badge = document.createElement('div');
      badge.className = 'tut-badge';
      badge.textContent = 'PRIMEROS PASOS';
      papel.insertBefore(badge, papel.querySelector('.tut-papel-cabecera'));
    }
    var oldPin = papel ? papel.querySelector('.tut-pin') : null;
    if (oldPin) oldPin.remove();
    if (papel) {
      var pin = document.createElement('span');
      pin.className = 'tut-pin';
      pin.setAttribute('aria-hidden', 'true');
      papel.insertBefore(pin, papel.firstChild);
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
            var cardKey = ['observa', 'propon', 'mira'][i] || '';
            sym.className = 'tut-bloque-sym tut-bloque-ico tut-bloque-ico--' + cardKey;
            sym.innerHTML = '<img src="' + esc(tutAssetUrl(TUT_CARD_ICONS[i])) + '" alt="" loading="lazy"/>';
            div.classList.add('tut-bloque--card');
          } else if (pasoNum === 3 && b.tit && TUT_ROW_ICONS[b.tit]) {
            var rowKey = ({ 'MAPA': 'mapa', 'VECINOS': 'vecinos', 'MENSAJITOS': 'mensajitos', 'NUEVO PLAN': 'plan' })[b.tit] || '';
            sym.className = 'tut-bloque-sym tut-bloque-ico tut-bloque-ico--' + rowKey;
            sym.innerHTML = '<img src="' + esc(tutAssetUrl(TUT_ROW_ICONS[b.tit])) + '" alt="" loading="lazy"/>';
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
    var papel = box.querySelector('.tut-papel') || box.querySelector('[data-tut-papel]');
    if (papel) {
      papel.className = 'tut-papel tut-paso-5';
    }
    var skipBtn = box.querySelector('.tut-skip');
    if (skipBtn) skipBtn.hidden = true;
    var finTit = tut.finale.tit || '';
    var titEl = $('[data-tut-fin-tit]');
    if (titEl) {
      titEl.innerHTML = '<span class="tut-tit-spark" aria-hidden="true"></span><span class="tut-tit-txt">' + esc(finTit) + '</span><span class="tut-tit-spark tut-tit-spark--r" aria-hidden="true"></span>';
    }
    var texto = tut.finale.txt || '';
    var partes = texto.split(/\n\n+/);
    var leadEl = $('[data-tut-fin-lead]');
    var restEl = $('[data-tut-fin-rest]');
    var textoEl = $('[data-tut-fin-texto]');
    if (leadEl) leadEl.textContent = partes[0] || '';
    if (restEl) restEl.textContent = partes.slice(1).join('\n\n');
    if (textoEl) textoEl.textContent = texto;
    var heroFin = $('[data-tut-fin-hero]');
    if (heroFin) {
      heroFin.hidden = false;
      heroFin.innerHTML = '<img class="tut-hero-img" src="' + esc(tutAssetUrl('illus-pueblo.png')) + '" alt=""/>';
    }
    var dots = $('[data-tut-pasos]', box);
    if (dots) {
      dots.innerHTML = '';
      for (var i = 0; i < 5; i++) { var s = document.createElement('span'); s.className = 'is-on'; dots.appendChild(s); }
    }
    var btn = $('[data-tut-fin-ok]');
    if (btn) btn.textContent = tut.finale.boton || 'Que empiece el tema';
    box.hidden = false;
    document.body.setAttribute('data-tut-finale', '1');
    syncScrollLock();
  }
  function capaHistoriaCelebracionAbierta() {
    var root = $('.play-root');
    return !!(root && root.getAttribute('data-capa') === 'historia_celebracion');
  }
  async function cerrarTutFinale() {
    /* aside[...]: nunca resuelve a <body>, hidden solo puede aplicar al modal. */
    var box = $('aside[data-tut-finale]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-finale');
    syncScrollLock();
    const res = await api('partida.tutorial_finale', {});
    if (res && res.tutorial && cacheEstado) {
      cacheEstado.tutorial = res.tutorial;
    }
    await refresh();
    if (res && res.historia) procesarCelebraciones(res.historia);
    if (!capaHistoriaCelebracionAbierta()) setCapa('');
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
    const estEl = $('[data-vida-modal-estado]');
    if (estEl) {
      let hint = '';
      if (vida && vida.critico) hint = 'El corazón se debilita...';
      else if (valor >= 80) hint = 'El pueblo respira con fuerza.';
      else if (valor <= 39) hint = 'El latido se siente tenue.';
      estEl.textContent = hint;
      estEl.hidden = !hint;
      estEl.className = 'vida-estado-pista mini' + (vida && vida.critico ? ' vida-estado--critica' : (valor >= 80 ? ' vida-estado--alta' : (valor <= 39 ? ' vida-estado--baja' : '')));
    }
    const modal = $('[data-aht-screen="vida_pueblo"]');
    if (modal) {
      modal.classList.toggle('vida-modal--critica', !!(vida && vida.critico));
      modal.classList.toggle('vida-modal--alta', valor >= 80 && !(vida && vida.critico));
      modal.classList.toggle('vida-modal--baja', valor <= 39 && !(vida && vida.critico));
    }
  }

  function setCapa(name) {
    const root = $('.play-root');
    const prev = (root && root.getAttribute('data-capa')) || '';

    // ââ BRIDGE V4 ââââââââââââââââââââââââââââââââââââââââââââââ
    // Si screen-manager gestiona esta screen, delegar lifecycle.
    // Esto asegura que stack/velo/scroll V4 se mantengan correctamente.
    if (window.AHTScreenManager) {
      // Abrir screen V4: delegar a open()
      if (name && window.AHTScreenManager.V4_SCREENS.has(name)) {
        if (window.AHTScreenManager.getCurrent() !== name) {
          window.AHTScreenManager.open(name, { silent: true });
        }
        // V4 ya manejó dock/scroll/history. Ejecutar SOLO renderers legacy.
        if (name !== 'mentes') mentesEncIdActivo = null;
        if (name === 'vida_pueblo') renderVidaPuebloModal();
        if (name === 'parejas') renderParejasModalList(parejasParaUI(cacheInsp || {}));
        if (name === 'inventario') renderInventario();
        if (name === 'historia') renderHistoriaPueblo();
        if (name === 'ajustes') syncAjustesUI();
        if (name === 'necesidades_global') { setCapa('vecinos'); setVecTab('cuidados'); return; }
        return;
      }
      // Cerrar screen V4: delegar a close()
      if (window.AHTScreenManager.isAnyOpen() && (!name || !window.AHTScreenManager.V4_SCREENS.has(name))) {
        window.AHTScreenManager.close();
        if (!window.AHTScreenManager.isAnyOpen()) {
          root.removeAttribute('data-capa');
        }
        syncScrollLock();
        if (name) { /* fall through to legacy setCapa */ } else { return; }
      }
    }
    // ââ FIN BRIDGE V4 ââââââââââââââââââââââââââââââââââââââââââ

    if (!name) root.removeAttribute('data-capa');
    else root.setAttribute('data-capa', name);
    if (name !== 'mentes') mentesEncIdActivo = null;
    if (name === 'vida_pueblo') renderVidaPuebloModal();
    if (name === 'parejas') renderParejasModalList(parejasParaUI(cacheInsp || {}));
    if (name === 'inventario') renderInventario();
    if (name === 'historia') renderHistoriaPueblo();
    if (name === 'ajustes') syncAjustesUI();
    if (name === 'necesidades_global') { setCapa('vecinos'); setVecTab('cuidados'); return; }
    if (name && name !== prev && !uiHistSilent) uiHistPush();
    $$('.dock button, .play-bottom-nav-btn').forEach(function (b) {
      const open = b.getAttribute('data-open');
      if (b.classList.contains('play-bottom-nav-btn')) return;
      b.classList.toggle('is-on', name ? open === name : !open);
    });
    syncBottomNav(name || '');
    syncScrollLock();
  }

// --- Inventario / Regalos (F1 + F2) ---
  let invSelObjeto = null;
  let invSelVecino = null;
  let invFichaRid = null;
  let invFichaNombre = '';
  let invEntregando = false;

  function abrirRegalosDesdeFicha(id, nombre) {
    invFichaRid = id || null;
    invFichaNombre = String(nombre || '');
    setCapa('inventario');
  }

// --- Historia del Pueblo ---
  async function renderHistoriaPueblo() {
    const root = $('.play-root');
    if (!root) return;
    const grid = $('[data-historia-grid]', root);
    const sub = $('[data-historia-sub]', root);
    if (!grid) return;
    try {
      const r = await api('historia.snapshot');
      if (!r || !r.ok) { grid.innerHTML = '<p class="mini">No se pudo cargar la historia.</p>'; return; }
      const h = r.historia;
      if (sub) sub.textContent = '\uD83D\uDCD6 ' + h.total_revelados + ' de ' + h.total_hitos + ' recuerdos';
      let html = '';
      for (const hito of h.hitos) {
        const cls = hito.revelado ? '' : ' historia-polaroid--bloqueada';
        html += '<div class="historia-polaroid' + cls + '" data-hito-id="' + esc(hito.id) + '">';
        html += '<span class="historia-orden">' + hito.orden + '</span>';
        html += '<div class="historia-img-wrap">';
        if (hito.revelado && hito.imagen_url) {
          html += '<img src="' + esc(hito.imagen_url) + '" alt="' + esc(hito.nombre) + '" loading="lazy"/>';
        } else {
          html += '<div class="historia-placeholder" aria-hidden="true">' + (hito.revelado ? '&#x1F4F7;' : '&#x1F512;') + '</div>';
        }
        html += '</div>';
        html += '<div class="historia-titulo">' + esc(hito.nombre) + '</div>';
        if (hito.revelado && hito.protagonistas && hito.protagonistas.length) {
          html += '<div class="historia-protagonistas">';
          for (const p of hito.protagonistas) {
            html += '<div class="historia-avatar">';
            if (p.retrato) {
              html += '<img class="historia-avatar-img" src="' + esc(p.retrato) + '" alt="' + esc(p.nombre) + '"/>';
            } else {
              html += '<div class="historia-avatar-img" style="display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#a09080;">' + esc((p.nombre || '?')[0]) + '</div>';
            }
            html += '<span class="historia-avatar-nombre">' + esc(p.nombre) + '</span>';
            html += '</div>';
          }
          html += '</div>';
          html += '<div class="historia-dia">D&iacute;a ' + hito.dia + '</div>';
        }
        html += '</div>';
      }
      grid.innerHTML = html;
    } catch (e) {
      grid.innerHTML = '<p class="mini">Error al cargar la historia.</p>';
    }
  }

  async function renderNecesidadesGlobal() {
    var body = $('[data-necesidades-global-body]');
    var vacio = $('[data-necesidades-global-vacio]');
    var filtersWrap = $('[data-necesidades-global-filters]');
    if (!body) return;
    body.innerHTML = '<p class="necg-vacio mini">Cargando...</p>';
    if (vacio) vacio.hidden = true;
    try {
      var r = await api('partida.necesidades_global');
      var data = r.ok && r.necesidades ? r.necesidades : null;
      if (!data || !data.residentes || !data.residentes.length) {
        body.innerHTML = '<p class="necg-vacio">No hay datos de necesidades a&uacute;n.</p>';
        if (filtersWrap) filtersWrap.innerHTML = '';
        return;
      }
      var allResidents = data.residentes;
      var nombres = { social: 'Socializar', diversion: 'Diversi\u00f3n', actividad: 'Actividad', calma: 'Calma' };
      var orden = ['social', 'diversion', 'actividad', 'calma'];

      var initialFilter = necgFiltroInicial || 'todos';
      necgFiltroInicial = '';

      var selectedResId = null;

      if (filtersWrap) {
        var fhtml = '<button type="button" class="necg-filt' + (initialFilter === 'todos' ? ' necg-filt--on' : '') + '" data-nec-filter="todos">Todos</button>';
        fhtml += '<button type="button" class="necg-filt' + (initialFilter === 'necesitan' ? ' necg-filt--on' : '') + '" data-nec-filter="necesitan">Con necesidad</button>';
        orden.forEach(function (nec) {
          fhtml += '<button type="button" class="necg-filt' + (initialFilter === nec ? ' necg-filt--on' : '') + '" data-nec-filter="' + nec + '">' + necIconHtml(nec, 16) + ' ' + nombres[nec] + '</button>';
        });
        filtersWrap.innerHTML = fhtml;
      }

      function getWorstBand(res) {
        var worst = 'bien';
        var worstVal = 101;
        orden.forEach(function (nec) {
          var n = res.necesidades[nec];
          if (n && n.valor < worstVal) { worstVal = n.valor; worst = n.banda; }
        });
        return worst;
      }

      function getWorstNec(res) {
        var worstId = null;
        var worstVal = 101;
        var worstBand = 'bien';
        orden.forEach(function (nec) {
          var n = res.necesidades[nec];
          if (n && n.banda !== 'bien' && n.valor < worstVal) {
            worstVal = n.valor;
            worstBand = n.banda;
            worstId = nec;
          }
        });
        return worstId ? { id: worstId, nombre: nombres[worstId], icono: necIconHtml(worstId, 14), valor: worstVal, banda: worstBand } : null;
      }

      function bandLabel(banda) {
        if (banda === 'en_rojo') return 'Muy bajo';
        if (banda === 'lo_necesita') return 'Algo bajo';
        if (banda === 'le_vendria_bien') return 'Normal';
        return 'Bien';
      }

      function avatarHtml(url, id, nombre, size) {
        var img = url || tokenDe(id);
        if (img) {
          return '<span class="necg-caro-avatar" style="width:' + size + 'px;height:' + size + 'px;min-width:' + size + 'px;min-height:' + size + 'px"><img src="' + esc(img) + '" alt=""/></span>';
        }
        var ini = (nombre || '?').charAt(0);
        return '<span class="necg-caro-avatar necg-caro-avatar--fallback" style="width:' + size + 'px;height:' + size + 'px;min-width:' + size + 'px;min-height:' + size + 'px">' + esc(ini) + '</span>';
      }

      function renderCarousel(filteredResidents) {
        var needsAttention = [];
        filteredResidents.forEach(function (res) {
          var worst = getWorstNec(res);
          if (worst) {
            needsAttention.push({ res: res, worst: worst });
          }
        });
        needsAttention.sort(function (a, b) { return a.worst.valor - b.worst.valor; });

        if (!needsAttention.length) {
          body.innerHTML = '<p class="necg-vacio">\u2728 Todos est\u00e1n bien por ahora.</p>';
          return;
        }

        var html = '';

        html += '<div class="necg-alertas">';
        html += '<h4 class="necg-alertas-title">Vecinos que necesitan atenci\u00f3n</h4>';
        html += '<p class="necg-alertas-hint">Pulsa en uno para ver sus necesidades.</p>';
        html += '<div class="necg-carousel">';
        needsAttention.forEach(function (item, idx) {
          var res = item.res;
          var w = item.worst;
          var isWorst = idx === 0;
          var isActive = res.id === selectedResId;
          var cardCls = 'necg-caro-card';
          if (isWorst) cardCls += ' necg-caro-card--worst';
          if (isActive || (!selectedResId && isWorst)) cardCls += ' is-active';
          html += '<div class="' + cardCls + '" data-necg-caro="' + esc(res.id) + '">';
          html += avatarHtml(res.retrato_url, res.id, res.nombre, 48);
          html += '<span class="necg-caro-nom">' + esc(res.nombre) + '</span>';
          html += '<span class="necg-caro-badge necg-caro-badge--' + w.banda + '">' + w.icono + ' ' + bandLabel(w.banda) + '</span>';
          html += '</div>';
        });
        html += '</div></div>';

        html += '<div class="necg-sep"></div>';

        var selId = selectedResId || needsAttention[0].res.id;
        var selRes = null;
        needsAttention.forEach(function (item) {
          if (item.res.id === selId) selRes = item.res;
        });
        if (!selRes) selRes = needsAttention[0].res;

        html += '<div class="necg-detail">';
        html += '<h4 class="necg-detail-title">Necesidades de ' + esc(selRes.nombre) + '</h4>';
        var hasAny = false;
        orden.forEach(function (nec) {
          var n = selRes.necesidades[nec];
          if (!n || n.banda === 'bien') return;
          hasAny = true;
          var pct = Math.max(0, Math.min(100, n.valor));
          var typeCls = 'necg-need-row--' + nec;
          html += '<div class="necg-need-group necg-need-group--' + nec + '">';
          html += '<div class="necg-need-row ' + typeCls + '">';
          html += '<span class="necg-need-icon">' + necIconHtml(nec, 22) + '</span>';
          html += '<span class="necg-need-name">' + nombres[nec] + '</span>';
          html += '<div class="necg-bar"><div class="necg-bar-fill necg-bar-fill--' + nec + '" style="width:' + pct + '%"></div></div>';
          html += '<span class="necg-need-val">' + Math.round(pct) + '%</span>';
          html += '</div>';
          if (n.copy) {
            html += '<p class="necg-need-copy">' + esc(n.copy) + '</p>';
          }
          html += '</div>';
        });
        if (!hasAny) {
          html += '<p class="necg-vacio">\u2728 Todas sus necesidades est\u00e1n cubiertas.</p>';
        }
        html += '</div>';

        body.innerHTML = html;

        body.addEventListener('click', function (e) {
          var caroCard = e.target.closest('[data-necg-caro]');
          if (caroCard) {
            var rid = caroCard.getAttribute('data-necg-caro');
            if (rid) {
              selectedResId = rid;
              renderCarousel(filteredResidents);
            }
            return;
          }
          var detailCard = e.target.closest('[data-necg-res]');
          if (detailCard) {
            var rid2 = detailCard.getAttribute('data-necg-res');
            if (rid2) abrirFicha(rid2);
          }
        });
      }

      function applyFilter(filter) {
        var filtered;
        if (filter === 'todos') {
          filtered = allResidents;
        } else if (filter === 'necesitan') {
          filtered = allResidents.filter(function (res) {
            return orden.some(function (nec) {
              var n = res.necesidades[nec];
              return n && (n.banda === 'lo_necesita' || n.banda === 'en_rojo');
            });
          });
        } else {
          filtered = allResidents.filter(function (res) {
            var n = res.necesidades[filter];
            return n && n.banda !== 'bien';
          });
        }
        renderCarousel(filtered);
      }

      applyFilter(initialFilter);

      if (filtersWrap) {
        filtersWrap.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-nec-filter]');
          if (!btn) return;
          var filter = btn.getAttribute('data-nec-filter');
          selectedResId = null;
          filtersWrap.querySelectorAll('.necg-filt').forEach(function (b) { b.classList.remove('necg-filt--on'); });
          btn.classList.add('necg-filt--on');
          applyFilter(filter);
        });
      }
    } catch (e) {
      body.innerHTML = '<p class="necg-vacio">Error al cargar necesidades.</p>';
    }
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
    actualizarInvNavBadge(items);
    const sub = $('[data-inv-sub]', root);
    if (sub) {
      sub.textContent = invFichaRid && r && r.residente_nombre
        ? 'Elige un detalle para ' + r.residente_nombre + '.'
        : 'Detalles guardados para regalar a los vecinos.';
      sub.hidden = false;
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
    $$('.inv-objeto', root).forEach(function (b) { b.classList.remove('is-sel'); });
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
      if (nom) vecinos.push({ id: id, nombre: nom, avatar: tokenDe(id) });
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
      if (v.avatar) {
        chip.innerHTML = '<img class="inv-vecino-avatar" src="' + esc(v.avatar) + '" alt=""/><span class="inv-vecino-nombre">' + esc(v.nombre) + '</span>';
      } else {
        chip.innerHTML = '<span class="inv-vecino-avatar inv-vecino-avatar--ini">' + esc((v.nombre || '?')[0]) + '</span><span class="inv-vecino-nombre">' + esc(v.nombre) + '</span>';
      }
      chip.addEventListener('click', function () {
        invSelVecino = v;
        $$('.inv-vecino', root).forEach(function (c) { c.classList.remove('is-sel'); });
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
    if (invEntregando) return;
    invEntregando = true;
    const btnEntregar = $('[data-inv-entregar]', root);
    const feedback = $('[data-inv-feedback]', root);
    if (btnEntregar) btnEntregar.disabled = true;
    try {
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
        const escena = (r && typeof r.escena === 'string') ? r.escena : '';
        const eco = (r && typeof r.eco_emocional === 'string') ? r.eco_emocional : '';
        const avatar = tokenDe(invSelVecino.id);
        const reaccion = r.reaccion || '';
        const isPos = reaccion === 'le_encanta' || reaccion === 'le_gusta';
        const isNeg = reaccion === 'no_le_gusta';
        const reaccionLabel = reaccion === 'le_encanta' ? 'Le encanta' : reaccion === 'le_gusta' ? 'Le gusta' : reaccion === 'no_le_gusta' ? 'No le gusta' : reaccion === 'mas_o_menos' ? 'Mas o menos' : '';
        let avatarHtml = '';
        if (avatar) {
          avatarHtml = '<img class="inv-feedback-avatar" src="' + esc(avatar) + '" alt=""/>';
        } else {
          avatarHtml = '<span class="inv-feedback-avatar inv-feedback-avatar--ini">' + esc((invSelVecino.nombre || '?')[0]) + '</span>';
        }
        let reaccionBadge = reaccionLabel ? '<span class="inv-feedback-badge inv-feedback-badge--' + esc(reaccion) + '">' + esc(reaccionLabel) + '</span>' : '';
        feedback.hidden = false;
        feedback.innerHTML =
          '<div class="inv-feedback-card">' +
            '<div class="inv-feedback-cara">' + avatarHtml + '</div>' +
            '<div class="inv-feedback-body">' +
              '<span class="inv-feedback-nombre">' + esc(invSelVecino.nombre) + '</span>' +
              reaccionBadge +
              (escena ? '<span class="inv-feedback-escena">' + esc(escena) + '</span>' : '') +
              '<span class="inv-feedback-texto">' + esc(texto) + '</span>' +
              (eco ? '<span class="inv-feedback-eco">' + esc(eco) + '</span>' : '') +
              (extra ? '<span class="inv-feedback-extra">' + extra + '</span>' : '') +
            '</div>' +
          '</div>';
        feedback.classList.toggle('is-mal', isNeg);
        feedback.classList.toggle('is-bien', isPos);
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
    } finally {
      invEntregando = false;
    }
  }
  // --- fin Inventario / Regalos (F1) ---

  /** Catálogo estático de regalos (data/catalogos/regalos.json) para assets en mensajitos. */
  let cacheCatalogoRegalos = null;
  let cacheCatalogoRegalosCargando = null;

  function catalogoRegalosUiVersion() {
    var script = document.querySelector('script[src*="play-v3.js"]');
    if (script && script.src) {
      var m = script.src.match(/[?&]v=([^&]+)/);
      if (m) return decodeURIComponent(m[1]);
    }
    return '';
  }

  function ensureCatalogoRegalos() {
    if (cacheCatalogoRegalos) return Promise.resolve(cacheCatalogoRegalos);
    if (cacheCatalogoRegalosCargando) return cacheCatalogoRegalosCargando;
    cacheCatalogoRegalosCargando = fetch('data/catalogos/regalos.json?v=' + encodeURIComponent(catalogoRegalosUiVersion()))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        const map = { byId: {}, byNombre: {} };
        (data && data.items || []).forEach(function (it) {
          if (!it || !it.id) return;
          const row = {
            id: String(it.id),
            nombre: String(it.nombre || it.id),
            url: 'assets/play-v3/' + String(it.asset || ('regalos/' + it.id + '.png'))
          };
          map.byId[row.id] = row;
          map.byNombre[row.nombre.trim().toLowerCase()] = row;
        });
        cacheCatalogoRegalos = map;
        return map;
      })
      .catch(function () {
        cacheCatalogoRegalos = { byId: {}, byNombre: {} };
        return cacheCatalogoRegalos;
      })
      .finally(function () { cacheCatalogoRegalosCargando = null; });
    return cacheCatalogoRegalosCargando;
  }

  function mensajitoEsObjetoRecibido(m) {
    const t = String((m && m.tipo) || '');
    return t === 'regalo_recompensa' || t === 'detallito_sorpresa' || t === 'regalito_recompensa';
  }

  function mensajitoObjetoNombreDeTexto(m) {
    const t = String((m && m.texto) || '');
    const patterns = [
      /te manda\s+(.+?)\.\s*(?:Guardado|$)/i,
      /te deja\s+(.+?)\s+como agradecimiento/i,
      /encuentras\s+(.+?)\./i,
      /del d[ií]a:\s*(.+?)\.\s*(?:Guardado|$)/i,
      /Detallito merecido:\s*(.+?)\./i,
      /sorpresa del d[ií]a:\s*(.+?)\./i
    ];
    for (let i = 0; i < patterns.length; i++) {
      const hit = t.match(patterns[i]);
      if (hit && hit[1]) return hit[1].trim();
    }
    return '';
  }

  function mensajitoObjetoIdDe(m) {
    if (!m || typeof m !== 'object') return '';
    const origen = m.origen || {};
    if (origen.objeto_id) return String(origen.objeto_id);
    const eid = String(origen.evento_id || '');
    if (eid.indexOf('detallito_sorpresa:') === 0) {
      return eid.slice('detallito_sorpresa:'.length);
    }
    if (eid.indexOf('regalito_recompensa:') === 0) {
      return eid.slice('regalito_recompensa:'.length);
    }
    return '';
  }

  function mensajitoObjetoVistaDe(m, catalogo) {
    if (!mensajitoEsObjetoRecibido(m)) return null;
    const origen = (m && m.origen) || {};
    if (origen.objeto_nombre) {
      return {
        id: String(origen.objeto_id || ''),
        nombre: String(origen.objeto_nombre),
        url: String(origen.objeto_imagen || ''),
        desc: origen.regalito_origen === 'historia_pueblo' ? 'Historia del Pueblo' : (origen.regalito_origen === 'misiones_3x3' ? 'Tres misiones del día' : '')
      };
    }
    const cat = catalogo || cacheCatalogoRegalos || { byId: {}, byNombre: {} };
    const idDirecto = mensajitoObjetoIdDe(m);
    if (idDirecto && cat.byId[idDirecto]) return cat.byId[idDirecto];
    const nomTxt = mensajitoObjetoNombreDeTexto(m);
    if (nomTxt && cat.byNombre[nomTxt.toLowerCase()]) return cat.byNombre[nomTxt.toLowerCase()];
    if (nomTxt) return { id: '', nombre: nomTxt, url: '' };
    return null;
  }

  function htmlMensajitoRegaloObjeto(vista) {
    if (!vista || !vista.nombre) return '';
    const imgHtml = vista.url
      ? '<img class="aht-msg-gift-icon" src="' + esc(vista.url) + '" alt="" loading="lazy" decoding="async"/>'
      : '<span class="aht-msg-gift-icon">\uD83C\uDF81</span>';
    return '<div class="aht-msg-gift" aria-label="Objeto recibido">' +
      imgHtml +
      '<div><p class="aht-msg-gift-name">' + esc(vista.nombre) + '</p>' +
      (vista.desc ? '<p class="aht-msg-gift-desc">' + esc(vista.desc) + '</p>' : '') +
      '</div></div>';
  }

  function dineroTxt(insp, estado) {
    const eco = (insp && insp.economia && insp.economia.dinero) ? insp.economia.dinero.balance : null;
    const cel = estado && estado.celeste ? estado.celeste.dinero : null;
    const v = eco !== null && eco !== undefined ? eco : cel;
    if (v === null || v === undefined || v === '') return 'â€”';
    return String(Math.round(Number(v))) + ' â¬';
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
    if (mensajitoEsObjetoRecibido(m) && !remitenteIdDe(m)) return '';
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
    if (Array.isArray(m.opciones_consejo) && m.opciones_consejo.length &&
        (m.estado_decision || '') === 'pendiente' &&
        (m.estado || '') === 'pendiente') return true;
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
    const soloLectura = ['respuesta_plan', 'peticion_resultado', 'marcha_publica', 'marcha_despedida', 'legado_despedida'];
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

  function mensajitoTintDeRemitente(id) {
    const s = String(id || '');
    if (!s) return 0;
    let h = 0;
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    return (Math.abs(h) % 4);
  }

  var MSG_PAPERS = ['rose', 'blue', 'green', 'cream', 'lilac'];
  function msgFnv1a(str) {
    var s = String(str || '');
    var h = 0x811c9dc5;
    for (var i = 0; i < s.length; i++) {
      h ^= s.charCodeAt(i);
      h = Math.imul(h, 0x01000193);
    }
    return h >>> 0;
  }
  function msgPaperDeRemitente(rid, nombre) {
    return MSG_PAPERS[msgFnv1a(String(rid || nombre || '')) % MSG_PAPERS.length];
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
    const base = cls || 'aht-msg-avatar';
    const dataAttr = rid ? ' data-persona-id="' + esc(rid) + '"' : '';
    if (tok) return '<img class="' + base + '" src="' + esc(tok) + '" alt=""' + dataAttr + '/>';
    return '<span class="' + base + ' cara-ini"' + dataAttr + '>' + esc(inicialDe(nombre || '?')) + '</span>';
  }

  function htmlAvatarEleccion(personaje_id, nombre, cls) {
    const tok = personaje_id ? tokenDe(personaje_id) : null;
    const base = cls || 'aht-msg-avatar aht-msg-avatar--mini';
    const dataAttr = personaje_id ? ' data-persona-id="' + esc(personaje_id) + '"' : '';
    if (tok) {
      return '<span class="msg-eleccion-avatar-wrap"><img class="' + base + '" src="' + esc(tok) + '" alt=""' + dataAttr + '/></span>';
    }
    return '<span class="msg-eleccion-avatar-wrap"><span class="' + base + ' cara-ini"' + dataAttr + '>' + esc(inicialDe(nombre || '?')) + '</span></span>';
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
    if (diaLbl && hora) return diaLbl + ' Â· ' + hora;
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


  function htmlPerfilCandidato(m) {
    const p = m && m.perfil_candidato;
    if (!p || typeof p !== 'object' || !p.nombre) return '';
    const retrato = p.retrato_url
      ? '<img class="llegada-perfil-foto" src="' + esc(p.retrato_url) + '" alt=""/>'
      : '<span class="llegada-perfil-foto llegada-perfil-foto--ini">' + esc(inicialDe(p.nombre)) + '</span>';
    const chips = [];
    if (p.edad) chips.push('<span class="llegada-perfil-chip">' + esc(String(p.edad)) + ' a\u00f1os</span>');
    if (p.hobby_visible) chips.push('<span class="llegada-perfil-chip">' + esc(p.hobby_visible) + '</span>');
    (p.rasgos_visibles || []).forEach(function (r) {
      if (r) chips.push('<span class="llegada-perfil-chip">' + esc(r) + '</span>');
    });
    return '<div class="llegada-perfil" data-llegada-perfil="1">' + retrato +
      '<div class="llegada-perfil-body">' +
      '<div class="llegada-perfil-nom">' + esc(p.nombre) + ' <span class="llegada-perfil-tag">Nuevo candidato</span></div>' +
      (p.presentacion ? '<p class="llegada-perfil-txt">' + esc(p.presentacion) + '</p>' : '') +
      (chips.length ? '<div class="llegada-perfil-chips">' + chips.join('') + '</div>' : '') +
      '</div></div>';
  }

  let llegadaCelebradaKey = null;

  function pintarLlegadaCelebracionSiToca() {
    const insp = cacheInsp || {};
    const llegadas = insp.llegadas || {};
    const pres = llegadas.presentacion && llegadas.presentacion.ultima;
    if (!pres || !pres.residente_id) return;
    const key = String(pres.residente_id) + '|' + String(pres.dia || 0) + '|' + String(pres.hora || 0);
    if (llegadaCelebradaKey === key) return;
    try {
      if (sessionStorage.getItem('aht_llegada_celebra') === key) {
        llegadaCelebradaKey = key;
        return;
      }
    } catch (e) {}
    llegadaCelebradaKey = key;
    try { sessionStorage.setItem('aht_llegada_celebra', key); } catch (e) {}
    const nom = pres.nombre || nombreDe(pres.residente_id) || 'Un nuevo vecino';
    let txt = nom + ' se ha incorporado al pueblo.';
    if (pres.acompanante_id) {
      const nomA = nombreDe(pres.acompanante_id);
      if (nomA) txt += ' ' + nomA + ' le ense\u00f1a Villaborde.';
    }
    toast(txt);
  }

  // ââ Celebración de historia del pueblo âââââââââââââââââââââ
  const celebracionesConsumidas = new Set();
  let colaCelebraciones = [];
  let celebracionHitoActual = '';
  let celebracionRecompensaActual = null;

  function procesarCelebraciones(historia) {
    const celebs = (historia && historia.celebraciones) || [];
    if (!celebs.length) return;
    for (const c of celebs) {
      if (celebracionesConsumidas.has(c.hito_id)) continue;
      colaCelebraciones.push(c);
    }
    mostrarSiguienteCelebracion();
  }

  function mostrarSiguienteCelebracion() {
    if (capaHistoriaCelebracionAbierta()) return;
    if (enTutorialPrimerosPasos()) return;
    if (document.body.hasAttribute('data-tut-activo')) return;
    if (document.body.hasAttribute('data-tut-finale')) return;
    let c = null;
    while (colaCelebraciones.length) {
      c = colaCelebraciones.shift();
      if (!celebracionesConsumidas.has(c.hito_id)) break;
      c = null;
    }
    if (!c) return;
    celebracionHitoActual = c.hito_id || '';
    renderCelebracion(c);
    setCapa('historia_celebracion');
  }

  function renderCelebracion(c) {
    const img = $('[data-historia-celebracion-img]');
    const texto = $('[data-historia-celebracion-texto]');
    const protWrap = $('[data-historia-celebracion-protagonistas]');
    const recDiv = $('[data-historia-celebracion-recompensa]');
    const recObj = $('[data-historia-celebracion-recompensa-objeto]');
    const diaDiv = $('[data-historia-celebracion-dia]');
    if (img) { img.src = c.imagen || ''; img.alt = c.nombre || ''; }
    if (texto) texto.textContent = c.texto_narrativo || 'Primer recuerdo del pueblo descubierto.';
    if (diaDiv) diaDiv.textContent = c.dia ? 'D\u00eda ' + c.dia : '';
    if (protWrap) {
      let ph = '';
      const prots = c.protagonistas || [];
      for (let i = 0; i < prots.length; i++) {
        const p = prots[i];
        ph += '<span class="histdet-protagonista">';
        if (p.retrato) {
          ph += '<span class="histdet-protagonista-avatar-wrap"><img class="histdet-protagonista-avatar" src="' + esc(p.retrato) + '" alt="' + esc(p.nombre) + '"/></span>';
        } else {
          ph += '<div class="histdet-protagonista-avatar histdet-protagonista-avatar--fallback">' + esc((p.nombre || '?')[0]) + '</div>';
        }
        ph += '<span class="histdet-protagonista-nombre">' + esc(p.nombre) + '</span>';
        ph += '</span>';
      }
      protWrap.innerHTML = ph;
    }
    if (recDiv) {
      const recImg = $('[data-historia-celebracion-recompensa-img]');
      if (c.recompensa && c.recompensa.objeto_nombre) {
        recDiv.hidden = false;
        if (recObj) recObj.textContent = c.recompensa.objeto_nombre + (c.recompensa.cantidad > 1 ? ' \u00d7' + c.recompensa.cantidad : '');
        if (recImg) {
          if (c.recompensa.objeto_imagen) {
            recImg.src = c.recompensa.objeto_imagen;
            recImg.alt = c.recompensa.objeto_nombre;
            recImg.hidden = false;
          } else recImg.hidden = true;
        }
      } else {
        recDiv.hidden = true;
        if (recImg) recImg.hidden = true;
      }
    }
    celebracionRecompensaActual = c.recompensa || null;
  }


  function animarRecompensaAlInventario(recompensa) {
    return new Promise(function (resolve) {
      if (!recompensa || !recompensa.objeto_imagen) { resolve(); return; }
      var fly = document.createElement('div');
      fly.className = 'histcele-recompensa-fly';
      fly.innerHTML = '<img src="' + esc(recompensa.objeto_imagen) + '" alt="' + esc(recompensa.objeto_nombre || '') + '"/>';
      document.body.appendChild(fly);
      requestAnimationFrame(function () {
        fly.classList.add('is-on');
        setTimeout(function () { fly.remove(); resolve(); }, 720);
      });
    });
  }

  async function postCelebracionRecompensaAnim(hitoId, ackResult) {
    if (!hitoId || !ackResult || !ackResult.animacion_pendiente) return;
    var rec = ackResult.recompensa || celebracionRecompensaActual;
    if (!rec || !rec.objeto_imagen) return;
    await animarRecompensaAlInventario(rec);
    await api('historia.recompensa_anim_ack', { hito_id: hitoId });
  }

  async function celebracionClose(hitoId) {
    if (!hitoId) {
      setCapa('');
      return;
    }
    var ackResult = await api('historia.celebrar_ack', { hito_id: hitoId });
    if (!ackResult || ackResult.ok === false || !ackResult.ack_ok) {
      console.warn('[AHT] celebrar_ack failed â€” celebration NOT consumed', { hito_id: hitoId, result: ackResult });
      return;
    }
    celebracionesConsumidas.add(hitoId);
    colaCelebraciones = colaCelebraciones.filter(function (c) { return c.hito_id !== hitoId; });
    setCapa('');
    await postCelebracionRecompensaAnim(hitoId, ackResult);
    celebracionRecompensaActual = null;
    setTimeout(mostrarSiguienteCelebracion, 400);
  }

  async function celebracionIrAlbum() {
    const hitoId = celebracionHitoActual;
    if (!hitoId) {
      setCapa('historia');
      return;
    }
    var ackResult = await api('historia.celebrar_ack', { hito_id: hitoId });
    if (!ackResult || ackResult.ok === false || !ackResult.ack_ok) {
      console.warn('[AHT] celebrar_ack failed â€” celebration NOT consumed', { hito_id: hitoId, result: ackResult });
      return;
    }
    celebracionesConsumidas.add(hitoId);
    colaCelebraciones = colaCelebraciones.filter(function (c) { return c.hito_id !== hitoId; });
    await postCelebracionRecompensaAnim(hitoId, ackResult);
    celebracionRecompensaActual = null;
    setCapa('historia');
    renderHistoriaPueblo().then(function () {
      setTimeout(function () {
        const pol = $('[data-hito-id="' + hitoId + '"]');
        if (pol) pol.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }, 200);
    });
    setTimeout(mostrarSiguienteCelebracion, 600);
  }

  let historiaDetalleHitoActual = '';

  async function historiaDetalleAbrir(hitoId) {
    try {
      const r = await api('historia.snapshot');
      if (!r || !r.ok) return;
      const hito = (r.historia.hitos || []).find(function (h) { return h.id === hitoId && h.revelado; });
      if (!hito) return;
      historiaDetalleHitoActual = hitoId;
      const img = $('[data-historia-detalle-img]');
      const texto = $('[data-historia-detalle-texto]');
      const protWrap = $('[data-historia-detalle-protagonistas]');
      const diaDiv = $('[data-historia-detalle-dia]');
      if (img) { img.src = hito.imagen_url || ''; img.alt = hito.nombre || ''; }
      if (texto) texto.textContent = hito.texto_narrativo || '';
      if (protWrap) {
        let ph = '';
        const prots = hito.protagonistas || [];
        for (let i = 0; i < prots.length; i++) {
          const p = prots[i];
          ph += '<span class="histdet-protagonista">';
          if (p.retrato) {
            ph += '<span class="histdet-protagonista-avatar-wrap"><img class="histdet-protagonista-avatar" src="' + esc(p.retrato) + '" alt="' + esc(p.nombre) + '"/></span>';
          } else {
            ph += '<div class="histdet-protagonista-avatar histdet-protagonista-avatar--fallback">' + esc((p.nombre || '?')[0]) + '</div>';
          }
          ph += '<span class="histdet-protagonista-nombre">' + esc(p.nombre) + '</span>';
          ph += '</span>';
        }
        protWrap.innerHTML = ph;
      }
      if (diaDiv) diaDiv.textContent = hito.dia ? 'D\u00eda ' + hito.dia : '';
      var recWrap = $('[data-historia-detalle-recompensa]');
      var recImg = $('[data-historia-detalle-recompensa-img]');
      var recNom = $('[data-historia-detalle-recompensa-nombre]');
      if (recWrap) {
        if (hito.recompensa && hito.recompensa.objeto_nombre) {
          recWrap.hidden = false;
          if (recNom) recNom.textContent = hito.recompensa.objeto_nombre;
          if (recImg) {
            if (hito.recompensa.objeto_imagen) {
              recImg.src = hito.recompensa.objeto_imagen;
              recImg.alt = hito.recompensa.objeto_nombre;
              recImg.hidden = false;
            } else recImg.hidden = true;
          }
        } else recWrap.hidden = true;
      }
      setCapa('historia_detalle');
    } catch (e) { /* noop */ }
  }

  function historiaDetalleClose() {
    setCapa('historia');
  }

  function mensajitoPlazoLabel(m) {
    if (!m || m.estado_pueblo === 'caducada') return '';
    const raw = String(m.plazo_humano || '').trim();
    if (!raw || raw === 'Cuando puedas.') return '';
    if (raw === 'El tiempo se acaba.') return 'Últimas horas';
    let label = raw.replace(/^Te quedan\s+/i, 'Quedan ').replace(/^Te queda\s+/i, 'Queda ');
    if (/^quedan?\s+\d+\s*h/i.test(label)) {
      return label.charAt(0).toUpperCase() + label.slice(1);
    }
    return raw;
  }

  function mensajitoEstadoNatural(m) {
    const pueblo = m.estado_pueblo || '';
    const tipo = String(m.tipo || '');
    const familia = String(m.familia_mensajito || '');
    const origenTipo = String((m.origen && m.origen.tipo_evento) || '');
    if (pueblo === 'cumplida') {
      if (tipo === 'peticion_resultado' || origenTipo === 'peticion_resultado') return 'Petición resuelta';
      return 'Hecho';
    }
    if (pueblo === 'caducada') {
      if (tipo === 'peticion_resultado' || origenTipo === 'peticion_resultado') return '';
      return 'Ya no necesita ayuda';
    }
    if ((m.estado || '') === 'en_espera') return 'En espera';
    if ((m.estado || '') === 'resuelto' && !mensajitoTieneAccionReal(m)) {
      if (familia === 'f_seguimiento') return 'Seguimiento cerrado';
      if (familia === 'cierre_evento_pueblo') return 'Evento cerrado';
      return 'Cerrado';
    }
    return '';
  }

  function mensajitoEstadoIcono(txt) {
    if (!txt) return '';
    if (/resuelta|hecho|cerrado/i.test(txt)) return '\u2713';
    if (/espera|ya no/i.test(txt)) return '\u25CB';
    return '\u2022';
  }


  function mensajitoTsOrden(m) {
    if (!m) return 0;
    const dia = (m.ts_juego && m.ts_juego.dia) || m.dia || 0;
    const hora = typeof mensajitoHora === 'function' ? mensajitoHora(m) : 0;
    return dia * 24 + hora;
  }

  function mensajitoPermiteOrigenPorHilo(m) {
    if (!m) return false;
    const hilo = String(m.hilo_id || '').trim();
    if (!hilo) return false;
    const tipo = String(m.tipo || '');
    const fam = String(m.familia_mensajito || '');
    if (tipo === 'peticion_resultado') return true;
    if (fam === 'f_seguimiento' || fam === 'cierre_evento_pueblo') return true;
    if (tipo === 'respuesta_plan') return true;
    return false;
  }

  function mensajitoBuscarOrigen(m, allMsgs) {
    if (!m) return null;
    const mid = String(m.mensaje_origen_id || '').trim();
    if (mid) {
      const found = (allMsgs || []).find(function (x) { return x && String(x.id) === mid; });
      if (found) return found;
    }
    const pid = String(m.peticion_id || '').trim();
    const tipo = String(m.tipo || '');
    const origenTipo = String((m.origen && m.origen.tipo_evento) || '');
    if (pid && (tipo === 'peticion_resultado' || origenTipo === 'peticion_resultado')) {
      const candidatos = (allMsgs || []).filter(function (x) {
        if (!x || x.id === m.id) return false;
        if (String(x.peticion_id || '') !== pid) return false;
        const t = String(x.tipo || '');
        const fam = String(x.familia_mensajito || '');
        return t === 'peticion' || fam === 'f_peticion' || fam === 'f_presentacion';
      }).sort(function (a, b) { return mensajitoTsOrden(a) - mensajitoTsOrden(b); });
      if (candidatos.length) return candidatos[0];
    }
    const hilo = String(m.hilo_id || '').trim();
    if (hilo && mensajitoPermiteOrigenPorHilo(m)) {
      const miTs = mensajitoTsOrden(m);
      const candidatos = (allMsgs || []).filter(function (x) {
        if (!x || x.id === m.id) return false;
        if (String(x.hilo_id || '') !== hilo) return false;
        return mensajitoTsOrden(x) < miTs;
      }).sort(function (a, b) { return mensajitoTsOrden(a) - mensajitoTsOrden(b); });
      if (candidatos.length) return candidatos[candidatos.length - 1];
    }
    return null;
  }

  function mensajitoTituloHilo(origen) {
    if (!origen) return 'Mensaje anterior';
    const fam = String(origen.familia_mensajito || '');
    const nom = nombrePublicoDe(origen);
    if (fam === 'f_peticion' || fam === 'f_presentacion' || origen.tipo === 'peticion') {
      return 'Petición de ' + nom;
    }
    if (fam === 'anuncio_evento_pueblo') return 'Anuncio del evento';
    if (fam === 'f_colectivo') return 'Propuesta del pueblo';
    if (fam === 'f_seguimiento') return 'Consejo anterior';
    return 'Mensaje de ' + nom;
  }

  function htmlMensajitoHilo(origen) {
    if (!origen) return '';
    const nomOrig = nombrePublicoDe(origen);
    let cuerpo = cuerpoMensajito(origen, nomOrig);
    if (cuerpo.length > 120) cuerpo = cuerpo.slice(0, 117) + '\u2026';
    const cuando = mensajitoCuandoLabel(origen);
    return '<div class="aht-msg-thread" aria-label="Contexto del mensaje">' +
      '<div class="aht-msg-thread-origin">' +
      '<span class="aht-msg-thread-label">' + esc(mensajitoTituloHilo(origen)) + '</span>' +
      '<p class="aht-msg-thread-text">\u201C' + esc(cuerpo) + '\u201D</p>' +
      (cuando ? '<span class="aht-msg-thread-date">' + esc(cuando) + '</span>' : '') +
      '</div>' +
      '<div class="aht-msg-thread-arrow" aria-hidden="true"></div>' +
      '</div>';
  }

  function mensajitoCtaAccionLabel(m, accionId, etiquetaDefecto) {
    const fam = String(m.familia_mensajito || '');
    const datos = (m.datos_familia && typeof m.datos_familia === 'object') ? m.datos_familia : {};
    const plantilla = String(datos.plantilla_id || '');
    switch (accionId) {
      case 'organizar_algo':
        if (fam === 'f_duda_permanencia') return 'Ayudarle a quedarse';
        if (fam === 'f_alerta_vecinal') {
          var _obsId = datos.observado_id || '';
          var _obsRes = (cacheInsp && cacheInsp.residentes && cacheInsp.residentes[_obsId]) || {};
          var _obsGen = (_obsRes.identidad_publica && _obsRes.identidad_publica.genero) || '';
          return 'Organizar algo con ' + (_obsGen === 'mujer' ? 'ella' : 'él');
        }
        return 'Organizar plan';
      case 'organizar_encargo':
        if (fam === 'f_presentacion' || plantilla === 'conocer_a_alguien') return 'Elegir plan';
        if (plantilla === 'algo_distinto') return 'Organizar algo distinto';
        return 'Ayudarle a organizarlo';
      case 'organizar_cumple':
        return 'Organizar su cumple';
      case 'investigar':
        return 'Ver perfil';
      case 'mediar_reparar':
        return 'Mediar entre ellos';
      case 'participar_cumple':
        return 'Felicitarle';
      default:
        return etiquetaDefecto || '';
    }
  }

  function mensajitoCtaOrganizarLabel(m) {
    const preset = m.preset_organizar;
    if (!preset || typeof preset !== 'object') return 'Organizar plan';
    const modo = String(preset.modo || preset.tipo || '').toLowerCase();
    const fam = String(m.familia_mensajito || '');
    if (fam === 'f_presentacion' || modo === 'presentar') return 'Elegir plan';
    if (modo === 'pareja') return 'Organizar quedada';
    if (modo === 'solo') return 'Organizar plan';
    return 'Ayudarle a organizarlo';
  }

  function mensajitoCtaDestinoLabel(m) {
    const tipo = String(m.tipo || '');
    if (tipo === 'respuesta_plan') return 'Leer respuesta';
    if (tipo === 'peticion_resultado') return '';
    if (tipo === 'marcha_publica' || tipo === 'marcha_despedida' || tipo === 'legado_despedida') return '';
    if (tipo === 'detallito_sorpresa') return '';
    if (tipo === 'regalito_recompensa') return '';
    if (tipo === 'regalo_recompensa') {
      if (!mensajitoDestinoFicha(m)) return '';
      return mensajitoEstaLeido(m) ? 'Ver perfil' : 'Entendido';
    }
    if (!mensajitoDestinoFicha(m)) return '';
    return mensajitoEstaLeido(m) ? 'Ver perfil' : 'Abrir mensaje';
  }

  function htmlOpcionesEleccion(titulo, opcionesHtml, extraCls) {
    return '<div class="aht-msg-choice' + (extraCls ? ' ' + extraCls : '') + '">' +
      '<span class="aht-msg-choice-title">' + esc(titulo) + '</span>' +
      '<div class="aht-msg-choice-list">' + opcionesHtml + '</div>' +
      '</div>';
  }

  function htmlAccionesMensajito(m) {
    if (Array.isArray(m.opciones_consejo) && m.opciones_consejo.length &&
        (m.estado_decision || '') === 'pendiente' &&
        (m.estado || '') === 'pendiente') {
      const tit = m.consejo_titulo || '\u00bfQu\u00e9 le dices?';
      const optsHtml = m.opciones_consejo.map(function (o) {
        if (!o || !o.id) return '';
        return '<button type="button" class="aht-msg-choice-opt" data-consejo-opcion="' + esc(o.id) + '">' +
          '<span class="aht-msg-choice-opt-copy"><span class="aht-msg-choice-opt-name">' + esc(o.etiqueta || o.id) + '</span></span>' +
          '<span class="aht-msg-choice-opt-arrow" aria-hidden="true">\u203A</span></button>';
      }).join('');
      return '<div class="aht-msg-actions aht-msg-choice aht-msg-choice--consejo">' + htmlOpcionesEleccion(tit, optsHtml) + '</div>';
    }
    if (Array.isArray(m.selector_opciones) && m.selector_opciones.length &&
        m.selector_estado === 'pendiente' &&
        (m.estado_pueblo || 'pendiente') === 'pendiente' && (m.estado || '') === 'pendiente') {
      const titSel = m.selector_titulo || '\u00bfA qui\u00e9n le presentar\u00edas?';
      const optsHtml = m.selector_opciones.map(function (o) {
        if (!o || !o.personaje_id) return '';
        const hint = o.pista ? '<span class="aht-msg-choice-opt-hint">' + esc(o.pista) + '</span>' : '';
        return '<button type="button" class="aht-msg-choice-opt" data-elegir-persona="' + esc(o.personaje_id) + '">' +
          htmlAvatarEleccion(o.personaje_id, o.nombre) +
          '<span class="aht-msg-choice-opt-copy"><span class="aht-msg-choice-opt-name">' + esc(o.nombre || '') + '</span>' + hint + '</span>' +
          '<span class="aht-msg-choice-opt-arrow" aria-hidden="true">\u203A</span></button>';
      }).join('');
      return '<div class="aht-msg-actions aht-msg-choice">' + htmlOpcionesEleccion(titSel, optsHtml) + '</div>';
    }
    if (m.tipo === 'candidato_llegada' &&
        (m.estado || '') === 'pendiente' &&
        (m.estado_decision || '') !== 'resuelto') {
      const opts = Array.isArray(m.acompanantes_opciones) ? m.acompanantes_opciones : [];
      const titA = m.selector_titulo_acompanante || '\u00bfQui\u00e9n le ense\u00f1a Villaborde?';
      const optsHtml = opts.map(function (o) {
        if (!o || !o.personaje_id) return '';
        const hint = o.pista ? '<span class="aht-msg-choice-opt-hint">' + esc(o.pista) + '</span>' : '';
        return '<button type="button" class="aht-msg-choice-opt llegada-acomp-opt" data-llegada-acomp="' + esc(o.personaje_id) + '">' +
          htmlAvatarEleccion(o.personaje_id, o.nombre) +
          '<span class="aht-msg-choice-opt-copy"><span class="aht-msg-choice-opt-name">' + esc(o.nombre || '') + '</span>' + hint + '</span>' +
          '<span class="aht-msg-choice-opt-arrow" aria-hidden="true">\u203A</span></button>';
      }).join('');
      return '<div class="aht-msg-actions aht-msg-choice llegada-acciones">' +
        htmlOpcionesEleccion(titA, optsHtml || '<p class="llegada-sin-acomp">Nadie libre ahora para la bienvenida.</p>') +
        '<div class="llegada-acciones-pie">' +
        '<button type="button" class="aht-msg-btn aht-msg-btn--soft" data-llegada-rechazar="1">Ahora no</button>' +
        '</div></div>';
    }
    const acciones = Array.isArray(m.acciones_ui) ? m.acciones_ui : [];
    if (!acciones.length) return '';
    return '<div class="aht-msg-actions">' + acciones.map(function (a) {
      const suave = (a.estilo || '') === 'suave';
      const cls = 'aht-msg-btn aht-msg-btn--primary' + (suave ? ' aht-msg-btn--soft' : '');
      const lab = mensajitoCtaAccionLabel(m, a.id || '', a.etiqueta || a.id || '');
      return '<button type="button" class="' + cls + '" data-accion-id="' + esc(a.id || '') + '">' +
        esc(lab) + '</button>';
    }).join('') + '</div>';
  }

  async function resolverAccionMensajito(m, accionId, extra) {
    if (!m || !m.id || !accionId) return false;
    if (accionId === 'elegir_persona') {
      const pid = (extra && extra.personaje_id) || '';
      if (!pid) {
        toast('Selecciona una persona primero.');
        return false;
      }
    }
    const payload = { mensaje_id: m.id, accion: accionId };
    if (extra && typeof extra === 'object') Object.assign(payload, extra);
    const r = await api('buzon.resolver', payload);
    if (!r.ok) {
      toast(r.mensaje_ui || r.error || 'No se pudo completar la acci\u00f3n.');
      return false;
    }
    if (r.mensaje_ui) toast(r.mensaje_ui);
    if (r.abrir_ficha) await abrirFicha(r.abrir_ficha);
    if (r.preset_organizar) abrirOrganizarConPreset(r.preset_organizar);
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
        if (ev.target.closest('.msg-eleccion-avatar-wrap')) return;
        ev.stopPropagation();
        if (btn.disabled) return;
        art.querySelectorAll('[data-elegir-persona]').forEach(function (b) { b.disabled = true; });
        const ok = await resolverAccionMensajito(m, 'elegir_persona', {
          personaje_id: btn.getAttribute('data-elegir-persona')
        });
        if (ok) await refresh();
        else art.querySelectorAll('[data-elegir-persona]').forEach(function (b) { b.disabled = false; });
      });
    });
    const accionesRaw = Array.isArray(m.acciones) ? m.acciones : [];
    const accionConsejo = accionesRaw.indexOf('responder_celestine') >= 0
      ? 'responder_celestine'
      : (accionesRaw.indexOf('responder_escuchar') >= 0 ? 'responder_escuchar' : 'responder_consejo');
    art.querySelectorAll('[data-llegada-acomp]').forEach(function (btn) {
      btn.addEventListener('click', async function (ev) {
        if (ev.target.closest('.msg-eleccion-avatar-wrap')) return;
        ev.stopPropagation();
        if (btn.disabled) return;
        const acomp = btn.getAttribute('data-llegada-acomp');
        if (!acomp) return;
        art.querySelectorAll('[data-llegada-acomp]').forEach(function (b) { b.disabled = true; });
        const ok = await resolverAccionMensajito(m, 'aceptar_candidato', { acompanante_id: acomp });
        if (ok) await refresh();
        else art.querySelectorAll('[data-llegada-acomp]').forEach(function (b) { b.disabled = false; });
      });
    });
    art.querySelectorAll('[data-llegada-rechazar]').forEach(function (btn) {
      btn.addEventListener('click', async function (ev) {
        ev.stopPropagation();
        if (btn.disabled) return;
        btn.disabled = true;
        const ok = await resolverAccionMensajito(m, 'rechazar_candidato');
        if (ok) await refresh();
        else btn.disabled = false;
      });
    });
    art.querySelectorAll('[data-consejo-opcion]').forEach(function (btn) {
      btn.addEventListener('click', async function (ev) {
        ev.stopPropagation();
        if (btn.disabled) return;
        art.querySelectorAll('[data-consejo-opcion]').forEach(function (b) { b.disabled = true; });
        const ok = await resolverAccionMensajito(m, accionConsejo, {
          opcion_id: btn.getAttribute('data-consejo-opcion')
        });
        if (ok) await refresh();
        else art.querySelectorAll('[data-consejo-opcion]').forEach(function (b) { b.disabled = false; });
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
    btn.className = 'aht-msg-read-toggle' + (leido ? ' is-leido' : ' is-pendiente');
    btn.innerHTML = leido ? '\u2713 Le\u00eddo' : '\u25CB No le\u00eddo';
    btn.setAttribute('data-read', leido ? 'true' : 'false');
    btn.setAttribute('aria-pressed', leido ? 'true' : 'false');
    btn.setAttribute('aria-label', leido ? 'Marcar como no le\u00eddo' : 'Marcar como le\u00eddo');
    btn.removeAttribute('title');
    btn.setAttribute('data-tip', leido ? 'Volver a dejarlo como nuevo' : 'Marcar como visto');
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
      (compact ? ' â€” ' : '<br/>') +
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
    var cumplidas = hoy.filter(function (m) { return (m.estado || '') === 'cumplida'; }).length;
    var total = hoy.length;
    var todasCumplidas = total > 0 && cumplidas === total;
    list.insertAdjacentHTML('beforeend',
      '<span class="mis-doodle mis-doodle-star" aria-hidden="true"><svg viewBox="0 0 16 16" width="14" height="14"><path d="M8 1l2.2 4.5L15 6.3l-3.5 3.4.8 4.9L8 12.2 3.7 14.6l.8-4.9L1 6.3l4.8-.8z" fill="currentColor"/></svg></span>');
    hoy.forEach(function (m) {
      list.insertAdjacentHTML('beforeend', htmlMisionItem(m));
    });
    if (todasCumplidas) {
      list.insertAdjacentHTML('beforeend',
        '<div class="mis-sello" aria-label="Completado"><span class="mis-sello-txt">COMPLETADO</span></div>');
    } else if (total > 0) {
      list.insertAdjacentHTML('beforeend',
        '<div class="mis-progreso"><span class="mis-progreso-txt">' + cumplidas + ' de ' + total + ' hechas</span></div>');
    }
    list.insertAdjacentHTML('beforeend',
      '<span class="mis-doodle mis-doodle-check" aria-hidden="true"><svg viewBox="0 0 20 20" width="18" height="18"><path d="M4 10.5l4 4 8-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>');
    enlazarAccionesMision(list, hoy);
  }


  const CELESTINE_OBJETIVO_POBLACION_FALLBACK = 16;

  function capObjetivoPoblacionVisible(partida) {
    const raw = partida && partida.celeste && partida.celeste.objetivo_poblacion_activa;
    const cap = Number(raw);
    return cap > 0 ? cap : CELESTINE_OBJETIVO_POBLACION_FALLBACK;
  }

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
    const cap = capObjetivoPoblacionVisible(partida);
    let conNecesidad = 0;
    ids.forEach(function (id) {
      var rt = res[id].runtime && res[id].runtime.necesidades;
      if (!rt) return;
      if (['social', 'diversion', 'actividad', 'calma'].some(function (nec) {
        var n = rt[nec];
        return n && n.banda !== 'bien';
      })) conNecesidad++;
    });
    return { vecinos: ids.length, cap: cap, parejas: parejasList.length, crisis: crisis, emo: emo, conNecesidad: conNecesidad };
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
    if (met.conNecesidad > 0) {
      bits.push('<div class="vecinos-stat celeste-necesitan-algo" role="presentation" data-celestine-necesitan="1">' +
        '<span class="vecinos-stat-ico" aria-hidden="true">\ud83e\ude77</span>' +
        '<span class="vecinos-stat-k">Necesitan algo</span>' +
        '<strong class="vecinos-stat-v">' + esc(String(met.conNecesidad)) + '</strong></div>');
    }
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

    if (enCurso) return lugar + ' Â· En curso Â· ' + hora;
    if (Number(enc.dia) === Number(reloj.dia_pueblo)) return lugar + ' Â· Hoy ' + hora;
    return lugar + ' Â· Día ' + (enc.dia || '?') + ' Â· ' + hora;
  }
  function emocionDe(id) {
    var r = cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id];
    var emo = (r && r.runtime && r.runtime.estado_emocional && r.runtime.estado_emocional.id) || 'neutro';
    if (['neutro', 'alegre', 'triste', 'enfadado'].indexOf(emo) < 0) emo = 'neutro';
    return emo;
  }


  function htmlMpParFace(id) {
    var img = tokenDe(id);
    if (!img) return '';
    return '<span class="inicio-mp-par-cara-wrap" aria-hidden="true"><img class="inicio-mp-par-cara" src="' + esc(img) + '" alt=""/></span>';
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

  function carasPlanHtml(ids, max) {
    var lim = (max == null) ? 2 : max;
    return (ids || []).slice(0, lim).map(function (id) { return htmlCaraToken(id); }).join('');
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
      ? coleccion.filter(function (e) { return e && e.id && !planEsEventoPueblo(e); })
      : ((partida && partida.encuentros) || []).filter(function (e) {
          if (!e || !e.id || planEsEventoPueblo(e)) return false;
          if (cur && cur.id === e.id) return true;
          return encuentroOcupaAhora(e, estado);
        });
    if (cur && cur.id && !planEsEventoPueblo(cur) && !lista.some(function (e) { return e.id === cur.id; })) {
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
      '<p class="prox-nombres">' + esc(ids.map(function (id) { return nombreDe(id); }).join(' Â· ')) + '</p>' +
      '<p class="prox-meta' + (enCurso ? ' prox-meta--en-curso' : '') + '"><span class="prox-meta-ico" aria-hidden="true"></span>' +
      esc(formatPlanMeta(enc, estado)) + '</p>' +
      htmlIntervencionResultado(enc, estado, enCurso ? { cardFlash: true } : {});
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
  function temasIntervencionDe(iv) {
    var hobbyAcc = (iv.acciones || []).find(function (a) { return a.id === 'hobby'; });
    return (hobbyAcc && hobbyAcc.temas_por_objetivo) ? hobbyAcc.temas_por_objetivo : null;
  }
  function kickerRompeHieloJs(iv, rompeId, temas) {
    var hobbyAcc = (iv.acciones || []).find(function (a) { return a.id === 'hobby'; });
    var kickers = (hobbyAcc && hobbyAcc.kickers_rompe) ? hobbyAcc.kickers_rompe.slice() : [
      'A ver, %s\u2026 \u00bfpor d\u00f3nde tiramos?',
      'Venga, %s\u2026 a ver si damos en el clavo.'
    ];
    var na = nombreDe(rompeId);
    var nb = '';
    if (temas && temas.length && temas[0].interlocutor_id) {
      nb = nombreDe(temas[0].interlocutor_id);
    } else if (temas && temas.length && temas[0].residente_id) {
      nb = nombreDe(temas[0].residente_id);
    }
    if (nb) {
      kickers.push('A ver si encontramos un tema que le entre a ' + nb + '\u2026');
      kickers.push(na + ', \u00bfqu\u00e9 le planteamos a ' + nb + '?');
    }
    var tpl = kickers[Math.floor(Math.random() * kickers.length)];
    if (tpl.indexOf('%s') >= 0) return tpl.replace('%s', na);
    return tpl;
  }
  function pintarTemasIntervencion(wrap, iv, objetivoId) {
    var panel = wrap.querySelector('[data-temas-panel]');
    var kicker = wrap.querySelector('[data-enc-int-kicker-tema]');
    if (!panel) return;
    var map = temasIntervencionDe(iv);
    var temas = (map && objetivoId && map[objetivoId]) ? map[objetivoId] : [];
    if (kicker) {
      kicker.textContent = kickerRompeHieloJs(iv, objetivoId, temas);
    }
    panel.innerHTML = '';
    temas.forEach(function (h) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'enc-int-btn enc-int-btn--hobby enc-int-opt';
      btn.setAttribute('data-enc-int-accion', 'hobby');
      btn.setAttribute('data-hobby-id', h.id || '');
      btn.setAttribute('data-residente-id', h.interlocutor_id || h.residente_id || '');
      btn.textContent = h.etiqueta || h.id || '';
      panel.appendChild(btn);
    });
  }
  function textoFeedbackIntervencion(iv) {
    if (!iv || !iv.ultimo) return '';
    return iv.ultimo.texto || '';
  }
  function htmlIntervencionResultado(enc, estado, opts) {
    opts = opts || {};
    if (!planEsEnCurso(enc, estado)) return '';
    var iv = intervencionVistaDe(enc, estado);
    if (!iv || !iv.usada || !iv.ultimo || !iv.ultimo.texto) return '';
    var tono;
    var txt;
    if (opts.cardFlash) {
      var flash = mentesFeedbackCardActivo(enc.id);
      if (!flash) return '';
      tono = flash.tono;
      txt = flash.txt;
    } else {
      tono = iv.ultimo.tono || 'neutral';
      txt = textoFeedbackIntervencion(iv);
    }
    return '<div class="enc-int-result enc-int-result--card"><p class="enc-int-result-txt enc-int-result-txt--' + esc(tono) + '">' + esc(txt) + '</p></div>';
  }
  function htmlIntervencionEncuentro(enc, estado) {
    if (!planEsEnCurso(enc, estado)) return '';
    var iv = intervencionVistaDe(enc, estado);
    if (!iv) return '';

    /* FASE 1: Si hay turnos, usar UI de turnos. */
    var turnos = iv.turnos || [];
    var turnoActual = iv.turno_actual || 0;
    var turnosMax = iv.turnos_max || 4;
    var actorActual = iv.actor_actual || '';
    var barra = iv.barra_quedada || 0;

    /* Si no hay más turnos y hay resultado, mostrar resumen. */
    if (turnos.length >= turnosMax && iv.ultimo && iv.ultimo.texto) {
      return htmlIntervencionResultado(enc, estado);
    }

    /* Si no está disponible y no hay turnos, no mostrar nada (legacy). */
    if (!iv.disponible && turnos.length === 0) return '';
    if (!iv.acciones || !iv.acciones.length) return '';

    var ids = enc.participantes || [];
    if (ids.length < 2) return '';

    /* FASE 1: Construir UI de turno. */
    var html = '<div class="enc-int enc-int--modal enc-int--turnos" data-enc-int data-enc-id="' + esc(enc.id || '') + '">';

    /* Barra de quedada */
    html += '<div class="enc-int-barra" data-enc-int-barra>';
    html += '<p class="enc-int-barra-tit">\u2728 C\u00f3mo va la quedada</p>';
    html += '<div class="enc-int-barra-track">';
    var barraPct = Math.max(0, Math.min(100, 50 + barra * 4));
    html += '<div class="enc-int-barra-fill" style="width:' + barraPct + '%"></div>';
    html += '<span class="enc-int-barra-emoji-left">\ud83e\udee0</span>';
    html += '<span class="enc-int-barra-emoji-mid">\ud83d\ude10</span>';
    html += '<span class="enc-int-barra-emoji-right">\ud83d\ude0a</span>';
    html += '<span class="enc-int-barra-emoji-fire">\ud83d\udd25</span>';
    html += '</div>';
    html += '</div>';

    /* Turnos completados (reacciones previas) */
    if (turnos.length > 0) {
      html += '<div class="enc-int-historial">';
      turnos.forEach(function (t) {
        var emoji = t.tono === 'bien' ? '\ud83d\udc4d' : (t.tono === 'mal' ? '\ud83d\udc4e' : '\ud83d\ude10');
        html += '<span class="enc-int-historial-item" title="' + esc(t.texto || '') + '">';
        html += '<span class="enc-int-historial-nom">' + esc(nombreDe(t.actor)) + '</span> ';
        html += '<span class="enc-int-historial-emoji">' + emoji + '</span>';
        html += '</span>';
      });
      html += '</div>';
    }

    /* Cara del actor actual + receptor */
    var receptorActual = '';
    for (var ri = 0; ri < ids.length; ri++) {
      if (ids[ri] !== actorActual) { receptorActual = ids[ri]; break; }
    }
    html += '<div class="enc-int-duo" aria-hidden="true">';
    html += '<div class="enc-int-duo-persona enc-int-duo-persona--activo">' + htmlCaraToken(actorActual, { wrapClass: 'enc-int-duo-cara' }) +
      '<span class="enc-int-duo-nombre">' + esc(nombreDe(actorActual)) + '</span></div>';
    html += '<span class="enc-int-duo-flecha">\u2192</span>';
    html += '<div class="enc-int-duo-persona">' + htmlCaraToken(receptorActual, { wrapClass: 'enc-int-duo-cara' }) +
      '<span class="enc-int-duo-nombre">' + esc(nombreDe(receptorActual)) + '</span></div>';
    html += '</div>';

    /* Indicador de turno */
    html += '<p class="enc-int-kicker enc-int-kicker--turno">Turno ' + (turnoActual + 1) + '/' + turnosMax + '</p>';

    /* Acciones disponibles */
    html += '<div class="enc-int-btns enc-int-temas-grid">';
    iv.acciones.forEach(function (ac) {
      if (!ac.disponible) return;
      var btn = '<button type="button" class="enc-int-btn" data-enc-int-accion="' + esc(ac.id) + '">';
      if (ac.id === 'hobby') {
        btn = '<button type="button" class="enc-int-btn enc-int-btn--hobby" data-enc-int-accion="' + esc(ac.id) + '">';
      }
      btn += esc(ac.etiqueta || ac.id);
      btn += '</button>';
      html += btn;
    });
    html += '</div>';

    /* Temas de hobby (si aplica) */
    html += '<div class="enc-int-temas-grid" data-temas-panel hidden></div>';

    /* Feedback de la última acción (si hay) */
    if (turnos.length > 0) {
      var ultimo = turnos[turnos.length - 1];
      var emojiR = ultimo.tono === 'bien' ? '\ud83d\udc4d' : (ultimo.tono === 'mal' ? '\ud83d\udc4e' : '\ud83d\ude10');
      html += '<div class="enc-int-result enc-int-result--card">';
      html += '<p class="enc-int-result-txt enc-int-result-txt--' + esc(ultimo.tono || 'neutral') + '">';
      html += emojiR + ' ' + esc(ultimo.texto || '');
      html += '</p></div>';
    }

    html += '</div>';
    return html;
  }
  function encuentroPorId(encId) {
    var id = String(encId || '');
    if (!id || !cacheEstado) return null;
    var col = Array.isArray(cacheEstado.encuentros_en_curso) ? cacheEstado.encuentros_en_curso : [];
    for (var i = 0; i < col.length; i++) {
      if (col[i] && String(col[i].id) === id) return col[i];
    }
    if (cacheEstado.encuentro_en_curso && String(cacheEstado.encuentro_en_curso.id) === id) {
      return cacheEstado.encuentro_en_curso;
    }
    return null;
  }
  function htmlMentesCta(enc, iv) {
    var txt = ctaTxtEncuentroMov(enc, iv);
    return '<button type="button" class="plan-unif-cta plan-unif-cta--curso enc-mov-cta enc-mov-cta--mentes" data-enc-mentes-open data-enc-id="' + esc(enc.id || '') + '">' + esc(txt) + '</button>';
  }
  function htmlMentesCtaResumen(enc, iv) {
    var txt = ctaTxtEncuentroMov(enc, iv);
    return '<p class="enc-mov-resumen enc-mov-resumen--cuece" data-enc-mentes-open data-enc-id="' + esc(enc.id || '') + '" role="button" tabindex="0">' + esc(txt) + '</p>';
  }
  function htmlMentesCtaMock(enc, iv) {
    var txt = ctaTxtEncuentroMov(enc, iv);
    return '<button type="button" class="enc-mov-cta enc-mov-cta--mock" data-enc-mentes-open data-enc-id="' + esc(enc.id || '') + '">' +
      '<span class="enc-mov-cta-txt">' + esc(txt) + '</span><span class="enc-mov-cta-flecha" aria-hidden="true">&rsaquo;</span></button>';
  }
  function mostrarMentesResultado(iv) {
    var body = document.querySelector('[data-mentes-body]');
    if (!body) return;
    var wrap = body.querySelector('[data-enc-int]');
    if (!wrap) return;
    var stepPersona = wrap.querySelector('[data-enc-int-paso="persona"]');
    var stepAccion = wrap.querySelector('[data-enc-int-paso="accion"]');
    var stepResult = wrap.querySelector('[data-enc-int-paso="resultado"]');
    if (stepPersona) stepPersona.hidden = true;
    if (stepAccion) stepAccion.hidden = true;
    if (stepResult) {
      var tono = (iv && iv.ultimo && iv.ultimo.tono) || 'neutral';
      var txt = textoFeedbackIntervencion(iv);
      var box = stepResult.querySelector('[data-enc-int-resultado]');
      if (box) {
        box.innerHTML = '<p class="enc-int-result-txt enc-int-result-txt--' + esc(tono) + '">' + esc(txt) + '</p>';
      }
      stepResult.hidden = false;
    }
  }
  function montarMentesModal(encId) {
    var enc = encuentroPorId(encId);
    var body = document.querySelector('[data-mentes-body]');
    if (!enc || !body) return;
    var contenido = htmlIntervencionEncuentro(enc, cacheEstado);
    if (!contenido || contenido.indexOf('data-enc-int') < 0) return;
    mentesEncIdActivo = String(encId || '');
    body.innerHTML = contenido;
    setCapa('mentes');
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
    var capaActual = ($('.play-root') && $('.play-root').getAttribute('data-capa')) || '';
    activarMentesFeedbackCard(encId, vistaIntervencion);

    /* FASE 1: Re-render del modal con el nuevo estado de turnos. */
    if (r.ok && capaActual === 'mentes' && String(mentesEncIdActivo) === String(encId)) {
      var enc = encuentroPorId(encId);
      var body = document.querySelector('[data-mentes-body]');
      if (enc && body) {
        var contenido = htmlIntervencionEncuentro(enc, cacheEstado);
        if (contenido && contenido.indexOf('data-enc-int') >= 0) {
          body.innerHTML = contenido;
        } else {
          mostrarMentesResultado(vistaIntervencion);
        }
      }
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
        esc(ids.map(function (id) { return nombreDe(id); }).join(' Â· ')) + '</span>' +
        '<span class="agenda-fila-meta">' + esc(formatPlanMeta(enc, cacheEstado)) + '</span></span>';
      box.appendChild(btn);
    });
  }
  function resumenCotilleoUi(txt, max) {
    var s = String(txt || '').replace(/\s+/g, ' ').trim();
    var lim = max || 110;
    if (!s) return '';
    if (s.length <= lim) return s;
    return s.slice(0, lim - 1).trim() + 'â€”';
  }

  /* === Planes en curso â€” carrusel movil (misma fuente canonica que desktop) === */

  function planEsEventoPueblo(enc) {
    if (!enc) return false;
    if (enc.intencion === 'evento_pueblo') return true;
    if (enc.evento_pueblo_id || enc.evento_pueblo_catalogo_id) return true;
    if (enc.es_evento_pueblo === true) return true;
    if (eventoPuebloCatalogoId(enc, cacheInsp)) return true;
    var lug = String(enc.lugar || enc.lugar_id || '');
    if (lug === 'lug_bingo') return true;
    var idsEvt = enc.participantes || [];
    if (idsEvt.length >= 3 && enc.intencion !== 'celeste_organizado') return true;
    return false;
  }

  function maxCarasProxPlan(enc, ids) {
    var n = (ids || []).length;
    if (planEsEventoPueblo(enc)) return Math.min(4, Math.max(1, n));
    return Math.min(2, Math.max(1, n));
  }

  const EVENTO_PUEBLO_NOMBRE_UI = {
    noche_bingo: 'Noche de bingo',
    partido_futbol_benefico: 'Partido de fútbol benéfico',
    sesion_cine_comunitaria: 'Sesión de cine comunitaria',
    tardeo_en_el_bar: 'Tardeo en el bar',
    clase_abierta_gimnasio: 'Clase abierta en el gimnasio',
    club_lectura: 'Club de lectura'
  };

  function ahtAssetUrl(path) {
    var p = String(path || '').trim();
    if (!p) return '';
    if (/^https?:\/\//i.test(p) || p.indexOf('data:') === 0) return p;
    if (p.charAt(0) === '/') return p;
    var base = (typeof window !== 'undefined' && window.AHT_BASE) ? String(window.AHT_BASE) : '';
    if (!base && typeof document !== 'undefined' && document.location) {
      base = String(document.location.pathname || '').replace(/[^/]*$/, '');
    }
    return base + p.replace(/^\//, '');
  }

  const EVENTO_PUEBLO_IMG = {
    noche_bingo: 'assets/play-v3/eventos/noche_bingo.png',
    partido_futbol_benefico: 'assets/play-v3/eventos/partido_futbol_benefico.png',
    sesion_cine_comunitaria: 'assets/play-v3/eventos/sesion_cine_comunitaria.png',
    tardeo_en_el_bar: 'assets/play-v3/eventos/tardeo_en_el_bar.png',
    clase_abierta_gimnasio: 'assets/play-v3/eventos/clase_abierta_gimnasio.png',
    club_lectura: 'assets/play-v3/eventos/club_lectura.png'
  };


  function mensajitoEventoPuebloImg(m) {
    if (!m) return '';
    var df = m.datos_familia || {};
    if (df.illustracion) return ahtAssetUrl(df.illustracion);
    var catId = String(df.evento_pueblo_catalogo_id || '');
    if (catId && EVENTO_PUEBLO_IMG[catId]) return ahtAssetUrl(EVENTO_PUEBLO_IMG[catId]);
    var tipo = String(m.tipo || m.familia_mensajito || '');
    if ((tipo === 'anuncio_evento_pueblo' || tipo === 'cierre_evento_pueblo') && catId) {
      return ahtAssetUrl('assets/play-v3/eventos/' + catId + '.png');
    }
    return '';
  }

  function htmlMensajitoEventoIllo(m) {
    var src = mensajitoEventoPuebloImg(m);
    if (!src) return '';
    return '<div class="carta-illo-evento" aria-hidden="true"><img class="carta-illo-evento-img" src="' + esc(src) + '" alt="" loading="lazy" decoding="async"/></div>';
  }

  function eventoPuebloCatalogoId(enc, partida) {
    if (!enc) return '';
    if (enc.evento_pueblo_catalogo_id) return String(enc.evento_pueblo_catalogo_id);
    partida = partida || cacheInsp || {};
    var encId = String(enc.id || '');
    var prog = (partida.eventos_pueblo && partida.eventos_pueblo.programados) || [];
    var i;
    for (i = 0; i < prog.length; i++) {
      var ev = prog[i];
      if (!ev) continue;
      if (String(ev.encuentro_id || '') === encId || String(ev.id || '') === String(enc.evento_pueblo_id || '')) {
        if (ev.catalogo_id) return String(ev.catalogo_id);
      }
    }
    return '';
  }

  function eventoPuebloImgSrc(enc, partida, evEstado) {
    var catId = enc ? eventoPuebloCatalogoId(enc, partida) : '';
    if (!catId && evEstado && evEstado.catalogo_id) catId = String(evEstado.catalogo_id);
    if (catId && EVENTO_PUEBLO_IMG[catId]) return ahtAssetUrl(EVENTO_PUEBLO_IMG[catId]);
    if (evEstado && evEstado.illustracion) return ahtAssetUrl(evEstado.illustracion);
    var lug = (enc && enc.lugar) || (evEstado && evEstado.lugar) || '';
    return orgLugarImg(lug);
  }

  function eventoPuebloIcoHtml(enc, partida, evEstado) {
    var catId = enc ? eventoPuebloCatalogoId(enc, partida) : '';
    if (!catId && evEstado && evEstado.catalogo_id) catId = String(evEstado.catalogo_id);
    if (!catId) catId = 'evento';
    var src = eventoPuebloImgSrc(enc, partida, evEstado);
    var inner = src
      ? '<img src="' + esc(src) + '" alt="" loading="lazy" decoding="async"/>'
      : '<span class="pp-evt-ico-fallback" aria-hidden="true"></span>';
    return '<span class="pp-evt-ico-frame pp-evt-ico-frame--' + esc(catId) + '" aria-hidden="true">' + inner + '</span>';
  }

  function nombreEventoPuebloDe(enc, partida) {
    partida = partida || cacheInsp || {};
    var encId = String((enc && enc.id) || '');
    var prog = (partida.eventos_pueblo && partida.eventos_pueblo.programados) || [];
    var i;
    for (i = 0; i < prog.length; i++) {
      var ev = prog[i];
      if (!ev) continue;
      if (String(ev.encuentro_id || '') === encId || String(ev.id || '') === String(enc.evento_pueblo_id || '')) {
        var nm = String(ev.nombre || '').trim();
        if (nm) return nm;
      }
    }
    var catId = String((enc && enc.evento_pueblo_catalogo_id) || '');
    if (EVENTO_PUEBLO_NOMBRE_UI[catId]) return EVENTO_PUEBLO_NOMBRE_UI[catId];
    return nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
  }

  function diaSemanaProxPlan(enc, estado) {
    if (enc && enc.dia_semana_ui) return enc.dia_semana_ui;
    var ev = estado && estado.proximo_evento_pueblo;
    if (ev && String(ev.encuentro_id || '') === String(enc.id || '') && ev.dia_semana_ui) {
      return ev.dia_semana_ui;
    }
    var diaHoy = Number((estado && estado.reloj && estado.reloj.dia_pueblo));
    if (Number(enc.dia) === diaHoy) return 'Hoy';
    return 'D\u00eda ' + (enc.dia || '?');
  }

  function metaEventoPuebloLinea(enc, estado) {
    return diaSemanaProxPlan(enc, estado) + ' \u00b7 ' +
      String(horaEnc(enc)).padStart(2, '0') + ':00 \u00b7 ' +
      nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
  }

  function asistentesEventoPuebloTxt(ids) {
    var lista = (ids || []).map(function (id) { return nombreDe(id); }).filter(Boolean);
    var n = lista.length;
    if (n <= 0) return '';
    if (n === 1) return lista[0];
    if (n === 2) return lista.join(' y ');
    return lista.slice(0, 2).join(', ') + ' y ' + (n - 2) + ' m\u00e1s';
  }

  function htmlProximoPlanCardEvento(enc, estado) {
    var ids = enc.participantes || [];
    var maxCaras = maxCarasProxPlan(enc, ids);
    var titulo = nombreEventoPuebloDe(enc, cacheInsp);
    var meta = metaEventoPuebloLinea(enc, estado);
    var asist = asistentesEventoPuebloTxt(ids);
    var ico = eventoPuebloIcoHtml(enc, cacheInsp, estado && estado.proximo_evento_pueblo);
    return '<article class="pp-mov-card pp-mov-card--evento pp-mov-card--evento-v31" data-pp-evento-pueblo="1" role="status">' +
      '<span class="pp-evt-tag">EVENTO PUEBLO</span>' +
      '<div class="pp-evt-main">' +
      '<div class="pp-evt-ico">' + ico + '</div>' +
      '<div class="pp-evt-copy">' +
      '<p class="pp-evt-tit">' + esc(titulo) + '</p>' +
      '<p class="pp-evt-meta">' + esc(meta) + '</p>' +
      '</div>' +
      '</div>' +
      '<div class="pp-evt-pie">' +
      '<div class="pp-evt-faces prox-faces prox-faces--grupo">' + carasPlanHtml(ids, maxCaras) + '</div>' +
      '<p class="pp-evt-asisten">' + esc(asist) + '</p>' +
      '</div>' +
      '</article>';
  }

  function proximosPlanesFuturos(partida, estado) {
    const reloj = (estado && estado.reloj) || {};
    const now = relojAbs(reloj.dia_pueblo, reloj.hora_actual);
    return ((partida && partida.encuentros) || []).filter(function (e) {
      if (!e || !e.id) return false;
      if (planEsEventoPueblo(e)) return false;
      if (String(e.estado || '') !== 'programado') return false;
      if (planEsEnCurso(e, estado)) return false;
      return now < relojAbs(e.dia, horaEnc(e)) + duracionEncHoras(e);
    }).sort(function (a, b) {
      return relojAbs(a.dia, horaEnc(a)) - relojAbs(b.dia, horaEnc(b)) ||
        String(a.id).localeCompare(String(b.id));
    });
  }

  function ppMovPersonasHtml(ids, max) {
    var lim = (max == null) ? 2 : max;
    return (ids || []).slice(0, lim).map(function (id) {
      return '<div class="pp-mov-persona">' +
        '<div class="pp-mov-persona-face">' + htmlCaraToken(id) + '</div>' +
        '<p class="pp-mov-persona-nombre">' + esc(nombreDe(id)) + '</p>' +
        '</div>';
    }).join('');
  }
  function htmlProximoPlanCardMovil(enc, estado) {
    if (planEsEventoPueblo(enc)) {
      return htmlProximoPlanCardEvento(enc, estado);
    }
    const ids = enc.participantes || [];
    const maxCaras = maxCarasProxPlan(enc, ids);
    const diaHoy = Number((estado && estado.reloj && estado.reloj.dia_pueblo));
    const sello = (Number(enc.dia) === diaHoy ? 'HOY' : 'D\u00cdA ' + (enc.dia || '?')) +
      ' \u00b7 ' + String(horaEnc(enc)).padStart(2, '0') + ':00';
    return '<article class="pp-mov-card pp-mov-card--mock">' +
      '<div class="pp-mov-top">' +
      '<p class="pp-mov-hora">' + esc(sello) + '</p>' +
      '<span class="pp-mov-star" aria-hidden="true"><span class="pp-mov-star-ico">\u2605</span></span>' +
      '</div>' +
      '<div class="pp-mov-body">' +
      '<div class="prox-faces' + (ids.length >= 2 ? ' prox-faces--duo' : '') + '">' +
      (ids.length >= 2 ? planDuoFacesMovilHtml(enc, ids) : carasPlanHtml(ids, maxCaras)) + '</div>' +
      '<div class="pp-mov-copy">' +
      '<p class="pp-mov-nombres">' + esc(ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ')) + '</p>' +
      '<p class="pp-mov-lugar">' + esc(nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar)) + '</p>' +
      '</div>' +
      '</div></article>';
  }

  function planDuoFacesDesktopHtml(enc, ids) {
    var slice = (ids || []).slice(0, 2);
    if (slice.length < 2) {
      return '<div class="plan-desk-duo-wrap">' +
        '<div class="enc-mov-faces prox-faces plan-desk-duo-faces">' + carasPlanHtml(slice) + '</div>' +
        '<p class="plan-desk-duo-nombre">' + esc(nombreDe(slice[0] || '')) + '</p></div>';
    }
    return '<div class="plan-desk-duo-wrap">' +
      '<div class="enc-mov-faces prox-faces plan-desk-duo-faces">' +
      htmlCaraToken(slice[0], { wrapClass: 'enc-mov-cara enc-mov-cara--l' }) +
      iconoPlanCentroHtml(enc) +
      htmlCaraToken(slice[1], { wrapClass: 'enc-mov-cara enc-mov-cara--r' }) +
      '</div>' +
      '<div class="plan-desk-duo-nombres">' +
      '<span class="plan-desk-duo-nombre plan-desk-duo-nombre--l">' + esc(nombreDe(slice[0])) + '</span>' +
      '<span class="plan-desk-duo-nombre plan-desk-duo-nombre--r">' + esc(nombreDe(slice[1])) + '</span>' +
      '</div></div>';
  }
  function planDuoFacesMovilHtml(enc, ids) {
    var slice = (ids || []).slice(0, 2);
    if (slice.length < 2) return carasPlanHtml(slice);
    return htmlCaraToken(slice[0], { wrapClass: 'enc-mov-cara enc-mov-cara--l' }) +
      iconoPlanCentroHtml(enc) +
      htmlCaraToken(slice[1], { wrapClass: 'enc-mov-cara enc-mov-cara--r' });
  }

  function htmlProximoPlanCardDesktop(enc, estado) {
    if (planEsEventoPueblo(enc)) {
      return htmlProximoPlanCardEvento(enc, estado);
    }
    const ids = enc.participantes || [];
    const maxCaras = maxCarasProxPlan(enc, ids);
    const diaHoy = Number((estado && estado.reloj && estado.reloj.dia_pueblo));
    const sello = (Number(enc.dia) === diaHoy ? 'HOY' : 'D\u00cdA ' + (enc.dia || '?')) +
      ' \u00b7 ' + String(horaEnc(enc)).padStart(2, '0') + ':00';
    return '<article class="pp-mov-card pp-mov-card--mock pp-mov-card--desk-duo">' +
      '<div class="pp-mov-top">' +
      '<p class="pp-mov-hora">' + esc(sello) + '</p>' +
      '<span class="pp-mov-star" aria-hidden="true"><span class="pp-mov-star-ico">\u2605</span></span>' +
      '</div>' +
      '<div class="pp-mov-body pp-mov-body--desk-duo">' +
      planDuoFacesDesktopHtml(enc, ids) +
      '<p class="pp-mov-lugar">' + esc(nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar)) + '</p>' +
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
    if (!lista.length) {
      block.classList.remove('is-on');
      block.classList.add('is-empty');
      block.classList.remove('proxplanes-movil--solo', 'proxplanes-movil--multi');
      track.innerHTML = '';
      renderProxplanesNavFor(block);
      return;
    }
    block.classList.remove('is-empty');
    block.classList.add('is-on');
    block.classList.toggle('proxplanes-movil--solo', lista.length === 1);
    block.classList.toggle('proxplanes-movil--multi', lista.length > 1);
    track.innerHTML = lista.map(function (enc) { return cardFn(enc, estado); }).join('');
    requestAnimationFrame(function () { renderProxplanesNavFor(block); });
  }
  function renderProximosPlanesMovil(estado) {
    inicioBlocks('[data-proxplanes-block]').forEach(function (block) {
      const view = block.closest('.inicio-mobile') ? 'mobile' : 'desktop';
      const cardFn = view === 'mobile' ? htmlProximoPlanCardMovil : htmlProximoPlanCardDesktop;
      renderProximosPlanesBlock(block, estado, cardFn);
    });
  }
  function htmlPlanUnifCardCurso(enc, estado) {
    const ids = (enc && enc.participantes) || [];
    const iv = intervencionVistaDe(enc, estado);
    const ctaVisible = ctaEncuentroMovVisible(enc, iv);
    const resultHtml = htmlIntervencionResultado(enc, estado, { cardFlash: true });
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    const nombres = ids.map(function (id) { return nombreDe(id); }).filter(Boolean).join(' \u00b7 ');
    const maxCaras = Math.min(2, Math.max(1, ids.length));
    var html = '<article class="plan-unif-card plan-unif-card--curso enc-mov-card" data-enc-mov-card data-enc-id="' + esc(enc.id || '') + '">' +
      '<span class="plan-unif-badge plan-unif-badge--curso">EN CURSO</span>' +
      '<span class="plan-unif-menu" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 7h8M8 12h8M8 17h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>' +
      '<div class="plan-unif-faces prox-faces">' + encCursoFacesHtml(enc) + '</div>' +
      '<p class="plan-unif-nombres">' + esc(nombres) + '</p>' +
      '<p class="plan-unif-lugar">' + esc(lugar) + '</p>';
    if (ctaVisible) {
      html += htmlMentesCta(enc, iv);
    } else if (resultHtml) {
      html += resultHtml;
    }
    return html + '</article>';
  }
  function htmlPlanUnifCardProx(enc, estado) {
    if (planEsEventoPueblo(enc)) {
      return htmlProximoPlanCardEvento(enc, estado);
    }
    const ids = enc.participantes || [];
    const maxCaras = maxCarasProxPlan(enc, ids);
    const diaHoy = Number((estado && estado.reloj && estado.reloj.dia_pueblo));
    const sello = (Number(enc.dia) === diaHoy ? 'HOY' : 'D\u00cdA ' + (enc.dia || '?')) +
      ' \u00b7 ' + String(horaEnc(enc)).padStart(2, '0') + ':00';
    return '<article class="plan-unif-card plan-unif-card--prox pp-mov-card" data-pp-mov-card data-enc-id="' + esc(enc.id || '') + '">' +
      '<span class="plan-unif-badge plan-unif-badge--prox">' + esc(sello) + '</span>' +
      '<div class="plan-unif-faces prox-faces">' + planDuoFacesMovilHtml(enc, ids) + '</div>' +
      '<p class="plan-unif-nombres">' + esc(ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ')) + '</p>' +
      '<p class="plan-unif-lugar">' + esc(nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar)) + '</p>' +
      '</article>';
  }
  function planUnifPaso(track) {
    const cards = track.querySelectorAll('.plan-unif-card');
    if (!cards.length) return 0;
    const st = getComputedStyle(track);
    const gap = parseFloat(st.columnGap || st.gap) || 0;
    return cards[0].offsetWidth + gap;
  }
  function renderPlanesUnifMoreFor(block) {
    const track = block && block.querySelector('[data-planes-unif-track]');
    const more = block && block.querySelector('[data-planes-unif-more]');
    if (!track || !more) return;
    const overflow = track.scrollWidth > track.clientWidth + 4;
    const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 6;
    more.hidden = !overflow || atEnd;
  }
  function planesUnifScrollNext(block) {
    const track = block && block.querySelector('[data-planes-unif-track]');
    if (!track) return;
    const paso = planUnifPaso(track);
    if (paso <= 0) return;
    track.scrollBy({ left: paso, behavior: 'smooth' });
    setTimeout(function () { renderPlanesUnifMoreFor(block); }, 320);
  }
  function renderPlanesUnifBlock(block, estado) {
    if (!block) return;
    const track = block.querySelector('[data-planes-unif-track]');
    const badges = block.querySelector('[data-planes-unif-badges]');
    if (!track) return;
    const curso = encuentrosEnCursoAhora(cacheInsp, estado);
    const proxFull = proximosPlanesFuturos(cacheInsp, estado);
    const prox = proxFull.slice(0, 6);
    const nCurso = curso.length;
    const nProx = proxFull.length;
    if (badges) {
      var parts = [];
      if (nCurso > 0) {
        parts.push('<span class="planes-unif-spine-badge planes-unif-spine-badge--curso" title="' + String(nCurso) + ' en curso">' + '<svg class="planes-unif-spine-badge-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.3-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 12a8 8 0 0 1-13.3 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' + '<span class="planes-unif-spine-badge-num">' + String(nCurso) + '</span></span>');
      }
      if (nProx > 0) {
        parts.push('<span class="planes-unif-spine-badge planes-unif-spine-badge--prox" title="' + String(nProx) + ' pr\u00f3ximos">' + '<svg class="planes-unif-spine-badge-ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 8v4l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' + '<span class="planes-unif-spine-badge-num">' + String(nProx) + '</span></span>');
      }
      if (parts.length) {
        badges.innerHTML = parts.join('');
        badges.hidden = false;
        badges.removeAttribute('aria-hidden');
      } else {
        badges.innerHTML = '';
        badges.hidden = true;
        badges.setAttribute('aria-hidden', 'true');
      }
    }
    const total = nCurso + prox.length;
    if (!total) {
      block.classList.remove('is-on');
      block.classList.add('is-empty');
      track.innerHTML = '';
      renderPlanesUnifMoreFor(block);
      return;
    }
    block.classList.remove('is-empty');
    block.classList.add('is-on');
    track.innerHTML = curso.map(function (enc) { return htmlPlanUnifCardCurso(enc, estado); }).join('') +
      prox.map(function (enc) { return htmlPlanUnifCardProx(enc, estado); }).join('');
    requestAnimationFrame(function () {
      renderPlanesUnifMoreFor(block);
    });
  }
  function renderPlanesUnifMovil(estado) {
    inicioBlocks('[data-planes-unif-block]').forEach(function (block) {
      renderPlanesUnifBlock(block, estado);
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
  var PLAN_TIPO_EMOJI = {
    conocerse: "\uD83D\uDC4B",
    quedar: "\u2615",
    amistad: "\uD83E\uDD1D",
    primera_cita: "\uD83D\uDC95",
    cita: "\u2764\uFE0F",
    romance: "\uD83D\uDC95",
    romantico: "\u2764\uFE0F",
    conflicto: "\u26A1",
    grupal: "\uD83D\uDC65",
    individual: "\uD83D\uDEB6",
    otro: "\u2728",
    social: "\u2615"
  };
  function planTipoEmojiDe(enc) {
    const tipo = String((enc && enc.tipo) || '').toLowerCase();
    if (PLAN_TIPO_EMOJI[tipo]) return PLAN_TIPO_EMOJI[tipo];
    const fam = familiaTipoEncuentro(enc);
    if (fam === 'social') return PLAN_TIPO_EMOJI.quedar;
    if (PLAN_TIPO_EMOJI[fam]) return PLAN_TIPO_EMOJI[fam];
    return PLAN_TIPO_EMOJI.otro;
  }
  function iconoPlanCentroHtml(enc) {
    const ids = (enc && enc.participantes) || [];
    if (ids.length < 2) return '';
    const emoji = planTipoEmojiDe(enc);
    return '<span class="enc-mov-tipo-ico enc-mov-tipo-ico--emoji" aria-hidden="true">' + emoji + '</span>';
  }
  function iconoRelacionesCentroHtml() {
    return '<span class="enc-mov-tipo-ico enc-mov-tipo-ico--relaciones" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 20.5s-6.5-4.2-6.5-8.4C5.5 9.2 8.1 7 11 7c1.6 0 2.7.7 3.5 1.6.8-.9 1.9-1.6 3.5-1.6 2.9 0 5.5 2.2 5.5 5.1 0 4.2-6.5 8.4-6.5 8.4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span>';
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
    return htmlCaraToken(slice[0], { wrapClass: 'enc-mov-cara enc-mov-cara--l' }) +
      iconoPlanCentroHtml(enc) +
      htmlCaraToken(slice[1], { wrapClass: 'enc-mov-cara enc-mov-cara--r' });
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
    return resumenEncursoSinMentes(enc, iv);
  }
  function htmlEncursoVistaPanel(enc) {
    const ids = enc.participantes || [];
    const nombres = ids.map(function (id) { return nombreDe(id); }).join(' \u00b7 ');
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    return '<div class="enc-mov-vista"><p class="enc-mov-vista-txt">' +
      esc(nombres) + ' \u00b7 ' + esc(lugar) + '</p></div>';
  }
  function encCursoNombresHtml(ids) {
    const list = ids || [];
    if (list.length === 2) {
      return esc(nombreDe(list[0])) + ' y ' + esc(nombreDe(list[1]));
    }
    return esc(list.map(function (id) { return nombreDe(id); }).join(' Â· '));
  }

  function encCursoTituloDe(enc) {
    if (planEsEventoPueblo(enc)) return nombreEventoPuebloDe(enc, cacheInsp);
    var ids = (enc && enc.participantes) || [];
    if (ids.length === 2) return nombreDe(ids[0]) + ' y ' + nombreDe(ids[1]);
    return nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
  }
  function encCursoAforoDe(enc, partida) {
    partida = partida || cacheInsp || {};
    var prog = (partida.eventos_pueblo && partida.eventos_pueblo.programados) || [];
    var i;
    for (i = 0; i < prog.length; i++) {
      var ev = prog[i];
      if (!ev) continue;
      if (String(ev.encuentro_id || '') === String((enc && enc.id) || '')) {
        if (ev.aforo_total) return ev.aforo_total;
        if (ev.aforo_ui) return String(ev.aforo_ui).replace(/[^\d]/g, '') || null;
      }
    }
    return null;
  }
  function encCursoMetaRichDe(enc, estado) {
    var lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    var ids = (enc && enc.participantes) || [];
    var parts = ['En curso', lugar];
    var aforo = encCursoAforoDe(enc, cacheInsp);
    if (aforo) parts.push('Aforo: ' + aforo);
    var n = ids.length;
    parts.push(n + ' vecino' + (n === 1 ? '' : 's') + ' apuntado' + (n === 1 ? '' : 's'));
    return parts.join(' \u00b7 ');
  }
  function encCursoPieFacesHtml(enc) {
    var ids = (enc && enc.participantes) || [];
    var max = Math.min(3, Math.max(1, ids.length));
    return carasPlanHtml(ids, max);
  }
  function encCursoLugarStampHtml(enc) {
    const src = orgLugarImg(enc && enc.lugar);
    if (!src) return '';
    return '<div class="enc-mov-lugar-stamp" aria-hidden="true"><img src="' + esc(src) + '" alt="" width="46" height="40" loading="lazy" decoding="async"></div>';
  }

  function encCursoCardHeadIconsHtml() {
    return '<div class="enc-mov-card-deco" aria-hidden="true">' +
      '<span class="enc-mov-deco-ico enc-mov-deco-ico--scroll"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 4h9l3 3v13H6V4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M15 4v4h4M8 11h8M8 15h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>' +
      '<span class="enc-mov-deco-ico enc-mov-deco-ico--people"><svg viewBox="0 0 24 24" focusable="false"><circle cx="8" cy="9" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="9" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 19c.8-3 2.8-4.5 4.5-4.5S12.7 16 13.5 19M10.5 19c.8-3 2.8-4.5 4.5-4.5s3.7 1.5 4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>' +
      '</div>';
  }
  function htmlEncursoCardEscena(enc, estado) {
    const ids = enc.participantes || [];
    const iv = intervencionVistaDe(enc, estado);
    const ctaVisible = ctaEncuentroMovVisible(enc, iv);
    const resultHtml = htmlIntervencionResultado(enc, estado, { cardFlash: true });
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    const hora = String(horaEnc(enc)).padStart(2, '0') + ':00';
    let html = '<article class="enc-mov-card enc-mov-card--escena enc-mov-card--ref-desk" data-enc-mov-card data-enc-id="' + esc(enc.id || '') + '">' +
      '<p class="enc-mov-estado-pill enc-mov-estado-pill--fuera"><span class="enc-mov-punto" aria-hidden="true"></span>EN CURSO</p>' +
      '<div class="enc-mov-escena">' +
      '<div class="enc-mov-faces prox-faces">' + encCursoFacesHtml(enc) + '</div>' +
      '<div class="enc-mov-escena-core">' +
      '<p class="enc-mov-nombres">' + encCursoNombresHtml(ids) + '</p>' +
      '<div class="enc-mov-donde-cuando">' +
      '<span class="enc-mov-lugar-line"><span class="enc-mov-pin" aria-hidden="true"></span> ' + esc(lugar) + '</span>' +
      '<span class="enc-mov-hora-line"><span class="enc-mov-reloj" aria-hidden="true"></span> ' + esc(hora) + '</span>' +
      '</div></div>' +
      '<span class="enc-mov-desk-chevron" aria-hidden="true">&#8250;</span></div>';
    if (ctaVisible) {
      html += htmlMentesCtaResumen(enc, iv);
    } else if (resultHtml) {
      html += resultHtml;
    }
    return html + '</article>';
  }
  function htmlEncursoCardDesktop(enc, estado) {
    return htmlEncursoCardEscena(enc, estado);
  }
    function htmlEncursoCardMovilV15(enc, estado) {
    const ids = (enc && enc.participantes) || [];
    const iv = intervencionVistaDe(enc, estado);
    const ctaVisible = ctaEncuentroMovVisible(enc, iv);
    const resultHtml = htmlIntervencionResultado(enc, estado, { cardFlash: true });
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    const nombres = ids.map(function (id) { return nombreDe(id); }).filter(Boolean).join(' \u00b7 ');
    const maxCaras = Math.min(2, Math.max(1, ids.length));
    var html = '<article class="enc-mov-card enc-mov-card--mock" data-enc-mov-card data-enc-id="' + esc(enc.id || '') + '">' +
      '<div class="enc-mov-mock-grid">' +
      '<div class="enc-mov-faces prox-faces">' + encCursoFacesHtml(enc) + '</div>' +
      '<div class="enc-mov-mock-main">' +
      '<p class="enc-mov-nombres">' + esc(nombres) + '</p>' +
      '<p class="enc-mov-lugar">' + esc(lugar) + '</p>' +
      '</div>' +
      '<div class="enc-mov-mock-side">' +
      '<p class="enc-mov-estado-pill"><span class="enc-mov-punto" aria-hidden="true"></span>EN CURSO</p>';
    if (ctaVisible) {
      html += htmlMentesCtaMock(enc, iv);
    }
    html += '</div></div>';
    if (!ctaVisible && resultHtml) {
      html += resultHtml;
    }
    return html + '</article>';
  }
  function htmlEncursoCardMovilV14(enc, estado) {
    return htmlEncursoCardMovilV15(enc, estado);
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
  function ppMovEsDesktop(block) {
    return !!(block && block.closest('.inicio-desktop-right') &&
      typeof window !== 'undefined' && window.matchMedia &&
      window.matchMedia('(min-width: 769px)').matches);
  }
  function ppMovPaso(track, block) {
    if (!track) return 0;
    if (ppMovEsDesktop(block)) return track.clientWidth || 0;
    const cards = track.querySelectorAll('.pp-mov-card');
    if (!cards.length) return 0;
    const st = getComputedStyle(track);
    const gap = parseFloat(st.columnGap || st.gap) || 0;
    return cards[0].offsetWidth + gap;
  }
  function ppMovIrA(block, idx) {
    const track = block && block.querySelector('[data-proxplanes-track]');
    if (!block || !track) return;
    const n = track.querySelectorAll('.pp-mov-card').length;
    const paso = ppMovPaso(track, block);
    if (paso <= 0) return;
    if (ppMovEsDesktop(block) && n > 2) {
      const max = Math.max(0, track.scrollWidth - track.clientWidth);
      const cur = typeof block._ppMovIndice === 'number' ? block._ppMovIndice : 0;
      var left = track.scrollLeft;
      if (typeof idx === 'number' && idx !== cur) {
        left = idx > cur ? Math.min(max, left + paso) : Math.max(0, left - paso);
      }
      block._ppMovIndice = idx > cur ? cur + 1 : (idx < cur ? Math.max(0, cur - 1) : cur);
      track.scrollTo({ left: left, behavior: 'smooth' });
      renderProxplanesNavFor(block);
      return;
    }
    if (n < 2) return;
    const i = Math.max(0, Math.min(n - 1, idx));
    block._ppMovIndice = i;
    track.scrollTo({ left: i * paso, behavior: 'smooth' });
    renderProxplanesNavFor(block);
  }
  function renderProxplanesNavFor(block) {
    const track = block && block.querySelector('[data-proxplanes-track]');
    const shell = block && block.querySelector('[data-proxplanes-shell]');
    const prev = block && block.querySelector('[data-pp-mov-prev]');
    const next = block && block.querySelector('[data-pp-mov-next]');
    if (!block || !track || !shell || !prev || !next) return;
    const n = track.querySelectorAll('.pp-mov-card').length;
    if (!block.classList.contains('is-on') || n < 1) {
      shell.hidden = true;
      shell.setAttribute('aria-hidden', 'true');
      prev.hidden = true;
      next.hidden = true;
      block.classList.remove('proxplanes-movil--many');
      return;
    }
    const paso = ppMovPaso(track, block);
    const idx = paso > 0 ? Math.min(n - 1, Math.max(0, Math.round(track.scrollLeft / paso))) : 0;
    block._ppMovIndice = idx;
    shell.hidden = false;
    shell.removeAttribute('aria-hidden');
    if (ppMovEsDesktop(block)) {
      const max = Math.max(0, track.scrollWidth - track.clientWidth);
      const needsCarousel = n > 2 && max > 2;
      block.classList.toggle('proxplanes-movil--many', needsCarousel);
      if (!needsCarousel) {
        prev.hidden = true;
        next.hidden = true;
        return;
      }
      prev.hidden = track.scrollLeft <= 2;
      next.hidden = track.scrollLeft >= max - 2;
      return;
    }
    block.classList.remove('proxplanes-movil--many');
    prev.hidden = n < 2 || idx <= 0;
    next.hidden = n < 2 || idx >= n - 1;
  }
  function renderProxplanesNav() {
    inicioBlocks('[data-proxplanes-block]').forEach(renderProxplanesNavFor);
  }
  function renderEncursosMovilIndicador() {
    renderEncursosMovilNav();
  }
  function bindPlanesUnifScroll() {
    if (bindPlanesUnifScroll._ok) return;
    bindPlanesUnifScroll._ok = true;
    document.addEventListener('scroll', function (ev) {
      const track = ev.target.closest && ev.target.closest('[data-planes-unif-track]');
      if (!track) return;
      const block = track.closest('[data-planes-unif-block]');
      if (block) renderPlanesUnifMoreFor(block);
    }, true);
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
      block.classList.remove('encursos-movil--solo', 'encursos-movil--multi');
      track.innerHTML = '';
      renderEncursosMovilNavFor(block);
      return;
    }
    block.classList.remove('is-empty');
    block.classList.add('is-on');
    block.classList.toggle('encursos-movil--solo', lista.length === 1);
    block.classList.toggle('encursos-movil--multi', lista.length > 1);
    track.innerHTML = lista.map(function (enc) { return cardFn(enc, estado); }).join('');
    requestAnimationFrame(function () {
      renderEncursosMovilNavFor(block);
    });
  }
  function renderInicioPlanesLibretaBadges(estado) {
    inicioBlocks('[data-inicio-planes-bloque]').forEach(function (bloque) {
      var badges = bloque.querySelector('[data-inicio-planes-badges]');
      if (!badges) return;
      var nCurso = encuentrosEnCursoAhora(cacheInsp, estado).length;
      var nProx = proximosPlanesFuturos(cacheInsp, estado).length;
      var parts = [];
      if (nCurso > 0) {
        parts.push('<span class="inicio-planes-libreta-badge inicio-planes-libreta-badge--curso" title="' + String(nCurso) + ' en curso">' +
          '<svg class="inicio-planes-libreta-badge-ico" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.3-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 12a8 8 0 0 1-13.3 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
          '<span>' + String(nCurso) + ' en curso</span></span>');
      }
      if (nProx > 0) {
        parts.push('<span class="inicio-planes-libreta-badge inicio-planes-libreta-badge--prox" title="' + String(nProx) + ' pr\u00f3ximos">' +
          '<svg class="inicio-planes-libreta-badge-ico" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 8v4l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
          '<span>' + String(nProx) + ' pr\u00f3ximos</span></span>');
      }
      if (parts.length) {
        badges.innerHTML = parts.join('');
        badges.hidden = false;
        badges.removeAttribute('aria-hidden');
      } else {
        badges.innerHTML = '';
        badges.hidden = true;
        badges.setAttribute('aria-hidden', 'true');
      }
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
      vecinosTotalBadge: String(met.vecinos),
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
        '<span class="obj-pareja-nombres">' + esc(nombreDe(ids[0])) + ' - ' + esc(nombreDe(ids[1])) + '</span>' +
        (crisis ? '<span class="pareja-crisis-sello">EN CRISIS</span>' : '');
      strip.appendChild(row);
    });
    if (!parejas || !parejas.length) {
      strip.innerHTML = '<p class="muted">A\u00fan no hay parejas registradas.</p>';
    }
  }


  function renderParejasModalList(parejas) {
    var list = $('[data-parejas-modal-list]');
    var teaser = $('[data-parejas-teaser]');
    if (!list) return;
    var arr = parejas || [];
    var crisis = arr.filter(function (r) { return esCrisisPareja(r); }).length;
    if (teaser) {
      if (!arr.length) teaser.textContent = 'A\u00fan no hay parejas registradas.';
      else if (crisis > 0) teaser.textContent = crisis + ' en crisis \u00b7 ' + arr.length + ' pareja' + (arr.length === 1 ? '' : 's');
      else teaser.textContent = arr.length + ' pareja' + (arr.length === 1 ? '' : 's') + ' en el pueblo';
    }
    if (!arr.length) {
      list.innerHTML = '<p class="par-vacio">A\u00fan no hay parejas registradas.</p>';
      return;
    }
    list.innerHTML = arr.map(function (rel) {
      var ids = idsPareja(rel);
      if (!ids || ids.length < 2) return '';
      var c = esCrisisPareja(rel);
      var tok = function (id) { return htmlCaraToken(id, { imgClass: 'obj-pareja-cara' }); };
      return '<article class="par-modal-item' + (c ? ' is-crisis' : '') + '">' +
        '<span class="obj-pareja-fotos">' + tok(ids[0]) +
        '<span class="obj-pareja-enlace" aria-hidden="true"></span>' + tok(ids[1]) + '</span>' +
        '<div class="par-modal-copy">' +
        '<p class="obj-pareja-nombres">' + esc(nombreDe(ids[0])) + ' - ' + esc(nombreDe(ids[1])) + '</p>' +
        (c ? '<p class="par-modal-crisis">En crisis</p>' : '') +
        '</div></article>';
    }).join('');
  }
  function updateMpDuoMisiones(items) {
    var hoy = items || [];
    var cumplidas = hoy.filter(function (m) { return (m.estado || '') === 'cumplida'; }).length;
    var pend = hoy.filter(function (m) {
      var est = m.estado || 'pendiente';
      return est === 'pendiente' || est === 'activa';
    }).length;
    inicioAll('[data-misiones-tit-corta]').forEach(function (el) {
      el.textContent = 'MISIONES';
    });
    inicioAll('[data-misiones-badge]').forEach(function (el) {
      if (!hoy.length) {
        el.textContent = '';
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
        return;
      }
      el.textContent = String(hoy.length);
      el.hidden = false;
      el.removeAttribute('aria-hidden');
    });
    inicioAll('[data-misiones-resumen-corta]').forEach(function (el) {
      if (!hoy.length) { el.textContent = 'Sin misiones hoy'; return; }
      if (pend > 0) el.textContent = pend + ' pendiente' + (pend === 1 ? '' : 's');
      else el.textContent = hoy.length + ' misi\u00f3n' + (hoy.length === 1 ? '' : 'es');
    });
    inicioAll('[data-misiones-progreso]').forEach(function (el) {
      if (!hoy.length) {
        el.textContent = '';
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
        return;
      }
      el.textContent = cumplidas + '/' + hoy.length;
      el.hidden = false;
      el.removeAttribute('aria-hidden');
    });
  }
  function updateMpDuoParejas(parejas) {
    var arr = parejas || [];
    var crisis = arr.filter(function (r) { return esCrisisPareja(r); }).length;
    var met = cacheInsp ? metricasSociales(cacheInsp) : null;
    var cap = met ? met.cap : '?';
    inicioAll('[data-parejas-tit-corta]').forEach(function (el) {
      el.textContent = 'PAREJAS';
    });
    inicioAll('[data-parejas-resumen-corta]').forEach(function (el) {
      if (!arr.length) el.textContent = '0 de ' + cap;
      else if (crisis > 0) el.textContent = crisis + ' en crisis';
      else el.textContent = arr.length + ' pareja' + (arr.length === 1 ? '' : 's');
    });
  }
function renderInicioMpDuo(misiones, parejas) {
    if (misiones !== undefined) updateMpDuoMisiones(misiones);
    if (parejas !== undefined) updateMpDuoParejas(parejas);
  }  function renderParejasStripIn(scopeSel, parejas) {
    document.querySelectorAll(scopeSel).forEach(function (root) {
      renderParejasStripEl(root.querySelector('[data-parejas-strip]'), parejas);
    });
    renderInicioMpDuo(undefined, parejas);
    renderParejasModalList(parejas);
  }

  function renderInicioMobile(vm, estado) {
    setAllHtml('[data-resumen-stats]', vm.statsHtml);
    setAllText('[data-vecinos-poblacion]', vm.vecinosPoblacion);
    setAllText('[data-cotilleo-teaser]', vm.cotilleoTeaser);
    document.querySelectorAll('.inicio-mobile-tiles [data-vecinos-total-badge]').forEach(function (el) {
      const n = parseInt(vm.vecinosTotalBadge, 10) || 0;
      if (n > 0) {
        el.textContent = String(n);
        el.hidden = false;
      } else {
        el.textContent = '';
        el.hidden = true;
      }
    });
    renderVecinosPreviewIn('.inicio-mobile');
    renderParejasStripIn('.inicio-mobile', vm.parejas);
    if (cacheMisionesStripItems) renderMisionesStripIn('.inicio-mobile.inicio-mobile-feed', cacheMisionesStripItems);
    renderPlanesUnifMovil(estado);
    renderProximosPlanesMovil(estado);
    renderEncursosMovil(estado);
  }

  function renderInicioDesktop(vm, estado) {
    setAllHtml('[data-resumen-stats]', vm.statsHtml);
    setAllText('[data-vecinos-poblacion]', vm.vecinosPoblacion);
    setAllText('[data-cotilleo-teaser]', vm.cotilleoTeaser);
    renderVecinosPreviewIn('.inicio-desktop');
    renderParejasStripIn('.inicio-desktop', vm.parejas);
    if (cacheMisionesStripItems) renderMisionesStripIn('.inicio-desktop', cacheMisionesStripItems);
    renderProximosPlanesMovil(estado);
    renderEncursosMovil(estado);
    renderInicioPlanesLibretaBadges(estado);
  }

  function renderInicio(estado, buzon, diario) {
    const vm = buildInicioViewModel(estado, buzon, diario);
    renderInicioMobile(vm, estado);
    renderInicioDesktop(vm, estado);
    renderProximoEventoPueblo(estado);
    actualizarCotiBadgesUI();
  }


  function tituloEventoPuebloUi(txt) {
    var s = String(txt || '').trim();
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
  }
  function renderProximoEventoPueblo(estado) {
    const slots = document.querySelectorAll('[data-proximo-evento-slot]');
    if (!slots.length) return;
    const ev = estado && estado.proximo_evento_pueblo;
    const vacio = !ev || (!ev.nombre_ui && !ev.nombre);
    slots.forEach(function (slot) {
      if (vacio) {
        slot.hidden = true;
        slot.setAttribute('aria-hidden', 'true');
        return;
      }
      slot.hidden = false;
      slot.removeAttribute('aria-hidden');
      const card = slot.querySelector('[data-proximo-evento-card]');
      const tagTxt = slot.querySelector('[data-proximo-evento-tag-txt]');
      const tit = slot.querySelector('[data-proximo-evento-tit]');
      const meta = slot.querySelector('[data-proximo-evento-meta]');
      const ico = slot.querySelector('[data-proximo-evento-ico]');
      const enMarcha = String(ev.estado || '') === 'en_curso';
      if (card) card.classList.toggle('inicio-evento-card--marcha', enMarcha);
      if (tagTxt) tagTxt.textContent = enMarcha ? '\u00a1AHORA!' : 'EVENTO PUEBLO';
      var pie = card && card.querySelector('[data-proximo-evento-pie]');
      if (card && !pie) {
        pie = document.createElement('div');
        pie.className = 'inicio-evento-pie';
        pie.setAttribute('data-proximo-evento-pie', '');
        card.appendChild(pie);
      }
      var idsAp = Array.isArray(ev.participantes_apuntados) ? ev.participantes_apuntados : [];
      if (pie) {
        if (idsAp.length) {
          pie.hidden = false;
          pie.innerHTML = '<div class="inicio-evento-faces prox-faces prox-faces--grupo">' +
            carasPlanHtml(idsAp, Math.min(4, idsAp.length)) + '</div>' +
            '<p class="inicio-evento-asisten">' + esc(asistentesEventoPuebloTxt(idsAp)) + '</p>';
        } else {
          pie.hidden = true;
          pie.innerHTML = '';
        }
      }
      if (tit) tit.textContent = tituloEventoPuebloUi(ev.nombre_ui || ev.nombre || '');
      if (typeof pintarProximoEventoIco === 'function') pintarProximoEventoIco(ico, ev);
      else if (ico) ico.textContent = ev.icono || '\u{1F4C5}';
      if (meta) meta.textContent = ev.meta_ui || '';
      var cta = slot.querySelector('[data-proximo-evento-cta]') || (card && card.querySelector('[data-proximo-evento-participar]'));
      var plazas = parseInt(ev.plazas_disponibles, 10);
      if (isNaN(plazas)) plazas = 0;
      var pendienteSel = !!(ev.pendiente_seleccion || ev.seleccion_estado === 'pendiente_asistentes'); var puedeApuntar = pendienteSel;
      if (!cta && card) {
        cta = document.createElement('button');
        cta.type = 'button';
        cta.className = 'inicio-evento-cta';
        cta.setAttribute('data-proximo-evento-participar', '1');
        cta.innerHTML = '<span class="inicio-evento-cta-txt">PARTICIPAR \u203A</span><span class="inicio-evento-cta-spark" aria-hidden="true"></span>';
        card.appendChild(cta);
      }
      if (cta) {
        var ctaTxtEl = cta.querySelector('[data-proximo-evento-cta-txt]') || cta.querySelector('.inicio-evento-cta-txt');
        if (ctaTxtEl) ctaTxtEl.textContent = ev.cta_label || '¿Quién va?';
        cta.hidden = !puedeApuntar;
        cta.disabled = !puedeApuntar;
        if (puedeApuntar) {
          cta.onclick = function (evClick) {
            evClick.preventDefault();
            evClick.stopPropagation();
            abrirOrganizarEventoPueblo(ev);
          };
        }
      }
    });
  }

  function pintarProximoEventoIco(ico, ev) {
    if (!ico) return;
    const src = eventoPuebloImgSrc(null, cacheInsp, ev);
    const catId = ev && ev.catalogo_id ? String(ev.catalogo_id) : '';
    const esEvtImg = !!(catId && EVENTO_PUEBLO_IMG[catId]) || !!(ev && ev.illustracion);
    ico.className = 'inicio-evento-ico';
    if (src) {
      ico.classList.add(esEvtImg ? 'inicio-evento-ico--evento' : 'inicio-evento-ico--lugar');
      ico.innerHTML = '<img src="' + esc(src) + '" alt="" loading="lazy" decoding="async">';
      var img = ico.querySelector('img');
      if (img) {
        img.onerror = function () {
          var lugSrc = orgLugarImg((ev && ev.lugar) || '');
          if (lugSrc && img.src.indexOf(lugSrc) < 0) {
            img.onerror = null;
            img.src = lugSrc;
            ico.classList.remove('inicio-evento-ico--evento');
            ico.classList.add('inicio-evento-ico--lugar');
            return;
          }
          ico.classList.remove('inicio-evento-ico--lugar', 'inicio-evento-ico--evento');
          ico.classList.add('inicio-evento-ico--fallback');
          ico.innerHTML = '';
        };
      }
    } else {
      ico.classList.add('inicio-evento-ico--fallback');
      ico.innerHTML = '';
    }
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
    bindPlanesUnifScroll();
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
    return est + ' Â· ' + d.horario;
  }

  function pintarConsultaEdArt(lugId) {
    var el = $('[data-q-art]');
    if (!el) return;
    var img = orgLugarImg(lugId);
    if (img) {
      el.innerHTML = '<img src="' + esc(img) + '" alt="" loading="lazy" decoding="async"/>';
      el.hidden = false;
      el.setAttribute('aria-hidden', 'false');
    } else {
      el.innerHTML = '';
      el.hidden = true;
      el.setAttribute('aria-hidden', 'true');
    }
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
        estadoCls = 'consulta-ed-estado--abierto';
      } else if (d.abierto_ahora === false) {
        estado = 'Cerrado ahora';
        estadoCls = 'consulta-ed-estado--cerrado';
      }
      var inner = '';
      if (estado) {
        inner += '<span class="consulta-ed-estado ' + estadoCls + '">' + estado + '</span>';
      }
      inner += '<span class="consulta-ed-horas">' + esc(d.horario) + '</span>';
      return '<div class="consulta-ed-meta-block">' + inner + '</div>';
    }).join('');
    el.hidden = false;
  }

  function pintarOrgLugarHorario(lugId) {
    var el = $('[data-org-lugar-horario]');
    if (!el) return;
    el.innerHTML = '';
    el.hidden = true;
    var box = $('[data-org-dd-lugar]');
    if (!box) return;
    var id = lugId || ($('[data-org-lugar]') && $('[data-org-lugar]').value) || org.lugar || '';
    var trig = box.querySelector('.org-dd-trigger');
    if (!trig || !id) return;
    var label = trig.querySelector('.org-lugar-card-nom');
    var nom = label ? label.textContent : (nombreLugarTitulo(id, id) || 'Elegirâ');
    trig.innerHTML = orgDdTriggerContent('lugar', nom, id);
  }

  var NEC_ICON_PATHS = { social: 'assets/img/necesidades/social.png', diversion: 'assets/img/necesidades/diversion.png', actividad: 'assets/img/necesidades/actividad.png', calma: 'assets/img/necesidades/calma.png' };
  var NEC_NOMBRES = { social: 'Socializar', diversion: 'Diversi\u00f3n', actividad: 'Actividad', calma: 'Calma' };
  var NEC_ROL_LABEL = { principal: 'principal', secundaria: 'secundaria' };

  function necIconHtml(necId, size) {
    var src = NEC_ICON_PATHS[necId] || '';
    var s = size || 20;
    if (!src) return '';
    return '<img src="' + esc(src) + '" alt="' + esc(necId) + '" class="nec-icon" width="' + s + '" height="' + s + '" loading="lazy">';
  }

  function necLugarChipsHtml(necesidades) {
    if (!necesidades || typeof necesidades !== 'object') return '';
    var chips = '';
    var orden = ['social', 'diversion', 'actividad', 'calma'];
    orden.forEach(function (necId) {
      var rol = necesidades[necId];
      if (!rol) return;
      var nom = NEC_NOMBRES[necId] || necId;
      var rolLabel = NEC_ROL_LABEL[rol] || rol;
      var sec = rol === 'secundaria' ? ' org-lc-nec--sec' : '';
      chips += '<span class="org-lc-nec' + sec + '" title="' + esc(nom) + ' \u2014 ' + esc(rolLabel) + '" aria-label="' + esc(nom) + ' (' + esc(rolLabel) + ')">' + necIconHtml(necId, 16) + '</span>';
    });
    return chips;
  }

  function renderOrgLugaresCards() {
    var grid = $('[data-org-lugares-grid]');
    if (!grid) return;
    var lugares = destinosOperativos();
    grid.innerHTML = '';
    if (!lugares.length) { grid.innerHTML = '<p class="mini org-lugares-vacio">Sin lugares disponibles.</p>'; return; }
    var currentId = org.lugar || '';
    lugares.forEach(function (d) {
      var on = String(d.id) === String(currentId);
      var img = orgLugarImg(d.id);
      var estado = '', estadoCls = '';
      if (d.abierto_ahora === true) { estado = 'Abierto'; estadoCls = 'org-lugar-estado--abierto'; }
      else if (d.abierto_ahora === false) { estado = 'Cerrado'; estadoCls = 'org-lugar-estado--cerrado'; }
      var horario = d.horario || '';
      var necHtml = necLugarChipsHtml(d.necesidades);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'org-lc' + (on ? ' is-on' : '') + (necHtml ? ' has-necs' : '');
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.setAttribute('data-org-lug-id', d.id);
      var imgHtml = img
        ? '<img src="' + esc(img) + '" alt="" loading="lazy" decoding="async"/>'
        : '<span class="org-lc-fallback" aria-hidden="true"></span>';
      var isMobileLugares = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
      btn.innerHTML =
        '<span class="org-lc-art">' + imgHtml + '</span>' +
        '<span class="org-lc-body">' +
          '<span class="org-lc-nom">' + esc(d.nombre || d.id) + '</span>' +
          (estado ? '<span class="org-lugar-estado ' + estadoCls + '">' + esc(estado) + '</span>' : '') +
          (!isMobileLugares && horario ? '<span class="org-lc-horario">' + esc(horario) + '</span>' : '') +
          (necHtml ? '<span class="org-lc-necs">' + necHtml + '</span>' : '') +
        '</span>';
      btn.addEventListener('click', function (ev) {
        ev.preventDefault(); ev.stopPropagation();
        org.lugar = d.id;
        renderOrgLugaresCards();
        refreshOrgHorasGrid();
        actualizarOrgCrearBtn();
      });
      grid.appendChild(btn);
    });
  }

  function renderOrgDiasStrip() {
    var strip = $('[data-org-dias-strip]');
    if (!strip) return;
    var rv = (cacheEstado && cacheEstado.reloj_vista) || {};
    var dias = rv.proximos_dias || [];
    strip.innerHTML = '';
    if (!dias.length) { strip.innerHTML = '<p class="mini">Sin dias disponibles.</p>'; return; }
    org.dia = org.dia || (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo);
    if (org.dia && !dias.some(function (d) { return String(d.dia_pueblo) === String(org.dia); })) {
      org.dia = dias.length ? dias[0].dia_pueblo : null;
    }
    dias.forEach(function (d) {
      var on = String(d.dia_pueblo) === String(org.dia);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'org-dc' + (on ? ' is-on' : '');
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.setAttribute('data-org-dia-val', d.dia_pueblo);
      btn.textContent = d.etiqueta || ('Dia ' + d.dia_pueblo);
      if (d.etiqueta) {
        var parts = d.etiqueta.split(' ');
        if (parts.length >= 2) {
          btn.innerHTML = '<span class="org-dc-dia">' + esc(parts[0]) + '</span><span class="org-dc-fecha">' + esc(parts.slice(1).join(' ')) + '</span>';
        }
      }
      btn.addEventListener('click', function (ev) {
        ev.preventDefault(); ev.stopPropagation();
        org.dia = d.dia_pueblo;
        renderOrgDiasStrip();
        refreshOrgHorasGrid();
        actualizarOrgCrearBtn();
      });
      strip.appendChild(btn);
    });
  }

  async function refreshOrgHorasGrid() {
    var grid = $('[data-org-horas-grid]');
    if (!grid) { await refreshOrgHoras(); return; }
    grid.innerHTML = '';
    if (!orgParticipantesListos()) {
      grid.innerHTML = '<p class="mini org-horas-vacio">Selecciona vecinos primero.</p>';
      org.hora = 0;
      actualizarOrgCrearBtn();
      return;
    }
    if (!org.lugar) {
      grid.innerHTML = '<p class="mini org-horas-vacio">Elige un lugar.</p>';
      org.hora = 0;
      actualizarOrgCrearBtn();
      return;
    }
    if (!org.dia) {
      grid.innerHTML = '<p class="mini org-horas-vacio">Elige un dia.</p>';
      org.hora = 0;
      actualizarOrgCrearBtn();
      return;
    }
    var tipo = orgModo() === 'solo' ? 'individual' : (org.tipo || 'conocerse');
    try {
      var parts = orgSeleccionados();
      var r = await api('agenda.slots_compatibles', {
        participantes: parts,
        tipo: tipo,
        lugar_id: org.lugar,
        desde_dia: org.dia,
        max_dias: 7,
        max_slots: 48
      }, 'GET');
      if (!r.ok) {
        grid.innerHTML = '<p class="mini org-horas-vacio">' + esc(mensajeErrorOrgApi(r, 'No hay horarios disponibles.')) + '</p>';
        org.hora = 0;
        actualizarOrgCrearBtn();
        return;
      }
      var slots = (r.slots || []).filter(function (s) { return (s.dia || 0) === org.dia; });
      slots.sort(function (a, b) { return (a.hora || 0) - (b.hora || 0); });
      if (!slots.length) {
        grid.innerHTML = '<p class="mini org-horas-vacio">Sin huecos este dia.</p>';
        org.hora = 0;
        var hintEl = document.querySelector('[data-org-horas-hint]');
        if (hintEl && r.primera_compatible && (r.primera_compatible.dia || 0) !== org.dia) {
          hintEl.textContent = 'Primera compatible: ' + (r.primera_compatible.etiqueta_ui || '');
          hintEl.hidden = false;
        } else if (hintEl) { hintEl.hidden = true; }
        actualizarOrgCrearBtn();
        return;
      }
      if (org.hora && !slots.some(function (s) { return String(s.hora) === String(org.hora); })) {
        org.hora = slots[0].hora;
      }
      if (!org.hora) org.hora = slots[0].hora;
      slots.forEach(function (s) {
        var on = String(s.hora) === String(org.hora);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'org-hc' + (on ? ' is-on' : '');
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.setAttribute('data-org-hora-val', s.hora);
        btn.textContent = s.etiqueta_hora || String(s.hora).padStart(2, '0') + ':00';
        btn.addEventListener('click', function (ev) {
          ev.preventDefault(); ev.stopPropagation();
          org.hora = s.hora;
          refreshOrgHorasGrid();
          actualizarOrgCrearBtn();
        });
        grid.appendChild(btn);
      });
      var hintEl2 = document.querySelector('[data-org-horas-hint]');
      if (hintEl2) {
        if (r.hint_ui) { hintEl2.textContent = r.hint_ui; hintEl2.hidden = false; }
        else { hintEl2.hidden = true; }
      }
    } catch (e) {
      grid.innerHTML = '<p class="mini org-horas-vacio">Error cargando horarios.</p>';
      org.hora = 0;
    }
    actualizarOrgCrearBtn();
  }

  function renderOrgLugarInfo(lugId) {
    var wrap = $('[data-org-step-lugar-info]');
    var el = $('[data-org-lugar-info]');
    if (!wrap || !el) return;
    var id = lugId || org.lugar || '';
    if (!id) { wrap.hidden = true; el.innerHTML = ''; return; }
    var d = destinoOperativoPorId(id) || {};
    var desc = orgLugarDesc(id);
    var horario = d.horario || '';
    var abierto = d.abierto_ahora;
    var img = orgLugarImg(id);
    var estadoHtml = '';
    if (abierto === true) estadoHtml = '<span class="org-lugar-estado org-lugar-estado--abierto">Abierto ahora</span>';
    else if (abierto === false) estadoHtml = '<span class="org-lugar-estado org-lugar-estado--cerrado">Cerrado</span>';
    var horarioHtml = horario ? '<span class="org-li-horario">' + esc(horario) + '</span>' : '';
    var imgHtml = img ? '<img src="' + esc(img) + '" alt="" loading="lazy" decoding="async"/>' : '';
    var necHtml = necLugarChipsHtml(d.necesidades);
    el.innerHTML =
      '<div class="org-li-art">' + imgHtml + '</div>' +
      '<div class="org-li-body">' +
        '<span class="org-li-nom">' + esc(nombreLugarTitulo(id, id)) + '</span>' +
        '<span class="org-li-desc">' + esc(desc) + '</span>' +
        '<span class="org-li-meta">' + estadoHtml + horarioHtml + '</span>' +
        (necHtml ? '<span class="org-li-necs">' + necHtml + '</span>' : '') +
      '</div>';
    wrap.hidden = false;
  }

  function renderOrgEstado() {
    var wrap = $('[data-org-step-estado]');
    var el = $('[data-org-estado]');
    if (!wrap || !el) return;
    var parts = orgSeleccionados();
    if (!parts.length && !org.lugar) { wrap.hidden = true; el.innerHTML = ''; return; }
    var nombres = parts.map(function (id) { return nombreDe(id); }).join(', ');
    var lugar = org.lugar ? nombreLugarTitulo(org.lugar, org.lugar) : '';
    var hora = org.hora ? String(org.hora).padStart(2, '0') + ':00' : '';
    var dia = org.dia || '';
    var estadoLines = [];
    if (nombres) estadoLines.push('<span class="org-estado-row"><span class="org-estado-label">Vecinos:</span> ' + esc(nombres) + '</span>');
    if (lugar) estadoLines.push('<span class="org-estado-row"><span class="org-estado-label">Lugar:</span> ' + esc(lugar) + '</span>');
    if (dia || hora) estadoLines.push('<span class="org-estado-row"><span class="org-estado-label">Cuando:</span> Dia ' + dia + (hora ? ' a las ' + esc(hora) : '') + '</span>');
    el.innerHTML = estadoLines.join('');
    wrap.hidden = !estadoLines.length;
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

  function placeHabEnZona(box, p, i, total, prevZona, newZona) {
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
    var left = slot.left + jx;
    var top  = slot.top  + jy;

    // Clamp: keep entire token visual inside map boundary
    // Visual radius from .hab center (derived from CSS, not hardcoded):
    //   .cara half-size: 20px (40px width/height)
    //   ::before inset: -8px â†’ 28px from center (sides/top)
    //   ::after: bottom -5px + height 7px â†’ 32px from center (bottom)
    //   Idle animation peak: translate(Â±3, Â±4) scale(1.04)
    //   Animated bottom = 32 * 1.04 + 4 = 37.3px
    //   Animated top/sides = 28 * 1.04 + 4 = 33.1px
    var _zonaHit = box.closest('.mapa-zona-hit');
    var _layer   = box.closest('[data-mapa-zonas]');
    if (_zonaHit && _layer) {
      var _zR = _zonaHit.getBoundingClientRect();
      var _lR = _layer.getBoundingClientRect();
      var _zL = (_zR.left - _lR.left) / _lR.width;
      var _zT = (_zR.top  - _lR.top)  / _lR.height;
      var _zW = _zR.width  / _lR.width;
      var _zH = _zR.height / _lR.height;
      var _cx = _zL + (left / 100) * _zW;
      var _cy = _zT + (top  / 100) * _zH;
      var _radX  = 34; // 28px * 1.04 + 3px translateX (sides)
      var _radTop = 34; // 28px * 1.04 + 4px translateY (top)
      var _radBot = 38; // 32px * 1.04 + 4px translateY (bottom, includes ::after)
      var _minX = _radX / _lR.width;
      var _maxX = 1 - _radX / _lR.width;
      var _minY = _radTop / _lR.height;
      var _maxY = 1 - _radBot / _lR.height;
      _cx = Math.max(_minX, Math.min(_maxX, _cx));
      _cy = Math.max(_minY, Math.min(_maxY, _cy));
      left = ((_cx - _zL) / _zW) * 100;
      top  = ((_cy - _zT) / _zH) * 100;
    }

    el.style.left = left.toFixed(2) + '%';
    el.style.top  = top.toFixed(2)  + '%';
    
    // Movement animation: if NPC moved from a different zone, add movement class
    if (prevZona && prevZona !== newZona) {
      el.classList.add('hab-moviendo');
    }
    
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
    if (p.nombre) {
      el.insertAdjacentHTML('beforeend', '<span class="hab-nombre">' + esc(p.nombre) + '</span>');
    }
    box.appendChild(el);
  }

  function marcarConsultaLugar(el, lugarId) {
    if (!el) return;
    el.setAttribute('data-consulta-lugar', String(lugarId || '').replace(/^lug_/, ''));
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
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' Â· ');
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
      actualizarNotaAtras();
      return;
    }
    abrirQuienZona(zonaId, ops[0] ? ops[0].id : null, zonaBtn, silentHist);
  }


  var quienTemaActivo = null;
  var quienGenteCache = null;
  var quienAvatarFlip = false;
  var CONSULTA_ED_SWAP_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 7h11.2l-2.6-2.6L17 3l5 5-5 5-1.4-1.4L18.2 9H7V7zm10 10H5.8l2.6 2.6L7 21l-5-5 5-5 1.4 1.4L5.8 15H17v2z"/></svg>';

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
    quienGenteCache = gente || [];
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
    list.className = 'consulta-ed-avatars';
    var ordered = quienAvatarFlip ? gente.slice().reverse() : gente.slice();
    ordered.forEach(function (p, idx) {
      if (idx === 1 && ordered.length === 2) {
        var swap = document.createElement('button');
        swap.type = 'button';
        swap.className = 'consulta-ed-swap';
        swap.setAttribute('aria-label', 'Intercambiar orden');
        swap.innerHTML = CONSULTA_ED_SWAP_SVG;
        swap.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          quienAvatarFlip = !quienAvatarFlip;
          pintarQuienResidentes(quienGenteCache, quienTemaActivo, vacioTxt);
        });
        list.appendChild(swap);
      }
      var card = document.createElement('div');
      card.className = 'consulta-ed-persona';
      var caraWrap = document.createElement('div');
      caraWrap.innerHTML = htmlCaraToken(p.id, { wrapClass: 'consulta-ed-cara' });
      card.appendChild(caraWrap.firstChild);
      var nom = document.createElement('span');
      nom.className = 'consulta-ed-nombre';
      nom.textContent = p.nombre || p.id || '';
      card.appendChild(nom);
      list.appendChild(card);
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
    temas.sort(function (a, b) {
      var pa = MAPA_TEMA_PRIORIDAD[a.categoria];
      var pb = MAPA_TEMA_PRIORIDAD[b.categoria];
      if (pa == null) pa = 99;
      if (pb == null) pb = 99;
      return pa - pb;
    });
    var activos = temas;
    if (temaActivoId) {
      activos = temas.filter(function (t) { return t.tema_id === temaActivoId; });
    } else {
      activos = [temas[0]];
    }
    if (!activos.length) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = activos.slice(0, 1).map(function (tv) {
      var tag = tv.categoria_etiqueta || 'Tema';
      return '<section class="consulta-ed-tema-card">' +
        '<div class="consulta-ed-tema-head">' +
        '<p class="consulta-ed-tema-kicker">Aquí hay tema</p>' +
        '<span class="consulta-ed-tema-tag">' + esc(tag) + '</span>' +
        '</div>' +
        '<p class="consulta-ed-tema-txt">' + esc(tv.texto || '') + '</p>' +
        (tv.pista ? '<p class="consulta-ed-tema-pista">' + esc(tv.pista) + '</p>' : '') +
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
    if (window.AHTScreenManager && window.AHTScreenManager.injectDoodles) {
      window.AHTScreenManager.injectDoodles('edificios');
    }
    var destinosHorario = destId
      ? [destinoOperativoPorId(destId)].filter(Boolean)
      : destinosOperativosZona(zonaId);
    if (destId) {
      var dnom = destinosHorario[0] && destinosHorario[0].nombre;
      if (dnom) $('[data-q-tit]').textContent = dnom;
    }
    pintarHorarioQuien(destinosHorario);
    var lugIdZ = destId || (destinosHorario[0] && destinosHorario[0].id) || '';
    var subElZ = $('[data-q-subtitle]');
    if (subElZ) subElZ.textContent = LUGAR_SUBTITULO[lugIdZ] || '';
    var necElZ = $('[data-q-necesidades]');
    if (necElZ) {
      var metaNecZ = LUGAR_META[lugIdZ] || {};
      var necListZ = (metaNecZ.necesidades || []);
      necElZ.innerHTML = necListZ.map(function (n) {
        var k = n.toLowerCase();
        var icon = NEC_ICON_PATHS[k] || '';
        if (icon) return '<span class="qed-nec-pill"><img src="' + esc(icon) + '" alt="" class="qed-nec-ico"/>' + n + '</span>';
        return '<span class="qed-nec-pill">' + n + '</span>';
      }).join('');
    }
    var offlineElZ = $('[data-q-offline]');
    var vacioElZ = $('[data-q-sum]');
    if (offlineElZ) offlineElZ.hidden = gente.length > 0;
    if (vacioElZ && !gente.length) vacioElZ.hidden = true;
    quienTemaActivo = null;
    quienAvatarFlip = false;
    pintarConsultaEdArt(lugIdZ);
    pintarQuienResidentes(gente, null, gente.length ? '' : 'No hay ni un alma.');
    var countElZ = $('[data-q-count]');
    if (countElZ) countElZ.textContent = gente.length ? gente.length + ' persona' + (gente.length > 1 ? 's' : '') : '';
    pintarQuienTema(gente, null);
    var descElZ = $('[data-q-desc]');
    if (descElZ) descElZ.textContent = orgLugarDesc(lugIdZ);
    var necLugElZ = $('[data-q-nec-lugares]');
    if (necLugElZ) {
      var metaNecLZ = LUGAR_META[lugIdZ] || {};
      var necNamesZ = (metaNecLZ.necesidades || []);
      necLugElZ.innerHTML = necNamesZ.map(function (n) {
        var k = n.toLowerCase();
        var icon = NEC_ICON_PATHS[k] || '';
        var frase = NEC_FRASES_LUGAR[k] || '';
        return '<div class="qed-nec-lugar-row">'
          + (icon ? '<img src="' + esc(icon) + '" alt="" class="qed-nec-lugar-ico"/>' : '')
          + '<div class="qed-nec-lugar-text"><span class="qed-nec-lugar-name">' + esc(n) + '</span>'
          + (frase ? '<span class="qed-nec-lugar-frase">' + esc(frase) + '</span>' : '')
          + '</div></div>';
      }).join('');
    }
    var curElZ = $('[data-q-curiosities]');
    if (curElZ) {
      var cursZ = LUGAR_CURIOSIDADES[lugIdZ] || [];
      curElZ.innerHTML = cursZ.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('');
    }
    var footerElZ = $('[data-q-footer-quote]');
    if (footerElZ) footerElZ.textContent = LUGAR_FRASES[lugIdZ] || '';
    var box = $('[data-q-btns]');
    box.innerHTML = '';
    destinosOperativosZona(zonaId).forEach(function (d) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'consulta-ed-cta';
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
    if (t.length > 72) t = t.slice(0, 69) + '\u2026';
    if (!t) t = (m && m.titulo) || 'Objetivo';
    return t;
  }

  function updateMisionesPapelMeta(items) {
    var hoy = items || [];
    var pend = hoy.filter(function (m) {
      var est = m.estado || 'pendiente';
      return est === 'pendiente' || est === 'activa';
    }).length;
    inicioAll('.obj-misiones-papel').forEach(function (el) {
      var meta = el.querySelector('.obj-misiones-papel-meta');
      if (!meta) {
        meta = document.createElement('span');
        meta.className = 'obj-misiones-papel-meta';
        var tit = el.querySelector('.obj-misiones-papel-tit');
        if (tit && tit.parentNode) tit.parentNode.insertBefore(meta, tit.nextSibling);
      }
      if (pend > 0) {
        meta.textContent = pend + ' pendiente' + (pend === 1 ? '' : 's');
        meta.hidden = false;
      } else {
        meta.textContent = '';
        meta.hidden = true;
      }
    });
  }

  function actualizarMisionesCompletadas(items) {
    var hoy = items || [];
    var todas = hoy.length > 0 && hoy.every(function (m) { return (m.estado || '') === 'cumplida'; });
    inicioAll('.obj-misiones-papel').forEach(function (el) {
      el.classList.toggle('misiones-completadas', todas);
      var sello = el.querySelector('.mision-sello');
      if (todas && !sello) {
        sello = document.createElement('div');
        sello.className = 'mision-sello';
        sello.setAttribute('aria-label', 'Completado');
        sello.innerHTML = '<span class="mision-sello-txt">COMPLETADO</span>';
        el.appendChild(sello);
      } else if (!todas && sello) {
        sello.remove();
      }
    });
    updateMisionesPapelMeta(hoy);
  }
  function renderMisionesStripIn(scopeSel, items) {
    var root = document.querySelector(scopeSel);
    if (!root) return;
    renderMisionesStripEl(root.querySelector("[data-misiones-strip]"), items);
  }
  function renderMisionesStrip(items) {
    cacheMisionesStripItems = items || [];
    renderMisionesStripIn(".inicio-mobile.inicio-mobile-feed", cacheMisionesStripItems);
    renderMisionesStripIn(".inicio-desktop", cacheMisionesStripItems);
    actualizarMisionesCompletadas(items);
    renderInicioMpDuo(items, undefined);
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
      accBtn = '<button type="button" class="mis-check-accion" data-mision-accion="' + esc(m.id || '') + '">' +
        esc(m.accion_label || 'Ir') + '</button>';
    }
    var tit = m.titulo
      ? '<span class="mis-check-tit">' + esc(m.titulo) + '</span>'
      : '';
    var desc = (m.texto || m.hecho)
      ? '<span class="mis-check-desc">' + esc(m.texto || m.hecho) + '</span>'
      : '';
    var chkClass = 'mis-chk';
    var chkInner = '';
    if (est === 'cumplida') {
      chkClass += ' is-done';
      chkInner = '<svg class="mis-chk-ico" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8.5l3.5 3.5 7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    } else if (est === 'bloqueada') {
      chkClass += ' is-locked';
    }
    return '<div class="mis-check-row mis-check-' + est + (opts.primerosPasos ? ' mis-check-pp' : '') + '">' +
      '<span class="' + chkClass + '" aria-label="' + esc(estadoMisionLabel(est)) + '">' + chkInner + '</span>' +
      '<div class="mis-check-copy">' + tit + desc + '</div>' +
      accBtn + '</div>';
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
      return '<span class="mision-bolita cumplida" aria-label="Hecha"><span class="mision-check">\u2713</span></span>';
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

  function corazonAguaPairs() {
    return inicioAll('[data-corazon-fill]').map(function (fillEl) {
      var svg = fillEl.closest('svg');
      var surfaceEl = svg ? svg.querySelector('[data-corazon-surface]') : null;
      return { fillEl: fillEl, surfaceEl: surfaceEl };
    });
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

  function aplicarCorazonAguaTodos(fillY, fillH, phase) {
    corazonAguaPairs().forEach(function (pair) {
      aplicarCorazonAgua(pair.fillEl, pair.surfaceEl, fillY, fillH, phase);
    });
  }

  function animarCorazonAgua() {
    if (corazonOlaTimer) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    corazonOlaTimer = setInterval(function () {
      if (corazonOlaFillH <= 0.5) return;
      corazonOlaPhase += 0.24;
      aplicarCorazonAguaTodos(corazonOlaFillY, corazonOlaFillH, corazonOlaPhase);
    }, 130);
  }

  function corazonVidaSvgs() {
    var svgs = inicioAll('.top-vida .corazon-svg');
    if (svgs.length) return svgs;
    var one = $('.corazon-svg');
    return one ? [one] : [];
  }

  function corazonVidaSvg() {
    var svgs = corazonVidaSvgs();
    return svgs[0] || null;
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

  function pasarRatoBtns() {
    return $$('[data-pasar-rato]');
  }
  function setPasarRatoBusy(busy, busyLabel) {
    pasarRatoBtns().forEach(function (btn) {
      btn.disabled = !!busy;
      if (busy) {
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('is-busy');
        if (busyLabel) {
          const txt = btn.querySelector('.pasar-rato-txt');
          if (txt) txt.textContent = busyLabel;
        }
      } else {
        btn.removeAttribute('aria-busy');
        btn.classList.remove('is-busy');
      }
    });
  }
  function relojAbsDesdeEstado(estado) {
    const e = estado || cacheEstado;
    const reloj = (e && e.reloj) || {};
    const d = Number(reloj.dia_pueblo);
    const h = Number(reloj.hora_actual);
    if (!Number.isFinite(d) || !Number.isFinite(h)) return null;
    return d * 24 + h;
  }
  function relojAvanceRespuestaOk(r) {
    if (!r || typeof r !== 'object') return false;
    if (r.ok === true) return true;
    const nested = r.reloj;
    return !!(nested && typeof nested === 'object' && nested.ok === true);
  }
  function relojAbsAvanzo(antesAbs, despuesAbs) {
    return antesAbs !== null && despuesAbs !== null && despuesAbs > antesAbs;
  }
  function pintarModoReloj(esNoche) {
    aplicarNocheVisual(esNoche);
    $$('[data-es-noche]').forEach(function (el) {
      el.hidden = false;
      el.classList.toggle('is-dia', !esNoche);
      el.classList.toggle('is-noche', esNoche);
    });
    pasarRatoBtns().forEach(function (btn) {
      btn.classList.toggle('pasar-rato--noche', esNoche);
      btn.title = esNoche ? 'Avanza hasta las 08:00 de la mañana' : 'Avanza el tiempo exactamente 1 hora';
      if (!btn.classList.contains('is-busy')) {
        const txt = btn.querySelector('.pasar-rato-txt');
        if (txt) txt.textContent = esNoche ? 'Pasar la noche' : 'Pasar el rato';
      }
    });
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
      metaMobEl.innerHTML = '<span class="top-meta-stack"><span class="top-meta-prim">' + mobDia + ' \u00b7 ' + mobFecha + '</span><span class="top-meta-hora">' + mobHora + '</span></span>';
    });
    const vida = estado.vida_pueblo || null;
    const pct = vida && typeof vida.corazon_pct === 'number' ? vida.corazon_pct : 0;
    const critico = !!(vida && vida.critico);
    if (corazonAguaPairs().length) {
      var fillH = 52 * (pct / 100);
      var fillY = 52 - fillH;
      corazonOlaFillY = fillY;
      corazonOlaFillH = fillH;
      aplicarCorazonAguaTodos(fillY, fillH, corazonOlaPhase);
      animarCorazonAgua();
    }
    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';
    inicioAll('[data-vida-num]').forEach(function (el) { el.textContent = String(Math.round(pct)); });
    corazonVidaSvgs().forEach(function (corazonSvg) {
      corazonSvg.classList.toggle('corazon-vida--critico', critico);
      if (vida && vida.latido_anim) {
        triggerCorazonLatido(corazonSvg);
      } else if (vidaCorazonReady && vidaCorazonPctPrev !== null && pct !== vidaCorazonPctPrev) {
        triggerCorazonVidaReaction(corazonSvg, pct > vidaCorazonPctPrev ? 'sube' : 'baja');
      } else if (!vidaCorazonReady) {
        corazonSvg.classList.add('corazon-vida--reposo');
      }
    });
    if (corazonVidaSvgs().length) {
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
    inicioAll('.inicio-mobile-tiles .obj-buzon-txt').forEach(function (txtEl) {
      if (nPend > 0) {
        txtEl.setAttribute('data-sub', String(nPend) + ' sin leer');
      } else {
        txtEl.removeAttribute('data-sub');
      }
    });
    const cartas = (buzon || []).filter(function (m) {
      return (m.estado || '') === 'pendiente' || (m.estado || '') === 'en_espera';
    });
    const imp = cartas.some(function (m) { return m.clasificacion === 'importante'; });
    if (imp) $('.play-root').setAttribute('data-importante', '1');
    else $('.play-root').removeAttribute('data-importante');
  }

  /* ââ Auto-sync: sincronización periódica del reloj ââ */
  var _syncClockTimer = null;
  var _syncClockBusy = false;
  var _partidaSwitchBusy = false;
  var SYNC_CLOCK_INTERVAL_MS = 45000;

  function renderClockOnly(relojVista, relojRaw) {
    if (!relojVista) return;
    var rv = relojVista;
    var rl = relojRaw || {};
    var diaNum = rl.dia_pueblo;
    var h = rv.hora !== undefined ? rv.hora : rl.hora_actual;
    var fechaCorta = rv.fecha_corta || '';
    var diaLblHud = (diaNum !== undefined && diaNum !== null) ? ('D\u00eda ' + diaNum) : '';
    inicioAll('[data-dia-num]').forEach(function (el) { el.textContent = diaLblHud || '\u2014'; });
    var ht = h === undefined ? '\u2014:\u2014' : (String(h).padStart(2, '0') + ':00');
    $$('[data-dow]').forEach(function (el) {
      el.textContent = rv.dia_semana_ui || (diaNum !== undefined ? ('D\u00eda ' + diaNum) : '-');
    });
    $$('[data-fecha]').forEach(function (el) { el.textContent = fechaCorta; });
    $$('[data-hora]').forEach(function (el) { el.textContent = ht || '-'; });
    pintarModoReloj(esHoraNoche(typeof h === 'number' ? h : null));
    inicioAll('[data-dia-meta]').forEach(function (el) { el.textContent = fechaCorta || '\u2014'; });
    inicioAll('[data-top-meta-mobile]').forEach(function (el) {
      var mobDia = diaLblHud || 'D\u00eda \u2014';
      var mobFecha = fechaCorta || '\u2014';
      var min = rv.minuto !== undefined ? rv.minuto : rl.minuto_actual;
      var mobHora = h === undefined ? '\u2014:\u2014' : (String(h).padStart(2, '0') + ':' + String(min === undefined || min === null ? 0 : min).padStart(2, '0'));
      el.innerHTML = '<span class="top-meta-stack"><span class="top-meta-prim">' + mobDia + ' \u00b7 ' + mobFecha + '</span><span class="top-meta-hora">' + mobHora + '</span></span>';
    });
  }

  async function syncClock() {
    if (_syncClockBusy) return;
    if (!partidaId) return;
    _syncClockBusy = true;
    try {
      var r = await api('reloj.sincronizar', {});
      if (!r.ok) return;
      if (r.hay_cambios_visibles) {
        await refresh();
      } else {
        if (cacheEstado) {
          cacheEstado.reloj = r.reloj || cacheEstado.reloj;
          cacheEstado.reloj_vista = r.reloj_vista || cacheEstado.reloj_vista;
          cacheEstado.reloj_texto = r.reloj_texto || cacheEstado.reloj_texto;
        }
        renderClockOnly(r.reloj_vista, r.reloj);
      }
      if (isDebugOn()) {
        var tm = $('[data-taller-msg]');
        if (tm) tm.textContent = r.reloj_texto || '';
      }
    } finally {
      _syncClockBusy = false;
    }
  }

  function startSyncClock() {
    stopSyncClock();
    _syncClockTimer = setInterval(syncClock, SYNC_CLOCK_INTERVAL_MS);
  }
  function stopSyncClock() {
    if (_syncClockTimer) { clearInterval(_syncClockTimer); _syncClockTimer = null; }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') syncClock();
  });

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
    
    // Save previous screen positions and zone IDs for movement animation
    var prevPositions = {};
    $$('.mapa-zona-hit .habs .hab[data-residente]').forEach(function(el) {
      var rid = el.getAttribute('data-residente');
      var zid = el.closest('.mapa-zona-hit')?.getAttribute('data-zona');
      if (rid && zid) {
        var rect = el.getBoundingClientRect();
        prevPositions[rid] = { zone: zid, x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
      }
    });
    
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
        var prev = prevPositions[p.id] || null;
        var prevZone = prev ? prev.zone : null;
        placeHabEnZona(box, p, i, enZona.length, prevZone, zid);
      });
    });
    
    // Animate NPCs that changed zones: slide from old position to new
    requestAnimationFrame(function() {
      $$('.mapa-zona-hit .habs .hab[data-residente]').forEach(function(el) {
        var rid = el.getAttribute('data-residente');
        var prev = prevPositions[rid];
        if (!prev) return;
        var newZid = el.closest('.mapa-zona-hit')?.getAttribute('data-zona');
        if (!newZid || prev.zone === newZid) return;
        var rect = el.getBoundingClientRect();
        var dx = prev.x - (rect.left + rect.width / 2);
        var dy = prev.y - (rect.top + rect.height / 2);
        if (Math.abs(dx) < 2 && Math.abs(dy) < 2) return;
        el.style.transform = 'translate(' + dx.toFixed(1) + 'px, ' + dy.toFixed(1) + 'px)';
        el.style.transition = 'none';
        el.offsetHeight;
        el.classList.add('hab-moviendo');
        el.style.transition = 'transform 2.2s cubic-bezier(0.22, 1, 0.36, 1)';
        el.style.transform = '';
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
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' Â· ');
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
    if (window.AHTScreenManager && window.AHTScreenManager.injectDoodles) {
      window.AHTScreenManager.injectDoodles('edificios');
    }
    var lugId = destId || id;
    var gente = (cx.personas || []).filter(function (p) {
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
    var subEl = $('[data-q-subtitle]');
    if (subEl) subEl.textContent = LUGAR_SUBTITULO[lugId] || '';
    var necEl = $('[data-q-necesidades]');
    if (necEl) {
      var metaNec = LUGAR_META[lugId] || {};
      var necList = (metaNec.necesidades || []);
      necEl.innerHTML = necList.map(function (n) {
        var k = n.toLowerCase();
        var icon = NEC_ICON_PATHS[k] || '';
        if (icon) return '<span class="qed-nec-pill"><img src="' + esc(icon) + '" alt="" class="qed-nec-ico"/>' + n + '</span>';
        return '<span class="qed-nec-pill">' + n + '</span>';
      }).join('');
    }
    var offlineEl = $('[data-q-offline]');
    var vacioEl = $('[data-q-sum]');
    if (offlineEl) offlineEl.hidden = gente.length > 0;
    if (vacioEl && !gente.length) vacioEl.hidden = true;
    quienTemaActivo = null;
    quienAvatarFlip = false;
    pintarConsultaEdArt(lugId);
    pintarQuienResidentes(gente, null, gente.length ? '' : copyVacio(id));
    var countEl = $('[data-q-count]');
    if (countEl) countEl.textContent = gente.length ? gente.length + ' persona' + (gente.length > 1 ? 's' : '') : '';
    pintarQuienTema(gente, null);
    var descEl = $('[data-q-desc]');
    if (descEl) descEl.textContent = orgLugarDesc(lugId);
    var necLugEl = $('[data-q-nec-lugares]');
    if (necLugEl) {
      var metaNecL = LUGAR_META[lugId] || {};
      var necNames = (metaNecL.necesidades || []);
      necLugEl.innerHTML = necNames.map(function (n) {
        var k = n.toLowerCase();
        var icon = NEC_ICON_PATHS[k] || '';
        var frase = NEC_FRASES_LUGAR[k] || '';
        return '<div class="qed-nec-lugar-row">'
          + (icon ? '<img src="' + esc(icon) + '" alt="" class="qed-nec-lugar-ico"/>' : '')
          + '<div class="qed-nec-lugar-text"><span class="qed-nec-lugar-name">' + esc(n) + '</span>'
          + (frase ? '<span class="qed-nec-lugar-frase">' + esc(frase) + '</span>' : '')
          + '</div></div>';
      }).join('');
    }
    var curEl = $('[data-q-curiosities]');
    if (curEl) {
      var curs = LUGAR_CURIOSIDADES[lugId] || [];
      curEl.innerHTML = curs.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('');
    }
    var footerEl = $('[data-q-footer-quote]');
    if (footerEl) footerEl.textContent = LUGAR_FRASES[lugId] || '';
    const box = $('[data-q-btns]');
    box.innerHTML = '';
    (cx.destinos_operativos || []).forEach(function (d) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'consulta-ed-cta';
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
  let necgFiltroInicial = '';

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
    var inDesktopCelestine = !!(box.closest && box.closest('.inicio-desktop'));
    box.innerHTML = pick.map(function (id) {
      var r = res[id];
      var img = tokenDe(id);
      var nom = (r.identidad_publica && r.identidad_publica.nombre) || id;
      var ini = nom.charAt(0) || '?';
      if (!img) {
        return '<span class="obj-vecinos-preview-cara obj-vecinos-preview-ini">' + esc(ini) + '</span>';
      }
      if (inDesktopCelestine) {
        return '<span class="obj-vecinos-preview-cara-wrap" aria-hidden="true"><img class="obj-vecinos-preview-cara" src="' + esc(img) + '" alt=""/></span>';
      }
      return '<img class="obj-vecinos-preview-cara" src="' + esc(img) + '" alt=""/>';
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
      const vecTint = mensajitoTintDeRemitente(id);
      b.className = 'vecino-celda vecino-celda--decor-' + (idx % 6) +
        ' vecino-tinta-' + vecTint +
        (vecTint === 1 || vecTint === 2 ? ' vecino-borde-rallado' : '');
      b.setAttribute('data-residente', id);
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
    if (rel.etiqueta_vinculo === 'crisis') return '\uD83D\uDCA5 ';
    if (rel.etiqueta_vinculo === 'pareja') return '\uD83D\uDC95 ';
    if (rel.etiqueta_vinculo === 'ex_pareja') return '\uD83D\uDC94 ';
    var s = rel.etiqueta_social || '';
    var socialEmoji = '';
    if (s === 'cae_mal') socialEmoji = '\uD83D\uDE21 ';
    else if (s === 'buena_amistad' || s === 'muy_buena_amistad') socialEmoji = '\uD83D\uDC9A ';
    else if (s === 'amigo') socialEmoji = '\uD83D\uDE0A ';
    else if (s === 'conocido') socialEmoji = '\uD83D\uDC4B ';
    if (socialEmoji) return socialEmoji;
    if (rel.romance_visible && rel.etiqueta_romance) return '\uD83D\uDC98 ';
    return '';
  }

  function htmlRelRow(rel, opts) {
    opts = opts || {};
    const cara = tokenDe(rel.id);
    const ini = (rel.nombre || '?').charAt(0);
    const pct = barRelPct(rel);
    const lbl = etiquetaRelText(rel);
    const crisis = rel.etiqueta_vinculo === 'crisis';
    const compact = !!opts.compact;
    return (
      '<div class="ficha-rel-row' + (crisis ? ' is-crisis' : '') + (compact ? ' is-compact' : '') + '">' +
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

  function pintarRelacionesEn(box, rels, limit, opts) {
    if (!box) return;
    box.innerHTML = '';
    if (!rels.length) {
      box.innerHTML = '<p class="ficha-vacio">De momento, solo le conoces a ti. O eso dice el pueblo.</p>';
      return;
    }
    rels.slice(0, limit).forEach(function (rel) {
      box.insertAdjacentHTML('beforeend', htmlRelRow(rel, opts));
    });
  }


  function htmlFichaPlanItem(enc, vecinoId, estado) {
    const lugar = nombreLugarTitulo(enc.lugar_nombre || enc.lugar, enc.lugar);
    const reloj = (estado && estado.reloj) || {};
    const enCurso = planEsEnCurso(enc, estado);
    const hora = String(horaEnc(enc)).padStart(2, '0') + ':00';
    let meta;
    if (enCurso) meta = 'En curso \u00b7 ' + hora;
    else if (Number(enc.dia) === Number(reloj.dia_pueblo)) meta = 'Hoy \u00b7 ' + hora;
    else meta = 'D\u00eda ' + (enc.dia || '?') + ' \u00b7 ' + hora;
    const ids = enc.participantes || [];
    const otros = ids.filter(function (pid) { return String(pid) !== String(vecinoId); });
    let compHtml = '';
    if (otros.length) {
      const compId = otros[0];
      const img = tokenDe(compId);
      const nom = nombreDe(compId);
      compHtml = '<div class="ficha-plan-comp" title="' + esc(nom) + '">' +
        (img ? '<img class="ficha-plan-comp-cara" src="' + esc(img) + '" alt=""/>' :
          '<span class="ficha-plan-comp-ini">' + esc((nom.charAt(0) || '?')) + '</span>') +
        '</div>';
    }
    return '<article class="ficha-plan-item' + (compHtml ? ' has-comp' : '') + '">' +
      compHtml +
      '<div class="ficha-plan-main">' +
      '<span class="ficha-plan-lugar">' + esc(lugar) + '</span>' +
      '<span class="ficha-plan-meta">' + esc(meta) + '</span>' +
      (otros.length > 1 ? '<span class="ficha-plan-extra">+' + (otros.length - 1) + '</span>' : '') +
      '</div></article>';
  }

  function cerrarFichaRelOverlay() {
    setCapa(relVolverCapa || 'ficha');
  }

  function abrirFichaRelOverlay(nombre) {
    const list = $('[data-ficha-rel-list]');
    const tit = $('[data-ficha-rel-modal-tit]');
    if (!list) return;
    const root = $('.play-root');
    relVolverCapa = (root && root.getAttribute('data-capa')) || 'ficha';
    if (tit) tit.textContent = 'Relaciones de ' + (nombre || 'vecino');
    pintarRelacionesEn(list, fichaRelCache, fichaRelCache.length);
    setCapa('ficha_relaciones');
  }

  var VEC_REL_FILTROS = [
    { id: '', txt: 'Todas', icono: '' },
    { id: 'romance', txt: 'Parejas', icono: '\uD83D\uDC8C' },
    { id: 'bien', txt: 'Amistad', icono: '\uD83E\uDD1D' },
    { id: 'conocidos', txt: 'Conocidos', icono: '\uD83D\uDC4F' },
    { id: 'mal', txt: 'Malas', icono: '\uD83D\uDC94' }
  ];
  var vecRelCache = [];
  var vecRelFiltro = '';
  var vecRelPersona = '';
  var vecRelCargado = false;

  function aplicarVecTabUI() {
    const isRel = vecTabActiva === 'relaciones';
    const isCuid = vecTabActiva === 'cuidados';
    $$('[data-vec-tab]').forEach(function (btn) {
      const t = btn.getAttribute('data-vec-tab');
      const on = t === vecTabActiva;
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    const cuentaWrap = $('[data-vec-cuenta-wrap]');
    if (cuentaWrap) cuentaWrap.hidden = isRel || isCuid;
    $$('[data-vec-panel]').forEach(function (p) {
      p.hidden = p.getAttribute('data-vec-panel') !== vecTabActiva;
    });
    const capa = document.querySelector('[data-aht-screen="vecinos"]');
    if (capa) {
      capa.classList.toggle('is-relaciones', isRel);
      capa.classList.toggle('is-cuidados', isCuid);
    }
  }

  function setVecTab(tab) {
    vecTabActiva = ['vecinos', 'relaciones', 'cuidados'].indexOf(tab) >= 0 ? tab : 'vecinos';
    aplicarVecTabUI();
    if (vecTabActiva === 'relaciones') cargarVecRelaciones();
    if (vecTabActiva === 'cuidados') cargarVecCuidados();
    const root = $('.play-root');
    syncBottomNav((root && root.getAttribute('data-capa')) || '');
  }

  async function cargarVecRelaciones() {
    const list = $('[data-vec-rel-list]');
    if (list && !vecRelCargado) {
      list.innerHTML = '<p class="lista-vacia vec-rel-vacio">Mirando el cotilleo del pueblo\u2026</p>';
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

  /* ── Cuidados tab ─────────────────────────────────────────── */
  var vecCuidCache = null;
  var vecCuidCargado = false;
  var NEC_ORDEN_CUIDADOS = ['social', 'diversion', 'actividad', 'calma'];

  async function cargarVecCuidados() {
    var body = $('[data-necg-body]');
    var filtersWrap = $('[data-necg-filters]');
    var resumenEl = $('[data-vec-cuid-resumen]');
    if (!body) return;

    if (!vecCuidCargado) {
      if (body) body.innerHTML = '<p class="necg-vacio mini">Cargando...</p>';
      if (resumenEl) resumenEl.innerHTML = '';
      try {
        var r = await api('partida.necesidades_global');
        var data = r.ok && r.necesidades ? r.necesidades : null;
        if (!data || !data.residentes || !data.residentes.length) {
          if (body) body.innerHTML = '<p class="necg-vacio">No hay datos de necesidades a&uacute;n.</p>';
          return;
        }
        vecCuidCache = data.residentes;
        vecCuidCargado = true;
      } catch (e) {
        if (body) body.innerHTML = '<p class="necg-vacio">Error al cargar cuidados.</p>';
        return;
      }
    }

    var allResidents = vecCuidCache;
    var nom = NEC_NOMBRES || { social: 'Socializar', diversion: 'Diversi\u00f3n', actividad: 'Actividad', calma: 'Desconectar' };
    var orden = NEC_ORDEN_CUIDADOS;

    var selectedResId = null;
    var initialFilter = necgFiltroInicial || 'todos';
    necgFiltroInicial = '';

    function renderResumen(residents) {
      if (!resumenEl) return;
      var totales = { social: 0, diversion: 0, actividad: 0, calma: 0 };
      var count = residents.length;
      residents.forEach(function (res) {
        orden.forEach(function (nec) {
          var n = res.necesidades[nec];
          if (n) totales[nec] += n.valor;
        });
      });
      var rhtml = '<h3 class="vec-cuid-resumen-tit">Estado general del pueblo</h3>';
      rhtml += '<div class="vec-cuid-bars">';
      orden.forEach(function (nec) {
        var avg = count ? Math.round(totales[nec] / count) : 0;
        rhtml += '<div class="vec-cuid-bar-row">';
        rhtml += '<span class="vec-cuid-bar-ico" aria-hidden="true">' + necIconHtml(nec, 18) + '</span>';
        rhtml += '<span class="vec-cuid-bar-nom">' + (nom[nec] || nec) + '</span>';
        rhtml += '<div class="vec-cuid-bar vec-cuid-bar--' + getBand(avg) + '"><div class="vec-cuid-bar-fill vec-cuid-bar-fill--' + getBand(avg) + '" style="width:' + avg + '%"></div></div>';
        rhtml += '<span class="vec-cuid-bar-val">' + avg + '</span>';
        rhtml += '</div>';
      });
      rhtml += '</div>';
      resumenEl.innerHTML = rhtml;
    }

    function getBand(val) {
      if (val >= 75) return 'bien';
      if (val >= 50) return 'le_vendria_bien';
      if (val >= 25) return 'lo_necesita';
      return 'en_rojo';
    }

    function bandLabel(banda) {
      if (banda === 'en_rojo') return 'Muy bajo';
      if (banda === 'lo_necesita') return 'Algo bajo';
      if (banda === 'le_vendria_bien') return 'Normal';
      return 'Bien';
    }

    function getWorstNec(res) {
      var worstId = null;
      var worstVal = 101;
      var worstBand = 'bien';
      orden.forEach(function (nec) {
        var n = res.necesidades[nec];
        if (n && n.banda !== 'bien' && n.valor < worstVal) {
          worstVal = n.valor;
          worstBand = n.banda;
          worstId = nec;
        }
      });
      return worstId ? { id: worstId, nombre: nom[worstId], icono: necIconHtml(worstId, 14), valor: worstVal, banda: worstBand } : null;
    }

    function renderFilters() {
      if (!filtersWrap) return;
      var fhtml = '';
      orden.forEach(function (nec) {
        fhtml += '<button type="button" class="necg-filt' + (initialFilter === nec ? ' necg-filt--on' : '') + '" data-nec-filter="' + nec + '">' + necIconHtml(nec, 30) + '</button>';
      });
      fhtml += '<button type="button" class="necg-filt necg-filt--todos' + (initialFilter === 'todos' ? ' necg-filt--on' : '') + '" data-nec-filter="todos"><span class="necg-filt-all">&#x2716;</span></button>';
      filtersWrap.innerHTML = fhtml;
    }

    function renderCarousel(filteredResidents) {
      var html = '';

      html += '<div class="necg-alertas">';
      html += '<h4 class="necg-alertas-title">Vecinos del pueblo</h4>';
      html += '<div class="necg-carousel">';
      filteredResidents.forEach(function (res) {
        var worst = getWorstNec(res);
        var needsBadge = worst !== null;
        var isActive = res.id === selectedResId;
        var cardCls = 'necg-caro-card';
        if (needsBadge && worst.valor < 25) cardCls += ' necg-caro-card--worst';
        if (isActive || (!selectedResId && needsBadge && worst.valor < 25)) cardCls += ' is-active';
        else if (isActive) cardCls += ' is-active';
        html += '<div class="' + cardCls + '" data-necg-caro="' + esc(res.id) + '">';
        var img = res.retrato_url || tokenDe(res.id);
        var ini = (res.nombre || '?').charAt(0);
        html += '<span class="necg-caro-avatar">';
        if (img) html += '<img src="' + esc(img) + '" alt=""/>';
        else html += '<span class="necg-caro-avatar--fallback">' + esc(ini) + '</span>';
        html += '</span>';
        html += '<span class="necg-caro-nom">' + esc(res.nombre) + '</span>';
        if (needsBadge) {
          html += '<span class="necg-caro-badge necg-caro-badge--' + worst.banda + '">' + worst.icono + ' ' + bandLabel(worst.banda) + '</span>';
        }
        html += '</div>';
      });
      html += '</div></div>';

      html += '<div class="necg-sep"></div>';

      var selId = selectedResId;
      if (!selId) {
        for (var i = 0; i < filteredResidents.length; i++) {
          if (getWorstNec(filteredResidents[i])) { selId = filteredResidents[i].id; break; }
        }
        if (!selId && filteredResidents.length) selId = filteredResidents[0].id;
      }
      var selRes = null;
      filteredResidents.forEach(function (res) { if (res.id === selId) selRes = res; });
      if (!selRes && filteredResidents.length) selRes = filteredResidents[0];

      if (selRes) {
        html += '<div class="necg-detail">';
        html += '<h4 class="necg-detail-title">Necesidades de ' + esc(selRes.nombre) + '</h4>';
        html += '<div class="necg-detail-grid">';
        var hasAny = false;
        orden.forEach(function (nec) {
          var n = selRes.necesidades[nec];
          if (!n) return;
          hasAny = true;
          var pct = Math.max(0, Math.min(100, n.valor));
          html += '<div class="necg-need-group necg-need-group--' + nec + '">';
          html += '<div class="necg-need-row necg-need-row--' + nec + '">';
          html += '<span class="necg-need-icon">' + necIconHtml(nec, 22) + '</span>';
          html += '<span class="necg-need-name">' + nom[nec] + '</span>';
          html += '<span class="necg-need-val">' + Math.round(pct) + '%</span>';
          html += '</div>';
          html += '<div class="necg-bar necg-bar--' + n.banda + '"><div class="necg-bar-fill necg-bar-fill--' + n.banda + '" style="width:' + pct + '%"></div></div>';
          if (n.copy) {
            html += '<p class="necg-need-copy">' + esc(n.copy) + '</p>';
          }
          html += '</div>';
        });
        if (!hasAny) {
          html += '<p class="necg-vacio">\u2728 Todas sus necesidades est\u00e1n cubiertas.</p>';
        }
        html += '</div>';
        html += '</div>';
      }

      body.innerHTML = html;

      body.onclick = function (e) {
        var caroCard = e.target.closest('[data-necg-caro]');
        if (caroCard) {
          var rid = caroCard.getAttribute('data-necg-caro');
          if (rid) {
            selectedResId = rid;
            renderCarousel(filteredResidents);
          }
          return;
        }
      };
    }

    function applyFilter(filter) {
      var filtered;
      if (filter === 'todos') {
        filtered = allResidents;
      } else if (filter === 'necesitan') {
        filtered = allResidents.filter(function (res) {
          return orden.some(function (nec) {
            var n = res.necesidades[nec];
            return n && (n.banda === 'lo_necesita' || n.banda === 'en_rojo');
          });
        });
      } else {
        filtered = allResidents.filter(function (res) {
          var n = res.necesidades[filter];
          return n && n.banda !== 'bien';
        });
      }
      renderResumen(allResidents);
      renderCarousel(filtered);
    }

    renderFilters();
    applyFilter(initialFilter);

    if (filtersWrap) {
      filtersWrap.onclick = function (e) {
        var btn = e.target.closest('[data-nec-filter]');
        if (!btn) return;
        var filter = btn.getAttribute('data-nec-filter');
        selectedResId = null;
        filtersWrap.querySelectorAll('.necg-filt').forEach(function (b) { b.classList.remove('necg-filt--on'); });
        btn.classList.add('necg-filt--on');
        applyFilter(filter);
      };
    }
  }

  function renderVecCuidados() {
    cargarVecCuidados();
  }

  function vecRelPillTexto(dir) {
    dir = dir || {};
    if (dir.etiqueta_vinculo === 'pareja') return 'PAREJA';
    if (dir.etiqueta_vinculo === 'crisis') return 'EN CRISIS';
    if (dir.etiqueta_vinculo === 'ex_pareja') return 'EX PAREJA';
    if (dir.romance_visible && dir.etiqueta_romance) return String(dir.etiqueta_romance).toUpperCase();
    if ((dir.conocidos || dir.social_negativo) && dir.etiqueta_social_ui && dir.etiqueta_social !== 'desconocido') {
      return String(dir.etiqueta_social_ui).toUpperCase();
    }
    if (dir.conocidos) return 'CONOCIDO';
    return '';
  }

  function vecRelHasDir(dir) {
    return !!vecRelPillTexto(dir);
  }

  function vecRelToneFromDir(dir) {
    const cls = vecRelPillClass(dir);
    if (cls === 'vec-rel-pill--red') return 'red';
    if (cls === 'vec-rel-pill--pink') return 'pink';
    if (cls === 'vec-rel-pill--mustard') return 'mustard';
    return 'green';
  }

  function vecRelCardTone(f) {
    if (f.conflicto || f.mal) return 'red';
    if (f.romance) return 'pink';
    if (f.bien) return 'green';
    if (f.conocidos) return 'mustard';
    return 'neutral';
  }

  function vecRelFlechaHtml(dir, mutual, reverse) {
    const txt = vecRelPillTexto(dir) || 'CONOCIDO';
    const tone = vecRelToneFromDir(dir);
    if (mutual) {
      return '<div class="vec-rel-flecha-row vec-rel-flecha-row--mutual">' +
        '<span class="vec-rel-flecha-body vec-rel-flecha-body--' + tone + '">' + esc(txt) + '</span>' +
        '</div>';
    }
    var revCls = reverse ? ' vec-rel-flecha-row--rev' : '';
    return '<div class="vec-rel-flecha-row' + revCls + '">' +
      '<span class="vec-rel-flecha-body vec-rel-flecha-body--' + tone + '">' + esc(txt) + '</span>' +
      '</div>';
  }

  function vecRelPuenteHtml(ab, ba) {
    const tAb = vecRelPillTexto(ab);
    const tBa = vecRelPillTexto(ba);
    const hasAb = vecRelHasDir(ab);
    const hasBa = vecRelHasDir(ba);
    if (!hasAb && !hasBa) {
      return '<p class="vec-rel-puente-vacio">Se conocen de vista.</p>';
    }
    const same = hasAb && hasBa && tAb === tBa && (ab.social_bar_pct || 0) === (ba.social_bar_pct || 0);
    const parts = [];
    if (same) {
      parts.push(vecRelFlechaHtml(ab, true, false));
      parts.push('<p class="vec-rel-puente-nota">MISMO V\u00CDNCULO</p>');
    } else {
      if (hasAb) parts.push(vecRelFlechaHtml(ab, false, false));
      if (hasBa) parts.push(vecRelFlechaHtml(ba, false, true));
    }
    return parts.join('');
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

  function htmlVecRelCard(row) {
    const a = row.persona_a || {};
    const b = row.persona_b || {};
    const ab = row.a_hacia_b || {};
    const ba = row.b_hacia_a || {};
    const tAb = vecRelPillTexto(ab);
    const tBa = vecRelPillTexto(ba);
    const f = vecRelFlags(row);
    const tone = vecRelCardTone(f);
    const hasAb = vecRelHasDir(ab);
    const hasBa = vecRelHasDir(ba);
    const asim = hasAb && hasBa && (tAb !== tBa || (ab.social_bar_pct || 0) !== (ba.social_bar_pct || 0));
    const cls = 'vec-rel-card vec-rel-card--tone-' + tone +
      (f.romance ? ' is-amor' : '') +
      (f.mal ? ' is-mal' : '') +
      (f.conflicto ? ' is-conflicto' : '');
    const badge = f.conflicto ? '<span class="vec-rel-badge" aria-label="Conflicto activo">CONFLICTO</span>' : '';
    const puente = vecRelPuenteHtml(ab, ba);
    return (
      '<article class="' + cls + '">' +
      '<span class="vec-rel-card-accent" aria-hidden="true"></span>' +
      badge +
      '<div class="vec-rel-card-grid">' +
      '<button type="button" class="vec-rel-pers" data-vec-rel-open="' + esc(a.id || '') + '">' +
      '<span class="vec-rel-cara">' + caraVecRel(a.id, a.nombre) + '</span>' +
      '<span class="vec-rel-nom">' + esc(a.nombre || a.id || '?') + '</span></button>' +
      '<div class="vec-rel-puente">' + puente + '</div>' +
      '<button type="button" class="vec-rel-pers" data-vec-rel-open="' + esc(b.id || '') + '">' +
      '<span class="vec-rel-cara">' + caraVecRel(b.id, b.nombre) + '</span>' +
      '<span class="vec-rel-nom">' + esc(b.nombre || b.id || '?') + '</span></button>' +
      '</div>' +
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
        (quien ? esc(quien) + ' no tiene nada que contar por ahora.' : 'Aqu\u00ED no hay nada de nada todav\u00EDa.') + '</p>';
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

  function diasEnElPueblo(id) {
    var diaLlegada = diaLlegadaVecino(id);
    if (!diaLlegada) return 0;
    var hoy = new Date();
    var llegada = new Date(hoy.getFullYear(), 0, diaLlegada);
    if (llegada > hoy) llegada.setFullYear(hoy.getFullYear() - 1);
    var diff = Math.floor((hoy - llegada) / (1000 * 60 * 60 * 24));
    return Math.max(1, diff);
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
    const map = {
      neutro: '\uD83D\uDE10',
      alegre: '\uD83D\uDE0A',
      triste: '\uD83D\uDE22',
      enfadado: '\uD83D\uDE24'
    };
    return map[canonEmoId(emo)] || map.neutro;
  }

  var ANIMO_ICON_PATHS = {
    neutro: 'assets/img/estado_neutral.png',
    alegre: 'assets/img/estado_feliz.png',
    triste: 'assets/img/estado_triste.png',
    enfadado: 'assets/img/estado_enfadado.png'
  };

  function pintarAnimoFicha(vista) {
    const emo = canonEmoId(vista.estado_animo);
    const genero = vista && vista.genero;
    const txtEl = $('[data-ficha-animo-text]');
    const imgEl = $('[data-ficha-animo-img]');
    const pillEl = $('[data-ficha-animo-pill]');
    const ringEl = $('[data-ficha-cara-ring]');
    if (txtEl) txtEl.textContent = textoAnimoFichaPill(emo, genero);
    if (imgEl) {
      var iconPath = ANIMO_ICON_PATHS[emo] || ANIMO_ICON_PATHS.neutro;
      imgEl.src = iconPath;
      imgEl.alt = textoAnimoDisplay(emo);
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
    if (imgEl) {
      imgEl.classList.toggle('is-clickable', showAnimoQ);
      imgEl.onclick = bindAnimoOpen;
    }
    cerrarAnimoOverlay();
  }

  function cerrarAnimoOverlay() {
    if (window.AHTScreenManager) {
      window.AHTScreenManager.close();
    } else {
      setCapa(animoVolverCapa || 'ficha');
    }
  }

  function emoModalIcono(estadoId) {
    const map = {
      alegre: '\uD83D\uDE0A',
      triste: '\uD83D\uDE22',
      enfadado: '\uD83D\uDE24',
      neutro: '\uD83D\uDE10'
    };
    return map[canonEmoId(estadoId)] || map.neutro;
  }

  function animoDecoHtml(emoId) {
    const decos = {
      triste: '<span class="animo-deco animo-deco--drop1" aria-hidden="true">&#x1F4A7;</span>' +
              '<span class="animo-deco animo-deco--drop2" aria-hidden="true">&#x1F4A7;</span>' +
              '<span class="animo-deco animo-deco--drop3" aria-hidden="true">&#x1F4A7;</span>' +
              '<span class="animo-deco animo-deco--cloud" aria-hidden="true">&#x2601;</span>',
      alegre: '<span class="animo-deco animo-deco--star1" aria-hidden="true">&#x2B50;</span>' +
              '<span class="animo-deco animo-deco--star2" aria-hidden="true">&#x2728;</span>' +
              '<span class="animo-deco animo-deco--confetti" aria-hidden="true">&#x1F389;</span>' +
              '<span class="animo-deco animo-deco--spark" aria-hidden="true">&#x1F31F;</span>',
      enfadado: '<span class="animo-deco animo-deco--bolt1" aria-hidden="true">&#x26A1;</span>' +
                '<span class="animo-deco animo-deco--boom" aria-hidden="true">&#x1F4A5;</span>' +
                '<span class="animo-deco animo-deco--bolt2" aria-hidden="true">&#x26A1;</span>' +
                '<span class="animo-deco animo-deco--anger" aria-hidden="true">&#x1F4A2;</span>'
    };
    return decos[emoId] || '';
  }

  function htmlAnimoModal(exp, nom) {
    const estadoTxt = esc(String(exp.texto_estado || ''));
    const pensamiento = esc(exp.pensamiento || '');
    const desde = exp.desde_texto ? esc(exp.desde_texto) : '';
    const emoId = canonEmoId(exp.estado_id || '');
    const img = $('[data-ficha-img]') ? $('[data-ficha-img]').innerHTML : '';
    return '<div class="animo-scene animo-scene--' + esc(emoId) + '">' +
      '<div class="animo-deco-ring">' + animoDecoHtml(emoId) + '</div>' +
      '<div class="animo-card">' +
      '<div class="animo-retrato">' +
      '<div class="animo-avatar">' + img + '</div>' +
      '<h3 class="animo-nombre">' + esc(nom) + '</h3>' +
      '</div>' +
      '<div class="animo-emocion animo-emocion--' + esc(emoId) + '">' +
      '<div class="animo-emoji-hero" aria-hidden="true">' + emoModalIcono(emoId) + '</div>' +
      '<div class="animo-pensamiento animo-pensamiento--' + esc(emoId) + '">' +
      (pensamiento ? '<p>' + pensamiento + '</p>' : '') +
      '</div>' +
      '<div class="animo-meta">' +
      '<span class="animo-estado animo-estado--' + esc(emoId) + '">' +
      '<span class="animo-estado-ico" aria-hidden="true">' + svgAnimoBadge(emoId) + '</span>' +
      estadoTxt + '</span>' +
      (desde ? '<span class="animo-desde">' + desde + '</span>' : '') +
      '</div>' +
      '</div>' +
      '</div>' +
      '</div>';
  }

  function abrirAnimoModal() {
    const exp = fichaAnimoExplicacion;
    const body = $('[data-animo-body]');
    const root = $('.play-root');
    if (!exp || !body) return;
    animoVolverCapa = (root && root.getAttribute('data-capa')) || 'ficha';
    const nom = ($('[data-ficha-nombre]') && $('[data-ficha-nombre]').textContent) || '';
    body.innerHTML = htmlAnimoModal(exp, nom);
    setCapa('ficha_animo');
  }


  let diarioVecinoCache = [];
  let diarioVecinoFiltro = 'todo';
  let diarioVecinoBusca = '';
  let diarioVecinoOrden = 'reciente';
  let diarioHighlightId = null;
  let diarioVolverCapa = 'ficha';
  let animoVolverCapa = 'ficha';
  let relVolverCapa = 'ficha';
  let fichaOverlayActiva = false;

  function cerrarFichaOverlay() {
    if (!fichaOverlayActiva) return;
    fichaOverlayActiva = false;
    var capaF = document.querySelector('[data-aht-screen="ficha"]');
    if (capaF) {
      capaF.classList.remove('ds-ficha-overlay');
      capaF.removeAttribute('data-overlay-active');
    }
  }

  function cerrarDiarioVecino() {
    setCapa(diarioVolverCapa || 'ficha');
  }

  function diarioCategoriaMeta(e) {
    const filtro = e.filtro_grupo || 'relaciones';
    const txt = e.categoria_etiqueta || 'Relación';
    const clsMap = {
      planes: 'pg-pill--lavender',
      relaciones: 'pg-pill--mustard',
      cambios: 'pg-pill--tan'
    };
    const low = String(txt).toLowerCase();
    if (low.indexOf('ánimo') >= 0 || low.indexOf('animo') >= 0) {
      return { cls: 'pg-pill--red', txt: txt, grupo: 'cambios' };
    }
    return { cls: clsMap[filtro] || 'pg-pill--mustard', txt: txt, grupo: filtro };
  }

  function diarioEntradaMatchFiltro(e, filtro) {
    if (filtro === 'todo') return true;
    if (e.filtro_grupo) return e.filtro_grupo === filtro;
    return diarioCategoriaMeta(e).grupo === filtro;
  }

  function diarioEntradaMatchBusca(e, q) {
    if (!q) return true;
    const personas = Array.isArray(e.personas)
      ? e.personas.map(function (p) { return p.nombre || ''; }).join(' ')
      : '';
    const hay = [
      e.titulo || '',
      e.explicacion || e.texto || '',
      personas
    ].join(' ').toLowerCase();
    return hay.indexOf(q) >= 0;
  }

  function diarioPersonasHtml(e, rid) {
    if (Array.isArray(e.personas) && e.personas.length) {
      return e.personas.map(function (p) {
        const cara = p.retrato_url || tokenDe(p.id);
        return '<span class="fdi-con">' +
          (cara
            ? '<img class="fdi-con-ava" src="' + esc(cara) + '" alt=""/>'
            : '<span class="fdi-con-ava fdi-con-ava--ph" aria-hidden="true"></span>') +
          '<span class="fdi-con-nom">' + esc(p.nombre || 'Alguien del pueblo') + '</span></span>';
      }).join('');
    }
    const actores = Array.isArray(e.actores) ? e.actores : [];
    const otro = actores.find(function (a) { return a !== rid; });
    if (!otro) return '';
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const r = res[otro];
    const nombre = (r && r.identidad_publica && r.identidad_publica.nombre) || otro;
    const cara = tokenDe(otro);
    return '<span class="fdi-con">' +
      (cara ? '<img class="fdi-con-ava" src="' + esc(cara) + '" alt=""/>' : '') +
      '<span class="fdi-con-nom">' + esc(nombre) + '</span></span>';
  }

  /* â”€â”€ Diario: doodles decorativos (catálogo compartido) â”€â”€ */
  var DIARIO_DOODLE = {
    h: '<svg viewBox="0 0 24 24" fill="none" stroke="#e989a7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    s: '<svg viewBox="0 0 24 24" fill="none" stroke="#e3b04b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    f: '<svg viewBox="0 0 24 24" fill="none" stroke="#d4bee8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3" fill="#e3d6f0"/><path d="M12 2a3 3 0 0 1 0 6 3 3 0 0 1 0-6z"/><path d="M19.07 4.93a3 3 0 0 1-4.24 4.24 3 3 0 0 1 4.24-4.24z"/><path d="M22 12a3 3 0 0 1-6 0 3 3 0 0 1 6 0z"/><path d="M19.07 19.07a3 3 0 0 1-4.24-4.24 3 3 0 0 1 4.24 4.24z"/><path d="M12 22a3 3 0 0 1 0-6 3 3 0 0 1 0 6z"/><path d="M4.93 19.07a3 3 0 0 1 4.24-4.24 3 3 0 0 1-4.24 4.24z"/><path d="M2 12a3 3 0 0 1 6 0 3 3 0 0 1-6 0z"/><path d="M4.93 4.93a3 3 0 0 1 4.24 4.24A3 3 0 0 1 4.93 4.93z"/></svg>',
    p: '<svg viewBox="0 0 24 24" fill="#8faa84" stroke="none"><ellipse cx="7" cy="5" rx="2.5" ry="3"/><ellipse cx="17" cy="5" rx="2.5" ry="3"/><ellipse cx="12" cy="4" rx="2.5" ry="3"/><ellipse cx="12" cy="14" rx="5" ry="4"/></svg>',
    o: '<svg viewBox="0 0 24 24" fill="none" stroke="#b9a8dc" stroke-width="2" stroke-linecap="round"><ellipse cx="12" cy="12" rx="10" ry="7"/><circle cx="12" cy="12" r="3" fill="#d4bee8"/><circle cx="13" cy="11" r="1" fill="#fff"/></svg>',
    w: '<svg viewBox="0 0 24 24" fill="none" stroke="#e87a5a" stroke-width="2" stroke-linecap="round"><path d="M12 12c-2-2.67-6-4-6-8a6 6 0 0 1 12 0c0 4-4 5.33-6 8z"/><path d="M12 12c2-2.67 6-4 6-8a6 6 0 0 0-12 0c0 4 4 5.33 6 8z"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="#e3b04b" stroke-width="2" stroke-linecap="round"><line x1="8" y1="2" x2="6" y2="8"/><line x1="16" y1="4" x2="18" y2="10"/><line x1="12" y1="1" x2="12" y2="7"/></svg>',
    d: '<svg viewBox="0 0 24 24" fill="none" stroke="#e989a7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.84 4.61a3.5 3.5 0 0 0-5.68 0L12 5.17l-.84-.84a3.5 3.5 0 0 0-5.68 5.68L12 17.5l6.52-6.52a3.5 3.5 0 0 0-1.68-6.37z" fill="#f9dce4"/><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>'
  };

  var DIARIO_DOODLE_MAP = {
    'Relación': 'h',
    'Relacion': 'h',
    'Encuentro': 'f',
    'Descubrimiento': 's',
    'Ánimo': 'p',
    'Animo': 'p',
    'Cambio': 'p',
    'Plan': 's',
    'Nos vimos': 'f',
    'Algo con vecinos': 'f',
    'Señal romántica': 'h',
    'Senal romantica': 'h'
  };

  var DIARIO_DOODLE_EXTRA = ['o', 'w', 'x', 'd', 'h', 's', 'f'];

  function diarioDoodleHtml(e) {
    var cat = e.categoria_etiqueta || '';
    var key = DIARIO_DOODLE_MAP[cat] || 'f';
    var svg = DIARIO_DOODLE[key] || DIARIO_DOODLE.f;
    return '<span class="fdi-doodle" aria-hidden="true">' + svg + '</span>';
  }

  function diarioHoraHtml(e) {
    var ts = e.ts_juego;
    if (!ts || typeof ts.hora !== 'number') return '';
    var h = ts.hora;
    var m = typeof ts.minuto === 'number' ? ts.minuto : 0;
    return '<span class="fdi-hora">' + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + '</span>';
  }

  function entradaDiarioHtml(e, rid) {
    const meta = diarioCategoriaMeta(e);
    const eventoId = (e.origen && e.origen.evento_id) || e.evento_id || '';
    const titulo = String(e.titulo || '').trim() || 'Algo pasó';
    const explicacion = String(e.explicacion || e.texto || '').trim();
    const tono = e.tono || '';
    const tonoCls = tono ? ' fdi-card--' + tono : '';
    const personasHtml = diarioPersonasHtml(e, rid);
    const doodleHtml = diarioDoodleHtml(e);
    const horaHtml = diarioHoraHtml(e);
    let html = '<article class="ficha-diario-entrada fdi-entrada" data-diario-evento="' + esc(eventoId) + '">' +
      '<div class="ficha-diario-card fdi-card' + tonoCls + '">' +
      '<div class="fdi-card-body">' +
      '<div class="fdi-card-main">' +
      '<div class="fdi-card-head">' +
      doodleHtml +
      '<b class="ficha-diario-titulo">' + esc(titulo) + '</b>' +
      '</div>';
    if (explicacion && explicacion !== titulo) {
      html += '<p class="ficha-diario-texto">' + esc(explicacion) + '</p>';
    }
    html += '</div>' +
      '<div class="fdi-card-side">' +
      horaHtml +
      '<span class="ficha-diario-cat ' + meta.cls + '">' + esc(meta.txt) + '</span>';
    if (personasHtml) {
      html += '<div class="fdi-personas">' + personasHtml + '</div>';
    }
    html += '</div></div></div></article>';
    return html;
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
    let inTl = false;
    entradas.forEach(function (e) {
      const diaNum = e.dia || '';
      const diaLbl = e.fecha_corta || ('Día ' + diaNum);
      const diaKey = String(diaNum) + '|' + diaLbl;
      if (diaKey !== diaPrev) {
        if (inTl) html += '</div></div>';
        html += '<div class="fdi-dia-grupo">' +
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
      const sel = '[data-diario-evento="' + String(diarioHighlightId).replace(/"/g, '\"') + '"]';
      const dest = list.querySelector(sel);
      if (dest) {
        dest.classList.add('is-destacada');
        try { dest.scrollIntoView({ block: 'center' }); } catch (err) {}
      }
      diarioHighlightId = null;
    }
  }

  function pintarDiarioVecinoHero(nom, img, total) {
    var list = $('[data-diario-list]');
    if (!list) return;
    var heroHtml = '<div class="fdi-hero-body">' +
      '<div class="fdi-hero-portrait">' + img + '</div>' +
      '<div class="fdi-hero-txt">' +
      '<b class="fdi-hero-nom">Diario de ' + esc(nom) + '</b>' +
      '<p class="fdi-hero-sub">Mi historia en el pueblo</p>' +
      '</div>' +
      '<span class="fdi-hero-count">' + total + ' recuerdos</span>' +
      '<span class="fdi-hero-doodle" aria-hidden="true">' + (window.AHT_DOODLE_SVGS && window.AHT_DOODLE_SVGS.d || '') + '</span>' +
      '</div>';
    list.insertAdjacentHTML('afterbegin', heroHtml);
  }

  function syncDiarioVecinoFiltros() {
    $$('[data-diario-filt]').forEach(function (b) {
      const on = (b.getAttribute('data-diario-filt') || '') === diarioVecinoFiltro;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    const ord = $('[data-diario-orden]');
    if (ord) {
      ord.textContent = diarioVecinoOrden === 'antiguo' ? 'Más antiguo' : 'Más reciente';
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
    syncDiarioVecinoFiltros();
    pintarDiarioVecinoLista();
    pintarDiarioVecinoHero(nom, img, diarioVecinoCache.length);
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
    if (t.indexOf('café') >= 0 || t.indexOf('cafe') >= 0) return '? ';
    if (t.indexOf('biblioteca') >= 0 || t.indexOf('leer') >= 0) return '?? ';
    if (t.indexOf('pasear') >= 0 || t.indexOf('parque') >= 0) return '?? ';
    return '';
  }

  function pintarSlotsRasgos(box, slots) {
    if (!box) return;
    box.innerHTML = '';
    const items = (slots && slots.length) ? slots : slotsDesdeLista([]);
    items.forEach(function (sl) {
      const card = document.createElement('div');
      card.className = 'ficha-rasgo-tag' + (sl.descubierto ? '' : ' is-desconocido');
      const dot = document.createElement('span');
      dot.className = 'ficha-rasgo-dot';
      dot.setAttribute('aria-hidden', 'true');
      const lab = document.createElement('span');
      if (sl.descubierto) {
        lab.textContent = sl.texto || '';
      } else {
        lab.textContent = '?';
        lab.className = 'is-desconocido';
      }
      card.appendChild(dot);
      card.appendChild(lab);
      box.appendChild(card);
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
        lab.textContent = '';
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
    return 'Gente: ' + slotGenteTxt(items[0]) + ' Â· ' + slotGenteTxt(items[1]);
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
      'Cero planes. Cero prisas. Cero dramaâ por ahora.'
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
    if (!rel) return 'â€”';
    if (rel.etiqueta_vinculo === 'crisis') return 'En crisis';
    if (rel.etiqueta_vinculo === 'pareja') return 'Pareja';
    if (rel.etiqueta_vinculo === 'ex_pareja') return 'Ex pareja';
    var social = '';
    if (rel.etiqueta_social_ui) {
      social = String(rel.etiqueta_social_ui);
    } else {
      var map = {
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
      social = map[rel.etiqueta_social] || String(rel.etiqueta_social || '').replace(/_/g, ' ');
    }
    var rom = '';
    if (rel.romance_visible && rel.etiqueta_romance) {
      rom = String(rel.etiqueta_romance);
    }
    if (rom && social) return social + ' \u00B7 ' + rom;
    if (rom) return rom;
    return social || '\u2014';
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
        var edadNum = $('[data-ficha-edad-num]', edadEl);
        if (edadNum) edadNum.textContent = String(edadVal);
        edadEl.hidden = false;
      } else {
        var edadNum2 = $('[data-ficha-edad-num]', edadEl);
        if (edadNum2) edadNum2.textContent = '';
        edadEl.hidden = true;
      }
    }
    const trabajoEl = $('[data-ficha-trabajo]');
    const trabajoTxtEl = $('[data-ficha-trabajo-txt]');
    if (trabajoEl) {
      var t = vista.trabajo || {};
      var linea = '';
      if (t.desempleado) {
        linea = 'Sin trabajo \u{1F4CB}';
      } else if (t.linea_principal) {
        linea = t.linea_principal;
        if (t.linea_horario) linea += ' \u00b7 ' + t.linea_horario;
      } else if (vista.ocupacion) {
        linea = vista.ocupacion;
      }
      if (linea) {
        if (trabajoTxtEl) trabajoTxtEl.textContent = linea;
        trabajoEl.hidden = false;
      } else {
        if (trabajoTxtEl) trabajoTxtEl.textContent = '';
        trabajoEl.hidden = true;
      }
    }
    const desdeTagEl = $('[data-ficha-desde-tag]');
    const desdeDiasEl = $('[data-ficha-dias-num]');
    if (desdeTagEl && desdeDiasEl) {
      var dias = diasEnElPueblo(id);
      desdeDiasEl.textContent = String(dias);
      desdeTagEl.hidden = false;
    }
    var cumpleTxtEl = $('[data-ficha-cumple-txt]');
    if (cumpleTxtEl) {
      var cp = vista.cumpleanos || (f.identidad && f.identidad.cumpleanos);
      if (cp && cp.dia && cp.mes) {
        var meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        cumpleTxtEl.textContent = '(cumplo ' + cp.dia + ' de ' + (meses[(cp.mes | 0) - 1] || '') + ')';
        cumpleTxtEl.hidden = false;
      } else {
        cumpleTxtEl.textContent = '';
        cumpleTxtEl.hidden = true;
      }
    }
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
    pintarRelacionesEn(relBox, fichaRelCache, 2, { compact: true });
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
          planBox.insertAdjacentHTML('beforeend', htmlFichaPlanItem(enc, id, cacheEstado));
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
    // Necesidades personales — siempre las 4, con barra y color por banda
    const necSection = $('[data-ficha-necesidades]');
    const necBox = $('[data-ficha-necesidades-body]');
    if (necSection && necBox) {
      var necDefaults = [
        { id: 'social',    nombre: 'Social' },
        { id: 'diversion', nombre: 'Diversi\u00f3n' },
        { id: 'actividad', nombre: 'Actividad' },
        { id: 'calma',     nombre: 'Calma' }
      ];
      var nec = f.necesidades;
      var serverItems = (nec && nec.items) ? nec.items : [];
      var byId = {};
      serverItems.forEach(function (item) {
        var key = item.id || item.nombre || '';
        byId[key] = item;
      });
      necBox.innerHTML = '';
      necDefaults.forEach(function (def) {
        var item = byId[def.id] || byId[def.nombre] || {};
        var val = Math.max(0, Math.min(100, parseInt(item.valor, 10) || 0));
        var banda = esc(item.band || item.banda || '');
        var colorBar = banda === 'en_rojo' ? '#c42b4a'
          : banda === 'lo_necesita' ? '#d98a3e'
          : banda === 'le_vendria_bien' ? '#b8a44e'
          : '#5a9a6a';
        var copyTxt = item.copy || '';
        necBox.insertAdjacentHTML('beforeend',
          '<div class="ficha-nec-row">'
          + '<div class="ficha-nec-head">'
          + '<span class="ficha-nec-ico" aria-hidden="true">' + necIconHtml(def.id, 20) + '</span>'
          + '<span class="ficha-nec-nom">' + esc(item.nombre || def.nombre) + '</span>'
          + '<span class="ficha-nec-val">' + val + '</span>'
          + '</div>'
          + '<div class="ficha-nec-bar ficha-nec-bar--' + banda + '"><i style="width:' + val + '%;background:' + colorBar + '"></i></div>'
          + (copyTxt ? '<span class="ficha-nec-copy">' + esc(copyTxt) + '</span>' : '')
          + '</div>'
        );
      });
      necSection.hidden = false;
    }
    syncFichaNav();
  }

  function fichaNavBotones() {
    const capa = document.querySelector('[data-aht-screen="ficha"]');
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
      if (opts.overlay) {
        fichaOverlayActiva = true;
        var capaF = document.querySelector('[data-aht-screen="ficha"]');
        if (capaF) {
          capaF.classList.add('ds-ficha-overlay');
          capaF.setAttribute('data-overlay-active', '1');
        }
      } else {
        setCapa('ficha');
      }
    } catch (err) {
      console.error('pintarFicha', err);
      toast('No se pudo mostrar la ficha de este vecino.');
    }
  }

  function estadoCarta(m) {
    const pueblo = m.estado_pueblo || '';
    const natural = mensajitoEstadoNatural(m);
    if (pueblo === 'cumplida') return { cls: 'estado-cumplida', txt: natural || 'Hecho' };
    if (pueblo === 'caducada') return { cls: 'estado-caducada', txt: natural };
    if ((m.estado || '') === 'pendiente') return { cls: 'estado-pendiente', txt: '' };
    if ((m.estado || '') === 'leido') return { cls: 'estado-leida', txt: '' };
    if ((m.estado || '') === 'en_espera') return { cls: 'estado-espera', txt: natural || 'En espera' };
    if ((m.estado || '') === 'resuelto') return { cls: 'estado-cumplida', txt: natural };
    return { cls: '', txt: natural };
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
    const wrap = $('[data-buzon-leer-todos-wrap]');
    if (wrap) wrap.hidden = n === 0;
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
    if ((msgs || []).some(mensajitoEsObjetoRecibido) && !cacheCatalogoRegalos) {
      ensureCatalogoRegalos().then(function () { renderBuzon(cacheBuzon); });
    }
    box.innerHTML = '';
    renderMensajitosPop(msgs);
    actualizarBuzonLeerTodosBtn(msgs);
    const cartasTodas = mensajitosOrdenados(msgs);
    const nuevos = cartasTodas.filter(function (m) { return (m.estado || '') === 'pendiente'; });
    const leidos = cartasTodas.filter(function (m) { return (m.estado || '') !== 'pendiente'; });
        const tabCount = $('[data-buzon-tab-count]');
    if (tabCount) {
      tabCount.textContent = String(nuevos.length);
      tabCount.hidden = nuevos.length === 0;
    }
    let filtro = box.getAttribute('data-buzon-filtro') || 'nuevos';
    document.querySelectorAll('[data-buzon-tab]').forEach(function (tab) {
      const isSelected = tab.getAttribute('data-buzon-tab') === filtro;
      tab.classList.toggle('is-active', isSelected);
      tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
      tab.onclick = function () {
        filtro = tab.getAttribute('data-buzon-tab') || 'nuevos';
        box.setAttribute('data-buzon-filtro', filtro);
        document.querySelectorAll('[data-buzon-tab]').forEach(function (t) {
          const on = t === tab;
          t.classList.toggle('is-active', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        renderBuzon(cacheBuzon);
        const active = document.querySelector('[data-buzon-tab="' + filtro + '"]');
        if (active) { active.classList.add('is-active'); active.setAttribute('aria-selected', 'true'); }
      };
    });
    const cartas = filtro === 'leidos' ? leidos : nuevos;
    if (!cartas.length) {
      box.innerHTML = '<div class="aht-msg-empty">' +
        '<div class="aht-msg-empty-icon">\uD83D\uDCED</div>' +
        '<p class="aht-msg-empty-text">No hay mensajes por ahora. Cuando llegue algo, lo ver\u00e1s aqu\u00ed.</p>' +
        '</div>';
      return;
    }
    const accion = cartas.filter(mensajitoRequiereAccion);
    const info = cartas.filter(function (m) { return !mensajitoRequiereAccion(m); });

    function pintarCarta(m, esAccion, inclinIdx) {
      const art = document.createElement('article');
      const st = estadoCarta(m);
      const leido = (m.estado || '') !== 'pendiente';
      const nombre = nombrePublicoDe(m);
      const ridRem = remitenteIdDe(m);
      const esRegaloRecibido = mensajitoEsObjetoRecibido(m);
      const objetoVista = esRegaloRecibido ? mensajitoObjetoVistaDe(m) : null;
      const paper = msgPaperDeRemitente(ridRem || nombre);
      const origen = mensajitoBuscarOrigen(m, cacheBuzon);
      const esActualizacion = !!origen;
      const tieneEleccion = Array.isArray(m.selector_opciones) && m.selector_opciones.length &&
        m.selector_estado === 'pendiente' && (m.estado_pueblo || 'pendiente') === 'pendiente';
      art.className = 'aht-msg-card' +
        (esActualizacion ? ' aht-msg-card--thread' : '') +
        (tieneEleccion ? ' aht-msg-card--election' : '') +
        (esRegaloRecibido ? ' aht-msg-card--gift' : '');
      art.setAttribute('data-paper', paper);
      art.setAttribute('data-unread', leido ? 'false' : 'true');
      if (esAccion) art.setAttribute('data-action', 'true');
      else art.setAttribute('data-info', 'true');
      if (ridRem) art.setAttribute('data-remitente', ridRem);
      const cuerpo = cuerpoMensajito(m, nombre);
      const regaloObjetoHtml = objetoVista ? htmlMensajitoRegaloObjeto(objetoVista) : '';
      const perfilLlegadaHtml = (m.tipo === 'candidato_llegada') ? htmlPerfilCandidato(m) : '';
      const plazoLbl = mensajitoPlazoLabel(m);
      const cuando = mensajitoCuandoLabel(m);
      const avatarHtml = htmlAvatarMensajito(m, nombre, 'aht-msg-avatar');
      const flagHtml = !leido
        ? '<span class="aht-msg-flag aht-msg-flag--new">Nuevo</span>'
        : '<span class="aht-msg-flag aht-msg-flag--read">Visto</span>';
      const headerHtml = '<div class="aht-msg-header-row">' +
        (nombre && (ridRem || !esRegaloRecibido)
          ? '<p class="aht-msg-from" data-persona-id="' + esc(ridRem || '') + '" data-persona-nombre="' + esc(nombre) + '" role="button" tabindex="0">' + esc(nombre) + '</p>'
          : '') +
        flagHtml +
        '</div>';
      const statusHtml = '<div class="aht-msg-status-row">' +
        (cuando ? '<span class="aht-msg-date">' + esc(cuando) + '</span>' : '') +
        '<span class="aht-msg-status"></span>' +
        '</div>';
      const accionesDecision = htmlAccionesMensajito(m);
      let accionesHtml = '';
      if (accionesDecision) {
        accionesHtml = accionesDecision;
      } else if (m.preset_organizar && (m.estado_pueblo || 'pendiente') === 'pendiente' && (m.estado || '') === 'pendiente') {
        const orgLab = mensajitoCtaOrganizarLabel(m);
        accionesHtml = '<div class="aht-msg-actions">' +
          '<button type="button" class="aht-msg-btn aht-msg-btn--primary" data-carta-organizar="1">' + esc(orgLab) + '</button>' +
          '</div>';
      }
      const estadoTxt = st.txt || mensajitoEstadoNatural(m);
      const plazoHtml = plazoLbl
        ? '<div class="aht-msg-deadline">\uD83D\uDD52 ' + esc(plazoLbl) + '</div>'
        : '';
      const actualizacionEtq = esActualizacion
        ? '<span class="carta-actualizacion-etq">Actualizaci\u00f3n' + (cuando ? ' \u00b7 ' + esc(cuando.split(' \u00b7 ').pop()) : '') + '</span>'
        : '';
      const mostrarCabeceraVecino = !esRegaloRecibido || !!ridRem;
      const bodyHtml = '<div class="aht-msg-content">' +
        (esActualizacion ? htmlMensajitoHilo(origen) : '') +
        actualizacionEtq +
        '<p class="aht-msg-body">' + esc(cuerpo) + '</p>' +
        regaloObjetoHtml +
        perfilLlegadaHtml +
        plazoHtml +
        accionesHtml +
        '</div>';
      art.innerHTML = (mostrarCabeceraVecino ? avatarHtml + headerHtml + statusHtml : (cuando ? '<div class="aht-msg-status-row"><span class="aht-msg-date">' + esc(cuando) + '</span></div>' : '')) + bodyHtml;
      art.querySelectorAll('[data-carta-organizar]').forEach(function (btn) {
        btn.addEventListener('click', async function (ev) {
          ev.stopPropagation();
          if (!mensajitoEstaLeido(m)) await marcarMensajitoLeido(m);
          abrirOrganizarConPreset(m.preset_organizar);
        });
      });
      art.addEventListener('click', function (ev) {
        var fromEl = ev.target.closest('.aht-msg-from[data-persona-id]');
        if (fromEl) {
          ev.stopPropagation();
          var pid = fromEl.getAttribute('data-persona-id');
          if (pid) abrirFicha(pid, { overlay: true });
          return;
        }
        var avatarEl = ev.target.closest('.aht-msg-avatar[data-persona-id]');
        if (avatarEl && !avatarEl.closest('.aht-msg-choice-opt')) {
          ev.stopPropagation();
          var pid3 = avatarEl.getAttribute('data-persona-id');
          if (pid3) abrirFicha(pid3, { overlay: true });
          return;
        }
        var optAvatar = ev.target.closest('.msg-eleccion-avatar-wrap [data-persona-id]');
        if (optAvatar) {
          ev.stopPropagation();
          var pid4 = optAvatar.getAttribute('data-persona-id');
          if (pid4) abrirFicha(pid4, { overlay: true });
          return;
        }
        var optName = ev.target.closest('.aht-msg-choice-opt-name');
        if (optName) {
          var optBtn = optName.closest('button[data-elegir-persona], button[data-llegada-acomp]');
          if (optBtn && !optBtn.closest('.aht-msg-choice')) return;
        }
      });
      art.addEventListener('click', async function (ev) {
        if (ev.target.closest('button') || ev.target.closest('.aht-msg-read-toggle')) return;
        if (!mensajitoEstaLeido(m) && !mensajitoTieneAccionReal(m)) {
          await marcarMensajitoLeido(m);
          await refresh();
        }
      });
      wireAccionesMensajito(art, m);
      var readToggle = crearMsgLeidoToggle(m);
      var statusSlot = art.querySelector('.aht-msg-status');
      if (statusSlot) statusSlot.appendChild(readToggle);
      else art.appendChild(readToggle);
      return art;
    }

    let inclinGlobal = 0;
    function pintarSeccion(titulo, items, esAccion) {
      if (!items.length) return;
      const sec = document.createElement('section');
      sec.className = 'aht-msg-section';
      items.forEach(function (m) {
        sec.appendChild(pintarCarta(m, esAccion, inclinGlobal++));
      });
      box.appendChild(sec);
    }

    pintarSeccion('Piden algo', accion, true);
    info.forEach(function (m) {
      box.appendChild(pintarCarta(m, false, inclinGlobal++));
    });
  }

  function cotiFiltroIco(catId) {
    const k = String(catId || '').toLowerCase();
    if (!k || k === 'todo') {
      return '<svg class="coti-svg coti-svg--filtro" viewBox="0 0 32 32" aria-hidden="true"><rect x="5" y="5" width="9" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="18" y="5" width="9" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="5" y="18" width="9" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="18" y="18" width="9" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    return cotiCatSvg(k).replace('class="coti-svg"', 'class="coti-svg coti-svg--filtro"');
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
    if (bucket === 'hoy') return 'Hoy';
    if (bucket === 'ayer') return 'Ayer';
    if (e.fecha_corta) return e.fecha_corta;
    return e.dia ? ('día ' + e.dia) : '';
  }

  function htmlCotiAvatares(actores) {
    const ids = (actores && actores.length) ? actores.slice(0, 2) : [];
    if (!ids.length) return '<span class="coti-item-sin-cara" aria-hidden="true">â</span>';
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

  var COTI_LUGAR_HASHTAG = {
    lug_cafeteria: 'Cafeter\u00eda', lug_biblioteca: 'Biblioteca', lug_gimnasio: 'Gimnasio',
    lug_restaurante: 'Restaurante', lug_parque: 'Parque', lug_bar: 'Bar',
    lug_cine: 'Cine', lug_discoteca: 'Discoteca', lug_bingo: 'Bingo',
    lug_plaza: 'Plaza', lug_arcade: 'Arcade', lug_tienda_ropa: 'Tienda de ropa',
    lug_mirador: 'Mirador', lug_casa: 'Casa'
  };

  var COTI_CAT_HASHTAG = {
    romance: 'Romance', drama: 'Tensi\u00f3n', relacion: 'Relaci\u00f3n',
    encuentro: 'Encuentro', descubrimiento: 'Pista', pueblo: 'Pueblo',
    coincidencias: 'Casualidad'
  };

  function cotiHashtags(e) {
    var tags = [];
    if (e.lugar_id && COTI_LUGAR_HASHTAG[e.lugar_id]) {
      tags.push('#' + COTI_LUGAR_HASHTAG[e.lugar_id]);
    }
    var actors = e.actores || [];
    for (var i = 0; i < actors.length && i < 2; i++) {
      var nom = nombreDe(actors[i]);
      if (nom && nom !== actors[i]) tags.push('#' + nom);
    }
    var cat = String(e.categoria || '').toLowerCase();
    if (COTI_CAT_HASHTAG[cat]) tags.push('#' + COTI_CAT_HASHTAG[cat]);
    return tags;
  }

  function cotiReaccion(e) {
    var cat = String(e.categoria || '').toLowerCase();
    var dest = e.destacado === true;
    if (dest && cat === 'drama') return { icono: '\u26a1', texto: '\u00a1Tema caliente!' };
    if (dest && cat === 'romance') return { icono: '\ud83d\udc95', texto: '\u00a1Se huele algo!' };
    if (dest && cat === 'relacion') return { icono: '\ud83e\udd1d', texto: 'Parece que se han hablado' };
    if (dest && cat === 'pueblo') return { icono: '\ud83d\udce2', texto: 'Nuevo vecino en el pueblo' };
    if (dest && cat === 'descubrimiento') return { icono: '\ud83d\udd0d', texto: 'Pista interesante...' };
    if (!dest) return { icono: '\ud83d\udd0d', texto: 'Qu\u00e9 curioso...' };
    return { icono: '\u2615', texto: 'Algo se cuece' };
  }

  function htmlCotiItem(e, bucket) {
    var cat = String(e.categoria || 'encuentro').toLowerCase();
    var dest = e.destacado === true ? ' coti-item--destacado' : '';
    var cuando = cotiEtiquetaTiempo(e, bucket);
    var handle = e.lugar_handle || '@puebloconfidencial';
    var hashtags = cotiHashtags(e);
    var reaccion = cotiReaccion(e);
    var tagsHtml = hashtags.map(function (t) {
      return '<span class="coti-tag">' + esc(t) + '</span>';
    }).join('');
    return '<article class="coti-item coti-item--' + esc(cat) + dest + '">' +
      '<div class="coti-item-header">' +
        '<div class="coti-item-avatares">' + htmlCotiAvatares(e.actores) + '</div>' +
        '<div class="coti-item-meta-line">' +
          '<span class="coti-item-handle">' + esc(handle) + '</span>' +
          '<span class="coti-item-sep">\u00b7</span>' +
          '<span class="coti-item-tiempo">' + esc(cuando) + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="coti-item-body">' +
        '<p class="coti-item-txt">' + esc(e.texto || '') + '</p>' +
      '</div>' +
      (tagsHtml || reaccion.texto ? '<div class="coti-item-footer">' +
        (tagsHtml ? '<div class="coti-item-tags">' + tagsHtml + '</div>' : '') +
        (reaccion.texto ? '<div class="coti-item-reaccion"><span class="coti-item-reaccion-ico">' + reaccion.icono + '</span> <span class="coti-item-reaccion-txt">' + esc(reaccion.texto) + '</span></div>' : '') +
      '</div>' : '') +
      '</article>';
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
    if (!keys.length) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    let filtrosHtml = '<button type="button" class="coti-filtro coti-filtro--todo' + (cotiFiltroActivo === '' ? ' is-on' : '') + '" data-coti-filtro="" aria-label="Todos" title="Todos"><span class="coti-filtro-pill" aria-hidden="true">' + cotiFiltroIco('todo') + '</span></button>';
    filtrosHtml += keys.map(function (id) {
      const c = cats[id];
      const on = cotiFiltroActivo === id ? ' is-on' : '';
      return '<button type="button" class="coti-filtro coti-cat--' + esc(id) + on + '" data-coti-filtro="' + esc(id) + '" aria-label="' + esc(c.etiqueta) + '" title="' + esc(c.etiqueta) + '">' +
        '<span class="coti-filtro-pill" aria-hidden="true">' + cotiFiltroIco(id) + '</span></button>';
    }).join('');
    box.innerHTML = filtrosHtml;
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
    var items = cotiTodosItems(cotiCache);
    renderCotilleoFiltros(items);
    renderCotilleoLista(cotiCache);
    renderSeHablaDe(items);
  }

  function renderSeHablaDe(items) {
    var box = document.querySelector('[data-coti-se-habla]');
    if (!box) return;
    if (!items || !items.length) { box.innerHTML = ''; return; }
    var contar = {};
    items.forEach(function (e) {
      if (e.lugar_id && COTI_LUGAR_HASHTAG[e.lugar_id]) {
        var tag = '#' + COTI_LUGAR_HASHTAG[e.lugar_id];
        contar[tag] = (contar[tag] || 0) + 1;
      }
      var actors = e.actores || [];
      for (var i = 0; i < actors.length && i < 2; i++) {
        var nom = nombreDe(actors[i]);
        if (nom && nom !== actors[i]) {
          var t = '#' + nom;
          contar[t] = (contar[t] || 0) + 1;
        }
      }
      var cat = String(e.categoria || '').toLowerCase();
      if (COTI_CAT_HASHTAG[cat]) {
        var ct = '#' + COTI_CAT_HASHTAG[cat];
        contar[ct] = (contar[ct] || 0) + 1;
      }
    });
    var sorted = Object.keys(contar).sort(function (a, b) { return contar[b] - contar[a]; }).slice(0, 5);
    if (!sorted.length) { box.innerHTML = ''; return; }
    var html = '<h3 class="coti-sehabla-tit">🔥 Se habla de…</h3><ul class="coti-sehabla-list">';
    sorted.forEach(function (tag, i) {
      var rankClass = i < 3 ? ' coti-sehabla-top-' + (i + 1) : ' coti-sehabla-normal';
      html += '<li class="coti-sehabla-item' + rankClass + '"><span class="coti-sehabla-num">#' + (i + 1) + '</span> <span class="coti-sehabla-tag">' + esc(tag) + '</span> <span class="coti-sehabla-count">' + contar[tag] + '</span></li>';
    });
    html += '</ul>';
    box.innerHTML = html;
  }

  function idsResidentes() {
    return Object.keys((cacheInsp && cacheInsp.residentes) || {});
  }

  var ORG_LUGAR_DESC = {
    lug_cafeteria: 'El lugar perfecto para tomar algo y charlar tranquilamente.',
    lug_bar: 'Ambiente relajado para una copa y buena conversación.',
    lug_biblioteca: 'Rincones tranquilos para leer o charlar en voz baja.',
    lug_parque: 'Aire libre y calma para pasear o sentarse un rato.',
    lug_cine: 'Una sesión de cine para compartir sin prisas.',
    lug_gimnasio: 'Moverse juntos o animar un plan activo.',
    lug_restaurante: 'Mesa puesta para comer bien y hablar con calma.',
    lug_bingo: 'Partida de bingo y risas en comunidad.',
    lug_discoteca: 'Música y ambiente para soltar el cuerpo.',
    lug_karaoke: 'Cantar, reír y pasarlo bien.',
    lug_mirador: 'Vistas del pueblo para un momento especial.',
    lug_picnic: 'Manta, comida y charla al aire libre.',
    lug_arcade: 'Juegos y diversión en el recreativo.',
    lug_recreativo: 'Juegos y diversión en el recreativo.',
    lug_spa: 'Relax y cuidado para desconectar un poco.',
    lug_tienda: 'Un paseo por la tienda del pueblo.'
  };

  var LUGAR_SUBTITULO = {
    lug_cafeteria: 'Entre aroma a café y libros viejos.',
    lug_bar: 'Don las buenas conversaciones se brindan.',
    lug_biblioteca: 'Entre historias, la vida se ve mejor.',
    lug_parque: 'Aire libre, calma y buenos planes.',
    lug_cine: 'Entre historias, la vida se ve mejor.',
    lug_gimnasio: 'Moverse juntos siempre es mejor.',
    lug_restaurante: 'Buen comer y mejor compañía.',
    lug_bingo: 'Emoción y suerte en cada número.',
    lug_discoteca: 'La noche es joven y el ritmo también.',
    lug_karaoke: 'Las voces del pueblo también cantan.',
    lug_mirador: 'El pueblo se ve mejor desde arriba.',
    lug_picnic: 'Manta, sol y nada que hacer.',
    lug_arcade: 'Diversión a tope y sin.permissiones.',
    lug_recreativo: 'Diversión a tope y sin permissiones.',
    lug_spa: 'Un respiro para el cuerpo y el alma.',
    lug_tienda: 'Siempre hay algo que necesitas.'
  };

  var LUGAR_ACTIVIDADES = {
    lug_cafeteria: ['Tomar un café con calma', 'Leer tranquilamente', 'Charlar con amigos', 'Probar algo nuevo del menú'],
    lug_bar: ['Tomar una copa', 'Escuchar música en vivo', 'Conocer gente nueva', 'Disfrutar del ambiente nocturno'],
    lug_biblioteca: ['Leer en silencio', 'Buscar un libro nuevo', 'Estudiar a tu ritmo', 'Descubrir autores olvidados'],
    lug_parque: ['Pasear tranquilamente', 'Hacer ejercicio al aire libre', 'Sentarse a pensar', 'Jugar con amigos'],
    lug_cine: ['Ver una buena película', 'Compartir palomitas', 'Elegir la mejor sala', 'Disfrutar del directo'],
    lug_gimnasio: ['Hacer ejercicio', 'Entrenar con amigos', 'Probar máquinas nuevas', 'Descansar en el spa'],
    lug_restaurante: ['Comer bien', 'Probar el plato del día', 'Celebrar algo especial', 'Disfrutar de la carta'],
    lug_bingo: ['Jugar una partida', 'Hacer la planilla', 'Celebrar los aciertos', 'Pasarlo bien en grupo'],
    lug_discoteca: ['Bailar toda la noche', 'Escuchar buenos temas', 'Socializar en el barra', 'Disfrutar del ambiente'],
    lug_karaoke: ['Cantar tus canciones favoritas', 'Animar a los demás', 'Descubrir tu voz', 'Pasarlo genial'],
    lug_mirador: ['Contemplar las vistas', 'Hacer fotos al atardecer', 'Compartir un momento especial', 'Respirar aire fresco'],
    lug_picnic: ['Montar un buen picnic', 'Comer al aire libre', 'Charlar bajo los árboles', 'Relajarse sin prisas'],
    lug_arcade: ['Jugar a los arcade', 'Superar tus récords', 'Desafiar a amigos', 'Recordar los clásicos'],
    lug_recreativo: ['Jugar a los arcade', 'Superar tus récords', 'Desafiar a amigos', 'Recordar los clásicos'],
    lug_spa: ['Relajarte sin prisas', 'Disfrutar de un masaje', 'Desconectar del ruido', 'Cuidar tu cuerpo'],
    lug_tienda: ['Hacer compras', 'Descubrir cosas nuevas', 'Pasear entre estanterías', 'Encontrar algo especial']
  };

  var NEC_FRASES_LUGAR = {
    social: 'Compartir momentos y crear lazos.',
    diversion: 'Risas, juego y diversión sin fin.',
    actividad: 'Moverse, sudar y sentirse vivo.',
    calma: 'Respirar, desconectar y estar en paz.'
  };

  var LUGAR_CURIOSIDADES = {
    lug_cafeteria: ['Es uno de los lugares más populares del pueblo.', 'A veces se forman buenas amistades aquí.', 'El café de Lola es legendary entre los habitantes.'],
    lug_bar: ['El bar cierra cuando el último cliente se va.', 'Aquí se cuentan las mejores historias del pueblo.', 'La música en vivo atrae a más de uno.'],
    lug_biblioteca: ['Tiene libros que no encontrarás en ningún otro sitio.', 'Es el lugar más tranquilo del pueblo.', 'A veces se organizan clubes de lectura improvisados.'],
    lug_parque: ['El parque es el corazón del pueblo.', 'Aquí siempre hay algo que hacer.', 'Los atardeceres aquí son imperdibles.'],
    lug_cine: ['Proyectan películas que no se ven en ningún otro cine.', 'Las palomitas de Lola son las mejores del pueblo.', 'A veces se quedan después de la película para charlar.'],
    lug_gimnasio: ['Las máquinas están mejor de lo que parecen.', 'El spa es un secreto bien guardado.', 'Aquí se hacen las mejores amistades sudando.'],
    lug_restaurante: ['El plato del día siempre es una sorpresa.', 'Lola pone tanto amor como sazón.', 'Es el lugar favorito para celebrar.'],
    lug_bingo: ['Las noches de bingo son las más divertidas.', 'Siempre hay alguien que grita "¡BINGO!" antes de tiempo.', 'La emoción es contagiosa.'],
    lug_discoteca: ['La discoteca cierra cuando amanece.', 'El DJ siempre sabe lo que el pueblo necesita.', 'Las pistas de baile siempre están llenas.'],
    lug_karaoke: ['Todos cantan, pero no todos cantan bien.', 'La canción más pedida siempre es la misma.', 'El karaoke une a más que una copa.'],
    lug_mirador: ['Las vistas desde arriba no tienen precio.', 'Es el lugar perfecto para un momento especial.', 'Algunos vienen aquí a pensar.'],
    lug_picnic: ['Los picnic siempre terminan en risas.', 'Nadie trae suficiente comida, eso es seguro.', 'El lugar ideal para desconectar.'],
    lug_arcade: ['Los juegos retro nunca pasan de moda.', 'Aquí se forman las mejores competiciones.', 'Los récords aquí son sagrados.'],
    lug_recreativo: ['Los juegos retro nunca pasan de moda.', 'Aquí se forman las mejores competiciones.', 'Los récords aquí son sagrados.'],
    lug_spa: ['El spa es el secreto mejor guardado del pueblo.', 'Todos los que vienen quieren volver.', 'Aquí el tiempo se para un momento.'],
    lug_tienda: ['Siempre hay algo nuevo que descubrir.', 'Los domingos es el día más concurrido.', 'Aquí encuentras cosas que no sabías que necesitabas.']
  };

  var LUGAR_FRASES = {
    lug_cafeteria: 'La vida también tiene escenas inolvidables.',
    lug_bar: 'Las mejores historias se cuentan con una copa en la mano.',
    lug_biblioteca: 'Cada libro es una puerta a otra historia.',
    lug_parque: 'A veces lo mejor es simplemente estar.',
    lug_cine: 'La vida también tiene escenas inolvidables.',
    lug_gimnasio: 'La fuerza está en los buenos momentos compartidos.',
    lug_restaurante: 'Buen comer, mejor compañía.',
    lug_bingo: 'La suerte también se pasa en comunidad.',
    lug_discoteca: 'La música une más que las palabras.',
    lug_karaoke: 'Todos tenemos una canción que contar.',
    lug_mirador: 'A veces hay que subir para ver mejor.',
    lug_picnic: 'Lo simple también tiene su magia.',
    lug_arcade: 'Los buenos momentos no tienen nivel máximo.',
    lug_recreativo: 'Los buenos momentos no tienen nivel máximo.',
    lug_spa: 'Un momento de calma también es una aventura.',
    lug_tienda: 'Las mejores encuentros empiezan con un paseo.'
  };
  var ORG_HORAS_HINT_DEFAULT = 'Los horarios disponibles pueden variar según el lugar.';
  function orgLugarDesc(lugId) {
    var d = destinoOperativoPorId(lugId);
    if (d && d.descripcion) return String(d.descripcion);
    return ORG_LUGAR_DESC[String(lugId || '')] || 'Un sitio del pueblo para quedar.';
  }
  function orgLugarHastaTxt(horario) {
    if (!horario) return '';
    var m = String(horario).match(/(\d{1,2}:\d{2})\s*[-\u2013]\s*(\d{1,2}:\d{2})/);
    return m ? ('Abierto hasta ' + m[2]) : '';
  }
  var ORG_LUGAR_IMG = {
    lug_bar: 'bar.png', lug_biblioteca: 'biblioteca.png', lug_bingo: 'bingo.png', lug_cafeteria: 'cafeteria.png',
    lug_cine: 'cine.png', lug_discoteca: 'discoteca.png', lug_gimnasio: 'gimnasio.png', lug_parque: 'parque.png',
    lug_restaurante: 'restaurante.png', lug_karaoke: 'karaoke.png', lug_mirador: 'mirador.png', lug_picnic: 'picnic.png',
    lug_arcade: 'recreativo.png', lug_recreativo: 'recreativo.png', lug_spa: 'spa.png', lug_tienda: 'tienda.png'
  };
  function orgLugarImg(lugId) {
    var file = ORG_LUGAR_IMG[String(lugId || '')];
    if (!file) { var slug = String(lugId || '').replace(/^lug_/, ''); if (slug) file = slug + '.png'; }
    return file ? ahtAssetUrl('assets/play-v3/edificios/' + file) : '';
  }

  var LUGAR_META = {
    lug_biblioteca: { necesidades: ['Calma'], hobbies: ['Leer'] },
    lug_cafeteria: { necesidades: ['Social', 'Calma'], hobbies: [] },
    lug_parque: { necesidades: ['Actividad', 'Calma'], hobbies: ['Pasear'] },
    lug_cine: { necesidades: ['Diversion', 'Calma'], hobbies: ['Cine'] },
    lug_restaurante: { necesidades: ['Social', 'Diversion'], hobbies: [] },
    lug_bar: { necesidades: ['Social', 'Diversion'], hobbies: [] },
    lug_discoteca: { necesidades: ['Diversion', 'Social'], hobbies: ['Baile'] },
    lug_bingo: { necesidades: ['Social', 'Diversion'], hobbies: [] },
    lug_gimnasio: { necesidades: ['Actividad', 'Diversion'], hobbies: [] }
  };
  function pintarEdInfo(lugId) {
    var el = $('[data-q-info]');
    if (!el) return;
    var info = LUGAR_META[String(lugId || '')];
    if (!info || (!info.necesidades.length && !info.hobbies.length)) {
      el.hidden = true;
      el.innerHTML = '';
      return;
    }
    var html = '<p class="consulta-ed-info-kicker">Qu\u00e9 se puede hacer aqu\u00ed</p><div class="consulta-ed-info-chips">';
    info.necesidades.forEach(function (n) {
      html += '<span class="consulta-ed-info-chip">' + esc(n) + '</span>';
    });
    info.hobbies.forEach(function (h) {
      html += '<span class="consulta-ed-info-chip consulta-ed-info-chip--hobby">' + esc(h) + '</span>';
    });
    html += '</div>';
    el.innerHTML = html;
    el.hidden = false;
  }

  function orgLugarThumbHtml(lugId) {
    var img = orgLugarImg(lugId);
    var inner = img
      ? '<img src="' + esc(img) + '" alt="" loading="lazy" decoding="async"/>'
      : '<span class="org-lugar-thumb-fallback" aria-hidden="true"></span>';
    return '<span class="org-lugar-thumb" aria-hidden="true">' + inner + '</span>';
  }

  function pintarOrgLugares(lugares, value, onChange) {
    var box = $('[data-org-lugares-grid]');
    var native = $('[data-org-lugar]');
    if (!box) return;
    var opts = Array.isArray(lugares) ? lugares : [];
    var valStr = value === null || value === undefined ? '' : String(value);
    box.innerHTML = '';
    if (native) {
      native.innerHTML = '';
      opts.forEach(function (d) {
        var o = document.createElement('option');
        o.value = d.id; o.textContent = d.nombre || d.id;
        if (valStr !== '' && String(d.id) === valStr) o.selected = true;
        native.appendChild(o);
      });
      if (valStr !== '') native.value = valStr;
    }
    if (!opts.length) { box.innerHTML = '<p class="mini org-lugares-vacio">Sin lugares disponibles.</p>'; return; }
    opts.forEach(function (d) {
      var id = d.id, on = valStr !== '' && String(id) === valStr, img = orgLugarImg(id);
      var estado = '', estadoCls = '';
      if (d.abierto_ahora === true) { estado = 'Abierto ahora'; estadoCls = 'org-lugar-estado--abierto'; }
      else if (d.abierto_ahora === false) { estado = 'Cerrado ahora'; estadoCls = 'org-lugar-estado--cerrado'; }
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'org-lugar-card' + (on ? ' is-on' : '');
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.setAttribute('aria-label', d.nombre || id);
      btn.innerHTML = '<span class="org-lugar-card-art">' + (img ? '<img src="' + esc(img) + '" alt="" loading="lazy"/>' : '<span class="org-lugar-card-fallback" aria-hidden="true"></span>') + '</span><span class="org-lugar-card-body"><span class="org-lugar-card-nom">' + esc(d.nombre || id) + '</span>' + (estado ? '<span class="org-lugar-estado ' + estadoCls + '">' + esc(estado) + '</span>' : '') + '</span>';
      if (onChange) {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault(); ev.stopPropagation();
          if (native) { native.value = id; native.dispatchEvent(new Event('change', { bubbles: true })); }
          onChange(id); pintarOrgLugares(opts, id, onChange); pintarOrgLugarHorario(id);
        });
      }
      box.appendChild(btn);
    });
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


  function orgEsEventoPueblo() {
    return org && (org.modo === 'evento' || org.modo === 'evento_pueblo') && !!org.evento_pueblo_id;
  }

  function orgMaxVecinos() {
    if (!orgEsEventoPueblo()) return ORG_MAX_VECINOS;
    var plazas = org.evento_ctx && org.evento_ctx.plazas_disponibles;
    plazas = Number(plazas);
    if (!isFinite(plazas) || plazas <= 0) {
      if (plazas === 0) return 0;
      plazas = ORG_MAX_VECINOS;
    }
    var nEleg = orgEventoElegiblesCount();
    if (nEleg !== null) return Math.min(plazas, nEleg);
    return plazas;
  }

  function orgApuntadosEvento() {
    var ids = (org.evento_ctx && org.evento_ctx.participantes_apuntados) || [];
    var out = {};
    ids.forEach(function (id) { if (id) out[id] = true; });
    return out;
  }

  // Mapa de elegibilidad para Eventos del Pueblo (contrato compartido con el backend).
  // Devuelve null si el contexto no es evento_pueblo o no trae elegibles.
  function orgEventoElegibles() {
    if (!orgEsEventoPueblo()) return null;
    var ctx = org.evento_ctx || {};
    var lista = ctx.elegibles || (ctx.preset_organizar && ctx.preset_organizar.elegibles) || null;
    if (!lista || !lista.length) return null;
    var m = {};
    lista.forEach(function (x) { if (x && x.id) m[String(x.id)] = x; });
    return m;
  }

  function orgEventoElegiblesCount() {
    var m = orgEventoElegibles();
    if (!m) return null;
    var n = 0;
    for (var k in m) { if (m.hasOwnProperty(k) && m[k].elegible) n++; }
    return n;
  }

  function abrirOrganizarEventoPueblo(ev) {
    var preset = (ev && ev.preset_organizar) ? ev.preset_organizar : ev;
    if (!preset || !preset.evento_pueblo_id) return;
    var evtId = preset.evento_pueblo_id;
    api('evento_pueblo.elegibles', { evento_pueblo_id: evtId }).then(function (r) {
      if (r && r.ok && r.vecinos) {
        preset.elegibles = r.vecinos;
      }
      abrirOrganizarConPreset(preset);
    }).catch(function () {
      abrirOrganizarConPreset(preset);
    });
  }

  function aplicarOrgModoEventoUi() {
    var capa = document.querySelector('[data-aht-screen="organizar"]');
    var tit = document.querySelector('[data-aht-screen="organizar"] .org-tit');
    var modoToggle = document.querySelector('[data-org-modo-toggle]');
    var seccDonde = document.querySelector('.org-step--donde');
    var seccCuando = document.querySelector('.org-step--cuando');
    var quienTit = document.querySelector('.org-step--quienes .org-step-tit');
    var contador = document.querySelector('[data-org-vecinos-contador]');
    var esEvt = orgEsEventoPueblo();
    if (capa) capa.classList.toggle('org-plan-papel--evento', esEvt);
    if (tit) {
      if (esEvt) {
        var nom = (org.evento_ctx && (org.evento_ctx.nombre_ui || org.evento_ctx.nombre)) || 'Evento del pueblo';
        tit.innerHTML = '<span class="org-evento-kicker">EVENTO DEL PUEBLO</span><span class="org-evento-nombre">' + esc(nom) + '</span>';
      } else {
        tit.textContent = 'Nuevo plan';
      }
    }
    if (modoToggle) modoToggle.hidden = esEvt;
    if (seccDonde) seccDonde.hidden = esEvt;
    if (seccCuando) seccCuando.hidden = esEvt;
    if (quienTit) quienTit.textContent = esEvt ? 'Apuntar vecinos' : '¿Quiénes van?';
    if (contador) {
      if (esEvt && org.evento_ctx && org.evento_ctx.aforo_total) {
        var act = (org.evento_ctx.participantes_apuntados || []).length;
        var selCount = orgSeleccionados().length;
        var total = org.evento_ctx.aforo_total;
        contador.textContent = (act + selCount) + ' / ' + total + ' plazas';
        contador.hidden = false;
      } else {
        contador.hidden = true;
        contador.textContent = '';
      }
    }
  }
  function orgIdsDesdePreset(preset) {
    const out = [];
    if (preset && preset.a) out.push(preset.a);
    if (preset && preset.b && preset.b !== preset.a) out.push(preset.b);
    if (preset && preset.c && preset.c !== preset.a && preset.c !== preset.b) out.push(preset.c);
    return out.slice(0, orgMaxVecinos());
  }

  function actualizarOrgPickerHint() {
    const hint = $('[data-org-picker-hint]');
    if (!hint) return;
    const n = orgSeleccionados().length;
    const max = orgMaxVecinos();
    hint.classList.remove('is-limit');
    if (orgEsEventoPueblo()) {
      if (max <= 0) {
        hint.textContent = 'Aforo completo.';
        hint.classList.add('is-limit');
        return;
      }
      if (!n) {
        hint.textContent = 'Selecciona vecinos para apuntarlos (hasta ' + max + ').';
        return;
      }
      if (n < max) hint.textContent = n + ' vecino' + (n === 1 ? '' : 's') + ' elegido' + (n === 1 ? '' : 's') + '. Puedes a\u00f1adir hasta ' + max + '.';
      else hint.textContent = max + ' vecinos elegidos (m\u00e1ximo para este evento).';
      return;
    }
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
    if (orgEsEventoPueblo()) return parts.length >= 1 && orgMaxVecinos() > 0;
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
    btn.disabled = orgProponiendo;
    btn.classList.toggle('is-disabled', !val.ok && !orgProponiendo);
    btn.classList.toggle('is-busy', orgProponiendo);
    btn.setAttribute('aria-disabled', (!val.ok || orgProponiendo) ? 'true' : 'false');
    var txt = btn.querySelector('.org-crear-txt');
    if (txt) {
      if (orgProponiendo) txt.textContent = orgEsEventoPueblo() ? ORG_BTN_BUSY_EVENTO : ORG_BTN_BUSY;
      else txt.textContent = orgEsEventoPueblo() ? ORG_BTN_LABEL_EVENTO : ORG_BTN_LABEL;
    }
  }

  function setOrgHorasHint(txt, show) {
    var hintEl = document.querySelector('[data-org-horas-hint]');
    if (!hintEl) return;
    if (show && txt) {
      hintEl.textContent = txt;
    } else {
      hintEl.textContent = ORG_HORAS_HINT_DEFAULT;
    }
    hintEl.hidden = false;
  }

  function mensajeErrorOrgApi(r, fallback) {
    fallback = fallback || 'No se ha podido organizar el plan. Int\u00e9ntalo de nuevo.';
    if (!r) return fallback;
    if (r.mensaje_ui) return r.mensaje_ui;
    var err = String(r.error || '');
    if (err === 'participantes_requeridos' || err === 'participantes_insuficientes' || err === 'individual_un_participante') {
      if (orgModo() === 'solo') return 'Elige a qui\u00e9n va el plan.';
      var pend = mensajeOrgParticipantesPendientes();
      return pend || 'Elige al menos dos vecinos.';
    }
    if (err === 'PARTICIPANTES_EXCESO') return 'Puedes organizar planes con hasta 2 vecinos.';
    if (err === 'AFORO_COMPLETO') return 'El evento ya está completo.';
    if (err === 'PARTICIPANTE_YA_APUNTADO') return 'Ese vecino ya está apuntado al evento.';
    if (err === 'EVENTO_PUEBLO_NO_ENCONTRADO') return 'No se ha encontrado ese evento del pueblo.';
    if (err === 'EVENTO_PUEBLO_CERRADO') return 'Ese evento ya no admite apuntados.';
    if (err === 'lugar_requerido' || err === 'LUGAR_NO_OPERATIVO') return 'Elige un lugar.';
    if (err === 'dia_requerido' || err === 'hora_requerida' || err === 'HORA_PASADA') return 'Elige cuándo quedar.';
    if (err === 'LIMITE_INTERVENCIONES') return 'Has alcanzado el l\u00edmite de intervenciones de hoy.';
    if (err === 'network_error' || err === 'respuesta_no_json' || err === 'excepcion') return fallback;
    return fallback;
  }

  function validarOrgForm() {
    const parts = orgSeleccionados();
    if (orgEsEventoPueblo()) {
      if (orgMaxVecinos() <= 0) return { ok: false, msg: 'Aforo completo.' };
      var minEvt = (org.evento_ctx && org.evento_ctx.participantes_min) || 3; if (parts.length < minEvt) return { ok: false, msg: 'Elige al menos ' + minEvt + ' vecinos para el evento.' };
      if (new Set(parts).size !== parts.length) return { ok: false, msg: 'Elige a personas distintas.' };
      if (parts.length > orgMaxVecinos()) return { ok: false, msg: 'Has superado las plazas disponibles del evento.' };
      var em = orgEventoElegibles();
      if (em) {
        for (var i = 0; i < parts.length; i++) {
          var e = em[String(parts[i])];
          if (!e || !e.elegible) {
            return { ok: false, msg: 'Alguno de los vecinos elegidos no est\u00e1 disponible a esa hora. Quita los que aparecen bloqueados.' };
          }
        }
      }
      return { ok: true };
    }
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
      hint.textContent = orgEsEventoPueblo() ? 'Has alcanzado las plazas disponibles del evento.' : ('Puedes organizar planes con hasta ' + ORG_MAX_VECINOS + ' vecinos.');
    }
    if (strip) {
      strip.classList.add('is-limit');
      window.setTimeout(function () { strip.classList.remove('is-limit'); }, 420);
    }
  }

  function toggleOrgPicker(id) {
    if (!id) return;
    if (orgEsEventoPueblo()) {
      var em = orgEventoElegibles();
      if (em && em[id] && !em[id].elegible) {
        feedbackOrgPickerLimite();
        return;
      }
    }
    limpiarOrgAviso();
    const sel = orgSeleccionados();
    const idx = sel.indexOf(id);
    if (idx >= 0) {
      org.sel = sel.filter(function (x) { return x !== id; });
    } else if (sel.length >= orgMaxVecinos()) {
      feedbackOrgPickerLimite();
      return;
    } else {
      org.sel = sel.concat([id]);
    }
    syncOrgTipoDesdeSeleccion();
    pintarOrgPicker();
    actualizarOrgPickerHint();
    actualizarOrgModoEstado();
    if (orgEsEventoPueblo()) aplicarOrgModoEventoUi();
    refreshTipos();
    refreshOrgHorasGrid();
    actualizarOrgCrearBtn();
  }


  function ajustarOrgDdMenu(box) {
    var menu = box.querySelector('.org-dd-menu');
    var trigger = box.querySelector('.org-dd-trigger');
    var capa = box.closest('[data-aht-screen="organizar"]');
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
    var capa = $('[data-aht-screen="organizar"]');
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


  function orgLugarTriggerHtml(lugId, label) {
    var d = destinoOperativoPorId(lugId) || {};
    var desc = orgLugarDesc(lugId);
    var horario = d.horario || '';
    var abierto = d.abierto_ahora;
    var estadoLinea = '';

    if (horario) {
      var m = String(horario).match(/(\d{1,2}:\d{2})\s*[-\u2013]\s*(\d{1,2}:\d{2})/);
      if (m) {
        var apertura = m[1];
        var cierre = m[2];
        var es24h = (apertura === '00:00' || apertura === '0:00') && (cierre === '23:59' || cierre === '24:00');
        if (es24h) {
          estadoLinea = 'Abierto 24 h';
        } else if (abierto === true) {
          estadoLinea = 'Abierto ahora Â· hasta ' + cierre;
        } else if (abierto === false) {
          estadoLinea = 'Cerrado Â· abre a las ' + apertura;
        } else {
          estadoLinea = 'Horario: ' + apertura + 'â€“' + cierre;
        }
      }
    }

    var estadoHtml = estadoLinea
      ? '<span class="org-lugar-card-estado">' + esc(estadoLinea) + '</span>'
      : '';

    return '<span class="org-lugar-card-trigger">' +
      '<span class="org-lugar-card-row">' +
      orgLugarThumbHtml(lugId) +
      '<span class="org-lugar-card-body">' +
      '<span class="org-lugar-card-nom">' + esc(label) + '</span>' +
      '<span class="org-lugar-card-desc">' + esc(desc) + '</span>' +
      estadoHtml +
      '</span>' +
      '<span class="org-dd-chev" aria-hidden="true"></span>' +
      '</span></span>';
  }

  function orgDdTriggerContent(kind, label, valStr) {
    var chev = '<span class="org-dd-chev" aria-hidden="true"></span>';
    if (kind === 'lugar') {
      if (valStr) return orgLugarTriggerHtml(valStr, label);
      return '<span class="org-lugar-card-trigger org-lugar-card-trigger--empty"><span class="org-dd-label">' + esc(label) + '</span>' + chev + '</span>';
    }
    var ico = kind === 'dia'
      ? '<span class="org-dd-ico org-dd-ico--dia" aria-hidden="true"></span>'
      : (kind === 'hora' ? '<span class="org-dd-ico org-dd-ico--hora" aria-hidden="true"></span>' : '');
    return ico + '<span class="org-dd-label">' + esc(label) + '</span>' + chev;
  }

  function orgDdOptContent(kind, opt) {
    var optVal = opt.value === null || opt.value === undefined ? '' : String(opt.value);
    var txt = esc(opt.label || optVal);
    if (kind === 'lugar') {
      return orgLugarThumbHtml(optVal) + '<span class="org-dd-opt-txt">' + txt + '</span>';
    }
    return txt;
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
    var label = 'Elegirâ';
    opts.forEach(function (opt) {
      var optVal = opt.value === null || opt.value === undefined ? '' : String(opt.value);
      if (valStr !== '' && optVal === valStr) label = opt.label || optVal;
    });
    box.innerHTML = '';
    box.className = 'org-dd' + (kind === 'lugar' ? ' org-dd--lugar' : (kind === 'dia' ? ' org-dd--dia' : (kind === 'hora' ? ' org-dd--hora' : '')));
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
    trigger.className = 'org-dd-trigger' + (kind === 'lugar' ? ' org-dd-trigger--lugar' : '');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = orgDdTriggerContent(kind, label, valStr);
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
        if (kind === 'lugar') li.className += ' org-dd-opt--lugar';
        if (kind === 'lugar') li.innerHTML = orgDdOptContent(kind, opt);
        else li.textContent = opt.label || optVal;
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
    var apuntados = orgApuntadosEvento();
    var eleg = orgEventoElegibles();
    ids.forEach(function (id) {
      const r = res[id] || {};
      const nom = (r.identidad_publica && r.identidad_publica.nombre) || nombreDe(id) || id;
      const ini = String(nom).charAt(0) || '?';
      const img = tokenDe(id);
      const yaApuntado = !!apuntados[id];
      var noElegible = false;
      var motivo = '';
      if (eleg && eleg[id] && !eleg[id].elegible) {
        noElegible = true;
        motivo = eleg[id].motivo || 'no_disponible';
      }
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'org-picker-celda' + (sel.indexOf(id) >= 0 ? ' is-on' : '') + (yaApuntado ? ' is-apuntado' : '') + (noElegible ? ' is-bloqueado' : '');
      var title = yaApuntado ? (nom + ' (ya apuntado/a)') : nom;
      if (noElegible) {
        title = nom + ' â€” no disponible para este evento';
        if (motivo === 'agenda_ocupada') title = nom + ' â€” tiene otro plan a esa hora';
        else if (motivo === 'no_residente') title = nom + ' â€” no es residente';
      }
      btn.title = title;
      btn.setAttribute('aria-label', title);
      if (yaApuntado || noElegible) btn.disabled = true;
      btn.innerHTML = '<span class="org-picker-stack">' +
        '<span class="org-picker-cara">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="org-picker-ini">' + esc(ini) + '</span>') +
        '</span>' +
        (sel.indexOf(id) >= 0 ? '<span class="org-picker-check" aria-hidden="true">?</span>' : '') +
        (yaApuntado ? '<span class="org-picker-apuntado" aria-hidden="true">?</span>' : '') +
        (noElegible ? '<span class="org-picker-bloq" aria-hidden="true">??</span>' : '') +
        '</span><span class="org-picker-nom">' + esc(nom) + '</span>';
      btn.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); if (!yaApuntado && !noElegible) toggleOrgPicker(id); });
      box.appendChild(btn);
    });
  }

  function fillSelect(sel, value, excludeId) {
    sel.innerHTML = '<option value="">â€”</option>';
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
    org.modo = (p.modo === 'evento' || p.modo === 'evento_pueblo' || p.evento_pueblo_id) ? 'evento_pueblo' : (p.modo || null);
    org.evento_pueblo_id = p.evento_pueblo_id || null;
    org.evento_ctx = (p.modo === 'evento' || p.modo === 'evento_pueblo' || p.evento_pueblo_id) ? p : null;
    org.sel = orgIdsDesdePreset(p);
    org.tipo = p.tipo || ((p.modo === 'evento' || p.modo === 'evento_pueblo') ? 'otro' : '');
    org.lugar = p.lugar || '';
    org.peticion_id = p.peticion_id || null;
    if (org.modo === 'evento_pueblo') {
      org.dia = p.dia || null;
      org.hora = (p.hora !== undefined && p.hora !== null) ? p.hora : 17;
    } else {
      org.dia = null;
      org.hora = 17;
    }
    syncOrgTipoDesdeSeleccion();
    orgBuscaTxt = '';
    var buscaInp = $('[data-org-busca]');
    if (buscaInp) buscaInp.value = '';
    actualizarOrgModoEstado();
  }

  function abrirOrganizarConPreset(preset) {
    cerrarMensajitosPop();
    resetOrgForm(preset);
    orgPresetNuevo = true;
    limpiarOrgAviso();
    setCapa('organizar');
    if ($('.play-root')) $('.play-root').removeAttribute('data-consulta');
  }

  function syncOrgModoUi() {
    aplicarOrgModoEventoUi();
    actualizarOrgPickerHint();
    actualizarOrgModoEstado();
  }
  async function refreshOrgHoras() {
    if (!org.dia && cacheEstado && cacheEstado.reloj) org.dia = cacheEstado.reloj.dia_pueblo;
    var parts = orgSeleccionados();
    if (!orgParticipantesListos()) {
      pintarOrgDropdown('hora', [{ value: '', label: 'â€”', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('', false);
      actualizarOrgCrearBtn();
      return;
    }
    if (!org.lugar) {
      pintarOrgDropdown('hora', [{ value: '', label: 'â€”', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('Elige un lugar para ver horarios.', true);
      actualizarOrgCrearBtn();
      return;
    }
    if (!org.dia) {
      pintarOrgDropdown('hora', [{ value: '', label: 'â€”', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('Elige cuándo quedar para ver horarios.', true);
      actualizarOrgCrearBtn();
      return;
    }
    var tipo = orgModo() === 'solo' ? 'individual' : (org.tipo || 'conocerse');
    try {
      var r = await api('agenda.slots_compatibles', {
        participantes: parts,
        tipo: tipo,
        lugar_id: org.lugar,
        desde_dia: org.dia,
        max_dias: 7,
        max_slots: 48
      }, 'GET');
      if (!r.ok) {
        pintarOrgDropdown('hora', [{ value: '', label: 'â€”', disabled: true }], '', null);
        org.hora = 0;
        setOrgHorasHint(mensajeErrorOrgApi(r, 'No hay horarios disponibles ahora.'), true);
        actualizarOrgCrearBtn();
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
        hintEl.hidden = true;
      }
    } catch (e) {
      pintarOrgDropdown('hora', [{ value: '', label: 'Sin huecos', disabled: true }], '', null);
      org.hora = 0;
      setOrgHorasHint('No se pudieron cargar los horarios.', true);
    }
    actualizarOrgCrearBtn();
  }

  async function cargarElegiblesEvento() {
    if (!orgEsEventoPueblo() || !org.evento_pueblo_id) return;
    try {
      var r = await api('evento_pueblo.elegibles', { evento_pueblo_id: org.evento_pueblo_id });
      if (r && r.ok && r.vecinos && org.evento_ctx) {
        org.evento_ctx.elegibles = r.vecinos;
        org.evento_ctx.aforo = r.aforo;
        org.evento_ctx.plazas_disponibles = r.plazas_disponibles;
        org.evento_ctx.participantes_min = r.participantes_min;
        pintarOrgPicker();
        actualizarOrgPickerHint();
        actualizarOrgCrearBtn();
      }
    } catch (e) {}
  }

  async function fillOrganizar() {
    syncOrgModoUi();
    if (orgEsEventoPueblo()) {
      pintarOrgPicker();
      actualizarOrgPickerHint();
      actualizarOrgModoEstado();
      actualizarOrgCrearBtn();
      cargarElegiblesEvento();
      return;
    }
    const lugares = destinosOperativos();
    const lugOpts = lugares.map(function (d) { return { value: d.id, label: d.nombre }; });
    if (org.lugar && !lugOpts.some(function (o) { return o.value === org.lugar; })) org.lugar = '';
    if (!org.lugar && lugOpts.length) org.lugar = lugOpts[0].value;
    renderOrgLugaresCards();
    renderOrgLugarInfo(org.lugar);
    const rv = (cacheEstado && cacheEstado.reloj_vista) || {};
    const dias = rv.proximos_dias || [];
    const diaOpts = dias.map(function (d) {
      return { value: d.dia_pueblo, label: d.etiqueta || ('dia ' + d.dia_pueblo) };
    });
    org.dia = org.dia || (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo);
    if (org.dia && !diaOpts.some(function (o) { return String(o.value) === String(org.dia); })) {
      org.dia = diaOpts.length ? diaOpts[0].value : null;
    }
    renderOrgDiasStrip();
    pintarOrgCaras();
    await refreshTipos();
    await refreshOrgHorasGrid();
    renderOrgEstado();
    actualizarOrgCrearBtn();
  }

  async function refreshTipos() {
    const sel = orgSeleccionados();
    if (orgModo() === 'solo') {
      if (!sel.length) { org.tipo = ''; return; }
      org.tipo = 'individual';
      return;
    }
    if (sel.length < 2) { org.tipo = ''; return; }
    org.tipo = '';
    const r = await api('encuentro.tipos_permitidos', {
      participantes: sel,
      residente_a: sel[0],
      residente_b: sel[1]
    }, 'GET');
    const ops = r.opciones || [];
    org.tipo = r.tipo_sugerido || (ops[0] && ops[0].id) || '';
  }

  function mostrarOrgAviso(txt) {
    var el = $('[data-org-aviso]');
    if (el) {
      el.textContent = txt;
      el.hidden = false;
      try { el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) { /* noop */ }
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

  function mensajeExitoProponer(r) {
    var partsUi = orgSeleccionados();
    var na = nombreDe(partsUi[0]);
    var nb = nombreDe(partsUi[1] || '');
    var lugUi = nombreLugarTitulo(org.lugar, org.lugar);
    var horaUi = String(org.hora).padStart(2, '0') + ':00';
    var msg = r.mensaje_ui;
    if (!msg) {
      if (orgModo() === 'solo') {
        msg = 'Plan organizado: ' + na + ' en ' + lugUi + ', d\u00eda ' + org.dia + ' a las ' + horaUi + '.';
      } else {
        msg = 'Plan organizado: ' + na + ' y ' + nb + ' en ' + lugUi + ', d\u00eda ' + org.dia + ' a las ' + horaUi + '.';
      }
    }
    if (r.hora_ajustada || (r.propuesta && r.propuesta.hora_ajustada)) {
      msg += ' (El motor ajust\u00f3 la hora al hueco disponible.)';
    }
    return msg;
  }

  function mensajeRechazoProponer(r) {
    if (r.mensaje_ui) return r.mensaje_ui;
    var partsUi = orgSeleccionados();
    var na = nombreDe(partsUi[0]);
    var nb = nombreDe(partsUi[1] || '');
    var lugUi = nombreLugarTitulo(org.lugar, org.lugar);
    if (orgModo() === 'solo') return 'Plan rechazado: ' + na + ' en ' + lugUi + '.';
    return 'Plan rechazado: ' + na + ' y ' + nb + ' en ' + lugUi + '.';
  }

window.AHT_PLAN_IMAGES = {
  "aceptado": "data:image/jpeg;base64,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LrM63N2GWxU55484+g/2fekryfLHc1jSjRTq1Xoipo2j3OtzBvmiswfnlP8AEPRfU+/QV6NbQR2tvHBAgSKNQqqOwp6IqIqIoVVGAAMAClrspUlTXmeVi8ZLEvslsgooorU4wooooAKKKKACiiigAooooAo6lpNlqQH2yBXYDAfow+hHNYU3gu2Ofs93cR+zYYf0rq6KiVKEt0dFLF1qStCWh59qHhXUrNDJayJdqOqqNr4+h4P51ixXJ3EOCkiHBBGCD6EV63VDUdHsNROby1jkf+/0b8xzXPPCreDPRo5q9q6v5rf/ACPP/tIYffGfSk84dSfyrprvwXZuv+iTzwN7neP15/WsebwfqsZxFLayr2O4qfyxWEqNRdDup4rDVNpW9dP+AZs13tB2kZqsLx9wPHuPWt228GahKw+1XFvDH32Zdv6CugtfCOkQw7JIGnfvJI5z+mMURoVJeQ6mOwtJWvzehxK3KkglsGpWuVI65ro7nwPau+ba7uIV/ukBwPz5ot/BFurD7Re3Eq+igJn+dP2FTaxLx2EavzfgctE89zP5VrHJNJ1KIMkVpweG9ZuWBdI4F9ZZMn8hmu70+wttPg8qzhWJO+Op9yepqzW8cKvtM4aubO9qUUl57nK6Z4PhikWXUpjdMOkYXan49zXUqoVQqgBQMADtS0VvCEYK0UebWxFSu71HcKKKKsxCiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigD//Z",
  "rechazado": "data:image/jpeg;base64,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",
  "duda": "data:image/jpeg;base64,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",
  "pendiente": "data:image/jpeg;base64,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",
  "fallido": "data:image/jpeg;base64,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",
};

  function mostrarResultadoPlan(r) {
    var partsUi = orgSeleccionados();
    var idA = partsUi[0] || '';
    var idB = partsUi[1] || '';
    var na = nombreDe(idA);
    var nb = nombreDe(idB);
    var contraEl = $('[data-pr-contra]');
    var contraTxtEl = $('[data-pr-contra-text]');
    var mensajitoEl = $('[data-pr-mensajito]');
    var mensajitoTxtEl = $('[data-pr-mensajito-text]');
    var avatar1 = $('[data-pr-avatar-1]');
    var avatar2 = $('[data-pr-avatar-2]');
    var name1 = $('[data-pr-avatar-name-1]');
    var name2 = $('[data-pr-avatar-name-2]');
    var stateImg = $('[data-pr-state-img]');
    var respIcon1 = $('[data-pr-resp-icon-1]');
    var respText1 = $('[data-pr-resp-text-1]');
    var respIcon2 = $('[data-pr-resp-icon-2]');
    var respText2 = $('[data-pr-resp-text-2]');
    var vignette1 = $('[data-pr-panel-1]');
    var vignette2 = $('[data-pr-panel-2]');
    var resp1 = $('[data-pr-resp-1]');
    var resp2 = $('[data-pr-resp-2]');
    var messageEl = $('[data-pr-message]');

    var hasTwo = idA && idB;
    if (vignette2) vignette2.hidden = !hasTwo;
    if (resp2) resp2.hidden = !hasTwo;

    function setAvatar(el, id, nombre) {
      if (!el) return;
      el.style.cssText = 'width:90px;height:90px;border-radius:50%;overflow:hidden;border:3px solid #D8D1DE;background:#FCFBFE;display:flex;align-items:center;justify-content:center;flex-shrink:0;';
      if (!id) { el.innerHTML = ''; return; }
      var img = tokenDe(id) || retratoDe(id);
      if (img) {
        el.innerHTML = '<img src="' + esc(img) + '" alt="' + esc(nombre) + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"/>';
      } else {
        el.innerHTML = '<span class="cara-ini" style="font:700 1.6rem/1 Nunito,sans-serif;color:#75634F;">' + esc((nombre || '?')[0]) + '</span>';
      }
    }

    setAvatar(avatar1, idA, na);
    setAvatar(avatar2, idB, nb);
    if (name1) name1.textContent = na;
    if (name2) name2.textContent = nb;

    function getDecision(rid) {
      if (!r.propuesta || !r.propuesta.reacciones) return null;
      for (var i = 0; i < r.propuesta.reacciones.length; i++) {
        var reac = r.propuesta.reacciones[i];
        if (reac.residente_id === rid) return reac.decision || null;
      }
      return null;
    }

    function setVignetteClass(el, decision) {
      if (!el) return;
      el.className = el.className.replace(/pr-vignette--(acepta|rechaza|duda)/g, '').trim();
      if (decision === 'acepta') {
        el.classList.add('pr-vignette--acepta');
        el.style.borderColor = '#5aaf4a';
        el.style.background = 'rgba(110,190,80,.08)';
      } else if (decision === 'rechaza') {
        el.classList.add('pr-vignette--rechaza');
        el.style.borderColor = '#d45050';
        el.style.background = 'rgba(220,100,100,.07)';
      } else if (decision === 'duda') {
        el.classList.add('pr-vignette--duda');
        el.style.borderColor = '#c8a830';
        el.style.background = 'rgba(210,180,60,.07)';
      }
    }

    function setResponse(iconEl, textEl, wrapEl, decision) {
      if (!iconEl || !textEl) return;
      if (!decision) {
        if (wrapEl) wrapEl.hidden = true;
        return;
      }
      wrapEl.hidden = false;
      if (decision === 'acepta') {
        iconEl.textContent = '\uD83D\uDC9A';
        textEl.textContent = 'Acepta';
      } else if (decision === 'rechaza') {
        iconEl.textContent = '\uD83D\uDC94';
        textEl.textContent = 'Rechaza';
      } else {
        iconEl.textContent = '\uD83D\uDE10';
        textEl.textContent = 'Duda';
      }
    }

    function getEstadoImg(aceptada, rechazada) {
      var imgs = window.AHT_PLAN_IMAGES || {};
      if (aceptada && !rechazada) return imgs.aceptado || '';
      if (rechazada) return imgs.rechazado || '';
      return imgs.fallido || '';
    }

    function forceComicLayout() {
      var scene = document.querySelector('.pr-scene');
      if (scene) scene.style.cssText = 'display:grid;grid-template-columns:1fr auto 1fr;gap:.35rem;width:100%;max-width:420px;align-items:start;';
      [vignette1, vignette2].forEach(function(v) {
        if (v) v.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:.25rem;padding:.45rem .3rem .4rem;border-radius:18px 14px 20px 12px/14px 18px 10px 16px;background:#FCFBFE;border:3px solid #D8D1DE;box-shadow:3px 4px 0 rgba(48,40,58,.07);';
      });
      if (vignette1) vignette1.style.transform = 'rotate(-2deg)';
      if (vignette2) vignette2.style.transform = 'rotate(2deg)';
      var center = document.querySelector('.pr-center');
      if (center) center.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:.3rem;padding:.2rem 0;align-self:center;';
      if (stateImg) stateImg.style.cssText = 'width:80px;height:80px;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,.1));';
      if (messageEl) messageEl.style.cssText = 'font:700 .78rem/1.3 Nunito,sans-serif;color:#33261E;text-align:center;margin:0;max-width:120px;';
    }

    forceComicLayout();

    var decA = idA ? getDecision(idA) : null;
    var decB = idB ? getDecision(idB) : null;

    if (r.ok && !r.rechazada) {
      if (stateImg) stateImg.src = getEstadoImg(true, false);
      var dA = decA || 'acepta';
      var dB = decB || 'acepta';
      setVignetteClass(vignette1, dA);
      setVignetteClass(vignette2, dB);
      setResponse(respIcon1, respText1, resp1, dA);
      setResponse(respIcon2, respText2, resp2, dB);
      if (messageEl) messageEl.textContent = nombreLugarTitulo(org.lugar, org.lugar) + ' \u2014 D\u00EDa ' + org.dia + ' a las ' + String(org.hora).padStart(2, '0') + ':00';
      if (contraEl) contraEl.hidden = true;
    } else if (r.ok && r.rechazada) {
      if (stateImg) stateImg.src = getEstadoImg(false, true);
      setVignetteClass(vignette1, decA);
      setVignetteClass(vignette2, decB);
      setResponse(respIcon1, respText1, resp1, decA);
      setResponse(respIcon2, respText2, resp2, decB);
      if (messageEl) messageEl.textContent = r.mensaje_ui || 'Esta vez no ha cuajado el plan.';
      if (r.contrapropuesta && r.contrapropuesta.dia && r.contrapropuesta.hora) {
        var contraHora = String(r.contrapropuesta.hora).padStart(2, '0') + ':00';
        if (contraEl) contraEl.hidden = false;
        if (contraTxtEl) contraTxtEl.textContent = 'Pero quiz\u00E1s \u2014 D\u00EDa ' + r.contrapropuesta.dia + ' a las ' + contraHora;
      } else {
        if (contraEl) contraEl.hidden = true;
      }
    } else {
      if (stateImg) stateImg.src = getEstadoImg(false, false);
      setVignetteClass(vignette1, decA);
      setVignetteClass(vignette2, decB);
      setResponse(respIcon1, respText1, resp1, decA);
      setResponse(respIcon2, respText2, resp2, decB);
      if (messageEl) messageEl.textContent = r.mensaje_ui || 'Ha ocurrido un problemilla t\u00E9cnico. No es un rechazo social \u2014 int\u00E9ntalo de nuevo.';
      if (contraEl) contraEl.hidden = true;
    }
    if (r.nuevo_mensajito && mensajitoEl && mensajitoTxtEl) {
      mensajitoEl.hidden = false;
      mensajitoTxtEl.textContent = r.mensajito_aviso_ui || 'Tienes un nuevo Mensajito.';
    } else if (mensajitoEl) {
      mensajitoEl.hidden = true;
    }
    var prBody = document.querySelector('.pr-body');
    if (prBody && !prBody.querySelector('.pr-btn-volver')) {
      if (r.rechazada) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pr-btn-volver';
        btn.setAttribute('data-pr-close', '');
        btn.style.cssText = 'margin-top:.5rem;padding:.55rem 1.4rem;border-radius:12px;border:2px solid #33261E;background:#E8DFF5;color:#7C6BAE;font:700 .88rem/1 Nunito,sans-serif;cursor:pointer;box-shadow:2px 3px 0 rgba(51,38,30,.18);';
        btn.textContent = 'Volver a planes';
        btn.addEventListener('click', function() { cerrarResultadoPlan(); setCapa('organizar'); });
        prBody.appendChild(btn);
      } else {
        setTimeout(function() { cerrarResultadoPlan(); }, 2500);
      }
    }
    injectDoodlesPlanResultado();
  }

  function injectDoodlesPlanResultado() {
    var header = document.querySelector('.aht-screen[data-aht-screen="plan-resultado"] .aht-frame-header');
    if (!header || header.querySelector('.aht-doodle')) return;
    var svgs = window.AHT_DOODLE_SVGS || {};
    var positions = [
      { id: 'h', cls: 'd-fl', sz: 'd-lg' },
      { id: 's', cls: 'd-tl', sz: 'd-md' },
      { id: 'x', cls: 'd-bl', sz: 'd-sm' },
      { id: 'd', cls: 'd-tr', sz: 'd-md' },
      { id: 'x', cls: 'd-rm', sz: 'd-sm' },
      { id: 'h', cls: 'd-fr', sz: 'd-lg' }
    ];
    positions.forEach(function(p, i) {
      if (!svgs[p.id]) return;
      var span = document.createElement('span');
      span.className = 'aht-doodle ' + p.cls + ' ' + p.sz;
      span.setAttribute('aria-hidden', 'true');
      span.innerHTML = svgs[p.id];
      header.appendChild(span);
      requestAnimationFrame(function() {
        setTimeout(function() { span.classList.add('injected'); }, 50 * i);
      });
    });
  }

  function cerrarResultadoPlan() {
    if (window.AHTScreenManager) window.AHTScreenManager.closeAll();
    setCapa('');
  }

  async function aplicarRespuestaProponer(r) {
    if (!r || typeof r !== 'object') {
      mostrarResultadoPlan({ ok: false });
      setCapa('plan-resultado');
      return;
    }
    if (r.ok) {
      if (r.rechazada) {
        mostrarResultadoPlan(r);
        if (r.contrapropuesta && r.contrapropuesta.dia && r.contrapropuesta.hora) {
          org.dia = r.contrapropuesta.dia;
          org.hora = r.contrapropuesta.hora;
          await fillOrganizar();
        }
        setCapa('plan-resultado');
        await refresh();
        return;
      }
      mostrarResultadoPlan(r);
      if (r.nuevo_mensajito) {
        $('.play-root').setAttribute('data-importante', '1');
      }
      setCapa('plan-resultado');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);
      quizaMostrarTutFinale();
      return;
    }
    mostrarResultadoPlan(r);
    setCapa('plan-resultado');
    await refresh();
  }

  async function proponer() {
    if (orgProponiendo) return;
    limpiarOrgAviso();
    var val = validarOrgForm();
    if (!val.ok) {
      mostrarOrgAviso(val.msg);
      return;
    }
    const parts = orgSeleccionados();
    orgProponiendo = true;
    actualizarOrgCrearBtn();
    try {
      var r;
      if (orgEsEventoPueblo()) {
        r = await api('evento_pueblo.apuntar', {
          evento_pueblo_id: org.evento_pueblo_id,
          participantes: parts
        });
        if (r && r.ok) {
          var msgEvt = r.mensaje_ui || 'Vecinos apuntados al evento.';
          mostrarOrgAviso(msgEvt);
          toast(msgEvt);
          setCapa('');
          await refresh();
          return;
        }
        mostrarOrgAviso(mensajeErrorOrgApi(r, 'No se han podido apuntar los vecinos.'));
        await refresh();
        return;
      }
      const payload = {
        participantes: parts,
        dia: org.dia,
        hora: org.hora,
        tipo: orgModo() === 'solo' ? 'individual' : (org.tipo || 'conocerse'),
        lugar: org.lugar,
        modo: orgModo()
      };
      if (org.peticion_id) payload.peticion_id = org.peticion_id;
      r = await api('encuentro.proponer', payload);
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
      await aplicarRespuestaProponer(r);
    } catch (e) {
      mostrarOrgAviso('No se ha podido organizar el plan. Int\u00e9ntalo de nuevo.');
      try { console.error('[AHT plan] excepcion', e); } catch (x) {}
    } finally {
      orgProponiendo = false;
      actualizarOrgCrearBtn();
    }
  }
  function persistPartidaId(id) {
    if (!id) return;
    partidaId = id;
    try { localStorage.setItem(storageKey(), id); } catch (e) {}
    try { localStorage.removeItem('aht_partida_id'); } catch (e) {}
  }
  async function adoptSqlPartidaIfAny(opts) {
    opts = opts || {};
    const list = await api('partida.listar', {}, 'GET');
    if (!list.ok || !Array.isArray(list.partidas) || list.partidas.length === 0) return false;
    const canonicalId = list.partidas[0] && list.partidas[0].partida_id;
    if (!canonicalId) return false;
    if (!opts.forceRebind && partidaId) {
      const probe = await api('partida.estado', {}, 'GET');
      if (probe.ok) return true;
    }
    persistPartidaId(canonicalId);
    if (opts.forceRebind) {
      const probe = await api('partida.estado', {}, 'GET');
      if (!probe.ok) {
        try { localStorage.removeItem(storageKey()); } catch (e) {}
        partidaId = null;
        return false;
      }
    }
    return true;
  }

  async function recuperarPartidaIdPerdida() {
    if (!partidaId) {
      return adoptSqlPartidaIfAny({ forceRebind: true });
    }
    const probe = await api('partida.estado', {}, 'GET');
    if (probe.ok) return true;
    const err = String(probe.error || '').toUpperCase();
    if (err !== 'PARTIDA_NO_ENCONTRADA' && err !== 'SAVE_CORRUPTO') return false;
    if (await adoptSqlPartidaIfAny({ forceRebind: true })) {
      const retry = await api('partida.estado', {}, 'GET');
      if (retry.ok) return true;
    }
    try { localStorage.removeItem(storageKey()); } catch (e) {}
    partidaId = null;
    return adoptSqlPartidaIfAny({ forceRebind: true });
  }
  async function ensurePartida() {
    if (partidaId) {
      const probe = await api('partida.estado', {}, 'GET');
      if (probe.ok) return true;
      try { localStorage.removeItem(storageKey()); } catch (e) {}
      partidaId = null;
    }
    if (await adoptSqlPartidaIfAny({ forceRebind: true })) {
      const retry = await api('partida.estado', {}, 'GET');
      if (retry.ok) return true;
      try { localStorage.removeItem(storageKey()); } catch (e) {}
      partidaId = null;
    }
    const r = await api('partida.nueva', configNueva(true));
    if (r.ok && r.partida_id) persistPartidaId(r.partida_id);
    return !!r.ok;
  }
  async function refresh() {
    if (_partidaSwitchBusy) return;
    const popMensajitosAbierto = mensajitosPopAbierto;
    let paquete = await api('partida.refresh', {}, 'GET');
    if (!paquete.ok && partidaId) {
      const errRefresh = String(paquete.error || '').toUpperCase();
      const partidaPerdida = errRefresh === 'PARTIDA_NO_ENCONTRADA' || errRefresh === 'SAVE_CORRUPTO';
      if (partidaPerdida) {
        try { localStorage.removeItem(storageKey()); } catch (e) {}
        partidaId = null;
        if (await adoptSqlPartidaIfAny({ forceRebind: true })) {
          paquete = await api('partida.refresh', {}, 'GET');
        }
        if (!paquete.ok) {
          try { localStorage.removeItem(storageKey()); } catch (e) {}
          partidaId = null;
          if (await ensurePartida()) {
            paquete = await api('partida.refresh', {}, 'GET');
          }
        }
      }
    }
    if (!paquete.ok) {
      toast('Partida no disponible. Recarga la p\u00e1gina.');
      stopSyncClock();
      return;
    }
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
    pintarLlegadaCelebracionSiToca();
    procesarCelebraciones(paquete.historia);
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

  function limpiarCachesPartidaUi() {
    colaCelebraciones = [];
    celebracionHitoActual = '';
    celebracionesConsumidas.clear();
    cacheEstado = null;
    cacheInsp = null;
    cachePueblo = null;
    cacheBuzon = [];
    vidaCorazonPctPrev = null;
    vidaCorazonReady = false;
    org = { tipo: '', sel: [], lugar: '', dia: null, hora: 17 };
    playtestLogClient.entries = [];
    setCapa('');
  }

  async function nuevaPartidaLimpiaInterna() {
    try { localStorage.removeItem(tutIntroKey()); } catch (e) {}
    localStorage.removeItem(storageKey());
    const oldPartidaId = partidaId;
    partidaId = null;
    limpiarCachesPartidaUi();
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

  async function reiniciarPartidaActual() {
    if (_partidaSwitchBusy) return;
    _partidaSwitchBusy = true;
    stopSyncClock();
    try {
      if (!(await recuperarPartidaIdPerdida()) || !partidaId) {
        await nuevaPartidaLimpiaInterna();
        return;
      }
      try { localStorage.removeItem(tutIntroKey()); } catch (e) {}
      limpiarCachesPartidaUi();
      const r = await api('partida.reiniciar', configNueva(true));
      if (r.ok) {
        toast('Partida reiniciada.');
        quizaMostrarTutIntro();
        await refresh();
      } else {
        toast(r.mensaje_ui || 'No se pudo reiniciar la partida.');
      }
    } finally {
      _partidaSwitchBusy = false;
      startSyncClock();
    }
  }

  async function nuevaPartidaLimpia() {
    if (_partidaSwitchBusy) return;
    _partidaSwitchBusy = true;
    stopSyncClock();
    try {
      await nuevaPartidaLimpiaInterna();
    } finally {
      _partidaSwitchBusy = false;
      startSyncClock();
    }
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
    $$('[data-debug-copy]').forEach(function (btn) {
      if (btn._ahtDebugCopyBound) return;
      btn._ahtDebugCopyBound = true;
      btn.addEventListener('click', function () { copiarDebugExport(false); });
    });
    $$('[data-debug-copy-estado]').forEach(function (btn) {
      if (btn._ahtDebugCopyEstadoBound) return;
      btn._ahtDebugCopyEstadoBound = true;
      btn.addEventListener('click', function () { copiarDebugExport(true); });
    });
    $$('[data-debug-download]').forEach(function (btn) {
      if (btn._ahtDebugDownloadBound) return;
      btn._ahtDebugDownloadBound = true;
      btn.addEventListener('click', function () { descargarDebugExport(false); });
    });
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
    el.textContent = lineas.map(function (l) { return 'Â· ' + (l.texto || l.tipo || ''); }).join('\n');
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
    if (!pasarRatoBtns().length || pasarRatoEnCurso) return;
    pasarRatoEnCurso = true;
    setPasarRatoBusy(true, 'A ver qué se cueceâ');
    const relojAntes = relojAbsDesdeEstado();
    try {
      if (!(await recuperarPartidaIdPerdida())) {
        toast('No encuentro tu partida guardada. Recarga la página o inicia sesión en Intocables.');
        return;
      }
      const perdida = !!(cacheEstado && cacheEstado.partida_perdida) ||
        !!(cacheEstado && cacheEstado.vida_pueblo && cacheEstado.vida_pueblo.game_over_activo);
      if (perdida) {
        toast('La partida ha terminado. Empieza otra con Â«Nueva partidaÂ».');
        return;
      }
      const h = horaActualEstado();
      const nocturno = esHoraNoche(h);
      const horas = nocturno ? Math.max(1, (HORA_DIA_DESDE - h + 24) % 24) : 1;
      const r = await avanzarHoras(horas);
      const relojDespues = relojAbsDesdeEstado();
      const avanzo = relojAbsAvanzo(relojAntes, relojDespues);
      if (!relojAvanceRespuestaOk(r) && !avanzo) {
        toast(r.mensaje_ui || r.mensaje || 'Ahora no se puede pasar el rato.');
      }
    } finally {
      pasarRatoEnCurso = false;
      setPasarRatoBusy(false);
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
  (function bindProxplanesMovil() {
    document.querySelectorAll('[data-proxplanes-track]').forEach(function (ppTrack) {
      if (ppTrack._ahtPpMovScroll) return;
      ppTrack._ahtPpMovScroll = true;
      ppTrack.addEventListener('scroll', function () {
        const b = ppTrack.closest('[data-proxplanes-block]');
        if (b) renderProxplanesNavFor(b);
      }, { passive: true });
    });
  })();
  (function bindPasarRatoDelegacion() {
    const root = document.querySelector('.inicio-stage') || document.querySelector('.game-shell') || document.body;
    if (!root || root._ahtPasarDelegBound) return;
    root._ahtPasarDelegBound = true;
    root.addEventListener('click', function (ev) {
      if (!ev.target.closest('[data-pasar-rato]')) return;
      ev.preventDefault();
      pasarElRato();
    });
  })();
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
    const animoCloseBtn = ev.target.closest('[data-animo-close]');
    if (animoCloseBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      cerrarAnimoOverlay();
      return;
    }
    const animoVolverBtn = ev.target.closest('[data-animo-volver]');
    if (animoVolverBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      cerrarAnimoOverlay();
      return;
    }
    const relCloseBtn = ev.target.closest('[data-ficha-rel-close]');
    if (relCloseBtn) {
      const rootRel = $('.play-root');
      if (rootRel && rootRel.getAttribute('data-capa') === 'ficha_relaciones') {
        ev.preventDefault();
        ev.stopPropagation();
        cerrarFichaRelOverlay();
        return;
      }
    }
    const relVolverBtn = ev.target.closest('[data-frel-volver]');
    if (relVolverBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      cerrarFichaRelOverlay();
      return;
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
    const histCelebClose = ev.target.closest('[data-historia-celebracion-close]');
    if (histCelebClose) {
      ev.preventDefault();
      ev.stopPropagation();
      celebracionClose(celebracionHitoActual);
      return;
    }
    const histCelebAlbum = ev.target.closest('[data-historia-celebracion-album]');
    if (histCelebAlbum) {
      ev.preventDefault();
      ev.stopPropagation();
      celebracionIrAlbum();
      return;
    }
    const histDetClose = ev.target.closest('[data-historia-detalle-close]');
    if (histDetClose) {
      ev.preventDefault();
      historiaDetalleClose();
      return;
    }
    const pol = ev.target.closest('.historia-polaroid:not(.historia-polaroid--bloqueada)');
    if (pol && uiRootFrom(pol)) {
      ev.preventDefault();
      const hitoId = pol.getAttribute('data-hito-id');
      if (hitoId) historiaDetalleAbrir(hitoId);
      return;
    }
    const t = ev.target.closest('[data-close], .velo');
    if (t && uiRootFrom(t)) {
      cerrarUiCompleto();
      return;
    }
    const tutAjustes = ev.target.closest('[data-ajustes-tut]');
    if (tutAjustes && uiRootFrom(tutAjustes)) {
      if (!tieneTutorialV3()) return;
      setCapa('');
      abrirTutIntro(true);
      return;
    }
    const copyDiagAjustes = ev.target.closest('[data-ajustes-debug-copy]');
    if (copyDiagAjustes && uiRootFrom(copyDiagAjustes)) {
      ev.preventDefault();
      copiarDiagnosticoAht();
      return;
    }
    const dlDiagAjustes = ev.target.closest('[data-ajustes-debug-download]');
    if (dlDiagAjustes && uiRootFrom(dlDiagAjustes)) {
      ev.preventDefault();
      descargarDiagnosticoAht();
      return;
    }
    const reiniciarAjustes = ev.target.closest('[data-ajustes-reiniciar]');
    if (reiniciarAjustes && uiRootFrom(reiniciarAjustes)) {
      ev.preventDefault();
      reiniciarPartidaActual();
      return;
    }
    const celestNecesitan = ev.target.closest('[data-celestine-necesitan]');
    if (celestNecesitan && uiRootFrom(celestNecesitan)) {
      ev.preventDefault();
      ev.stopPropagation();
      necgFiltroInicial = 'necesitan';
      setCapa('vecinos');
      setVecTab('cuidados');
      return;
    }
    const necgRes = ev.target.closest('[data-necg-res]');
    if (necgRes && uiRootFrom(necgRes)) {
      var rid = necgRes.getAttribute('data-necg-res');
      if (rid) {
        setCapa('ficha');
        abrirFicha(rid);
      }
      return;
    }
    const open = ev.target.closest('[data-open]');
    if (open && uiRootFrom(open)) {
      // BRIDGE V4: si screen-manager ya manejó la apertura en capture phase,
      // NO duplicar setCapa/scroll/history.
      if (ev.__ahtV4) return;
      const name = open.getAttribute('data-open');
      cerrarMensajitosPop();
      if (name === 'relaciones') {
        setCapa('vecinos');
        setVecTab('relaciones');
        $('.play-root').removeAttribute('data-consulta');
        syncScrollLock();
        return;
      }
      if (name === 'necesidades_global') {
        setCapa('vecinos');
        setVecTab('cuidados');
        $('.play-root').removeAttribute('data-consulta');
        syncScrollLock();
        return;
      }
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
      if (name === 'buzon') {
        var bList = $('[data-buzon-list]');
        if (bList) bList.setAttribute('data-buzon-filtro', 'nuevos');
        document.querySelectorAll('[data-buzon-tab]').forEach(function (t) {
          t.classList.toggle('is-active', t.getAttribute('data-buzon-tab') === 'nuevos');
          t.setAttribute('aria-selected', t.getAttribute('data-buzon-tab') === 'nuevos' ? 'true' : 'false');
        });
        renderBuzon(cacheBuzon);
      }
      return;
    }
    const invEntregar = ev.target.closest('[data-inv-entregar]');
    if (invEntregar && uiRootFrom(invEntregar)) {
      ev.preventDefault();
      entregarRegalo();
      return;
    }
    const invCancelar = ev.target.closest('[data-inv-cancelar]');
    if (invCancelar && uiRootFrom(invCancelar)) {
      ev.preventDefault();
      renderInventario();
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
    const planesUnifMore = ev.target.closest('[data-planes-unif-more]');
    if (planesUnifMore) {
      ev.preventDefault();
      ev.stopPropagation();
      const puBlock = planesUnifMore.closest('[data-planes-unif-block]');
      if (puBlock) planesUnifScrollNext(puBlock);
      return;
    }
    const encMovPrev = ev.target.closest('[data-enc-mov-prev]');
    if (encMovPrev) {
      ev.preventDefault();
      ev.stopPropagation();
      const encBlock = encMovPrev.closest('[data-encursos-block]');
      if (encBlock) {
        const idx = typeof encBlock._encMovIndice === 'number' ? encBlock._encMovIndice : 0;
        encMovIrA(encBlock, idx - 1);
      }
      return;
    }
    const encMovNext = ev.target.closest('[data-enc-mov-next]');
    if (encMovNext) {
      ev.preventDefault();
      ev.stopPropagation();
      const encBlock = encMovNext.closest('[data-encursos-block]');
      if (encBlock) {
        const idx = typeof encBlock._encMovIndice === 'number' ? encBlock._encMovIndice : 0;
        encMovIrA(encBlock, idx + 1);
      }
      return;
    }
    const ppMovPrev = ev.target.closest('[data-pp-mov-prev]');
    if (ppMovPrev) {
      ev.preventDefault();
      ev.stopPropagation();
      const ppBlock = ppMovPrev.closest('[data-proxplanes-block]');
      if (ppBlock) {
        const idx = typeof ppBlock._ppMovIndice === 'number' ? ppBlock._ppMovIndice : 0;
        ppMovIrA(ppBlock, idx - 1);
      }
      return;
    }
    const ppMovNext = ev.target.closest('[data-pp-mov-next]');
    if (ppMovNext) {
      ev.preventDefault();
      ev.stopPropagation();
      const ppBlock = ppMovNext.closest('[data-proxplanes-block]');
      if (ppBlock) {
        const idx = typeof ppBlock._ppMovIndice === 'number' ? ppBlock._ppMovIndice : 0;
        ppMovIrA(ppBlock, idx + 1);
      }
      return;
    }
    const encMentesOpen = ev.target.closest('[data-enc-mentes-open]');
    if (encMentesOpen) {
      ev.preventDefault();
      ev.stopPropagation();
      var encIdMentes = encMentesOpen.getAttribute('data-enc-id') || '';
      if (!encIdMentes) {
        var movCardM = encMentesOpen.closest('[data-enc-mov-card]');
        if (movCardM) encIdMentes = movCardM.getAttribute('data-enc-id') || '';
      }
      if (encIdMentes) montarMentesModal(encIdMentes);
      return;
    }
    const encIntBtn = ev.target.closest('[data-enc-int-accion]');
    if (encIntBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      var wrap = encIntBtn.closest('[data-enc-int]');
      if (!wrap || wrap.classList.contains('is-busy')) return;
      var encId = wrap.getAttribute('data-enc-id');
      var acc = encIntBtn.getAttribute('data-enc-int-accion');
      var objetivoId = wrap.getAttribute('data-enc-int-objetivo') || '';
      var extra = { objetivo: objetivoId || undefined };
      if (acc === 'hobby') {
        extra.hobby_id = encIntBtn.getAttribute('data-hobby-id');
        extra.residente_id = encIntBtn.getAttribute('data-residente-id');
        if (!extra.hobby_id) {
          var encH = encuentroPorId(encId);
          var ivH = encH ? intervencionVistaDe(encH, cacheEstado) : null;
          if (ivH) {
            var idsH = encH.participantes || [];
            var actorH = ivH.actor_actual || '';
            var receptorH = '';
            for (var riH = 0; riH < idsH.length; riH++) {
              if (idsH[riH] !== actorH) { receptorH = idsH[riH]; break; }
            }
            if (receptorH) {
              wrap.setAttribute('data-enc-int-objetivo', receptorH);
              wrap.classList.add('is-busy');
              pintarTemasIntervencion(wrap, ivH, receptorH);
              var panelT = wrap.querySelector('[data-temas-panel]');
              if (panelT) panelT.hidden = false;
              wrap.classList.remove('is-busy');
            }
          }
          return;
        }
      }
      wrap.classList.add('is-busy');
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
      var encRow = (cacheEstado && cacheEstado.encuentros_en_curso || []).find(function (e) {
        return e && String(e.id) === String(wrapP.getAttribute('data-enc-id'));
      }) || (cacheEstado && cacheEstado.encuentro_en_curso && String(cacheEstado.encuentro_en_curso.id) === String(wrapP.getAttribute('data-enc-id')) ? cacheEstado.encuentro_en_curso : null);
      var ivP = encRow ? intervencionVistaDe(encRow, cacheEstado) : null;
      if (ivP) pintarTemasIntervencion(wrapP, ivP, personaId);
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
      var stepRE = wrapV.querySelector('[data-enc-int-paso="resultado"]');
      if (stepRE) stepRE.hidden = true;
      var temasBox = wrapV.querySelector('.enc-int-temas');
      if (temasBox) temasBox.hidden = true;
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
  const fichaRelVolver = $('[data-frel-volver]');
  if (fichaRelVolver) fichaRelVolver.addEventListener('click', cerrarFichaRelOverlay);



  const animoClose = $('[data-animo-close]');
  if (animoClose) animoClose.addEventListener('click', cerrarAnimoOverlay);
  const animoVolver = $('[data-animo-volver]');
  if (animoVolver) animoVolver.addEventListener('click', cerrarAnimoOverlay);

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
    const capa = document.querySelector('[data-aht-screen="ficha"]');
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
    fichaVolver.addEventListener('click', function () {
    if (fichaOverlayActiva) { cerrarFichaOverlay(); return; }
    setCapa('vecinos'); renderVecinos();
  });
  }

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && fichaOverlayActiva) {
      ev.preventDefault();
      ev.stopPropagation();
      cerrarFichaOverlay();
    }
  });


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
    if (filtroBtn && ev.target.closest('[data-aht-screen="vecinos"]')) {
      vecRelFiltro = filtroBtn.getAttribute('data-vec-rel-filtro') || '';
      pintarVecRelFiltros();
      renderVecRelLista();
      return;
    }
    const pers = ev.target.closest('[data-vec-rel-open]');
    if (pers && pers.getAttribute('data-vec-rel-open') && ev.target.closest('[data-aht-screen="vecinos"]')) {
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

  var prClose = $('[data-pr-close]');
  if (prClose) prClose.addEventListener('click', cerrarResultadoPlan);

  actualizarControlMusica();
  $$('[data-musica-toggle]').forEach(function (btn) {
    if (btn.__ahtMusicaBound) return;
    btn.__ahtMusicaBound = true;
    btn.addEventListener('click', function () { cambiarMusica(!musicaActiva); });
  });
  $$('[data-musica-vol]').forEach(function (input) {
    if (input.__ahtMusVolBound) return;
    input.__ahtMusVolBound = true;
    input.addEventListener('input', function () {
      setMusicaVolumen(parseInt(input.value, 10) || 0);
    });
  });
  $$('[data-sfx-vol]').forEach(function (input) {
    if (input.__ahtSfxVolBound) return;
    input.__ahtSfxVolBound = true;
    input.addEventListener('input', function () {
      if (window.AhtAudioFeedback && typeof window.AhtAudioFeedback.setVolume === 'function') {
        window.AhtAudioFeedback.setVolume(parseInt(input.value, 10) / 100);
      }
    });
  });
  syncAjustesUI();
  iniciarMusicaFondo(true);

  window.addEventListener('popstate', function () {
    if (uiHistDepth > 0) uiHistDepth--;
    uiHistBack();
  });

  // V4 lifecycle: reset buzon tab to NUEVOS on every open
  document.addEventListener('aht-screen-open', function (ev) {
    var screen = ev.detail && ev.detail.screen;
    if (screen === 'buzon') {
      var bList = $('[data-buzon-list]');
      if (bList) bList.setAttribute('data-buzon-filtro', 'nuevos');
      document.querySelectorAll('[data-buzon-tab]').forEach(function (t) {
        t.classList.toggle('is-active', t.getAttribute('data-buzon-tab') === 'nuevos');
        t.setAttribute('aria-selected', t.getAttribute('data-buzon-tab') === 'nuevos' ? 'true' : 'false');
      });
      renderBuzon(cacheBuzon);
    }
    if (screen === 'vecinos') {
      vecBuscaTxt = '';
      var vecInp = $('[data-vec-busca]');
      if (vecInp) vecInp.value = '';
      renderVecinos();
    }
    if (screen === 'historia') {
      renderHistoriaPueblo();
    }
    if (screen === 'necesidades_global') {
      setCapa('vecinos');
      setVecTab('cuidados');
    }
    if (screen === 'organizar') {
      orgBuscaTxt = '';
      var buscaInp = $('[data-org-busca]');
      if (buscaInp) buscaInp.value = '';
      if (!orgPresetNuevo) resetOrgForm();
      orgPresetNuevo = false;
      fillOrganizar();
    }
    if (screen === 'inventario') {
      invFichaRid = null;
      invFichaNombre = '';
      renderInventario();
    }
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
      return refresh().then(function () {
        quizaMostrarTutIntro();
        startSyncClock();
      });
    });
  });
})();
