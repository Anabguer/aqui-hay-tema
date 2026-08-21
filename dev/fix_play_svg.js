/* eslint-disable */
const fs = require('fs');
const path = require('path');
const phpPath = path.join(__dirname, '..', 'play.php');
let s = fs.readFileSync(phpPath, 'utf8');

// Remove BOM if present
if (s.charCodeAt(0) === 0xfeff) s = s.slice(1);

const broken = /<svg class="corazon-svg corazon-org" viewBox="0 0 58 52"[^>]*>/;
const fixed = `<svg class="corazon-svg corazon-org" viewBox="0 0 58 52" width="68" height="62" aria-hidden="true">
          <defs><filter id="corazon-hand" x="-5%" y="-5%" width="110%" height="110%"><feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="0.8"/></filter></defs>`;

if (broken.test(s)) {
  s = s.replace(broken, fixed);
  fs.writeFileSync(phpPath, s, 'utf8');
  console.log('SVG fixed');
} else {
  console.log('SVG already ok or pattern not found');
}
