<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\LlegadaPresentacionEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialIncorporaciones;
use AquiHayTema\Engine\TutorialPrimerosPasos;

$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'llegada-pres-' . time());
TutorialPrimerosPasos::marcarFinaleVisto($p);
$p['tutorial']['jugable_completado'] = true;
$p['llegadas']['modo'] = 'normal';
$p['llegadas']['cooldown_hasta_dia'] = 0;
CandidatoLlegadaEngine::activarModoNormal($p, $root);

$perfil = LlegadaPresentacionEngine::perfilCandidato($root, 'per_p010');
ok(($perfil['ok'] ?? false) === true, 'perfil candidato ok');
ok(($perfil['nombre'] ?? '') !== '', 'perfil tiene nombre');

$activos = TutorialIncorporaciones::residentesActivos($p);
ok(count($activos) >= 1, 'ids para flujo');

$p['llegadas']['candidato_activo'] = null;
$p['llegadas']['en_camino'] = null;
$of = null;
for ($i = 0; $i < 48; $i++) {
    $of = CandidatoLlegadaEngine::intentarOfrecer($p, $root, null, 24);
    if ($of !== null) {
        break;
    }
    CandidatoLlegadaEngine::avanzarMinutosReloj($p, 120);
}
ok($of !== null, 'candidato ofrecido');
$msgId = (string) ($of['mensaje_id'] ?? '');
$msg = BuzonEngine::buscar($p, $msgId);
ok(is_array($msg['perfil_candidato'] ?? null), 'mensaje con perfil_candidato');
ok(count($msg['acompanantes_opciones'] ?? []) >= 1, 'mensaje con acompanantes_opciones');
$acompanante = (string) (($msg['acompanantes_opciones'][0]['personaje_id'] ?? '') ?: ($activos[0] ?? ''));

$rSin = CandidatoLlegadaEngine::aceptar($p, $root, $msgId);
ok(($rSin['error'] ?? '') === 'falta_acompanante', 'aceptar sin acompanante falla');
ok(($p['llegadas']['en_camino'] ?? null) === null, 'sin en_camino sin acompanante');

$r = CandidatoLlegadaEngine::aceptar($p, $root, $msgId, null, $acompanante);
ok($r['ok'] ?? false, 'aceptar con acompanante ok');
ok(($p['llegadas']['en_camino']['acompanante_id'] ?? '') === $acompanante, 'acompanante en en_camino');

$min = (int) ($p['llegadas']['en_camino']['espera_minutos'] ?? 1);
CandidatoLlegadaEngine::avanzarMinutosReloj($p, $min + 1);
$tick = CandidatoLlegadaEngine::tick($p, $root, null, 1);
ok(count($tick['llegadas_completadas'] ?? []) >= 1, 'llegada completada tras espera');

$nuevoId = (string) ($tick['llegadas_completadas'][0]['catalog_id'] ?? ($of['catalog_id'] ?? ''));
ok(isset($p['residentes'][$nuevoId]), 'nuevo residente activo');
ok(isset($p['llegadas']['presentacion']['ultima']['residente_id']), 'celebracion registrada');

$cotLlegada = 0;
foreach ($p['buzon'] ?? [] as $m) {
    if (is_array($m) && ($m['tipo'] ?? '') === 'llegada_pueblo') {
        $cotLlegada++;
    }
}
ok($cotLlegada >= 1, 'cotilleo llegada_pueblo');

$encBien = null;
foreach (EncuentroEngine::list($p) as $enc) {
    if (($enc['intencion'] ?? '') === 'bienvenida_llegada') {
        $encBien = $enc;
        break;
    }
}
ok($encBien !== null, 'encuentro franja bienvenida');
if (is_array($encBien)) {
    $parts = $encBien['participantes'] ?? [];
    ok(in_array($nuevoId, $parts, true) && in_array($acompanante, $parts, true), 'bienvenida incluye ambos');
}

// 16 → 15 → 16 (presentación no rompe cap)
while (count(TutorialIncorporaciones::residentesActivos($p)) < 16) {
    $rFill = $svc->crearResidentePlaceholderDev($p);
    if (!($rFill['ok'] ?? false)) {
        break;
    }
}
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 16, 'relleno hasta 16');
$ridMarcha = TutorialIncorporaciones::residentesActivos($p)[0];
$p['residentes'][$ridMarcha]['presencia'] = 'antiguo_residente';
CapacidadViviendas::liberarResidente($p, $ridMarcha);
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 15, 'marcha manual deja 15');
$rLlegada = $svc->crearResidentePlaceholderDev($p);
ok(($rLlegada['ok'] ?? false) === true, 'refill tras vacante');
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 16, 'vuelve a 16');

exit($fail > 0 ? 1 : 0);
