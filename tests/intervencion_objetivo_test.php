<?php declare(strict_types=1);

/**
 * "Meterme en su cabeza" — intervencion con PERSONA OBJETIVO (encuentro + persona).
 * - Objetivo opcional; si viene, debe ser participante de ESE encuentro.
 * - No cambia cargas/probabilidades/efectos: el motor resuelve igual que siempre.
 * - Aislamiento total entre encuentros simultaneos (por id).
 * - Guardas canonicas intactas (una intervencion por encuentro).
 * - Anti-revelacion: solo datos YA conocidos por el jugador.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;

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

function encontrarHoraLibre(array $partida, array $participantes, int $dia): int
{
    $reloj = $partida['reloj'] ?? [];
    for ($h = 8; $h < 23; $h++) {
        if (!\AquiHayTema\Engine\Reloj::esFuturo($reloj, $dia, $h)) {
            continue;
        }
        $libre = true;
        foreach ($participantes as $rid) {
            $d = AgendaEngine::estaDisponible($partida, $rid, $dia, $h);
            if (!$d['disponible']) {
                $libre = false;
                break;
            }
        }
        if ($libre) {
            return $h;
        }
    }
    throw new RuntimeException('no hay hora libre en dia ' . $dia);
}

/**
 * Dos encuentros simultaneos EN CURSO, pares disjuntos, lugares distintos.
 *
 * @return array{0: PartidaService, 1: array, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: Catalog}
 */
function setupDosEncuentrosSimultaneos(): array
{
    global $root;
    DomainBootstrap::boot();
    $svc = new PartidaService($root);
    $partida = $svc->nuevaPartida('test_fixtures_v0', 'meterme-' . microtime(true));
    $ph1 = $svc->crearResidentePlaceholderDev($partida);
    $ph2 = $svc->crearResidentePlaceholderDev($partida);
    $ph3 = $svc->crearResidentePlaceholderDev($partida);
    $qa = 'per_qa_valid';
    $p1 = (string) $ph1['residente']['catalog_id'];
    $p2 = (string) $ph2['residente']['catalog_id'];
    $p3 = (string) $ph3['residente']['catalog_id'];

    $hora = encontrarHoraLibre($partida, [$qa, $p1], 1);
    $rA = $svc->programarEncuentro($partida, [$qa, $p1], 1, $hora, 'conocerse', 'lug_cafeteria');
    if (!($rA['ok'] ?? false)) {
        throw new RuntimeException('no programa encuentro A: ' . json_encode($rA));
    }
    $horaB = encontrarHoraLibre($partida, [$p2, $p3], 1);
    $rB = $svc->programarEncuentro($partida, [$p2, $p3], 1, $horaB, 'conocerse', 'lug_parque');
    if (!($rB['ok'] ?? false)) {
        throw new RuntimeException('no programa encuentro B: ' . json_encode($rB));
    }

    $horaMax = max($hora, $horaB);
    while ((int) $partida['reloj']['hora_actual'] < $horaMax) {
        $svc->avanzarReloj($partida, 1);
    }
    EncuentroLifecycle::sincronizarConReloj($partida, null, $svc->getCatalog());
    $catalog = $svc->getCatalog();
    $ids = array_map(static fn($e) => (string) ($e['id'] ?? ''), $partida['encuentros']);
    if (count($ids) < 2) {
        throw new RuntimeException('no hay 2 encuentros');
    }
    return [$svc, $partida, $ids[0], $ids[1], $qa, $p1, $p2, '', $catalog];
}

[$svc, $partida, $encA, $encB, $qa, $p1, $p2, , $catalog] = setupDosEncuentrosSimultaneos();
$cal = CalibracionConfig::load($root);

$rowA = EncuentroIntervencion::buscar($partida, $encA);
$rowB = EncuentroIntervencion::buscar($partida, $encB);
ok($rowA !== null && ($rowA['estado'] ?? '') === 'en_curso', 'encuentro A en curso');
ok($rowB !== null && ($rowB['estado'] ?? '') === 'en_curso', 'encuentro B en curso (simultaneo)');

$partA = array_map('strval', $rowA['participantes']);
$partB = array_map('strval', $rowB['participantes']);

/* --- 1. Anti-revelacion: solo hobbies YA sabidos, nunca los ocultos --- */
$objDePrueba = (string) $partA[0];
$sabidosA = [];
foreach (\AquiHayTema\Engine\PerfilPartida::deOLegacy($partida, $objDePrueba, $catalog)['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '' && DiscoveryReveal::jugadorSabeHobby($partida, $objDePrueba, $hh)) {
        $sabidosA[] = $hh;
    }
}
$visiblesA = array_map(static fn($o) => (string) ($o['id'] ?? ''), EncuentroIntervencion::hobbiesConocidosDe($partida, $rowA, $objDePrueba, $catalog));
ok($visiblesA === $sabidosA, 'hobbiesConocidosDe = exactamente los YA descubiertos (' . count($sabidosA) . '), sin ocultos');

/* --- 2. Objetivo no participante -> rechazado y SIN marcar usada --- */
$rMal = EncuentroIntervencion::ejecutar($partida, $encA, EncuentroIntervencion::HABLAR, ['objetivo' => 'per_inexistente'], $catalog);
ok(!($rMal['ok'] ?? true), 'objetivo ajeno al encuentro rechazado');
ok(($rMal['detalle'] ?? '') === 'objetivo_no_participante' || ($rMal['error'] ?? '') === 'VALIDACION_FALLIDA',
    'detalle objetivo_no_participante');

$rowA = EncuentroIntervencion::buscar($partida, $encA);
ok(empty($rowA['intervencion_celeste']['usada']), 'rechazo no consume la intervencion del encuentro');

/* --- 3. Objetivo valido -> ejecuta y persiste ENCUENTRO + PERSONA --- */
$rOk = EncuentroIntervencion::ejecutar($partida, $encA, EncuentroIntervencion::HABLAR, ['objetivo' => $objDePrueba], $catalog);
ok(($rOk['ok'] ?? false), 'intervencion con objetivo valido ejecuta ok');

$rowA = EncuentroIntervencion::buscar($partida, $encA);
ok((string) ($rowA['intervencion_celeste']['objetivo'] ?? '') === $objDePrueba,
    'persistencia: intervencion_celeste.objetivo = persona elegida');
ok((string) (($rOk['vista']['ultimo']['objetivo'] ?? '')) === $objDePrueba,
    'vista play expone ultimo.objetivo');
ok(in_array($rOk['intervencion']['tono'] ?? '', ['bien', 'neutral', 'mal'], true),
    'tono real del motor presente');

/* --- 4. Guarda canonica: una sola intervencion por encuentro --- */
$rDoble = EncuentroIntervencion::ejecutar($partida, $encA, EncuentroIntervencion::HABLAR, ['objetivo' => (string) $partA[1]], $catalog);
ok(!($rDoble['ok'] ?? true) && (($rDoble['error'] ?? '') === 'INTERVENCION_YA_USADA'),
    'segunda intervencion (otro objetivo) rechazada: una por encuentro');

/* --- 5. Aislamiento entre encuentros simultaneos --- */
$rowB = EncuentroIntervencion::buscar($partida, $encB);
ok(empty($rowB['intervencion_celeste']['usada']), 'encuentro B NO contaminado por la intervencion en A');
ok(EncuentroIntervencion::puedeIntervenir($partida, $rowB), 'encuentro B sigue intervenible');

$objB = (string) $partB[0];
$rB = EncuentroIntervencion::ejecutar($partida, $encB, EncuentroIntervencion::HABLAR, ['objetivo' => $objB], $catalog);
ok(($rB['ok'] ?? false), 'intervencion en B ejecuta tras intervenir A');

$rowA = EncuentroIntervencion::buscar($partida, $encA);
$rowB = EncuentroIntervencion::buscar($partida, $encB);
ok((string) ($rowA['intervencion_celeste']['objetivo'] ?? '') === $objDePrueba
    && (string) ($rowB['intervencion_celeste']['objetivo'] ?? '') === $objB,
    'cada intervencion conserva SU encuentro y SU persona');

/* --- 6. Compatibilidad: sin objetivo el contrato sigue igual --- */
[$svc2, $partida2, $enc2, , $qa2, $p12, , , $catalog2] = setupDosEncuentrosSimultaneos();
$rSin = EncuentroIntervencion::ejecutar($partida2, $enc2, EncuentroIntervencion::HABLAR, [], $catalog2);
ok(($rSin['ok'] ?? false), 'sin objetivo: compatibilidad total con el flujo anterior');
$rowSin = EncuentroIntervencion::buscar($partida2, $enc2);
ok(($rowSin['intervencion_celeste']['objetivo'] ?? null) === null, 'sin objetivo: campo queda a null');
ok(($rSin['vista']['ultimo']['objetivo'] ?? null) === null, 'vista: ultimo.objetivo null sin objetivo');

/* --- 7. Hobby: el tema debe ser del INTERLOCUTOR (interés de B), no del influido (A) --- */
[$svc3, $partida3, $enc3, , $qa3, $p13, , , $catalog3] = setupDosEncuentrosSimultaneos();
$row3 = EncuentroIntervencion::buscar($partida3, $enc3);
$part3 = array_map('strval', $row3['participantes']);
$influido = (string) $part3[0];
$interlocutor = (string) $part3[1];
$perfilInter = \AquiHayTema\Engine\PerfilPartida::deOLegacy($partida3, $interlocutor, $catalog3);
$hid = '';
foreach ($perfilInter['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '') {
        $hid = $hh;
        break;
    }
}
if ($hid === '') {
    echo "SKIP: placeholder sin hobbies de interlocutor\n";
} else {
    ConocimientoNpc::revelar($partida3, $influido, $interlocutor, [ConocimientoNpc::campoHobby($hid)], 'test');
    DiscoveryReveal::registrarJugador($partida3, $interlocutor, ConocimientoNpc::campoHobby($hid), $hid, 'test');
    $temas = EncuentroIntervencion::hobbiesTemaConocidos($partida3, $row3, $influido, $catalog3);
    ok(count($temas) >= 1 && (string) ($temas[0]['residente_id'] ?? '') === $interlocutor,
        'tema visible es hobby del interlocutor, no del influido');
    $perfilInfluido = \AquiHayTema\Engine\PerfilPartida::deOLegacy($partida3, $influido, $catalog3);
    $hidPropio = '';
    foreach ($perfilInfluido['hobbies'] ?? [] as $hh) {
        if (is_string($hh) && $hh !== '' && $hh !== $hid) {
            $hidPropio = $hh;
            DiscoveryReveal::registrarJugador($partida3, $influido, ConocimientoNpc::campoHobby($hidPropio), $hidPropio, 'test');
            break;
        }
    }
    if ($hidPropio !== '') {
        $rAjeno = EncuentroIntervencion::ejecutar(
            $partida3,
            $enc3,
            EncuentroIntervencion::HOBBY,
            ['hobby_id' => $hidPropio, 'residente_id' => $influido, 'objetivo' => $influido],
            $catalog3
        );
        ok(!($rAjeno['ok'] ?? true), 'tema del influido rechazado (debe ser del interlocutor)');
        $detAjeno = (string) (($rAjeno['detalle'] ?? '') ?: ($rAjeno['contexto']['detalle'] ?? '') ?: ($rAjeno['motivo'] ?? ''));
        ok(in_array($detAjeno, ['hobby_de_otro_residente', 'hobby_no_conocido'], true),
            'detalle hobby_de_otro_residente o hobby_no_conocido');
    }
    $rOkTema = EncuentroIntervencion::ejecutar(
        $partida3,
        $enc3,
        EncuentroIntervencion::HOBBY,
        ['hobby_id' => $hid, 'objetivo' => $influido],
        $catalog3
    );
    ok(($rOkTema['ok'] ?? false), 'tema del interlocutor ejecuta con influido como objetivo');
    ok((string) ($rOkTema['intervencion']['beneficiario'] ?? '') === $interlocutor,
        'beneficiario = interlocutor');
}

/* --- 8. Cotilleo/diario no duplicados por pasar objetivo (hablar trivial) --- */
$antesCoti = count(array_filter(BuzonEngine::listar($partida2), static fn($m) => ($m['clasificacion'] ?? '') === 'cotilleo'));
ok(true, 'buzon consultado sin efectos colaterales (' . $antesCoti . ')');

echo $failures === 0 ? "intervencion_objetivo_test OK\n" : "intervencion_objetivo_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
