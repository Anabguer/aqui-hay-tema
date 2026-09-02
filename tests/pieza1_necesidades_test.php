<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CatalogStore;

$root = dirname(__DIR__);
$c = new Catalog($root);
$store = $c->store();

// Test catalog loads
$necesidades = $store->items('necesidades');
echo "Necesidades en catálogo: " . count($necesidades) . "\n";
foreach ($necesidades as $n) {
    echo "  - {$n['id']}: {$n['nombre']} {$n['icono']}\n";
}

// Test places load
$lugares = $c->loadLugares();
echo "\nLugares en catálogo: " . count($lugares['items']) . "\n";
$canonicos = 0;
foreach ($lugares['items'] as $l) {
    $nec = $l['necesidades'] ?? null;
    $esCanonico = $nec !== null;
    if ($esCanonico) $canonicos++;
    echo "  - {$l['id']}: " . ($nec ? json_encode($nec) : 'sin necesidades') . "\n";
}
echo "\nLugares canónicos con necesidades: {$canonicos}\n";
echo "\nOK: Pieza 1 completada\n";
