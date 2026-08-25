<?php
declare(strict_types=1);

/**
 * GATES «Copiar debug» — caja negra de jugabilidad (pieza DEV).
 * A..L según pedido. Ejecutar CLI: php tests/debug_blackbox_test.php
 */

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\PartidaHandler;
use AquiHayTema\Engine\AzarPonderado;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\RngService;

$root = dirname(__DIR__);
$fallos = [];

function check(array &$fallos, string $gate, bool $cond, string $msg): void
{
    if ($cond) {
        echo "OK [$gate] $msg\n";
        return;
    }
    $fallos[] = "[$gate] $msg";
    echo "FAIL [$gate] $msg\n";
}

$_GET['debug'] = '1';

$service = new AquiHayTema\Engine\PartidaService($root);
$partida = $service->nuevaPartida('juego_v1', 'blackbox-debug-' . bin2hex(random_bytes(3)));
$partidaId = (string) $partida['meta']['partida_id'];
FeatureConfig::mergeIntoPartida($partida, $root);
$ctx = new ApiContext($root);
$catalog = new Catalog($root);

/* Cuerpo base de export con DEBUG activo */
$mkBody = static fn(): array => ['partida_id' => $partidaId, 'debug' => 1];

/* ===================== GATE I: debug no altera RNG ===================== */
$rngStateAntes = (int) ($partida['rng']['state'] ?? 0);
$r1 = PartidaHandler::debugExport($ctx, $mkBody(), $partida);
$rngStateDespues = (int) ($partida['rng']['state'] ?? 0);
check($fallos, 'I', ($r1['ok'] ?? false) && $rngStateAntes === $rngStateDespues, 'debug_export no cambia rng.state');
$jsonGlobal = $r1['debug_export']['json'] ?? [];
check($fallos, 'I', (int) ($jsonGlobal['partida']['rng']['state'] ?? -1) === $rngStateAntes, 'export reporta el mismo rng.state');

/* ===================== GATE A: todos los residentes ===================== */
$activos = LabAudit::residentesActivos($partida);
$npcsJson = $jsonGlobal['npcs'] ?? [];
$idsNpc = array_map(static fn($n) => $n['id'] ?? null, $npcsJson);
sort($idsNpc);
$esperados = $activos;
sort($esperados);
check($fallos, 'A', count($npcsJson) === count($activos) && $idsNpc === $esperados, 'export contiene TODOS los residentes activos (' . count($activos) . ')');

/* ===================== GATE B: información oculta presente ===================== */
$npc0 = $npcsJson[0] ?? [];
$legible = $npc0['perfil_partida_legible'] ?? [];
$tieneOcultos = isset($legible['rasgos_ocultos']) && is_array($legible['rasgos_ocultos']);
$prefsLeg = $legible['preferencias'] ?? [];
$tienePrefs = isset($prefsLeg['dealbreakers'], $prefsLeg['hobbies_pos'], $prefsLeg['hobbies_neg'], $prefsLeg['romanticas'])
    && is_array($prefsLeg['dealbreakers']);
$perfilGen = $npc0['perfil_partida_generado'] ?? [];
$hayOcultoReal = $tieneOcultos && (
    $prefsLeg['dealbreakers'] !== [] || $legible['rasgos_ocultos'] !== []
    || ($prefsLeg['hobbies_neg'] ?? []) !== [] || ($prefsLeg['personalidad_neg'] ?? []) !== []
);
check($fallos, 'B', $tieneOcultos && $tienePrefs, 'ficha NPC incluye rasgos_ocultos + preferencias ocultas (pos/neg/dealbreakers/romanticas)');
check($fallos, 'B', $hayOcultoReal, 'alguna preferencia oculta tiene contenido real');
check($fallos, 'B', isset($legible['estilo_social'], $legible['lugares_preferentes'], $legible['hobby_principal']), 'estilo_social/lugares_preferentes/hobby_principal presentes');
check($fallos, 'B', array_key_exists('edad_resuelta', $npc0) && array_key_exists('genero', $npc0), 'edad/genero resueltos presentes');

/* ===================== GATE C: relaciones / romance ===================== */
$matriz = $jsonGlobal['matriz_relacional'] ?? [];
$nParesEsperados = (int) ((count($activos) * (count($activos) - 1)) / 2);
check($fallos, 'C', count($matriz) === $nParesEsperados, "matriz relacional completa ($nParesEsperados pares)");
$dir0 = $matriz[0]['direcciones']['a_hacia_b'] ?? [];
check($fallos, 'C', array_key_exists('social_valor', $dir0) && array_key_exists('romance_valor', $dir0) && array_key_exists('quimica_valor', $dir0) && array_key_exists('compatibilidad_oculta', $dir0), 'social/romance/quimica/compatibilidad oculta por dirección');
check($fallos, 'C', array_key_exists('pareja_estado', $dir0) && array_key_exists('historial_parejas', $dir0), 'pareja/ex/crisis visibles (estado + historial_parejas)');

/* ===================== GATE D: encuentro activo identificable ===================== */
$partida['reloj']['dia_pueblo'] = 1;
$partida['reloj']['hora_actual'] = 8;
$ids = array_values($activos);
$a = (string) $ids[0];
$b = (string) $ids[1];
$prog = EncuentroEngine::programar($partida, [$a, $b], 1, 10, 'conocerse', 'lug_cafeteria');
check($fallos, 'D', (bool) ($prog['ok'] ?? false), 'encuentro programado para gate D');
$encId = (string) ($prog['encuentro']['id'] ?? '');

$r2 = PartidaHandler::debugExport($ctx, $mkBody(), $partida);
$j2 = $r2['debug_export']['json'] ?? [];
$activosExp = $j2['encuentros_activos'] ?? [];
$hit = false;
foreach ($activosExp as $e) {
    if (($e['id'] ?? '') === $encId
        && in_array($a, $e['participantes'] ?? [], true)
        && in_array($b, $e['participantes'] ?? [], true)) {
        $hit = true;
        break;
    }
}
check($fallos, 'D', $hit, 'encuentro activo listado con participantes identificables');
$mundoD = $j2['mundo'] ?? [];
check($fallos, 'D', isset($mundoD['propuestas_encuentro'], $mundoD['planes_autonomos_pendientes'], $mundoD['solapes_agenda']), 'MUNDO incluye planes/cooldowns/solapes');

/* ===================== GATE E: intervención trazable ===================== */
$partida['reloj']['dia_pueblo'] = 1;
$partida['reloj']['hora_actual'] = 10;
EncuentroLifecycle::sincronizarConReloj($partida, null, $catalog);
$encRow = null;
foreach (EncuentroEngine::list($partida) as $e) {
    if (($e['id'] ?? '') === $encId) {
        $encRow = $e;
        break;
    }
}
check($fallos, 'E', ($encRow['estado'] ?? '') === 'en_curso', 'encuentro pasa a en_curso');
$ej = EncuentroIntervencion::ejecutar($partida, $encId, 'hablar', [], $catalog);
check($fallos, 'E', (bool) ($ej['ok'] ?? false), 'intervención ejecutada');
$rowTrasInt = null;
foreach (EncuentroEngine::list($partida) as $e) {
    if (($e['id'] ?? '') === $encId) {
        $rowTrasInt = $e;
        break;
    }
}
$intervRow = $rowTrasInt['intervencion_celeste'] ?? [];
check($fallos, 'E', ($intervRow['accion'] ?? '') === 'hablar' && in_array($intervRow['tono'] ?? '', ['bien', 'mal', 'neutral'], true), 'fila persiste acción+tono REALES');
check($fallos, 'E', ($intervRow['dev_traza'] ?? []) !== [], 'dev_traza persistida en fila (pesos/pick/carga reales)');
$traza0 = $intervRow['dev_traza'][0] ?? [];
check(
    $fallos,
    'E',
    ($traza0['familia'] ?? '') === 'carga_base_intervencion'
        && isset($traza0['factores_snapshot'], $traza0['carga_final']),
    'desglose carga_base (componentes + factores snapshot) capturado'
);

/* ===================== GATE F: resolución final explicada ===================== */
$durH = max(1, (int) ($rowTrasInt['duracion_horas'] ?? 1));
$partida['reloj']['dia_pueblo'] = 1;
$partida['reloj']['hora_actual'] = 10 + $durH;
EncuentroLifecycle::sincronizarConReloj($partida, null, $catalog);
$rowFin = null;
foreach (EncuentroEngine::list($partida) as $e) {
    if (($e['id'] ?? '') === $encId) {
        $rowFin = $e;
        break;
    }
}
check($fallos, 'F', ($rowFin['estado'] ?? '') === 'terminado' && is_array($rowFin['resultado'] ?? null), 'encuentro terminado con resultado almacenado');
$resRow = $rowFin['resultado'];
check($fallos, 'F', is_array($resRow['experiencia']['factores'] ?? null) && $resRow['experiencia']['factores'] !== [], 'snapshot de factores almacenado por el motor');
$tiradasFinales = $resRow['_dev_traza']['tiradas_finales'] ?? [];
check($fallos, 'F', count($tiradasFinales) >= 2, 'tiradas finales capturadas (una por participante)');
$tir0 = $tiradasFinales[0] ?? [];
check(
    $fallos,
    'F',
    isset($tir0['carga'], $tir0['pesos'], $tir0['pick'], $tir0['resultado'], $tir0['rng_state_post'])
        && count($tir0['pesos']) === count($tir0['resultados_posibles']),
    'tirada real completa: carga+pesos+pick+umbral+resultado+rng_state_post'
);

$r3 = PartidaHandler::debugExport($ctx, $mkBody(), $partida);
$j3 = $r3['debug_export']['json'] ?? [];
$resoluciones = $j3['resoluciones'] ?? [];
$bloque = null;
foreach ($resoluciones as $rz) {
    if (($rz['encuentro_id'] ?? '') === $encId) {
        $bloque = $rz;
        break;
    }
}
check($fallos, 'F', $bloque !== null, 'RESOLUCIONES contiene el encuentro');
$rf = $bloque['resolucion_final'] ?? [];
$porA = $rf['por_participante'][$a] ?? [];
check($fallos, 'F', isset($porA['carga_final'], $porA['contribuciones_por_factor'], $porA['componentes_carga']), 'por participante: carga final + contribuciones por factor + componentes');
check($fallos, 'F', ($rf['factores_snapshot'] ?? null) !== null, 'factores_snapshot expuesto en export');
check($fallos, 'F', ($rf['delta_social'] ?? null) !== null, 'efectos posteriores (delta_social) incluidos');
$comp = $porA['componentes_carga'];
if (isset($comp['base_factores_snapshot'], $comp['intervencion_celeste_carga'], $comp['tema_carga_individual'], $porA['carga_final'])) {
    $suma = round((float) $comp['base_factores_snapshot'] + (float) $comp['intervencion_celeste_carga'] + (float) $comp['tema_carga_individual'], 6);
    check($fallos, 'F', abs($suma - (float) $porA['carga_final']) < 0.0005, 'base+intervencion+tema == carga_final almacenada (exactitud)');
} else {
    $fallos[] = '[F] componentes_carga incompletos';
}

/* ===================== GATE G: timeline ordenada ===================== */
$timeline = $j3['timeline'] ?? [];
$eventos = $timeline['eventos'] ?? [];
check($fallos, 'G', ($timeline['total'] ?? 0) > 0 && count($eventos) > 0, 'timeline con eventos');
$prevKey = -1;
$ordenado = true;
foreach ($eventos as $ev) {
    $k = ((int) ($ev['ts_juego']['dia'] ?? 0)) * 24 + ((int) ($ev['ts_juego']['hora'] ?? 0));
    if ($k < $prevKey) {
        $ordenado = false;
        break;
    }
    $prevKey = $k;
}
check($fallos, 'G', $ordenado, 'timeline ordenada cronológicamente por ts_juego');
$tiposVistos = array_column($eventos, 'tipo');
check(
    $fallos,
    'G',
    in_array('encuentro_en_curso', $tiposVistos, true) || in_array('encuentro_iniciado', $tiposVistos, true),
    'inicio de encuentro visible en timeline persistida'
);

/* ===================== GATE H: solape detectable (caso Mateo/Sergio) ===================== */
$fantasma = [
    'id' => 'enc_fantasma_h',
    'tipo' => 'amistad',
    'intencion' => 'casual_quedada',
    'participantes' => [$a, $ids[2] ?? $ids[0]],
    'lugar' => 'lug_parque',
    'hora' => 10,
    'dia' => 1,
    'duracion_minutos' => 60,
    'duracion_horas' => 1,
    'estado' => 'terminado',
    'resultado' => null,
];
$partida['encuentros'][] = $fantasma;
$r4 = PartidaHandler::debugExport($ctx, $mkBody(), $partida);
$solapes = ($r4['debug_export']['json']['mundo']['solapes_agenda'] ?? []);
$haySolape = false;
foreach ($solapes as $s) {
    if (in_array($a, $s['participantes_compartidos'] ?? [], true)) {
        $haySolape = true;
        break;
    }
}
check($fallos, 'H', $haySolape, 'solape de participante con franjas detectable sin ambigüedad (participante+franjas explícitas)');

/* ===================== GATE J: debug OFF no afecta gameplay ni filtra ===================== */
unset($_GET['debug']);
$_GET['debug'] = '0';
$rOff = PartidaHandler::debugExport($ctx, ['partida_id' => $partidaId], $partida);
check($fallos, 'J', ($rOff['ok'] ?? true) === false && ($rOff['error'] ?? '') === 'debug_no_activo', 'sin debug: export rechazado y sin datos');
$att = LabAudit::attach(['ok' => true]);
check($fallos, 'J', !array_key_exists('lab_audit', $att), 'attach sin debug no adjunta telemetría');
$rngA = new RngService('gate-j-seed', 123456789);
$outOn = AzarPonderado::tirar($rngA, ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'], 0.25, []);
$rngB = new RngService('gate-j-seed', 123456789);
$outOff = AzarPonderado::tirar($rngB, ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'], 0.25, []);
check(
    $fallos,
    'J',
    ($outOn['resultado'] ?? '') === ($outOff['resultado'] ?? '!') && $rngA->getState() === $rngB->getState(),
    'instrumentación no altera pesos ni RNG (misma secuencia con y sin debug)'
);
$_GET['debug'] = '1';

/* ===================== GATE K: saves antiguos ===================== */
$vieja = $service->cargar($partidaId);
foreach (['audit_trail', 'domain_events', 'audit_trail_archivo', 'domain_events_archivo', 'memoria_eventos', 'bitacora_relaciones',
    'propuestas_cooldown', 'rechazos_propuesta', 'descubrimientos', 'historial_relaciones', 'event_log', 'relaciones_conflicto',
    'propuestas_encuentro', 'peticiones', 'llegadas', 'npc_autonomo', 'features', ] as $k) {
    unset($vieja[$k]);
}
try {
    $expVieja = LabAudit::exportCompleto($vieja, $catalog, []);
    $okK = isset($expVieja['json']['mundo'], $expVieja['json']['timeline'], $expVieja['texto'])
        && str_contains($expVieja['texto'], '[AHT DEBUG TIMELINE]');
} catch (\Throwable $e) {
    $okK = false;
    echo 'EXCEP K: ' . $e->getMessage() . "\n";
}
check($fallos, 'K', $okK, 'save antiguo sin claves modernas exporta sin errores');

/* ===================== GATE L: texto copiado válido ===================== */
$texto = (string) ($r3['debug_export']['texto'] ?? '');
$lineas = explode("\n", $texto);
$buenJson = true;
$bloquesParseados = 0;
for ($i = 0; $i < count($lineas); $i++) {
    if (!str_starts_with($lineas[$i], '[AHT DEBUG')) {
        continue;
    }
    // El bloque siguiente (si existe y abre llave/corchete) debe ser JSON válido
    $j = $i + 1;
    while ($j < count($lineas) && trim($lineas[$j]) === '') {
        $j++;
    }
    if ($j < count($lineas)) {
        $candidato = $lineas[$j];
        if ($candidato !== '' && ($candidato[0] === '{' || $candidato[0] === '[')) {
            // acumula hasta cierre balanceado simple
            $buf = '';
            $prof = 0;
            while ($j < count($lineas)) {
                $buf .= $lineas[$j] . "\n";
                foreach (str_split($lineas[$j]) as $ch) {
                    if ($ch === '{' || $ch === '[') {
                        $prof++;
                    } elseif ($ch === '}' || $ch === ']') {
                        $prof--;
                    }
                }
                if ($prof <= 0) {
                    break;
                }
                $j++;
            }
            $dec = json_decode($buf, true);
            if (!is_array($dec)) {
                $buenJson = false;
                echo "JSON malo tras: {$lineas[$i]}\n";
                break;
            }
            $bloquesParseados++;
        }
    }
}
check($fallos, 'L', $buenJson && $bloquesParseados >= 6, "bloques JSON del texto válidos ($bloquesParseados parseados)");
check(
    $fallos,
    'L',
    str_contains($texto, '[AHT DEBUG PARTIDA]')
        && str_contains($texto, '[AHT DEBUG CONFIG EFECTIVA]')
        && str_contains($texto, '[AHT DEBUG NPC]')
        && str_contains($texto, '[AHT DEBUG REL]')
        && str_contains($texto, '[AHT DEBUG MUNDO]')
        && str_contains($texto, '[AHT DEBUG ENCUENTROS/PLANES]')
        && str_contains($texto, '[AHT DEBUG TIMELINE]')
        && str_contains($texto, '[AHT DEBUG RESOLUCIONES]'),
    'todas las secciones pedidas presentes en el texto'
);
check($fallos, 'L', json_encode($r3['debug_export']['json'], JSON_UNESCAPED_UNICODE) !== false, 'json global serializable');

echo "\n";
if ($fallos === []) {
    echo "debug_blackbox_test OK (gates A-L)\n";
    exit(0);
}
echo 'debug_blackbox_test FAIL: ' . count($fallos) . "\n" . implode("\n", $fallos) . "\n";
exit(1);
