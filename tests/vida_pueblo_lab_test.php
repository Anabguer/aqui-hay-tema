<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorVidaPueblo;
use AquiHayTema\Engine\VidaPuebloEngine;

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

$lab = SimuladorVidaPueblo::ejecutar($root, [7, 30], 2, 'lab-vida-test');
ok(isset($lab['por_perfil']['A']['por_horizonte']['7']), 'lab perfil A 7d');
ok(isset($lab['por_perfil']['G']['por_horizonte']['30']), 'lab farming 30d');
ok(($lab['deltas_lab']['_etiqueta'] ?? '') === 'INPUT_DE_LAB', 'deltas etiquetados INPUT DE LAB');

$g = $lab['por_perfil']['G']['por_horizonte']['30'];
ok((float) ($g['latidos_media'] ?? 1) === 0.0, 'farming no válido: 0 Latidos en 30d');
ok((float) ($g['max_max'] ?? 100) <= 99, 'farming no válido no sienta a 100');

$off = $lab['offline'];
ok((int) ($off['30d']['aplicado'] ?? 0) === -15, 'offline 30d cap −15');
ok((int) ($off['1d']['aplicado'] ?? 0) >= -15, 'offline 1d no supera cap');
ok(empty($off['30d']['game_over_activo']), 'offline 30d sin GO activo');
ok((int) ($off['critico_12_30d']['final'] ?? 0) === 5, 'offline crítico suelo 5');
ok(empty($off['critico_12_30d']['game_over_pendiente']), 'offline crítico sin GO');

$f = $lab['por_perfil']['F']['por_horizonte']['30'];
ok((float) ($f['inicial_media'] ?? 0) === 12.0, 'F arranca en crítico');
ok(($f['pct_recuperacion'] ?? 0) !== null, 'F mide recuperación');

$d = $lab['por_perfil']['D']['por_horizonte']['7'];
ok((float) ($d['game_over_max'] ?? 0) >= 0, 'D reporta GO teórico');

ok(strpos(json_encode($lab), 'INPUT_DE_LAB') !== false, 'informe lab declara input sintético');
ok(!class_exists('AquiHayTema\\Engine\\MisionEngine', false), 'B3 misiones no existe');

$playSrc = file_get_contents($root . '/assets/js/play.js');
ok(strpos($playSrc, 'vida_pueblo') === false && strpos($playSrc, 'VidaPueblo') === false, 'PLAY JS no muestra Vida');

exit($failures > 0 ? 1 : 0);
