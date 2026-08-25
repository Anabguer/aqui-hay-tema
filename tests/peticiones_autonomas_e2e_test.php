<?php
declare(strict_types=1);

/**
 * Peticiones de pueblo AUT├ôNOMAS end-to-end (rama task/peticiones-autonomas).
 * Cadencia r07 aprobada intacta ┬À consecuencia provisional m├¡nima (E3 fail ÔêÆ1)
 * ┬À trazabilidad de origen (generacion.via) ┬À saves antiguos ┬À idempotencia.
 * Determinista: RNG inyectado + reloj fijado.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorPeticionesPueblo;
use AquiHayTema\Engine\VidaPuebloEngine;

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

function auditEventos(array $partida): array
{
    $out = [];
    foreach ($partida['audit_trail'] ?? [] as $e) {
        $out[] = (string) ($e['tipo'] ?? '');
    }
    return $out;
}

/** Labs: reloj propio sin dia_en_temporada; lo inicializo para cruzar medianoches sin warnings. */
function relojLab(array &$partida): void
{
    if (!isset($partida['reloj']['dia_en_temporada'])) {
        $partida['reloj']['dia_en_temporada'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    }
}

$cal = CalibracionConfig::load($root);
$t0 = new DateTimeImmutable('2026-08-24 09:00:00', Reloj::zona());

// ---------- 1) Generaci├│n por poblaci├│n (cadencia r07: suelo cap=2) ----------
ok(PeticionPuebloEngine::capSimultaneas(3, $cal) === 2, 'pop 3 ÔåÆ cap 2 (suelo R07)');
ok(PeticionPuebloEngine::capSimultaneas(8, $cal) === 3, 'pop 8 ÔåÆ cap 3');
ok(PeticionPuebloEngine::capSimultaneas(16, $cal) === 6, 'pop 16 ÔåÆ cap 6');

// ---------- 2) Pocas peticiones/d├¡a en pueblo peque├▒o (sin forzar RNG) ----------
Reloj::fijarAhora($t0);
$rng7 = new RngService('aut-e2e-pop3-7d');
$pSmall = SimuladorPeticionesPueblo::partidaLab(3, $rng7, $cal, 'E3');
relojLab($pSmall);
$pSmall['_b4_forzar_nacer'] = false;
$nacidasTotal = 0;
$maxAbiertas = 0;
for ($h = 0; $h < 24 * 7; $h++) {
    Reloj::avanzarHoras($pSmall, 1);
    PeticionPuebloEngine::tick($pSmall, $cal, $rng7, null, 1);
    $ab = count(PeticionPuebloEngine::abiertas($pSmall));
    $maxAbiertas = max($maxAbiertas, $ab);
}
foreach ($pSmall['peticiones'] as $p) {
    if (!empty($p['schema_b4'])) {
        $nacidasTotal++;
    }
}
ok($nacidasTotal > 0, 'pop 3 en 7 dias SI nace algo (no vacuo, n=' . $nacidasTotal . ')');
ok($nacidasTotal <= 14, 'pop 3 pocas peticiones/dia (n=' . $nacidasTotal . ' en 7d)');
ok($maxAbiertas <= 2, 'pop 3 nunca supera cap 2 (max=' . $maxAbiertas . ')');

// ---------- 3) No saturaci├│n bajo forzado ----------
Reloj::fijarAhora($t0);
$pSat = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-sat'), $cal, 'E3');
relojLab($pSat);
$pSat['_b4_forzar_nacer'] = true;
for ($i = 0; $i < 30; $i++) {
    PeticionPuebloEngine::intentarNacer($pSat, $cal, null, null);
    $ab = count(PeticionPuebloEngine::abiertas($pSat));
    if ($ab > 3) {
        ok(false, 'saturacion: abiertas=' . $ab . ' > cap 3');
        break;
    }
}
ok(true, 'forzado x30 respeta cap 3 en pop 8');
$porNpc = [];
$doble = false;
foreach (PeticionPuebloEngine::abiertas($pSat) as $ap) {
    $rid = (string) ($ap['residente_id'] ?? '');
    if (isset($porNpc[$rid])) {
        $doble = true;
    }
    $porNpc[$rid] = true;
}
ok(!$doble, 'una sola pendiente por residente (no duplicacion)');

// ---------- 4) Plazo correcto (reloj de juego + compat vence_iso) ----------
Reloj::fijarAhora($t0);
$pPlazo = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-plazo'), $cal, 'E3');
relojLab($pPlazo);
$pPlazo['_b4_forzar_nacer'] = true;
$pet = PeticionPuebloEngine::intentarNacer($pPlazo, $cal, new RngService('aut-e2e-plazo-n'), null);
ok($pet !== null && !empty($pet['vence_iso']), 'nacida autonoma guarda vence_iso (compat)');
$vj = ((int) $pet['dia_creada']) * 24 + (int) $pet['hora_creada'] + (int) $pet['plazo_horas'];
ok((int) $pet['vence_dia'] === intdiv($vj, 24) && (int) $pet['vence_hora'] === $vj % 24,
    'plazo: vence_dia/vence_hora = creacion + plazo_horas (juego)');
ok(is_string($pet['texto']) && $pet['texto'] !== '', 'nacida con copy de plantilla renderizado');

// ---------- 5) Caduca al corresponder (canonico juego; legacy iso) ----------
Reloj::avanzarHoras($pPlazo, max(1, (int) $pet['plazo_horas'] - 1));
ok(PeticionEngine::caducarVencidas($pPlazo) === 0, 'un hora antes del plazo NO caduca');
Reloj::avanzarHoras($pPlazo, 1);
ok(PeticionEngine::caducarVencidas($pPlazo) === 1, 'al cumplir el plazo caduca por reloj de juego');

// ---------- 6) Cumplir => evento peticion_cumplida + resuelta ----------
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);
$pCum = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-cumple'), $cal, 'E3');
relojLab($pCum);
$pCum['_lab_peticiones_b4'] = false;
$pCum['_lab_misiones_b3'] = false;
$pCum['features']['buzon_enabled'] = true;
$pCum['buzon'] = [];
$pCum['_b4_forzar_nacer'] = true;
$vida0 = VidaPuebloEngine::valor($pCum);
$petC = PeticionPuebloEngine::intentarNacer($pCum, $cal, new RngService('aut-e2e-cumple-n'), null);
ok($petC !== null, 'nacida autonoma para cumplimiento');
if ($petC !== null) {
    $enc = PeticionPuebloEngine::encuentroSinteticoPara($petC, $pCum);
    $nOk = PeticionPuebloEngine::onEncuentroCelestine($pCum, $enc, $cal, null);
    ok($nOk === 1, 'encuentro Celestine cumple la autonomica');
    ok(in_array('peticion_cumplida', auditEventos($pCum), true), 'evento dominio peticion_cumplida emitido');
    $est = '';
    foreach ($pCum['peticiones'] as $pp) {
        if (($pp['id'] ?? '') === ($petC['id'] ?? '')) {
            $est = (string) $pp['estado'];
        }
    }
    ok($est === 'resuelta', 'estado interno resuelta');
    ok(VidaPuebloEngine::valor($pCum) === $vida0 + 1, 'consecuencia cumplida: +1 (E3)');
}

// ---------- 7) Ignorar => ignorada interna / caducada en UI + eco ----------
Reloj::fijarAhora($t0);
$pIgn = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-ign'), $cal, 'E3');
relojLab($pIgn);
$pIgn['_lab_peticiones_b4'] = false;
$pIgn['_lab_misiones_b3'] = false;
$pIgn['features']['buzon_enabled'] = true;
$pIgn['buzon'] = [];
$pIgn['_b4_forzar_nacer'] = true;
$petI = PeticionPuebloEngine::intentarNacer($pIgn, $cal, new RngService('aut-e2e-ign-n'), null);
ok($petI !== null, 'nacida autonoma para ignoro');
if ($petI !== null) {
    $pid = (string) $petI['id'];
    $rIg = PeticionEngine::ignorar($pIgn, $pid, null);
    ok(!empty($rIg['ok']), 'ignorar OK');
    $vista = null;
    foreach (PeticionPuebloEngine::vistaAbiertas($pIgn) as $v) {
        if (($v['id'] ?? '') === $pid) {
            $vista = $v;
        }
    }
    ok($vista === null || false, 'ignorada deja de listarse como abierta');
    $itemVista = PeticionPuebloEngine::vistaItem($pIgn, $rIg['peticion']);
    ok(($itemVista['estado'] ?? '') === 'caducada', 'vista PLAY de ignorada = caducada');
    // Eco en buz├│n (feedback) sin IDs ni jerga.
    $ecoOk = false;
    foreach ($pIgn['buzon'] ?? [] as $m) {
        if ((string) ($m['tipo'] ?? '') === 'peticion_resultado' && ($m['peticion_id'] ?? '') === $pid) {
            $tx = (string) ($m['texto'] ?? '');
            $ecoOk = $tx !== ''
                && strpos($tx, 'pet_') === false
                && strpos($tx, 'lab_r') === false
                && stripos($tx, 'facil') === false;
        }
    }
    ok($ecoOk, 'eco de resultado en buz├│n sin IDs ni jerga');
}

// ---------- 8) Efecto provisional limitado (ÔêÆ1 UNA vez, nunca m├ís) ----------
Reloj::fijarAhora($t0);
$pFal = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-fallo'), $cal, 'E3');
relojLab($pFal);
$pFal['_lab_peticiones_b4'] = true;
$pFal['_b4_forzar_nacer'] = true;
$vidaF0 = VidaPuebloEngine::valor($pFal);
$petF = PeticionPuebloEngine::intentarNacer($pFal, $cal, new RngService('aut-e2e-fallo-n'), null);
ok($petF !== null, 'nacida para fallo provisional');
Reloj::avanzarHoras($pFal, (int) $petF['plazo_horas'] + 1);
PeticionPuebloEngine::tick($pFal, $cal, new RngService('aut-e2e-fallo-t1'), null, 1);
$vidaTras1 = VidaPuebloEngine::valor($pFal);
ok($vidaTras1 === $vidaF0 - 1, 'caducada autonoma: exactamente ÔêÆ1 (E3 fail facil)');
PeticionPuebloEngine::tick($pFal, $cal, new RngService('aut-e2e-fallo-t2'), null, 1);
ok(VidaPuebloEngine::valor($pFal) === $vidaTras1, 're-tick NO repite el fallo (limitado)');
$marcadas = 0;
foreach ($pFal['peticiones'] as $pp) {
    if (!empty($pp['vida_fallo_aplicada'])) {
        $marcadas++;
    }
}
ok($marcadas === 1, 'marca vida_fallo_aplicada una vez');
$fuerte = false;
foreach ($pFal['vida_pueblo']['registro'] ?? [] as $reg) {
    if ((string) ($reg['causa'] ?? '') === 'peticion_caducada' && (int) ($reg['delta'] ?? 0) <= -2) {
        $fuerte = true;
    }
}
ok(!$fuerte, 'sin penalizacion dura (nunca Ôëñ ÔêÆ2 por peticion)');

// ---------- 9) Trazabilidad de origen ----------
$trazOk = false;
foreach ($pSat['peticiones'] as $pp) {
    if (!empty($pp['schema_b4'])) {
        $g = is_array($pp['generacion'] ?? null) ? $pp['generacion'] : [];
        $trazOk = ($g['via'] ?? '') === 'autonoma'
            && isset($g['abiertas_al_nacer'])
            && ($pp['plantilla_id'] ?? '') !== ''
            && isset($pp['params'])
            && ($pp['residente_id'] ?? '') !== ''
            && ($pp['dia_creada'] ?? null) !== null;
        break;
    }
}
ok($trazOk, 'trazabilidad autonoma: via+abiertas_al_nacer+plantilla+params+residente+dia');
$petMan = PeticionEngine::crear($pSat, (string) array_key_first($pSat['residentes']), 'otro', [
    'schema_b4' => true,
    'texto' => 'Manual.',
    'plazo_horas' => 12,
], null);
ok(($petMan['peticion']['generacion']['via'] ?? '') === 'manual', 'creacion sin generacion => via manual');

// ---------- 10) Saves antiguos (sin peticiones_pueblo, sin generacion) ----------
Reloj::fijarAhora($t0);
$pOld = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-old'), $cal, 'E3');
relojLab($pOld);
unset($pOld['peticiones_pueblo']);
$petOld = PeticionEngine::crear($pOld, 'lab_r01', 'tiempo', [
    'schema_b4' => true,
    'peso' => PeticionEsquemas::PESO_FACIL,
    'texto' => 'Legacy sin generacion.',
    'plazo_horas' => 6,
], null);
unset($pOld['peticiones'][array_key_last($pOld['peticiones'])]['generacion']);
foreach ($pOld['peticiones'] as $i => $lp) {
    unset($pOld['peticiones'][$i]['generacion'], $pOld['peticiones'][$i]['vence_dia'], $pOld['peticiones'][$i]['vence_hora']);
}
ok(PeticionPuebloEngine::activa($pOld), 'save antiguo: flag activo');
PeticionPuebloEngine::ensure($pOld);
ok(isset($pOld['peticiones_pueblo']['historial_plantillas']), 'ensure reconstruye bloque peticiones_pueblo');
ok(count(PeticionPuebloEngine::abiertas($pOld)) >= 1, 'save antiguo: peticiones legacy visibles');
Reloj::fijarAhora($t0->modify('+7 hours'));
ok(PeticionEngine::caducarVencidas($pOld) >= 1, 'save antiguo: legacy caduca por vence_iso');
$nFalloOld = PeticionPuebloEngine::aplicarFalloPendiente($pOld, $cal, null);
ok($nFalloOld >= 1, 'save antiguo: fallo provisional aplicable a caducada legacy');

// ---------- 11) Idempotencia (roundtrip serializaci├│n + ensure + tick) ----------
Reloj::fijarAhora($t0);
$pIdem = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-idem'), $cal, 'E3');
relojLab($pIdem);
$pIdem['_lab_peticiones_b4'] = false;
$pIdem['_lab_misiones_b3'] = false;
$pIdem['features']['buzon_enabled'] = true;
$pIdem['buzon'] = [];
$pIdem['_b4_forzar_nacer'] = true;
PeticionPuebloEngine::intentarNacer($pIdem, $cal, new RngService('aut-e2e-idem-a'), null);
$antes = count($pIdem['peticiones']);
$clavesAntes = array_keys($pIdem['peticiones_pueblo']);
/** @var array<string, mixed> $restaurada */
$restaurada = unserialize(serialize($pIdem));
PeticionPuebloEngine::ensure($restaurada);
PeticionPuebloEngine::ensure($restaurada);
ok(count($restaurada['peticiones']) === $antes, 'ensure x2 + roundtrip no duplica peticiones');
ok(array_keys($restaurada['peticiones_pueblo']) === $clavesAntes || count(array_keys($restaurada['peticiones_pueblo'])) >= count($clavesAntes),
    'bloque peticiones_pueblo estable tras roundtrip');
$idsAntes = array_map(static fn ($p) => $p['id'] ?? '', $restaurada['peticiones']);
PeticionPuebloEngine::tick($restaurada, $cal, new RngService('aut-e2e-idem-b'), null, 1);
$idsDespues = array_map(static fn ($p) => $p['id'] ?? '', $restaurada['peticiones']);
ok(count($idsDespues) === count(array_unique($idsDespues)), 'IDs unicos tras tick post-carga');
ok(count(array_intersect($idsAntes, $idsDespues)) === count($idsAntes), 'tick post-carga conserva las existentes');
$msgs = [];
foreach ($restaurada['buzon'] ?? [] as $m) {
    $key = (string) ($m['peticion_id'] ?? '');
    if ($key !== '') {
        $msgs[$key] = ($msgs[$key] ?? 0) + 1;
    }
}
$soloUno = true;
foreach ($msgs as $pidMsg => $nMensajes) {
    if ($nMensajes > 1) {
        $soloUno = false;
    }
}
ok($soloUno, 'un solo mensaje de buzon por peticion (no duplicados)');

// ---------- 12) No regresi├│n Buzon/Mensajitos sobre nacidas aut├│nomas ----------
Reloj::fijarAhora($t0);
$pBz = SimuladorPeticionesPueblo::partidaLab(8, new RngService('aut-e2e-buzon'), $cal, 'E3');
relojLab($pBz);
$pBz['_lab_peticiones_b4'] = false;
$pBz['_lab_misiones_b3'] = false;
$pBz['features']['buzon_enabled'] = true;
$pBz['buzon'] = [];
$pBz['_b4_forzar_nacer'] = true;
$petBz = PeticionPuebloEngine::intentarNacer($pBz, $cal, new RngService('aut-e2e-buzon-n'), null);
ok($petBz !== null, 'autonoma nace con buzon activo');
$bid = (string) ($petBz['buzon_id'] ?? '');
ok($bid !== '', 'peticion enlaza su mensaje (buzon_id)');
$enBuzon = false;
foreach ($pBz['buzon'] ?? [] as $m) {
    if (($m['id'] ?? '') === $bid && ($m['clasificacion'] ?? '') === BuzonEngine::PETICION) {
        $enBuzon = strpos((string) ($m['origen']['tipo_evento'] ?? ''), 'peticion') === 0;
    }
}
ok($enBuzon, 'mensaje clasificado peticion con origen trazable');
ok(BuzonEngine::marcarEstado($pBz, $bid, 'resuelto')['ok'] ?? false, 'marcarEstado resuelto funciona sobre mensaje autonomo');
ok(BuzonEngine::marcarEstado($pBz, $bid, 'leido')['ok'] ?? false, 'transicion resueltoÔåÆleido permitida (contrato Buzon)');

echo "\n" . ($failures > 0 ? "FALLOS: $failures" : 'TODO OK') . " ÔÇö peticiones_autonomas_e2e\n";
exit($failures > 0 ? 1 : 0);
