<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CatalogAudit;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\ContentValidationException;
use AquiHayTema\Engine\JsonFile;
use AquiHayTema\Engine\LugarValidator;
use AquiHayTema\Engine\PersonajeValidator;

$root = dirname(__DIR__);
$store = new CatalogStore($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$audit = CatalogAudit::comparar($root);
ok($audit['fuente_verdad'] === 'data/catalogos/*.json', 'fuente de verdad JSON');
ok($audit['rocio']['hobby_en_catalogo'] === true, 'bingo en catálogo');
ok($audit['rocio']['estilo_en_catalogo'] === true, 'sociable_selectiva en catálogo');
ok(count($audit['rocio']['rasgos_en_catalogo']) === 3, 'tres rasgos de Rocío en catálogo');

$errores = PersonajeValidator::validarArchivo(
    "{$root}/data/personajes/per_i03.json",
    LugarValidator::extraerIds(JsonFile::read("{$root}/data/lugares/lugares.json")),
    $store
);
ok($errores === [], 'validador acepta Rocío contra catálogo');

$store2 = new CatalogStore($root);
ok(in_array('bingo', $store2->ids('hobbies'), true), 'CatalogStore lee bingo');

exit($failures > 0 ? 1 : 0);
