/* Feedback sonoro sutil del juego. Efectos principales con MP3; resto con Web Audio. */
(function (global) {
  'use strict';

  var EFFECTS_KEY = 'aht_efectos_sonido';
  var SFX_VOL_KEY = 'aht_sfx_vol';
  var STATE_PREFIX = 'aht_sfx_estado_v1:';
  var effectsOn = true;
  var sfxVolume = 0.55;
  var active = 0;
  var maxActive = 2;
  var context = null;
  var master = null;
  var currentPartida = null;
  var states = Object.create(null);
  var observeQueue = Promise.resolve();
  var samples = {
    mensajito: 'assets/audio/mensajito.mp3',
    cotilleo: 'assets/audio/cotilleo.mp3',
    romance: 'assets/audio/romance.mp3',
    conflicto: 'assets/audio/conflicto.mp3'
  };
  var sampleCache = Object.create(null);
  var patterns = {
    mision: { notes: [523, 659, 784], length: .42, gap: .07, level: .38, wave: 'sine' },
    descubrimiento: { notes: [740, 988], length: .31, gap: .065, level: .3, wave: 'sine' },
    llegada: { notes: [392, 494, 659], length: .38, gap: .075, level: .35, wave: 'sine' },
    nuevo_dia: { notes: [330, 440, 554], length: .48, gap: .09, level: .27, wave: 'sine' }
  };

  try { effectsOn = localStorage.getItem(EFFECTS_KEY) !== '0'; } catch (e) {}
  try {
    var storedSfxVol = parseFloat(localStorage.getItem(SFX_VOL_KEY));
    if (isFinite(storedSfxVol) && storedSfxVol >= 0 && storedSfxVol <= 1) sfxVolume = storedSfxVol;
  } catch (e) {}

  function noop() {}

  function setStored(key, value) {
    try { localStorage.setItem(key, value); } catch (e) {}
  }

  function getStored(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  function isEffect(name) {
    return !!samples[name] || !!patterns[name];
  }

  function updateEffectsControl() {
    var control = document.querySelector('[data-efectos-toggle]');
    if (!control) return;
    control.dataset.efectos = effectsOn ? 'on' : 'off';
    control.setAttribute('aria-pressed', effectsOn ? 'true' : 'false');
    var label = effectsOn ? 'Desactivar efectos de sonido' : 'Activar efectos de sonido';
    control.setAttribute('aria-label', label);
    control.setAttribute('title', label);
  }

  function setEffects(on) {
    effectsOn = !!on;
    setStored(EFFECTS_KEY, effectsOn ? '1' : '0');
    updateEffectsControl();
  }

  function getVolume() {
    return sfxVolume;
  }

  function setVolume(v) {
    sfxVolume = Math.max(0, Math.min(1, Number(v) || 0));
    setStored(SFX_VOL_KEY, String(sfxVolume));
    if (master && master.gain) {
      try { master.gain.value = sfxVolume * (0.16 / 0.55); } catch (e) {}
    }
    Object.keys(sampleCache).forEach(function (name) {
      var audio = sampleCache[name];
      if (audio) try { audio.volume = sfxVolume; } catch (e) {}
    });
  }

  function ensureContext() {
    if (context) return context;
    var Ctx = global.AudioContext || global.webkitAudioContext;
    if (!Ctx) return null;
    try {
      context = new Ctx();
      master = context.createGain();
      master.gain.value = sfxVolume * (0.16 / 0.55);
      master.connect(context.destination);
    } catch (e) {
      context = null;
      master = null;
    }
    return context;
  }

  function releaseActive(ms) {
    global.setTimeout(function () { active = Math.max(0, active - 1); }, ms);
  }

  function playSample(name) {
    var src = samples[name];
    if (!src) return false;
    var audio = sampleCache[name];
    if (!audio) {
      audio = new Audio(src);
      audio.preload = 'auto';
      audio.volume = sfxVolume;
      sampleCache[name] = audio;
    }
    active++;
    try {
      audio.currentTime = 0;
      var promise = audio.play();
      if (promise && typeof promise.catch === 'function') promise.catch(noop);
    } catch (e) {}
    var duration = audio.duration;
    releaseActive(((duration && isFinite(duration)) ? duration : 1.2) * 1000 + 80);
    return true;
  }

  function playPattern(name) {
    var pattern = patterns[name];
    if (!pattern) return false;
    var ctx = ensureContext();
    if (!ctx || !master) return false;
    active++;
    var start = ctx.currentTime + .015;
    var total = pattern.length + Math.max(0, pattern.notes.length - 1) * pattern.gap;
    pattern.notes.forEach(function (frequency, index) {
      var when = start + index * pattern.gap;
      try {
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = pattern.wave;
        osc.frequency.setValueAtTime(frequency, when);
        gain.gain.setValueAtTime(.0001, when);
        gain.gain.exponentialRampToValueAtTime(pattern.level, when + .025);
        gain.gain.exponentialRampToValueAtTime(.0001, when + pattern.length);
        osc.connect(gain);
        gain.connect(master);
        osc.start(when);
        osc.stop(when + pattern.length + .035);
      } catch (e) {}
    });
    try { if (ctx.state === 'suspended') ctx.resume().catch(noop); } catch (e) {}
    releaseActive((total + .18) * 1000);
    return true;
  }


  function pauseAll() {
    Object.keys(sampleCache).forEach(function (name) {
      var audio = sampleCache[name];
      if (!audio) return;
      try { audio.pause(); } catch (e) {}
    });
    if (context && context.state === 'running') {
      try { context.suspend().catch(noop); } catch (e) {}
    }
  }

  function resumeContext() {
    if (context && context.state === 'suspended') {
      try { context.resume().catch(noop); } catch (e) {}
    }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) pauseAll();
    else resumeContext();
  });
  window.addEventListener('pagehide', pauseAll);

  function play(name) {
    if (!effectsOn || !isEffect(name) || active >= maxActive) return false;
    if (samples[name]) return playSample(name);
    return playPattern(name);
  }

  function partidaIdDe(data) {
    var p = data && data.partida;
    var e = data && data.estado;
    var candidates = [
      data && data.partida_id,
      p && p.meta && p.meta.partida_id,
      p && p.partida_id,
      e && e.meta && e.meta.partida_id,
      e && e.partida_id,
      currentPartida,
      getStored('aht_partida_id_juego'),
      getStored('aht_partida_id')
    ];
    for (var i = 0; i < candidates.length; i++) {
      if (candidates[i] !== null && candidates[i] !== undefined && String(candidates[i]) !== '') {
        return String(candidates[i]);
      }
    }
    return 'sin_partida';
  }

  function blankState() {
    return { dayKnown: false, day: null, eventsReady: false, events: [], messagesReady: false, messages: [], gossipReady: false, gossip: [], suppress: {} };
  }

  function stateFor(pid) {
    if (states[pid]) return states[pid];
    var state = blankState();
    var raw = getStored(STATE_PREFIX + pid);
    if (raw) {
      try {
        var parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') state = Object.assign(state, parsed);
      } catch (e) {}
    }
    if (!Array.isArray(state.events)) state.events = [];
    if (!Array.isArray(state.messages)) state.messages = [];
    if (!Array.isArray(state.gossip)) state.gossip = [];
    if (!state.suppress || typeof state.suppress !== 'object') state.suppress = {};
    states[pid] = state;
    return state;
  }

  function saveState(pid, state) {
    setStored(STATE_PREFIX + pid, JSON.stringify({
      dayKnown: !!state.dayKnown,
      day: state.day,
      eventsReady: !!state.eventsReady,
      events: state.events.slice(-180),
      messagesReady: !!state.messagesReady,
      messages: state.messages.slice(-180),
      gossipReady: !!state.gossipReady,
      gossip: state.gossip.slice(-180)
    }));
  }

  function addPending(list, name) {
    if (isEffect(name) && list.indexOf(name) < 0) list.push(name);
  }

  function dayDe(data) {
    var r = data && data.estado && (data.estado.reloj || data.estado.reloj_vista);
    if (!r && data && data.partida) r = data.partida.reloj;
    if (!r) return null;
    var value = r.dia_pueblo;
    if (value === undefined) value = r.dia;
    var day = Number(value);
    return isFinite(day) ? day : null;
  }

  function observeDay(data, state, pending) {
    var day = dayDe(data);
    if (day === null) return;
    if (!state.dayKnown) {
      state.dayKnown = true;
      state.day = day;
    } else if (state.day !== day) {
      var oldDay = state.day;
      state.day = day;
      if (day > Number(oldDay || 0)) addPending(pending, 'nuevo_dia');
    }
  }

  function domainEventsDe(data) {
    var p = data && data.partida;
    return p && Array.isArray(p.domain_events) ? p.domain_events : [];
  }

  function observeDomainEvents(data, state, pending) {
    var events = domainEventsDe(data);
    if (!events.length) {
      if (data && data.partida && !state.eventsReady) state.eventsReady = true;
      return;
    }
    var fresh = [];
    events.forEach(function (event, index) {
      if (!event || typeof event !== 'object') return;
      var type = String(event.evento || event.tipo || '');
      var key = String(event.correlacion_id || (type + ':' + JSON.stringify(event.ts_juego || {}) + ':' + index));
      if (state.events.indexOf(key) < 0) fresh.push({ type: type, key: key });
    });
    if (!state.eventsReady) {
      state.events = events.map(function (event, index) {
        return String(event && (event.correlacion_id || ((event.evento || event.tipo || '') + ':' + JSON.stringify(event.ts_juego || {}) + ':' + index)));
      }).slice(-180);
      state.eventsReady = true;
      return;
    }
    fresh.forEach(function (event) {
      state.events.push(event.key);
      if (event.type === 'mision_cumplida') addPending(pending, 'mision');
      if (event.type === 'descubrimiento_registrado') addPending(pending, 'descubrimiento');
      if (event.type === 'senal_romantica' || event.type === 'pareja_hito') {
        addPending(pending, 'romance');
        state.suppress.romance = true;
      }
      if (event.type === 'residente_incorporado') addPending(pending, 'llegada');
      if (event.type === 'discusion') {
        addPending(pending, 'conflicto');
        state.suppress.conflicto = true;
      }
    });
    state.events = state.events.slice(-180);
  }

  function itemKey(item, index, prefix) {
    if (item && item.id !== undefined && String(item.id) !== '') return String(item.id);
    var text = item && (item.texto || item.mensaje || item.titulo || '');
    var day = item && (item.dia || item.fecha_corta || '');
    return prefix + ':' + String(day) + ':' + String(text);
  }

  function observeMessages(items, state, pending) {
    if (!Array.isArray(items)) return;
    var fresh = [];
    items.forEach(function (item, index) {
      var key = itemKey(item, index, 'mensaje');
      if (state.messages.indexOf(key) < 0) fresh.push(key);
    });
    if (!state.messagesReady) {
      state.messages = items.map(function (item, index) { return itemKey(item, index, 'mensaje'); }).slice(-180);
      state.messagesReady = true;
      return;
    }
    fresh.forEach(function (key) { state.messages.push(key); });
    if (fresh.length) addPending(pending, 'mensajito');
    state.messages = state.messages.slice(-180);
  }

  function gossipItems(data) {
    var coti = data && data.cotilleo;
    if (!coti && data && data.diario) coti = data.diario.cotilleo;
    if (!coti) return null;
    if (Array.isArray(coti)) return coti;
    return [].concat(Array.isArray(coti.hoy) ? coti.hoy : [], Array.isArray(coti.ayer) ? coti.ayer : [], Array.isArray(coti.viejos) ? coti.viejos : []);
  }

  function observeGossip(items, state, pending) {
    if (!Array.isArray(items)) return;
    var fresh = [];
    items.forEach(function (item, index) {
      var key = itemKey(item, index, 'cotilleo');
      if (state.gossip.indexOf(key) < 0) fresh.push({ key: key, item: item || {} });
    });
    if (!state.gossipReady) {
      state.gossip = items.map(function (item, index) { return itemKey(item, index, 'cotilleo'); }).slice(-180);
      state.gossipReady = true;
      state.suppress = {};
      return;
    }
    fresh.forEach(function (entry) {
      state.gossip.push(entry.key);
      var type = String(entry.item.tipo || '');
      if (type === 'discusion') {
        if (state.suppress.conflicto) state.suppress.conflicto = false;
        else addPending(pending, 'conflicto');
      } else if (type === 'senal_romantica') {
        if (state.suppress.romance) state.suppress.romance = false;
        else addPending(pending, 'romance');
      } else {
        addPending(pending, 'cotilleo');
      }
    });
    state.suppress = {};
    state.gossip = state.gossip.slice(-180);
  }

  function processPayload(action, data) {
    if (!data || data.ok === false) return;
    if (action === 'partida.nueva' && data.partida_id) currentPartida = String(data.partida_id);
    var pid = partidaIdDe(data);
    var state = stateFor(pid);
    var pending = [];
    observeDay(data, state, pending);
    observeDomainEvents(data, state, pending);
    var messages = Array.isArray(data.mensajes) ? data.mensajes : (data.buzon && Array.isArray(data.buzon.mensajes) ? data.buzon.mensajes : null);
    observeMessages(messages, state, pending);
    observeGossip(gossipItems(data), state, pending);
    saveState(pid, state);
    pending.slice(0, 2).forEach(function (name, index) {
      global.setTimeout(function () { play(name); }, index * 160);
    });
  }

  function actionDe(input) {
    var raw = input && input.url ? input.url : String(input || '');
    try {
      var url = new URL(raw, global.location.href);
      return url.searchParams.get('action') || '';
    } catch (e) {
      var match = raw.match(/[?&]action=([^&]+)/);
      return match ? decodeURIComponent(match[1]) : '';
    }
  }

  function installFetchObserver() {
    if (typeof global.fetch !== 'function' || global.fetch.__ahtAudioFeedback) return;
    var nativeFetch = global.fetch;
    var wrapped = function () {
      var action = actionDe(arguments[0]);
      var request;
      try { request = nativeFetch.apply(this, arguments); } catch (e) { throw e; }
      return request.then(function (response) {
        if (action) {
          try {
            response.clone().json().then(function (data) {
              observeQueue = observeQueue.then(function () { processPayload(action, data); }, function () { processPayload(action, data); }).catch(noop);
            }).catch(noop);
          } catch (e) {}
        }
        return response;
      });
    };
    wrapped.__ahtAudioFeedback = true;
    global.fetch = wrapped;
  }

  function bindControls() {
    var control = document.querySelector('[data-efectos-toggle]');
    if (control && !control.__ahtBound) {
      control.__ahtBound = true;
      control.addEventListener('click', function () { setEffects(!effectsOn); });
    }
    document.querySelectorAll('[data-aht-sfx]').forEach(function (button) {
      if (button.__ahtBound) return;
      button.__ahtBound = true;
      button.addEventListener('click', function () { play(button.getAttribute('data-aht-sfx')); });
    });
    document.querySelectorAll('[data-sfx-vol]').forEach(function (input) {
      if (input.__ahtSfxVolBound) return;
      input.__ahtSfxVolBound = true;
      input.addEventListener('input', function () {
        setVolume(parseInt(input.value, 10) / 100);
      });
    });
    updateEffectsControl();
  }

  global.AhtAudioFeedback = {
    play: play,
    pauseAll: pauseAll,
    setEffects: setEffects,
    getVolume: getVolume,
    setVolume: setVolume,
    isEnabled: function () { return effectsOn; },
    names: Object.keys(samples).concat(Object.keys(patterns))
  };

  installFetchObserver();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindControls);
  else bindControls();
})(window);
