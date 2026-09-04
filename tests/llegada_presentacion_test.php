<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EncuentroResolver;
use AquiHayTema\Engine\LlegadaPresentacionEngine;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
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

// ──────────────────────────────────────────────────────
// §24.1 — Acompañar al nuevo vecino: agenda, resolución real, idempotencia
// ──────────────────────────────────────────────────────

// --- Test: Agenda bloquea a ambos participantes durante la franja ---
$encBien = null;
foreach (EncuentroEngine::list($p) as $enc) {
    if (($enc['intencion'] ?? '') === 'bienvenida_llegada'
        && in_array($nuevoId, $enc['participantes'] ?? [], true)
        && ($enc['estado'] ?? '') === 'en_curso'
    ) {
        $encBien = $enc;
        break;
    }
}
ok($encBien !== null, '§24.1 encore bienvenida activo para test agenda');
if (is_array($encBien)) {
    $diaEnc = (int) ($encBien['dia'] ?? 1);
    $horaEnc = (int) ($encBien['hora'] ?? 0);
    $parts = $encBien['participantes'] ?? [];
    $p1 = (string) ($parts[0] ?? '');
    $p2 = (string) ($parts[1] ?? '');

    $dispP1 = AgendaEngine::estaDisponible($p, $p1, $diaEnc, $horaEnc);
    ok(($dispP1['disponible'] ?? true) === false, '§24.1 agenda bloquea participante 1 durante bienvenida');
    $dispP2 = AgendaEngine::estaDisponible($p, $p2, $diaEnc, $horaEnc);
    ok(($dispP2['disponible'] ?? true) === false, '§24.1 agenda bloquea participante 2 durante bienvenida');

    $dispInt = AgendaEngine::estaDisponibleIntervalo($p, $p1, $diaEnc, $horaEnc, LlegadaPresentacionEngine::FRANJA_HORAS);
    ok(($dispInt['disponible'] ?? true) === false, '§24.1 intervalo bienvenida bloqueado para participante 1');

    $otros = array_diff(TutorialIncorporaciones::residentesActivos($p), [$p1, $p2]);
    if ($otros !== []) {
        $otro = (string) reset($otros);
        $dispOtro = AgendaEngine::estaDisponible($p, $otro, $diaEnc, $horaEnc);
        ok(($dispOtro['disponible'] ?? false) === true, '§24.1 tercero libre durante bienvenida');
    }

    // --- Test: Slots marcados como ocupados (antes de resolver) ---
    $slotsOcupados = 0;
    for ($h = $horaEnc; $h < $horaEnc + LlegadaPresentacionEngine::FRANJA_HORAS; $h++) {
        $disp = AgendaEngine::estaDisponible($p, $p1, $diaEnc, $h);
        if (!($disp['disponible'] ?? true)) {
            $slotsOcupados++;
        }
    }
    ok($slotsOcupados >= 1, '§24.1 agenda marca slots ocupados durante bienvenida');
}

// --- Test: Resolución usa pipeline real (no placeholder) ---
if (is_array($encBien)) {
    $encBienRef = null;
    foreach ($p['encuentros'] as &$enc) {
        if (($enc['id'] ?? '') === ($encBien['id'] ?? '___none')) {
            $encBienRef = &$enc;
            break;
        }
    }
    if (is_array($encBienRef)) {
        $encBienRef['dia'] = (int) ($p['reloj']['dia_pueblo'] ?? 1);
        $encBienRef['hora'] = max(0, (int) ($p['reloj']['hora_actual'] ?? 0) - 2);
        $encBienRef['duracion_horas'] = 1;
        $catalog = new \AquiHayTema\Engine\Catalog($root);
        EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);

        $resuelveReal = false;
        foreach ($p['encuentros'] as $enc) {
            if (($enc['id'] ?? '') === ($encBien['id'] ?? '') && ($enc['estado'] ?? '') === 'terminado') {
                $resultado = $enc['resultado'] ?? [];
                if (!empty($resultado['_deltas_reales']) || empty($resultado['_placeholder'])) {
                    $resuelveReal = true;
                }
                break;
            }
        }
        ok($resuelveReal, '§24.1 resolución bienvenida usa pipeline real (no placeholder)');
    }
}

// --- Test: Contacto registrado tras resolución real ---
if (is_array($encBien)) {
    $parts = $encBien['participantes'] ?? [];
    if (count($parts) >= 2) {
        $a = (string) $parts[0];
        $b = (string) $parts[1];
        $socialAB = RelacionEngine::socialHacia($p, $a, $b);
        ok(is_array($socialAB), '§24.1 contacto registrado A→B tras resolución');
        $socialBA = RelacionEngine::socialHacia($p, $b, $a);
        ok(is_array($socialBA), '§24.1 contacto registrado B→A tras resolución');
    }
}

// --- Test: Memoria registrada tras resolución ---
if (is_array($encBien)) {
    $memEventos = false;
    foreach ($p['memoria_eventos'] ?? [] as $evt) {
        if (($evt['familia'] ?? '') === 'encuentro'
            && is_array($evt['participantes'] ?? null)
            && in_array($nuevoId, $evt['participantes'], true)
        ) {
            $memEventos = true;
            break;
        }
    }
    ok($memEventos, '§24.1 memoria registró evento de encuentro bienvenida');
}

// --- Test: Idempotencia — segunda llamada NO duplica encuentro ---
if (is_array($encBien)) {
    $countAntes = count(EncuentroEngine::list($p));
    LlegadaPresentacionEngine::alLlegadaEfectiva($p, $nuevoId, $root, $enCamino ?? null, null);
    $countDespues = count(EncuentroEngine::list($p));
    ok($countDespues === $countAntes, '§24.1 idempotencia: no duplica bienvenida');

    // Solo 1 encuentro total con intencion bienvenida_llegada para este residente
    $totalBien = 0;
    foreach (EncuentroEngine::list($p) as $enc) {
        if (($enc['intencion'] ?? '') === 'bienvenida_llegada'
            && in_array($nuevoId, $enc['participantes'] ?? [], true)
        ) {
            $totalBien++;
        }
    }
    ok($totalBien === 1, '§24.1 solo 1 bienvenida total por residente');
}

echo "--- §24.1 tests completados ---\n";

exit($fail > 0 ? 1 : 0);
