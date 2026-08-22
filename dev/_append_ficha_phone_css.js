const fs = require('fs');
const p = 'assets/css/play-v3-bloques-residencias.css';
let c = fs.readFileSync(p, 'utf8');
if (c.includes('phone[data-capa="ficha"] .capa-ficha')) {
  console.log('already');
  return;
}
fs.appendFileSync(
  p,
  `
@media (max-width: 520px) {
  .play-v3 .play-root.phone[data-capa="ficha"] .capa-ficha {
    left: 8px !important;
    right: 8px !important;
    top: auto !important;
    bottom: 74px !important;
    max-height: 72% !important;
    transform: none !important;
    width: auto !important;
    max-width: none !important;
    padding: 1.35rem .75rem .7rem !important;
  }
}
`
);
console.log('phone css added');
