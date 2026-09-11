<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioHitoEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;

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
$service = new PartidaService($root);

// --- 1) Entrada de hito relacional tiene ts_juego con hora ---
$p1 = $service->nuevaPartida('juego_v1', 'hora-hito');
$p1['reloj']['hora_actual'] = 14;
$ids = array_keys($p1['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
RelacionBitacora::registrar($p1, RelacionBitacora::SE_CONOCIERON, [$a, $b]);
$entradas = DiarioEngine::listarPorResidente($p1, $a);
$hayTs = false;
foreach ($entradas as $e) {
    $ts = is_array($e['ts_juego'] ?? null) ? $e['ts_juego'] : [];
    if (($ts['hora'] ?? null) !== null) {
        $hayTs = true;
        break;
    }
}
ok($hayTs, '1. entrada de hito tiene ts_juego con hora');

// --- 2) Entrada de hito de ruptura tiene ts_juego ---
$p2 = $service->nuevaPartida('juego_v1', 'hora-ruptura');
$p2['reloj']['hora_actual'] = 20;
$ids2 = array_keys($p2['residentes']);
$a2 = (string) $ids2[0];
$b2 = (string) $ids2[1];
RelacionBitacora::registrar($p2, RelacionBitacora::RUPTURA, [$a2, $b2]);
$entradas2 = DiarioEngine::listarPorResidente($p2, $a2);
$hayTs2 = false;
foreach ($entradas2 as $e) {
    $ts = is_array($e['ts_juego'] ?? null) ? $e['ts_juego'] : [];
    if (($ts['hora'] ?? null) !== null) {
        $hayTs2 = true;
        break;
    }
}
ok($hayTs2, '2. entrada de ruptura tiene ts_juego');

// --- 3) Hora del reloj se propaga correctamente ---
$p3 = $service->nuevaPartida('juego_v1', 'hora-propagacion');
$p3['reloj']['hora_actual'] = 9;
$ids3 = array_keys($p3['residentes']);
$a3 = (string) $ids3[0];
$b3 = (string) $ids3[1];
RelacionBitacora::registrar($p3, RelacionBitacora::FLECHAZO, [$a3, $b3]);
$entradas3 = DiarioEngine::listarPorResidente($p3, $a3);
$horaCorrecta = false;
foreach ($entradas3 as $e) {
    $ts = is_array($e['ts_juego'] ?? null) ? $e['ts_juego'] : [];
    if ((int) ($ts['hora'] ?? -1) === 9) {
        $horaCorrecta = true;
        break;
    }
}
ok($horaCorrecta, '3. hora del reloj (9) se propaga a la entrada');

echo $failures === 0 ? "OK diario_hora\n" : "FAIL diario_hora ({$failures})\n";
exit($failures > 0 ? 1 : 0);
