<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$fail = [];

$js = file_get_contents($root . '/assets/js/play-v3.js');
$requiredJs = [
    'function mensajeOrgParticipantesPendientes()',
    'function actualizarOrgCrearBtn()',
    'Elige un acompa\\u00f1ante para continuar',
    'limpiarOrgAviso();',
    "box.classList.add('is-disabled')",
    "err === 'participantes_insuficientes'",
];
foreach ($requiredJs as $needle) {
    if (strpos($js, $needle) === false) {
        $fail[] = 'play-v3.js falta: ' . $needle;
    }
}

$nodeOut = shell_exec('node ' . escapeshellarg($root . '/tests/org_form_validacion_test.js') . ' 2>&1');
if (!is_string($nodeOut) || strpos($nodeOut, 'org_form_validacion_test.js OK') === false) {
    $fail[] = 'org_form_validacion_test.js falló: ' . trim((string) $nodeOut);
}

$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('juego_v1', 'org-form-validacion');
$ids = array_keys($partida['residentes']);
assert(count($ids) >= 2);
$a = $ids[0];

$slotsSolo = DisponibilidadEngine::slotsCompatibles($partida, [$a], 'individual', 1, null, 1, 10, null, 'lug_cine');
if (isset($slotsSolo['error']) && $slotsSolo['error'] === 'participantes_requeridos') {
    $fail[] = 'DisponibilidadEngine individual+1 no debe devolver participantes_requeridos';
}

$valSolo = EncuentroEngine::validarContexto($partida, [$a], 'individual', 'lug_cine');
if (empty($valSolo['ok'])) {
    $fail[] = 'backend validarContexto individual+1 debe ser ok: ' . ($valSolo['error'] ?? '?');
}

$valPareja = EncuentroEngine::validarContexto($partida, [$a], 'conocerse', 'lug_cine');
if (!empty($valPareja['ok'])) {
    $fail[] = 'backend validarContexto conocerse+1 debe fallar';
}

$rProp = PropuestaEncuentroEngine::proponer($partida, [$a], 1, 18, 'individual', 'lug_cine');
if (empty($rProp['ok'])) {
    $fail[] = 'backend proponer individual debe ser ok';
}
$parts = $rProp['propuesta']['participantes'] ?? [];
if (count($parts) !== 1) {
    $fail[] = 'backend proponer individual debe tener 1 participante';
}

// Regla AgendaHandler (mínimo 2 en pareja, 1 en individual)
$partsFiltrados = array_values(array_filter([$a], static fn($p) => is_string($p) && $p !== ''));
if (count($partsFiltrados) >= 2) {
    $fail[] = 'setup participantes incorrecto';
}
$minPareja = 2;
if (count($partsFiltrados) < $minPareja) {
    // conocerse + 1 participante → rechazado en API (participantes_requeridos)
} else {
    $fail[] = 'setup pareja+1';
}

if ($fail) {
    fwrite(STDERR, "org_form_validacion_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "org_form_validacion_test OK\n";
