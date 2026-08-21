/* eslint-disable */
const fs = require('fs');
const path = require('path');
const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(jsPath, 'utf8');

s = s.replace(
  `btn.innerHTML = '<span class="res-fachada" aria-hidden="true"><span class="res-letra">' + d.label + '</span></span>' +
            '<span class="res-meta">Pr\\u00f3ximamente</span>';`,
  `btn.innerHTML = '<span class="res-fachada" aria-hidden="true"></span>' +
            '<span class="res-id">' + d.label + '</span>' +
            '<span class="res-meta">Pr\\u00f3ximamente</span>';`
);

s = s.replace(
  `btn.innerHTML = '<span class="res-fachada" aria-hidden="true"><span class="res-letra">' + d.label + '</span></span>' +
            '<span class="res-meta">' + occ + '/' + cap + '</span>';`,
  `btn.innerHTML = '<span class="res-fachada" aria-hidden="true"></span>' +
            '<span class="res-id">' + d.label + '</span>' +
            '<span class="res-meta">' + occ + '/' + cap + '</span>';`
);

fs.writeFileSync(jsPath, s);
console.log('block labels patched');
