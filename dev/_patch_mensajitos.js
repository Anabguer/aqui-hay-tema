const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8');
const helpers = fs.readFileSync('dev/_patch_mensajitos_helpers.js', 'utf8');
if (!s.includes('function esIdInterno')) {
  s = s.replace('  function cuerpoCarta(m, de) {', helpers + '  function cuerpoCarta(m, de) {');
}
const newRender = fs.readFileSync('dev/_patch_mensajitos_render.js', 'utf8');
const re = /  function renderBuzon\(msgs\) \{[\s\S]*?  \}\r?\n\r?\n  function renderCotilleo/;
if (!re.test(s)) { console.error('pattern fail'); process.exit(1); }
s = s.replace(re, newRender.trim() + '\r\n\r\n  function renderCotilleo');
fs.writeFileSync(p, s, 'utf8');
console.log('patched');
