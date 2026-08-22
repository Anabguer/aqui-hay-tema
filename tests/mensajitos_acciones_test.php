<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
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

DomainBootstrap::boot();
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'mensajitos-acciones-' . time());

BuzonEngine::crear($p, [
    'id' => 'msg_bruno_test',
    'tipo' => 'candidato_llegada',
    'texto' => 'Bruno quiere mudarse al pueblo. ¿Le dejamos hueco?',
    'acciones' => ['aceptar_candidato', 'rechazar_candidato'],
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
]);
$msg = BuzonEngine::buscar($p, 'msg_bruno_test');
ok($msg !== null, 'mensaje creado');
ok(BuzonEngine::tieneDecisionPendiente($msg ?? []), 'decision pendiente inicial');

BuzonEngine::marcarLeido($p, 'msg_bruno_test');
$leido = BuzonEngine::buscar($p, 'msg_bruno_test');
ok(BuzonEngine::estaLeido($leido ?? []), 'marcado leido');
ok(BuzonEngine::tieneDecisionPendiente($leido ?? []), 'decision sigue pendiente tras leer');

$ui = BuzonEngine::enriquecerParaUi($leido ?? []);
ok(count($ui['acciones_ui'] ?? []) === 2, 'acciones_ui con dos botones');
ok(($ui['acciones_ui'][0]['etiqueta'] ?? '') === 'Dejarle hueco', 'copy aceptar');

$r = MensajitoAcciones::resolver($p, 'msg_bruno_test', 'rechazar_candidato', $root);
ok(($r['ok'] ?? false) === false || ($r['error'] ?? '') === 'sin_candidato', 'rechazar sin candidato activo no rompe');

$p['llegadas']['candidato_activo'] = [
    'catalog_id' => 'per_test_br',
    'nombre' => 'Bruno',
    'mensaje_id' => 'msg_bruno_test',
    'estado' => CandidatoLlegadaEngine::ESTADO_PENDIENTE,
];
$p['llegadas']['modo'] = 'normal';
$r2 = MensajitoAcciones::resolver($p, 'msg_bruno_test', 'rechazar_candidato', $root);
ok($r2['ok'] ?? false, 'rechazar candidato ok');
$fin = BuzonEngine::buscar($p, 'msg_bruno_test');
ok(!BuzonEngine::tieneDecisionPendiente($fin ?? []), 'decision resuelta tras rechazar');

exit($failures > 0 ? 1 : 0);
