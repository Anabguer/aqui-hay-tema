#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/src/dev_gate.php';

use AquiHayTema\Engine\SimulationRunner;

if (!aht_dev_enabled()) {
    putenv('AHT_DEV=1');
}

$days = 30;
$seed = null;
$config = 'test_fixtures_v0';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--seed=')) {
        $seed = substr($arg, 7);
    } elseif (str_starts_with($arg, '--config=')) {
        $config = substr($arg, 9);
    }
}

$r = SimulationRunner::run(dirname(__DIR__), $days, $seed, $config);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($r['ok'] ? 0 : 1);
