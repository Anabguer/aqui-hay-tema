<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;

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

echo "=== Pieza 8: vistaGlobalNecesidades ===\n\n";

// --- Test 1: feature gate desactivado ---
echo "Feature gate:\n";
$p = ['features' => ['necesidades_enabled' => false]];
$result = PartidaService::vistaGlobalNecesidades($p);
ok($result === null, 'Feature gate off -> null');

// --- Test 2: feature gate activado, sin residentes ---
echo "\nSin residentes:\n";
$p = ['features' => ['necesidades_enabled' => true]];
$result = PartidaService::vistaGlobalNecesidades($p);
ok($result === null, 'Sin residentes -> null');

// --- Test 3: con residentes con necesidades bajas ---
echo "\nCon residentes con necesidades bajas:\n";
$p = [
    'features' => ['necesidades_enabled' => true],
    'residentes' => [
        'r1' => [
            'nombre' => 'Ana',
            'runtime' => [
                'necesidades' => [
                    'social' => ['valor' => 30, 'banda' => 'lo_necesita'],
                    'diversion' => ['valor' => 60, 'banda' => 'le_vendria_bien'],
                    'actividad' => ['valor' => 85, 'banda' => 'bien'],
                    'calma' => ['valor' => 20, 'banda' => 'en_rojo'],
                ],
            ],
        ],
        'r2' => [
            'nombre' => 'Benja',
            'runtime' => [
                'necesidades' => [
                    'social' => ['valor' => 50, 'banda' => 'le_vendria_bien'],
                    'diversion' => ['valor' => 30, 'banda' => 'lo_necesita'],
                    'actividad' => ['valor' => 80, 'banda' => 'bien'],
                    'calma' => ['valor' => 85, 'banda' => 'bien'],
                ],
            ],
        ],
        'r3' => [
            'nombre' => 'Celeste',
            'runtime' => [
                'necesidades' => [
                    'social' => ['valor' => 85, 'banda' => 'bien'],
                    'diversion' => ['valor' => 80, 'banda' => 'bien'],
                    'actividad' => ['valor' => 90, 'banda' => 'bien'],
                    'calma' => ['valor' => 90, 'banda' => 'bien'],
                ],
            ],
        ],
    ],
];
$result = PartidaService::vistaGlobalNecesidades($p);
ok($result !== null, 'Con datos -> no null');
ok(isset($result['items']), 'Tiene items');

// Social: solo Ana (30=lo_necesita), Benja(50)=le_vendria_bien no cuenta
ok(isset($result['items']['social']), 'social en items');
$social = $result['items']['social'];
ok(count($social['residentes']) === 1, 'social 1 residente (Ana lo_necesita)');
ok($social['residentes'][0]['id'] === 'r1', 'social residente es r1 (Ana)');

// Calma: Ana en_rojo (20)
ok(isset($result['items']['calma']), 'calma en items');
$calma = $result['items']['calma'];
ok(count($calma['residentes']) === 1, 'calma 1 residente (Ana en_rojo)');
ok($calma['residentes'][0]['nombre'] === 'Ana', 'calma residente es Ana');

// Actividad: ninguna (todas bien)
ok(!isset($result['items']['actividad']), 'actividad filtrada (todas bien)');

// Diversion: Benja lo_necesita(30), Ana le_vendria_bien(60=no cuenta)
ok(isset($result['items']['diversion']), 'diversion en items');
$diversion = $result['items']['diversion'];
ok(count($diversion['residentes']) === 1, 'diversion 1 residente (Benja lo_necesita)');

// --- Test 4: verificar estructura de datos ---
echo "\nEstructura de datos:\n";
$result = PartidaService::vistaGlobalNecesidades($p);
if (isset($result['items']['social'])) {
    $s = $result['items']['social'];
    ok(isset($s['residentes']), 'social tiene residentes');
    ok($s['residentes'][0]['id'] === 'r1', 'primer residente r1 (Ana)');
    ok($s['residentes'][0]['copy'] !== '', 'copy no vacía');
    ok($s['residentes'][0]['banda'] === 'lo_necesita', 'banda es lo_necesita');
}

// --- Test 5: copia de texto ---
echo "\nCopy de texto:\n";
$result = PartidaService::vistaGlobalNecesidades($p);
ok(isset($result['items']['social']), 'social en items');
if (isset($result['items']['social'])) {
    $social = $result['items']['social'];
    // $social es un array con 'banda' y 'residentes'
    ok(is_array($social), 'social es array');
    if (is_array($social) && isset($social['residentes']) && count($social['residentes']) > 0) {
        $first = $social['residentes'][0] ?? null;
        ok($first !== null, 'primer residente existe');
        if ($first !== null) {
            ok(isset($first['copy']), 'residente tiene copy');
            ok($first['copy'] !== null, 'copy no es null');
            ok(is_string($first['copy']), 'copy es string');
        }
    }
}

// --- Test 6: todas las necesidades críticas ---
echo "\nTodas las necesidades críticas:\n";
$result = PartidaService::vistaGlobalNecesidades($p);
$criticas = 0;
foreach ($result['items'] as $nec => $data) {
    $criticas += count($data['residentes']);
}
ok($criticas > 0, 'Hay necesidades críticas mostradas');
echo "  Mostrando $criticas residentes con necesidades críticas\n";

// --- Test 7: sin necesidades bajas ---
echo "\nSin necesidades bajas:\n";
$p2 = [
    'features' => ['necesidades_enabled' => true],
    'residentes' => [
        'r1' => [
            'nombre' => 'Paco',
            'runtime' => [
                'necesidades' => [
                    'social' => ['valor' => 90, 'banda' => 'bien'],
                    'diversion' => ['valor' => 90, 'banda' => 'bien'],
                    'actividad' => ['valor' => 90, 'banda' => 'bien'],
                    'calma' => ['valor' => 90, 'banda' => 'bien'],
                ],
            ],
        ],
    ],
];
$result = PartidaService::vistaGlobalNecesidades($p2);
ok($result === null, 'Todas bien -> null');

// --- Test 8: vacío ---
echo "\nVacío:\n";
$result = PartidaService::vistaGlobalNecesidades([]);
ok($result === null, 'Partida vacía -> null');

echo "\n=== Resultado: $ok ok, $fail fail ===\n";
exit($fail > 0 ? 1 : 0);