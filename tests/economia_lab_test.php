<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\SimuladorEconomia;

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

$lab = SimuladorEconomia::ejecutar($root, [30, 100, 365], 2, 'lab-eco-test');
ok(!empty($lab['_provisional']), 'lab marcado provisional');
ok(($lab['_canon'] ?? true) === false, 'lab declara no canon');
ok(isset($lab['modelos']['generosa']['por_perfil']['A']['por_horizonte']['30']), 'modelo generosa perfil A 30d');
ok(isset($lab['modelos']['media']['por_perfil']['F']['por_horizonte']['365']), 'modelo media farmer 365d');
ok(isset($lab['modelos']['lenta']['por_perfil']['C']['por_horizonte']['100']), 'modelo lenta casual 100d');

$f30 = $lab['modelos']['media']['por_perfil']['F']['por_horizonte']['30'];
ok((int) ($f30['pct_bloque_b'] ?? 99) === 0, 'farmer no compra Bloque B antes del gate temporal (30d)');

$features = FeatureConfig::defaults($root);
ok(empty($features['economy_enabled']), 'economy_enabled sigue OFF en features.json');

$play = file_get_contents($root . '/assets/js/play.js');
ok($play !== false && strpos($play, 'SimuladorEconomia') === false, 'PLAY no cablea el lab de economía');

$ledger = file_get_contents($root . '/src/Engine/EconomyLedger.php');
ok($ledger !== false && strpos($ledger, '_placeholder') !== false, 'EconomyLedger sigue siendo placeholder');

ok(isset($lab['bloqueado_decision'][0]['pregunta']), 'lab expone BLOQUEADO_DECISION');
ok(($lab['recomendacion']['modelo'] ?? '') === 'media', 'recomendación apunta a modelo media');

$src = file_get_contents($root . '/src/Engine/SimuladorEconomia.php');
ok($src !== false && strpos($src, 'economy_enabled') !== false && strpos($src, 'No enciende economy_enabled') !== false, 'lab declara que no enciende economía');

exit($failures > 0 ? 1 : 0);
