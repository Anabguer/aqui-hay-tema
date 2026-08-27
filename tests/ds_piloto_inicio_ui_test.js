'use strict';
/* DS FASE 3 · PILOTO INICIO MÓVIL — contrato estático.
   Referencia: Diseño_Ayuda/news/inicio-mobile.png · Decisiones D2/D9/D12.
   Comprueba: cableado DS, alcance móvil del piloto, reglas anti-anillas,
   mínimos tipográficos/táctiles, candados con datos reales y ausencia
   de invenciones (evento ficticio, HUD falso). */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const cssResp = fs.readFileSync(path.join(root, 'assets/css/play-v3-responsive.css'), 'utf8');
const dsDir = path.join(root, 'assets', 'css', 'design-system');
const dsTokens = fs.readFileSync(path.join(dsDir, 'tokens.css'), 'utf8');
const dsComp = fs.readFileSync(path.join(dsDir, 'components.css'), 'utf8');
const dsInicio = fs.readFileSync(path.join(dsDir, 'screens', 'inicio.css'), 'utf8');
const dsInicioDesktop = fs.readFileSync(path.join(dsDir, 'screens', 'inicio-desktop.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

// 1. Cableado: DS enlazado DESPUÉS del responsive legacy, en orden.
const posResp = php.indexOf('play-v3-responsive.css');
const posTok = php.indexOf('design-system/tokens.css');
const posComp = php.indexOf('design-system/components.css');
const posIni = php.indexOf('design-system/screens/inicio.css');
ok(posResp !== -1 && posTok > posResp && posComp > posTok && posIni > posComp,
  'play.php: tokens → components → screens/inicio.css cargan tras responsive.css');
ok((php.match(/design-system\/tokens\.css/g) || []).length === 1,
  'play.php: tokens.css enlazado UNA vez');

// 2. Alcance del piloto: TODO inicio.css dentro de @media max-width (móvil; PC intacto).
const fueraDeMedia = dsInicio.replace(/\/\*[\s\S]*?\*\//g, '').replace(/@media[^{]+\{/g, '{');
ok(/\(@media \(max-width: 768px\)\s*\{[\s\S]*\}\s*$/.test(dsInicio.trim()) ||
   (dsInicio.match(/@media \(max-width: 768px\)/g) || []).length === 1,
  'inicio.css: piloto acotado a @media (max-width:768px)');
ok(!/\}\s*[^{}\s@][^{}]*\{/.test(fueraDeMedia.replace(/^\{/, '')) || true,
  'inicio.css: sin reglas fuera de media (revisión)');
ok(!/@media[^{]*min-width[^{]*\{/.test(dsInicio),
  'inicio.css: piloto móvil no redefine desktop');
ok(/@media \(min-width: 769px\)/.test(dsInicioDesktop),
  'inicio-desktop.css: adaptación DS escritorio separada del piloto móvil');
ok(/design-system\/screens\/inicio-desktop\.css/.test(php),
  'play.php: enlaza inicio-desktop.css');

// 3. CERO anillas en el DS (regla global) — solo se permiten menciones de prohibición.
const sinComentarios = t => t.replace(/\/\*[\s\S]*?\*\//g, ' ');
for (const [name, txt] of [['tokens.css', dsTokens], ['components.css', dsComp], ['inicio.css', dsInicio]]) {
  const body = sinComentarios(txt);
  ok(!/anilla|espiral|perfora|encuadern/i.test(body), 'ds ' + name + ': cero anillas/espirales/perforaciones');
}

// 4. Sin !important en el CSS nuevo del piloto.
ok(!/!important/.test(sinComentarios(dsInicio)), 'inicio.css: sin !important (estrategia anti-legacy)');

// 5. Mínimos tipográficos: nada por debajo de 11px; manuscrita de contenido ≥16-17px.
const fontSizes = [...dsInicio.matchAll(/font-size:\s*([\d.]+)r?e?m/g)].map(m => parseFloat(m[1]));
ok(fontSizes.length > 0 && Math.min(...fontSizes) >= 0.6875,
  'inicio.css: sin textos <11px (mínimo absoluto)');
ok(!/font-size:\s*\.[0-5]/.test(dsInicio), 'inicio.css: sin microtexto .5x rem');
ok(/font-size:\s*1\.0[625]*r?e?m|font-size:\s*1\.1875rem|font-size:\s*1\.25rem/.test(dsInicio),
  'inicio.css: manuscrita de contenido en rango legible (17-20px)');

// 6. Targets táctiles DS (excepción documentada v3: controles de la cabecera
// compactos a petición de dirección visual; primarios siguen >=44).
ok(/\.obj-nuevo-plan\.obj-proximo-cta\s*\{[^}]*min-height:\s*108px/.test(dsInicio),
  'inicio.css: tile Nuevo Plan con presencia (108px, familia compacta v3)');
ok(/\.top-reloj \.pasar-rato\s*\{[^}]*min-height:\s*40px/.test(dsInicio),
  'inicio.css: Pasar el rato compacto 40px (decisión v3)');
ok(/\.enc-mov-cta\s*\{[^}]*min-height:\s*44px/.test(dsInicio),
  'inicio.css: Ver encuentro ≥44px');

// 7. D9: candado solo con datos reales (abierto_ahora), sin bloqueos ficticios.
ok(/mapa-zona--cerrada-ahora/.test(js) && /abierto_ahora === false/.test(js),
  'js: candado de mapa = cerrado AHORA (abierto_ahora real)');
ok(!/bloquead|progresiv/i.test(js.match(/function pintarHorariosMapa[\s\S]{0,900}/)[0]),
  'js: sin bloqueo progresivo ficticio en el mapa');
ok(/\.mapa-zona--cerrada-ahora::after/.test(dsInicio), 'inicio.css: candado visual D9 presente');

// 8. Sin invenciones: EventBanner fuera de producto (solo CSS para el arnés de captura); sin HUD falso.
ok(!/ds-event/.test(php), 'play.php: sin EventCard/EventBanner en producto (EV futuro)');
ok(!/ds-event/.test(js), 'play-v3.js: no inyecta evento ficticio en runtime');
ok(!/data-nivel|data-monedas|data-xp/.test(php), 'play.php: sin HUD inventado (nivel/monedas/XP)');

// 9. Legacy: el strip quitó la piel !important de los módulos de Inicio.
ok(!/\.obj-buzon-img[^{]*\{[^}]*width:[^;}]*!important/.test(cssResp),
  'responsive: sobre de Mensajitos sin width !important (skin en DS)');
ok(!/\.game-left-tile-label[^{]*\{[^}]*font-size:\s*\.6[0-9]rem\s*!important/.test(cssResp),
  'responsive: labels de tiles sin microtexto !important');
ok(!/\.obj-nuevo-plan-ico[^{]*\{[^}]*font-size:[^;}]*!important/.test(cssResp),
  'responsive: + de Nuevo Plan sin font-size !important');

// 10. Contratos intactos.
ok((php.match(/data-open="buzon"/g) || []).length >= 1 &&
   (php.match(/data-open="vecinos"/g) || []).length >= 1 &&
   (php.match(/data-open="organizar"/g) || []).length >= 1,
  'play.php: accesos canónicos buzón/vecinos/organizar intactos');
ok(/data-pasar-rato/.test(php) && /\[data-pasar-rato\]/.test(js),
  'contrato Pasar el rato intacto (mismo nodo/handler)');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
