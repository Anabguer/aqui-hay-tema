<?php
declare(strict_types=1);

/**
 * Diagnósticos post-revisión Neni (sin recalibrar p/discovery/cotilleo).
 * php dev/diagnostico_post_neni.php
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\CoincidenciasEngine;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\TutorialBucle;
use AquiHayTema\Engine\TutorialIncorporaciones;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainEvents;

$root = dirname(__DIR__);
$svc = new PartidaService($root);

function embudoPerfil(PartidaService $svc, string $root, string $perfil, int $dias, string $seed): array
{
    $p = $svc->nuevaPartida('juego_v1', $seed);
    TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_BUZON, $root);
    TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_VECINO, $root);
    TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_PLAN, $root);
    // Completar día 1 hasta ~8
    for ($i = 0; $i < 16; $i++) {
        $svc->avanzarReloj($p, 1);
    }

    $funnel = [
        'generados' => 0,
        'abiertos_vistos' => 0, // bot “ve” el buzón = procesa ese día
        'aceptados' => 0,
        'rechazados' => 0,
        'expirados' => 0,
        'llegadas_efectivas' => 0,
        'bloqueados_cooldown' => 0,
        'bloqueados_sin_hueco' => 0,
        'dias_entre_candidatos' => [],
        'dias_hasta_respuesta' => [],
        'ultimo_candidato_dia' => null,
        'pop_inicial_post_tut' => count(TutorialIncorporaciones::residentesActivos($p)),
    ];

    $ratePlan = ['activa' => 0.5, 'normal' => 0.25, 'torpe' => 0.35, 'inactiva' => 0.05][$perfil] ?? 0.2;
    // Atención al buzón: activa siempre; normal 70%; torpe 40% aceptar / a veces ignora; inactiva deja expirar
    $pAtender = ['activa' => 1.0, 'normal' => 0.85, 'torpe' => 0.55, 'inactiva' => 0.05][$perfil] ?? 0.5;
    $pAceptarSiAtiende = ['activa' => 0.85, 'normal' => 0.70, 'torpe' => 0.45, 'inactiva' => 0.50][$perfil] ?? 0.7;

    for ($d = 0; $d < $dias; $d++) {
        $dia = (int) $p['reloj']['dia_pueblo'];
        $ids = TutorialIncorporaciones::residentesActivos($p);

        if (CapacidadViviendas::huecos($p) <= 0) {
            $funnel['bloqueados_sin_hueco']++;
        }
        if ($dia < (int) ($p['llegadas']['cooldown_hasta_dia'] ?? 0)
            && ($p['llegadas']['candidato_activo'] ?? null) === null
            && ($p['llegadas']['en_camino'] ?? null) === null
        ) {
            $funnel['bloqueados_cooldown']++;
        }

        // Detectar nuevo candidato
        $cand = $p['llegadas']['candidato_activo'] ?? null;
        if (is_array($cand) && (($cand['_contado'] ?? false) !== true)) {
            $funnel['generados']++;
            $p['llegadas']['candidato_activo']['_contado'] = true;
            if ($funnel['ultimo_candidato_dia'] !== null) {
                $funnel['dias_entre_candidatos'][] = $dia - $funnel['ultimo_candidato_dia'];
            }
            $funnel['ultimo_candidato_dia'] = $dia;
            $cand = $p['llegadas']['candidato_activo'];
        }

        if (is_array($cand)) {
            $atiende = (mt_rand() / mt_getrandmax()) < $pAtender;
            if ($atiende) {
                $funnel['abiertos_vistos']++;
                $funnel['dias_hasta_respuesta'][] = $dia - (int) ($cand['dia_oferta'] ?? $dia);
                if ((mt_rand() / mt_getrandmax()) < $pAceptarSiAtiende) {
                    CandidatoLlegadaEngine::aceptar($p, $root);
                    $funnel['aceptados']++;
                    $esp = (int) ($p['llegadas']['en_camino']['espera_minutos'] ?? 3);
                    CandidatoLlegadaEngine::avanzarMinutosReloj($p, $esp);
                    $before = count(TutorialIncorporaciones::residentesActivos($p));
                    CandidatoLlegadaEngine::tick($p, $root, null, 1);
                    if (count(TutorialIncorporaciones::residentesActivos($p)) > $before) {
                        $funnel['llegadas_efectivas']++;
                    }
                } else {
                    CandidatoLlegadaEngine::rechazar($p, $root);
                    $funnel['rechazados']++;
                }
            }
            // si no atiende, puede expirar en tick del reloj
        }

        // planes
        if (count($ids) >= 2 && (mt_rand() / mt_getrandmax()) < $ratePlan) {
            $a = $ids[array_rand($ids)];
            $b = $ids[array_rand($ids)];
            if ($a !== $b) {
                PropuestaEncuentroEngine::proponer($p, [$a, $b], $dia, 14, 'conocerse', 'lug_cafeteria');
            }
        }

        $histAntes = count($p['llegadas']['historial'] ?? []);
        $svc->avanzarReloj($p, 24);
        foreach (array_slice($p['llegadas']['historial'] ?? [], $histAntes) as $h) {
            if (($h['resultado'] ?? '') === CandidatoLlegadaEngine::ESTADO_EXPIRADO) {
                $funnel['expirados']++;
            }
            if (($h['resultado'] ?? '') === CandidatoLlegadaEngine::ESTADO_LLEGADO) {
                // puede duplicar si ya contamos arriba; solo contar si no vía aceptar same-day
            }
        }
    }

    $funnel['pop_final'] = count(TutorialIncorporaciones::residentesActivos($p));
    $funnel['tasa_aceptacion_si_visto'] = $funnel['abiertos_vistos'] > 0
        ? round($funnel['aceptados'] / $funnel['abiertos_vistos'], 3) : null;
    $funnel['dias_entre_mediana'] = mediana($funnel['dias_entre_candidatos']);
    $funnel['perfil'] = $perfil;
    $funnel['dias'] = $dias;
    $funnel['seed'] = $seed;
    return $funnel;
}

function mediana(array $xs): ?float
{
    if ($xs === []) {
        return null;
    }
    sort($xs);
    $n = count($xs);
    $m = (int) floor($n / 2);
    return $n % 2 ? (float) $xs[$m] : ($xs[$m - 1] + $xs[$m]) / 2.0;
}

function diagCotilleo(PartidaService $svc, string $root, int $pop, int $dias): array
{
    $p = $svc->nuevaPartida('playtest_01', 'coti-' . $pop);
    if ($pop > 16) {
        CapacidadViviendas::abrirBloque($p, 'b');
    }
    // rellenar
    $ops = new \AquiHayTema\Engine\ResidenteOperations(new \AquiHayTema\Engine\Catalog($root));
    foreach ((new \AquiHayTema\Engine\Catalog($root))->listPersonajeIds() as $id) {
        if (count(TutorialIncorporaciones::residentesActivos($p)) >= $pop) {
            break;
        }
        if ($id === 'per_qa_valid' || isset($p['residentes'][$id])) {
            continue;
        }
        if (CapacidadViviendas::huecos($p) <= 0) {
            break;
        }
        $ops->incorporarCatalogo($p, $id, 'residente');
    }
    while (count(TutorialIncorporaciones::residentesActivos($p)) < $pop && CapacidadViviendas::huecos($p) > 0) {
        $r = $ops->crearPlaceholderDev($p);
        if (!($r['ok'] ?? false)) {
            break;
        }
    }

    $coincidencias = 0;
    $dignas = 0;
    $descartadas_patron = 0;
    $publicados = 0;
    $cal = CalibracionConfig::load($root);

    for ($d = 0; $d < $dias; $d++) {
        $antesHist = count($p['historial_coincidencias'] ?? []);
        $antesCoti = 0;
        foreach (BuzonEngine::listar($p) as $m) {
            if (($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO || ($m['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO) {
                $antesCoti++;
            }
        }
        $svc->avanzarRelojPasoAPaso($p, 24);
        $nuevas = array_slice($p['historial_coincidencias'] ?? [], $antesHist);
        foreach ($nuevas as $e) {
            if (!is_array($e)) {
                continue;
            }
            $coincidencias++;
            $ids = is_array($e['residentes'] ?? null) ? $e['residentes'] : [];
            $lugar = (string) ($e['lugar_id'] ?? '');
            $diaE = (int) ($e['dia'] ?? 0);
            $env = ['residentes' => $ids, 'lugar_id' => $lugar, 'dia' => $diaE];
            if (CotilleoNarrativo::coincidenciaDigna($p, $env, $cal)) {
                $dignas++;
            } else {
                $descartadas_patron++;
            }
        }
        $despuesCoti = 0;
        foreach (BuzonEngine::listar($p) as $m) {
            if (($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO || ($m['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO) {
                $despuesCoti++;
            }
        }
        $publicados += max(0, $despuesCoti - $antesCoti);
    }

    return [
        'pop' => count(TutorialIncorporaciones::residentesActivos($p)),
        'dias' => $dias,
        'coincidencias_registradas' => $coincidencias,
        'patron_digno_min_dias' => (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_min_dias_par_lugar', 3),
        'ventana_dias' => (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_ventana_dias', 7),
        'habrian_sido_dignas_al_evaluar' => $dignas,
        'descartadas_por_filtro_patron_o_ya_publicado' => $descartadas_patron,
        'cotilleos_publicados' => $publicados,
        'nota' => 'Filtro: mismo par+lugar en ≥3 días de ventana 7. NPC_AUTONOMO_PLAN no publica. Encuentro digno solo si delta social/romance/conflicto/discovery.',
    ];
}

function diagDiscovery(PartidaService $svc, string $root): array
{
    $p = $svc->nuevaPartida('playtest_01', 'disc-diag');
    $id = TutorialIncorporaciones::residentesActivos($p)[0];
    $snaps = [];
    foreach ([1, 10, 30, 100] as $target) {
        while ((int) $p['reloj']['dia_pueblo'] < $target) {
            $ids = TutorialIncorporaciones::residentesActivos($p);
            if (count($ids) >= 2 && ((int) $p['reloj']['dia_pueblo'] % 2 === 0)) {
                PropuestaEncuentroEngine::proponer($p, [$ids[0], $ids[1]], (int) $p['reloj']['dia_pueblo'], 15, 'conocerse', 'lug_cafeteria');
            }
            $svc->avanzarReloj($p, 24);
        }
        $items = DiscoveryEngine::listarPorResidente($p, $id);
        $snaps['dia_' . $target] = count($items);
    }
    return [
        'residente' => IdentidadPublica::nombre($p, $id),
        'snapshots_n' => $snaps,
        'BALANCE_PENDIENTE_CATALOGO_PERSONAJES' => true,
        'nota' => 'No recalibrar. Catálogo amplio pendiente; curva longitudinal a diseñar después.',
    ];
}

echo "=== EMBUDO LLEGADAS ===\n";
$embudos = [];
foreach (['activa', 'normal', 'torpe', 'inactiva'] as $perfil) {
    foreach ([30, 100, 365] as $dias) {
        // 365 solo 1 seed
        $seeds = $dias >= 365 ? ['e1'] : ['e1', 'e2'];
        foreach ($seeds as $seed) {
            $row = embudoPerfil($svc, $root, $perfil, $dias, $seed . "-$perfil-d$dias");
            $embudos[] = $row;
            echo sprintf(
                "%s %dd gen=%d visto=%d ace=%d rech=%d exp=%d lleg=%d pop=%d→%d cd_days=%s\n",
                $perfil,
                $dias,
                $row['generados'],
                $row['abiertos_vistos'],
                $row['aceptados'],
                $row['rechazados'],
                $row['expirados'],
                $row['llegadas_efectivas'],
                $row['pop_inicial_post_tut'],
                $row['pop_final'],
                json_encode($row['dias_entre_mediana'])
            );
        }
    }
}

echo "=== COTILLEO ===\n";
$coti = [];
foreach ([8, 16, 32] as $pop) {
    $c = diagCotilleo($svc, $root, $pop, 30);
    $coti[$pop] = $c;
    echo "pop{$pop}: coinc={$c['coincidencias_registradas']} dignas={$c['habrian_sido_dignas_al_evaluar']} pub={$c['cotilleos_publicados']}\n";
}

echo "=== DISCOVERY ===\n";
$disc = diagDiscovery($svc, $root);
print_r($disc);

$report = [
    'meta' => ['ts' => date('c'), 'commit_base' => 'post-3d2f767-revision-neni'],
    'voluntad' => [
        'canon' => 'media_geometrica',
        'cal_key' => 'voluntad.resolucion_plan',
    ],
    'embudos' => $embudos,
    'diagnostico_crecimiento' => [
        'veredicto' => 'C',
        'detalle' => 'El lab B llamaba intentarOfrecer con p diaria completa cada día. Los horizontes usaban avanzarReloj(24h) con una sola tirada a p_hora≈p/24 (bug de composición). Corregido: tick recibe horas y compone 1-(1-p)^(h/24). Además bots inactivos dejan expirar; torpe rechaza más. No se han tocado p_base/p_por_hueco.',
        'esperado_humana_razonable' => 'Con atención ~85% y aceptación ~70% sobre ofertas, Bloque A (8→16) encaja en ~30–80 días según RNG/cooldown, no en 365 estancado.',
    ],
    'discovery' => $disc,
    'cotilleo' => $coti,
    'bloqueados' => [
        'RELACIONES' => 'PARCIAL',
        'MARCHAS' => 'BLOQUEADO_DECISION',
        'ECONOMIA' => 'NO_IMPLEMENTADO (encargo aparte)',
        'DISCOVERY_RITMO' => 'BALANCE_PENDIENTE_CATALOGO_PERSONAJES',
        'COTILLEO_VOLUMEN' => 'BALANCE/CONTENIDO — filtro patrón ≥3 días; muchas familias de evento no publican',
    ],
];

$path = $root . '/docs/DIAGNOSTICO_POST_NENI.json';
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$md = "# DIAGNÓSTICO POST-REVISIÓN NENI\n\n";
$md .= "Generado: {$report['meta']['ts']}\n\n";
$md .= "## A. Voluntad\n\nCanonizado **media geométrica** (`voluntad.resolucion_plan`). Tests: `tests/voluntad_media_geometrica_test.php`.\n\n";
$md .= "## B–C. Embudo llegadas y crecimiento\n\n";
$md .= "| perfil | días | gen | visto | ace | rech | exp | lleg | pop_i→f | días entre (med) |\n|---|---|---|---|---|---|---|---|---|---|\n";
foreach ($embudos as $e) {
    $md .= sprintf(
        "| %s | %d | %d | %d | %d | %d | %d | %d | %d→%d | %s |\n",
        $e['perfil'],
        $e['dias'],
        $e['generados'],
        $e['abiertos_vistos'],
        $e['aceptados'],
        $e['rechazados'],
        $e['expirados'],
        $e['llegadas_efectivas'],
        $e['pop_inicial_post_tut'],
        $e['pop_final'],
        $e['dias_entre_mediana'] === null ? '—' : $e['dias_entre_mediana']
    );
}
$md .= "\n**Veredicto crecimiento: C (cuello de botella de integración)** + componente B (bots).\n\n";
$md .= $report['diagnostico_crecimiento']['detalle'] . "\n\n";
$md .= "Expectativa jugadora humana razonable: " . $report['diagnostico_crecimiento']['esperado_humana_razonable'] . "\n\n";
$md .= "## D. Discovery\n\n";
$md .= "**BALANCE_PENDIENTE_CATALOGO_PERSONAJES** — no recalibrado.\n\n";
$md .= json_encode($disc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$md .= "## E. Cotilleo\n\n";
foreach ($coti as $pop => $c) {
    $md .= "- Pop $pop / {$c['dias']}d: coincidencias={$c['coincidencias_registradas']}, dignas_patron={$c['habrian_sido_dignas_al_evaluar']}, publicados={$c['cotilleos_publicados']}\n";
}
$md .= "\nClasificación: **CONTENIDO/BALANCE** (no bug de duplicados). Filtro `cotilleo_min_dias_par_lugar=3` en ventana 7. `NPC_AUTONOMO_PLAN` → null. Faltan familias publicables (no implementar ahora).\n\n";
$md .= "## F. Bugs corregidos\n\n";
$md .= "1. Voluntad plan: producto → media geométrica (decisión cerrada).\n";
$md .= "2. Llegadas: avanzar N horas aplicaba p/24 una sola vez → composición 1-(1-p)^(N/24).\n\n";
$md .= "## G. Siguen bloqueados\n\n";
foreach ($report['bloqueados'] as $k => $v) {
    $md .= "- **$k**: $v\n";
}
file_put_contents($root . '/docs/DIAGNOSTICO_POST_NENI.md', $md);
echo "Wrote docs/DIAGNOSTICO_POST_NENI.md\n";
