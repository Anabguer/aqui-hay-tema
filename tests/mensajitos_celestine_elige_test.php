<?php
declare(strict_types=1);

/*
 * MENSAJITOS JUGABLES: CELESTINE ELIGE PERSONA (conocer_a_alguien).
 * Pipeline real: nacerConocer -> snapshot opciones -> elegir_persona ->
 * propuesta PRESENTAR canónica -> voluntad/agenda/cooldown -> feedback.
 * Sin tocar calibración. Sin inventar NPC. Sin datos privados en el DTO.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\PeticionPlantillas;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

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

function partidaBase(string $root, string $seed, string $t0s): array
{
    Reloj::fijarAhora(new DateTimeImmutable($t0s, Reloj::zona()));
    DomainBootstrap::resetForTests();
    DomainBootstrap::boot();
    $service = new \AquiHayTema\Engine\PartidaService($root);
    $p = $service->nuevaPartida('juego_v1', $seed, ['fecha' => '2026-08-17', 'hora' => 8]);
    completarTutorialJuegoV1($p, $root);
    $service->avanzarRelojPasoAPaso($p, 24); // D2·08; pareja tutorial ya se conoce
    return [$service, $p];
}

/** Petición B4 manual con forma legacy (para tests de compatibilidad). */
function crearPet(array &$p, string $rid, string $plantilla, array $params): array
{
    $pl = PeticionPlantillas::porId($plantilla);
    $texto = (string) ($pl['copy'] ?? 'pet');
    if (isset($params['lugar_id'])) {
        $texto = str_replace('{lugar}', 'el lugar', $texto);
    }
    if (isset($params['otro'])) {
        $texto = str_replace('{otro}', 'alguien', $texto);
    }
    $r = PeticionEngine::crear($p, $rid, (string) ($pl['tipo_legado'] ?? 'otro'), [
        'schema_b4' => true,
        'plantilla_id' => $plantilla,
        'familia' => (string) ($pl['familia'] ?? ''),
        'params' => $params,
        'texto' => $texto,
        'hecho' => (string) ($pl['hecho'] ?? ''),
        'peso' => (string) ($pl['peso'] ?? 'facil'),
        'exigencia' => (int) ($pl['exigencia'] ?? 0),
        'plazo_horas' => (int) ($pl['plazo_horas'] ?? 24),
        'cuenta_latido' => false,
        '_placeholder_copy' => false,
    ], null);
    return $r['peticion'] ?? [];
}

function aislarPet(array &$p, string $keepId): void
{
    foreach ($p['peticiones'] as &$lp) {
        if (!empty($lp['schema_b4'])
            && (string) ($lp['id'] ?? '') !== $keepId
            && (string) ($lp['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA) {
            $lp['estado'] = 'caducada';
        }
    }
    unset($lp);
}

function petPorId(array $p, string $id): ?array
{
    foreach ($p['peticiones'] as $lp) {
        if ((string) ($lp['id'] ?? '') === $id) {
            return $lp;
        }
    }
    return null;
}

function msgPorId(array $p, ?string $id): ?array
{
    if ($id === null || $id === '') {
        return null;
    }
    foreach ($p['buzon'] as $m) {
        if ((string) ($m['id'] ?? '') === $id) {
            return BuzonEngine::normalizar($m);
        }
    }
    return null;
}

/** Evaluator stub: acepta todos salvo el rid marcado, que rechaza por VOLUNTAD. */
final class StubRechazaUno implements VoluntadEvaluator
{
    private string $rechaza;
    public function __construct(string $rechaza)
    {
        $this->rechaza = $rechaza;
    }
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        if ($residenteId === $this->rechaza) {
            return [
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
                'motivo_tecnico' => 'stub_rechaza_voluntad',
                'motivo_tipo' => 'banal',
                'copy_id' => 'hoy_no_me_da_la_vida',
                'score' => 10,
                'p' => 0.1,
                'factores' => ['stub' => true],
                '_bloqueado_decision' => false,
            ];
        }
        return [
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'stub_acepta',
            'motivo_tipo' => null,
            'copy_id' => null,
            'score' => 60,
            'p' => 0.9,
            'factores' => ['stub' => true],
            '_bloqueado_decision' => false,
            '_joint_plan' => true,
        ];
    }
}

/** Evaluator stub: rechaza a TODOS por INDISPONIBILIDAD (agenda). */
final class StubIndisponibilidad implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
            'motivo_tecnico' => 'stub_agenda_ocupada',
            'copy_id' => null,
            'factores' => ['agenda_disponible' => false],
            '_bloqueado_decision' => false,
        ];
    }
}

/** Cierra peticiones abiertas previas del residente (el tick natural pudo nacerle una). */
function liberarPeticionario(array &$p, string $rid): void
{
    foreach ($p['peticiones'] as &$lp) {
        if ((string) ($lp['residente_id'] ?? '') === $rid
            && (string) ($lp['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA) {
            $lp['estado'] = 'caducada';
        }
    }
    unset($lp);
}

/** Cierra TODAS las B4 abiertas (para probar nacimientos con huecos libres). */
function liberarPueblo(array &$p): void
{
    foreach ($p['peticiones'] as &$lp) {
        if (!empty($lp['schema_b4'])
            && (string) ($lp['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA) {
            $lp['estado'] = 'caducada';
        }
    }
    unset($lp);
}

/** Nace conocer_a_alguien para el peticionario y devuelve [pet, mensajeBuzon]. */
function nacerSelector(array &$p, string $rid): array
{
    liberarPeticionario($p, $rid);
    $pet = PeticionPuebloEngine::nacerConocer($p, $rid);
    if ($pet === null) {
        return [null, null];
    }
    aislarPet($p, (string) $pet['id']);
    return [$pet, msgPorId($p, $pet['buzon_id'] ?? null)];
}

// ================================================================
echo "== 1-5) Nacimiento con selector: opciones reales, sin peticionario, únicas, <=3, snapshot estable ==\n";
{
    $tMulti = 0;
    $tSinPet = 0;
    $tUnicas = 0;
    $tMax3 = 0;
    $tEstable = 0;
    for ($i = 0; $i < 6; $i++) {
        [, $p] = partidaBase($root, "celest-a-$i", Reloj::TEST_AHORA);
        $rid = (string) $p['tutorial']['pareja_mision1']['a'];
        // Pista legítima pre-cargada sobre el primer presentable (reveal día 1 real).
        $presentables = PeticionPuebloEngine::presentablesParaConocer($p, $rid);
        if (count($presentables) >= 2) {
            $perfil = \AquiHayTema\Engine\PerfilPartida::de($p, $presentables[0]);
            $h0 = (string) (($perfil['hobbies'] ?? [])[0] ?? '');
            if ($h0 !== '') {
                DiscoveryReveal::registrarJugador($p, $presentables[0], 'hobby:' . $h0, $h0, 'reveal_inicial');
            }
        }
        [$pet, $msg] = nacerSelector($p, $rid);
        if ($pet === null || $msg === null) {
            continue;
        }
        $ops = is_array($pet['params']['opciones'] ?? null) ? $pet['params']['opciones'] : [];
        if (count($ops) < 2) {
            continue;
        }
        $tMulti++;
        $ids = array_map(static fn($o) => (string) ($o['personaje_id'] ?? ''), $ops);
        if (!in_array($rid, $ids, true)) {
            $tSinPet++;
        }
        if (count($ids) === count(array_unique($ids))) {
            $tUnicas++;
        }
        if (count($ops) <= PeticionPuebloEngine::MAX_OPCIONES_SELECTOR) {
            $tMax3++;
        }
        foreach ($ops as $o) {
            if (!isset($p['residentes'][$o['personaje_id']])
                || ($p['residentes'][$o['personaje_id']]['presencia'] ?? '') !== 'residente') {
                $tMulti--; // opción no residente real
                break;
            }
        }
        // Snapshot estable: reloj avanza, el save guardado NO cambia.
        $antes = serialize([$pet['params']['opciones'], $msg['selector_opciones']]);
        (new \AquiHayTema\Engine\PartidaService($root))->avanzarRelojPasoAPaso($p, 5);
        $pet2 = petPorId($p, (string) $pet['id']);
        $msg2 = msgPorId($p, $pet['buzon_id'] ?? null);
        $despues = serialize([
            is_array($pet2['params']['opciones'] ?? null) ? $pet2['params']['opciones'] : [],
            is_array($msg2['selector_opciones'] ?? null) ? $msg2['selector_opciones'] : [],
        ]);
        if ($antes === $despues && $despues !== '') {
            $tEstable++;
        }
        // Mensajito con acciones_ui listas para pintar.
        ok(BuzonEngine::tieneDecisionPendiente(msgPorId($p, $pet['buzon_id'] ?? null) ?? []), "1.$i: decisión pendiente en Mensajito");
    }
    ok($tMulti === 6, "1: nuevas peticiones ofrecen >=2 personas reales (6/6): $tMulti");
    ok($tSinPet === $tMulti, "2: peticionario nunca entre las opciones ($tSinPet/$tMulti)");
    ok($tUnicas === $tMulti, "3: sin duplicados ($tUnicas/$tMulti)");
    ok($tMax3 === $tMulti, "4: máximo acordado (" . PeticionPuebloEngine::MAX_OPCIONES_SELECTOR . ") ($tMax3/$tMulti)");
    ok($tEstable === $tMulti, "5: snapshot estable tras avanzar reloj ($tEstable/$tMulti)");
}

echo "\n== 12) Privacidad: DTO mínimo, pista solo si revelada, nada de química/scores/prefs ==\n";
{
    [, $p] = partidaBase($root, 'celest-priv-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    [$pet, $msg] = nacerSelector($p, $rid);
    ok($pet !== null && $msg !== null, '12-pre: petición con selector nacida');
    $ops = is_array($pet['params']['opciones'] ?? null) ? $pet['params']['opciones'] : [];
    $clavesOk = true;
    foreach ($ops as $o) {
        $ks = array_keys($o);
        sort($ks);
        if ($ks !== ['nombre', 'personaje_id', 'pista'] && $ks !== ['nombre', 'personaje_id']) {
            $clavesOk = false;
        }
    }
    ok($clavesOk, '12: claves del DTO solo personaje_id/nombre/pista');
    $json = json_encode(['opciones' => $ops, 'selector' => $msg['selector_opciones'] ?? []], JSON_UNESCAPED_UNICODE);
    $prohibido = ['quimica', 'compat', 'atraccion', 'score', '"p"', 'probab', 'preferencia', 'romantica', 'rasgos_ocultos', 'dealbreaker'];
    $sinPriv = true;
    foreach ($prohibido as $bad) {
        if (stripos((string) $json, $bad) !== false) {
            $sinPriv = false;
        }
    }
    ok($sinPriv, '12: ninguna clave/dato privado viaja al frontend');
    // Pista solo si ya estaba revelada: la pre-cargada existe; otra opción sin reveal -> pista null o legítima.
    $conPista = 0;
    $pistasLegitimas = true;
    foreach ($ops as $o) {
        $pidO = (string) $o['personaje_id'];
        if (($o['pista'] ?? null) !== null) {
            $conPista++;
            $perfil = \AquiHayTema\Engine\PerfilPartida::de($p, $pidO);
            $algunaRevelada = false;
            foreach ((is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : []) as $h) {
                if (DiscoveryReveal::jugadorSabeHobby($p, $pidO, (string) $h)) {
                    $algunaRevelada = true;
                    break;
                }
            }
            if (!$algunaRevelada) {
                $pistasLegitimas = false;
            }
        }
    }
    ok($pistasLegitimas, '12: toda pista procede de un descubrimiento ya registrado');
    ok($conPista >= 1, "12: hay pista útil cuando hubo reveal previo ($conPista opciones con pista)");
}

echo "\n== 6) 0 candidatos -> no nace petición imposible; 1 candidato -> legacy sin selector ==\n";
{
    [, $p] = partidaBase($root, 'celest-cero-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    foreach ($p['residentes'] as $k => $r) {
        if ($k !== $rid) {
            $p['residentes'][$k]['presencia'] = 'fuera';
        }
    }
    $nula = PeticionPuebloEngine::nacerConocer($p, $rid);
    ok($nula === null, '6: 0 candidatos válidos -> no nace');
    // 1 válido: comportamiento actual (params.otro), sin selector.
    [, $p1] = partidaBase($root, 'celest-uno-0', Reloj::TEST_AHORA);
    $rid1 = (string) $p1['tutorial']['pareja_mision1']['a'];
    $conservar = [$rid1];
    $presentables1 = PeticionPuebloEngine::presentablesParaConocer($p1, $rid1);
    if (count($presentables1) >= 1) {
        $conservar[] = $presentables1[0];
    }
    foreach ($p1['residentes'] as $k => $r) {
        if (!in_array($k, $conservar, true)) {
            $p1['residentes'][$k]['presencia'] = 'fuera';
        }
    }
    liberarPeticionario($p1, $rid1);
    $pet1 = PeticionPuebloEngine::nacerConocer($p1, $rid1);
    ok($pet1 !== null, '6-bis: 1 candidato -> sí nace');
    ok(isset($pet1['params']['otro']) && !isset($pet1['params']['opciones']), '6-bis: contrato legacy params.otro, sin opciones');
    $m1 = msgPorId($p1, $pet1['buzon_id'] ?? null);
    ok($m1 !== null && empty($m1['selector_opciones']), '6-bis: Mensajito legacy sin selector');
    ok(!in_array('elegir_persona', $m1['acciones'] ?? [], true), '6-bis: sin acción elegir_persona en legacy');
}

echo "\n== 7) Elección válida -> candidato_elegido + propuesta PRESENTAR canónica ==\n";
{
    [, $p] = partidaBase($root, 'celest-elegir-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    [$pet, $msg] = nacerSelector($p, $rid);
    $ops = $pet['params']['opciones'];
    $objetivo = (string) $ops[0]['personaje_id'];
    $nPropsAntes = count($p['propuestas_encuentro'] ?? []);
    $r = PeticionPuebloEngine::elegirCandidato(
        $p,
        (string) $msg['id'],
        $objetivo,
        [],
        new StubRechazaUno('__nadie__')
    );
    ok(($r['ok'] ?? false) === true && ($r['ya_elegido'] ?? false) === false, '7: elección aceptada por backend');
    $pet2 = petPorId($p, (string) $pet['id']);
    ok((string) ($pet2['candidato_elegido'] ?? '') === $objetivo, '7: candidato_elegido persistido');
    ok((string) ($pet2['selector']['via'] ?? '') === 'celestine', '7: trazabilidad via=celestine');
    $propsNuevas = array_slice($p['propuestas_encuentro'] ?? [], $nPropsAntes);
    ok(count($propsNuevas) === 1, '7: exactamente UNA propuesta creada');
    $prop = $propsNuevas[0] ?? [];
    ok(\AquiHayTema\Engine\PropuestaNivel::aliasTipo((string) ($prop['tipo'] ?? '')) === \AquiHayTema\Engine\PropuestaNivel::PRESENTAR, '7: tipo PRESENTAR (canónico)');
    $partes = is_array($prop['participantes'] ?? null) ? $prop['participantes'] : [];
    sort($partes);
    $esperado = [$rid, $objetivo];
    sort($esperado);
    ok($partes === $esperado, '7: participantes = peticionario + elegido');
    // Vía capas: la llamada DIRECTA al motor no cierra la decisión del Mensajito;
    // eso lo hace MensajitoAcciones::resolver (capa API/UI), verificado en caso 9.
    $estadoMsg = (string) (msgPorId($p, $pet['buzon_id'] ?? null)['estado_decision'] ?? '');
    ok($estadoMsg === BuzonEngine::DECISION_PENDIENTE, '7: llamada directa al motor deja la decisión en la capa acciones');
}

echo "\n== 8) ID fuera del snapshot -> rechazado ==\n";
{
    [, $p] = partidaBase($root, 'celest-fuera-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    [$pet, $msg] = nacerSelector($p, $rid);
    $idsSnap = array_map(static fn($o) => (string) $o['personaje_id'], $pet['params']['opciones']);
    $fuera = '';
    foreach (PeticionPuebloEngine::residentes($p) as $x) {
        if ($x !== $rid && !in_array($x, $idsSnap, true)) {
            $fuera = $x;
            break;
        }
    }
    if ($fuera === '') {
        // Pueblo pequeño con snapshot = todos: usar ID inexistente.
        $fuera = 'per_inexistente_xy';
    } else {
        $p['residentes'][$fuera]['presencia'] = 'residente';
    }
    $r8 = PeticionPuebloEngine::elegirCandidato($p, (string) $msg['id'], $fuera, [], new StubRechazaUno('__nadie__'));
    ok(($r8['ok'] ?? true) === false && ($r8['error'] ?? '') === 'candidato_fuera_snapshot', '8: fuera del snapshot rechazado');
    $pet8 = petPorId($p, (string) $pet['id']);
    ok(($pet8['candidato_elegido'] ?? null) === null, '8: no se registró elección inválida');
    $r8b = PeticionPuebloEngine::elegirCandidato($p, (string) $msg['id'], $rid, [], new StubRechazaUno('__nadie__'));
    ok(($r8b['error'] ?? '') === 'candidato_fuera_snapshot', '8-bis: el propio peticionario no es elegible');
}

echo "\n== 9) Idempotencia: doble resolución no duplica presentación ==\n";
{
    [, $p] = partidaBase($root, 'celest-idem-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    [$pet, $msg] = nacerSelector($p, $rid);
    $objetivo = (string) $pet['params']['opciones'][0]['personaje_id'];
    $payload = ['mensaje_id' => (string) $msg['id'], 'accion' => MensajitoAcciones::ELEGIR_PERSONA, 'personaje_id' => $objetivo];
    $r1 = MensajitoAcciones::resolver($p, (string) $msg['id'], MensajitoAcciones::ELEGIR_PERSONA, $root, null, $payload);
    ok(($r1['ok'] ?? false) === true, '9: primera resolución OK');
    ok((string) (msgPorId($p, (string) $msg['id'])['estado_decision'] ?? '') !== BuzonEngine::DECISION_PENDIENTE, '9-bis: capa acciones cerró la decisión del Mensajito');
    $nProps = count($p['propuestas_encuentro'] ?? []);
    $r2 = MensajitoAcciones::resolver($p, (string) $msg['id'], MensajitoAcciones::ELEGIR_PERSONA, $root, null, $payload);
    ok(($r2['ok'] ?? true) === false && ($r2['error'] ?? '') === 'sin_decision_pendiente', '9: reenvío por MensajitoAcciones bloqueado');
    $r3 = PeticionPuebloEngine::elegirCandidato($p, (string) $msg['id'], $objetivo, [], new StubRechazaUno('__nadie__'));
    ok(($r3['ok'] ?? false) === true && ($r3['ya_elegido'] ?? false) === true, '9: reenvío directo devuelve eco ya_elegido');
    ok(count($p['propuestas_encuentro'] ?? []) === $nProps, '9: cero propuestas nuevas tras reenvíos');
}

echo "\n== 10) La voluntad del presentado puede RECHAZAR; petición sigue abierta + feedback tercero ==\n";
{
    [, $p] = partidaBase($root, 'celest-vol-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    [$pet, $msg] = nacerSelector($p, $rid);
    $objetivo = (string) $pet['params']['opciones'][0]['personaje_id'];
    $r = PeticionPuebloEngine::elegirCandidato($p, (string) $msg['id'], $objetivo, [], new StubRechazaUno($objetivo));
    ok(($r['ok'] ?? false) === true && ($r['propuesta_estado'] ?? '') === 'rechazada', '10: propuesta rechazada por voluntad');
    ok(empty($r['programado']), '10: ningún encuentro programado');
    $pet2 = petPorId($p, (string) $pet['id']);
    ok((string) ($pet2['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA, '10: petición sigue ABIERTA');
    $ecoOk = false;
    foreach ($p['buzon'] as $m) {
        if ((string) ($m['tipo'] ?? '') === 'peticion_resultado'
            && strpos((string) ($m['texto'] ?? ''), IdentidadPublica::nombre($p, $objetivo)) !== false
            && strpos((string) ($m['texto'] ?? ''), IdentidadPublica::nombre($p, $rid)) !== false) {
            $ecoOk = true;
            break;
        }
    }
    ok($ecoOk, '10: mensajito eco "sí quería pero X no" presente');
}

echo "\n== 11) Agenda y cooldown siguen actuando ==\n";
{
    [, $p] = partidaBase($root, 'celest-agenda-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    [$pet, $msg] = nacerSelector($p, $rid);
    $objetivo = (string) $pet['params']['opciones'][0]['personaje_id'];
    $rAg = PeticionPuebloEngine::elegirCandidato($p, (string) $msg['id'], $objetivo, [], new StubIndisponibilidad());
    ok(($rAg['ok'] ?? false) === true && ($rAg['rechazo_clase'] ?? '') === PropuestaEncuentro::CLASE_INDISPONIBILIDAD, '11: indisponibilidad de agenda respeta al elegido');
    // Cooldown canónico: voluntad rechaza -> RechazoMemoria marca cooldown ->
    // re-presentar al mismo candidato con voluntad CANÓNICA cae por cooldown,
    // incluso aunque el peticionario tenga compromiso 'exacta'.
    [, $p2] = partidaBase($root, 'celest-cool-0', Reloj::TEST_AHORA);
    $rid2 = (string) $p2['tutorial']['pareja_mision1']['a'];
    [$petC, $msgC] = nacerSelector($p2, $rid2);
    $objC = (string) $petC['params']['opciones'][0]['personaje_id'];
    PeticionPuebloEngine::elegirCandidato($p2, (string) $msgC['id'], $objC, [], new StubRechazaUno($objC));
    ok(\AquiHayTema\Engine\PropuestaCooldown::activo(
        $p2,
        $rid2,
        $objC,
        \AquiHayTema\Engine\PropuestaNivel::PRESENTAR
    ), '11-pre: cooldown marcado tras rechazo de voluntad');
    $slot = ((int) $p2['reloj']['dia_pueblo']) * 24 + (int) $p2['reloj']['hora_actual'] + 1;
    $rCD = PropuestaEncuentroEngine::proponer(
        $p2,
        [$rid2, $objC],
        intdiv($slot, 24),
        $slot % 24,
        \AquiHayTema\Engine\PropuestaNivel::PRESENTAR,
        PeticionPuebloEngine::LUGAR_PRESENTACION
    );
    $cdEnReaccion = false;
    foreach (($rCD['propuesta']['reacciones'] ?? []) as $rc) {
        if ((string) ($rc['motivo_tecnico'] ?? '') === 'cooldown_propuesta') {
            $cdEnReaccion = true;
        }
    }
    ok(empty($rCD['programado']) && ($rCD['propuesta']['estado'] ?? '') === 'rechazada' && $cdEnReaccion, '11: cooldown canónico tumba la re-presentación inmediata');
}
function GameErrorCaseCooldown(): string
{
    return \AquiHayTema\Engine\GameError::ENCUENTRO_RECHAZADO_COOLDOWN;
}

echo "\n== 13) Save antiguo con params.otro sigue funcionando ==\n";
{
    [, $p] = partidaBase($root, 'celest-leg-0', Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mission1'] ?? $p['tutorial']['pareja_mision1'];
    $aId = (string) $parT['a'];
    $bId = (string) $parT['b'];
    $pet = crearPet($p, $aId, 'conocer_a_alguien', ['otro' => $bId]);
    aislarPet($p, (string) $pet['id']);
    ok(PeticionPuebloEngine::encaja($pet, [
        'tipo' => 'conocerse',
        'participantes' => [$aId, $bId],
        'lugar' => 'lug_cafeteria',
    ]), '13: legacy encaja con cualquier PRESENTAR');
    // Elegir persona sobre legacy -> error limpio, sin crash ni selector fantasma.
    $mL = msgPorId($p, $pet['buzon_id'] ?? null);
    $rL = PeticionPuebloEngine::elegirCandidato($p, (string) ($mL['id'] ?? ''), $bId, [], new StubRechazaUno('__nadie__'));
    ok(($rL['ok'] ?? true) === false && ($rL['error'] ?? '') === 'sin_opciones_legacy', '13: elegir_persona sobre legacy -> sin_opciones_legacy');
    $rL2 = MensajitoAcciones::resolver($p, (string) ($mL['id'] ?? ''), MensajitoAcciones::ELEGIR_PERSONA, $root, null, ['personaje_id' => $bId]);
    ok(($rL2['ok'] ?? true) === false, '13: capa acciones también lo deniega en legacy');
    // El flujo canónico de cumplir sigue igual con legacy.
    foreach ([17, 18, 19, 20] as $h) {
        $rp = PropuestaEncuentroEngine::proponer($p, [$aId, $bId], 2, $h, 'conocerse', 'lug_cafeteria');
        if (!empty($rp['programado'])) {
            break;
        }
    }
    $rc = PeticionPuebloEngine::cumplir($p, (string) $pet['id']);
    ok(($rc['ok'] ?? false) === true, '13: cumplir canónico OK en legacy');
}

echo "\n== 14) Resto de plantillas B4 intactas ==\n";
{
    [, $p] = partidaBase($root, 'celest-b4-0', Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mision1'];
    $aId = (string) $parT['a'];
    $bId = (string) $parT['b'];
    $casos = [
        ['quedar_con_x', ['otro' => $bId], ['tipo' => 'quedar', 'participantes' => [$aId, $bId], 'lugar' => 'lug_cafe']],
        ['volver_a_ver', ['otro' => $bId], ['tipo' => 'quedar', 'participantes' => [$aId, $bId], 'lugar' => 'lug_cafe']],
        ['primera_cita_pet', ['otro' => $bId], ['tipo' => 'primera_cita', 'participantes' => [$aId, $bId], 'lugar' => 'lug_cine']],
        ['ir_al_lugar', ['lugar_id' => 'lug_cine'], ['tipo' => 'quedar', 'participantes' => [$aId], 'lugar' => 'lug_cine']],
    ];
    $todosOk = true;
    foreach ($casos as [$tpl, $params, $enc]) {
        $pt = crearPet($p, $aId, $tpl, $params);
        if (!PeticionPuebloEngine::encaja($pt, $enc)) {
            $todosOk = false;
        }
        if (isset($pt['params']['opciones'])) {
            $todosOk = false; // otras plantillas NO ganan selector
        }
    }
    ok($todosOk, '14: encaje y params de otras plantillas sin cambios, sin selector');
    $pd = crearPet($p, $aId, 'salir_de_casa', []);
    ok(PeticionPuebloEngine::encaja($pd, ['tipo' => 'quedar', 'participantes' => [$aId, $bId], 'lugar' => 'lug_parque']), '14: salir_de_casa intacta');
}

echo "\n== 15) Cap/cadencia R07 intactos ==\n";
{
    $calVacio = [];
    ok(PeticionPuebloEngine::capSimultaneas(4, $calVacio) === 2, '15: suelo R07 cap_min=2 en pueblo pequeño');
    ok(PeticionPuebloEngine::capSimultaneas(15, $calVacio) === 5, '15: fórmula pct media poblaciones');
    ok(PeticionPuebloEngine::capSimultaneas(40, $calVacio) === 10, '15: techo cap_max=10');
    [, $p] = partidaBase($root, 'celest-r07-0', Reloj::TEST_AHORA);
    liberarPueblo($p);
    $antes = count($p['peticiones']);
    $p['_b4_forzar_nacer'] = true;
    $nacidas = 0;
    for ($i = 0; $i < 4; $i++) {
        if (PeticionPuebloEngine::intentarNacer($p, []) !== null) {
            $nacidas++;
        }
    }
    unset($p['_b4_forzar_nacer']);
    ok($nacidas > 0, "15: tick forzado sigue naciendo peticiones (+$nacidas)");
    ok(count($p['peticiones']) >= $antes, '15: registro de peticiones crece sin alterar cadencia base');
}

echo "\n== 16) Feedback cumplida/caducada coherente con selector ==\n";
{
    [, $p] = partidaBase($root, 'celest-fb-0', Reloj::TEST_AHORA);
    $rid = (string) $p['tutorial']['pareja_mision1']['a'];
    [$pet, $msg] = nacerSelector($p, $rid);
    $objetivo = (string) $pet['params']['opciones'][0]['personaje_id'];
    $re = PeticionPuebloEngine::elegirCandidato($p, (string) $msg['id'], $objetivo, [], new StubRechazaUno('__nadie__'));
    $prog = !empty($re['programado']);
    $intentos = 0;
    while (!$prog && $intentos < 6) {
        $slot = ((int) $p['reloj']['dia_pueblo']) * 24 + (int) $p['reloj']['hora_actual'] + 1;
        $rp = PropuestaEncuentroEngine::proponer(
            $p,
            [$rid, $objetivo],
            intdiv($slot, 24),
            $slot % 24,
            \AquiHayTema\Engine\PropuestaNivel::PRESENTAR,
            PeticionPuebloEngine::LUGAR_PRESENTACION,
            null,
            new StubRechazaUno('__nadie__')
        );
        $prog = !empty($rp['programado']);
        $intentos++;
    }
    ok($prog, '16-pre: presentación programada');
    $svc = new \AquiHayTema\Engine\PartidaService($root);
    $estado = PeticionPuebloEngine::EST_ABIERTA;
    for ($k = 0; $k < 48 && $estado !== PeticionPuebloEngine::EST_RESUELTA; $k++) {
        $svc->avanzarRelojPasoAPaso($p, 1);
        $estado = (string) (petPorId($p, (string) $pet['id'])['estado'] ?? '');
    }
    ok($estado === PeticionPuebloEngine::EST_RESUELTA, '16: cumplida tras celebrarse la presentación');
    $ecoCumplida = false;
    foreach ($p['buzon'] as $m) {
        if ((string) ($m['tipo'] ?? '') === 'peticion_resultado'
            && strpos((string) ($m['texto'] ?? ''), IdentidadPublica::nombre($p, $rid)) !== false) {
            $ecoCumplida = true;
            break;
        }
    }
    ok($ecoCumplida, '16: eco positivo de cumplida presente');
    // Caducada: petición nueva ignorada hasta vencer.
    [$petX, ] = nacerSelector($p, $rid);
    if ($petX !== null) {
        $svc->avanzarRelojPasoAPaso($p, 49);
        $estadoX = (string) (petPorId($p, (string) $petX['id'])['estado'] ?? '');
        ok($estadoX === PeticionPuebloEngine::EST_CADUCADA, '16-bis: caducidad canónica intacta con selector');
    }
}

echo "\n";
echo $failures === 0 ? "TODO OK\n" : "FALLOS: $failures\n";
exit($failures === 0 ? 0 : 1);
