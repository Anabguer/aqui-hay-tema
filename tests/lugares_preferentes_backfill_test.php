<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\SchemaFields;

DomainBootstrap::boot();
$root = dirname(__DIR__);

$partida = [
    'meta' => ['partida_id' => 'part_test_lugpref', 'seed' => 'seed_lugpref'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
    'residentes' => [
        'per_p001' => [
            'presencia' => 'residente',
            'runtime' => [
                'perfil_partida' => [
                    'fuente' => 'generado',
                    'hobbies' => ['costura'],
                ],
            ],
        ],
        'per_p002' => [
            'presencia' => 'residente',
            'runtime' => [
                'perfil_partida' => [
                    'fuente' => 'generado',
                    'lugares_preferentes' => ['lug_parque'],
                ],
            ],
        ],
    ],
];

SchemaFields::ensure($partida);

$prefs1 = $partida['residentes']['per_p001']['runtime']['perfil_partida']['lugares_preferentes'] ?? [];
assert(is_array($prefs1) && count($prefs1) >= 1, 'backfill asigna lugares_preferentes');
assert(
    ($partida['residentes']['per_p002']['runtime']['perfil_partida']['lugares_preferentes'] ?? []) === ['lug_parque'],
    'no pisa lugares_preferentes existentes'
);

$mapa = PresenciaEngine::resolver($partida, $root);
$presentes = 0;
foreach ($mapa['lugares'] as $lug) {
    $presentes += count($lug['residentes_presentes'] ?? []);
}
assert($presentes >= 1, 'presencia en mapa tras backfill');

// Idempotente
$fingerprint = json_encode($partida['residentes']['per_p001']['runtime']['perfil_partida']);
PerfilPartida::reconciliarLugaresPreferentes($partida);
assert(
    json_encode($partida['residentes']['per_p001']['runtime']['perfil_partida']) === $fingerprint,
    'reconciliar es idempotente'
);

echo "lugares_preferentes_backfill_test OK ($presentes presentes)\n";
