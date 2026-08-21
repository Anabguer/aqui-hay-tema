const fs = require('fs');
const propPath = 'W:/juegos/aqui-hay-tema/src/Engine/PropuestaEncuentroEngine.php';
let prop = fs.readFileSync(propPath, 'utf8');
const propNeedle = `        $participantes = $ctx['participantes'];\r\n        $lugarId = $ctx['lugar'];\r\n        $tipo = PropuestaNivel::aliasTipo($tipo);`;
const propInsert = `        $participantes = $ctx['participantes'];\r\n        $lugarId = $ctx['lugar'];\r\n        if (!Reloj::esFuturo($partida['reloj'] ?? [], $dia, $hora)) {\r\n            return GameError::respuesta(GameError::HORA_PASADA, ['dia' => $dia, 'hora' => $hora]);\r\n        }\r\n        $tipo = PropuestaNivel::aliasTipo($tipo);`;
if (!prop.includes('Reloj::esFuturo')) {
  if (!prop.includes(propNeedle)) throw new Error('prop needle not found');
  prop = prop.replace(propNeedle, propInsert);
  fs.writeFileSync(propPath, prop);
}
console.log('prop', prop.includes('Reloj::esFuturo'));

const encPath = 'W:/juegos/aqui-hay-tema/src/Engine/EncuentroEngine.php';
let enc = fs.readFileSync(encPath, 'utf8');
const encNeedle = `        if (!($ctx['ok'] ?? false)) {\r\n            return $ctx;\r\n        }\r\n        $participantes = $ctx['participantes'];\r\n        $lugarId = $ctx['lugar'];\r\n\r\n        if (!ComplejoCatalog::estaAbierto((string) $lugarId, $hora)) {`;
const encInsert = `        if (!($ctx['ok'] ?? false)) {\r\n            return $ctx;\r\n        }\r\n        if (!Reloj::esFuturo($partida['reloj'] ?? [], $dia, $hora)) {\r\n            return GameError::respuesta(GameError::HORA_PASADA, ['dia' => $dia, 'hora' => $hora]);\r\n        }\r\n        $participantes = $ctx['participantes'];\r\n        $lugarId = $ctx['lugar'];\r\n\r\n        if (!ComplejoCatalog::estaAbierto((string) $lugarId, $hora)) {`;
if (!enc.includes('Reloj::esFuturo')) {
  if (!enc.includes(encNeedle)) throw new Error('enc needle not found');
  enc = enc.replace(encNeedle, encInsert);
  fs.writeFileSync(encPath, enc);
}
console.log('enc', enc.includes('Reloj::esFuturo'));
