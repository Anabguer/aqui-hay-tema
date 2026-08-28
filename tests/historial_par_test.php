<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistorialPar;
use AquiHayTema\Engine\RelacionBitacora;

$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$pA = 'per_hugo';
$pB = 'per_tamara';
$pC = 'per_lucia';

// --- Partida con dos encuentros entre A y B ---
$partida = [
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 18],
    'residentes' => [
        $pA => ['identidad_publica' => ['nombre' => 'Hugo']],
        $pB => ['identidad_publica' => ['nombre' => 'Tamara']],
        $pC => ['identidad_publica' => ['nombre' => 'Lucía']],
    ],
    'bitacora_relaciones' => [
        [
            'tipo' => RelacionBitacora::SE_CONOCIERON,
            'participantes' => [$pA, $pB],
            'fecha' => ['dia' => 1],
            'meta' => [],
        ],
        [
            'tipo' => RelacionBitacora::PRIMERA_CITA,
            'participantes' => [$pA, $pB],
            'fecha' => ['dia' => 3],
            'meta' => [],
        ],
        [
            'tipo' => RelacionBitacora::SE_CONOCIERON,
            'participantes' => [$pA, $pC],
            'fecha' => ['dia' => 2],
            'meta' => [],
        ],
    ],
    'encuentros' => [
        [
            'id' => 'enc1',
            'participantes' => [$pA, $pB],
            'tipo' => 'conocerse',
            'dia' => 1,
            'hora' => 12,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $pA => ['resultado' => 'bien', 'carga' => 0.3],
                    $pB => ['resultado' => 'bien', 'carga' => 0.2],
                ],
            ],
        ],
        [
            'id' => 'enc2',
            'participantes' => [$pA, $pB],
            'tipo' => 'cita',
            'dia' => 3,
            'hora' => 19,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $pA => ['resultado' => 'muy_bien', 'carga' => 0.5],
                    $pB => ['resultado' => 'bien', 'carga' => 0.3],
                ],
            ],
        ],
        [
            'id' => 'enc_other',
            'participantes' => [$pA, $pC],
            'tipo' => 'conocerse',
            'dia' => 2,
            'hora' => 17,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $pA => ['resultado' => 'mal', 'carga' => -0.2],
                    $pC => ['resultado' => 'bien', 'carga' => 0.1],
                ],
            ],
        ],
        [
            'id' => 'enc_pending',
            'participantes' => [$pA, $pB],
            'tipo' => 'conocerse',
            'dia' => 4,
            'hora' => 18,
            'estado' => 'programado',
            'resultado' => null,
        ],
    ],
    'memoria_eventos' => [],
];

// --- Test 1: entre() devuelve estructura completa ---
$h = HistorialPar::entre($partida, $pA, $pB);
ok(is_array($h), 'entre: es array');
ok($h['clave'] === $pA . ':' . $pB || $h['clave'] === $pB . ':' . $pA, 'entre: clave presente');
ok(is_array($h['hitos']), 'entre: hitos');
ok(is_array($h['encuentros']), 'entre: encuentros');
ok(is_array($h['resumen']), 'entre: resumen');

// --- Test 2: hitos filtrados por par ---
$hitos = HistorialPar::hitos($partida, $pA, $pB);
ok(count($hitos) === 2, 'hitos: solo 2 entre A y B');
ok($hitos[0]['tipo'] === RelacionBitacora::SE_CONOCIERON, 'hitos: primero se conocieron');
ok($hitos[0]['dia'] === 1, 'hitos: dia 1');
ok($hitos[1]['tipo'] === RelacionBitacora::PRIMERA_CITA, 'hitos: segundo primera cita');
ok($hitos[1]['dia'] === 3, 'hitos: dia 3');

// --- Test 3: hitos excluyen a C ---
$hitosAC = HistorialPar::hitos($partida, $pA, $pC);
ok(count($hitosAC) === 1, 'hitos AC: solo 1');
ok($hitosAC[0]['tipo'] === RelacionBitacora::SE_CONOCIERON, 'hitos AC: se conocieron');

// --- Test 4: encuentros filtrados ---
$encs = HistorialPar::encuentros($partida, $pA, $pB);
ok(count($encs) === 2, 'encuentros: solo 2 terminados');
ok($encs[0]['id'] === 'enc1', 'encuentros: primero enc1');
ok($encs[0]['resultado_a'] === 'bien', 'encuentros: enc1 A bien');
ok($encs[1]['resultado_a'] === 'muy_bien', 'encuentros: enc2 A muy_bien');

// --- Test 5: encuentro programado NO incluido ---
$encsAll = HistorialPar::encuentros($partida, $pA, $pB);
$ids = array_column($encsAll, 'id');
ok(!in_array('enc_pending', $ids), 'encuentros: programado excluido');

// --- Test 6: resumen ---
$res = HistorialPar::resumen($partida, $pA, $pB);
ok($res['se_conocen'] === true, 'resumen: se conocen');
ok($res['ha_habido_cita'] === true, 'resumen: ha habido cita');
ok($res['es_pareja'] === false, 'resumen: no son pareja');
ok($res['total_encuentros'] === 2, 'resumen: 2 encuentros');
ok($res['experiencias_positivas'] > 0, 'resumen: experiencias positivas');
ok($res['ultima_tendencia'] === 'positiva', 'resumen: tendencia positiva');

// --- Test 7: orden asimétrico ---
$h2 = HistorialPar::entre($partida, $pB, $pA);
ok($h2['clave'] === $h['clave'], 'orden asimétrico: misma clave');

// --- Test 8: par sin historia ---
$h3 = HistorialPar::entre($partida, $pB, $pC);
ok(count(HistorialPar::hitos($partida, $pB, $pC)) === 0, 'sin historia: 0 hitos');
ok(count(HistorialPar::encuentros($partida, $pB, $pC)) === 0, 'sin historia: 0 encuentros');

echo $fail === 0 ? "\nhistorial_par_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
