<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/src/dev_gate.php';

use AquiHayTema\Engine\StressTestRunner;

if (!aht_dev_enabled()) {
    putenv('AHT_DEV=1');
}

$r = StressTestRunner::run(dirname(__DIR__), 100);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($r['ok'] ? 0 : 1);
