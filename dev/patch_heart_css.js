const fs = require('fs');
const p = 'W:/juegos/aqui-hay-tema/assets/css/play-v3-shell-ui.css';
let c = fs.readFileSync(p, 'utf8');
c = c.replace(
  '.top-vida { display: flex; flex-direction: column; align-items: center; gap: .08rem; justify-self: end; padding-right: .15rem; }',
  '.top-vida { display: flex; flex-direction: column; align-items: center; gap: .04rem; justify-self: end; padding-right: .2rem; min-width: 72px; }'
);
c = c.replace(
  '.obj-vida-kicker { font-size: .5rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #8a7a66; }',
  '.obj-vida-kicker { font-size: .46rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #a89888; opacity: .85; }'
);
c = c.replace(
  '.corazon-stroke { stroke: #2c261f; stroke-width: 2.6; stroke-linejoin: round; }',
  '.corazon-stroke { stroke: #2c261f; stroke-width: 3; stroke-linejoin: round; }'
);
fs.writeFileSync(p, c);
console.log('heart prominence ok');
