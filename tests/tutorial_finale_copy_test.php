<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialPrimerosPasos;

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'finale_copy_' . time());
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$v = TutorialPrimerosPasos::vistaPublica($p);
$fin = $v['finale'] ?? [];
ok(($fin['tit'] ?? '') === 'Bueno. Ya sabes lo básico.', 'titulo finale acordado');
ok(str_contains((string) ($fin['txt'] ?? ''), 'Ya sabes mirar, cotillear y organizar planes'), 'texto finale acordado');
ok(str_contains((string) ($fin['txt'] ?? ''), 'he oído que pronto llegan nuevos vecinos'), 'finale menciona nuevos vecinos');
ok(str_contains((string) ($fin['txt'] ?? ''), 'Suerte. La vas a necesitar'), 'cierre finale acordado');
ok(($fin['boton'] ?? '') === 'Que empiece el tema', 'boton finale');

exit($failures > 0 ? 1 : 0);
