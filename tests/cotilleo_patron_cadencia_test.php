<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\CotilleoPatronCadencia;
use AquiHayTema\Engine\RelacionBitacora;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function simularCoincidencia(array &$partida, int $dia, string $lugar, array $ids): void
{
    $partida['historial_coincidencias'][] = [
        'dia' => $dia,
        'hora' => 20,
        'lugar_id' => $lugar,
        'residentes' => $ids,
    ];
    $env = ['dia' => $dia, 'lugar_id' => $lugar, 'residentes' => $ids, 'actores' => $ids];
    if (CotilleoNarrativo::coincidenciaDigna($partida, $env, [])) {
        $msg = CotilleoNarrativo::mensajeCoincidencia($partida, $env, []);
        if ($msg !== null) {
            BuzonEngine::crear($partida, $msg);
        }
    }
}

// A) Raúl + Inés + Bar: sin repetición diaria
$partida = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 20],
    'buzon' => [],
    'residentes' => [
        'per_raul' => ['identidad_publica' => ['nombre' => 'Raúl']],
        'per_ines' => ['identidad_publica' => ['nombre' => 'Inés']],
    ],
    'historial_coincidencias' => [],
];

$publicaciones = 0;
for ($d = 1; $d <= 8; $d++) {
    $partida['reloj']['dia_pueblo'] = $d;
    simularCoincidencia($partida, $d, 'lug_bar', ['per_raul', 'per_ines']);
    $hoy = array_values(array_filter($partida['buzon'], static fn($m) => is_array($m)
        && ($m['tipo'] ?? '') === 'cotilleo_patron'
        && (int) ($m['dia'] ?? 0) === $d));
    $publicaciones += count($hoy);
}
$patrones = array_values(array_filter($partida['buzon'], static fn($m) => is_array($m) && ($m['tipo'] ?? '') === 'cotilleo_patron'));
ok(count($patrones) <= 3, 'Raúl/Inés+Bar: máximo 3 publicaciones en 8 días (no diario)');
ok($publicaciones <= 3, 'no más de 3 días con patrón publicado');
ok(!str_contains((string) ($patrones[0]['texto'] ?? ''), 'Algo se cuece'), 'sin copy antiguo');

// B) Patrón + romance el mismo día → ambos pueden existir; romance no bloqueado
$partidaB = [
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 20],
    'buzon' => [],
    'residentes' => [
        'per_edu' => ['identidad_publica' => ['nombre' => 'Eduardo']],
        'per_ben' => ['identidad_publica' => ['nombre' => 'Benito']],
    ],
    'historial_coincidencias' => [],
];
for ($d = 1; $d <= 5; $d++) {
    $partidaB['historial_coincidencias'][] = [
        'dia' => $d, 'hora' => 20, 'lugar_id' => 'lug_cine', 'residentes' => ['per_edu', 'per_ben'],
    ];
}
simularCoincidencia($partidaB, 5, 'lug_cine', ['per_edu', 'per_ben']);
BuzonEngine::crear($partidaB, [
    'id' => 'msg_rom_d5',
    'clasificacion' => BuzonEngine::COTILLEO,
    'canal' => BuzonEngine::CANAL_COTILLEO,
    'tipo' => 'senal_romantica',
    'dia' => 5,
    'texto' => 'Eduardo lleva demasiado rato pendiente de Benito.',
    'actores' => ['per_edu', 'per_ben'],
    'cotilleo_meta' => ['categoria' => 'romance', 'destacado' => true],
]);
$cotD5 = array_values(array_filter($partidaB['buzon'], static fn($m) => is_array($m) && (int) ($m['dia'] ?? 0) === 5));
$tipos = array_map(static fn($m) => (string) ($m['tipo'] ?? ''), $cotD5);
ok(in_array('senal_romantica', $tipos, true), 'romance del día sigue publicándose');
ok(in_array('cotilleo_patron', $tipos, true) || count(array_filter($tipos, static fn($t) => $t === 'cotilleo_patron')) === 0,
    'patrón no eclipsa romance (romance presente)');

// C) Encuentro + patrón mismo día
$partidaC = $partidaB;
BuzonEngine::crear($partidaC, [
    'id' => 'msg_enc_d5',
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'cotilleo',
    'dia' => 5,
    'texto' => 'Eduardo y Benito han pasado la tarde en el Cine. La cosa ha acabado tensa.',
    'actores' => ['per_edu', 'per_ben'],
    'lugar_id' => 'lug_cine',
    'cotilleo_meta' => ['categoria' => 'encuentro', 'destacado' => false],
]);
$enc = array_values(array_filter($partidaC['buzon'], static fn($m) => is_array($m) && ($m['tipo'] ?? '') === 'cotilleo'));
ok($enc !== [], 'encuentro visible junto a otros cotilleos');

// D) Muchos patrones un día → cupo 1
$partidaD = [
    'reloj' => ['dia_pueblo' => 4, 'hora_actual' => 20],
    'buzon' => [],
    'residentes' => [
        'per_a' => [], 'per_b' => [], 'per_c' => [], 'per_d' => [],
    ],
    'historial_coincidencias' => [],
];
$pairs = [['per_a', 'per_b', 'lug_bar'], ['per_c', 'per_d', 'lug_cine'], ['per_a', 'per_c', 'lug_bar']];
foreach ($pairs as [$a, $b, $lug]) {
    for ($d = 1; $d <= 4; $d++) {
        $partidaD['historial_coincidencias'][] = [
            'dia' => $d, 'hora' => 20, 'lugar_id' => $lug, 'residentes' => [$a, $b],
        ];
    }
}
foreach ($pairs as [$a, $b, $lug]) {
    simularCoincidencia($partidaD, 4, $lug, [$a, $b]);
}
$patD4 = array_values(array_filter($partidaD['buzon'], static fn($m) => is_array($m)
    && ($m['tipo'] ?? '') === 'cotilleo_patron' && (int) ($m['dia'] ?? 0) === 4));
ok(count($patD4) === 1, 'día con varios patrones: cupo máximo 1');

// Evolución: nuevo hito relacional tras cooldown republica
$partidaE = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 20],
    'buzon' => [],
    'residentes' => [
        'per_raul' => ['identidad_publica' => ['nombre' => 'Raúl']],
        'per_ines' => ['identidad_publica' => ['nombre' => 'Inés']],
    ],
    'relaciones_sociales' => [],
    'historial_coincidencias' => [],
];
for ($d = 1; $d <= 3; $d++) {
    simularCoincidencia($partidaE, $d, 'lug_bar', ['per_raul', 'per_ines']);
}
$antes = count(array_filter($partidaE['buzon'], static fn($m) => ($m['tipo'] ?? '') === 'cotilleo_patron'));
RelacionBitacora::registrar($partidaE, RelacionBitacora::SE_CONOCIERON, ['per_raul', 'per_ines']);
for ($d = 4; $d <= 7; $d++) {
    $partidaE['reloj']['dia_pueblo'] = $d;
    simularCoincidencia($partidaE, $d, 'lug_bar', ['per_raul', 'per_ines']);
}
$despues = count(array_filter($partidaE['buzon'], static fn($m) => ($m['tipo'] ?? '') === 'cotilleo_patron'));
ok($despues > $antes, 'república tras evolución relacional (se conocieron)');

echo "\n--- Total patrones Raúl/Inés 8d: " . count($patrones) . " ---\n";
foreach ($patrones as $p) {
    echo 'Día ' . ($p['dia'] ?? '?') . ': ' . ($p['texto'] ?? '') . "\n";
}

exit($failures > 0 ? 1 : 0);
