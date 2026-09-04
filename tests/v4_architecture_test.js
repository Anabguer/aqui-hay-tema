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
    test('3.2 position:fixed', fcs.position === 'fixed');
    test('3.3 border-radius', fcs.borderRadius !== '0px');
    test('3.4 box-shadow', fcs.boxShadow !== 'none');
    var fOk = true;
    allF.forEach(function (f) { if (getComputedStyle(f).position !== 'fixed') fOk = false; });
    test('3.5 Frame uniform all screens', fOk);
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