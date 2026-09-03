<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\NecesidadPeticionBridge;

$ok = 0;
$fail = 0;

function ok(bool $cond, string $msg): void
{
    global $ok, $fail;
    if ($cond) {
        $ok++;
        echo "  OK: $msg\n";
    } else {
        $fail++;
        echo "  FAIL: $msg\n";
    }
}

function makePartidaConNecesidades(array $necesidades): array
{
    $res = [];
    $res['features'] = ['necesidades_enabled' => true];
    $res['residentes'] = [
        'r1' => [
            'runtime' => [
                'necesidades' => $necesidades,
            ],
        ],
    ];
    return $res;
}

echo "=== PIEZA 7: NecesidadPeticionBridge ===\n\n";

// --- Test 1: boostPrioridad con feature gate ---
echo "Feature gate:\n";
$p = [
    'features' => ['necesidades_enabled' => false],
    'residentes' => [
        'r1' => [
            'runtime' => [
                'necesidades' => [
                    'social' => ['valor' => 30],
                ],
            ],
        ],
    ],
];
ok(NecesidadPeticionBridge::boostPrioridad($p, 'r1', ['id' => 'ir_al_lugar']) === 0, 'Feature gate desactivado -> boost 0');

$p2 = makePartidaConNecesidades(['social' => ['valor' => 30]]);
ok(NecesidadPeticionBridge::boostPrioridad($p2, 'r1', ['id' => 'ir_al_lugar']) > 0, 'Feature gate activado -> boost > 0');

// --- Test 2: boostPrioridad por plantilla ---
echo "\nBoost por plantilla:\n";
$necs = [
    'social' => ['valor' => 30],
    'diversion' => ['valor' => 60],
    'actividad' => ['valor' => 80],
    'calma' => ['valor' => 90],
];
$p = makePartidaConNecesidades($necs);

// social(30)=lo_necesita(10), diversion(60)=le_vendria_bien(4), actividad(80)=bien(0), calma(90)=bien(0)
$boost = NecesidadPeticionBridge::boostPrioridad($p, 'r1', ['id' => 'ir_al_lugar']);
ok($boost === 14, "ir_al_lugar: social lo_necesita(10) + diversion le_vendria(4) = 14");

$boost2 = NecesidadPeticionBridge::boostPrioridad($p, 'r1', ['id' => 'salir_de_casa']);
ok($boost2 === 14, "salir_de_casa: same = 14");

$boost3 = NecesidadPeticionBridge::boostPrioridad($p, 'r1', ['id' => 'conocer_a_alguien']);
ok($boost3 === 10, "conocer_a_alguien: solo social lo_necesita = 10");

// --- Test 3: sin necesidades bajas ---
echo "\nSin necesidades bajas:\n";
$p2 = makePartidaConNecesidades([
    'social' => ['valor' => 85],
    'diversion' => ['valor' => 80],
    'actividad' => ['valor' => 90],
    'calma' => ['valor' => 75],
]);
ok(NecesidadPeticionBridge::boostPrioridad($p2, 'r1', ['id' => 'ir_al_lugar']) === 0, 'Todas bien -> boost 0');

// --- Test 4: sin runtime.necesidades ---
echo "\nSin runtime.necesidades:\n";
$p3 = [
    'features' => ['necesidades_enabled' => true],
    'residentes' => ['r1' => ['runtime' => []]],
];
ok(NecesidadPeticionBridge::boostPrioridad($p3, 'r1', ['id' => 'ir_al_lugar']) === 0, 'Sin runtime.necesidades -> boost 0');

// --- Test 5: necesidadMasUrgente ---
echo "\nnecesidadMasUrgente:\n";
$p = makePartidaConNecesidades([
    'social' => ['valor' => 60],
    'diversion' => ['valor' => 25],
    'actividad' => ['valor' => 80],
    'calma' => ['valor' => 70],
]);
ok(NecesidadPeticionBridge::necesidadMasUrgente($p, 'r1') === 'diversion', 'Mas baja es diversion (25)');

$p4 = makePartidaConNecesidades([
    'social' => ['valor' => 85],
    'diversion' => ['valor' => 90],
    'actividad' => ['valor' => 80],
    'calma' => ['valor' => 75],
]);
ok(NecesidadPeticionBridge::necesidadMasUrgente($p4, 'r1') === null, 'Todas >= 75 -> null');

// --- Test 6: copiasDesdeNecesidad ---
echo "\ncopiasDesdeNecesidad:\n";
ok(count(NecesidadPeticionBridge::copiasDesdeNecesidad('social')) === 4, 'social 4 copias');
ok(count(NecesidadPeticionBridge::copiasDesdeNecesidad('diversion')) === 4, 'diversion 4 copias');
ok(count(NecesidadPeticionBridge::copiasDesdeNecesidad('actividad')) === 4, 'actividad 4 copias');
ok(count(NecesidadPeticionBridge::copiasDesdeNecesidad('calma')) === 4, 'calma 4 copias');
ok(count(NecesidadPeticionBridge::copiasDesdeNecesidad('otra')) === 0, 'desconocida -> 0 copias');

// --- Test 7: boost > 0 cuando hay necesidad baja ---
echo "\nBoost > 0 con necesidad baja:\n";
$p = makePartidaConNecesidades(['social' => ['valor' => 20]]);
ok(NecesidadPeticionBridge::boostPrioridad($p, 'r1', ['id' => 'conocer_a_alguien']) > 0, 'social en_rojo -> boost > 0');

$p = makePartidaConNecesidades(['diversion' => ['valor' => 15]]);
ok(NecesidadPeticionBridge::boostPrioridad($p, 'r1', ['id' => 'ir_al_lugar']) > 0, 'diversion en_rojo -> boost > 0');

// --- Test 8: boost = 0 cuando todas bien ---
echo "\nBoost = 0 cuando todas bien:\n";
$p = makePartidaConNecesidades([
    'social' => ['valor' => 90],
    'diversion' => ['valor' => 90],
    'actividad' => ['valor' => 90],
    'calma' => ['valor' => 90],
]);
ok(NecesidadPeticionBridge::boostPrioridad($p, 'r1', ['id' => 'conocer_a_alguien']) === 0, 'Todas 90 -> boost 0');

echo "\n=== Resultado: $ok ok, $fail fail ===\n";
exit($fail > 0 ? 1 : 0);