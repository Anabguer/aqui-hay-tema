<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\RegalosHandler;
use AquiHayTema\Engine\InventarioEngine;

$root = dirname(__DIR__);
$ctx = new ApiContext($root);
$partida = [
    'meta' => ['partida_id' => 'smoke_inv_listar'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
    'residentes' => [
        'r1' => ['identidad_publica' => ['nombre' => 'Ana']],
    ],
];
InventarioEngine::ensure($partida);
InventarioEngine::anadir($partida, 'libro', 1, new \AquiHayTema\Engine\CatalogStore($root));

$vac = RegalosHandler::inventario($ctx, [], $partida);
$hint = RegalosHandler::inventario($ctx, ['residente_id' => 'r1'], $partida);

$ok = ($vac['ok'] ?? false) === true
    && ($hint['ok'] ?? false) === true
    && is_array($vac['inventario'] ?? null)
    && count($vac['inventario']) === 1
    && ($vac['inventario'][0]['id'] ?? '') === 'libro';

echo ($ok ? 'OK' : 'FAIL') . ': inventario.listar smoke handler' . PHP_EOL;
exit($ok ? 0 : 1);
