<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Simulación QA sin jugador — fixtures controlados, no autonomía rica. */
final class SimulationRunner
{
    public static function run(
        string $projectRoot,
        int $days,
        ?string $seed = null,
        string $configId = 'test_fixtures_v0',
        int $residentesActivosExtra = 1
    ): array {
        $t0 = microtime(true);
        $days = max(1, min(365, $days));
        $informe = [
            'ok' => true,
            '_nota' => 'Simulación QA no canónica',
            'days' => $days,
            'seed' => $seed,
            'errores' => [],
            'invariantes_rotas' => [],
            'encuentros_programados' => 0,
            'encuentros_resueltos' => 0,
            'conflictos_agenda' => 0,
            'eventos_dominio' => 0,
            'relaciones_cambios' => 0,
            'coincidencias' => 0,
            'save_bytes_por_dia' => [],
        ];

        $service = new PartidaService($projectRoot);
        try {
            $partida = $service->nuevaPartida($configId, $seed ?? 'sim-' . $days);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $emociones = $service->emociones();
        $catalog = new CatalogStore($projectRoot);
        $estadosValidos = $catalog->ids('estados_emocionales');
        $qa = 'per_qa_valid';
        $extraIds = [];
        for ($i = 0; $i < max(1, $residentesActivosExtra); $i++) {
            $ph = $service->crearResidentePlaceholderDev($partida);
            $extraIds[] = (string) ($ph['residente']['catalog_id'] ?? '');
        }
        $extraIds = array_values(array_filter($extraIds, static fn($id) => $id !== ''));

        for ($d = 0; $d < $days; $d++) {
            $dia = (int) $partida['reloj']['dia_pueblo'];
            foreach ($extraIds as $extraId) {
                $slots = DisponibilidadEngine::slotsCompatibles($partida, [$qa, $extraId], 'conocerse', $dia, 12, 1, 3);
                if (($slots['ok'] ?? false) && !empty($slots['slots'])) {
                    $slot = $slots['slots'][0];
                    $r = $service->programarEncuentro($partida, [$qa, $extraId], $slot['dia'], $slot['hora'], 'conocerse');
                    if ($r['ok'] ?? false) {
                        $informe['encuentros_programados']++;
                    } else {
                        $informe['errores'][] = ['dia' => $dia, 'programar' => $r['error'] ?? 'fail', 'extra_id' => $extraId];
                    }
                }
            }

            $adv = $service->avanzarReloj($partida, 24);
            $informe['encuentros_resueltos'] += (int) ($adv['encuentros_resueltos'] ?? 0);
            $informe['coincidencias'] += (int) ($adv['coincidencias_detectadas'] ?? 0);

            $cal = DevCalendarService::vistaDia($partida, $dia, $service->getCatalog());
            $informe['conflictos_agenda'] += count($cal['conflictos'] ?? []);

            self::checkInvariants($partida, $informe, $projectRoot, $emociones, $estadosValidos);
            $informe['save_bytes_por_dia'][] = strlen((string) json_encode($partida));
        }

        $service->guardar($partida);
        $path = (new PartidaRepository($projectRoot))->pathFor($partida['meta']['partida_id']);
        $t1 = microtime(true);

        $informe['partida_id'] = $partida['meta']['partida_id'];
        $informe['save_bytes'] = is_file($path) ? filesize($path) : 0;
        $informe['ms_total'] = round(($t1 - $t0) * 1000, 2);
        $informe['eventos_dominio'] = count($partida['domain_events'] ?? []);
        $informe['relaciones_cambios'] = count($partida['historial_relaciones'] ?? []);
        $informe['audit_trail_size'] = count($partida['audit_trail'] ?? []);
        $informe['residentes'] = count($partida['residentes']);
        $informe['residentes_activos_extra'] = count($extraIds);
        $informe['historial_coincidencias_size'] = count($partida['historial_coincidencias'] ?? []);
        self::checkSaveGrowth($informe);

        return $informe;
    }

    private static function checkInvariants(
        array $partida,
        array &$informe,
        string $projectRoot,
        EmotionalStateService $emociones,
        array $estadosValidos = []
    ): void
    {
        if ((int) ($partida['reloj']['hora_actual'] ?? -1) < 0 || (int) ($partida['reloj']['hora_actual'] ?? 99) > 23) {
            $informe['invariantes_rotas'][] = 'hora_fuera_rango';
        }

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);

        // Coincidencias: no debe haber duplicados por key.
        $keys = [];
        foreach ($partida['historial_coincidencias'] ?? [] as $c) {
            if (!is_array($c) || empty($c['key'])) {
                continue;
            }
            $k = (string) $c['key'];
            if (isset($keys[$k])) {
                $informe['invariantes_rotas'][] = 'duplicado_coincidencia_key:' . $k;
            }
            $keys[$k] = true;
        }

        // Residentes no deberían aparecer en dos lugares simultáneamente (técnico).
        $pres = PresenciaEngine::resolver($partida, $projectRoot, $dia, $hora);
        $lugPorResid = [];
        foreach ($pres['lugares'] ?? [] as $lug) {
            $lid = (string) ($lug['id'] ?? '');
            foreach ($lug['residentes_presentes'] ?? [] as $rp) {
                $rid = (string) ($rp['id'] ?? '');
                if ($rid === '') continue;
                $lugPorResid[$rid][] = $lid;
            }
        }
        foreach ($lugPorResid as $rid => $lids) {
            $lids = array_values(array_unique(array_filter($lids, static fn($x) => is_string($x) && $x !== '')));
            if (count($lids) > 1) {
                $informe['invariantes_rotas'][] = 'residente_dos_lugares:' . $rid;
            }
        }

        foreach ($partida['encuentros'] ?? [] as $enc) {
            foreach ($enc['participantes'] ?? [] as $p) {
                if (!isset($partida['residentes'][$p])) {
                    $informe['invariantes_rotas'][] = 'encuentro_participante_huérfano:' . $enc['id'];
                }
            }
        }

        // Encuentros: coherencia mínima.
        $encIds = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            $id = (string) ($enc['id'] ?? '');
            if ($id !== '') {
                if (isset($encIds[$id])) {
                    $informe['invariantes_rotas'][] = 'duplicado_encuentro_id:' . $id;
                }
                $encIds[$id] = true;
            }
            $parts = $enc['participantes'] ?? [];
            if (!is_array($parts) || count($parts) < 2) {
                $informe['invariantes_rotas'][] = 'encuentro_sin_participantes_mínimos:' . ($enc['id'] ?? 'enc_unknown');
            }
            $h = (int) ($enc['hora'] ?? -1);
            if ($h < 0 || $h > 23) {
                $informe['invariantes_rotas'][] = 'encuentro_hora_fuera_rango:' . ($enc['id'] ?? 'enc_unknown');
            }
        }

        $ocupacionSlot = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            $estado = (string) ($enc['estado'] ?? '');
            if (!in_array($estado, ['programado', 'en_curso'], true)) {
                continue;
            }
            $slot = (int) ($enc['dia'] ?? 0) . ':' . (int) ($enc['hora'] ?? -1);
            foreach ($enc['participantes'] ?? [] as $p) {
                $k = $p . '@' . $slot;
                if (isset($ocupacionSlot[$k])) {
                    $informe['invariantes_rotas'][] = 'encuentro_imposible_doble_slot:' . $p;
                }
                $ocupacionSlot[$k] = true;
            }
        }

        $corrVistos = [];
        foreach ($partida['domain_events'] ?? [] as $ev) {
            $cid = (string) ($ev['correlacion_id'] ?? '');
            $tipo = (string) ($ev['evento'] ?? '');
            if ($cid === '') {
                continue;
            }
            $ck = $tipo . '|' . $cid;
            if (isset($corrVistos[$ck])) {
                $informe['invariantes_rotas'][] = 'evento_duplicado:' . $ck;
            }
            $corrVistos[$ck] = true;
        }

        $capAudit = PersistenciaCaps::cap($partida, 'audit_trail_cap', 200);
        $capDom = PersistenciaCaps::cap($partida, 'domain_events_cap', 200);
        $capCoin = PersistenciaCaps::cap($partida, 'historial_coincidencias_cap', 500);
        if (count($partida['audit_trail'] ?? []) > $capAudit) {
            $informe['invariantes_rotas'][] = 'audit_trail_excede_cap';
        }
        if (count($partida['domain_events'] ?? []) > $capDom) {
            $informe['invariantes_rotas'][] = 'domain_events_excede_cap';
        }
        if (count($partida['historial_coincidencias'] ?? []) > $capCoin) {
            $informe['invariantes_rotas'][] = 'historial_coincidencias_excede_cap';
        }

        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            $estId = (string) ($res['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
            if ($estadosValidos !== [] && !in_array($estId, $estadosValidos, true)) {
                $informe['invariantes_rotas'][] = 'estado_emocional_desconocido:' . $rid . ':' . $estId;
            }
            try {
                $resolved = $emociones->resolverResidente($partida, $res);
                $fb = (bool) ($resolved['fallback'] ?? false);
                $asset = is_array($resolved['asset'] ?? null) ? $resolved['asset'] : [];
                if (!$fb) {
                    if (empty($asset['existe'])) {
                        $informe['invariantes_rotas'][] = 'asset_inexistente_sin_fallback:' . $rid;
                    }
                    $packVer = (int) ($resolved['visual_identity_version'] ?? 0);
                    $fileVer = (int) ($asset['identidad_version'] ?? 0);
                    if ($packVer > 0 && $fileVer > 0 && $packVer !== $fileVer) {
                        $informe['invariantes_rotas'][] = 'identidad_visual_desfasada:' . $rid;
                    }
                }
            } catch (\Throwable $e) {
                $informe['invariantes_rotas'][] = 'resolver_emociones_fallo:' . $rid;
            }
        }
    }

    private static function checkSaveGrowth(array &$informe): void
    {
        $serie = $informe['save_bytes_por_dia'] ?? [];
        if (count($serie) < 8) {
            return;
        }
        $first = max(1, (int) $serie[0]);
        $last = (int) $serie[array_key_last($serie)];
        $mid = (int) $serie[(int) floor(count($serie) / 2)];
        if ($last > 2_000_000) {
            $informe['invariantes_rotas'][] = 'save_json_excede_2mb';
        }
        // Crecimiento superlineal tosco: la segunda mitad crece mucho más que la primera.
        if ($mid > 0 && ($last - $mid) > 8 * max(1, $mid - $first) && $last > 400_000) {
            $informe['invariantes_rotas'][] = 'save_crecimiento_descontrolado';
        }
        $informe['save_bytes_json_ultimo'] = $last;
    }

    /**
     * QA de flujo jugable: partida nueva, 2+ residentes, programar, cancelar, resolver, 30 días.
     */
    public static function runFlujoLargoPlay(
        string $projectRoot,
        int $days = 30,
        string $seed = 'flujo-play-30'
    ): array {
        $t0 = microtime(true);
        $days = max(1, min(365, $days));
        $informe = [
            'ok' => true,
            '_nota' => 'QA flujo play largo — no canónico',
            'days' => $days,
            'seed' => $seed,
            'errores' => [],
            'invariantes_rotas' => [],
            'encuentros_programados' => 0,
            'encuentros_resueltos' => 0,
            'encuentros_cancelados' => 0,
            'agenda_liberada_tras_cancel' => false,
            'relacion_placeholder_ok' => false,
        ];

        $service = new PartidaService($projectRoot);
        try {
            $partida = $service->nuevaPartida('test_fixtures_v0', $seed);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $emociones = $service->emociones();
        $catalog = new CatalogStore($projectRoot);
        $estadosValidos = $catalog->ids('estados_emocionales');
        $qa = 'per_qa_valid';
        $phA = $service->crearResidentePlaceholderDev($partida);
        $phB = $service->crearResidentePlaceholderDev($partida);
        $idA = (string) ($phA['residente']['catalog_id'] ?? '');
        $idB = (string) ($phB['residente']['catalog_id'] ?? '');
        if ($idA === '' || $idB === '') {
            return ['ok' => false, 'error' => 'placeholders_no_creados'];
        }

        $slots1 = DisponibilidadEngine::slotsCompatibles($partida, [$qa, $idA], 'conocerse', 1, 8, 2, 8);
        if (!($slots1['ok'] ?? false) || empty($slots1['slots'])) {
            $informe['errores'][] = 'sin_slots_qa_a';
        } else {
            $s = $slots1['slots'][0];
            $enc1 = $service->programarEncuentro($partida, [$qa, $idA], (int) $s['dia'], (int) $s['hora'], 'conocerse');
            if ($enc1['ok'] ?? false) {
                $informe['encuentros_programados']++;
                $informe['enc_resolver_id'] = $enc1['encuentro']['id'];
            } else {
                $informe['errores'][] = $enc1['error'] ?? 'programar_1';
            }
        }

        $slots2 = DisponibilidadEngine::slotsCompatibles($partida, [$qa, $idB], 'conocerse', 1, 8, 2, 8);
        $enc2Id = null;
        $enc2Dia = null;
        $enc2Hora = null;
        if (($slots2['ok'] ?? false) && !empty($slots2['slots'])) {
            $s2 = $slots2['slots'][0];
            $enc2 = $service->programarEncuentro($partida, [$qa, $idB], (int) $s2['dia'], (int) $s2['hora'], 'amistad');
            if ($enc2['ok'] ?? false) {
                $informe['encuentros_programados']++;
                $enc2Id = $enc2['encuentro']['id'];
                $enc2Dia = (int) $enc2['encuentro']['dia'];
                $enc2Hora = (int) $enc2['encuentro']['hora'];
            } else {
                $informe['errores'][] = $enc2['error'] ?? 'programar_2';
            }
        }

        if ($enc2Id !== null) {
            $cancel = $service->cancelarEncuentro($partida, $enc2Id);
            if ($cancel['ok'] ?? false) {
                $informe['encuentros_cancelados']++;
                $disp = AgendaEngine::estaDisponible($partida, $idB, $enc2Dia, $enc2Hora);
                $informe['agenda_liberada_tras_cancel'] = (bool) ($disp['disponible'] ?? false);
            } else {
                $informe['errores'][] = $cancel['error'] ?? 'cancelar';
            }
        }

        $goto = $service->irAlProximoEncuentro($partida);
        if ($goto['ok'] ?? false) {
            $adv = $service->avanzarReloj($partida, 1);
            $informe['encuentros_resueltos'] += (int) ($adv['encuentros_resueltos'] ?? 0);
        }

        $rel = RelacionEngine::obtenerEntre($partida, $qa, $idA);
        $informe['relacion_placeholder_ok'] = ($rel['social'] !== null);

        for ($d = 0; $d < $days; $d++) {
            $dia = (int) $partida['reloj']['dia_pueblo'];
            $hora = (int) $partida['reloj']['hora_actual'];
            $slots = DisponibilidadEngine::slotsCompatibles($partida, [$qa, $idA], 'conocerse', $dia, $hora, 2, 3);
            if (($slots['ok'] ?? false) && !empty($slots['slots'])) {
                $slot = $slots['slots'][0];
                $r = $service->programarEncuentro($partida, [$qa, $idA], (int) $slot['dia'], (int) $slot['hora'], 'conocerse');
                if ($r['ok'] ?? false) {
                    $informe['encuentros_programados']++;
                }
            }
            $adv = $service->avanzarReloj($partida, 24);
            $informe['encuentros_resueltos'] += (int) ($adv['encuentros_resueltos'] ?? 0);
            self::checkInvariants($partida, $informe, $projectRoot, $emociones, $estadosValidos);
        }

        $val = PartidaValidator::validar($partida);
        if ($val !== []) {
            $informe['invariantes_rotas'][] = 'partida_invalida';
            $informe['validacion'] = $val;
        }

        $service->guardar($partida);
        $path = (new PartidaRepository($projectRoot))->pathFor($partida['meta']['partida_id']);
        $informe['partida_id'] = $partida['meta']['partida_id'];
        $informe['save_bytes'] = is_file($path) ? filesize($path) : 0;
        $informe['rng_state'] = (int) ($partida['rng']['state'] ?? 0);
        $informe['reloj'] = $partida['reloj'];
        $informe['encuentros_por_estado'] = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            $st = (string) ($enc['estado'] ?? '');
            $informe['encuentros_por_estado'][$st] = ($informe['encuentros_por_estado'][$st] ?? 0) + 1;
        }
        $informe['eventos_dominio'] = count($partida['domain_events'] ?? []);
        $informe['audit_trail_size'] = count($partida['audit_trail'] ?? []);
        $informe['ms_total'] = round((microtime(true) - $t0) * 1000, 2);
        $informe['ok'] = $informe['errores'] === [] && ($informe['invariantes_rotas'] ?? []) === [];

        return $informe;
    }
}
