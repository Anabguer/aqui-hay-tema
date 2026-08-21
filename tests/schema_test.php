<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SchemaMigrator;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$v1 = [
    'meta' => ['schema_version' => 1, 'seed' => 'm1'],
    'citas' => [['id' => 'cita_abcd', 'participantes' => ['a', 'b'], 'dia' => 1, 'hora' => 10, 'estado' => 'programado']],
    'celeste' => ['encuentros_usados_hoy' => 1, 'encuentros_max_dia' => 5],
];
$m = SchemaMigrator::migrate($v1);
ok((int) $m['meta']['schema_version'] === 3, 'migra a v2');
ok(isset($m['encuentros'][0]) && !isset($m['citas']), 'citas -> encuentros');
ok($m['encuentros'][0]['id'] === 'enc_abcd', 'id migrado');
ok(isset($m['rng']['state']), 'rng inicializado');

$v2 = ['meta' => ['schema_version' => 2, 'seed' => 'm2'], 'residentes' => []];
$m2 = SchemaMigrator::migrate($v2);
ok((int) $m2['meta']['schema_version'] === 3, 'v2 no bump');
ok(isset($m2['propuestas_encuentro'], $m2['peticiones'], $m2['compatibilidad_oculta']), 'campos vida aditivos');

exit($failures > 0 ? 1 : 0);
