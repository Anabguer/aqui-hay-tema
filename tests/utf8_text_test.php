<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Utf8Text;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$tree = ['a' => ['b' => "ok\xE9"]];
$paths = Utf8Text::rutasInvalidas($tree);
ok(count($paths) === 1, 'diagnóstico encuentra una ruta');
ok(($paths[0]['path'] ?? '') === 'a.b', 'ruta anidada correcta');
ok(isset($paths[0]['hex']), 'incluye hex');

ok(Utf8Text::paraJson('') === '', 'vacío estable');
ok(Utf8Text::paraJson('Árbol') === 'Árbol', 'UTF-8 nativo intacto');
ok(Utf8Text::isValid(Utf8Text::mayusculas(Utf8Text::primeraLetra('Álvaro'))), 'iniciales UTF-8 válidas');

echo $failures === 0 ? "utf8_text_test OK\n" : "utf8_text_test FAIL ($failures)\n";
exit($failures > 0 ? 1 : 0);
