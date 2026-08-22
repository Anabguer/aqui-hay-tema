const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8');
s = s.replace('const btnProx = #btn-debug-proximo;', "const btnProx = $('#btn-debug-proximo');");
if (!s.includes("$('#btn-debug-proximo')")) {
  console.error('not fixed');
  process.exit(1);
}
// Clear intro flag on nueva partida
const needle = `  async function nuevaPartidaLimpia() {
    localStorage.removeItem(storageKey());`;
const repl = `  async function nuevaPartidaLimpia() {
    try { localStorage.removeItem(tutIntroKey()); } catch (e) {}
    localStorage.removeItem(storageKey());`;
if (s.includes(needle) && !s.includes('removeItem(tutIntroKey')) {
  s = s.replace(needle, repl);
}
fs.writeFileSync(p, s);
console.log('hotfix ok');
