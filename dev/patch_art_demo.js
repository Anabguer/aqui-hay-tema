/* eslint-disable */
const fs = require('fs');
const path = require('path');
const jsPath = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(jsPath, 'utf8');

s = s.replace(
  `    if (reales.length) return reales;
    if (IS_LAB && (ART_DEMO || !reales.length)) return LAB_DEMO_PAREJAS;
    return [];`,
  `    if (ART_DEMO) return LAB_DEMO_PAREJAS;
    if (reales.length) return reales;
    if (IS_LAB) return LAB_DEMO_PAREJAS;
    return [];`
);

s = s.replace(
  `      else if (IS_LAB && !parejasReales.length) {
        const note = document.createElement('p');
        note.className = 'obj-lab-note';
        note.textContent = 'Vista demo (lab)';
        strip.insertBefore(note, strip.firstChild);
      }`,
  `      else if (IS_LAB && ART_DEMO) {
        const note = document.createElement('p');
        note.className = 'obj-lab-note';
        note.textContent = 'Vista demo (lab)';
        strip.insertBefore(note, strip.firstChild);
      }`
);

if (!s.includes('ART_DEMO_COTILLEO')) {
  s = s.replace(
    `    if (teaser) teaser.textContent = ult || 'Todav\u00eda no hay cotilleo hoy.';`,
    `    if (teaser) {
      teaser.textContent = ult || (ART_DEMO
        ? 'Dicen que alguien ha visto a Marta salir demasiado pronto\u2026'
        : 'Todav\u00eda no hay cotilleo hoy.');
    }`
  );
}

s = s.replace(
  `    const badge = $('[data-buzon-badge]');
    if (badge) {
      badge.textContent = String(pend.length);`,
  `    const badge = $('[data-buzon-badge]');
    const buzonCount = ART_DEMO ? Math.max(pend.length, 2) : pend.length;
    if (badge) {
      badge.textContent = String(buzonCount);`
);

s = s.replace(
  `      if (pend.length > 0) {
        badge.hidden = false;
        badge.classList.add('is-on');
      } else {
        badge.hidden = true;
        badge.classList.remove('is-on');
      }`,
  `      if (buzonCount > 0) {
        badge.hidden = false;
        badge.classList.add('is-on');
      } else {
        badge.hidden = true;
        badge.classList.remove('is-on');
      }`
);

fs.writeFileSync(jsPath, s);
console.log('art_demo fixtures applied');
