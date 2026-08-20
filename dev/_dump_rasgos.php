<?php
$j = json_decode(file_get_contents(__DIR__ . '/../data/catalogos/rasgos.json'), true);
foreach (($j['items'] ?? []) as $i) {
    echo ($i['id'] ?? '?') . ' | ' . ($i['etiqueta'] ?? '') . "\n";
}
