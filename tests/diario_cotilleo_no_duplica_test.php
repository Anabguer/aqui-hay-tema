<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioHitoEngine;
use AquiHayTema\Engine\DiarioNarrativaBridge;
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

// --- 1) Hito relacional genera entrada en 1ra persona, NO en 3ra persona ---
$p1 = $service->nuevaPartida('juego_v1', 'dcnd-1ra');
$ids = array_keys($p1['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
$p1['features']['buzon_enabled'] = true;

RelacionBitacora::registrar($p1, RelacionBitacora::SE_CONOCIERON, [$a, $b]);

$entradasA = DiarioEngine::listarPorResidente($p1, $a);
$entradas3ra = array_filter($entradasA, static function ($e) use ($a) {
    $texto = (string) ($e['texto'] ?? '');
    return str_contains($texto, 'han ') && !str_contains($texto, 'hemos ') && !str_contains($texto, 'he ');
});

ok(count($entradas3ra) === 0, '1. sin entradas en 3ra persona en diario personal');

// --- 2) Cotilleo (buzón) puede usar 3ra persona, pero NO se espeja al diario ---
$p2 = $service->nuevaPartida('juego_v1', 'dcnd-espejo');
$p2['features']['buzon_enabled'] = true;
$ids2 = array_keys($p2['residentes']);
$a2 = (string) $ids2[0];
$b2 = (string) $ids2[1];

$msg = [
    'clasificacion' => 'cotilleo',
    'tipo' => 'cotilleo',
    'texto' => $a2 . ' y ' . $b2 . ' han pasado la tarde en la Cafetería.',
    'actores' => [$a2, $b2],
    'origen' => [
        'evento_id' => 'encuentro:enc_test_2',
        'tipo_evento' => 'encuentro_terminado',
        'es_narrativo' => false,
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
];
$creado = DiarioNarrativaBridge::desdeMensaje($p2, $msg);
ok($creado === null, '2. espejo de cotilleo de encuentro NO entra al diario');

// --- 3) Texto de 1ra persona contiene nombre del otro ---
$p3 = $service->nuevaPartida('juego_v1', 'dcnd-1ra-nombre');
$ids3 = array_keys($p3['residentes']);
$a3 = (string) $ids3[0];
$b3 = (string) $ids3[1];
RelacionBitacora::registrar($p3, RelacionBitacora::SE_CONOCIERON, [$a3, $b3]);
$entradas3 = DiarioEngine::listarPorResidente($p3, $a3);
$nombreB = $p3['residentes'][$b3]['identidad_publica']['nombre'] ?? $b3;
$hayNombre = false;
foreach ($entradas3 as $e) {
    if (str_contains((string) ($e['texto'] ?? ''), $nombreB)) {
        $hayNombre = true;
        break;
    }
}
ok($hayNombre, '3. entrada de 1ra persona menciona nombre del otro');

echo $failures === 0 ? "OK diario_cotilleo_no_duplica\n" : "FAIL diario_cotilleo_no_duplica ({$failures})\n";
exit($failures > 0 ? 1 : 0);
