<?php
$f = dirname(__DIR__) . '/data/configs/calibracion_vida.json';
$j = json_decode(file_get_contents($f), true);
$j['hitos_relacionales']['max_pares_evaluados_por_dia'] = 24;
$j['hitos_relacionales']['crush_tercero']['max_intentos_por_dia'] = 12;
file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "ok\n";
