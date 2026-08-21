const fs = require('fs');
const p = 'W:/juegos/aqui-hay-tema/assets/css/play-v3-shell-ui.css';
let c = fs.readFileSync(p, 'utf8');
if (!c.includes('cara-silueta')) {
  const block = `
.cara-silueta {
  width: 28px; height: 28px; border-radius: 50%; flex: 0 0 28px;
  margin-left: -10px; border: 2px solid #fff; background: #e8dfd0;
  box-shadow: 0 1px 2px rgba(44,38,31,.1); position: relative; overflow: hidden;
}
.cara-silueta:first-child { margin-left: 0; }
.cara-silueta::before {
  content: ""; position: absolute; top: 5px; left: 50%; transform: translateX(-50%);
  width: 10px; height: 10px; border-radius: 50%; background: #c9b59a;
}
.cara-silueta::after {
  content: ""; position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%);
  width: 16px; height: 12px; border-radius: 50% 50% 0 0; background: #c9b59a;
}`;
  c = c.replace(
    '.obj-pueblo-faces .cara-ini { display: grid; place-items: center; font-size: .65rem; font-weight: 800; }',
    '.obj-pueblo-faces .cara-ini { display: grid; place-items: center; font-size: .65rem; font-weight: 800; }' + block
  );
  fs.writeFileSync(p, c);
}
console.log('css siluetas', c.includes('cara-silueta'));
