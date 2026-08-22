const fs = require('fs');
const jsPath = 'assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

js = js.replace('let fichaRelCache = [];', '  let fichaRelCache = [];');

const oldHandler = `  document.body.addEventListener('click', function (ev) {
    const t = ev.target.closest('[data-close], .velo');
    if (t && uiRootFrom(t)) {
      setCapa('');
      $('.play-root').removeAttribute('data-consulta');
      return;
    }`;

const newHandler = `  document.body.addEventListener('click', function (ev) {
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
    }`;

if (!js.includes(oldHandler)) {
  console.error('handler not found');
  process.exit(1);
}
js = js.replace(oldHandler, newHandler);
fs.writeFileSync(jsPath, js, 'utf8');
console.log('close handler ok');
