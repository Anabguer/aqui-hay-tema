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

// --- 1) No aparece "desconocidos" en diario ---
$p1 = $service->nuevaPartida('juego_v1', 'gen-desconocidos');
$ids = array_keys($p1['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
RelacionBitacora::registrar($p1, RelacionBitacora::SE_CONOCIERON, [$a, $b]);
$entradas = DiarioEngine::listarPorResidente($p1, $a);
$hayDesconocidos = false;
foreach ($entradas as $e) {
    $texto = (string) ($e['texto'] ?? '');
    if (str_contains($texto, 'desconocidos') || str_contains($texto, 'desconocidas')) {
        $hayDesconocidos = true;
        break;
    }
}
ok(!$hayDesconocidos, '1. no aparece "desconocidos/desconocidas" en diario');

// --- 2) No aparece "enfadad" sin concordancia en diario ---
$p2 = $service->nuevaPartida('juego_v1', 'gen-enfadado');
$ids2 = array_keys($p2['residentes']);
$a2 = (string) $ids2[0];
$b2 = (string) $ids2[1];

$p2['residentes'][$a2]['runtime']['estado_emocional'] = [
    'id' => 'enfadado',
    'origen' => 'encuentro',
    'contexto' => [
        'encuentro_id' => 'enc_test_gen',
        'resultado_experiencia' => 'mal',
    ],
    'desde' => ['dia' => 1, 'hora' => 10],
];

RelacionBitacora::registrar($p2, RelacionBitacora::DISCUSION_FUERTE, [$a2, $b2]);
$entradas2 = DiarioEngine::listarPorResidente($p2, $a2);
$hayEnfadadoCrudo = false;
foreach ($entradas2 as $e) {
    $texto = (string) ($e['texto'] ?? '');
    if (str_contains($texto, 'está enfadado') || str_contains($texto, 'está enfadada')) {
        $hayEnfadadoCrudo = true;
        break;
    }
}
ok(!$hayEnfadadoCrudo, '2. no aparece "está enfadado/a" crudo en diario');

// --- 3) No aparece "tocado/tocada" en cotilleo ---
// Verificamos que EmocionalNarrativa::cotilleoParaOrigen no usa "tocad"
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\EmocionalNarrativa;

$p3 = $service->nuevaPartida('juego_v1', 'gen-tocado');
$ids3 = array_keys($p3['residentes']);
$a3 = (string) $ids3[0];
$b3 = (string) $ids3[1];
$cotilleo = EmocionalNarrativa::cotilleoParaOrigen($p3, $a3, 'rechazo_emocional', ['hacia' => $b3]);
if ($cotilleo !== null) {
    ok(!str_contains($cotilleo, 'tocado') && !str_contains($cotilleo, 'tocada'), '3. cotilleo no usa "tocado/tocada"');
} else {
    ok(true, '3. cotilleo es null (skip)');
}

echo $failures === 0 ? "OK diario_genero_neutro\n" : "FAIL diario_genero_neutro ({$failures})\n";
exit($failures > 0 ? 1 : 0);
