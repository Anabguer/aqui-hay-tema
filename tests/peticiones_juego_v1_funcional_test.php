<?php
declare(strict_types=1);

/*
 * Prueba funcional B4 en juego_v1 (flag ON).
 * Avanza 7 dias SIEMPRE por el pipeline real paso a paso
 * (PartidaService::avanzarRelojPasoAPaso, misma ruta que api reloj.avanzar paso_a_paso).
 * Mide: nacimientos, plantillas, cap simultaneo, Mensajitos, mudanzas, trabajo,
 * rechazos, misiones, y valida la caducidad por RELOJ DE JUEGO (vence_dia/vence_hora)
 * con fallback legacy vence_iso.
 * No modifica calibracion ni balance.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionPlantillas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\TutorialPrimerosPasos;

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

function linea(string $s): void
{
    echo $s . "\n";
}

$t0 = new DateTimeImmutable(Reloj::TEST_AHORA, Reloj::zona());
Reloj::fijarAhora($t0);
DomainBootstrap::resetForTests();
DomainBootstrap::boot();

// ---------- 0. Plantillas requeridas presentes en el catalogo ----------
$idsCatalogo = array_map(static fn($pl) => (string) $pl['id'], PeticionPlantillas::catalogo());
$requeridas = ['conocer_a_alguien', 'quedar_con_x', 'volver_a_ver', 'primera_cita_pet', 'ir_al_lugar', 'algo_distinto', 'salir_de_casa'];
$faltan = array_diff($requeridas, $idsCatalogo);
ok($faltan === [], 'plantillas requeridas activas en catalogo');
$plazoPorPlantilla = [];
foreach (PeticionPlantillas::catalogo() as $pl) {
    $plazoPorPlantilla[(string) $pl['id']] = (int) ($pl['plazo_horas'] ?? 0);
}
linea('');
linea('== PLANTILLAS / PLAZO EN HORAS REALES ==');
foreach ($requeridas as $rid2) {
    linea(sprintf('  %-18s plazo=%2d h reales', $rid2, $plazoPorPlantilla[$rid2] ?? -1));
}
linea('');

// ---------- 1. Partida juego_v1 nueva ----------
$service = new PartidaService($root);
$p = $service->nuevaPartida('juego_v1', 'pet-func-v1', ['fecha' => '2026-08-17', 'hora' => 8]);
$cal = CalibracionConfig::load($root);

ok(($p['meta']['config_id'] ?? '') === 'juego_v1', 'partida juego_v1 creada');
ok(FeatureConfig::isEnabled($p, PeticionPuebloEngine::FLAG), 'flag peticiones_pueblo_enabled ON');
$nRes0 = count(PeticionPuebloEngine::residentes($p));
$cap0 = PeticionPuebloEngine::capSimultaneas($nRes0, $cal);
linea("poblacion inicial={$nRes0} cap inicial={$cap0}");

// ---------- 1b. Tutorial primeros pasos por la ruta real (desbloquea mudanzas dia 1) ----------
ok(completarTutorialJuegoV1($p, $root), 'tutorial completado: mudanzas dia 1 desbloqueadas');

// ---------- metricas ----------
$seenPets = [];
$births = [];
$maxSim = 0;
$violacionesCap = 0;
$dobleNpc = false;
$pobPorDia = [];
$cumplidasIds = [];
$progIntentos = 0;
$progOk = 0;
$rechazosProgramar = [];

function registrarEstado(array $p, array $cal, array &$births, array &$seenPets, int &$maxSim, int &$violacionesCap, bool &$dobleNpc): void
{
    foreach ($p['peticiones'] ?? [] as $pet) {
        if (empty($pet['schema_b4']) || isset($seenPets[$pet['id']])) {
            continue;
        }
        $seenPets[$pet['id']] = true;
        $births[] = [
            'id' => (string) $pet['id'],
            'dia' => (int) ($pet['dia_creada'] ?? 0),
            'hora' => (int) ($pet['hora_creada'] ?? 0),
            'plantilla' => (string) ($pet['plantilla_id'] ?? '?'),
            'residente' => (string) $pet['residente_id'],
            'texto' => (string) ($pet['texto'] ?? ''),
            'creada_iso' => (string) ($pet['creada_iso'] ?? ''),
            'vence_iso' => (string) ($pet['vence_iso'] ?? ''),
        ];
    }
    $abiertas = PeticionPuebloEngine::abiertas($p);
    $cap = PeticionPuebloEngine::capSimultaneas(count(PeticionPuebloEngine::residentes($p)), $cal);
    if (count($abiertas) > $maxSim) {
        $maxSim = count($abiertas);
    }
    if (count($abiertas) > $cap) {
        $violacionesCap++;
    }
    $porNpc = [];
    foreach ($abiertas as $ap) {
        $rid = (string) $ap['residente_id'];
        if (isset($porNpc[$rid])) {
            $dobleNpc = true;
        }
        $porNpc[$rid] = true;
    }
}

/**
 * Jugador ligero: programa por el pipeline real el encuentro que cumple una peticion abierta.
 */
function intentarCumplirUna(array &$p, PartidaService $service, array &$rechazosProgramar): bool
{
    $reloj = $p['reloj'];
    $diaHoy = (int) $reloj['dia_pueblo'];
    foreach (PeticionPuebloEngine::abiertas($p) as $pet) {
        $enc = PeticionPuebloEngine::encuentroSinteticoPara($pet, $p);
        $partes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        if (count($partes) < 2) {
            continue;
        }
        for ($dh = 1; $dh <= 30; $dh++) {
            $abs = ((int) $reloj['dia_pueblo']) * 24 + (int) $reloj['hora_actual'] + $dh;
            $d = intdiv($abs, 24);
            $h = $abs % 24;
            if ($h < 10 || $h > 21 || $d > $diaHoy + 1) {
                continue;
            }
            $r = $service->programarEncuentro($p, $partes, $d, $h, (string) $enc['tipo'], (string) $enc['lugar']);
            if ($r['ok'] ?? false) {
                return true;
            }
            $rechazosProgramar[] = [(string) $pet['plantilla_id'], (string) ($r['error'] ?? 'programar')];
        }
    }
    return false;
}

/**
 * Tutorial primeros pasos por su ruta real (proponer/alLeerMensaje): desbloquea mudanzas.
 */
function completarTutorialJuegoV1(array &$p, string $root): bool
{
    $catalogTut = new Catalog($root);
    $par = is_array($p['tutorial']['pareja_mision1'] ?? null) ? $p['tutorial']['pareja_mision1'] : [];
    $aTut = (string) ($par['a'] ?? '');
    $bTut = (string) ($par['b'] ?? '');
    $tercero = (string) ($p['tutorial']['tercero'] ?? '');
    $lugM3 = (string) ($p['tutorial']['lugar_m3'] ?? 'lug_cine');
    foreach ([12, 14, 16, 18, 20] as $hTut) {
        $rM1 = PropuestaEncuentroEngine::proponer($p, [$aTut, $bTut], 1, $hTut, 'conocerse', 'lug_cafeteria');
        if (!empty($rM1['ok'])) {
            break;
        }
    }
    TutorialPrimerosPasos::alLeerMensaje($p, (string) ($p['tutorial']['mensajito_id'] ?? ''), $catalogTut);
    foreach ([21, 19, 17, 15] as $hTut) {
        $rM3 = PropuestaEncuentroEngine::proponer($p, [$tercero], 1, $hTut, 'individual', $lugM3);
        if (!empty($rM3['ok'])) {
            break;
        }
    }
    return !empty($p['tutorial']['jugable_completado']);
}

// ---------- 2. 7 dias por el pipeline paso a paso ----------
linea('== AVANCE 7 DIAS (paso a paso, pipeline real) ==');
for ($d = 1; $d <= 7; $d++) {
    if ($d >= 2) {
        $progIntentos++;
        if (intentarCumplirUna($p, $service, $rechazosProgramar)) {
            $progOk++;
        }
    }
    $res = $service->avanzarRelojPasoAPaso($p, 24);
    ok(($res['ok'] ?? false) === true, "dia {$d}: avanzarRelojPasoAPaso(24) ok");
    registrarEstado($p, $cal, $births, $seenPets, $maxSim, $violacionesCap, $dobleNpc);
    $nRes = count(PeticionPuebloEngine::residentes($p));
    $cap = PeticionPuebloEngine::capSimultaneas($nRes, $cal);
    $abi = count(PeticionPuebloEngine::abiertas($p));
    $pobPorDia[$d] = $nRes;
    foreach (PeticionEngine::listar($p, 'resuelta') as $pr) {
        if (!empty($pr['schema_b4'])) {
            $cumplidasIds[(string) $pr['id']] = true;
        }
    }
    linea(sprintf(
        '  fin dia %d | pob=%d cap=%d abiertas=%d nacidas_acum=%d cumplidas=%d',
        $d,
        $nRes,
        $cap,
        $abi,
        count($births),
        count($cumplidasIds)
    ));
}
registrarEstado($p, $cal, $births, $seenPets, $maxSim, $violacionesCap, $dobleNpc);

// ---------- 3. Verificaciones ----------
linea('');
linea('== NACIMIENTOS ==');
$histo = [];
foreach ($births as $b) {
    $histo[$b['plantilla']] = ($histo[$b['plantilla']] ?? 0) + 1;
    linea(sprintf(
        '  D%d %02dh [%s] %s -> "%s"',
        $b['dia'],
        $b['hora'],
        $b['plantilla'],
        IdentidadPublica::nombre($p, $b['residente']),
        $b['texto']
    ));
}
linea('  histograma: ' . json_encode($histo, JSON_UNESCAPED_UNICODE));

ok(count($births) >= 1, 'nacen peticiones espontaneas (nacidas=' . count($births) . ')');
ok(count($histo) >= 2, 'variedad de plantillas segun estado/relaciones (tipos=' . count($histo) . ')');

// Mensajitos (buzon)
$birthDe = [];
foreach ($births as $b) {
    $birthDe[$b['id']] = $b['residente'];
}
$msgPetOk = 0;
$byClas = [];
foreach ($p['buzon'] ?? [] as $m) {
    $clas = (string) ($m['clasificacion'] ?? '');
    $byClas[$clas] = ($byClas[$clas] ?? 0) + 1;
    if ($clas !== BuzonEngine::PETICION || !isset($seenPets[(string) ($m['peticion_id'] ?? '')])) {
        continue;
    }
    $tx = (string) ($m['texto'] ?? '');
    // Contrato narrativo: el remitente va en de_persona (la UI lo pinta);
    // el texto es 1.ª persona del vecino, sin prefijo "Nombre:" ni jerga.
    $deOk = isset($birthDe[(string) ($m['peticion_id'] ?? '')])
        && (string) ($m['de_persona'] ?? '') === $birthDe[(string) ($m['peticion_id'] ?? '')];
    $nombreEnTx = (bool) preg_match('/^[A-ZÁÉÍÓÚÑ][^:]{1,24}: /u', $tx);
    if ($deOk && $tx !== '' && !$nombreEnTx && strpos($tx, 'pet_') === false) {
        $msgPetOk++;
    }
}
linea('  buzon por clasificacion: ' . json_encode($byClas, JSON_UNESCAPED_UNICODE));
ok($msgPetOk === count($births), 'todas las nacidas aparecen en Mensajitos (' . $msgPetOk . '/' . count($births) . ')');
$otrosMsgs = ($byClas[BuzonEngine::COTILLEO] ?? 0) + ($byClas[BuzonEngine::IMPORTANTE] ?? 0)
    + ($byClas[BuzonEngine::OPORTUNIDAD] ?? 0);
ok($otrosMsgs > 0, 'otros mensajes siguen vivos (cotilleo/importante/oportunidad=' . $otrosMsgs . ')');

// Cap simultaneo
$capFinal = PeticionPuebloEngine::capSimultaneas(count(PeticionPuebloEngine::residentes($p)), $cal);
ok($violacionesCap === 0, 'nunca supera el cap simultaneo (max=' . $maxSim . ', cap final=' . $capFinal . ')');
ok(!$dobleNpc, 'maximo una peticion abierta por residente');

// Mudanzas (llegadas tutorial V3)
$pobFinal = count(PeticionPuebloEngine::residentes($p));
ok($pobFinal === 8, 'mudanzas intactas: poblacion llega a 8 (' . $pobFinal . ')');
linea('  poblacion por dia: ' . json_encode($pobPorDia));

// Trabajo
$conTrabajo = 0;
foreach ($p['residentes'] as $r) {
    if (!empty($r['runtime']['ocupacion'])) {
        $conTrabajo++;
    }
}
ok($conTrabajo >= 1, 'trabajo intacto: residentes con ocupacion=' . $conTrabajo . '/' . $pobFinal);

// Misiones diarias siguen
$misionesTotales = count($p['misiones_diarias']['items'] ?? []);
ok($misionesTotales > 0, 'misiones diarias siguen generandose');

// Rechazos observados al programar (sistema vivo)
linea('  programar intentos=' . $progIntentos . ' ok=' . $progOk . ' rechazos_ctx=' . count($rechazosProgramar));
if ($rechazosProgramar !== []) {
    $motivos = [];
    foreach ($rechazosProgramar as $rp) {
        $k = (string) $rp[1];
        $motivos[$k] = ($motivos[$k] ?? 0) + 1;
    }
    linea('  motivos de rechazo: ' . json_encode($motivos));
}
ok($progOk >= 1, 'el jugador-ligero completo peticiones via puente real (' . $progOk . ')');
ok(count($cumplidasIds) >= 1, 'hay peticiones resueltas tras encuentros celebrados (' . count($cumplidasIds) . ')');

// Pendientes al final
$pendientes = PeticionPuebloEngine::vistaAbiertas($p);
linea('== ESTADO FINAL ==');
linea('  nacidas totales: ' . count($births));
linea('  cumplidas: ' . count($cumplidasIds));
linea('  maximo simultaneo alcanzado: ' . $maxSim . ' (cap final=' . $capFinal . ')');
linea('  pendientes al final: ' . count($pendientes));
foreach ($pendientes as $pd) {
    linea('   - [' . $pd['quien'] . '] "' . $pd['texto'] . '" | ' . $pd['plazo_humano']);
}

// ---------- 4. CADUCIDAD POR RELOJ DE JUEGO (vence_dia/vence_hora) ----------
// Estructura: crear persiste vence_dia/vence_hora (canonico) + vence_iso (compat/fallback).
// Prueba 1: salir_de_casa creado dia 2 · 10:00 con plazo 24h -> vence dia 3 · 10:00.
Reloj::fijarAhora($t0);
$p2 = $service->nuevaPartida('juego_v1', 'pet-func-v1-b', ['fecha' => '2026-08-17', 'hora' => 8]);
$p2['reloj']['dia_pueblo'] = 2;
$p2['reloj']['hora_actual'] = 10;
$idsRes = PeticionPuebloEngine::residentes($p2);
$rSDC = PeticionEngine::crear($p2, (string) $idsRes[0], 'tiempo', [
    'schema_b4' => true,
    'plantilla_id' => 'salir_de_casa',
    'peso' => 'facil',
    'texto' => 'Llevo dias sin salir. Sacame de casa.',
    'plazo_horas' => 24,
], null);
ok(!empty($rSDC['ok']), 'P1: salir_de_casa creado D2·10:00');
ok((int) $rSDC['peticion']['vence_dia'] === 3 && (int) $rSDC['peticion']['vence_hora'] === 10, 'P1: vence dia 3 · 10:00 (24h de juego)');
ok(!empty($rSDC['peticion']['vence_iso']), 'P1: vence_iso conservado como compat');

// Prueba 2 + 7: avanzar 23h por pipeline real paso a paso -> sigue pendiente.
$idSDC = (string) $rSDC['peticion']['id'];
$r23 = $service->avanzarRelojPasoAPaso($p2, 23);
ok(($r23['ok'] ?? false) === true, 'P7: reloj.avanzar paso_a_paso ok');
$estado23 = null;
foreach ($p2['peticiones'] as $lp) {
    if (($lp['id'] ?? '') === $idSDC) {
        $estado23 = (string) $lp['estado'];
    }
}
ok($estado23 === 'abierta', "P2: tras 23h de juego sigue pendiente (estado={$estado23})");

// Prueba 3: 1h mas -> caduca (cruza el vencimiento exacto).
$service->avanzarRelojPasoAPaso($p2, 1);
$estado24 = null;
foreach ($p2['peticiones'] as $lp) {
    if (($lp['id'] ?? '') === $idSDC) {
        $estado24 = (string) $lp['estado'];
    }
}
ok($estado24 === 'caducada', "P3: a las 24h de juego caduca (estado={$estado24})");

// Prueba 4 + ejemplo medianoche: algo_distinto D3·20:00 +12h -> vence D4·08:00.
// Se avanza en segundos reales (reloj real congelado): DEBE caducar por juego.
$p4 = $service->nuevaPartida('juego_v1', 'pet-func-v1-c', ['fecha' => '2026-08-17', 'hora' => 8]);
$p4['reloj']['dia_pueblo'] = 3;
$p4['reloj']['hora_actual'] = 20;
$idsRes4 = PeticionPuebloEngine::residentes($p4);
$rAD = PeticionEngine::crear($p4, (string) $idsRes4[0], 'actividad', [
    'schema_b4' => true,
    'plantilla_id' => 'algo_distinto',
    'peso' => 'facil',
    'texto' => 'Necesito hacer algo distinto.',
    'plazo_horas' => 12,
], null);
ok((int) $rAD['peticion']['vence_dia'] === 4 && (int) $rAD['peticion']['vence_hora'] === 8, 'P4: D3·20:00 +12h -> vence D4·08:00 (medianoche)');
$idAD = (string) $rAD['peticion']['id'];
$segReal0 = microtime(true);
$rMid = $service->avanzarRelojPasoAPaso($p4, 13);
$segRealTarda = microtime(true) - $segReal0;
$estadoMid = null;
foreach ($p4['peticiones'] as $lp) {
    if (($lp['id'] ?? '') === $idAD) {
        $estadoMid = (string) $lp['estado'];
    }
}
linea(sprintf('  13h de juego cruzando medianoche tardaron %.2f s reales', $segRealTarda));
ok(($rMid['ok'] ?? false) === true && $segRealTarda < 60, 'P4: dias de juego en pocos segundos reales');
ok($estadoMid === 'caducada', "P4: caduca POR RELOJ DE JUEGO aunque el real no avanzo (estado={$estadoMid})");

// Prueba 5: save legacy solo con vence_iso -> fallback por reloj real intacto.
$p5 = $service->nuevaPartida('juego_v1', 'pet-func-v1-d', ['fecha' => '2026-08-17', 'hora' => 8]);
$idsRes5 = PeticionPuebloEngine::residentes($p5);
$rLeg = PeticionEngine::crear($p5, (string) $idsRes5[0], 'tiempo', [
    'schema_b4' => true,
    'plantilla_id' => 'salir_de_casa',
    'peso' => 'facil',
    'texto' => 'Legacy.',
    'plazo_horas' => 24,
], null);
foreach ($p5['peticiones'] as $i => $lp) {
    if (($lp['id'] ?? '') === ($rLeg['peticion']['id'] ?? '')) {
        unset($p5['peticiones'][$i]['vence_dia'], $p5['peticiones'][$i]['vence_hora']);
    }
}
ok(PeticionEngine::caducarVencidas($p5) === 0, 'P5: legacy sin tocar aun no caduca');
Reloj::fijarAhora($t0->modify('+25 hours'));
ok(PeticionEngine::caducarVencidas($p5) === 1, 'P5: legacy cae a vence_iso y caduca por reloj real');
Reloj::fijarAhora($t0);

// Prueba 6: cap lleno -> sin cumplir nada -> caducan por juego -> huecos liberados -> nacen nuevas.
$p6 = $service->nuevaPartida('juego_v1', 'pet-func-v1-e', ['fecha' => '2026-08-17', 'hora' => 8]);
ok(completarTutorialJuegoV1($p6, $root), 'P6: tutorial completado');
$service->avanzarRelojPasoAPaso($p6, 12);
$idsRes6 = PeticionPuebloEngine::residentes($p6);
ok(count($idsRes6) >= 7, 'P6: poblacion >=7 para cap 3 (pob=' . count($idsRes6) . ')');
$p6['_b4_forzar_nacer'] = true;
$nacidasFase1 = 0;
for ($i = 0; $i < 15 && count(PeticionPuebloEngine::abiertas($p6)) < 3; $i++) {
    if (PeticionPuebloEngine::intentarNacer($p6, $cal, null, null) !== null) {
        $nacidasFase1++;
    }
}
ok(count(PeticionPuebloEngine::abiertas($p6)) === 3, 'P6: cap 3 lleno (' . $nacidasFase1 . ' nacidas)');
$p6['_b4_forzar_nacer'] = false;
$totalCadCap = 0;
$guard = 0;
while (count(PeticionPuebloEngine::abiertas($p6)) > 0 && $guard < 40) {
    $rAv = $service->avanzarRelojPasoAPaso($p6, 6);
    if (($rAv['ok'] ?? false) !== true) {
        break;
    }
    $totalCadCap += (int) ($rAv['peticiones_caducadas'] ?? 0);
    $guard++;
}
ok(count(PeticionPuebloEngine::abiertas($p6)) === 0, 'P6: todas caducan avanzando juego sin cumplir nada');
ok($totalCadCap >= 3, 'P6: caducadas reportadas por el pipeline=' . $totalCadCap);
$p6['_b4_forzar_nacer'] = false;
$nacidasTras = 0;
for ($i = 0; $i < 40 && $nacidasTras < 1; $i++) {
    $service->avanzarRelojPasoAPaso($p6, 1);
    if (PeticionPuebloEngine::intentarNacer($p6, $cal, null, null) !== null) {
        $nacidasTras++;
    }
}
ok($nacidasTras >= 1, 'P6: hueco liberado y nacen nuevas peticiones');
ok(count(PeticionPuebloEngine::abiertas($p6)) <= PeticionPuebloEngine::capSimultaneas(count(PeticionPuebloEngine::residentes($p6)), $cal), 'P6: cap respetado tras regeneracion');

// Compat: las nacidas del RUN A siguen llevando vence_iso con su plazo.
$muestraCompat = 0;
foreach ($births as $b) {
    if ($b['vence_iso'] !== '') {
        $muestraCompat++;
    }
}
ok($muestraCompat === count($births), 'compat: vence_iso presente en todas las nacidas (' . $muestraCompat . ')');

Reloj::fijarAhora(null);

linea('');
echo $failures === 0 ? "OK peticiones_juego_v1_funcional\n" : "FAIL peticiones_juego_v1_funcional ({$failures})\n";
exit($failures > 0 ? 1 : 0);
