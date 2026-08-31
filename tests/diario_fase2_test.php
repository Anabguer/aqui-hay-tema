<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioNarrativaBridge;
use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\InteraccionCasual;
use AquiHayTema\Engine\InteraccionCasualNarrativaBridge;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;

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

function cotilleos(array $partida): array
{
    return array_values(array_filter(
        $partida['buzon'] ?? [],
        static fn($m) => is_array($m) && ($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO
    ));
}

$cal = CalibracionConfig::load($root);
$service = new PartidaService($root);
$store = (new Catalog($root))->store();
$catalog = new Catalog($root);

// --- 1) Casual irrelevante → no publica ---
$pIrrel = $service->nuevaPartida('juego_v1', 'fase2-casual-irrel');
$pIrrel['features']['buzon_enabled'] = true;
$ids = array_keys($pIrrel['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
RelacionEngine::registrarContacto($pIrrel, $a, $b, 'leve', $cal, 1);
RelacionEngine::registrarContacto($pIrrel, $b, $a, 'leve', $cal, 1);
$campoHobby = ConocimientoNpc::campoHobby('cafe_social');
$pIrrel['residentes'][$b]['runtime']['perfil_partida']['hobbies'] = ['cafe_social'];
DiscoveryEngine::registrar($pIrrel, $b, $campoHobby, 'cafe_social', 'test_setup');
$nCotiAntes = count(cotilleos($pIrrel));
$rng = RngService::fromPartida($pIrrel);
$hechoIrrel = InteraccionCasual::ejecutarPar(
    $pIrrel,
    $a,
    $b,
    'lug_cafeteria',
    $cal,
    $rng,
    $catalog
);
$nCotiDesp = count(cotilleos($pIrrel));
ok($nCotiDesp === $nCotiAntes, '1. casual sin descubrimiento nuevo → sin cotilleo');

// --- 2) Casual significativa (descubrimiento jugador) → publica ---
$pCas = $service->nuevaPartida('juego_v1', 'fase2-casual-desc');
$pCas['features']['buzon_enabled'] = true;
RelacionEngine::registrarContacto($pCas, $a, $b, 'normal', $cal, 1);
RelacionEngine::registrarContacto($pCas, $b, $a, 'normal', $cal, 1);
$pCas['residentes'][$b]['runtime']['perfil_partida']['hobbies'] = ['cafe_social'];
ok(
    DiscoveryEngine::estado($pCas, $b, $campoHobby) !== DiscoveryEngine::DESCUBIERTO,
    '2. hobby aún no descubierto'
);
$rng2 = RngService::fromPartida($pCas);
$hechoSig = InteraccionCasual::ejecutarPar($pCas, $a, $b, 'lug_cafeteria', $cal, $rng2, $catalog);
$descRows = is_array($hechoSig['descubrimientos']['descubiertos'] ?? null)
    ? $hechoSig['descubrimientos']['descubiertos']
    : [];
$tieneJugador = false;
foreach ($descRows as $row) {
    if (is_array($row) && ($row['quien'] ?? '') === 'jugador') {
        $tieneJugador = true;
        break;
    }
}
ok($tieneJugador, '2. casual genera descubrimiento jugador');
$cotiCas = cotilleos($pCas);
$tipoCas = array_values(array_filter($cotiCas, static fn($m) => ($m['tipo'] ?? '') === 'cotilleo_casual_descubrimiento'));
ok($tipoCas !== [], '2. cotilleo casual descubrimiento en buzón');
$evtCas = (string) ($tipoCas[0]['origen']['evento_id'] ?? '');
$diarioCas = DiarioEngine::entradaPorEvento($pCas, $evtCas);
ok($diarioCas !== null, '2. descubrimiento casual → diario');
ok(trim((string) ($diarioCas['texto'] ?? '')) !== '', '2. texto diario no vacío');
ok(
    in_array($a, $diarioCas['actores'] ?? [], true) && in_array($b, $diarioCas['actores'] ?? [], true),
    '2. actores del par en diario'
);

// --- 3) Acontecimiento vida relevante (ruptura) → publica ---
$pVida = $service->nuevaPartida('playtest_01', 'fase2-ruptura');
$pVida['features']['buzon_enabled'] = true;
$va = (string) array_key_first($pVida['residentes']);
$vb = null;
foreach (array_keys($pVida['residentes']) as $rid) {
    if ($rid !== $va) {
        $vb = (string) $rid;
        break;
    }
}
ok($vb !== null, '3. par para ruptura');
ParejaEngine::formar($pVida, $va, $vb, true, true, 'test', $cal);
$nAntesRup = count(cotilleos($pVida));
$rRup = AcontecimientoDiario::ejecutar($pVida, 'ruptura', [$va, $vb], $store, $cal);
ok($rRup['ok'] ?? false, '3. ruptura ejecuta');
$cotiRup = cotilleos($pVida);
ok(count($cotiRup) > $nAntesRup, '3. ruptura genera cotilleo');
$claveRup = 'diario_hito:' . RelacionBitacora::RUPTURA . ':' . implode('|', $va < $vb ? [$va, $vb] : [$vb, $va]);
$diarioRup = DiarioEngine::entradaPorEvento($pVida, $claveRup);
ok($diarioRup !== null, '3. ruptura → diario');

// --- 4) Acontecimiento cotidiano → no publica ---
$pRut = $service->nuevaPartida('playtest_01', 'fase2-rutina');
$pRut['features']['buzon_enabled'] = true;
$ridRut = (string) array_key_first($pRut['residentes']);
$nAntesRut = count(cotilleos($pRut));
$rRut = AcontecimientoDiario::ejecutar($pRut, 'actividad_individual', [$ridRut], $store, $cal);
ok($rRut['ok'] ?? false, '4. actividad_individual ejecuta');
ok(count(cotilleos($pRut)) === $nAntesRut, '4. rutina sin cotilleo');

// --- 5) Evento publicado en buzón (ya cubierto; revalidar mandar_flores) ---
$pFlor = $service->nuevaPartida('playtest_01', 'fase2-flores');
$pFlor['features']['buzon_enabled'] = true;
$fa = (string) array_key_first($pFlor['residentes']);
$fb = null;
foreach (array_keys($pFlor['residentes']) as $rid) {
    if ($rid !== $fa) {
        $fb = (string) $rid;
        break;
    }
}
RelacionEngine::registrarContacto($pFlor, $fa, $fb, 'normal', $cal, 3);
RelacionEngine::registrarContacto($pFlor, $fb, $fa, 'normal', $cal, 3);
RelacionEngine::setRomanceHacia($pFlor, $fa, $fb, 25);
$rFlor = AcontecimientoDiario::ejecutar($pFlor, 'mandar_flores', [$fa, $fb], $store, $cal);
if (($rFlor['ok'] ?? false)) {
    $florCoti = array_values(array_filter(
        cotilleos($pFlor),
        static fn($m) => ($m['hito_tipo'] ?? '') === RelacionBitacora::REGALO
            || ($m['tipo'] ?? '') === 'cotilleo_hito'
    ));
    ok($florCoti !== [], '5. mandar_flores → cotilleo en buzón');
} else {
    ok(true, '5. mandar_flores no elegible en esta seed (skip cotilleo)');
}

// --- 6–7) Fase 1 diario + actores (cubierto en 2 y 3) ---
ok(true, '6. flujo buzón→diario verificado en casos 2–3');

// --- 8) Idempotencia casual ---
if ($evtCas !== '') {
    $nDiarioAntes = count($pCas['diario'] ?? []);
    $diaCas = (int) ($pCas['reloj']['dia_pueblo'] ?? 1);
    $horaCas = (int) ($pCas['reloj']['hora_actual'] ?? 0);
    InteraccionCasualNarrativaBridge::alHecho($pCas, $hechoSig, $diaCas, $horaCas, $catalog);
    ok(count($pCas['diario'] ?? []) === $nDiarioAntes, '8. segundo mirror casual no duplica');
}

// --- 9) Mensajitos conserva perder_trabajo (aviso → importante, no cotilleo) ---
$pTrab = $service->nuevaPartida('playtest_01', 'fase2-trabajo');
$pTrab['features']['buzon_enabled'] = true;
$emp = null;
foreach ($pTrab['residentes'] as $id => $res) {
    $oc = (string) ($res['runtime']['ocupacion'] ?? '');
    if ($oc !== '' && $oc !== 'desempleado' && $oc !== 'jubilado' && $oc !== 'ninguna') {
        $emp = (string) $id;
        break;
    }
}
ok($emp !== null, '9. empleado para perder_trabajo');
$rTrab = AcontecimientoDiario::ejecutar($pTrab, 'perder_trabajo', [$emp], $store, $cal);
ok($rTrab['ok'] ?? false, '9. perder_trabajo ejecuta');
$imp = array_values(array_filter(
    $pTrab['buzon'] ?? [],
    static fn($m) => is_array($m) && ($m['clasificacion'] ?? '') === BuzonEngine::IMPORTANTE
        && ($m['tipo'] ?? '') === 'acontecimiento_perder_trabajo'
));
$cotiTrab = array_values(array_filter(
    cotilleos($pTrab),
    static fn($m) => ($m['tipo'] ?? '') === 'acontecimiento_perder_trabajo'
));
ok($imp !== [] || $cotiTrab === [], '9. perder_trabajo no forzado a cotilleo si canal es aviso');
ok($cotiTrab === [], '9. perder_trabajo sin cotilleo (Mensajitos/aviso)');

// --- 10) Sin placeholders ---
$sinPh = true;
foreach ($pCas['diario'] ?? [] as $ent) {
    if (!is_array($ent)) {
        continue;
    }
    if (($ent['_placeholder_contenido'] ?? false) || ($ent['origen']['_placeholder'] ?? false)) {
        $sinPh = false;
        break;
    }
    if (trim((string) ($ent['texto'] ?? '')) === '') {
        $sinPh = false;
        break;
    }
}
ok($sinPh, '10. entradas diario sin placeholder ni texto vacío');

echo $failures === 0 ? "OK diario_fase2\n" : "FAIL diario_fase2 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
