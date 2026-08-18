<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\EstiloSocial;
use AquiHayTema\Engine\JsonFile;
use AquiHayTema\Engine\LugarValidator;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PersonajeValidator;

$root = dirname(__DIR__);
$store = new CatalogStore($root);
$lugares = LugarValidator::extraerIds(JsonFile::read("{$root}/data/lugares/lugares.json"));
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$errs = PersonajeValidator::validarArchivo("{$root}/data/personajes/per_i03.json", $lugares, $store);
ok($errs === [], 'Rocío valida sin cambiar identidad');
if ($errs !== []) {
    echo json_encode($errs, JSON_UNESCAPED_UNICODE) . "\n";
}

$catalog = new Catalog($root);
$rocio = $catalog->loadPersonaje('per_i03');
ok($rocio['vida']['hobby_principal'] === 'bingo', 'hobby bingo intacto');
ok($rocio['vida']['estilo_social'] === 'sociable_selectiva', 'etiqueta estilo intacta');
ok($rocio['vida']['rasgos_publicos'] === ['observadora', 'practica', 'socarrona'], 'rasgos intactos');

$ejes = EstiloSocial::resolver($rocio['vida'], $store);
ok(($ejes['ejes']['selectividad'] ?? '') === 'selectiva', 'ejes derivados de etiqueta (ficha no reescrita)');
ok($ejes['fuente'] === 'catalogo_etiqueta', 'fuente ejes = catálogo, no ficha');

$service = new PartidaService($root);
$partida = $service->nuevaPartida('debug_v0', 'rocio-debug');
ok(isset($partida['residentes']['per_i03']), 'debug_v0 incorpora a Rocío');

ok($store->canonId('rasgos', 'observadora') === 'observador', 'alias observadora → observador');
ok($store->accepts('rasgos', 'observadora'), 'id ficha observadora sigue siendo válido');

exit($failures > 0 ? 1 : 0);
