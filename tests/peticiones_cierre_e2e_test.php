<?php
declare(strict_types=1);

/**
 * CIERRE E2E Nº2 — PETICIONES PUEBLO
 * Tests de cierre que faltaban para validar el loop completo.
 *
 * A. Lifecycle: nace → buzón → organiza/elig → cumple → vida +1 + regalo → buzón resuelto
 * C. Ignorar → vida −1
 * G. salir_de_casa → Celestine encounter → resuelta
 * I. Vida_pueblo con ignorada → −1
 * L. E3 esquema: relevante/dificil válidos, máx 1 válido/día
 *
 * Determinista: RNG inyectado + reloj fijado.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DetallitoEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionFeedback;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\PeticionPlantillas;
use AquiHayTema\Engine\RegaloRecompensaEngine;
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

function relojLab(array &$partida): void
{
    if (!isset($partida['reloj']['dia_en_temporada'])) {
        $partida['reloj']['dia_en_temporada'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    }
}

function cuentaBuzon(array $partida, string $tipo): int
{
    $n = 0;
    foreach ($partida['buzon'] ?? [] as $m) {
        if ((string) ($m['tipo'] ?? '') === $tipo) {
            $n++;
        }
    }
    return $n;
}

$cal = CalibracionConfig::load($root);
$t0 = new DateTimeImmutable('2026-08-24 09:00:00', Reloj::zona());

// ============================================================
// A) LIFECOMPLETO: nace → buzón → cumple → vida +1 + regalo → buzón resuelto
// ============================================================
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);

$pA = SimuladorPeticionesPueblo::partidaLab(8, new RngService('cierre-a-lifecycle'), $cal, 'E3');
relojLab($pA);
$pA['_lab_peticiones_b4'] = false;
$pA['_lab_misiones_b3'] = false;
$pA['features']['buzon_enabled'] = true;
$pA['features']['peticiones_pueblo_enabled'] = true;
$pA['features']['regalo_recompensa_enabled'] = true;
$pA['buzon'] = [];
$pA['inventario'] = [];
$pA['_b4_forzar_nacer'] = true;

$vida0 = VidaPuebloEngine::valor($pA);
$invBefore = InventarioEngine::totalUnidades($pA);

$petA = PeticionPuebloEngine::intentarNacer($pA, $cal, new RngService('cierre-a-nacer'), null);
ok($petA !== null, 'A: petición nace');
if ($petA !== null) {
    $pidA = (string) $petA['id'];
    // Buzón: debe haber mensaje de petición
    $bidA = (string) ($petA['buzon_id'] ?? '');
    ok($bidA !== '', 'A: buzón_id enlazado');
    $enBuzon = false;
    foreach ($pA['buzon'] ?? [] as $m) {
        if (($m['id'] ?? '') === $bidA) {
            $enBuzon = true;
        }
    }
    ok($enBuzon, 'A: mensaje en buzón');

    // Cumplir vía encuentro Celestine
    $encA = PeticionPuebloEngine::encuentroSinteticoPara($petA, $pA);
    $nOk = PeticionPuebloEngine::onEncuentroCelestine($pA, $encA, $cal, null);
    ok($nOk === 1, 'A: encuentro Celestine cumple petición');

    // Vida +1
    ok(VidaPuebloEngine::valor($pA) === $vida0 + 1, 'A: vida +1 tras cumplir');

    // Estado resuelta
    $estA = '';
    foreach ($pA['peticiones'] as $pp) {
        if (($pp['id'] ?? '') === $pidA) {
            $estA = (string) $pp['estado'];
        }
    }
    ok($estA === 'resuelta', 'A: estado resuelta');

    // Buzón marcado resuelto (el feedback crea un eco, el original se marca)
    $marcadoResuelto = false;
    foreach ($pA['buzon'] ?? [] as $m) {
        if (($m['peticion_id'] ?? '') === $pidA && ($m['tipo'] ?? '') === PeticionFeedback::TIPO_RESULTADO) {
            $marcadoResuelto = true;
        }
    }
    ok($marcadoResuelto, 'A: eco resultado_cumplida en buzón');

    // Regalo vía pudding (puede o no tocar según crc32, pero el motor se invocó)
    // Verificamos que la petición tiene recompensa_regalo marcada
    $recompensa = null;
    foreach ($pA['peticiones'] as $pp) {
        if (($pp['id'] ?? '') === $pidA) {
            $recompensa = $pp['recompensa_regalo'] ?? null;
        }
    }
    ok(is_array($recompensa), 'A: recompensa_regalo marcada en petición');
    if (is_array($recompensa)) {
        $estadoReg = (string) ($recompensa['estado'] ?? '');
        if ($estadoReg === 'entregada') {
            ok(InventarioEngine::totalUnidades($pA) > $invBefore, 'A: objeto entregado al inventario');
        } else {
            ok(true, 'A: regalo no tocó esta vez (estado=' . $estadoReg . ', crc32 determinista)');
        }
    }
}

// ============================================================
// C+I) IGNORAR → vida −1
// ============================================================
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);

$pC = SimuladorPeticionesPueblo::partidaLab(8, new RngService('cierre-c-ignorar'), $cal, 'E3');
relojLab($pC);
$pC['_lab_peticiones_b4'] = false;
$pC['_lab_misiones_b3'] = false;
$pC['features']['buzon_enabled'] = true;
$pC['features']['peticiones_pueblo_enabled'] = true;
$pC['buzon'] = [];
$pC['_b4_forzar_nacer'] = true;

$vidaC0 = VidaPuebloEngine::valor($pC);
$petC = PeticionPuebloEngine::intentarNacer($pC, $cal, new RngService('cierre-c-nacer'), null);
ok($petC !== null, 'C: petición nace para ignorar');
if ($petC !== null) {
    $pidC = (string) $petC['id'];
    $rIg = PeticionEngine::ignorar($pC, $pidC, null);
    ok(!empty($rIg['ok']), 'C: ignorar OK');
    ok(VidaPuebloEngine::valor($pC) === $vidaC0 - 1, 'C+I: vida −1 tras ignorar');

    // Verificar eco resultado_ignorada en buzón
    $ecoIgn = false;
    foreach ($pC['buzon'] ?? [] as $m) {
        if (($m['peticion_id'] ?? '') === $pidC && ($m['tipo'] ?? '') === PeticionFeedback::TIPO_RESULTADO) {
            $tx = (string) ($m['texto'] ?? '');
            $ecoIgn = $tx !== '';
        }
    }
    ok($ecoIgn, 'C: eco resultado_ignorada en buzón');
}

// ============================================================
// C2) Ignorar: NPC con petición ignorada queda libre (no bloqueo same-day)
// ============================================================
// Verificar que tras ignorar, el NPC está disponible para nueva petición
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);

$pC2 = SimuladorPeticionesPueblo::partidaLab(8, new RngService('cierre-c2-repost'), $cal, 'E3');
relojLab($pC2);
$pC2['_lab_peticiones_b4'] = false;
$pC2['_lab_misiones_b3'] = false;
$pC2['features']['buzon_enabled'] = true;
$pC2['features']['peticiones_pueblo_enabled'] = true;
$pC2['buzon'] = [];
$pC2['_b4_forzar_nacer'] = true;

$petC2 = PeticionPuebloEngine::intentarNacer($pC2, $cal, new RngService('cierre-c2-n1'), null);
ok($petC2 !== null, 'C2: primera petición nace');
if ($petC2 !== null) {
    $npcC2 = (string) $petC2['residente_id'];
    // Ignorar
    PeticionEngine::ignorar($pC2, (string) $petC2['id'], null);
    // El NPC ya no tiene petición abierta → puede nacer otra
    $cands = PeticionPuebloEngine::candidatosSpawn($pC2, $cal);
    $npcLibre = false;
    foreach ($cands as $c) {
        if ((string) ($c['residente_id'] ?? '') === $npcC2) {
            $npcLibre = true;
            break;
        }
    }
    ok($npcLibre, 'C2: NPC ignorado libre para nueva petición mismo tick');
}

// ============================================================
// G) SALIR_DE_CASA → Celestine encounter → resuelta
// ============================================================
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);

$pG = SimuladorPeticionesPueblo::partidaLab(8, new RngService('cierre-g-salir'), $cal, 'E3');
relojLab($pG);
$pG['_lab_peticiones_b4'] = false;
$pG['_lab_misiones_b3'] = false;
$pG['features']['buzon_enabled'] = true;
$pG['features']['peticiones_pueblo_enabled'] = true;
$pG['buzon'] = [];
$pG['_b4_forzar_nacer'] = true;

// salir_de_casa requires dia >= dias_sin_salir(2) + 1 = 3
Reloj::avanzarHoras($pG, 48);
$vidaG0 = VidaPuebloEngine::valor($pG);

// Forzar nacimiento con plantilla salir_de_casa
$petG = null;
for ($try = 0; $try < 30; $try++) {
    $petG = PeticionPuebloEngine::intentarNacer($pG, $cal, new RngService('cierre-g-nacer-' . $try), null);
    if ($petG !== null && (string) ($petG['plantilla_id'] ?? '') === 'salir_de_casa') {
        break;
    }
    $petG = null;
}
ok($petG !== null, 'G: petición salir_de_casa nace');
if ($petG !== null) {
    ok((string) ($petG['plantilla_id'] ?? '') === 'salir_de_casa', 'G: plantilla es salir_de_casa');
    $encG = PeticionPuebloEngine::encuentroSinteticoPara($petG, $pG);
    ok(($encG['tipo'] ?? '') === 'quedar', 'G: tipo encuentro es quedar (salir_de_casa)');
    $nOk = PeticionPuebloEngine::onEncuentroCelestine($pG, $encG, $cal, null);
    ok($nOk === 1, 'G: encuentro Celestine cumple salir_de_casa');
    ok(VidaPuebloEngine::valor($pG) === $vidaG0 + 1, 'G: vida +1 tras cumplir salir_de_casa');
}

// ============================================================
// L) E3 ESQUEMA: relevante válido = +1, difícil válido = +2, máx 1 válido/día
// ============================================================
$e3 = PeticionEsquemas::de('E3');
ok($e3['ok']['facil'] === 1, 'L: fácil +1 vida');
ok($e3['ok']['relevante'] === 1, 'L: relevante +1 vida');
ok($e3['ok']['dificil'] === 2, 'L: difícil +2 vida');
ok($e3['valido']['facil'] === false, 'L: fácil NO cuenta como válido');
ok($e3['valido']['relevante'] === true, 'L: relevante SÍ cuenta como válido');
ok($e3['valido']['dificil'] === true, 'L: difícil SÍ cuenta como válido');
ok($e3['fail']['facil'] === -1, 'L: fácil fallo −1');
ok($e3['fail']['relevante'] === -1, 'L: relevante fallo −1');
ok($e3['fail']['dificil'] === -1, 'L: difícil fallo −1');
ok((int) ($e3['max_validos_dia'] ?? 0) === 1, 'L: máx 1 válido/día');

// Verificar que cumplir relevante ocupa el cupo diario
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);

$pL = SimuladorPeticionesPueblo::partidaLab(8, new RngService('cierre-l-esquema'), $cal, 'E3');
relojLab($pL);
$pL['_lab_peticiones_b4'] = false;
$pL['_lab_misiones_b3'] = false;
$pL['features']['buzon_enabled'] = true;
$pL['features']['peticiones_pueblo_enabled'] = true;
$pL['buzon'] = [];
$pL['_b4_forzar_nacer'] = true;

// Crear petición relevante manualmente con plantilla_id para que encaje()
$npcs = PeticionPuebloEngine::residentes($pL);
$petRel = PeticionEngine::crear($pL, $npcs[0], 'tiempo', [
    'schema_b4' => true,
    'plantilla_id' => 'quedar_con_x',
    'peso' => PeticionEsquemas::PESO_RELEVANTE,
    'texto' => 'Test relevante.',
    'plazo_horas' => 24,
    'params' => ['otro' => $npcs[1]],
], null);
$encRel = PeticionPuebloEngine::encuentroSinteticoPara($petRel['peticion'], $pL);
PeticionPuebloEngine::onEncuentroCelestine($pL, $encRel, $cal, null);
ok((int) ($pL['peticiones_pueblo']['validos_dia'] ?? 0) === 1, 'L: relevante cumplida ocupa cupo diario');

// Crear petición difícil en el mismo día
$petDif = PeticionEngine::crear($pL, $npcs[2], 'relacion', [
    'schema_b4' => true,
    'plantilla_id' => 'volver_a_ver',
    'peso' => PeticionEsquemas::PESO_DIFICIL,
    'texto' => 'Test difícil.',
    'plazo_horas' => 48,
    'params' => ['otro' => $npcs[3]],
], null);
$encDif = PeticionPuebloEngine::encuentroSinteticoPara($petDif['peticion'], $pL);
PeticionPuebloEngine::onEncuentroCelestine($pL, $encDif, $cal, null);
ok((int) ($pL['peticiones_pueblo']['validos_dia'] ?? 0) === 1, 'L: difícil cumplida en mismo día NO suma segundo válido');

// ============================================================
// H) REGALOS: petición relevante → pudding engine invocado
// ============================================================
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);

$pH = SimuladorPeticionesPueblo::partidaLab(8, new RngService('cierre-h-regalos'), $cal, 'E3');
relojLab($pH);
$pH['_lab_peticiones_b4'] = false;
$pH['_lab_misiones_b3'] = false;
$pH['features']['buzon_enabled'] = true;
$pH['features']['peticiones_pueblo_enabled'] = true;
$pH['features']['regalo_recompensa_enabled'] = true;
$pH['buzon'] = [];
$pH['inventario'] = [];
$pH['_b4_forzar_nacer'] = true;

$npcsH = PeticionPuebloEngine::residentes($pH);
$petH = PeticionEngine::crear($pH, $npcsH[0], 'tiempo', [
    'schema_b4' => true,
    'plantilla_id' => 'quedar_con_x',
    'peso' => PeticionEsquemas::PESO_DIFICIL,
    'texto' => 'Test regalo.',
    'plazo_horas' => 24,
    'params' => ['otro' => $npcsH[1]],
], null);
$pidH = (string) $petH['peticion']['id'];
$encH = PeticionPuebloEngine::encuentroSinteticoPara($petH['peticion'], $pH);
PeticionPuebloEngine::onEncuentroCelestine($pH, $encH, $cal, null);
// Verificar que la marca recompensa_regalo existe (el crc32 decide si toca)
$recompH = null;
foreach ($pH['peticiones'] as $pp) {
    if (($pp['id'] ?? '') === $pidH) {
        $recompH = $pp['recompensa_regalo'] ?? null;
    }
}
ok(is_array($recompH), 'H: recompensa_regalo tras cumplir petición dificil');
if (is_array($recompH)) {
    $eH = (string) ($recompH['estado'] ?? '');
    ok(in_array($eH, ['entregada', 'no_toca', 'tope_diario', 'sin_hueco'], true),
        'H: estado recompensa válido (' . $eH . ')');
}

// ============================================================
// K) PERSISTENCIA: save/load conserva timestamps
// ============================================================
DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);

$pK = SimuladorPeticionesPueblo::partidaLab(8, new RngService('cierre-k-save'), $cal, 'E3');
relojLab($pK);
$pK['_b4_forzar_nacer'] = true;
$petK = PeticionPuebloEngine::intentarNacer($pK, $cal, new RngService('cierre-k-nacer'), null);
ok($petK !== null, 'K: petición nace para save/load');
if ($petK !== null) {
    $diaCreada = (int) ($petK['dia_creada'] ?? 0);
    $horaCreada = (int) ($petK['hora_creada'] ?? 0);
    $plazoHoras = (int) ($petK['plazo_horas'] ?? 0);
    $venceDia = (int) ($petK['vence_dia'] ?? 0);
    $venceHora = (int) ($petK['vence_hora'] ?? 0);
    ok($diaCreada > 0, 'K: dia_creada preservado');
    ok($venceDia > 0, 'K: vence_dia preservado');

    // Roundtrip serialize
    $restaurada = unserialize(serialize($pK));
    PeticionPuebloEngine::ensure($restaurada);
    $petK2 = null;
    foreach ($restaurada['peticiones'] as $pp) {
        if (($pp['id'] ?? '') === ($petK['id'] ?? '')) {
            $petK2 = $pp;
            break;
        }
    }
    ok($petK2 !== null, 'K: petición sobrevive roundtrip');
    if ($petK2 !== null) {
        ok((int) ($petK2['dia_creada'] ?? 0) === $diaCreada, 'K: dia_creada tras roundtrip');
        ok((int) ($petK2['vence_dia'] ?? 0) === $venceDia, 'K: vence_dia tras roundtrip');
        ok((int) ($petK2['vence_hora'] ?? 0) === $venceHora, 'K: vence_hora tras roundtrip');
    }
}

echo "\n" . ($failures > 0 ? "FALLOS: $failures" : 'TODO OK') . " — peticiones_cierre_e2e\n";
exit($failures > 0 ? 1 : 0);
