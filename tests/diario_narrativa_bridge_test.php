<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioNarrativaBridge;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\VistaCotilleoV3;

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

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$service = new PartidaService($root);

// --- 1) Encuentro social → cotilleo + diario ---
$pEnc = $service->nuevaPartida('playtest_01', 'diario-narr-enc');
$pEnc['features']['buzon_enabled'] = true;
$ida = 'per_p001';
$idb = 'per_p002';
$enc = $service->programarEncuentro($pEnc, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa encuentro');
$dia = (int) $enc['encuentro']['dia'];
$hora = (int) $enc['encuentro']['hora'];
$dur = max(1, (int) ($enc['encuentro']['duracion_horas'] ?? 1));
$now = ((int) $pEnc['reloj']['dia_pueblo']) * 24 + (int) $pEnc['reloj']['hora_actual'];
$adv = $service->avanzarRelojPasoAPaso($pEnc, max(1, $dia * 24 + $hora + $dur - $now));
ok($adv['ok'] ?? false, 'encuentro termina');

$cotilleos = array_values(array_filter(
    $pEnc['buzon'] ?? [],
    static fn($m) => is_array($m) && ($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO
));
ok($cotilleos !== [], 'cotilleo en buzón');
$encId = (string) ($pEnc['encuentros'][0]['id'] ?? '');
$diarioEnc = DiarioEngine::entradaPorEvento($pEnc, $encId);
ok($diarioEnc !== null, 'encuentro → entrada diario');
ok(
    in_array($ida, $diarioEnc['actores'] ?? [], true) && in_array($idb, $diarioEnc['actores'] ?? [], true),
    'actores del encuentro en diario'
);
ok(($diarioEnc['_placeholder_contenido'] ?? true) === false, 'sin placeholder en diario encuentro');

// --- 2) Hito relacional → diario ---
$pHito = $service->nuevaPartida('juego_v1', 'diario-narr-hito');
$ids = array_keys($pHito['residentes']);
ok(count($ids) >= 2, 'dos residentes');
$a = (string) $ids[0];
$b = (string) $ids[1];
RelacionBitacora::registrar($pHito, RelacionBitacora::SE_CONOCIERON, [$a, $b]);
$clave = RelacionBitacora::SE_CONOCIERON . ':' . implode('|', [$a < $b ? $a : $b, $a < $b ? $b : $a]);
$diarioHito = DiarioEngine::entradaPorEvento($pHito, $clave);
ok($diarioHito !== null, 'hito se_conocieron → diario');
ok(in_array($a, $diarioHito['actores'] ?? [], true), 'actor A en diario hito');

// --- 3) Acontecimiento vida con cotilleo → diario ---
$pVida = $service->nuevaPartida('playtest_01', 'diario-narr-vida');
$pVida['features']['buzon_enabled'] = true;
$store = (new Catalog($root))->store();
$rid = null;
foreach ($pVida['residentes'] as $id => $res) {
    $oc = (string) ($res['runtime']['ocupacion'] ?? '');
    if ($oc !== '' && $oc !== 'desempleado' && $oc !== 'jubilado' && $oc !== 'ninguna') {
        $rid = (string) $id;
        break;
    }
}
ok($rid !== null, 'empleado para perder_trabajo');
$rVida = AcontecimientoDiario::ejecutar($pVida, 'perder_trabajo', [$rid], $store, $cal);
ok($rVida['ok'] ?? false, 'perder_trabajo ejecuta');
$diarioVida = DiarioEngine::entradaPorEvento($pVida, 'perder_trabajo');
$tieneCotilleo = array_filter(
    $pVida['buzon'] ?? [],
    static fn($m) => is_array($m) && ($m['tipo'] ?? '') === 'acontecimiento_perder_trabajo'
);
if ($diarioVida !== null) {
    ok(in_array($rid, $diarioVida['actores'] ?? [], true), 'residente vida en actores');
    ok(trim((string) ($diarioVida['texto'] ?? '')) !== '', 'texto diario vida no vacío');
} else {
  $cot = array_filter(
      $pVida['buzon'] ?? [],
      static fn($m) => is_array($m) && ($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO
          && ($m['tipo'] ?? '') === 'acontecimiento_perder_trabajo'
  );
  if ($cot !== []) {
      ok(false, 'cotilleo perder_trabajo sin espejo diario');
  } else {
      ok(true, 'perder_trabajo sin cotilleo (canal mensajito): diario omitido OK');
  }
}

// --- 4–6) listarPorResidente y no implicados ---
$porA = DiarioEngine::listarPorResidente($pHito, $a);
$porB = DiarioEngine::listarPorResidente($pHito, $b);
ok(count($porA) >= 1 && count($porB) >= 1, 'listarPorResidente devuelve entradas a implicados');
if (count($ids) >= 3) {
    $c = (string) $ids[2];
    ok(count(DiarioEngine::listarPorResidente($pHito, $c)) === 0, 'vecino no implicado sin entradas');
}

// --- 7) Idempotencia mirror ---
$pIdem = $service->nuevaPartida('juego_v1', 'diario-narr-idem');
$pIdem['features']['buzon_enabled'] = true;
$msg = [
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'cotilleo',
    'texto' => 'Prueba idempotencia diario.',
    'actores' => [(string) array_key_first($pIdem['residentes'])],
    'origen' => [
        'evento_id' => 'test_idem_diario_evt',
        'tipo_evento' => 'test',
        'es_narrativo' => false,
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
];
$r1 = BuzonEngine::crear($pIdem, $msg);
DiarioNarrativaBridge::mirrorCotilleoBuzon($pIdem, $r1);
$nAntes = count($pIdem['diario'] ?? []);
DiarioNarrativaBridge::mirrorCotilleoBuzon($pIdem, $r1);
ok(count($pIdem['diario'] ?? []) === $nAntes, 'segundo mirror no duplica');

// --- 8) Vista global sin doble copia buzón + diario ---
$pDup = $service->nuevaPartida('playtest_01', 'diario-narr-dup');
$pDup['features']['buzon_enabled'] = true;
$enc2 = $service->programarEncuentro($pDup, [$ida, $idb], 1, 18, 'conocerse', 'lug_parque');
$dia2 = (int) $enc2['encuentro']['dia'];
$hora2 = (int) $enc2['encuentro']['hora'];
$dur2 = max(1, (int) ($enc2['encuentro']['duracion_horas'] ?? 1));
$now2 = ((int) $pDup['reloj']['dia_pueblo']) * 24 + (int) $pDup['reloj']['hora_actual'];
$service->avanzarRelojPasoAPaso($pDup, max(1, $dia2 * 24 + $hora2 + $dur2 - $now2));
$coti = VistaCotilleoV3::de($pDup);
$textosHoy = array_map(static fn($e) => (string) ($e['texto'] ?? ''), $coti['hoy'] ?? []);
$nDiario = count($pDup['diario'] ?? []);
ok($nDiario >= 1, 'diario persistido tras encuentro');
$unicos = array_unique($textosHoy);
ok(count($textosHoy) === count($unicos), 'cotilleo global sin duplicar texto por buzón+diario');

// --- 9) No placeholder sin narrativa ---
$pPh = $service->nuevaPartida('juego_v1', 'diario-narr-ph');
$antesPh = count($pPh['diario'] ?? []);
DiarioNarrativaBridge::desdeMensaje($pPh, [
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'cotilleo',
    'texto' => '',
    '_placeholder_contenido' => false,
]);
DiarioNarrativaBridge::desdeMensaje($pPh, [
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'cotilleo',
    'texto' => 'Ruido técnico',
    '_placeholder_contenido' => true,
]);
ok(count($pPh['diario'] ?? []) === $antesPh, 'no entradas placeholder / sin texto');

// --- 10) Cotilleo sigue en buzón ---
ok(count($cotilleos) >= 1, 'cotilleo previo intacto en buzón');

echo $failures === 0 ? "OK diario_narrativa_bridge\n" : "FAIL diario_narrativa_bridge ({$failures})\n";
exit($failures > 0 ? 1 : 0);
