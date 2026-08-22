/**
 * Próximo plan + Parejas: usar tokenDe() y cargar cachePueblo antes de renderShellPanels.
 * node dev/_patch_retratos_tokens.js
 */
const fs = require('fs');
const p = require('path').join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(p, 'utf8');
const nl = s.includes('\r\n') ? '\r\n' : '\n';
let n = 0;

function rep(from, to, label) {
  const fromNl = from.replace(/\n/g, nl);
  const toNl = to.replace(/\n/g, nl);
  if (!s.includes(fromNl)) {
    console.error('MISSING:', label);
    process.exit(1);
  }
  s = s.replace(fromNl, toNl);
  n++;
  console.log('OK:', label);
}

rep(
  `  function carasPlanHtml(ids) {
    return ids.slice(0, 2).map(function (id) {
      const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
      if (t && t.url) return '<img src="' + esc(t.url) + '" alt=""/>';`,
  `  function carasPlanHtml(ids) {
    return ids.slice(0, 2).map(function (id) {
      const img = tokenDe(id);
      if (img) return '<img src="' + esc(img) + '" alt=""/>';`,
  'carasPlanHtml usa tokenDe'
);

rep(
  `        const tok = function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) return '<img class="obj-pareja-cara" src="' + esc(t.url) + '" alt=""/>';`,
  `        const tok = function (id) {
          const img = tokenDe(id);
          if (img) return '<img class="obj-pareja-cara" src="' + esc(img) + '" alt=""/>';`,
  'parejas tok usa tokenDe'
);

rep(
  `    renderHud(cacheEstado, buzon.mensajes || []);
    renderShellPanels(cacheEstado, buzon.mensajes || [], diario);
      renderMisiones(cacheEstado.misiones_hoy || (cacheInsp && cacheInsp.misiones_diarias));
    renderPueblo(mapa.pueblo || { complejos: [] });`,
  `    renderHud(cacheEstado, buzon.mensajes || []);
    renderPueblo(mapa.pueblo || { complejos: [] });
    renderShellPanels(cacheEstado, buzon.mensajes || [], diario);
      renderMisiones(cacheEstado.misiones_hoy || (cacheInsp && cacheInsp.misiones_diarias));`,
  'refresh: renderPueblo antes de renderShellPanels'
);

fs.writeFileSync(p, s);
console.log('patched', n, 'blocks');
