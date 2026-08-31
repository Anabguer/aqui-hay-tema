<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$PRIVACY = [
    '/\bestá desanimad[oa]\b/i', '/\bestá triste\b/i', '/\bestá alegre\b/i',
    '/\bestá enfadad[oa]\b/i', '/\bse fija bastante\b/i', '/\bha decidido que\b/i',
    '/\ble apetecía\b/i', '/\bnecesitaba\b/', '/\bse siente\b/i', '/\bpiensa que\b/i',
];

for ($si = 1; $si <= 5; $si++) {
    $seed = sprintf('p10-3-pv-%02d', $si);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $service->avanzarRelojPasoAPaso($p, 24);
    $mx = 15;
    while (($p['llegadas']['candidato_activo'] ?? null) !== null && $mx-- > 0) {
        $a = CapacidadViviendas::residentesActivos($p);
        if ($a === []) break;
        $r = CandidatoLlegadaEngine::aceptar($p, $root, null, null, (string)$a[0]);
        if (!($r['ok'] ?? false)) break;
        $service->avanzarRelojPasoAPaso($p, 1);
    }
    for ($d = 1; $d <= 20; $d++) {
        $service->avanzarRelojPasoAPaso($p, 24);
        foreach ($p['buzon'] ?? [] as $m) {
            if (($m['canal'] ?? '') !== 'cotilleo' || ($m['dia'] ?? 0) !== $d) continue;
            $texto = $m['texto'] ?? '';
            foreach ($PRIVACY as $pat) {
                if (preg_match($pat, $texto)) {
                    echo "PRIVACY [$seed D{$d}] tipo=" . ($m['tipo'] ?? '?') . ": " . substr($texto, 0, 120) . "\n";
                    break;
                }
            }
        }
    }
}
echo "=== DONE ===\n";
