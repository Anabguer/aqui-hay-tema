/*
 * PUM DEBUGGER — pegar en consola DevTools, abrir buzón, leer tabla.
 * Mide gap entre tabs y primera card + DOM structure en cada render.
 * Para parar: window.__pumObs.disconnect()
 */
(function () {
  var box = document.querySelector('[data-buzon-list]');
  if (!box) { console.error('[PUM] No [data-buzon-list]'); return; }

  var snaps = [];
  var renderN = 0;

  function measure(label) {
    renderN++;
    var tabs   = document.querySelector('.aht-frame-tabs');
    var body   = document.querySelector('.aht-frame-body');
    var sec    = box.querySelector('.aht-msg-section');
    var card   = box.querySelector('.aht-msg-card');
    var tabRect = tabs ? tabs.getBoundingClientRect() : null;
    var cardRect = card ? card.getBoundingClientRect() : null;

    var gapPx = (tabRect && cardRect)
      ? Math.round(cardRect.top - tabRect.bottom)
      : null;

    var s = {
      n: renderN,
      t: Math.round(performance.now()) + 'ms',
      label: label,
      gapTabsCard: gapPx,
      listPaddingTop: getComputedStyle(box).paddingTop,
      listGap: getComputedStyle(box).gap,
      listScrollH: box.scrollHeight,
      cardCount: box.querySelectorAll('.aht-msg-card').length,
      sectionCount: box.querySelectorAll('.aht-msg-section').length,
      bodyPaddingTop: body ? getComputedStyle(body).paddingTop : null,
      tabsMarginBot: tabs ? getComputedStyle(tabs).marginBottom : null,
      firstCardH: card ? card.offsetHeight : null,
      sectionPaddingTop: sec ? getComputedStyle(sec).paddingTop : null,
      childrenTypes: Array.from(box.children).map(function (c) {
        return c.className || c.tagName;
      }).join(' > ')
    };

    snaps.push(s);
    console.log('[PUM #' + s.n + '] ' + label +
      ' | gap=' + gapPx + 'px' +
      ' | list-pt=' + s.listPaddingTop +
      ' | gap-css=' + s.listGap +
      ' | cards=' + s.cardCount +
      ' | sections=' + s.sectionCount +
      ' | children=' + s.childrenTypes);
    return s;
  }

  /* Medición inicial */
  measure('INIT (primer paint)');

  /* Scheduled measurements */
  setTimeout(function () { measure('100ms'); }, 100);
  setTimeout(function () { measure('500ms'); }, 500);
  setTimeout(function () { measure('1s'); }, 1000);
  setTimeout(function () { measure('2s'); }, 2000);
  setTimeout(function () { measure('3s'); }, 3000);

  /* MutationObserver: detecta re-render (ensureCatalogoRegalos o cualquier otro) */
  var prevHTML = box.innerHTML;
  var obs = new MutationObserver(function () {
    var now = box.innerHTML;
    if (now !== prevHTML) {
      measure('DOM_CHANGE (innerHTML diff)');
      prevHTML = now;
    }
  });
  obs.observe(box, { childList: true, subtree: true, attributes: true });
  window.__pumObs = obs;

  console.log('%c[PUM] Activo — abre buzón y lee la tabla.', 'color:#d85a78;font-weight:bold');
  console.log('[PUM] Para parar: window.__pumObs.disconnect()');
  console.log('[PUM] Resumen: window.__pumSnaps = snaps (accesible desde consola)');
  window.__pumSnaps = snaps;
})();
