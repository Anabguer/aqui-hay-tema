/* ============================================================
   AHT V4 — Architecture Verification Test (FASE 5)
   Demuestra ownership limpio: UNA propiedad -> UN propietario.
   Verifica autoridad global de X, Frame, Backdrop, Tokens.

   Uso: pegar en consola del navegador con el juego abierto.
   ============================================================ */
(function () {
  'use strict';
  var results = [];
  function test(name, ok, detail) {
    results.push({ name: name, ok: ok, detail: detail || '' });
  }

  /* 1. BACKDROP */
  var velo = document.querySelector('.aht-velo');
  test('1.1 aht-velo exists', !!velo);
  if (velo) {
    var cs = getComputedStyle(velo);
    test('1.2 position:fixed', cs.position === 'fixed');
    test('1.3 z-index:100', cs.zIndex === '100');
    test('1.4 opacity:0 default', cs.opacity === '0');
    test('1.5 pointer-events:none', cs.pointerEvents === 'none');
  }

  /* 2. X BUTTON GLOBAL AUTHORITY */
  var allX = document.querySelectorAll('.aht-frame-close');
  test('2.1 X buttons (>=19)', allX.length >= 19);
  var xBtn = document.querySelector('.aht-frame-close');
  if (xBtn) {
    var xcs = getComputedStyle(xBtn);
    test('2.2 display:grid', xcs.display === 'grid');
    test('2.3 cursor:pointer', xcs.cursor === 'pointer');
    test('2.4 position:absolute', xcs.position === 'absolute');
    test('2.5 color from token', xcs.color === 'rgb(196, 43, 74)');
    var xOk = true;
    allX.forEach(function (x) { if (getComputedStyle(x).display !== 'grid') xOk = false; });
    test('2.6 X uniform all screens', xOk);
  }

  /* 3. FRAME GLOBAL AUTHORITY */
  var allF = document.querySelectorAll('.aht-frame');
  test('3.1 Frame elements (>=19)', allF.length >= 19);
  var frame = document.querySelector('.aht-frame');
  if (frame) {
    var fcs = getComputedStyle(frame);
    test('3.2 frame NOT position:fixed (parent handles it)', fcs.position !== 'fixed');
    test('3.3 border-radius', fcs.borderRadius !== '0px');
    test('3.4 box-shadow', fcs.boxShadow !== 'none');
    var fOk = true;
    allF.forEach(function (f) { if (getComputedStyle(f).position === 'fixed') fOk = false; });
    test('3.5 no frame has position:fixed', fOk);
  }

  /* 4. SCREENS */
  var screens = document.querySelectorAll('.aht-screen');
  test('4.1 19 V4 screens', screens.length === 19);

  /* 5. TOKENS */
  var rs = getComputedStyle(document.documentElement);
  var toks = ['--aht-z-velo','--aht-z-frame','--aht-overlay','--aht-surface-raised',
    '--aht-border','--aht-frame-radius','--aht-font-hand','--aht-dur','--aht-accent',
    '--aht-close-color','--aht-close-bg','--aht-close-border','--aht-header-bg',
    '--aht-header-mark','--aht-body-bg'];
  var miss = [];
  toks.forEach(function (t) { if (!rs.getPropertyValue(t).trim()) miss.push(t); });
  test('5.1 all tokens defined', miss.length === 0, miss.length ? miss.join(', ') : '');

  /* 6. SCREEN MANAGER */
  var sm = window.AHTScreenManager;
  test('6.1 AHTScreenManager exists', !!sm);
  if (sm) {
    test('6.2 open()', typeof sm.open === 'function');
    test('6.3 close()', typeof sm.close === 'function');
    test('6.4 isAnyOpen()', typeof sm.isAnyOpen === 'function');
    test('6.5 V4_SCREENS set', sm.V4_SCREENS instanceof Set);
    test('6.6 V4_SCREENS size 19', sm.V4_SCREENS.size === 19);
  }

  /* 7. HEADER STRUCTURE */
  test('7.1 headers (>=19)', document.querySelectorAll('.aht-frame-header').length >= 19);
  test('7.2 titles (>=19)', document.querySelectorAll('.aht-frame-title').length >= 19);

  /* 8. CSS ARCHITECTURE */
  var v4sheets = Array.from(document.styleSheets).filter(function (ss) {
    try { var h = ss.href || ''; return h.includes('/v4/'); } catch (e) { return false; }
  });
  var impCnt = 0;
  v4sheets.forEach(function (ss) {
    try { Array.from(ss.cssRules || []).forEach(function (r) {
      if (r.cssText && r.cssText.includes('!important')) impCnt++;
    }); } catch (e) {}
  });
  test('8.1 zero !important in V4 CSS', impCnt === 0, impCnt ? impCnt + ' found' : '');

  /* 9. VISUAL VISIBILITY — open a screen and verify frame renders */
  if (window.AHTScreenManager && typeof window.AHTScreenManager.open === 'function') {
    var prevOpen = window.AHTScreenManager.isAnyOpen();
    window.AHTScreenManager.open('vecinos');
    var aside = document.querySelector('[data-aht-screen="vecinos"]');
    var frameEl = aside ? aside.querySelector('.aht-frame') : null;
    if (aside && frameEl) {
      var aRect = aside.getBoundingClientRect();
      var fRect = frameEl.getBoundingClientRect();
      var veloCs = velo ? getComputedStyle(velo) : null;
      test('9.1 aside has non-zero height', aRect.height > 0, 'height=' + aRect.height);
      test('9.2 frame has non-zero height', fRect.height > 0, 'height=' + fRect.height);
      test('9.3 frame visible (opacity)', getComputedStyle(frameEl).opacity === '1');
      test('9.4 frame above backdrop', veloCs ? (getComputedStyle(frameEl).zIndex >= veloCs.zIndex) : false);
      test('9.5 frame inside viewport', fRect.top >= 0 && fRect.left >= 0 && fRect.right <= window.innerWidth);
    } else {
      test('9.1 aside/frame found', false, 'aside=' + !!aside + ' frame=' + !!frameEl);
    }
    window.AHTScreenManager.close();
  }

  /* 10. SHARED VIEW-Switch (Mensajitos + Vecinos) */
  var buzonAside = document.querySelector('[data-aht-screen="buzon"]');
  var vecinosAside = document.querySelector('[data-aht-screen="vecinos"]');
  var buzonTabs = buzonAside ? buzonAside.querySelector('.aht-frame-tabs') : null;
  var vecinosTabs = vecinosAside ? vecinosAside.querySelector('.aht-frame-tabs') : null;
  test('10.1 buzon has .aht-frame-tabs', !!buzonTabs);
  test('10.2 vecinos has .aht-frame-tabs', !!vecinosTabs);
  if (buzonTabs) {
    var bTabs = buzonTabs.querySelectorAll('.aht-frame-tab');
    test('10.3 buzon has 2 tabs', bTabs.length === 2);
    var bActive = buzonTabs.querySelector('.is-active');
    test('10.4 buzon has is-active tab', !!bActive);
    if (bActive) {
      var bCs = getComputedStyle(bActive);
      test('10.5 buzon active bg is accent pink', bCs.backgroundColor === 'rgb(196, 43, 74)', bCs.backgroundColor);
      test('10.6 buzon active text is white', bCs.color === 'rgb(255, 255, 255)', bCs.color);
    }
    var bInactive = Array.from(bTabs).find(function(t){ return !t.classList.contains('is-active'); });
    if (bInactive) {
      var biCs = getComputedStyle(bInactive);
      test('10.7 buzon inactive has lilac bg', biCs.backgroundColor !== 'rgba(0, 0, 0, 0)', biCs.backgroundColor);
    }
  }
  if (vecinosTabs) {
    var vTabs = vecinosTabs.querySelectorAll('.aht-frame-tab');
    test('10.8 vecinos has 2 tabs', vTabs.length === 2);
    var vActive = vecinosTabs.querySelector('.is-active');
    test('10.9 vecinos has is-active tab', !!vActive);
    if (vActive) {
      var vCs = getComputedStyle(vActive);
      test('10.10 vecinos active bg is accent pink', vCs.backgroundColor === 'rgb(196, 43, 74)', vCs.backgroundColor);
      test('10.11 vecinos active text is white', vCs.color === 'rgb(255, 255, 255)', vCs.color);
    }
  }
  test('10.12 tabs share same CSS class names',
    !!buzonTabs && !!vecinosTabs &&
    buzonTabs.className === vecinosTabs.className);

  /* Print */
  var pass = 0, fail = 0;
  console.log('\n=== AHT V4 Architecture Test (FASE 5) ===');
  results.forEach(function (r) {
    var icon = r.ok ? 'PASS' : 'FAIL';
    console.log('[' + icon + '] ' + r.name + (r.detail ? ' -- ' + r.detail : ''));
    if (r.ok) pass++; else fail++;
  });
  console.log('--------------------------------');
  console.log('Total: ' + (pass+fail) + ' | Pass: ' + pass + ' | Fail: ' + fail);
  console.log('================================\n');
  return { pass: pass, fail: fail, results: results };
})();