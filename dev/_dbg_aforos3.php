<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\AforoEngine;
use AquiHayTema\Engine\LugarAtributos;
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
$attr = LugarAtributos::de('lug_discoteca');
echo 'attr=' . json_encode($attr) . PHP_EOL;
echo 'sum=' . (AforoEngine::ocupacion($p, 'lug_discoteca', $dia, $hora) + 1) . PHP_EOL;
echo 'cabe=' . var_export(AforoEngine::cabe($p, 'lug_discoteca', $dia, $hora, 1), true) . PHP_EOL;
