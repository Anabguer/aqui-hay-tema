<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelojOperations;
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

$cal = CalibracionConfig::load($root);
$svc = new PartidaService($root);

$pNueva = $svc->nuevaPartida('juego_v1', 'vp-gameplay-nueva');
ok(FeatureConfig::isEnabled($pNueva, VidaPuebloEngine::FLAG), 'juego_v1 vida on');
ok(VidaPuebloEngine::valor($pNueva) === 65, 'partida nueva empieza en 65');

$p = $pNueva;
VidaPuebloEngine::aplicar($p, VidaPuebloEngine::DELTA_MISION_CUMPLIDA, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_JUGADOR,
    'atribuible_celestine' => true,
    'positivo_valido_latido' => true,
], $cal);
ok(VidaPuebloEngine::valor($p) === 67, 'misión cumplida +2');

VidaPuebloEngine::aplicar($p, VidaPuebloEngine::DELTA_MISION_FALLIDA, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_FALLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_SISTEMA,
    'atribuible_celestine' => true,
], $cal);
ok(VidaPuebloEngine::valor($p) === 64, 'misión fallida/caducada -3');

$encBien = [
    'id' => 'enc_vp_bien',
    'intencion' => 'celeste_organizado',
    'participantes' => ['per_a', 'per_b'],
];
$resBien = [
    'por_participante' => [
        'per_a' => ['resultado' => 'muy_bien'],
        'per_b' => ['resultado' => 'bien'],
    ],
];
$antesEnc = VidaPuebloEngine::valor($p);
$rEnc = VidaPuebloEngine::aplicarEncuentroOrganizado($p, $encBien, $resBien, $cal);
ok(($rEnc['resultado'] ?? '') === 'muy_bien' || ($rEnc['resultado'] ?? '') === 'bien', 'deriva resultado encuentro');
ok(VidaPuebloEngine::valor($p) === $antesEnc + 2, 'encuentro jugador muy_bien +2');

$encMal = [
    'id' => 'enc_vp_mal',
    'intencion' => 'celeste_organizado',
    'participantes' => ['per_a', 'per_b'],
];
$resMal = ['por_participante' => [
    'per_a' => ['resultado' => 'muy_mal'],
    'per_b' => ['resultado' => 'muy_mal'],
]];
$antesMal = VidaPuebloEngine::valor($p);
VidaPuebloEngine::aplicarEncuentroOrganizado($p, $encMal, $resMal, $cal);
ok(VidaPuebloEngine::valor($p) === $antesMal - 2, 'encuentro jugador muy_mal -2');

$encAuto = [
    'id' => 'enc_vp_auto',
    'intencion' => 'autonomo',
    'participantes' => ['per_a', 'per_b'],
];
$antesAuto = VidaPuebloEngine::valor($p);
VidaPuebloEngine::aplicarEncuentroOrganizado($p, $encAuto, $resMal, $cal);
ok(VidaPuebloEngine::valor($p) === $antesAuto, 'encuentro autónomo casual 0');

$pMax = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
    'features' => [VidaPuebloEngine::FLAG => true],
    'vida_pueblo' => ['valor' => 99, 'positivos_desde_latido' => 0, 'umbral_positivos_latido' => 25],
];
VidaPuebloEngine::ensure($pMax, $cal);
VidaPuebloEngine::aplicar($pMax, 5, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_JUGADOR,
    'atribuible_celestine' => true,
    'positivo_valido_latido' => false,
], $cal);
ok(VidaPuebloEngine::valor($pMax) === 99, 'clamp máximo sin latido cap 99');

$pLat = [
    'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 12],
    'features' => [VidaPuebloEngine::FLAG => true],
];
VidaPuebloEngine::ensure($pLat, $cal);
$latido = false;
for ($i = 0; $i < 40; $i++) {
    $rr = VidaPuebloEngine::aplicar($pLat, 2, [
        'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
        'origen' => VidaPuebloEngine::ORIGEN_JUGADOR,
        'atribuible_celestine' => true,
        'positivo_valido_latido' => true,
    ], $cal);
    if (!empty($rr['latido'])) {
        $latido = true;
        break;
    }
}
ok($latido, 'alcanzar 100 produce latido');
ok(VidaPuebloEngine::valor($pLat) === 75, 'post-latido 75');
$vistaLat = VidaPuebloEngine::vista($pLat, $cal);
ok(($vistaLat['corazon_pct'] ?? -1) === 75, 'corazon_pct coincide tras latido');

$pMin = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
    'features' => [VidaPuebloEngine::FLAG => true],
];
VidaPuebloEngine::ensure($pMin, $cal);
VidaPuebloEngine::aplicar($pMin, -65, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_FALLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_SISTEMA,
    'atribuible_celestine' => true,
], $cal);
ok(VidaPuebloEngine::valor($pMin) === 0, 'clamp mínimo 0');
ok(!empty($pMin['vida_pueblo']['game_over_pendiente']), 'game over pendiente');
ok(!empty($pMin['vida_pueblo']['game_over_activo']), 'game over activo con flag on');
ok(VidaPuebloEngine::derrotaVisibleEnPlay($pMin, $cal), 'derrota visible en play');

$est = $svc->estadoResumido($pNueva);
ok(($est['vida_pueblo']['corazon_pct'] ?? -1) === VidaPuebloEngine::valor($pNueva), 'API corazon_pct = valor real');

$pCad = $svc->nuevaPartida('playtest_01', 'vp-gameplay-cad');
$vidaCad = VidaPuebloEngine::valor($pCad);
$nPend = 0;
foreach (MisionDiariaEngine::delDia($pCad) as $m) {
    if (($m['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE) {
        $nPend++;
    }
}
$reloj = new RelojOperations($root);
$reloj->avanzar($pCad, 24);
ok(VidaPuebloEngine::valor($pCad) === $vidaCad - (3 * $nPend), 'caducadas aplican -3 cada una');

exit($failures > 0 ? 1 : 0);
