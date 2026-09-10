/* ============================================================
   AHT V4 — SCREEN MANAGER
   Autoridad ÚNICA para: abrir, cerrar, X, ESC, backdrop,
   bloqueo del mapa, foco, stacking, restauración, ciclo de vida.

   Este módulo ENVUELVE el sistema data-capa existente y le
   añade stack real, lifecycle y aislamiento V4.

   NO reescribe play-v3.js. Se integra como capa superior.
   ============================================================ */

(function () {
  'use strict';

  /* ── Helpers ─────────────────────────────────────────────── */
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

  /* ── Estado interno ──────────────────────────────────────── */
  const stack = [];          // [{id, scrollY, dataAttrs}]
  let isOpen = false;
  let currentScreen = null;
  let velo = null;
  let root = null;
  let bodyLockY = 0;
  let bodyLockPad = 0;

  /* ── Configuración de screens V4 ────────────────────────── */
  // screens que usan el sistema V4 completo (frame + stack)
  const V4_SCREENS = new Set([
    'vecinos', 'agenda', 'mentes', 'ficha', 'ficha_relaciones',
    'ficha_animo', 'ficha_diario', 'misiones',
    'parejas', 'historia', 'historia_detalle',
    'vida_pueblo', 'buzon', 'inventario', 'ajustes', 'diario',
    'organizar', 'plan-resultado'
  ]);

  // sub-screens que se apilan sobre su padre
  const SUB_SCREENS = new Set([
    'ficha_relaciones', 'ficha_animo', 'ficha_diario'
  ]);

  // screens que NO usan stack (se cierran directamente)
  const FLAT_SCREENS = new Set([
    'historia_detalle'
  ]);

  /* ── DOODLES — catálogo decorativo header ─────────────────── */
  // Catálogo compartido vía doodle-catalog.js (window.AHT_DOODLE_SVGS).
  // Screen Manager e Inicio consumen la misma fuente, sin duplicación.
  var DOODLE_SVGS = window.AHT_DOODLE_SVGS || {};

  // Posiciones: distribuidas por TODO el header, no solo alrededor del título.
  // cls = clase CSS, sz = tier (sm/md/lg), tilt = rotación individual del SVG.
  var DOODLE_POSITIONS = {
    tl:  { cls: 'd-tl',  sz: 'md', tilt: -5  },
    tr:  { cls: 'd-tr',  sz: 'md', tilt: 14  },
    lm:  { cls: 'd-lm',  sz: 'sm', tilt: -10 },
    rm:  { cls: 'd-rm',  sz: 'sm', tilt: 8   },
    bl:  { cls: 'd-bl',  sz: 'sm', tilt: 6   },
    br:  { cls: 'd-br',  sz: 'sm', tilt: -4  },
    fl:  { cls: 'd-fl',  sz: 'lg', tilt: -12 },
    fml: { cls: 'd-fml', sz: 'md', tilt: 10  },
    fbl: { cls: 'd-fbl', sz: 'sm', tilt: -6  },
    fr:  { cls: 'd-fr',  sz: 'lg', tilt: 8   },
    fmr: { cls: 'd-fmr', sz: 'md', tilt: -14 },
    fbr: { cls: 'd-fbr', sz: 'sm', tilt: 5   }
  };

  // Firma determinista por screen: 5-7 doodles repartidos por el header.
  // Composición scrapbook: grandes en esquinas, medianas cerca del título,
  // pequeños acentos distribuidos. Misma screen = misma decoración siempre.
  var DOODLE_MAP = {
    vecinos:              { d: [
      {id:'h',pos:'fl'},{id:'x',pos:'tl'},{id:'f',pos:'tr'},
      {id:'s',pos:'rm'},{id:'x',pos:'fmr'},{id:'h',pos:'fr'}
    ], tilt: 0.8 },
    buzon:                { d: [
      {id:'h',pos:'fl'},{id:'x',pos:'tl'},{id:'w',pos:'bl'},
      {id:'d',pos:'tr'},{id:'x',pos:'rm'},{id:'h',pos:'fr'},{id:'x',pos:'fmr'}
    ], tilt: -1.2 },
    misiones:             { d: [
      {id:'s',pos:'fl'},{id:'x',pos:'tl'},{id:'h',pos:'lm'},
      {id:'x',pos:'tr'},{id:'s',pos:'fmr'},{id:'x',pos:'fr'}
    ], tilt: 0.3 },
    agenda:               { d: [
      {id:'f',pos:'fl'},{id:'o',pos:'tl'},{id:'x',pos:'bl'},
      {id:'h',pos:'tr'},{id:'x',pos:'rm'},{id:'f',pos:'fr'}
    ], tilt: -0.5 },
    inventario:           { d: [
      {id:'w',pos:'fl'},{id:'p',pos:'tl'},{id:'x',pos:'tr'},
      {id:'s',pos:'rm'},{id:'x',pos:'fmr'},{id:'w',pos:'fr'}
    ], tilt: 1.0 },
    ajustes:              { d: [
      {id:'o',pos:'fl'},{id:'s',pos:'tl'},{id:'x',pos:'tr'},
      {id:'f',pos:'rm'},{id:'x',pos:'fmr'},{id:'o',pos:'fr'}
    ], tilt: -0.7 },
    diario:               { d: [
      {id:'d',pos:'fl'},{id:'x',pos:'tl'},{id:'h',pos:'bl'},
      {id:'x',pos:'tr'},{id:'w',pos:'rm'},{id:'d',pos:'fr'}
    ], tilt: 0.6 },
    mentes:               { d: [
      {id:'w',pos:'fl'},{id:'f',pos:'tl'},{id:'x',pos:'lm'},
      {id:'h',pos:'tr'},{id:'x',pos:'fmr'},{id:'w',pos:'fr'}
    ], tilt: -0.9 },
    organizar:            { d: [
      {id:'s',pos:'fl'},{id:'f',pos:'tl'},{id:'x',pos:'bl'},
      {id:'h',pos:'tr'},{id:'x',pos:'rm'},{id:'s',pos:'fr'}
    ], tilt: 0.4 },
    parejas:              { d: [
      {id:'h',pos:'fl'},{id:'d',pos:'tl'},{id:'x',pos:'tr'},
      {id:'f',pos:'rm'},{id:'x',pos:'fmr'},{id:'h',pos:'fr'}
    ], tilt: -0.6 },
    vida_pueblo:          { d: [
      {id:'h',pos:'fl'},{id:'s',pos:'tl'},{id:'x',pos:'bl'},
      {id:'o',pos:'tr'},{id:'x',pos:'rm'},{id:'h',pos:'fr'}
    ], tilt: 0.5 },
    historia:             { d: [
      {id:'o',pos:'fl'},{id:'f',pos:'tl'},{id:'x',pos:'lm'},
      {id:'w',pos:'tr'},{id:'x',pos:'fmr'},{id:'o',pos:'fr'}
    ], tilt: -0.4 },
    historia_detalle:     { d: [
      {id:'w',pos:'fl'},{id:'h',pos:'tl'},{id:'x',pos:'tr'},
      {id:'s',pos:'rm'},{id:'x',pos:'fmr'},{id:'w',pos:'fr'}
    ], tilt: 0.7 },
    historia_celebracion: { d: [
      {id:'d',pos:'fl'},{id:'s',pos:'tl'},{id:'x',pos:'bl'},
      {id:'h',pos:'tr'},{id:'x',pos:'rm'},{id:'d',pos:'fr'}
    ], tilt: -0.3 },
    ficha:                { d: [
      {id:'p',pos:'fl'},{id:'w',pos:'tl'},{id:'x',pos:'tr'},
      {id:'h',pos:'rm'},{id:'x',pos:'fmr'},{id:'p',pos:'fr'}
    ], tilt: 0.9 },
    ficha_relaciones:     { d: [
      {id:'h',pos:'fl'},{id:'o',pos:'tl'},{id:'x',pos:'bl'},
      {id:'f',pos:'tr'},{id:'x',pos:'rm'},{id:'h',pos:'fr'}
    ], tilt: -0.8 },
    ficha_animo:          { d: [
      {id:'f',pos:'fl'},{id:'h',pos:'tl'},{id:'x',pos:'tr'},
      {id:'w',pos:'rm'},{id:'x',pos:'fmr'},{id:'f',pos:'fr'}
    ], tilt: 0.2 },
    ficha_diario:         { d: [
      {id:'w',pos:'fl'},{id:'d',pos:'tl'},{id:'x',pos:'bl'},
      {id:'h',pos:'tr'},{id:'x',pos:'rm'},{id:'w',pos:'fr'}
    ], tilt: -1.0 },
    necesidades_global:   { d: [
      {id:'s',pos:'fl'},{id:'p',pos:'tl'},{id:'x',pos:'tr'},
      {id:'o',pos:'rm'},{id:'x',pos:'fmr'},{id:'s',pos:'fr'}
    ], tilt: 0.35 },
    edificios:            { d: [
      {id:'h',pos:'fl'},{id:'w',pos:'tl'},{id:'x',pos:'tr'},
      {id:'o',pos:'rm'},{id:'x',pos:'fmr'},{id:'h',pos:'fr'}
    ], tilt: 0.5 },
    'plan-resultado':     { d: [
      {id:'h',pos:'fl'},{id:'s',pos:'tl'},{id:'x',pos:'bl'},
      {id:'d',pos:'tr'},{id:'x',pos:'rm'},{id:'h',pos:'fr'}
    ], tilt: -0.4 }
  };

  /**
   * Inyectar doodles decorativos en el HEADER de una screen.
   * Lazy, idempotente: solo inserta si no existen ya.
   * Los doodles se insertan en .aht-frame-header (no en .aht-frame-title)
   * para distribuirse por todo el espacio libre del header.
   * @param {string} screenId
   */
  function injectDoodles(screenId) {
    var combo = DOODLE_MAP[screenId];
    if (!combo) return;

    var screen = root.querySelector('.aht-screen[data-aht-screen="' + screenId + '"]');
    if (!screen) return;

    var header = screen.querySelector('.aht-frame-header');
    var title = screen.querySelector('.aht-frame-title');
    if (!header || !title) return;

    // Idempotente: si ya tiene doodles, no re-inyectar
    if (header.querySelector('.aht-doodle')) return;

    // Tilt determinista por screen
    title.style.setProperty('--aht-title-tilt', combo.tilt + 'deg');

    // Crear e inyectar doodles en el HEADER
    combo.d.forEach(function(entry, i) {
      var pos = DOODLE_POSITIONS[entry.pos];
      var span = document.createElement('span');
      span.className = 'aht-doodle ' + pos.cls + ' ' + pos.sz;
      span.setAttribute('aria-hidden', 'true');
      span.innerHTML = DOODLE_SVGS[entry.id];
      header.appendChild(span);

      // Fade-in escalonado
      requestAnimationFrame(function() {
        setTimeout(function() { span.classList.add('injected'); }, 50 * i);
      });
    });
  }

  /* ── Inicialización ──────────────────────────────────────── */
  function init() {
    root = $('.play-root');

    // Crear backdrop V4 SEPARADO del .velo legacy.
    // El .velo legacy tiene autoridad visual legacy con !important.
    // .aht-velo tiene autoridad V4 propia — cero dependencia legacy.
    velo = document.createElement('div');
    velo.className = 'aht-velo';
    root.appendChild(velo);

    // Event listeners
    document.addEventListener('keydown', handleKeydown);
    velo.addEventListener('click', handleBackdropClick);

    // Interceptar data-open clicks
    document.body.addEventListener('click', handleGlobalClick, true);

    // Popstate para browser back
    window.addEventListener('popstate', handlePopState);
  }

  /* ── API pública ─────────────────────────────────────────── */

  /**
   * Abrir una screen V4.
   * @param {string} screenId - Nombre de la screen (data-capa value)
   * @param {object} opts - Opciones: {silent, overlay, scrollY}
   */
  function open(screenId, opts) {
    opts = opts || {};
    if (!root || !V4_SCREENS.has(screenId)) return false;

    const prev = currentScreen;

    // Si hay una screen abierta y no es sub-screen, pushear al stack
    if (currentScreen && !SUB_SCREENS.has(screenId) && !FLAT_SCREENS.has(screenId)) {
      pushStack(currentScreen);
    } else if (SUB_SCREENS.has(screenId) && currentScreen) {
      // Sub-screen: push del padre actual
      pushStack(currentScreen);
    }

    // Aplicar data-capa
    root.setAttribute('data-capa', screenId);
    currentScreen = screenId;
    isOpen = true;

    // Mostrar velo
    showVelo();

    // Bloquear scroll
    lockScroll();

    // Auto-push history (a menos que sea silent)
    if (!opts.silent) {
      historyPushState(screenId);
    }

    // Actualizar dock highlight
    updateDock(screenId);

    // Focus trap
    requestAnimationFrame(function() {
      var frame = root.querySelector('.aht-frame-close');
      if (frame) frame.focus();
    });

    // Doodles decorativos del header (lazy, idempotente)
    injectDoodles(screenId);

    // Lifecycle event — legacy code can react to screen opens
    try { document.dispatchEvent(new CustomEvent('aht-screen-open', { detail: { screen: screenId } })); } catch(e) {}

    return true;
  }

  /**
   * Cerrar la screen actual y volver a la anterior del stack.
   */
  function close() {
    if (!isOpen || !currentScreen) return false;

    // DOM-staleness guard: if a legacy screen (e.g. historia_celebracion)
    // was shown on top via setCapa(), the DOM data-capa may differ from
    // currentScreen. In that case, V4 doesn't own the visible screen —
    // clear V4 state without popping the stack, let legacy handle it.
    var domCapa = root.getAttribute('data-capa') || '';
    if (domCapa && domCapa !== currentScreen && !V4_SCREENS.has(domCapa)) {
      currentScreen = null;
      isOpen = false;
      stack.length = 0;
      hideVelo();
      unlockScroll();
      document.body.classList.remove('play-v3--scroll-lock');
      delete document.body.dataset.scrollLockY;
      delete document.body.dataset.scrollLockPad;
      document.body.style.top = '';
      document.body.style.paddingRight = '';
      updateDock('');
      return true;
    }

    var closingScreen = currentScreen;
    var prevScreen = popStack();

    if (prevScreen) {
      // Restaurar screen anterior
      root.setAttribute('data-capa', prevScreen.id);
      currentScreen = prevScreen.id;

      // Restaurar scroll position
      requestAnimationFrame(function() {
        var scrollable = getScrollableElement(prevScreen.id);
        if (scrollable && prevScreen.scrollY !== undefined) {
          scrollable.scrollTop = prevScreen.scrollY;
        }
      });
    } else {
      // No hay más screens en el stack — cerrar todo
      root.removeAttribute('data-capa');
      currentScreen = null;
      isOpen = false;
      hideVelo();
      unlockScroll();

      // DEPENDENCIA FUNCIONAL LEGACY TEMPORAL:
      // Limpiar scroll lock legacy que setCapa() aplicó en apertura.
      // Cuando renderers se expongan desde play-v3.js IIFE, se añadirá
      // stopPropagation en [data-open] y esta limpieza no será necesaria.
      document.body.classList.remove('play-v3--scroll-lock');
      delete document.body.dataset.scrollLockY;
      delete document.body.dataset.scrollLockPad;
      document.body.style.top = '';
      document.body.style.paddingRight = '';
    }

    // Actualizar dock
    updateDock(currentScreen || '');

    return true;
  }

  /**
   * Cerrar todo (nuclear). Equivale a cerrarUiCompleto.
   */
  function closeAll() {
    stack.length = 0;
    currentScreen = null;
    isOpen = false;
    root.removeAttribute('data-capa');
    hideVelo();
    unlockScroll();
    updateDock('');

    // Reset legacy consultation
    root.removeAttribute('data-consulta');

    // Pop browser history
    try {
      var depth = parseInt(root.dataset.ahtHistDepth || '0', 10);
      if (depth > 0) {
        history.go(-depth);
      }
    } catch(e) {}
    root.dataset.ahtHistDepth = '0';
  }

  /**
   * Verificar si una screen está abierta.
   */
  function isScreenOpen(screenId) {
    return currentScreen === screenId;
  }

  /**
   * Obtener la screen actual.
   */
  function getCurrent() {
    return currentScreen;
  }

  /**
   * Obtener la profundidad del stack.
   */
  function getStackDepth() {
    return stack.length;
  }

  /* ── Stack interno ───────────────────────────────────────── */

  function pushStack(screenId) {
    var scrollable = getScrollableElement(screenId);
    stack.push({
      id: screenId,
      scrollY: scrollable ? scrollable.scrollTop : 0
    });
  }

  function popStack() {
    return stack.length > 0 ? stack.pop() : null;
  }

  /* ── Velo ────────────────────────────────────────────────── */

  function showVelo() {
    if (velo) {
      velo.classList.add('aht-velo--visible');
    }
  }

  function hideVelo() {
    if (velo) {
      velo.classList.remove('aht-velo--visible');
    }
  }

  /* ── Scroll lock ─────────────────────────────────────────── */

  function lockScroll() {
    if (!document.body.classList.contains('play-v3')) return;
    if (document.body.classList.contains('aht-scroll-locked')) return;

    bodyLockY = window.scrollY || window.pageYOffset || 0;
    bodyLockPad = Math.max(0, window.innerWidth - document.documentElement.clientWidth);

    document.body.dataset.ahtScrollLockY = String(bodyLockY);
    document.body.style.top = '-' + bodyLockY + 'px';
    if (bodyLockPad > 0) {
      document.body.style.paddingRight = bodyLockPad + 'px';
    }
    document.body.classList.add('aht-scroll-locked');
  }

  function unlockScroll() {
    if (!document.body.classList.contains('aht-scroll-locked')) return;

    var restore = parseInt(document.body.dataset.ahtScrollLockY || '0', 10) || 0;
    document.body.classList.remove('aht-scroll-locked');
    document.body.style.top = '';
    document.body.style.paddingRight = '';
    delete document.body.dataset.ahtScrollLockY;
    window.scrollTo(0, restore);
  }

  /* ── Event handlers ──────────────────────────────────────── */

  function handleKeydown(e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      if (isOpen) {
        e.preventDefault();
        e.stopPropagation();
        close();
      }
    }
  }

  function handleBackdropClick(e) {
    if (isOpen) {
      close();
    }
  }

  function handleGlobalClick(e) {
    var target = e.target;

    //[data-close] buttons
    var closeBtn = target.closest('[data-close]');
    if (closeBtn && closeBtn.closest('.play-root')) {
      // Only handle V4 screens
      if (currentScreen && V4_SCREENS.has(currentScreen)) {
        e.preventDefault();
        e.stopPropagation();
        close();
        return;
      }
    }

    // .aht-frame-close buttons (V4 close)
    var v4Close = target.closest('.aht-frame-close');
    if (v4Close) {
      if (currentScreen && V4_SCREENS.has(currentScreen)) {
        e.preventDefault();
        e.stopPropagation();
        close();
        return;
      }
    }

    // .aht-frame-back / [data-capa-back] � volver en stack V4 (p. ej. Cotilleos)
    var v4Back = target.closest('.aht-frame-back[data-capa-back], [data-capa-back].aht-frame-back');
    if (v4Back && v4Back.closest('.play-root')) {
      if (currentScreen && V4_SCREENS.has(currentScreen)) {
        e.preventDefault();
        e.stopPropagation();
        close();
        return;
      }
    }

     // Skip if click is on celestNecesitan (nested inside data-open vecinos button).
    if (target.closest('[data-celestine-necesitan]')) return;
    // [data-open] — V4 owns lifecycle via capture-phase open().
    // Mark event so legacy bubbling handler skips redundant setCapa/scroll/history.
    var openBtn = target.closest('[data-open]');
    if (openBtn) {
      var screenId = openBtn.getAttribute('data-open');
      if (V4_SCREENS.has(screenId)) {
        e.preventDefault();
        e.__ahtV4 = true;
        open(screenId);
        return;
      }
    }
  }

  function handlePopState() {
    if (isOpen) {
      close();
    }
  }

  /* ── History ─────────────────────────────────────────────── */

  function historyPushState(screenId) {
    try {
      var depth = parseInt(root.dataset.ahtHistDepth || '0', 10);
      depth++;
      root.dataset.ahtHistDepth = String(depth);
      history.pushState({ ahtV4: depth, screen: screenId }, '');
    } catch(e) {}
  }

  /* ── Dock update ─────────────────────────────────────────── */

  function updateDock(screenId) {
    $$('.dock button, .play-bottom-nav-btn').forEach(function(b) {
      var open = b.getAttribute('data-open');
      if (b.classList.contains('play-bottom-nav-btn')) return;
      b.classList.toggle('is-on', screenId ? open === screenId : !open);
    });

    // Legacy sync
    if (typeof syncBottomNav === 'function') {
      syncBottomNav(screenId);
    }
  }

  /* ── Scrollable element lookup ───────────────────────────── */

  function getScrollableElement(screenId) {
    if (!root) return null;
    var selectors = {
      'vecinos': '[data-aht-screen="vecinos"] .vec-panel',
      'buzon': '[data-aht-screen="buzon"] [data-buzon-list]',
      'ficha': '[data-aht-screen="ficha"] .ficha-body',
      'agenda': '[data-aht-screen="agenda"] .agenda-list',
      'misiones': '[data-aht-screen="misiones"] .mis-body',
      'parejas': '[data-aht-screen="parejas"] .par-body',
      'inventario': '[data-aht-screen="inventario"] .inv-body',
      'organizar': '[data-aht-screen="organizar"] .org-body',
      'ajustes': '[data-aht-screen="ajustes"] .ajustes-body',
      'historia': '[data-aht-screen="historia"] .historia-grid',
      'vida_pueblo': '[data-aht-screen="vida_pueblo"] .vida-body',
      'necesidades_global': '[data-aht-screen="necesidades_global"] .necg-body',
      'diario': '[data-aht-screen="diario"] .coti-body',
      'mentes': '[data-aht-screen="mentes"] .mentes-body'
    };
    var sel = selectors[screenId];
    return sel ? root.querySelector(sel) : null;
  }

  /* ── Expose global API ───────────────────────────────────── */

  function isAnyOpen() {
    return isOpen;
  }

  window.AHTScreenManager = {
    open: open,
    close: close,
    closeAll: closeAll,
    isOpen: isScreenOpen,
    isAnyOpen: isAnyOpen,
    getCurrent: getCurrent,
    getStackDepth: getStackDepth,
    init: init,
    injectDoodles: injectDoodles,
    V4_SCREENS: V4_SCREENS
  };

  /* ── Auto-init on DOMContentLoaded ───────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();

/* ============================================================ */
/* Plan images (data URIs) + doodle injection for plan-resultado */
/* Runs BEFORE play-v3.js; sets window.AHT_PLAN_IMAGES so      */
/* getEstadoImg() in play-v3.js can use data URIs.              */
/* Also injects "Volver a planes" button via MutationObserver.  */
/* ============================================================ */
(function(){
  window.AHT_PLAN_IMAGES = {
    "aceptado": "data:image/jpeg;base64,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LrM63N2GWxU55484+g/2fekryfLHc1jSjRTq1Xoipo2j3OtzBvmiswfnlP8AEPRfU+/QV6NbQR2tvHBAgSKNQqqOwp6IqIqIoVVGAAMAClrspUlTXmeVi8ZLEvslsgooorU4wooooAKKKKACiiigAooooAo6lpNlqQH2yBXYDAfow+hHNYU3gu2Ofs93cR+zYYf0rq6KiVKEt0dFLF1qStCWh59qHhXUrNDJayJdqOqqNr4+h4P51ixXJ3EOCkiHBBGCD6EV63VDUdHsNROby1jkf+/0b8xzXPPCreDPRo5q9q6v5rf/ACPP/tIYffGfSk84dSfyrprvwXZuv+iTzwN7neP15/WsebwfqsZxFLayr2O4qfyxWEqNRdDup4rDVNpW9dP+AZs13tB2kZqsLx9wPHuPWt228GahKw+1XFvDH32Zdv6CugtfCOkQw7JIGnfvJI5z+mMURoVJeQ6mOwtJWvzehxK3KkglsGpWuVI65ro7nwPau+ba7uIV/ukBwPz5ot/BFurD7Re3Eq+igJn+dP2FTaxLx2EavzfgctE89zP5VrHJNJ1KIMkVpweG9ZuWBdI4F9ZZMn8hmu70+wttPg8qzhWJO+Op9yepqzW8cKvtM4aubO9qUUl57nK6Z4PhikWXUpjdMOkYXan49zXUqoVQqgBQMADtS0VvCEYK0UebWxFSu71HcKKKKsxCiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigD//Z",
    "rechazado": "data:image/jpeg;base64,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",
    "duda": "data:image/jpeg;base64,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",
    "pendiente": "data:image/jpeg;base64,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",
    "fallido": "data:image/jpeg;base64,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",
  };

  /* Watch for plan-resultado becoming visible */
  var root = document.querySelector(".play-root");
  if (!root) return;
  function patchPlanResultado() {
    var si = document.querySelector("[data-pr-state-img]");
    if (si && si.src && si.src.indexOf("plan_") > -1) {
      var imgs = window.AHT_PLAN_IMAGES || {};
      var estado = "fallido";
      if (si.src.indexOf("aceptado") > -1) estado = "aceptado";
      else if (si.src.indexOf("rechazado") > -1) estado = "rechazado";
      else if (si.src.indexOf("duda") > -1) estado = "duda";
      else if (si.src.indexOf("pendiente") > -1) estado = "pendiente";
      if (imgs[estado] && si.src !== imgs[estado]) si.src = imgs[estado];
    }
    var prBody = document.querySelector(".pr-body");
    if (prBody && !prBody.querySelector(".pr-btn-volver") && window.__aht_pr_rechazada) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "pr-btn-volver";
      btn.setAttribute("data-pr-close", "");
      btn.style.cssText = "margin-top:.5rem;padding:.55rem 1.4rem;border-radius:12px;border:2px solid #33261E;background:#E8DFF5;color:#7C6BAE;font:700 .88rem/1 Nunito,sans-serif;cursor:pointer;box-shadow:2px 3px 0 rgba(51,38,30,.18);";
      btn.textContent = "Volver a planes";
      btn.addEventListener("click", function() {
        if (typeof setCapa === "function") setCapa("organizar");
        else if (typeof cerrarResultadoPlan === "function") cerrarResultadoPlan();
      });
      prBody.appendChild(btn);
    }
  }
  new MutationObserver(function() {
    var capa = root.getAttribute("data-capa") || "";
    if (capa.indexOf("plan-resultado") > -1) {
      setTimeout(patchPlanResultado, 100);
      setTimeout(patchPlanResultado, 400);
    }
  }).observe(root, {attributes:true, attributeFilter:["data-capa"]});
})();