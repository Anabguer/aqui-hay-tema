<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\ComplejoCatalog;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

ok(ComplejoCatalog::horarioUi('lug_biblioteca') === '10:00–20:00', 'biblioteca horario');
ok(ComplejoCatalog::horarioUi('lug_discoteca') === '22:00–04:00', 'discoteca horario nocturno');
ok(str_contains(ComplejoCatalog::horarioUi('lug_restaurante'), '·'), 'restaurante doble franja');
ok(!ComplejoCatalog::estaAbierto('lug_discoteca', 19), 'discoteca cerrada a las 19');
ok(ComplejoCatalog::estaAbierto('lug_biblioteca', 19) === ComplejoCatalog::horaEnFranja(19, 10, 20), 'biblioteca 19 coherente');

exit($failures > 0 ? 1 : 0);
