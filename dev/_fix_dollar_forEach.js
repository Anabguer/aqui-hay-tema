const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8');
const bad = "$('[data-org-modo]').forEach";
const good = "$$('[data-org-modo]').forEach";
const n = (s.match(new RegExp(bad.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length;
if (n === 0) {
  console.error('nothing to fix');
  process.exit(1);
}
s = s.split(bad).join(good);
fs.writeFileSync(p, s);
console.log('fixed', n, 'occurrences');
