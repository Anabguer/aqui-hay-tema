<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PlaytestGuia;
use AquiHayTema\Engine\RelojOperations;

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

$service = new PartidaService($root);
$p = $service->nuevaPartida('playtest_01', 'guia-neni-1');
ok(PlaytestGuia::activa($p), 'guía activa en playtest_01');
$v = PlaytestGuia::vista($p, $root);
ok(!empty($v['activo']), 'vista activa');
ok(strpos((string) ($v['titulo'] ?? ''), 'DÍA') !== false, 'título con día');
ok(count($v['ahora_mismo'] ?? []) >= 3, 'bloque ahora mismo');
ok(count($v['que_hacer_ahora'] ?? []) >= 2, 'qué hacer ahora');
ok(count($v['objetivos'] ?? []) === 9, '9 objetivos');
$hechos0 = array_filter($v['objetivos'], static function ($o) {
    return !empty($o['hecho']);
});
ok(count($hechos0) === 0 || count($hechos0) <= 2, 'partida nueva: casi ningún objetivo marcado');

$est = $service->estadoResumido($p);
ok(isset($est['playtest_guia']['titulo']), 'estado expone playtest_guia');

$antes = PlaytestGuia::snapshot($p, $root);
$reloj = new RelojOperations($root, $service->getLogger(), $service->emociones());
$r = $reloj->avanzarPasoAPaso($p, 1);
ok(($r['ok'] ?? true) !== false, 'avanzar 1h');
ok(isset($r['playtest_guia_evento']['titulo']), 'evento humano tras +1h');
ok(isset($r['playtest_guia_evento']['lineas']) && is_array($r['playtest_guia_evento']['lineas']), 'líneas humanas');
$ev = $r['playtest_guia_evento'];
$txt = implode(' ', $ev['lineas']);
ok(
    strpos($txt, 'No ha pasado nada importante') !== false
    || strpos($txt, 'ha salido') !== false
    || strpos($txt, 'coincid') !== false
    || strpos($txt, 'mensaje') !== false
    || strpos($txt, 'casa') !== false,
    'resumen legible (algo o nada importante)'
);
ok(strpos(json_encode($ev), 'score') === false, 'sin scores en el evento');
ok(isset($r['resumen_avance']), 'debug técnico sigue disponible');

$p2 = $service->nuevaPartida('juego_v1', 'guia-no');
ok(!PlaytestGuia::activa($p2), 'juego_v1 no activa la guía de playtest');

echo $failures === 0 ? "OK playtest_guia\n" : "FAIL playtest_guia ({$failures})\n";
exit($failures > 0 ? 1 : 0);
