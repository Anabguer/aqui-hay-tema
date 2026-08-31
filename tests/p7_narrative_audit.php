<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelojOperations;

$root = dirname(__DIR__);

DomainBootstrap::boot();
$service = new PartidaService($root);

echo "P7 NARRATIVE AUDIT\n";
echo str_repeat('=', 70) . "\n\n";

$p = $service->nuevaPartida('playtest_01', 'p7-audit-01');
$reloj = new RelojOperations($root, $service->getLogger(), $service->emociones());

echo "Partida creada con seed 'p7-audit-01'\n";
$activos = CapacidadViviendas::residentesActivos($p);
echo "Residentes iniciales: " . count($activos) . " (" . implode(', ', $activos) . ")\n\n";

$tracked = array_slice($activos, 0, 4);

$dailySnapshots = [];
$characterTraces = [];
$emotionAudit = [];

foreach ($tracked as $rid) {
    $characterTraces[$rid] = [];
}

$prevCotilleoCount = 0;
$prevDiarioCount = 0;
$prevMensajitosCount = 0;

for ($day = 1; $day <= 15; $day++) {
    echo "--- Simulando dia $day ---\n";

    $result = $reloj->avanzarPasoAPaso($p, 24);

    $acceptCount = 0;
    while (($p['llegadas']['candidato_activo'] ?? null) !== null) {
        $a = CapacidadViviendas::residentesActivos($p);
        if ($a === []) break;
        $r = CandidatoLlegadaEngine::aceptar($p, $root, null, null, (string)$a[0]);
        if (!($r['ok'] ?? false)) break;
        $acceptCount++;
        $reloj->avanzarPasoAPaso($p, 1);
    }
    if ($acceptCount > 0) {
        echo "  Aceptados $acceptCount candidato(s)\n";
    }

    $snap = [];
    $snap['dia'] = $day;

    $buzonEntries = $p['buzon'] ?? [];
    $cotilleosAll = array_filter($buzonEntries, fn($m) => ($m['canal'] ?? '') === 'cotilleo');
    $cotilleosHoy = array_filter($cotilleosAll, fn($m) => (int)($m['dia'] ?? 0) === $day);
    $mensajitosAll = array_filter($buzonEntries, fn($m) => ($m['canal'] ?? '') === 'buzon' && ($m['clasificacion'] ?? '') !== 'cotilleo');

    $snap['cotilleos_buzon'] = count($cotilleosAll);
    $snap['cotilleos_nuevos_hoy'] = count($cotilleosHoy);
    $snap['diario_entradas'] = count($p['diario'] ?? []);
    $snap['diario_hitos'] = count(array_filter($p['diario'] ?? [], function ($e) {
        $tipo = (string) ($e['tipo'] ?? '');
        return str_contains($tipo, 'hito') || str_contains($tipo, 'relacional');
    }));
    $snap['mensajitos'] = count($mensajitosAll);

    $emotions = [];
    $nonNeutroCount = 0;
    foreach (CapacidadViviendas::residentesActivos($p) as $rid) {
        $emocion = $p['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? 'neutro';
        $emotions[] = $rid . ':' . $emocion;
        if ($emocion !== 'neutro') {
            $nonNeutroCount++;
        }
    }
    $snap['emociones_triggered'] = $nonNeutroCount;
    $snap['emociones_detail'] = $emotions;

    $encuentrosResueltos = array_filter($p['encuentros'] ?? [], fn($e) => ($e['estado'] ?? '') === 'terminado');
    $snap['encuentros_resueltos'] = count($encuentrosResueltos);
    $snap['memoria_eventos'] = count($p['memoria_eventos'] ?? []);
    $snap['cotilleos_new'] = $snap['cotilleos_buzon'] - $prevCotilleoCount;
    $snap['diario_new'] = $snap['diario_entradas'] - $prevDiarioCount;
    $snap['mensajitos_new'] = $snap['mensajitos'] - $prevMensajitosCount;

    $dailySnapshots[$day] = $snap;
    $prevCotilleoCount = $snap['cotilleos_buzon'];
    $prevDiarioCount = $snap['diario_entradas'];
    $prevMensajitosCount = $snap['mensajitos'];

    foreach ($tracked as $rid) {
        $trace = [];
        $trace['dia'] = $day;

        $met = [];
        foreach ($p['encuentros'] ?? [] as $enc) {
            if (($enc['estado'] ?? '') !== 'terminado') continue;
            if (!in_array($rid, $enc['participantes'] ?? [], true)) continue;
            foreach ($enc['participantes'] as $other) {
                if ($other !== $rid) $met[] = $other;
            }
        }
        $trace['met'] = array_values(array_unique($met));

        $social = [];
        foreach ($p['relaciones_sociales'] ?? [] as $rel) {
            if (($rel['persona_a'] ?? '') === $rid || ($rel['persona_b'] ?? '') === $rid) {
                $otro = ($rel['persona_a'] ?? '') === $rid ? ($rel['persona_b'] ?? '') : ($rel['persona_a'] ?? '');
                $social[] = $otro . ':' . ($rel['tipo'] ?? 'desconocido');
            }
        }
        $trace['social'] = $social;

        $romantic = [];
        foreach ($p['relaciones_romanticas'] ?? [] as $rel) {
            if (($rel['persona_a'] ?? '') === $rid || ($rel['persona_b'] ?? '') === $rid) {
                $otro = ($rel['persona_a'] ?? '') === $rid ? ($rel['persona_b'] ?? '') : ($rel['persona_a'] ?? '');
                $romantic[] = $otro . ':' . ($rel['estado_pareja'] ?? 'ninguna');
            }
        }
        $trace['romantic'] = $romantic;

        $diarioMentions = 0;
        foreach ($p['diario'] ?? [] as $entry) {
            if (in_array($rid, $entry['actores'] ?? [], true)) $diarioMentions++;
        }
        $trace['diario_mentions'] = $diarioMentions;

        $cotilleoMentions = 0;
        foreach ($p['buzon'] ?? [] as $msg) {
            if (($msg['canal'] ?? '') !== 'cotilleo') continue;
            $texto = (string) ($msg['texto'] ?? '');
            $actores = $msg['actores'] ?? [];
            if (in_array($rid, $actores, true) || str_contains($texto, $rid)) {
                $cotilleoMentions++;
            }
        }
        $trace['cotilleo_mentions'] = $cotilleoMentions;

        $emocion = $p['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? 'neutro';
        $trace['emocion'] = $emocion;

        $msgsSent = 0;
        foreach ($p['buzon'] ?? [] as $msg) {
            if (($msg['de_persona'] ?? '') === $rid && ($msg['clasificacion'] ?? '') !== 'cotilleo') $msgsSent++;
        }
        $trace['mensajitos_enviados'] = $msgsSent;

        $characterTraces[$rid][$day] = $trace;
    }

    foreach (CapacidadViviendas::residentesActivos($p) as $rid) {
        $estado = $p['residentes'][$rid]['runtime']['estado_emocional'] ?? [];
        $estadoId = EstadoEmocional::canonId((string) ($estado['id'] ?? ''));
        if ($estadoId === EstadoEmocional::NEUTRO || $estadoId === '') continue;

        $yaRegistrado = false;
        foreach ($emotionAudit as $existing) {
            if ($existing['resident_id'] === $rid && $existing['emotion'] === $estadoId) {
                $yaRegistrado = true;
                break;
            }
        }
        if ($yaRegistrado) continue;

        $origen = (string) ($estado['origen'] ?? '');
        $ctx = is_array($estado['contexto'] ?? null) ? $estado['contexto'] : [];

        $hasContexto = !empty($ctx);

        $hasDiarioEntry = false;
        foreach ($p['diario'] ?? [] as $entry) {
            if (in_array($rid, $entry['actores'] ?? [], true)) {
                $hasDiarioEntry = true;
                break;
            }
        }

        $hasCotilleo = false;
        foreach ($p['buzon'] ?? [] as $msg) {
            if (($msg['canal'] ?? '') !== 'cotilleo') continue;
            if (in_array($rid, $msg['actores'] ?? [], true)) {
                $hasCotilleo = true;
                break;
            }
        }

        $hasPistaFicha = EmocionalNarrativa::pistaFicha($estado) !== null;

        $emotionAudit[] = [
            'resident_id' => $rid,
            'emotion' => $estadoId,
            'origen' => $origen,
            'has_contexto' => $hasContexto,
            'has_diario_entry' => $hasDiarioEntry,
            'has_cotilleo' => $hasCotilleo,
            'has_pista_ficha' => $hasPistaFicha,
        ];
    }
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "## SECTION 1: SUPERFICIES NARRATIVAS\n";
echo str_repeat('=', 70) . "\n\n";

echo str_pad('Dia', 5)
    . str_pad('cotilleos_buzon', 18)
    . str_pad('cotilleos_nuevos', 18)
    . str_pad('diario_total', 14)
    . str_pad('diario_hitos', 14)
    . str_pad('mensajitos', 12)
    . str_pad('emociones', 12)
    . str_pad('encuentros', 12)
    . str_pad('memoria', 10)
    . "\n";
echo str_repeat('-', 115) . "\n";

foreach ($dailySnapshots as $day => $s) {
    echo str_pad((string)$day, 5)
        . str_pad((string)$s['cotilleos_buzon'], 18)
        . str_pad((string)$s['cotilleos_nuevos_hoy'], 18)
        . str_pad((string)$s['diario_entradas'], 14)
        . str_pad((string)$s['diario_hitos'], 14)
        . str_pad((string)$s['mensajitos'], 12)
        . str_pad((string)$s['emociones_triggered'], 12)
        . str_pad((string)$s['encuentros_resueltos'], 12)
        . str_pad((string)$s['memoria_eventos'], 10)
        . "\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "## SECTION 2: TRACE DE PERSONAJES\n";
echo str_repeat('=', 70) . "\n\n";

foreach ($tracked as $rid) {
    $nombre = $p['residentes'][$rid]['identidad_publica']['nombre'] ?? $rid;
    echo "--- $nombre ($rid) ---\n";

    for ($day = 1; $day <= 15; $day++) {
        if (!isset($characterTraces[$rid][$day])) continue;
        $t = $characterTraces[$rid][$day];

        $encuentrosHoy = $t['met'] !== [];
        $socialHoy = $t['social'] !== [];
        $romanticHoy = $t['romantic'] !== [];
        $diarioHoy = $t['diario_mentions'] > 0;
        $cotilleoHoy = $t['cotilleo_mentions'] > 0;
        $emocionHoy = $t['emocion'] !== 'neutro';
        $msgsHoy = $t['mensajitos_enviados'] > 0;

        if (!$encuentrosHoy && !$socialHoy && !$romanticHoy && !$diarioHoy && !$cotilleoHoy && !$emocionHoy && !$msgsHoy) {
            continue;
        }

        echo "  D$day:\n";
        if ($encuentrosHoy) {
            $nombresEncuentro = [];
            foreach ($t['met'] as $m) {
                $nombresEncuentro[] = $p['residentes'][$m]['identidad_publica']['nombre'] ?? $m;
            }
            echo "    Encuentros: " . implode(', ', $nombresEncuentro) . "\n";
        }
        if ($socialHoy) {
            echo "    Social: " . implode(', ', $t['social']) . "\n";
        }
        if ($romanticHoy) {
            echo "    Romantico: " . implode(', ', $t['romantic']) . "\n";
        }
        if ($diarioHoy) {
            echo "    Diario menciones: " . $t['diario_mentions'] . "\n";
        }
        if ($cotilleoHoy) {
            echo "    Cotilleo menciones: " . $t['cotilleo_mentions'] . "\n";
        }
        if ($emocionHoy) {
            echo "    Emocion: " . $t['emocion'] . "\n";
        }
        if ($msgsHoy) {
            echo "    Mensajitos enviados: " . $t['mensajitos_enviados'] . "\n";
        }
    }
    echo "\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "## SECTION 3: EMOCIONES Y CAUSAS\n";
echo str_repeat('=', 70) . "\n\n";

if ($emotionAudit === []) {
    echo "(No se registraron emociones no-neutro durante la simulacion)\n";
} else {
    foreach ($emotionAudit as $ea) {
        $nombre = $p['residentes'][$ea['resident_id']]['identidad_publica']['nombre'] ?? $ea['resident_id'];
        echo "Residente: $nombre ({$ea['resident_id']})\n";
        echo "  Emocion: {$ea['emotion']}\n";
        echo "  Origen: {$ea['origen']}\n";
        echo "  has_contexto: " . ($ea['has_contexto'] ? 'Si' : 'No') . "\n";
        echo "  has_diario_entry: " . ($ea['has_diario_entry'] ? 'Si' : 'No') . "\n";
        echo "  has_cotilleo: " . ($ea['has_cotilleo'] ? 'Si' : 'No') . "\n";
        echo "  has_pista_ficha: " . ($ea['has_pista_ficha'] ? 'Si' : 'No') . "\n";
        echo "\n";
    }
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "## SECTION 4: ROMANCE VISIBLE\n";
echo str_repeat('=', 70) . "\n\n";

$couplesFound = false;
foreach ($p['relaciones_romanticas'] ?? [] as $rel) {
    $estadoPareja = $rel['estado_pareja'] ?? 'ninguna';
    if ($estadoPareja === 'ninguna') continue;

    $a = $rel['persona_a'] ?? '';
    $b = $rel['persona_b'] ?? '';
    $aName = $p['residentes'][$a]['identidad_publica']['nombre'] ?? $a;
    $bName = $p['residentes'][$b]['identidad_publica']['nombre'] ?? $b;
    $couplesFound = true;

    echo "Pareja: $aName <-> $bName\n";
    echo "  Estado: $estadoPareja\n";

    $romanceStart = null;
    $history = $rel['historial_parejas'] ?? [];
    if (!empty($history)) {
        $first = $history[0];
        $ini = $first['inicio'] ?? null;
        if ($ini !== null) {
            $romanceStart = 'D' . ($ini['dia'] ?? '?') . ' H' . ($ini['hora'] ?? '?');
        }
    }
    echo "  Romance inicio: " . ($romanceStart ?? 'desconocido') . "\n";

    $firstDate = null;
    foreach ($p['bitacora_relaciones'] ?? [] as $h) {
        if (($h['tipo'] ?? '') === RelacionBitacora::PRIMERA_CITA) {
            $par = $h['par'] ?? [];
            sort($par);
            $check = [$a, $b];
            sort($check);
            if ($par === $check) {
                $firstDate = 'D' . ($h['fecha']['dia'] ?? '?') . ' H' . ($h['fecha']['hora'] ?? '?');
                break;
            }
        }
    }
    echo "  Primera cita: " . ($firstDate ?? 'no registrada') . "\n";

    $declaration = null;
    foreach ($p['bitacora_relaciones'] ?? [] as $h) {
        if (($h['tipo'] ?? '') === RelacionBitacora::DECLARACION) {
            $par = $h['par'] ?? [];
            sort($par);
            $check = [$a, $b];
            sort($check);
            if ($par === $check) {
                $declaration = 'D' . ($h['fecha']['dia'] ?? '?') . ' H' . ($h['fecha']['hora'] ?? '?');
                break;
            }
        }
    }
    echo "  Declaracion: " . ($declaration ?? 'no registrada') . "\n";

    $encCount = 0;
    $iniDia = $ini['dia'] ?? PHP_INT_MAX;
    foreach ($p['encuentros'] ?? [] as $enc) {
        if (($enc['estado'] ?? '') !== 'terminado') continue;
        $part = $enc['participantes'] ?? [];
        if (in_array($a, $part, true) && in_array($b, $part, true)) {
            if ((int) ($enc['dia'] ?? 0) < $iniDia) $encCount++;
        }
    }
    echo "  Encuentros antes de pareja: $encCount\n";

    $cotilleoBeforeCouple = 0;
    foreach ($p['buzon'] ?? [] as $msg) {
        if (($msg['canal'] ?? '') !== 'cotilleo') continue;
        $texto = (string) ($msg['texto'] ?? '');
        $diaMsg = (int) ($msg['dia'] ?? 0);
        if ($diaMsg < $iniDia && (str_contains($texto, $aName) || str_contains($texto, $bName))) {
            $cotilleoBeforeCouple++;
        }
    }
    echo "  Cotilleos antes de pareja: $cotilleoBeforeCouple\n";

    $diarioFirstDate = false;
    if ($firstDate !== null) {
        $fdDia = (int) substr($firstDate, 1, strpos($firstDate, ' ') - 1);
        foreach ($p['diario'] ?? [] as $entry) {
            if ((int) ($entry['dia'] ?? 0) === $fdDia && (in_array($a, $entry['actores'] ?? [], true) || in_array($b, $entry['actores'] ?? [], true))) {
                $diarioFirstDate = true;
                break;
            }
        }
    }
    echo "  Diario primera cita: " . ($diarioFirstDate ? 'Si' : 'No') . "\n";

    $clase = 'C';
    if ($firstDate !== null && $declaration !== null) $clase = 'A';
    elseif ($cotilleoBeforeCouple > 0 || $encCount > 2) $clase = 'B';
    echo "  Clasificacion: $clase (A=anticipable, B=pista, C=sorpresa)\n";
    echo "\n";
}

if (!$couplesFound) {
    echo "(No se formaron parejas durante la simulacion)\n\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "## SECTION 5: RUIDO VS SILENCIO\n";
echo str_repeat('=', 70) . "\n\n";

$hechosTotal = count($p['memoria_eventos'] ?? []);
$hechosSignificativos = 0;
foreach ($p['memoria_eventos'] ?? [] as $ev) {
    if ((int) ($ev['intensidad'] ?? 0) >= 3 || ($ev['resultado_experiencia'] ?? null) !== null) {
        $hechosSignificativos++;
    }
}

$cotilleosTotal = count(array_filter($p['buzon'] ?? [], fn($m) => ($m['canal'] ?? '') === 'cotilleo'));
$diarioTotal = count($p['diario'] ?? []);
$mensajitosTotal = count(array_filter($p['buzon'] ?? [], fn($m) => ($m['canal'] ?? '') === 'buzon' && ($m['clasificacion'] ?? '') !== 'cotilleo'));
$ratioSignalNoise = $hechosTotal > 0 ? round($hechosSignificativos / $hechosTotal, 4) : 0;

echo "hechos_sociales_total: $hechosTotal\n";
echo "hechos_significativos: $hechosSignificativos\n";
echo "cotilleos_total: $cotilleosTotal\n";
echo "diario_total: $diarioTotal\n";
echo "mensajitos_total: $mensajitosTotal\n";
echo "ratio_signal_noise: $ratioSignalNoise\n";

echo "\nEvolucion diaria:\n";
echo str_pad('Dia', 5)
    . str_pad('memoria', 10)
    . str_pad('cotilleos', 12)
    . str_pad('diario', 10)
    . str_pad('mensajitos', 12)
    . "\n";
echo str_repeat('-', 50) . "\n";
foreach ($dailySnapshots as $day => $s) {
    echo str_pad((string)$day, 5)
        . str_pad((string)$s['memoria_eventos'], 10)
        . str_pad((string)$s['cotilleos_buzon'], 12)
        . str_pad((string)$s['diario_entradas'], 10)
        . str_pad((string)$s['mensajitos'], 12)
        . "\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "## SECTION 6: CONTRADICCIONES\n";
echo str_repeat('=', 70) . "\n\n";

$terminados = array_values(array_filter(
    $p['encuentros'] ?? [],
    fn($e) => ($e['estado'] ?? '') === 'terminado'
));

$samples = array_slice($terminados, 0, 20);

if ($samples === []) {
    echo "(No hay encuentros terminados para analizar)\n";
} else {
    echo "Analizando " . count($samples) . " encuentros...\n\n";

    foreach ($samples as $enc) {
        $encId = $enc['id'] ?? '???';
        $part = $enc['participantes'] ?? [];
        $dia = $enc['dia'] ?? '?';
        $hora = $enc['hora'] ?? '?';

        $porPart = $enc['resultado']['por_participante'] ?? [];
        $worstRes = 'normal';
        $worstScore = 99;
        foreach ($porPart as $pid => $pRow) {
            $res = (string) ($pRow['resultado'] ?? 'normal');
            $score = match($res) { 'muy_bien' => 2, 'bien' => 1, 'normal' => 0, 'mal' => -1, 'muy_mal' => -2, default => 0 };
            if ($score < $worstScore) {
                $worstScore = $score;
                $worstRes = $res;
            }
        }
        $resultado = $worstRes;

        $names = [];
        foreach ($part as $pid) {
            $names[] = $p['residentes'][$pid]['identidad_publica']['nombre'] ?? $pid;
        }
        $parLabel = implode('-', $names);

        $cotilleoMatch = 'N/A';
        foreach ($p['buzon'] ?? [] as $msg) {
            if (($msg['canal'] ?? '') !== 'cotilleo') continue;
            $texto = (string) ($msg['texto'] ?? '');
            $coincideNombre = false;
            foreach ($names as $nm) {
                if (str_contains($texto, $nm)) { $coincideNombre = true; break; }
            }
            if (!$coincideNombre) continue;

            $esNegativo = in_array($resultado, ['mal', 'muy_mal'], true);
            $textoNegativo = str_contains($texto, 'polvo') || str_contains($texto, 'suelos') || str_contains($texto, 'fatal');
            $esPositivo = in_array($resultado, ['bien', 'muy_bien'], true);
            $textoPositivo = str_contains($texto, 'animad') || str_contains($texto, 'buen');

            if ($esNegativo && $textoNegativo) $cotilleoMatch = 'C0';
            elseif ($esPositivo && $textoPositivo) $cotilleoMatch = 'C0';
            elseif ($esNegativo && $textoPositivo) $cotilleoMatch = 'C1';
            elseif ($esPositivo && $textoNegativo) $cotilleoMatch = 'C1';
            else $cotilleoMatch = 'C2';
            break;
        }

        $diarioMatch = 'N/A';
        foreach ($p['diario'] ?? [] as $entry) {
            $actorMatch = false;
            foreach ($part as $pid) {
                if (in_array($pid, $entry['actores'] ?? [], true)) { $actorMatch = true; break; }
            }
            if (!$actorMatch) continue;

            $tipo = (string) ($entry['tipo'] ?? '');
            $esHitoPositivo = str_contains($tipo, 'exito') || str_contains($tipo, 'alegria') || str_contains($tipo, 'hito_positivo');
            $esHitoNegativo = str_contains($tipo, 'conflicto') || str_contains($tipo, 'tristeza') || str_contains($tipo, 'hito_negativo');
            $esNegativo = in_array($resultado, ['mal', 'muy_mal'], true);
            $esPositivo = in_array($resultado, ['bien', 'muy_bien'], true);

            if ($esNegativo && $esHitoNegativo) $diarioMatch = 'C0';
            elseif ($esPositivo && $esHitoPositivo) $diarioMatch = 'C0';
            elseif ($esNegativo && $esHitoPositivo) $diarioMatch = 'C1';
            elseif ($esPositivo && $esHitoNegativo) $diarioMatch = 'C1';
            else $diarioMatch = 'C2';
            break;
        }

        $emocionMatch = 'N/A';
        foreach ($part as $pid) {
            $emocion = $p['residentes'][$pid]['runtime']['estado_emocional']['id'] ?? 'neutro';
            if ($emocion === 'neutro') continue;

            $esNegativo = in_array($resultado, ['mal', 'muy_mal'], true);
            $esPositivo = in_array($resultado, ['bien', 'muy_bien'], true);
            $emocionNegativa = $emocion === 'triste' || $emocion === 'enfadado';
            $emocionPositiva = $emocion === 'alegre';

            if ($esNegativo && $emocionNegativa) $emocionMatch = 'C0';
            elseif ($esPositivo && $emocionPositiva) $emocionMatch = 'C0';
            elseif ($esNegativo && $emocionPositiva) $emocionMatch = 'C1';
            elseif ($esPositivo && $emocionNegativa) $emocionMatch = 'C1';
            else $emocionMatch = 'C2';
            break;
        }

        $contradictions = 0;
        if ($cotilleoMatch === 'C1' || $cotilleoMatch === 'C2') $contradictions++;
        if ($diarioMatch === 'C1' || $diarioMatch === 'C2') $contradictions++;
        if ($emocionMatch === 'C1' || $emocionMatch === 'C2') $contradictions++;

        $finalClass = 'C0';
        if ($contradictions >= 2) $finalClass = 'C3';
        elseif ($contradictions === 1) $finalClass = 'C2';

        echo "[$encId] D$dia H$hora $parLabel\n";
        echo "  resultado: $resultado\n";
        echo "  cotilleo: $cotilleoMatch | diario: $diarioMatch | emocion: $emocionMatch\n";
        echo "  -> $finalClass (0=coherente, 3=contradictorio)\n";
    }
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "## RESUMEN FINAL\n";
echo str_repeat('=', 70) . "\n\n";

echo "Dias simulados: 15\n";
echo "Residentes finales: " . count(CapacidadViviendas::residentesActivos($p)) . "\n";
echo "Total cotilleos generados: $cotilleosTotal\n";
echo "Total entradas de diario: $diarioTotal\n";
echo "Total mensajitos: $mensajitosTotal\n";
echo "Total memoria_eventos: $hechosTotal\n";
echo "Ratio senal/ruido: $ratioSignalNoise\n";
echo "Emociones registradas: " . count($emotionAudit) . "\n";

$couplesCount = 0;
foreach ($p['relaciones_romanticas'] ?? [] as $rel) {
    if (($rel['estado_pareja'] ?? 'ninguna') !== 'ninguna') $couplesCount++;
}
echo "Parejas formadas: $couplesCount\n";

$totalEncuentros = count(array_filter($p['encuentros'] ?? [], fn($e) => ($e['estado'] ?? '') === 'terminado'));
echo "Encuentros terminados: $totalEncuentros\n";

echo "\nAuditoria completada.\n";