<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);

function aceptarCandidatos(PartidaService $svc, array &$p): void {
    global $root;
    $mx = 15;
    while (($p['llegadas']['candidato_activo'] ?? null) !== null && $mx-- > 0) {
        $a = CapacidadViviendas::residentesActivos($p);
        if ($a === []) break;
        $r = CandidatoLlegadaEngine::aceptar($p, $root, null, null, (string)$a[0]);
        if (!($r['ok'] ?? false)) break;
        $svc->avanzarRelojPasoAPaso($p, 1);
    }
}

$SEEDS = 5;
$DIAS = 10;

echo "=== AHT-P9: DETECCION DUPLICADOS — {$SEEDS}x{$DIAS} ===\n\n";

$totales = ['evento_id_dup' => 0, 'texto_dup' => 0, 'actor_texto_dup' => 0, 'total' => 0, 'unicos' => 0];
$ejemplos_dup = [];

for ($i = 1; $i <= $SEEDS; $i++) {
    $seed = sprintf('p9-dup-%02d', $i);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $service->avanzarRelojPasoAPaso($p, 24);
    aceptarCandidatos($service, $p);

    for ($d = 1; $d <= $DIAS; $d++) {
        $service->avanzarRelojPasoAPaso($p, 24);
    }

    $porEventoId = [];
    $porTextoHash = [];
    $porActorTexto = [];

    foreach ($p['diario'] ?? [] as $e) {
        $totales['total']++;
        $eventoId = $e['origen']['evento_id'] ?? '';
        $texto = trim($e['texto'] ?? '');
        $actores = $e['actores'] ?? [];
        $tipo = $e['tipo'] ?? '';
        $subtipo = $e['subtipo'] ?? '';
        $dia = $e['dia'] ?? 0;

        if ($eventoId !== '') {
            if (isset($porEventoId[$eventoId])) {
                $totales['evento_id_dup']++;
                if (count($ejemplos_dup) < 10) {
                    $ejemplos_dup[] = "EV Dup: evento_id={$eventoId} tipo={$tipo}/{$subtipo} D{$dia} texto=" . substr($texto, 0, 60);
                }
            }
            $porEventoId[$eventoId] = true;
        }

        $textoHash = md5($texto);
        if ($texto !== '' && isset($porTextoHash[$textoHash])) {
            $totales['texto_dup']++;
            if (count($ejemplos_dup) < 20) {
                $ejemplos_dup[] = "TXT Dup: tipo={$tipo}/{$subtipo} D{$dia} texto=" . substr($texto, 0, 60);
            }
        }
        $porTextoHash[$textoHash] = true;

        $actorKey = md5(implode(',', $actores) . '|' . $texto);
        if ($texto !== '' && count($actores) > 0 && isset($porActorTexto[$actorKey])) {
            $totales['actor_texto_dup']++;
            if (count($ejemplos_dup) < 30) {
                $ejemplos_dup[] = "ACT Dup: tipo={$tipo}/{$subtipo} actors=" . implode('/', $actores) . " D{$dia}";
            }
        }
        $porActorTexto[$actorKey] = true;
    }
}

$unicos = $totales['total'] - $totales['evento_id_dup'];
$totales['unicos'] = $unicos;

echo "=== DUPLICADOS POR EVENTO_ID ===\n";
echo "Total entradas: {$totales['total']}\n";
echo "Duplicados (evento_id): {$totales['evento_id_dup']}\n";
echo "Unicos (evento_id): {$unicos}\n";

echo "\n=== DUPLICADOS POR TEXTO ===\n";
echo "Duplicados (texto identico): {$totales['texto_dup']}\n";

echo "\n=== DUPLICADOS POR ACTOR+TEXTO ===\n";
echo "Duplicados (actor+texto): {$totales['actor_texto_dup']}\n";

echo "\n=== EJEMPLOS DE DUPLICADOS ===\n";
foreach ($ejemplos_dup as $ej) {
    echo "  {$ej}\n";
}

echo "\n=== RESUMEN ===\n";
echo "Total: {$totales['total']}\n";
echo "Unicos (evento_id): {$unicos}\n";
echo "Duplicados evento_id: {$totales['evento_id_dup']} (" . round($totales['evento_id_dup'] / max(1, $totales['total']) * 100, 1) . "%)\n";
echo "Duplicados texto: {$totales['texto_dup']} (" . round($totales['texto_dup'] / max(1, $totales['total']) * 100, 1) . "%)\n";
echo "\n=== DONE ===\n";
