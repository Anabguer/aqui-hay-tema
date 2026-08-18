<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CatalogAudit;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\ContractEnums;
use AquiHayTema\Engine\PersonajeValidator;

$root = dirname(__DIR__);
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

ok(in_array('bingo', $audit['hobbies']['solo_en_json'], true), 'bingo está en catálogo JSON, no en contrato V0');
ok(in_array('sociable_selectiva', $audit['estilos_sociales']['solo_en_json'], true), 'sociable_selectiva en JSON, no en contrato');
ok(in_array('observadora', $audit['rasgos']['solo_en_json'], true), 'observadora en JSON, no en contrato');

ok($audit['rocio']['hobby_principal'] === 'bingo', 'Rocío conserva hobby bingo');
ok($audit['rocio']['en_catalogo_json']['hobby'] === true, 'bingo válido en JSON');
ok($audit['rocio']['en_contrato_v0']['hobby'] === false, 'bingo no está en contrato V0 (validador T4)');

$errores = PersonajeValidator::validarArchivo("{$root}/data/personajes/per_i03.json", []);
ok(count($errores) > 0, 'validador V0 sigue rechazando Rocío (ficha no modificada)');

$store = new CatalogStore($root);
ok(in_array('bingo', $store->ids('hobbies'), true), 'CatalogStore lee bingo');

$combo = CatalogAudit::combinatoria();
ok($combo['rasgos_triples_v0'] === 120, 'C(10,3)=120');
ok($combo['personas_por_hobby_si_100_v0'] > 8, '12 hobbies clonan en pool 100');

ok(!in_array('bingo', ContractEnums::HOBBY, true), 'ContractEnums V0 intacto');

exit($failures > 0 ? 1 : 0);
