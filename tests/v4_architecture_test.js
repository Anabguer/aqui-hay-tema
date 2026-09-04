/* ============================================================
   AHT V4 — Architecture Verification Test
   Demuestra ownership limpio de X y Frame en V4.

   Uso: pegar en consola del navegador con el juego abierto.
   ============================================================ */
(function () {
  'use strict';

  var results = [];
  var PASS = 'PASS';
  var FAIL = 'FAIL';

  function test(name, ok, detail) {
    results.push({ name: name, ok: ok, detail: detail || '' });
  }

  // 1. Backdrop V4 ownership
  var velo = document.querySelector('.aht-velo');
  test('aht-velo element exists', !!velo);
  if (velo) {
    var cs = getComputedStyle(velo);
    test('aht-velo position is fixed', cs.position === 'fixed');
    test('aht-velo opacity is 0 when hidden', cs.opacity === '0' || velo.classList.contains('aht-velo--visible'));
    var veloLegacy = document.querySelector('.play-root > .velo');
    test('legacy .velo is separate element', !!veloLegacy && veloLegacy !== velo);
  }

  // 2. X button ownership
  var xBtn = document.querySelector('.aht-frame-close');
  test('aht-frame-close element exists', !!xBtn);
  if (xBtn) {
    var xcs = getComputedStyle(xBtn);
    test('X has grid display', xcs.display === 'grid');
    test('X has cursor pointer', xcs.cursor === 'pointer');
    test('X has position absolute', xcs.position === 'absolute');
  }

  // 3. Frame ownership
  var frame = document.querySelector('.aht-frame');
  test('.aht-frame element exists', !!frame);
  if (frame) {
    var fcs = getComputedStyle(frame);
    test('Frame has position fixed', fcs.position === 'fixed');
    test('Frame has border-radius', fcs.borderRadius !== '0px');
    test('Frame has box-shadow', fcs.boxShadow !== 'none');
  }

  // 4. Screen visibility
  var screens = document.querySelectorAll('.aht-screen');
  test('19 V4 screens exist', screens.length === 19);

  // 5. Token ownership
  var rootStyle = getComputedStyle(document.documentElement);
  var tokens = ['--aht-z-velo', '--aht-z-frame', '--aht-overlay', '--aht-surface-raised', '--aht-border', '--aht-frame-radius', '--aht-font-hand', '--aht-dur'];
  var missingTokens = [];
  tokens.forEach(function (t) {
    var val = rootStyle.getPropertyValue(t).trim();
    if (!val) missingTokens.push(t);
  });
  test('All V4 CSS tokens defined', missingTokens.length === 0, missingTokens.length > 0 ? 'Missing: ' + missingTokens.join(', ') : '');

  // 6. Screen Manager API
  var sm = window.AHTScreenManager;
  test('AHTScreenManager global exists', !!sm);
  if (sm) {
    test('SM has open()', typeof sm.open === 'function');
    test('SM has close()', typeof sm.close === 'function');
    test('SM has isAnyOpen()', typeof sm.isAnyOpen === 'function');
    test('SM has getCurrent()', typeof sm.getCurrent === 'function');
    test('SM has V4_SCREENS set', sm.V4_SCREENS instanceof Set);
    test('SM V4_SCREENS has 18 screens', sm.V4_SCREENS.size === 18);
  }

  // Print results
  var pass = 0, fail = 0;
  console.log('\n=== AHT V4 Architecture Test ===');
  results.forEach(function (r) {
    var icon = r.ok ? 'PASS' : 'FAIL';
    console.log('[' + icon + '] ' + r.name + (r.detail ? ' -- ' + r.detail : ''));
    if (r.ok) pass++; else fail++;
  });
  console.log('--------------------------------');
  console.log('Total: ' + (pass + fail) + ' | Pass: ' + pass + ' | Fail: ' + fail);
  console.log('================================\n');

  return { pass: pass, fail: fail, results: results };
})();
