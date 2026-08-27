'use strict';
/**
 * Modal Vecinos — contrato estático visual + JS (móvil + desktop).
 * Referencia: mockup vecinos modal (2 cols móvil, tarjetas papel, tabs, buscador).
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/play-v3-vecinos.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

// HTML: estructura cabecera + tabs + paneles
ok(/class="ds-modal-head vecinos-head"/.test(php), 'play.php: cabecera vecinos-head');
ok(/data-vec-tab="vecinos"/.test(php) && /data-vec-tab="relaciones"/.test(php), 'play.php: tabs Vecinos/Relaciones');
ok(/data-vec-panel="vecinos"/.test(php) && /data-vec-panel="relaciones"/.test(php), 'play.php: paneles vecinos/relaciones');
ok(/data-vec-busca/.test(php), 'play.php: buscador vecinos');
ok(/data-vecinos-list/.test(php), 'play.php: grid data-vecinos-list');
ok(!/play-v3-inicio/.test(php.match(/capa-vecinos[\s\S]{0,200}/)?.[0] || ''), 'play.php: vecinos sin tocar inicio');

// JS: tarjetas mockup + tabs relaciones + datos dinámicos
ok(/function emoPillVecino/.test(js), 'js: emoPillVecino para pills reales');
ok(/vecino-celda--decor-/.test(js), 'js: decoración variada por índice');
ok(/emoPillVecino\(emo, genero\)/.test(js), 'js: pill con estado real en renderVecinos');
ok(/VER FICHA/.test(js), 'js: botón VER FICHA en tarjeta');
ok(/function setVecTab/.test(js) && /function cargarVecRelaciones/.test(js), 'js: pestaña Relaciones operativa');
ok(/metricasSociales\(cacheInsp/.test(js), 'js: contador dinámico vecinos/cap');
ok(/textoAnimoFichaPill/.test(js), 'js: labels de ánimo del sistema (no inventados)');
ok(js.includes('return { vecinos: ids.length, cap: cap'),
  'js: metricasSociales intacto (no corrupto)');

// CSS móvil: 2 columnas, papel, tarjetas
ok(/grid-template-columns:\s*1fr 1fr/.test(css), 'css móvil: grid 2 columnas');
ok(/\.capa-vecinos::after[\s\S]{0,120}cinta|washi|235,215,175/i.test(css) ||
   /\.capa-vecinos::after/.test(css), 'css: cinta decorativa modal');
ok(/\.vecino-celda--decor-0::before/.test(css), 'css: decoraciones tarjeta variadas');
ok(/\.vecino-cara[\s\S]{0,80}76px/.test(css), 'css móvil: avatar grande (~76px)');
ok(/\.vecino-ver/.test(css) && /\.vecino-emo-pill/.test(css), 'css: pill emo + VER FICHA');
ok(/\.vec-tab\.is-on[\s\S]{0,80}background:\s*var\(--ds-pink/.test(css), 'css: tab Vecinos activo rosa');
ok(/\.vec-tab:not\(\.is-on\)|\.vec-tab\s*\{[^}]*dashed|border:\s*2px dashed/.test(css), 'css: tab inactivo borde discontinuo');
ok(css.includes('.vecinos-cuenta.ds-pill') && css.includes('color: #fff !important'),
  'css: badge contador rosa/blanco');

// CSS desktop: más ancho + más columnas (no estirar móvil)
ok(/@media \(min-width: 769px\)[\s\S]{0,500}720px/.test(css), 'css desktop: modal más ancha');
ok(/@media \(min-width: 769px\)[\s\S]{0,800}repeat\(3, 1fr\)/.test(css), 'css desktop: 3 columnas');
ok(/@media \(min-width: 1024px\)[\s\S]{0,400}repeat\(4, 1fr\)/.test(css), 'css desktop xl: 4 columnas');

// Alcance: no reglas globales fuera de capa-vecinos (salvo media con pc selector)
const rulesOutside = css.replace(/\/\*[\s\S]*?\*\//g, '').split('\n').filter(function (line) {
  const t = line.trim();
  if (!t || t.startsWith('@media') || t === '}' || t.endsWith('{')) return false;
  return t.includes('{') && !t.includes('.capa-vecinos') && !t.includes('.play-root.pc[data-capa="vecinos"]') && !t.includes('.play-root.phone[data-capa="vecinos"]');
});
ok(rulesOutside.length === 0, 'css: reglas acotadas a capa-vecinos / data-capa vecinos');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\nvecinos_modal_ui_test OK');
