<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadSalidaIndividual;

/**
 * Motor diario 09:00–22:00. Al empezar el día solo se deciden HUECOS, no quién ni qué.
 * Al llegar el hueco se evalúa el estado real.
 */
final class MotorVidaDiaria
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function presupuesto(int $nResidentes, array $cal, RngService $rng): int
    {
        $k = (float) CalibracionConfig::get($cal, 'acontecimientos_dia.escala_sqrt', 1.15);
        $off = (float) CalibracionConfig::get($cal, 'acontecimientos_dia.escala_offset', 1.0);
        $n = (int) round($k * sqrt(max(1, $nResidentes)) + $off);
        $min = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.presupuesto_min', 2);
        $max = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.presupuesto_max', 10);
        if ($n < $min) {
            $n = $min;
        }
        if ($n > $max + (int) round(sqrt($nResidentes))) {
            $n = $max + (int) round(sqrt($nResidentes) * 0.4);
        }
        return max(1, $n);
    }

    /**
     * @param array<string, mixed> $cal
     * @return list<int>
     */
    public static function repartirHuecos(int $presupuesto, array $cal, RngService $rng): array
    {
        $ini = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.hora_inicio', 9);
        $fin = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.hora_fin', 22);
        $pool = [];
        for ($h = $ini; $h <= $fin; $h++) {
            $pool[] = $h;
        }
        $want = min($presupuesto, count($pool));
        $huecos = [];
        while (count($huecos) < $want && $pool !== []) {
            $idx = $rng->nextInt(0, count($pool) - 1);
            $huecos[] = $pool[$idx];
            array_splice($pool, $idx, 1);
        }
        sort($huecos);
        return $huecos;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function alComenzarDia(array &$partida, array $cal, RngService $rng): array
    {
        $n = count($partida['residentes'] ?? []);
        $p = self::presupuesto($n, $cal, $rng);
        $huecos = self::repartirHuecos($p, $cal, $rng);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['huecos_vida'] = [
            'dia' => $dia,
            'presupuesto' => $p,
            'horas' => $huecos,
            'ejecutados' => [],
        ];
        $rng->persistToPartida($partida);
        return $partida['huecos_vida'];
    }

    /**
     * Tick de UNA hora. No predecide la historia.
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function tickHora(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger = null
    ): array {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $ini = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.hora_inicio', 9);
        $fin = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.hora_fin', 22);
        $out = [
            'dia' => $dia,
            'hora' => $hora,
            'vida' => null,
            'autonomo' => null,
            'casuales' => [],
        ];
        if ($hora < $ini || $hora > $fin) {
            self::tickRecuperacionPasiva($partida, $cal);
            return $out;
        }
        if (!isset($partida['huecos_vida']['dia']) || (int) $partida['huecos_vida']['dia'] !== $dia) {
            self::alComenzarDia($partida, $cal, $rng);
        }
        $horasHueco = is_array($partida['huecos_vida']['horas'] ?? null) ? $partida['huecos_vida']['horas'] : [];
        if (in_array($hora, $horasHueco, true) && !in_array($hora, $partida['huecos_vida']['ejecutados'] ?? [], true)) {
            $out['vida'] = self::ejecutarHuecoVida($partida, $catalog, $cal, $rng, $logger);
            $partida['huecos_vida']['ejecutados'][] = $hora;
        }
        $out['autonomo'] = self::quizasSalidaIndividual($partida, $catalog, $cal, $rng, $logger);
        $out['iniciativa_social'] = IniciativaSocial::quizasDelTick($partida, $catalog, $cal, $rng, $logger);
        $out['casuales'] = self::casualesDeHora($partida, $catalog, $cal, $rng);

        // Necesidades: decay horario para todos los residentes
        self::tickNecesidades($partida, $cal);

        $rng->persistToPartida($partida);
        return $out;
    }

    /**
     * Aplica decay de necesidades a todos los residentes activos.
     * Solo durante horas de juego (hora_inicio – hora_fin).
     *
     * @param array<string, mixed> $cal
     */
    private static function tickNecesidades(array &$partida, array $cal): void
    {
        if (!FeatureConfig::isEnabled($partida, 'necesidades_enabled')) {
            return;
        }
        foreach ($partida['residentes'] as &$res) {
            NecesidadEstado::ensureResidente($res);
            NecesidadEstado::aplicarDecay($res, $cal);
        }
        unset($res);
        self::tickRecuperacionPasiva($partida, $cal);
    }

    private static function tickRecuperacionPasiva(array &$partida, array $cal): void
    {
        if (!FeatureConfig::isEnabled($partida, 'necesidades_enabled')) {
            return;
        }
        $rp = (float) CalibracionConfig::get($cal, 'necesidades.recuperacion_pasiva', 0.0);
        if ($rp <= 0.0) {
            return;
        }
        $MAX = 100;
        $MIN = 0;
        $TODAS = ['social', 'diversion', 'actividad', 'calma'];
        foreach ($partida['residentes'] as &$res) {
            $res['runtime'] ??= [];
            $res['runtime']['necesidades'] ??= [];
            $rt = &$res['runtime'];
            foreach ($TODAS as $nec) {
                $rt['necesidades'][$nec] ??= ['valor' => 85, 'banda' => 'bien', 'ultima_actualizacion' => null, 'ultima_recuperacion' => null];
                $rt['necesidades'][$nec]['valor'] = min($MAX, $rt['necesidades'][$nec]['valor'] + $rp);
                $v = $rt['necesidades'][$nec]['valor'];
                $rt['necesidades'][$nec]['banda'] = $v >= 75 ? 'bien' : ($v >= 50 ? 'le_vendria_bien' : ($v >= 25 ? 'lo_necesita' : 'en_rojo'));
            }
        }
        unset($res);
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    private static function ejecutarHuecoVida(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger
    ): ?array {
        $store = $catalog->store();
        $capa = $rng->nextFloat() < 0.42 ? 'vida' : 'relacion';
        $items = [];
        foreach ($store->items('acontecimientos') as $item) {
            $fam = (string) ($item['familia'] ?? '');
            if ($capa === 'vida' && in_array($fam, ['trabajo', 'ocio', 'vida'], true)) {
                $items[] = $item;
            }
            if ($capa === 'relacion' && in_array($fam, ['romance', 'romance_accion', 'romance_hito', 'pareja', 'consejo'], true)) {
                $items[] = $item;
            }
        }
        if ($items === []) {
            foreach ($store->items('acontecimientos') as $item) {
                $items[] = $item;
            }
        }
        $protagonista = self::elegirProtagonista($partida, $cal, $rng);
        if ($protagonista === null) {
            SimFunnelProbe::on($partida, 'hueco', ['ev' => 'sin_protagonista', '_k' => 'sin_protagonista_agenda', 'capa' => $capa, '_solo_conteo' => true]);
            return null;
        }
        $familiasPlay = CalibracionConfig::get($cal, 'acontecimientos_dia.familias_en_play', null);
        $enPlay = empty($partida['lab_vida_activa']) && FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled');
        if ($enPlay && is_array($familiasPlay) && $familiasPlay !== []) {
            $items = array_values(array_filter($items, static function ($item) use ($familiasPlay) {
                return in_array((string) ($item['familia'] ?? ''), $familiasPlay, true);
            }));
        }
        $elegido = self::elegirEvento($partida, $items, $protagonista, $cal, $rng);
        if ($elegido === null) {
            SimFunnelProbe::on($partida, 'hueco', ['ev' => 'sin_evento_valido', '_k' => 'sin_evento_valido', 'capa' => $capa, 'prot' => $protagonista, '_solo_conteo' => true]);
            return ['omitido' => 'sin_evento_valido', 'protagonista' => $protagonista];
        }
        $r = AcontecimientoDiario::ejecutar($partida, $elegido['id'], $elegido['participantes'], $store, $cal, $logger);
        $itemEl = $store->item('acontecimientos', $elegido['id']);
        SimFunnelProbe::on($partida, 'hueco', [
            'ev' => 'ejecutado',
            '_k' => 'ejecutado_' . (string) ($itemEl['familia'] ?? '?'),
            'capa' => $capa,
            'id' => $elegido['id'],
            'fam' => (string) ($itemEl['familia'] ?? '?'),
            'ok' => (bool) ($r['ok'] ?? false),
            'error' => $r['error'] ?? null,
        ]);
        self::marcarActividad($partida, $elegido['participantes']);
        return ['capa' => $capa, 'evento' => $elegido['id'], 'resultado' => $r];
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function elegirProtagonista(array $partida, array $cal, RngService $rng): ?string
    {
        $ids = array_keys($partida['residentes'] ?? []);
        if ($ids === []) {
            return null;
        }
        $bonusDias = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.olvidados_bonus_dias', 3);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $pesos = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            $disp = AgendaEngine::estaDisponible($partida, $id, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            $w = 1.0;
            $ult = (int) ($partida['residentes'][$id]['runtime']['ultimo_protagonismo_dia'] ?? 0);
            if ($ult === 0 || ($dia - $ult) >= $bonusDias) {
                $w += 3.8;
            }
            $emo = (string) ($partida['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
            if ($emo === EstadoEmocional::TRISTE) {
                $w += 0.8;
            }
            $pesos[] = ['id' => $id, 'w' => $w];
        }
        return self::pickPeso($pesos, $rng);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $cal
     * @return array{id:string,participantes:list<string>}|null
     */
    private static function elegirEvento(array $partida, array $items, string $protagonista, array $cal, RngService $rng): ?array
    {
        $pesosFam = CalibracionConfig::get($cal, 'acontecimientos_dia.pesos_familias', []);
        $cands = [];
        $flechazoCands = 0;
        $ids = array_keys($partida['residentes'] ?? []);
        foreach ($items as $item) {
            $need = (int) ($item['participantes'] ?? 1);
            $fam = (string) ($item['familia'] ?? '');
            $wFam = is_array($pesosFam) && isset($pesosFam[$fam]) ? (float) $pesosFam[$fam] : 0.4;
            if ($need <= 1) {
                $el = AcontecimientoElegibilidad::cumple($partida, $item, [$protagonista], $cal);
                if ($el['ok']) {
                    $cands[] = ['id' => (string) $item['id'], 'participantes' => [$protagonista], 'w' => max(0.05, $wFam)];
                }
                continue;
            }
            foreach ($ids as $otro) {
                $otro = (string) $otro;
                if ($otro === $protagonista) {
                    continue;
                }
                $el = AcontecimientoElegibilidad::cumple($partida, $item, [$protagonista, $otro], $cal);
                if ($el['ok']) {
                    if (SeleccionSocialPeso::debeOmitirPorConflicto($partida, $protagonista, $otro, $cal)) {
                        continue;
                    }
                    if ((string) $item['id'] === 'flechazo') {
                        $flechazoCands++;
                    }
                    $w = max(0.05, $wFam);
                    if (RelacionEngine::seConocen($partida, $protagonista, $otro)) {
                        $w *= 1.6;
                        $w += SeleccionSocialPeso::bonusSocialDirigido($partida, $protagonista, $otro) / 25.0;
                    }
                    $w = SeleccionSocialPeso::aplicarConflicto($w, $partida, $protagonista, $otro, $cal);
                    $cands[] = [
                        'id' => (string) $item['id'],
                        'participantes' => [$protagonista, $otro],
                        'w' => $w,
                    ];
                }
            }
        }
        SimFunnelProbe::on($partida, 'elegir_evento', [
            'ev' => 'escaneo',
            '_k' => 'escaneo',
            'prot' => $protagonista,
            'n_cands' => count($cands),
            'flechazo_n' => $flechazoCands,
        ]);
        if ($cands === []) {
            return null;
        }
        $pick = self::pickPeso($cands, $rng);
        foreach ($cands as $c) {
            if (($c['id'] . ':' . implode(',', $c['participantes'])) === $pick) {
                return ['id' => $c['id'], 'participantes' => $c['participantes']];
            }
        }
        $c = $cands[0];
        return ['id' => $c['id'], 'participantes' => $c['participantes']];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    private static function quizasSalidaIndividual(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger
    ): ?array {
        $n = count($partida['residentes'] ?? []);
        $k = (float) CalibracionConfig::get($cal, 'autonomia.salidas_individuales_sqrt', 0.7);
        $off = (float) CalibracionConfig::get($cal, 'autonomia.salidas_individuales_offset', 0.4);
        $cupoDia = (int) max(1, round($k * sqrt(max(1, $n)) + $off));
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hechas = 0;
        foreach ($partida['npc_autonomo']['historial_eventos'] ?? [] as $ev) {
            if ((int) ($ev['dia'] ?? 0) === $dia && ($ev['accion'] ?? '') === 'visitar_lugar') {
                $hechas++;
            }
        }
        if ($hechas >= $cupoDia) {
            SimFunnelProbe::on($partida, 'salida_individual', ['ev' => 'cupo_lleno', '_k' => 'cupo_lleno', '_solo_conteo' => true]);
            return null;
        }
        if ($rng->nextFloat() > 0.60) {
            SimFunnelProbe::on($partida, 'salida_individual', ['ev' => 'rng_no_sale', '_k' => 'rng_no_sale', '_solo_conteo' => true]);
            return null;
        }
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $aislamientoUmbral = (int)   CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_umbral_dias', 0);
        $aislamientoBonusSal = (float) CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_bonus_salida', 0.0);
        $ids = array_keys($partida['residentes'] ?? []);
        $pesos = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            $disp = AgendaEngine::estaDisponible($partida, $id, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            $w = 1.0;
            $ult = (int) ($partida['residentes'][$id]['runtime']['ultimo_protagonismo_dia'] ?? 0);
            if ($ult === 0 || ($dia - $ult) >= 3) {
                $w *= (float) CalibracionConfig::get($cal, 'autonomia.poco_activo_bonus', 1.6);
            }
            $emo = (string) ($partida['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
            $w += ((int) EstadoEmocional::modificadores($emo, $cal)['iniciativa_social']) / 40.0;
            // Candidato C: bonus anti-aislamiento — empujón gradual si lleva muchos días sin contacto social real
            if ($aislamientoUmbral > 0 && $aislamientoBonusSal > 0.0) {
                $ultCon = (int) ($partida['residentes'][$id]['runtime']['ultimo_contacto_social_dia'] ?? 0);
                $diasSin = ($ultCon === 0) ? $dia : max(0, $dia - $ultCon);
                if ($diasSin >= $aislamientoUmbral) {
                    // Escalado: leve hasta 2× umbral, mayor a partir de 2× umbral
                    $factor = $diasSin >= ($aislamientoUmbral * 2) ? $aislamientoBonusSal * 1.5 : $aislamientoBonusSal;
                    $w += $factor;
                }
            }
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        $quien = self::pickPeso($pesos, $rng);
        if ($quien === null) {
            SimFunnelProbe::on($partida, 'salida_individual', ['ev' => 'sin_disponibles_agenda', '_k' => 'sin_disponibles_agenda', '_solo_conteo' => true]);
            return null;
        }
        $ops = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if ($ops === []) {
            $ops = ['lug_cafeteria', 'lug_parque', 'lug_biblioteca'];
        }
        $evVol = VoluntadSalidaIndividual::evaluar($partida, $quien, $ops, $catalog, $cal, $rng);
        if (!($evVol['acepta'] ?? false)) {
            SimFunnelProbe::on($partida, 'salida_individual', [
                'ev' => 'voluntad_rechaza',
                '_k' => 'voluntad_rechaza',
                'quien' => $quien,
                'score' => $evVol['score'] ?? null,
                'p' => $evVol['p'] ?? null,
            ]);
            return null;
        }
        // Candidato C: si el residente está aislado, subir bonus de atracción por lugar ocupado
        $calLugar = $cal;
        $aislamientoBonusLugar = (float) CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_bonus_lugar', 0.0);
        if ($aislamientoUmbral > 0 && $aislamientoBonusLugar > 0.0 && $quien !== null) {
            $ultConQ = (int) ($partida['residentes'][$quien]['runtime']['ultimo_contacto_social_dia'] ?? 0);
            $diasSinQ = ($ultConQ === 0) ? $dia : max(0, $dia - $ultConQ);
            if ($diasSinQ >= $aislamientoUmbral) {
                $bonusActual = (float) CalibracionConfig::get($cal, 'autonomia.atraccion_ocupacion_bonus', 0);
                $calLugar['autonomia']['atraccion_ocupacion_bonus'] = $bonusActual + $aislamientoBonusLugar;
            }
        }
        $lugar = LugarAutonomo::elegir($partida, $quien, null, $ops, $rng, $catalog, $calLugar);
        if ($lugar === null) {
            $lugar = is_array($ops) && $ops !== [] ? (string) $ops[0] : 'lug_cafeteria';
        }
        // FASE 1 (fix HORA_PASADA): la actividad se agenda SIEMPRE a futuro
        // (Reloj::esFuturo es estricto: misma hora = pasado). Se busca la próxima
        // franja válida respetando ventana canónica, apertura del lugar y agenda
        // (trabajo/sueño/ocupaciones/encuentros ya reservados).
        $franja = self::siguienteFranjaFutura($partida, $quien, (string) $lugar, $cal);
        if ($franja === null || !AforoEngine::cabe($partida, $lugar, (int) $franja['dia'], (int) $franja['hora'], 1)) {
            SimFunnelProbe::on($partida, 'salida_individual', ['ev' => 'aforo_o_lugar_fail', '_k' => 'aforo_o_lugar_fail', '_solo_conteo' => true]);
            return null;
        }
        $attr = LugarAtributos::de($lugar);
        $r = EncuentroEngine::programar($partida, [$quien], (int) $franja['dia'], (int) $franja['hora'], 'individual', $lugar, null, $logger);
        if (!($r['ok'] ?? false)) {
            SimFunnelProbe::on($partida, 'salida_individual', [
                'ev' => 'error_programar',
                '_k' => 'error_programar_' . (string) ($r['error'] ?? '?'),
                'quien' => $quien,
                'lugar' => $lugar,
                'err' => $r['error'] ?? null,
            ]);
            return ['error' => $r['error'] ?? 'no_programado', 'quien' => $quien];
        }
        if (isset($r['encuentro']['id'])) {
            foreach ($partida['encuentros'] as $i => $enc) {
                if (($enc['id'] ?? '') === $r['encuentro']['id']) {
                    $partida['encuentros'][$i]['duracion_minutos'] = $attr['duracion_minutos'];
                    $partida['encuentros'][$i]['duracion_horas'] = $attr['horas'];
                    $partida['encuentros'][$i]['intencion'] = 'autonomo';
                }
            }
        }
self::marcarActividad($partida, [$quien]);
        $nowDia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $nowHora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $partida['npc_autonomo']['historial_eventos'][] = [
            'dia' => $nowDia,
            'hora' => $nowHora,
            'accion' => 'visitar_lugar',
            'residente_id' => $quien,
            'lugar' => $lugar,
            'programado_dia' => (int) $franja['dia'],
            'programado_hora' => (int) $franja['hora'],
        ];
        return ['quien' => $quien, 'lugar' => $lugar, 'encuentro' => $r['encuentro'] ?? null];
    }

    /**
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    private static function casualesDeHora(array &$partida, Catalog $catalog, array $cal, RngService $rng): array
    {
        $out = [];
        $porLugar = [];
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (!LugarAtributos::ocupaHora($enc, $dia, $hora)) {
                continue;
            }
            $lug = (string) ($enc['lugar'] ?? '');
            if ($lug === '') {
                continue;
            }
            foreach ($enc['participantes'] ?? [] as $pid) {
                $porLugar[$lug][(string) $pid] = true;
            }
        }
        foreach ($porLugar as $lug => $set) {
            $ids = array_keys($set);
            if (count($ids) < 2) {
                continue;
            }
            $hecho = InteraccionCasual::resolverGrupo(
                $partida,
                $ids,
                (string) $lug,
                $dia,
                $hora,
                $rng,
                $cal,
                $catalog
            );
            foreach ($hecho as $h) {
                $out[] = $h;
            }
        }
        return $out;
    }

    /**
     * @param list<string> $ids
     */
    private static function marcarActividad(array &$partida, array $ids): void
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        foreach ($ids as $id) {
            if (isset($partida['residentes'][$id])) {
                $partida['residentes'][$id]['runtime']['ultimo_protagonismo_dia'] = $dia;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $pesos
     */
    private static function pickPeso(array $pesos, RngService $rng): ?string
    {
        if ($pesos === []) {
            return null;
        }
        $sum = 0.0;
        foreach ($pesos as $p) {
            $sum += (float) ($p['w'] ?? 0);
        }
        if ($sum <= 0) {
            return (string) ($pesos[0]['id'] ?? null);
        }
        $pick = $rng->nextFloat() * $sum;
        $acc = 0.0;
        foreach ($pesos as $p) {
            $acc += (float) $p['w'];
            if ($pick <= $acc) {
                if (isset($p['participantes'])) {
                    return $p['id'] . ':' . implode(',', $p['participantes']);
                }
                return isset($p['id']) ? (string) $p['id'] : null;
            }
        }
        $last = $pesos[count($pesos) - 1];
        if (isset($last['participantes'])) {
            return $last['id'] . ':' . implode(',', $last['participantes']);
        }
        return isset($last['id']) ? (string) $last['id'] : null;
    }

    /**
     * FASE 1: primera hora FUTURA (máx +48 h) que cumple ventana canónica,
     * apertura del lugar y disponibilidad de agenda del residente.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array{dia:int,hora:int}|null
     */
    private static function siguienteFranjaFutura(array $partida, string $quien, string $lugar, array $cal): ?array
    {
        $ini = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.hora_inicio', 9);
        $fin = (int) CalibracionConfig::get($cal, 'acontecimientos_dia.hora_fin', 22);
        $nowAbs = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        for ($k = 1; $k <= 48; $k++) {
            $abs = $nowAbs + $k;
            $d = intdiv($abs, 24);
            $h = $abs % 24;
            if ($h < $ini || $h > $fin) {
                continue;
            }
            if (!ComplejoCatalog::estaAbierto($lugar, $h)) {
                continue;
            }
            $disp = AgendaEngine::estaDisponible($partida, $quien, $d, $h);
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            return ['dia' => $d, 'hora' => $h];
        }
        return null;
    }

    /**
     * Catch-up batch: registra actividad NPC plausible para un día offline.
     * NO ejecuta motores reales (encuentros, acontecimientos) — solo registra que la vida continuó.
     * Los encuentros programados previamente al ausente sí se resuelven por EncuentroLifecycle.
     *
     * @param array<string, mixed> $cal
     * @return array{eventos:int,salidas:int}
     */
    public static function catchUpBatchDia(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger = null
    ): array {
        $maxEv = (int) CalibracionConfig::get($cal, 'catch_up.eventos_por_dia', 3);
        $maxSal = (int) CalibracionConfig::get($cal, 'catch_up.salidas_por_dia', 1);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $eventos = 0;
        $salidas = 0;

        $vidaActual = FeatureConfig::isEnabled($partida, VidaPuebloEngine::FLAG)
            ? VidaPuebloEngine::valor($partida)
            : 100;
        $suelo = (int) CalibracionConfig::get($cal, 'vida_pueblo.offline_suelo', 5);
        if ($vidaActual <= $suelo + 5) {
            return ['eventos' => 0, 'salidas' => 0];
        }

        $ids = array_keys($partida['residentes'] ?? []);
        if ($ids === []) {
            return ['eventos' => 0, 'salidas' => 0];
        }

        $nEv = min($maxEv, max(1, (int) round(sqrt(count($ids)))));
        $eventos = $nEv;

        $cupoSal = (int) max(1, round(
            (float) CalibracionConfig::get($cal, 'autonomia.salidas_individuales_sqrt', 0.7) * sqrt(max(1, count($ids)))
            + (float) CalibracionConfig::get($cal, 'autonomia.salidas_individuales_offset', 0.4)
        ));
        $hechas = 0;
        foreach ($partida['npc_autonomo']['historial_eventos'] ?? [] as $ev) {
            if ((int) ($ev['dia'] ?? 0) === $dia && ($ev['accion'] ?? '') === 'visitar_lugar') {
                $hechas++;
            }
        }
        $salidas = min($maxSal, max(0, $cupoSal - $hechas));

        if ($eventos > 0 || $salidas > 0) {
            $partida['catch_up_log'] = $partida['catch_up_log'] ?? [];
            $partida['catch_up_log'][] = [
                'dia' => $dia,
                'eventos' => $eventos,
                'salidas' => $salidas,
            ];
        }

        $rng->persistToPartida($partida);
        return ['eventos' => $eventos, 'salidas' => $salidas];
    }
}
