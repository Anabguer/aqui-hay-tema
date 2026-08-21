const fs = require('fs');
const p = require('path').join(__dirname, '..', 'assets/js/play-v3.js');
let js = fs.readFileSync(p, 'utf8');

if (!js.includes('abrirConsultaZona(zona.getAttribute')) {
  js = js.replace(
    `    const cx = ev.target.closest('.complejo[data-complejo]');\r\n    if (cx) {\r\n      abrirConsulta(cx.getAttribute('data-complejo'));\r\n    }`,
    `    const zona = ev.target.closest('.mapa-zona-hit[data-zona]');\r\n    if (zona) {\r\n      abrirConsultaZona(zona.getAttribute('data-zona'));\r\n      return;\r\n    }\r\n    const cx = ev.target.closest('.complejo[data-complejo]');\r\n    if (cx) {\r\n      abrirConsulta(cx.getAttribute('data-complejo'));\r\n    }`
  );
}

if (!js.includes('initMapaCanonico().then')) {
  js = js.replace(
    `  ensurePartida().then(function () {\r\n    return refresh().then(function () { quizaMostrarTutIntro(); });\r\n  });`,
    `  initMapaCanonico().then(function () {\r\n    return ensurePartida().then(function () {\r\n      return refresh().then(function () { quizaMostrarTutIntro(); });\r\n    });\r\n  });`
  );
}

fs.writeFileSync(p, js);
console.log('ok', js.includes('initMapaCanonico().then'), js.includes('abrirConsultaZona(zona.getAttribute'));
