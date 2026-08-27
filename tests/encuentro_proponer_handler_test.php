<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\EncuentrosHandler;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\VidaPuebloEngine;

$root = dirname(__DIR__);
$ctx = new ApiContext($root);
$cal = CalibracionConfig::load($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function idsResidentes(array $p): array
{
    return array_values(array_keys(array_filter(
        $p['residentes'] ?? [],
        static fn ($r) => is_array($r) && ($r['presencia'] ?? '') === 'residente'
    )));
}

function proponerHandler(ApiContext $ctx, array &$p, array $body): array
{
    $ctx->partidaCargadaSincronizada = true;
    return EncuentrosHandler::proponer($ctx, $body, $p);
}

// A) Propuesta normal vía handler (sin excepción, JSON de negocio)
$svc = $ctx->service;
$pA = $svc->nuevaPartida('juego_v1', 'handler_norm_' . time());
$pA['reloj'] = ['dia_pueblo' => 2, 'hora_actual' => 10];
FeatureConfig::mergeIntoPartida($pA, $root);
$idsA = idsResidentes($pA);
$a = (string) $idsA[0];
$b = (string) $idsA[1];
foreach ([$a, $b] as $rid) {
    $pA['residentes'][$rid]['runtime']['estado_emocional'] = [
        'id' => 'alegre',
        'hasta' => ['dia_pueblo' => 10, 'hora_actual' => 23],
        'origen' => 'test',
    ];
}
$bodyA = [
    'partida_id' => (string) ($pA['meta']['partida_id'] ?? ''),
    'residente_a' => $a,
    'residente_b' => $b,
    'dia' => 2,
    'hora' => 14,
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
];
try {
    $rA = proponerHandler($ctx, $pA, $bodyA);
} catch (Throwable $e) {
    ok(false, 'A: handler no lanza excepcion (' . $e->getMessage() . ')');
    $rA = [];
}
ok(!isset($rA['mensaje']) || !str_contains((string) ($rA['mensaje'] ?? ''), 'undefined method'), 'A: sin fatal PHP');
$estadoA = (string) (($rA['propuesta']['estado'] ?? '') ?: '');
ok(
    ($rA['ok'] ?? false) === true
    || in_array($rA['error'] ?? '', [
        GameError::ENCUENTRO_RECHAZADO_VOLUNTAD,
        GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD,
        GameError::ENCUENTRO_RECHAZADO_COOLDOWN,
        GameError::TIPO_ENCUENTRO_NO_DISPONIBLE,
    ], true)
    || in_array($estadoA, ['programada', 'rechazada', 'propuesta'], true),
    'A: respuesta JSON de negocio valida (' . ($rA['error'] ?? $estadoA ?: 'ok') . ')'
);

// B) Cooldown de pareja vía handler
$pB = $svc->nuevaPartida('juego_v1', 'handler_cd_' . time());
$pB['reloj'] = ['dia_pueblo' => 2, 'hora_actual' => 9];
FeatureConfig::mergeIntoPartida($pB, $root);
$idsB = idsResidentes($pB);
$aB = (string) $idsB[0];
$bB = (string) $idsB[1];
PropuestaCooldown::marcar($pB, $aB, $bB, 'conocerse', []);
$bodyB = [
    'residente_a' => $aB,
    'residente_b' => $bB,
    'dia' => 2,
    'hora' => 11,
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
];
try {
    $rB = proponerHandler($ctx, $pB, $bodyB);
} catch (Throwable $e) {
    ok(false, 'B: handler cooldown no lanza (' . $e->getMessage() . ')');
    $rB = [];
}
ok(($rB['error'] ?? '') === GameError::ENCUENTRO_RECHAZADO_COOLDOWN, 'B: ENCUENTRO_RECHAZADO_COOLDOWN');
ok(($rB['mensaje_ui'] ?? '') !== '', 'B: mensaje_ui presente');

// C) Partida perdida → rechazo limpio
$pC = $svc->nuevaPartida('juego_v1', 'handler_perd_' . time());
FeatureConfig::mergeIntoPartida($pC, $root);
$pC['features'][VidaPuebloEngine::FLAG] = true;
$pC['vida_pueblo']['game_over_activo'] = true;
$idsC = idsResidentes($pC);
$rDirect = VidaPuebloEngine::rechazoSiPerdida($pC, $cal);
ok(is_array($rDirect) && ($rDirect['error'] ?? '') === 'partida_perdida', 'C: rechazoSiPerdida devuelve partida_perdida');
$bodyC = [
    'residente_a' => (string) $idsC[0],
    'residente_b' => (string) ($idsC[1] ?? $idsC[0]),
    'dia' => 2,
    'hora' => 14,
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
];
$rC = proponerHandler($ctx, $pC, $bodyC);
ok(($rC['error'] ?? '') === 'partida_perdida', 'C: handler devuelve partida_perdida sin excepcion');
ok(!empty($rC['mensaje_ui']), 'C: mensaje_ui de derrota');

echo $failures === 0
    ? "\nencuentro_proponer_handler_test OK\n"
    : "\nencuentro_proponer_handler_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
