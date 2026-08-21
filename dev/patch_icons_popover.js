const fs = require('fs');
const jsPath = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');
js = js.replace(
  /lines\.push\(\{ icon: '[^']*', k: 'Vecinos', v: String\(met\.vecinos\) \}\);/,
  "lines.push({ icon: '·', k: 'Vecinos', v: String(met.vecinos) });"
);
js = js.replace(
  /if \(met\.parejas\) lines\.push\(\{ icon: '[^']*', k: 'Parejas'/,
  "if (met.parejas) lines.push({ icon: '♥', k: 'Parejas'"
);
js = js.replace(
  /if \(met\.crisis\) lines\.push\(\{ icon: '[^']*', k: 'En crisis'/,
  "if (met.crisis) lines.push({ icon: '!', k: 'En crisis'"
);
js = js.replace(
  /if \(met\.emo\.alegre\) lines\.push\(\{ icon: '[^']*', k: 'Alegres'/,
  "if (met.emo.alegre) lines.push({ icon: '+', k: 'Alegres'"
);
js = js.replace(
  /if \(met\.emo\.triste\) lines\.push\(\{ icon: '[^']*', k: 'Tristes'/,
  "if (met.emo.triste) lines.push({ icon: '-', k: 'Tristes'"
);
js = js.replace(
  /if \(met\.emo\.enfadado\) lines\.push\(\{ icon: '[^']*', k: 'Enfadados'/,
  "if (met.emo.enfadado) lines.push({ icon: 'x', k: 'Enfadados'"
);
fs.writeFileSync(jsPath, js);

const cssPath = 'W:/juegos/aqui-hay-tema/assets/css/play-v3.css';
let pv3 = fs.readFileSync(cssPath, 'utf8');
if (!pv3.includes('display: none !important')) {
  pv3 = pv3.replace(
    `.selector { right: 3%; top: 12%; }\r\n.quien { right: 3%; top: 10%; max-height: 68%; overflow: auto; }`,
    `.selector { display: none !important; }\r\n.quien {\r\n  position: absolute; left: 0; top: 0; right: auto;\r\n  max-width: min(240px, 46%); max-height: 55%; overflow: auto;\r\n  z-index: 45; box-shadow: 4px 6px 0 rgba(44,38,31,.12);\r\n}`
  );
  fs.writeFileSync(cssPath, pv3);
  console.log('popover css ok');
} else {
  console.log('popover css already');
}
