<?php
require __DIR__ . '/../src/autoload.php';
$c = AquiHayTema\Engine\CalibracionConfig::load(dirname(__DIR__));
$h = $c['hitos_relacionales'] ?? null;
echo json_encode($h, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
