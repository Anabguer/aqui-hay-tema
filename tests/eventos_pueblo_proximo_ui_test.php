<?php
declare(strict_types=1);

// B3 — Próximo evento del pueblo en estado/UI.

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$service = new PartidaService($root);

const A = 'ana';
const B = 'bruno';
const C = 'carla';
const D = 'david';

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 11, 'dia_semana_inicio' => 1],
        'rng' => ['seed' => 'b3-proximo', 'state' => 1],
        'meta' => ['seed' => 'b3-proximo', 'partida_id' => 'test_b3'],
        'features' => ['eventos_pueblo_enabled' => true, 'buzon_enabled' => true],
        'residentes' => [
            A => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            B => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
            C => ['identidad_publica' => ['nombre' => 'Carla'], 'presencia' => 'residente', 'runtime' => []],
            D => ['identidad_publica' => ['nombre' => 'David'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria', 'lug_bingo'],
            'intervenciones_organizadas_usadas_hoy' => 0,
            'intervenciones_organizadas_max_dia' => 1,
        ],
        'encuentros' => [],
        'buzon' => [],
        'canales_publicados' => [],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'propuestas_encuentro' => [],
    ];
}

function findSeedOk(): int
{
    global $cal, $catalog;
    for ($st = 1; $st <= 5000; $st++) {
        $p = labPartida();
        $p['rng']['state'] = $st;
        $r = EventosPuebloEngine::planificar($p, 'noche_bingo', $cal, RngService::fromPartida($p), $catalog);
        if (str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado')) {
            return $st;
        }
    }

    return 0;
}

$stOk = findSeedOk();
ok($stOk > 0, '1 seed programa bingo');

$p = labPartida();
$p['rng']['state'] = $stOk;
EventosPuebloEngine::planificar($p, 'noche_bingo', $cal, RngService::fromPartida($p), $catalog);

$vista = EventosPuebloEngine::vistaProximoEvento($p, $catalog);
ok($vista !== null, '2 vistaProximoEvento devuelve fila');
ok(($vista['catalogo_id'] ?? '') === 'noche_bingo', '2 catalogo_id noche_bingo');
ok(($vista['nombre_ui'] ?? '') !== '', '2 nombre_ui no vacio');
ok(stripos((string) ($vista['nombre_ui'] ?? ''), 'bingo') !== false, '2 nombre_ui menciona bingo');
ok(($vista['meta_ui'] ?? '') !== '', '2 meta_ui no vacia');
ok((int) ($vista['participantes_n'] ?? 0) >= 3, '2 participantes_n real');
ok(!empty($vista['es_evento_pueblo']), '2 marca es_evento_pueblo');
ok(($vista['icono'] ?? '') !== '', '2 icono presente');

$est = $service->estadoResumido($p);
ok(isset($est['proximo_evento_pueblo']), '3 estadoResumido expone proximo_evento_pueblo');
ok(($est['proximo_evento_pueblo']['id'] ?? '') === ($vista['id'] ?? ''), '3 estado coherente con vista');

$pVac = labPartida();
$estVac = $service->estadoResumido($pVac);
ok(!isset($estVac['proximo_evento_pueblo']), '4 sin evento no expone clave');

$enc = null;
foreach (EncuentroEngine::list($p) as $e) {
    if (($e['intencion'] ?? '') === 'evento_pueblo') {
        $enc = $e;
    }
}
if ($enc !== null) {
    $diaFin = (int) ($enc['dia'] ?? 5);
    $horaFin = (int) ($enc['hora'] ?? 18) + max(1, (int) ($enc['duracion_horas'] ?? 2));
    while ($horaFin >= 24) {
        $horaFin -= 24;
        $diaFin++;
    }
    $p['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin, 'dia_semana_inicio' => 1];
    EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
    $post = EventosPuebloEngine::vistaProximoEvento($p, $catalog);
    ok($post === null, '5 evento terminado deja de ser proximo');
    $estPost = $service->estadoResumido($p);
    ok(!isset($estPost['proximo_evento_pueblo']), '5 estado sin proximo tras terminar');
}

echo $fail === 0 ? "\nOK eventos_pueblo_proximo_ui_test\n" : "\nFAIL eventos_pueblo_proximo_ui_test ($fail)\n";
exit($fail > 0 ? 1 : 0);
