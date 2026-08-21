<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\AforoEngine;
use AquiHayTema\Engine\ComplejoCatalog;
$s = new PartidaService(dirname(__DIR__));
$p = $s->nuevaPartida('playtest_01', 'aforo-dbg');
$p['celeste']['lugares_desbloqueados'] = array_merge($p['celeste']['lugares_desbloqueados'] ?? [], ['lug_discoteca']);
$dia = (int)$p['reloj']['dia_pueblo'];
$hora = 23;
$ids = array_keys($p['residentes']);
for ($i = 0; $i < 8; $i++) {
    $p['npc_autonomo']['planes_pendientes'][] = [
        'lugar' => 'lug_discoteca', 'dia' => $dia, 'hora' => $hora,
        'duracion_minutos' => 120, 'participantes' => [$ids[$i % count($ids)]],
    ];
}
echo 'ocupacion=' . AforoEngine::ocupacion($p, 'lug_discoteca', $dia, $hora) . PHP_EOL;
echo 'cabe1=' . (AforoEngine::cabe($p, 'lug_discoteca', $dia, $hora, 1) ? 'true' : 'false') . PHP_EOL;
echo 'aforo=' . (ComplejoCatalog::destino('lug_discoteca')['aforo'] ?? '?') . PHP_EOL;
