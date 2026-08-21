const fs = require('fs');
const p = 'assets/js/play-v3.js';
let s = fs.readFileSync(p, 'utf8');
const re = /  const \$ = \(sel, root\) => \(root \|\| document\)\.querySelector\(sel\);\r?\n  const \$ = \(sel, root\) => Array\.from\(\(root \|\| document\)\.querySelectorAll\(sel\)\);/;
if (!re.test(s)) {
  console.error('pattern not found');
  process.exit(1);
}
s = s.replace(re, function () {
  return '  const $ = (sel, root) => (root || document).querySelector(sel);\n  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));';
});
fs.writeFileSync(p, s);
console.log('fixed $$');
