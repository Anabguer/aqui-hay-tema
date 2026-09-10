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
    ], tilt: 0.35 }
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
    V4_SCREENS: V4_SCREENS
  };

  /* ── Auto-init on DOMContentLoaded ───────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
