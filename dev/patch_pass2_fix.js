/* eslint-disable */
const fs = require('fs');
const path = require('path');
const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let js = fs.readFileSync(jsPath, 'utf8');
js = js.replace('const hMax = 50;', 'const hMax = 52;');
js = js.replace(
  `    $('[data-dinero]').forEach(function (el) {
      const v = dineroTxt(cacheInsp, estado);
      el.textContent = el.classList.contains('obj-dinero-txt') ? ('Dinero: ' + v) : v;
    });`,
  `    $$('[data-dinero]').forEach(function (el) {
      const v = dineroTxt(cacheInsp, estado);
      el.textContent = el.classList.contains('obj-dinero-txt') ? ('Dinero: ' + v) : v;
    });`
);
fs.writeFileSync(jsPath, js);
console.log('heart + dinero fix');
