const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8');
const bad = "('[data-vec-filtro]').forEach";
const good = "$$('[data-vec-filtro]').forEach";
const n = (s.split(bad).length - 1);
if (n === 0) {
  console.error('pattern not found');
  process.exit(1);
}
s = s.split(bad).join(good);
fs.writeFileSync(p, s, 'utf8');
console.log('fixed', n);
