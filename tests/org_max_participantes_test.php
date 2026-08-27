<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\RngService;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$fail = [];

$js = file_get_contents($root . '/assets/js/play-v3.js');
if (strpos($js, 'const ORG_MAX_VECINOS = 2;') === false) {
    $fail[] = 'play-v3.js debe tener ORG_MAX_VECINOS = 2';
}
if (strpos($js, 'Puedes organizar planes con hasta') === false) {
    $fail[] = 'play-v3.js falta feedback de límite canónico';
}

$nodeOut = shell_exec('node ' . escapeshellarg($root . '/tests/org_form_validacion_test.js') . ' 2>&1');
if (!is_string($nodeOut) || strpos($nodeOut, 'org_form_validacion_test.js OK') === false) {
    $fail[] = 'org_form_validacion_test.js falló: ' . trim((string) $nodeOut);
}

$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('juego_v1', 'org-max-participantes');
$ids = array_keys($partida['residentes']);
if (count($ids) < 3) {
    $fail[] = 'partida de prueba necesita al menos 3 residentes';
}
[$a, $b, $c] = [$ids[0], $ids[1], $ids[2]];

$r1 = PropuestaEncuentroEngine::proponer($partida, [$a], 1, 18, 'individual', 'lug_cine');
if (empty($r1['ok'])) {
    $fail[] = 'proponer 1 participante debe ser ok';
}

$r2 = PropuestaEncuentroEngine::proponer($partida, [$a, $b], 1, 19, 'conocerse', 'lug_cine');
if (empty($r2['ok'])) {
    $fail[] = 'proponer 2 participantes debe ser ok: ' . ($r2['error'] ?? '?');
}

$r3 = PropuestaEncuentroEngine::proponer($partida, [$a, $b, $c], 1, 20, 'conocerse', 'lug_cine');
if (!empty($r3['ok'])) {
    $fail[] = 'proponer 3 participantes debe fallar';
}
if (($r3['error'] ?? '') !== 'PARTICIPANTES_EXCESO') {
    $fail[] = 'proponer 3 debe devolver PARTICIPANTES_EXCESO, got: ' . ($r3['error'] ?? '?');
}
if (strpos((string) ($r3['mensaje_ui'] ?? ''), 'hasta 2 vecinos') === false) {
    $fail[] = 'proponer 3 debe incluir mensaje_ui de límite';
}

// Evento del Pueblo: 3+ participantes siguen válidos (motor propio, no PropuestaEncuentroEngine)
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$partidaEvt = [
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 11],
    'rng' => ['seed' => 'org-max-evt', 'state' => 1],
    'meta' => ['seed' => 'org-max-evt'],
    'features' => ['eventos_pueblo_enabled' => true],
    'residentes' => [
        $a => ['identidad_publica' => ['nombre' => 'A'], 'presencia' => 'residente', 'runtime' => []],
        $b => ['identidad_publica' => ['nombre' => 'B'], 'presencia' => 'residente', 'runtime' => []],
        $c => ['identidad_publica' => ['nombre' => 'C'], 'presencia' => 'residente', 'runtime' => []],
    ],
    'celeste' => [
        'lugares_desbloqueados' => ['lug_cafeteria', 'lug_bingo'],
        'intervenciones_organizadas_usadas_hoy' => 0,
        'intervenciones_organizadas_max_dia' => 1,
    ],
    'encuentros' => [],
];
$encEvt = null;
for ($st = 1; $st <= 5000; $st++) {
    $pTry = $partidaEvt;
    $pTry['rng']['state'] = $st;
    $rEvt = EventosPuebloEngine::planificar($pTry, 'noche_bingo', $cal, RngService::fromPartida($pTry), $catalog);
    if (!str_starts_with((string) ($rEvt['resultado'] ?? ''), 'evento_programado')) {
        continue;
    }
    foreach ($pTry['encuentros'] ?? [] as $enc) {
        if (($enc['intencion'] ?? '') === 'evento_pueblo') {
            $encEvt = $enc;
            break 2;
        }
    }
}
if ($encEvt === null) {
    $fail[] = 'no se pudo programar evento_pueblo de regresión';
} elseif (count($encEvt['participantes'] ?? []) < 3) {
    $fail[] = 'evento_pueblo debe permitir 3+ participantes';
}

if ($fail) {
    fwrite(STDERR, "org_max_participantes_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "org_max_participantes_test OK\n";
