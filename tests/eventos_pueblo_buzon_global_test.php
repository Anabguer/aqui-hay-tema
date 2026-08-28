<?php
declare(strict_types=1);

/**
 * Buzon global: partida nueva completa B1/B2/B3 sin overrides Neni.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\LugaresCanonicos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;

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
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$defaults = FeatureConfig::defaults($root);

ok(($defaults['buzon_enabled'] ?? false) === true, 'global buzon_enabled=true en features.json');
ok(($defaults['mensajitos_espontaneos_enabled'] ?? false) === true, 'espontaneos activos globalmente');
ok((bool) CalibracionConfig::get($cal, 'eventos_pueblo.activo', false), 'eventos_pueblo.activo global');

$items = EventosPuebloEngine::catalogItems($catalog);
ok(count($items) === 6, 'catalogo sigue con 6 eventos');

// Partida nueva real: solo flags (sin overrides manuales)
$svc = new PartidaService($root);
$seed = 'buzon-global-flags-' . gmdate('YmdHis');
$nueva = $svc->nuevaPartida('debug_v0', $seed);
ok(!($nueva['features']['eventos_pueblo_enabled'] ?? false), 'partida nueva sin override eventos_pueblo_enabled');
ok(FeatureConfig::isEnabled($nueva, 'buzon_enabled'), 'partida nueva buzon por default global');
ok(FeatureConfig::isEnabled($nueva, 'mensajitos_espontaneos_enabled'), 'partida nueva con espontaneos por default global');
ok(EventosPuebloEngine::activa($nueva, $cal), 'partida nueva eventos activos por calibracion');

/**
 * Circuito B1/B2/B3: mismos defaults globales, mundo controlado para planificar.
 *
 * @return array<string, mixed>
 */
function partidaConDefaultsGlobales(string $root): array
{
    $pool = [
        'a' => 'Ana', 'b' => 'Bruno', 'c' => 'Carla', 'd' => 'David',
        'e' => 'Elena', 'f' => 'Fran', 'g' => 'Gema', 'h' => 'Hugo',
        'i' => 'Ines', 'j' => 'Jorge', 'k' => 'Kiko', 'l' => 'Lola',
    ];
    $residentes = [];
    foreach ($pool as $id => $nombre) {
        $residentes[$id] = [
            'identidad_publica' => ['nombre' => $nombre],
            'presencia' => 'residente',
            'runtime' => [],
        ];
    }
    $p = [
        'reloj' => ['dia_pueblo' => 8, 'hora_actual' => 8],
        'rng' => ['seed' => 'buzon-global-circuit', 'state' => 1],
        'meta' => ['seed' => 'buzon-global-circuit'],
        'features' => [],
        'residentes' => $residentes,
        'celeste' => [
            'lugares_desbloqueados' => LugaresCanonicos::todos(),
            'intervenciones_organizadas_usadas_hoy' => 0,
            'intervenciones_organizadas_max_dia' => 1,
        ],
        'encuentros' => [],
        'buzon' => [],
    ];
    FeatureConfig::mergeIntoPartida($p, $root);
    return $p;
}

$p = partidaConDefaultsGlobales($root);
ok(!($p['features']['eventos_pueblo_enabled'] ?? false), 'circuito sin override eventos_pueblo_enabled');
ok(FeatureConfig::isEnabled($p, 'buzon_enabled'), 'circuito buzon por default global');
ok(EventosPuebloEngine::activa($p, $cal), 'circuito eventos activos global');

$eventoId = 'club_lectura';
$st = -1;
for ($i = 1; $i <= 12000; $i++) {
    $px = $p;
    $px['rng']['state'] = $i;
    $r = EventosPuebloEngine::planificar(
        $px,
        $eventoId,
        $cal,
        RngService::fromPartida($px),
        $catalog
    );
    if (!empty($r['ok'])) {
        $p = $px;
        $st = $i;
        break;
    }
}
ok($st > 0, 'B1 club_lectura planifica');

$anuncios = 0;
foreach ($p['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'anuncio_evento_pueblo') {
        $anuncios++;
        ok((string) ($m['datos_familia']['evento_pueblo_catalogo_id'] ?? '') === $eventoId, 'B2 anuncio catalogo club_lectura');
        ok((string) ($m['canal'] ?? BuzonEngine::CANAL_BUZON) === BuzonEngine::CANAL_BUZON, 'B2 anuncio en canal buzon');
    }
}
ok($anuncios === 1, 'B2 exactamente un anuncio');

$vista = EventosPuebloEngine::vistaProximoEvento($p, $catalog);
ok(($vista['catalogo_id'] ?? '') === $eventoId, 'B3 proximo club_lectura');
ok(($vista['icono'] ?? '') === '📚', 'B3 icono club');

$evRow = $p['eventos_pueblo']['programados'][0] ?? null;
ok(is_array($evRow), 'fila evento programada');
$evtId = (string) ($evRow['id'] ?? '');
ok($evtId !== '', 'evento tiene id');
ok((string) ($evRow['encuentro_id'] ?? '') === '', 'sin encuentro hasta hora del evento');

$p['reloj'] = [
    'dia_pueblo' => (int) ($evRow['dia'] ?? 8),
    'hora_actual' => (int) ($evRow['hora'] ?? 11),
];
EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);

$encId = (string) ($p['eventos_pueblo']['programados'][0]['encuentro_id'] ?? '');
$enc = null;
foreach ($p['encuentros'] as $e) {
    if (($e['id'] ?? '') === $encId) {
        $enc = $e;
        break;
    }
}
ok($enc !== null, 'encuentro evento_pueblo existe tras fallback autonomo');

if ($enc !== null) {
    $diaFin = (int) ($enc['dia'] ?? 8);
    $horaFin = (int) ($enc['hora'] ?? 11) + max(1, (int) ($enc['duracion_horas'] ?? 2));
    while ($horaFin >= 24) {
        $horaFin -= 24;
        $diaFin++;
    }
    $p['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin];
    EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
}

$cierres = 0;
foreach ($p['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'cierre_evento_pueblo') {
        $cierres++;
    }
}
ok($cierres === 1, 'B2 exactamente un cierre');
ok(EventosPuebloEngine::proximoEvento($p, $catalog) === null, 'B3 sin proximo tras cierre');

$activos = 0;
foreach ($p['eventos_pueblo']['programados'] ?? [] as $ev) {
    if (!is_array($ev)) {
        continue;
    }
    if (in_array(EventosPuebloEngine::estadoEvento($p, $ev), ['programado', 'en_curso'], true)) {
        $activos++;
    }
}
ok($activos === 0, 'sin eventos activos duplicados tras cierre');

echo $fail === 0 ? "\nOK eventos_pueblo_buzon_global_test\n" : "\nFAIL eventos_pueblo_buzon_global_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
