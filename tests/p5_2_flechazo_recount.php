<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
$root = dirname(__DIR__);
$service = new PartidaService($root);
$seeds = [];
for ($i = 1; $i <= 30; $i++) { $seeds[] = sprintf('p52-s%02d', $i); }
echo "=== FLECHAZO RECOUNT (30 seeds D1-D30) ===\n\n";
foreach ($seeds as $seed) {
    $start = microtime(true);
    $p = $service->nuevaPartida('juego_v1', $seed);
    for ($h = 0; $h < 30 * 24; $h++) {
        $service->avanzarRelojPasoAPaso($p, 1);
        if ((int)($p['reloj']['hora_actual'] ?? 0) === 0) {
            $mx = 5;
            while (($p['llegadas']['candidato_activo'] ?? null) !== null && $mx-- > 0) {
                $a = CapacidadViviendas::residentesActivos($p);
                if ($a === []) break;
                $r = CandidatoLlegadaEngine::aceptar($p, $root, null, null, (string)$a[0]);
                if (!($r['ok'] ?? false)) break;
                $service->avanzarRelojPasoAPaso($p, 1);
            }
        }
    }
    $el = round(microtime(true) - $start, 1);
    $tf = 0; $fd = [];
    foreach ($p['relaciones_romanticas'] ?? [] as $r) {
        foreach ($r['flechazos'] ?? [] as $fl) {
            $tf++;
            $fd[] = (string)($fl['dia'] ?? '?') . ':' . substr($r['persona_a'] ?? $r['a'] ?? '?', -4) . '->' . substr($r['persona_b'] ?? $r['b'] ?? '?', -4);
        }
    }
    $sn = 0; foreach ($p['memoria_eventos'] ?? [] as $e) { if (($e['familia'] ?? '') === 'romance_accion' && ($e['evento'] ?? '') === 'flechazo') $sn++; }
    $ct = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { $ct += count($r['historial_citas'] ?? []); }
    $dc = 0; foreach ($p['memoria_eventos'] ?? [] as $e) { if (($e['tipo'] ?? '') === 'declaracion') $dc++; }
    $pa = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { if (($r['estado_pareja'] ?? 'ninguna') !== 'ninguna') $pa++; }
    $fs = $tf > 0 ? '(' . implode(',', $fd) . ')' : '()';
    echo "[{$seed}] {$el}s flech={$tf}{$fs} senales={$sn} citas={$ct} decl={$dc} parejas={$pa}\n";
}
echo "=== DONE ===\n";