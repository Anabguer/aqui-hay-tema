<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$p = $service->nuevaPartida('juego_v1', 'p10-p8-check');
$service->avanzarRelojPasoAPaso($p, 24);
$mx = 15;
while (($p['llegadas']['candidato_activo'] ?? null) !== null && $mx-- > 0) {
    $a = CapacidadViviendas::residentesActivos($p);
    if ($a === []) break;
    $r = CandidatoLlegadaEngine::aceptar($p, $root, null, null, (string)$a[0]);
    if (!($r['ok'] ?? false)) break;
    $service->avanzarRelojPasoAPaso($p, 1);
}
$service->avanzarRelojPasoAPaso($p, 24 * 30);

$progresiones = array_filter($p['buzon'] ?? [], fn($m) => ($m['tipo'] ?? '') === 'progresion_romantica');
echo "Progresiones románticas: " . count($progresiones) . "\n";
foreach ($progresiones as $pg) {
    $actores = $pg['actores'] ?? [];
    echo "  [" . implode('/', $actores) . "] " . ($pg['texto'] ?? '') . "\n";
}
echo "\n=== P8 CHECK: 0 parejas sorpresa ===\n";
echo count($progresiones) . " progresiones detectadas\n";
