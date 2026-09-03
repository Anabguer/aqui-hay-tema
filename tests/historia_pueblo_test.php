<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\HistoriaPuebloVista;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$service = new PartidaService($root);

// --- 1) Ensure inicializa el array ---
$p1 = $service->nuevaPartida('juego_v1', 'hp-test-1');
ok(isset($p1['historia_pueblo']), '1. historia_pueblo existe en partida nueva');
ok(is_array($p1['historia_pueblo']), '1. es array');

// --- 2) Primer hito registrado al crear partida ---
$entradas = HistoriaPuebloEngine::listar($p1);
ok(count($entradas) >= 1, '2. al menos 1 hito registrado en partida nueva');

// --- 3) Hito empezo_el_cotarro existe ---
$clave = HistoriaPuebloEngine::clave(HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, array_keys($p1['residentes']));
$existe = HistoriaPuebloEngine::existe($p1, $clave);
ok($existe, '3. hito empezo_el_cotarro existe');

// --- 4) El hito tiene los protagonistas correctos ---
$entrada = HistoriaPuebloEngine::obtener($p1, $clave);
ok($entrada !== null, '4. entrada no null');
ok(!empty($entrada['revelado']), '4.1 hito está revelado');
ok(count($entrada['protagonistas']) === count($p1['residentes']), '4.2 todos los residentes son protagonistas');
ok(($entrada['dia'] ?? 0) >= 1, '4.3 día registrado >= 1');

// --- 5) Idempotencia ---
$nAntes = count(HistoriaPuebloEngine::listar($p1));
$resultado = HistoriaPuebloEngine::registrar($p1, HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, array_keys($p1['residentes']));
ok($resultado['ya_existia'] === true, '5. registrar duplicado retorna ya_existia=true');
ok(count(HistoriaPuebloEngine::listar($p1)) === $nAntes, '5.1 no se duplica entrada');

// --- 6) Vista snapshot ---
$snapshot = HistoriaPuebloVista::snapshot($p1);
ok($snapshot['total_revelados'] >= 1, '6. snapshot tiene al menos 1 revelado');
ok($snapshot['total_hitos'] >= 1, '6.1 snapshot tiene al menos 1 hito total');
ok(count($snapshot['hitos']) >= 1, '6.2 snapshot tiene al menos 1 hito en lista');

// --- 7) Vista hito revelado ---
$primero = $snapshot['hitos'][0];
ok($primero['revelado'] === true, '7. primer hito está revelado');
ok(!empty($primero['nombre']), '7.1 tiene nombre');
ok(!empty($primero['protagonistas']), '7.2 tiene protagonistas');
ok(($primero['dia'] ?? 0) >= 1, '7.3 tiene día');

// --- 8) Vista protagonista tiene nombre ---
$protagonista = $primero['protagonistas'][0];
ok(!empty($protagonista['nombre']), '8. protagonista tiene nombre');
ok(!empty($protagonista['id']), '8.1 protagonista tiene id');

// --- 9) Total revelados ---
ok(HistoriaPuebloEngine::totalRevelados($p1) >= 1, '9. totalRevelados >= 1');

// --- 10) Guardar y recargar ---
$service->guardar($p1);
$p2 = $service->cargar($p1['meta']['partida_id']);
$entradas2 = HistoriaPuebloEngine::listar($p2);
ok(count($entradas2) >= 1, '10. persistencia: hitos sobreviven guardar/cargar');

// --- 11) Nombre del hito en catálogo ---
$catalogo = HistoriaPuebloEngine::catalogo($p1);
ok(count($catalogo) >= 1, '11. catálogo tiene al menos 1 hito');
$catPrimero = $catalogo[0];
ok($catPrimero['id'] === HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, '11.1 primer hito del catálogo es empezo_el_cotarro');
ok($catPrimero['nombre'] === 'AQUÍ EMPEZÓ EL COTARRO', '11.2 nombre correcto');
ok($catPrimero['revelado'] === true, '11.3 primer hito revelado en catálogo');

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
