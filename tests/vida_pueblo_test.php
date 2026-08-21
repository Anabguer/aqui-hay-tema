<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\SchemaFields;
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

function partidaMin(): array
{
    return [
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10, 'ultima_sesion_iso' => null],
        'features' => [VidaPuebloEngine::FLAG => false],
        'audit_trail' => [],
    ];
}

$cal = CalibracionConfig::load($root);
ok((int) CalibracionConfig::get($cal, 'voluntad.base', 0) === 48, 'no toca voluntad.base');
ok((int) CalibracionConfig::get($cal, 'voluntad.mod_tipo.conocerse', 0) === 34, 'no toca conocerse +34');

$p = partidaMin();
VidaPuebloEngine::ensure($p, $cal);
ok(VidaPuebloEngine::valor($p) === 65, 'inicial 65');
ok(VidaPuebloEngine::banda(65, $cal)['id'] === VidaPuebloEngine::BANDA_TEMITA, 'banda 65 Hay temita');
ok(VidaPuebloEngine::banda(0, $cal)['etiqueta'] === 'Se nos va de las manos', 'banda 0');
ok(VidaPuebloEngine::banda(100, $cal)['id'] === VidaPuebloEngine::BANDA_LATIDO, 'banda 100 Latido');
ok(VidaPuebloEngine::vista($p, $cal)['etiqueta'] === 'Hay temita', 'vista sin número');
ok(!isset(VidaPuebloEngine::vista($p, $cal)['valor']), 'vista no expone el entero');

$rRng = VidaPuebloEngine::aplicar($p, -10, [
    'causa' => VidaPuebloEngine::CAUSA_LAB,
    'origen' => VidaPuebloEngine::ORIGEN_NPC_RNG,
    'atribuible_celestine' => true,
    'positivo_valido_latido' => false,
], $cal);
ok(($rRng['ok'] ?? true) === false, 'NPC RNG rechazado aunque meta.atribuible=true');
ok(VidaPuebloEngine::valor($p) === 65, 'RNG no mueve Vida');

$rEmo = VidaPuebloEngine::aplicar($p, -8, [
    'causa' => 'uña_rota',
    'origen' => 'emocion_rng',
    'atribuible_celestine' => false,
], $cal);
ok(($rEmo['ok'] ?? true) === false, 'no atribuible rechazado');
ok(VidaPuebloEngine::valor($p) === 65, 'emoción RNG no mueve Vida');

$bridge = file_get_contents($root . '/src/Engine/EmotionalEventBridge.php');
ok(strpos($bridge, 'VidaPuebloEngine') === false, 'EmotionalEventBridge no escribe Vida');
$motor = file_get_contents($root . '/src/Engine/MotorVidaDiaria.php');
ok(strpos($motor, 'VidaPuebloEngine') === false, 'MotorVidaDiaria no escribe Vida');

$r1 = VidaPuebloEngine::aplicar($p, 3, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
    'positivo_valido_latido' => true,
    'lab' => true,
], $cal);
ok(($r1['ok'] ?? false) && VidaPuebloEngine::valor($p) === 68, 'misión lab +3');
ok(count($p['vida_pueblo']['ledger']) === 1, 'ledger registra el cambio');
$le = $p['vida_pueblo']['ledger'][0];
ok($le['atribuible_celestine'] === true && $le['positivo_valido_latido'] === true, 'ledger campos requeridos');
ok(isset($le['causa'], $le['origen'], $le['dia'], $le['hora'], $le['delta']), 'ledger causa/origen/reloj');

$pFarm = partidaMin();
VidaPuebloEngine::ensure($pFarm, $cal);
for ($i = 0; $i < 50; $i++) {
    VidaPuebloEngine::aplicar($pFarm, 1, [
        'causa' => VidaPuebloEngine::CAUSA_LAB,
        'origen' => VidaPuebloEngine::ORIGEN_LAB,
        'atribuible_celestine' => true,
        'positivo_valido_latido' => false,
        'lab' => true,
    ], $cal);
}
ok(VidaPuebloEngine::valor($pFarm) === 99, 'anti-farm: sin positivos válidos cap 99');
ok((int) $pFarm['vida_pueblo']['latidos'] === 0, 'anti-farm: 0 Latidos');

$pLat = partidaMin();
VidaPuebloEngine::ensure($pLat, $cal);
$latidos = 0;
for ($i = 0; $i < 40; $i++) {
    $rr = VidaPuebloEngine::aplicar($pLat, 3, [
        'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
        'origen' => VidaPuebloEngine::ORIGEN_LAB,
        'atribuible_celestine' => true,
        'positivo_valido_latido' => true,
        'lab' => true,
    ], $cal);
    if ($rr['latido'] ?? false) {
        $latidos++;
        break;
    }
}
ok($latidos === 1, 'primer Latido al llegar a 100 con ≥25 válidos');
ok(VidaPuebloEngine::valor($pLat) === 75, 'resaca a 75');
ok((int) $pLat['vida_pueblo']['positivos_desde_latido'] === 0, 'contador válidos se resetea');
ok((int) $pLat['vida_pueblo']['latidos'] === 1, 'latidos=1');

$antes2 = VidaPuebloEngine::valor($pLat);
VidaPuebloEngine::aplicar($pLat, 3, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
    'positivo_valido_latido' => true,
    'lab' => true,
], $cal);
ok(VidaPuebloEngine::valor($pLat) === $antes2 + 3, 'tras Latido sube desde 75');
ok((int) $pLat['vida_pueblo']['latidos'] === 1, 'segundo Latido no dispara con 3 puntos');

$pLat2 = $pLat;
$got2 = false;
for ($i = 0; $i < 30; $i++) {
    $rr = VidaPuebloEngine::aplicar($pLat2, 3, [
        'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
        'origen' => VidaPuebloEngine::ORIGEN_LAB,
        'atribuible_celestine' => true,
        'positivo_valido_latido' => true,
        'lab' => true,
    ], $cal);
    if ($rr['latido'] ?? false) {
        $got2 = true;
        break;
    }
}
ok($got2 && (int) $pLat2['vida_pueblo']['latidos'] === 2, 'segundo Latido exige 25 válidos nuevos y 100');
ok(VidaPuebloEngine::valor($pLat2) === 75, 'segunda resaca a 75');

$pGo = partidaMin();
VidaPuebloEngine::ensure($pGo, $cal);
VidaPuebloEngine::aplicar($pGo, -65, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_FALLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
    'lab' => true,
], $cal);
ok(VidaPuebloEngine::valor($pGo) === 0, 'clamp 0');
ok(($pGo['vida_pueblo']['game_over_pendiente'] ?? false) === true, 'GO pendiente al llegar a 0');
ok(($pGo['vida_pueblo']['game_over_activo'] ?? true) === false, 'GO no activo en PLAY (flag/cal off)');
ok(VidaPuebloEngine::derrotaVisibleEnPlay($pGo, $cal) === false, 'derrota no visible');

$pOff = partidaMin();
VidaPuebloEngine::ensure($pOff, $cal);
$off = VidaPuebloEngine::aplicarAusencia($pOff, -80, ['lab' => true], $cal);
ok(VidaPuebloEngine::valor($pOff) === 50, 'offline cap −15 desde 65 → 50');
ok(($off['delta_capeado'] ?? 0) === -15, 'capeado −15');
ok(($pOff['vida_pueblo']['game_over_pendiente'] ?? true) === false, 'offline no deja GO pendiente');

$pOffC = partidaMin();
VidaPuebloEngine::ensure($pOffC, $cal);
VidaPuebloEngine::aplicar($pOffC, -53, [
    'causa' => VidaPuebloEngine::CAUSA_LAB_SETUP,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
    'lab' => true,
], $cal);
ok(VidaPuebloEngine::valor($pOffC) === 12, 'setup crítico 12');
VidaPuebloEngine::aplicarAusencia($pOffC, -40, ['lab' => true], $cal);
ok(VidaPuebloEngine::valor($pOffC) === 5, 'offline desde 12 suelo 5');
ok(($pOffC['vida_pueblo']['game_over_pendiente'] ?? true) === false, 'nunca GO exclusivamente offline');
ok(($pOffC['vida_pueblo']['llego_a_cero'] ?? false) === false, 'offline no marca llego_a_cero');

$vieja = ['reloj' => ['dia_pueblo' => 4, 'hora_actual' => 8], 'residentes' => []];
SchemaFields::ensure($vieja);
ok(isset($vieja['vida_pueblo']['valor']), 'schema aditivo en save viejo');
ok((int) $vieja['vida_pueblo']['valor'] === 65, 'save viejo recibe 65');

$service = new PartidaService($root);
$play = $service->nuevaPartida('playtest_01', 'vida-b1-flag');
ok(!empty($play['features'][VidaPuebloEngine::FLAG]), 'playtest enciende vida_pueblo');
ok(!empty($play['features'][MisionDiariaEngine::FLAG]), 'playtest enciende misiones diarias');
ok(!empty($play['features'][PeticionPuebloEngine::FLAG]), 'playtest enciende peticiones pueblo');
ok(isset($play['vida_pueblo']['valor']), 'partida nueva tiene bloque vida_pueblo');
ok(FeatureConfig::isEnabled($play, 'economy_enabled') === false, 'economía sigue off');

exit($failures > 0 ? 1 : 0);
