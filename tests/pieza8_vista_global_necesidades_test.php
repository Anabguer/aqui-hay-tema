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
ok(isset($result['residentes']), 'Tiene residentes');

// Social: solo Ana (30=lo_necesita), Benja(50)=le_vendria_bien no cuenta
$lista = $result['residentes'];
// Build needs index by necessity for easier assertions
$necIndex = [];
foreach ($lista as $res) {
    foreach ($res['necesidades'] as $nec => $data) {
        if (!isset($necIndex[$nec])) $necIndex[$nec] = [];
        $necIndex[$nec][] = ['id' => $res['id'], 'nombre' => $res['nombre'], 'banda' => $data['banda'], 'valor' => $data['valor']];
    }
}

ok(isset($necIndex['social']), 'social present in data');
$socialLNV = array_filter($necIndex['social'] ?? [], fn($r) => $r['banda'] === 'lo_necesita' || $r['banda'] === 'en_rojo');
ok(count($socialLNV) === 1, 'social 1 residente crítico (Ana lo_necesita)');
$socialFirst = reset($socialLNV);
ok($socialFirst['id'] === 'r1', 'social residente es r1 (Ana)');

// Calma: Ana en_rojo (20)
ok(isset($necIndex['calma']), 'calma present in data');
$calmaCrit = array_filter($necIndex['calma'] ?? [], fn($r) => $r['banda'] === 'lo_necesita' || $r['banda'] === 'en_rojo');
ok(count($calmaCrit) === 1, 'calma 1 residente crítico (Ana en_rojo)');
$calmaFirst = reset($calmaCrit);
ok($calmaFirst['id'] === 'r1', 'calma residente es r1 (Ana)');

// Actividad: ninguna (todas bien)
$actividadCrit = array_filter($necIndex['actividad'] ?? [], fn($r) => $r['banda'] === 'lo_necesita' || $r['banda'] === 'en_rojo');
ok(count($actividadCrit) === 0, 'actividad sin residentes críticos (todas bien)');

// Diversion: Benja lo_necesita(30), Ana le_vendria_bien(60=no cuenta)
ok(isset($necIndex['diversion']), 'diversion present in data');
$divCrit = array_filter($necIndex['diversion'] ?? [], fn($r) => $r['banda'] === 'lo_necesita' || $r['banda'] === 'en_rojo');
ok(count($divCrit) === 1, 'diversion 1 residente crítico (Benja lo_necesita)');

// --- Test 4: verificar estructura de datos ---
echo "\nEstructura de datos:\n";
$result = PartidaService::vistaGlobalNecesidades($p);
$lista = $result['residentes'];
$ana = null;
foreach ($lista as $res) {
    if ($res['id'] === 'r1') { $ana = $res; break; }
}
ok($ana !== null, 'r1 (Ana) found in residentes');
ok($ana['necesidades']['social']['banda'] === 'lo_necesita', 'Ana social banda es lo_necesita');
ok($ana['necesidades']['social']['copy'] !== '', 'Ana social copy no vacía');

// --- Test 5: copia de texto ---
echo "\nCopy de texto:\n";
$result = PartidaService::vistaGlobalNecesidades($p);
$lista = $result['residentes'];
ok(count($lista) > 0, 'Hay residentes');
$first = $lista[0];
ok(isset($first['necesidades']), 'Residente tiene necesidades');
$firstNec = reset($first['necesidades']);
ok($firstNec['copy'] !== null, 'copy no es null');
ok(is_string($firstNec['copy']), 'copy es string');

// --- Test 6: todas las necesidades críticas ---
echo "\nTodas las necesidades críticas:\n";
$result = PartidaService::vistaGlobalNecesidades($p);
$lista = $result['residentes'];
$criticas = 0;
foreach ($lista as $res) {
    foreach ($res['necesidades'] as $nec => $data) {
        if ($data['banda'] === 'lo_necesita' || $data['banda'] === 'en_rojo') {
            $criticas++;
        }
    }
}
ok($criticas > 0, 'Hay necesidades críticas mostradas');
echo "  Mostrando $criticas necesidades críticas\n";

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