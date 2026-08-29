const fs = require('fs');
const assert = require('assert');

const tokens = fs.readFileSync('assets/css/design-system/tokens.css', 'utf8');
const components = fs.readFileSync('assets/css/design-system/components.css', 'utf8');
const reading = fs.readFileSync('assets/css/design-system/typography-reading.css', 'utf8');
const play = fs.readFileSync('play.php', 'utf8');
const js = fs.readFileSync('assets/js/play-v3.js', 'utf8');

assert(tokens.includes('--aht-type-body: clamp(1rem'), 'falta token body');
assert(tokens.includes('--aht-type-secondary: clamp(0.875rem'), 'falta token secondary');
assert(tokens.includes('--aht-type-caption: clamp(0.8125rem'), 'falta token caption');
assert(tokens.includes('--aht-type-body: 1rem;'), 'falta piso movil body 16px');
assert(tokens.includes('--aht-type-caption: 0.8125rem;'), 'falta piso movil caption 13px');

assert(components.includes('.ds-text-body'), 'falta clase ds-text-body');
assert(components.includes('font-family: var(--ds-font-ui)'), 'ds-text-body debe usar Nunito');

assert(play.includes('typography-reading.css'), 'play.php debe cargar typography-reading.css');
assert(reading.includes('TYPO-READING-v2'), 'falta capa TYPO-READING-v2');
assert(reading.includes('--aht-read-font'), 'falta token lectura shorthand');
assert(reading.includes('.capa-buzon .carta-msg .cuerpo'), 'falta override cuerpo mensajitos');
assert(/\.msg-eleccion-nom[\s\S]*Caveat/.test(reading), 'nombres eleccion deben ser Caveat');
assert(/\.carta-msg \.cuerpo[\s\S]*--aht-read-font/.test(reading), 'cuerpo debe usar aht-read-font');
assert(reading.includes('.capa-mentes'), 'MENTES debe tener reglas legibles');

assert(js.includes('function htmlAvatarEleccion'), 'falta htmlAvatarEleccion');
assert(/htmlAvatarEleccion\(o\.personaje_id, o\.nombre\)/.test(js), 'elecciones deben renderizar avatar');

console.log('typography_reading_static_test OK');
