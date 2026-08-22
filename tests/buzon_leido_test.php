<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialPrimerosPasos;

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'buzon_leido_' . time());
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

BuzonEngine::crear($p, [
    'id' => 'msg_test_a',
    'texto' => 'Primero',
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
]);
BuzonEngine::crear($p, [
    'id' => 'msg_test_b',
    'texto' => 'Segundo',
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
]);
BuzonEngine::crear($p, [
    'id' => 'msg_espera_test',
    'texto' => 'En espera no cuenta',
    'estado' => 'en_espera',
    'clasificacion' => BuzonEngine::IMPORTANTE,
]);
BuzonEngine::crear($p, [
    'id' => 'msg_cotilleo_test',
    'texto' => 'Cotilleo no cuenta',
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::COTILLEO,
    'canal' => BuzonEngine::CANAL_COTILLEO,
]);

ok(BuzonEngine::contarNoLeidos($p) === 2, 'badge inicial 2 no leídos');

$r = BuzonEngine::marcarLeido($p, 'msg_test_a');
ok($r['ok'] ?? false, 'marcar leido ok');
ok(!empty($r['mensaje']['leido_en']), 'leido_en persistido');
ok(BuzonEngine::contarNoLeidos($p) === 1, 'badge 2→1');

BuzonEngine::marcarLeido($p, 'msg_test_b');
ok(BuzonEngine::contarNoLeidos($p) === 0, 'badge 1→0');

// Tutorial mensajito: completar M1 crea uno pendiente
$pareja = $p['tutorial']['pareja_mision1'] ?? [];
$a = (string) ($pareja['a'] ?? '');
$b = (string) ($pareja['b'] ?? '');
\AquiHayTema\Engine\PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, 18, \AquiHayTema\Engine\PropuestaNivel::PRESENTAR, 'lug_cafeteria');
$mid = (string) ($p['tutorial']['mensajito_id'] ?? '');
ok($mid !== '', 'mensajito tutorial creado');
$pendTras = BuzonEngine::listar($p, 'pendiente', null, BuzonEngine::CANAL_BUZON);
$idsPend = array_map(static fn($m) => (string) ($m['id'] ?? ''), $pendTras);
ok(in_array($mid, $idsPend, true), 'mensajito tutorial pendiente tras M1');
ok(BuzonEngine::contarNoLeidos($p) >= 1, 'badge cuenta no leídos tras M1');

$visibles = BuzonEngine::listar($p, null, null, BuzonEngine::CANAL_BUZON);
$idsVisibles = array_map(static fn($m) => (string) ($m['id'] ?? ''), $visibles);
ok(in_array($mid, $idsVisibles, true), 'mensajito tutorial visible en listado buzon');

exit($failures > 0 ? 1 : 0);
