<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\PartidaService;

$failures = 0;
$checks = 0;

function ok(bool $c, string $m): void
{
    global $failures, $checks;
    $checks++;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

echo "=== E2E: runtime → vistaNecesidades → fichaResidente(JSON) → JS input ===\n\n";

$root = dirname(__DIR__);
$service = new PartidaService($root);

$p = $service->nuevaPartida('juego_v1', 'e2e-nec-valor');
$rid = (string) array_key_first($p['residentes'] ?? []);
ok($rid !== '' && isset($p['residentes'][$rid]), "partida creada, residente=$rid");

$valores = [
    'social'    => 20,
    'diversion' => 30,
    'actividad' => 40,
    'calma'     => 15,
];

$bandasEsperadas = [];
foreach ($valores as $nec => $v) {
    $bandasEsperadas[$nec] = NecesidadEstado::calcularBanda($v);
}

$p['residentes'][$rid]['runtime']['necesidades'] = [];
foreach ($valores as $nec => $v) {
    $p['residentes'][$rid]['runtime']['necesidades'][$nec] = [
        'valor' => $v,
        'banda' => NecesidadEstado::calcularBanda($v),
    ];
}

echo "Input values:\n";
foreach ($valores as $nec => $v) {
    echo "  $nec=$v ({$bandasEsperadas[$nec]})\n";
}
echo "\n";

// STEP 1
$residente = &$p['residentes'][$rid];
$obtener = NecesidadEstado::obtener($residente);
foreach ($valores as $nec => $v) {
    ok($obtener[$nec]['valor'] === $v, "STEP 1: obtener().$nec.valor == $v");
}

// STEP 2
$method = new ReflectionMethod(PartidaService::class, 'vistaNecesidades');
$necesidadesVista = $method->invoke(null, $residente, $p);
ok($necesidadesVista !== null, 'STEP 2a: vistaNecesidades returns non-null');

$items = $necesidadesVista['items'] ?? [];
$itemMap = [];
foreach ($items as $it) { $itemMap[$it['id']] = $it; }
ok(count($items) === 4, 'STEP 2b: vistaNecesidades returns 4 items');

foreach ($valores as $nec => $v) {
    ok(isset($itemMap[$nec]), "STEP 2c: $nec in vistaNecesidades items");
    if (isset($itemMap[$nec])) {
        ok($itemMap[$nec]['valor'] === $v, "STEP 2d: $nec valor in vistaNecesidades == $v (got {$itemMap[$nec]['valor']})");
        ok($itemMap[$nec]['banda'] === $bandasEsperadas[$nec], "STEP 2e: $nec banda == {$bandasEsperadas[$nec]}");
    }
}

// STEP 3
$ficha = $service->fichaResidente($p, $rid, true);
ok(is_array($ficha), 'STEP 3a: fichaResidente returns array');
ok(isset($ficha['vista_play']), 'STEP 3b: ficha has vista_play');
ok(array_key_exists('necesidades', $ficha), 'STEP 3c: ficha has necesidades key');

// STEP 4
$necFromFicha = $ficha['necesidades'];
if ($necFromFicha !== null && isset($necFromFicha['items'])) {
    $fichaItems = $necFromFicha['items'];
    $fichaMap = [];
    foreach ($fichaItems as $it) { $fichaMap[$it['id']] = $it; }

    foreach ($valores as $nec => $v) {
        ok(isset($fichaMap[$nec]), "STEP 4a: $nec present in ficha.necesidades.items");
        if (isset($fichaMap[$nec])) {
            ok($fichaMap[$nec]['valor'] === $v, "STEP 4b: $nec valor in ficha == $v (got {$fichaMap[$nec]['valor']})");
            ok($fichaMap[$nec]['banda'] === $bandasEsperadas[$nec], "STEP 4c: $nec banda in ficha == {$bandasEsperadas[$nec]}");
        }
    }
} else {
    ok(false, "STEP 4a: ficha.necesidades is null or empty!");
}

// STEP 5
$vp = $ficha['vista_play'];
ok(!isset($vp['necesidades']), 'STEP 5: vista_play does NOT contain necesidades (confirmed separate key)');

// STEP 6
$jsonPayload = json_encode($ficha, JSON_UNESCAPED_UNICODE);
ok($jsonPayload !== false, 'STEP 6a: ficha JSON-encodes');
$decoded = json_decode($jsonPayload, true);
ok(isset($decoded['necesidades']), 'STEP 6b: decoded JSON has necesidades');

// STEP 7: Exact frontend path
$apiResponse = ['ok' => true, 'ficha' => $decoded];
$r = $apiResponse;
$f = $r['ficha'] ?? [];
ok(isset($f['necesidades']), 'STEP 7a: f.necesidades exists (JS side)');

$nec = $f['necesidades'] ?? null;
if ($nec !== null && isset($nec['items']) && count($nec['items']) > 0) {
    $domMap = [];
    foreach ($nec['items'] as $item) {
        $val = max(0, min(100, (int) ($item['valor'] ?? 0)));
        $domMap[$item['id']] = [
            'valor' => $val,
            'banda' => $item['banda'] ?? '',
        ];
    }

    foreach ($valores as $necId => $v) {
        ok(isset($domMap[$necId]), "STEP 7b: $necId rendered in DOM");
        if (isset($domMap[$necId])) {
            ok($domMap[$necId]['valor'] === $v, "STEP 7c: $necId DOM valor == $v (got {$domMap[$necId]['valor']})");
        }
    }
    ok(true, "STEP 7d: Seccion necesidades VISIBLE (items=" . count($nec['items']) . ")");
} else {
    ok(false, "STEP 7d: Seccion necesidades OCULTA - items no llegan al JS!");
}

// FULL CHAIN DUMP
echo "\n=== FULL CHAIN DUMP ===\n";
echo "1. runtime.necesidades:\n";
foreach ($valores as $nec => $v) {
    $n = $residente['runtime']['necesidades'][$nec];
    echo "   $nec: valor={$n['valor']}, banda={$n['banda']}\n";
}
echo "\n2. vistaNecesidades() items:\n";
foreach ($items as $it) {
    echo "   {$it['id']}: valor={$it['valor']}, banda={$it['banda']}\n";
}
echo "\n3. fichaResidente() out[necesidades]:\n";
if (isset($ficha['necesidades']['items'])) {
    foreach ($ficha['necesidades']['items'] as $it) {
        echo "   {$it['id']}: valor={$it['valor']}, banda={$it['banda']}\n";
    }
} else {
    echo "   (null)\n";
}
echo "\n4. JSON -> f.necesidades.items:\n";
if ($nec !== null && isset($nec['items']) && count($nec['items']) > 0) {
    foreach ($nec['items'] as $it) {
        $val = max(0, min(100, (int) ($it['valor'] ?? 0)));
        echo "   {$it['id']}: valor=$val (JS parseInt -> bar width {$val}%)\n";
    }
} else {
    echo "   (section hidden)\n";
}

echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "{$failures} of {$checks} TESTS FAILED") . "\n";
exit($failures > 0 ? 1 : 0);
