<?php
declare(strict_types=1);

// FASE 2B (R2) · Declaración romántica autónoma.
//
// Cobertura:
//   1 flag OFF ⇒ marcador sigue siendo 'cita' (F2A intacta)
//   2 prematuras: citas_insuficientes / fuera_ventana / ultima_experiencia_mala
//   3 sin_reciprocidad y banda_baja
//   4 cooldowns: cooldown_hito (336h) y cooldown_propuesta (6h)
//   5 cap_hitos_dia (anti-cascada global)
//   6 marcador 'declaracion' con gap 48h y dispatch por procesarContinuidad
//   7 ACEPTACIÓN determinista (p=1): pareja formada + purga de marcadores + memoria
//   8 RECHAZO determinista (p=0): memoria completa, atribución correcta,
//     re-arma marcador de CITA, cooldown 336h posterior
//   9 diario: cuerpo 'Una declaración rechazada' con declara/rechaza CORRECTOS

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

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
DomainBootstrap::boot();

const DA = 'ana';
const DB = 'bruno';

function baseCal(): array
{
    $cal = CalibracionConfig::load(dirname(__DIR__));
    $cal['romance_autonomo']['declaracion_activa'] = true;
    $cal['romance_autonomo']['pareja_activa'] = true;
    return $cal;
}

function calForzada(array $voluntad): array
{
    $cal = baseCal();
    foreach ($voluntad as $k => $v) {
        $cal['voluntad'][$k] = $v;
    }
    return $cal;
}

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 20, 'hora_actual' => 12],
        'rng' => ['seed' => 'f2b', 'state' => 101],
        'meta' => ['seed' => 'f2b'],
        'residentes' => [
            DA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            DB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria', 'lug_parque']],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'encuentros' => [],
        'parentesco' => [],
        'continuidad_romantica' => [],
        'bitacora_relaciones' => [],
        'memoria_eventos' => [],
        'propuestas_cooldown' => [],
        'rechazos_propuesta' => [],
        'diario' => [],
    ];
}

function social30(array &$p, string $x, string $y): void
{
    for ($i = 0; $i < 3; $i++) {
        RelacionEngine::ajustarSocialHacia($p, $x, $y, 10);
    }
}

/** Señal mutua + hito PRIMERA_CITA + N citas resueltas con experiencia dada. */
function parDeclarable(array &$p, int $nCitas, string $expUltima, int $romAB = 28, int $romBA = 28): void
{
    social30($p, DA, DB);
    social30($p, DB, DA);
    RelacionEngine::setRomanceHacia($p, DA, DB, $romAB);
    RelacionEngine::setRomanceHacia($p, DB, DA, $romBA);
    // primera cita resuelta bien (día 18, termina 19h)
    $p['encuentros'][] = self_encuentroResuelto('enc_pc', 'primera_cita', 18, 17, 'bien');
    RelacionBitacora::registrar($p, RelacionBitacora::PRIMERA_CITA, [DA, DB]);
    // citas de continuidad resueltas
    for ($i = 0; $i < $nCitas - 1; $i++) {
        $dia = 19 + $i;
        $p['encuentros'][] = self_encuentroResuelto('enc_c' . $i, 'cita', $dia, 18, $expUltima);
    }
    MemoriaEventos::registrar($p, 'romance', [DA, DB], null, 'cita', 'bien');
    // reloj: 6h tras terminar la última cita (dentro de ventana 336h)
    $p['reloj']['dia_pueblo'] = 19 + max(0, $nCitas - 1) + 1;
    $p['reloj']['hora_actual'] = 0;
}

function self_encuentroResuelto(string $id, string $tipo, int $dia, int $hora, string $exp): array
{
    return [
        'id' => $id,
        'tipo' => $tipo,
        'intencion' => 'autonomo_npc',
        'participantes' => [DA, DB],
        'lugar' => 'lug_cafeteria',
        'dia' => $dia,
        'hora' => $hora,
        'duracion_minutos' => 60,
        'duracion_horas' => 1,
        'estado' => 'terminado',
        'resultado' => [
            '_deltas_reales' => true,
            'por_participante' => [
                DA => ['resultado' => $exp],
                DB => ['resultado' => $exp],
            ],
        ],
    ];
}

function setAbs(array &$p, int $abs): void
{
    $p['reloj']['dia_pueblo'] = intdiv($abs, 24);
    $p['reloj']['hora_actual'] = $abs % 24;
}

function absNow(array $p): int
{
    return ((int) $p['reloj']['dia_pueblo']) * 24 + (int) $p['reloj']['hora_actual'];
}

function markerDe(array $p): ?array
{
    foreach (($p['continuidad_romantica'] ?? []) as $m) {
        if (is_array($m) && in_array(DA, $m['par'], true) && in_array(DB, $m['par'], true)) {
            return $m;
        }
    }
    return null;
}

// ============ 1: flag OFF ⇒ comportamiento F2A intacto ============
$p = labPartida();
parDeclarable($p, 2, 'bien');
$calOff = CalibracionConfig::load($root);
IniciativaRomantica::registrarContinuidadPostCita($p, DA, DB, 'bien', $calOff);
$m = markerDe($p);
ok($m !== null && ($m['accion'] ?? 'cita') === 'cita', '1 flag OFF: marcador accion=cita (F2A)');
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $calOff);
ok(($el['motivo'] ?? '') === 'flag_off', '1 elegibilidad flag_off');

// ============ 2: prematuras ============
$p = labPartida();
parDeclarable($p, 1, 'bien'); // solo primera cita
$cal = baseCal();
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'citas_insuficientes', '2a citas_insuficientes (solo primera_cita)');

$p = labPartida();
parDeclarable($p, 2, 'bien');
setAbs($p, absNow($p) + 400); // > ventana 336h
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'fuera_ventana', '2b fuera_ventana');

$p = labPartida();
parDeclarable($p, 2, 'mal'); // última cita mala
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'ultima_experiencia_mala', '2c ultima_experiencia_mala');

// ============ 3: reciprocidad y banda ============
$p = labPartida();
parDeclarable($p, 2, 'bien', 28, 5); // B no llega a tilín
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'sin_reciprocidad', '3a sin_reciprocidad');

$p = labPartida();
parDeclarable($p, 2, 'bien', 15, 12); // señal mutua (≥8) pero < interes(22)
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'banda_baja', '3b banda_baja (< interes)');

$p = labPartida();
parDeclarable($p, 2, 'bien');
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['ok'] ?? false) === true && ($el['desde'] ?? '') !== '', '3c ELEGIBLE en condiciones canónicas');

// ============ 4: cooldowns ============
$p = labPartida();
parDeclarable($p, 2, 'bien');
MemoriaEventos::registrar($p, 'romance_hito', [DA, DB], null, 'declaracion_rechazada_previa');
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'cooldown_hito', '4a cooldown_hito (romance_hito 336h)');

$p = labPartida();
parDeclarable($p, 2, 'bien');
PropuestaCooldown::marcar($p, DA, DB, 'declaracion', $cal);
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'cooldown_propuesta', '4b cooldown_propuesta declaracion');

// ============ 5: cap anti-cascada ============
$p = labPartida();
parDeclarable($p, 2, 'bien');
RelacionBitacora::registrar($p, RelacionBitacora::INICIO_PAREJA, ['otro1', 'otro2']);
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'cap_hitos_dia', '5 cap_hitos_dia (max 1/día pueblo)');

// ============ 6: marcador declaracion + dispatch ============
$p = labPartida();
parDeclarable($p, 2, 'bien');
$st0 = absNow($p);
IniciativaRomantica::registrarContinuidadPostCita($p, DA, DB, 'bien', $cal);
$m = markerDe($p);
ok($m !== null && ($m['accion'] ?? '') === 'declaracion', '6a marcador accion=declaracion');
ok((int) ($m['desde_abs'] ?? 0) === $st0 + 48, '6b respiración temporal: intento a +48h');
ok(($m['declara'] ?? '') === DA, '6c declarante = mayor romance (A)');

// ============ 7: ACEPTACIÓN determinista (p≈1) ============
$calSi = calForzada(['p_min' => 1.0, 'p_max' => 1.0, 'score_excelente' => 101]);
$p = labPartida();
parDeclarable($p, 2, 'bien');
IniciativaRomantica::registrarContinuidadPostCita($p, DA, DB, 'bien', $calSi);
setAbs($p, absNow($p) + 48);
$res = IniciativaRomantica::procesarContinuidad($p, $calSi);
ok(count($res) === 1 && ($res[0]['resultado'] ?? '') === 'declaracion_aceptada', '7a declaracion_aceptada vía continuidad');
ok(ParejaEngine::estado($p, DA, DB) === ParejaEngine::PAREJA, '7b estado PAREJA');
$rel = RelacionEngine::obtenerEntre($p, DA, DB)['romance'];
ok((bool) ($rel['estabilidad_pareja']['activa'] ?? false), '7c estabilidad activa');
ok(count(RelacionBitacora::entre($p, DA, DB, RelacionBitacora::INICIO_PAREJA)) === 1, '7d hito INICIO_PAREJA único');
ok(markerDe($p) === null, '7e marcadores del par PURGADOS al formar');
$hayMemPareja = false;
foreach (($p['memoria_eventos'] ?? []) as $ev) {
    if (($ev['familia'] ?? '') === 'pareja') {
        $hayMemPareja = true;
    }
}
ok($hayMemPareja, '7f memoria familia pareja registrada');
$rSig = IniciativaRomantica::intentarSiguienteCita($p, DA, DB, $calSi);
ok(($rSig['resultado'] ?? '') === 'gate_ya_pareja_o_crisis', '7g continuidad F2A cortada por pareja');

// ============ 8: RECHAZO determinista (p≈0) + re-arma cita ============
$calNo = calForzada(['p_min' => 0.0, 'p_max' => 0.0]);
$p = labPartida();
parDeclarable($p, 2, 'bien', 28, 40); // B siente MÁS ⇒ pB<pA ⇒ quien rechaza = B (receptor)
IniciativaRomantica::registrarContinuidadPostCita($p, DA, DB, 'bien', $calNo);
setAbs($p, absNow($p) + 48);
$res = IniciativaRomantica::procesarContinuidad($p, $calNo);
ok(count($res) === 1 && str_starts_with((string) ($res[0]['resultado'] ?? ''), 'declaracion_rechazada_'), '8a declaracion_rechazada_*');
$hitos = RelacionBitacora::entre($p, DA, DB, RelacionBitacora::DECLARACION);
ok(count($hitos) === 1, '8b un hito DECLARACION');
$resHito = is_array($hitos[0]['resultado'] ?? null) ? $hitos[0]['resultado'] : [];
ok(($resHito['acepta_a'] ?? null) === true && ($resHito['acepta_b'] ?? null) === false, '8c resultado acepta flags');
ok(($hitos[0]['participantes'][0] ?? '') === DA && ($hitos[0]['participantes'][1] ?? '') === DB, '8d participantes [declara,rechaza]');
$m = markerDe($p);
ok($m !== null && ($m['accion'] ?? '') === 'cita', '8e continuidad RE-ARMADA como cita');
$el = IniciativaRomantica::elegibilidadDeclaracion($p, DA, DB, $cal);
ok(($el['motivo'] ?? '') === 'cooldown_hito', '8f nuevo intento bloqueado 336h (cooldown_hito)');
ok(\AquiHayTema\Engine\RechazoMemoria::countHacia($p, DB, DA) >= 1, '8g rechazo en memoria (B rechazó a A)');
ok(ParejaEngine::estado($p, DA, DB) === ParejaEngine::NINGUNA, '8h sin formación tras rechazo');

// ============ 9: diario con atribución correcta ============
$encontroDiario = false;
$textoOk = false;
foreach (($p['diario'] ?? []) as $e) {
    if ((string) ($e['titulo'] ?? '') === 'Una declaración rechazada') {
        $encontroDiario = true;
        $txt = (string) ($e['texto'] ?? '');
        $posA = strpos($txt, 'Ana');
        $posB = strpos($txt, 'Bruno');
        // Todas las variantes del banco sitúan a {declara} antes que {rechaza}.
        $textoOk = $posA !== false && $posB !== false && $posA < $posB;
    }
}
ok($encontroDiario, '9a diario: cuerpo de declaración rechazada presente');
ok($textoOk, '9b atribución correcta: Ana declaró, Bruno rechazó');

echo $fail === 0 ? "\nOK fase2b_declaracion\n" : "\nFAIL fase2b_declaracion ($fail)\n";
exit($fail === 0 ? 0 : 1);
