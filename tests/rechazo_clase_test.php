<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CopyVoluntad;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

/** @param array<string, mixed> $partida */
function basePartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 14],
        'residentes' => [
            'per_a' => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
            'per_b' => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_biblioteca', 'lug_cafeteria']],
        'propuestas_encuentro' => [],
        'encuentros' => [],
        'relaciones' => [],
        'relaciones_romanticas' => [],
        'rng' => ['seed' => 'test-rechazo-clase', 'cursor' => 0],
    ];
}

// --- A. rechazo banal → clase voluntad (respuesta pública) ---
$p1 = basePartida();
$propBanal = [
    'estado' => 'rechazada',
    'participantes' => ['per_a', 'per_b'],
    'reacciones' => [
        [
            'residente_id' => 'per_a',
            'nombre' => 'Carmen',
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => 'voluntad_rechaza_plan_geom_banal',
            'copy_id' => 'pintar_unias',
        ],
        [
            'residente_id' => 'per_b',
            'nombre' => 'Marta',
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
        ],
    ],
];
$ref = new ReflectionClass(PropuestaEncuentroEngine::class);
$mResp = $ref->getMethod('respuestaPropuesta');
$mResp->setAccessible(true);
/** @var array<string, mixed> $outBanal */
$outBanal = $mResp->invoke(null, $propBanal);
ok(($outBanal['rechazo_clase'] ?? '') === PropuestaEncuentro::CLASE_VOLUNTAD, 'rechazo banal → rechazo_clase voluntad');
ok(($outBanal['error'] ?? '') === 'ENCUENTRO_RECHAZADO_VOLUNTAD', 'rechazo banal → error VOLUNTAD');

// --- B. cooldown → clase cooldown (respuesta pública) ---
$p2 = basePartida();
PropuestaCooldown::marcar($p2, 'per_a', 'per_b', 'conocerse', []);
$propCd = [
    'estado' => 'rechazada',
    'participantes' => ['per_a', 'per_b'],
    'tipo' => 'conocerse',
    'reacciones' => [
        [
            'residente_id' => 'per_a',
            'nombre' => 'Carmen',
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
        ],
        [
            'residente_id' => 'per_b',
            'nombre' => 'Marta',
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_COOLDOWN,
            'motivo_tecnico' => 'cooldown_propuesta',
            'copy_id' => 'hoy_no_me_da_la_vida',
            'p' => 0.0,
        ],
    ],
];
/** @var array<string, mixed> $outCd */
$outCd = $mResp->invoke(null, $propCd);
ok(($outCd['rechazo_clase'] ?? '') === PropuestaEncuentro::CLASE_COOLDOWN, 'cooldown → rechazo_clase cooldown');
ok(($outCd['error'] ?? '') === 'ENCUENTRO_RECHAZADO_COOLDOWN', 'cooldown → error COOLDOWN');
ok(strpos((string) ($outCd['mensaje_ui'] ?? ''), 'Marta') !== false, 'cooldown mantiene copy con hablante');

// --- C. catálogo cooldown variado ---
$rng = \AquiHayTema\Engine\RngService::fromPartida($p2);
$ids = [];
for ($i = 0; $i < 20; $i++) {
    $ids[] = VoluntadPonderadaEvaluator::copyCooldownPublic($rng, []);
}
$uniq = count(array_unique($ids));
ok($uniq >= 2, 'pool cooldown tiene variedad (>=2 textos en 20 tiradas)');
foreach ($ids as $id) {
    ok(isset(CopyVoluntad::TEXTOS[$id]), 'copy cooldown id válido: ' . $id);
}

echo "\nTodas las comprobaciones de rechazo_clase pasaron.\n";
