<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\DiversityAnalyzer;
use AquiHayTema\Engine\JsonFile;
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

$informe = DiversityAnalyzer::desdeDirectorio($root . '/data/personajes', $store, 0.55);
ok($informe['personajes'] >= 2, 'analiza fichas reales (Rocío + Dani + QA)');
ok(isset($informe['pares']), 'devuelve pares');

$clonA = JsonFile::read($root . '/data/personajes/per_qa_valid.json');
$clonB = $clonA;
$clonB['id'] = 'per_qacl01';
$clonB['identidad']['nombre'] = 'QA Clone';
$aviso = DiversityAnalyzer::analizarFichas([$clonA, $clonB], $store, 0.55);
ok(count($aviso['avisos']) >= 1, 'clones sintéticos generan aviso (no bloqueo)');
ok(($aviso['avisos'][0]['similitud'] ?? 0) >= 0.55, 'similitud alta en clones');

$base = $store->read('aficiones.json');
for ($i = 1; $i <= 80; $i++) {
    $base['items'][] = [
        'id' => 'hob_syn_' . $i,
        'nombre' => 'Sintético ' . $i,
        'especificidad' => 'alta',
        '_dev_only' => true,
    ];
}
$store->hydrate('aficiones.json', $base);
ok($store->accepts('hobbies', 'hob_syn_80'), 'catálogo grande: 80 hobbies extra sin tocar PHP');
ok($store->accepts('hobbies', 'bingo'), 'bingo sigue presente');

$ficha = [
    'id' => 'per_syn01',
    'slot' => 'I01',
    'identidad' => ['nombre' => 'Syn', 'genero' => 'mujer', 'edad' => 30],
    'vida' => [
        'ocupacion' => 'autonomo',
        'hobby_principal' => 'hob_syn_80',
        'estilo_social' => 'tranquilo',
        'rasgos_publicos' => ['directo', 'leal', 'empatico'],
    ],
];
$errsOk = PersonajeValidator::validar($ficha, 'per_syn01.json', [], $store);
if ($errsOk !== []) {
    echo json_encode($errsOk, JSON_UNESCAPED_UNICODE) . "\n";
}
ok($errsOk === [], 'ficha con hobby nuevo del catálogo valida');

$fichaBad = $ficha;
$fichaBad['vida']['hobby_principal'] = 'hob_no_existe';
$errs = PersonajeValidator::validar($fichaBad, 'per_syn01.json', [], $store);
ok(count($errs) > 0, 'hobby desconocido se rechaza');

exit($failures > 0 ? 1 : 0);
