<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\VistaPuebloV3;

$root = dirname(__DIR__);
$service = new PartidaService($root);

$FASE_DESTINO = [
    'bar' => 'lug_bar', 'discoteca' => 'lug_discoteca', 'karaoke' => 'lug_karaoke',
    'cine' => 'lug_cine', 'recreativo' => 'lug_arcade',
    'restaurante' => 'lug_restaurante', 'bingo' => 'lug_bingo',
    'cafeteria' => 'lug_cafeteria', 'biblioteca' => 'lug_biblioteca', 'tienda' => 'lug_tienda_ropa',
    'gimnasio' => 'lug_gimnasio', 'spa' => 'lug_spa',
    'picnic' => 'lug_picnic', 'mirador' => 'lug_mirador',
];

$FASES = [
    1 => ['bar', 'cine', 'restaurante', 'cafeteria', 'gimnasio', 'picnic'],
    2 => ['bar', 'discoteca', 'cine', 'recreativo', 'restaurante', 'bingo', 'cafeteria', 'biblioteca', 'gimnasio', 'spa', 'picnic', 'mirador'],
    3 => array_keys($FASE_DESTINO),
];

$comp = json_decode((string) file_get_contents($root . '/assets/play-v3/edificios_composicion.json'), true);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

ok(is_array($comp['edificios'] ?? null) && count($comp['edificios']) === 14, 'JSON composición tiene 14 edificios');

foreach ([1, 2, 3] as $f) {
    $lugares = array_values(array_map(static fn ($id) => $FASE_DESTINO[$id], $FASES[$f]));
    $partida = $service->nuevaPartida('playtest_01', 'map-fase-' . $f);
    $partida['celeste']['lugares_desbloqueados'] = $lugares;
    $mapa = PresenciaEngine::resolver($partida, $root);
    $pueblo = VistaPuebloV3::de($partida, $mapa, $root);

    $operativos = [];
    foreach ($pueblo['complejos'] as $cx) {
        foreach ($cx['destinos'] as $d) {
            if (!empty($d['operativo'])) {
                $operativos[] = $d['id'];
            }
        }
    }

    ok(count($operativos) === count($FASES[$f]), "FASE $f: " . count($operativos) . ' destinos operativos (esperado ' . count($FASES[$f]) . ')');

    if ($f === 2) {
        ok(!in_array('lug_karaoke', $operativos, true), 'FASE 2: karaoke deshabilitado');
        ok(!in_array('lug_tienda_ropa', $operativos, true), 'FASE 2: tienda deshabilitada');
    }
}

ok(file_exists($root . '/assets/play-v3/edificios_composicion.json'), 'archivo edificios_composicion.json existe');
$js = (string) file_get_contents($root . '/assets/js/play-v3.js');
ok(strpos($js, 'layoutComposicionDefinitiva') !== false, 'play-v3.js usa layoutComposicionDefinitiva');
ok(strpos($js, 'layoutEdificios') === false, 'play-v3.js ya no usa layoutEdificios');

exit($failures > 0 ? 1 : 0);
