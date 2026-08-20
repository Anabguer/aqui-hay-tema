<?php
$j = json_decode(file_get_contents(__DIR__ . '/../data/configs/calibracion_vida.json'), true);
foreach (['pareja', 'crisis', 'ruptura', 'terceros', 'flechazo', 'romance', 'social', 'laboratorio_relacional', 'desgaste_pareja'] as $k) {
    echo "=== $k ===\n";
    echo json_encode($j[$k] ?? 'MISSING', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
