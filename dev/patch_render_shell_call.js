const fs = require('fs');
const jsPath = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

if (!js.includes('renderShellPanels(cacheEstado')) {
  js = js.replace(
    `    renderHud(cacheEstado, buzon.mensajes || []);
    renderPueblo(mapa.pueblo || { complejos: [] });
    renderBuzon(buzon.mensajes || []);`,
    `    renderHud(cacheEstado, buzon.mensajes || []);
    renderPueblo(mapa.pueblo || { complejos: [] });
    renderShellPanels(cacheEstado, buzon.mensajes || [], diario);
    renderBuzon(buzon.mensajes || []);`
  );
}

js = js.replace(
  `      const ids = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; }).slice(0, 3);
      puebloFaces.innerHTML = '';
      if (!ids.length) {
        puebloFaces.innerHTML = '<span class="cara-ini">?</span><span class="cara-ini">?</span><span class="cara-ini">?</span>';`,
  `      let ids = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; }).slice(0, 3);
      if (!ids.length && cachePueblo && cachePueblo.tokens) {
        ids = Object.keys(cachePueblo.tokens).slice(0, 3);
      }
      puebloFaces.innerHTML = '';
      if (!ids.length) {
        puebloFaces.innerHTML = '<span class="cara-silueta"></span><span class="cara-silueta"></span><span class="cara-silueta"></span>';`
);

js = js.replace(/\u00b7/g, '·').replace(/join\('[^']*'\)/g, function (m) {
  if (m.includes('prox-nombres') || m.includes('prox_meta')) return m;
  return m;
});

// Fix broken middle dot in proximo if present
js = js.replace(/join\('[^']*'\)/g, function (m) {
  if (m.startsWith("join('") && m.includes(' ')) return "join(' · ')";
  return m;
});

fs.writeFileSync(jsPath, js);
console.log('refresh hook ok', js.includes('renderShellPanels(cacheEstado'));
