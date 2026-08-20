<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Salidas autónomas individuales. Casa es el default.
 * No pisa planes de Celestine (agenda). No es un acontecimiento narrativo.
 */
final class AutonomiaSalidas
{
    public static function horaActiva(int $hora, array $cal = []): bool
    {
        $ini = (int) CalibracionConfig::get($cal, 'autonomia.hora_inicio', 7);
        $fin = (int) CalibracionConfig::get($cal, 'autonomia.hora_fin', 3);
        return ComplejoCatalog::horaEnFranja($hora, $ini, $fin);
    }

    /**
     * Probabilidad de intentar una salida esta hora. Por defecto más baja por la mañana
     * para no gastar el cupo diario antes de las franjas en las que hay coincidencias.
     */
    public static function pIntentar(int $hora, array $cal = []): float
    {
        $franjas = CalibracionConfig::get($cal, 'autonomia.p_franjas', null);
        if (is_array($franjas)) {
            foreach ($franjas as $f) {
                if (!is_array($f)) {
                    continue;
                }
                $ini = (int) ($f['ini'] ?? 0);
                $fin = (int) ($f['fin'] ?? 0);
                if (ComplejoCatalog::horaEnFranja($hora, $ini, $fin)) {
                    return max(0.0, (float) ($f['p'] ?? 0.0));
                }
            }
        }
        return (float) CalibracionConfig::get($cal, 'autonomia.p_intentar_hora', 0.18);
    }

    /**
     * Cupo diario de salidas autónomas del pueblo. Lineal con n: más población, más vida.
     */
    public static function cupoDia(int $n, array $cal = []): int
    {
        $lin = (float) CalibracionConfig::get($cal, 'autonomia.salidas_individuales_lineal', 0.48);
        $k = (float) CalibracionConfig::get($cal, 'autonomia.salidas_individuales_sqrt', 0.0);
        $off = (float) CalibracionConfig::get($cal, 'autonomia.salidas_individuales_offset', 0.5);
        $n = max(1, $n);
        return (int) max(1, round($lin * $n + $k * sqrt($n) + $off));
    }

    /**
     * Horas del ciclo activo, de hora_inicio a hora_fin exclusive.
     *
     * @return list<int>
     */
    public static function horasCiclo(array $cal = []): array
    {
        $ini = (int) CalibracionConfig::get($cal, 'autonomia.hora_inicio', 7);
        $fin = (int) CalibracionConfig::get($cal, 'autonomia.hora_fin', 3);
        $out = [];
        $h = (($ini % 24) + 24) % 24;
        $fin = (($fin % 24) + 24) % 24;
        for ($i = 0; $i < 24; $i++) {
            if ($h === $fin) {
                break;
            }
            $out[] = $h;
            $h = ($h + 1) % 24;
        }
        return $out;
    }

    /**
     * Salidas que deberían haberse producido al terminar esta hora.
     * Las p_franjas ponderan el reparto (tarde > mañana), no son un veto aleatorio del cupo.
     */
    public static function objetivoAcumulado(int $n, int $hora, array $cal = []): int
    {
        $cupo = self::cupoDia($n, $cal);
        $ciclo = self::horasCiclo($cal);
        if ($ciclo === []) {
            return $cupo;
        }
        $wSum = 0.0;
        $wHasta = 0.0;
        $visto = false;
        foreach ($ciclo as $h) {
            $w = max(0.01, self::pIntentar($h, $cal));
            $wSum += $w;
            if (!$visto) {
                $wHasta += $w;
            }
            if ($h === ((($hora % 24) + 24) % 24)) {
                $visto = true;
            }
        }
        if (!$visto || $wSum <= 0.0) {
            return 0;
        }
        $obj = (int) round($cupo * ($wHasta / $wSum));
        $ultima = $ciclo[count($ciclo) - 1];
        if (((($hora % 24) + 24) % 24) === $ultima) {
            return $cupo;
        }
        return max(0, min($cupo, $obj));
    }

    public static function maxSalidasEstaHora(int $n, array $cal = []): int
    {
        $cfg = (int) CalibracionConfig::get($cal, 'autonomia.max_salidas_misma_hora', 4);
        $escala = 1 + (int) floor(max(1, $n) / 16);
        return max(2, min(max(1, $cfg), $escala));
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function intentar(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger
    ): ?array {
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        if (!self::horaActiva($hora, $cal)) {
            return null;
        }
        $n = count($partida['residentes'] ?? []);
        if ($n <= 0) {
            return null;
        }
        $cupoDia = self::cupoDia($n, $cal);
        $maxPers = (int) CalibracionConfig::get($cal, 'autonomia.max_salidas_por_residente_dia', 1);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hechasDia = self::salidasDelDia($partida, $dia);
        if (count($hechasDia) >= $cupoDia) {
            return null;
        }

        $maxHora = self::maxSalidasEstaHora($n, $cal);
        $objetivo = self::objetivoAcumulado($n, $hora, $cal);
        $want = min($maxHora, max(0, $objetivo - count($hechasDia)));
        $salidas = [];
        for ($i = 0; $i < $want; $i++) {
            $hechasDia = self::salidasDelDia($partida, $dia);
            if (count($hechasDia) >= $cupoDia) {
                break;
            }
            $una = self::programarUna($partida, $catalog, $cal, $rng, $logger, $dia, $hora, $hechasDia, $maxPers);
            if ($una === null) {
                continue;
            }
            $salidas[] = $una;
        }
        if ($salidas === []) {
            return null;
        }
        return [
            'quien' => $salidas[0]['quien'],
            'lugar' => $salidas[0]['lugar'],
            'encuentro' => $salidas[0]['encuentro'] ?? null,
            'salidas' => $salidas,
        ];
    }

    /**
     * @param list<array<string, mixed>> $hechasDia
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    private static function programarUna(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger,
        int $dia,
        int $hora,
        array $hechasDia,
        int $maxPers
    ): ?array {
        $pesos = self::pesosCandidatos($partida, $cal, $dia, $hora, $hechasDia, $maxPers);
        $quien = MotorVidaDiaria::pickPesoPublico($pesos, $rng);
        if ($quien === null) {
            return null;
        }

        $ops = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if (!is_array($ops) || $ops === []) {
            $ops = ['lug_cafeteria', 'lug_parque', 'lug_biblioteca'];
        }
        $opsAbiertos = [];
        foreach ($ops as $lug) {
            $lug = (string) $lug;
            if ($lug === '' || $lug === 'lug_casa') {
                continue;
            }
            if (!ComplejoCatalog::estaAbierto($lug, $hora)) {
                continue;
            }
            $opsAbiertos[] = $lug;
        }
        if ($opsAbiertos === []) {
            return null;
        }

        $lugar = null;
        $durH = 1;
        $excluidos = [];
        for ($t = 0; $t < 4; $t++) {
            $opsTry = [];
            foreach ($opsAbiertos as $lug) {
                if (!in_array($lug, $excluidos, true)) {
                    $opsTry[] = $lug;
                }
            }
            if ($opsTry === []) {
                return null;
            }
            $cand = LugarAutonomo::elegir($partida, $quien, null, $opsTry, $rng, $catalog, $cal);
            if ($cand === null || !ComplejoCatalog::estaAbierto($cand, $hora)) {
                return null;
            }
            $attr = LugarAtributos::de($cand);
            $rest = ComplejoCatalog::horasRestantesAbiertas($cand, $hora);
            if ($rest < 1) {
                $excluidos[] = $cand;
                continue;
            }
            $durTry = min((int) $attr['horas'], $rest);
            if (!AforoEngine::cabeIntervalo($partida, $cand, $dia, $hora, $durTry, 1)) {
                if (!isset($partida['npc_autonomo']) || !is_array($partida['npc_autonomo'])) {
                    $partida['npc_autonomo'] = [];
                }
                if (!isset($partida['npc_autonomo']['stats']) || !is_array($partida['npc_autonomo']['stats'])) {
                    $partida['npc_autonomo']['stats'] = [];
                }
                $partida['npc_autonomo']['stats']['aforo_lleno'] =
                    (int) ($partida['npc_autonomo']['stats']['aforo_lleno'] ?? 0) + 1;
                $excluidos[] = $cand;
                continue;
            }
            $lugar = $cand;
            $durH = $durTry;
            break;
        }
        if ($lugar === null) {
            return null;
        }

        $r = EncuentroEngine::programar($partida, [$quien], $dia, $hora, 'individual', $lugar, null, $logger);
        if (!($r['ok'] ?? false)) {
            return null;
        }
        if (isset($r['encuentro']['id'])) {
            foreach ($partida['encuentros'] as $i => $enc) {
                if (($enc['id'] ?? '') === $r['encuentro']['id']) {
                    $partida['encuentros'][$i]['duracion_minutos'] = $durH * 60;
                    $partida['encuentros'][$i]['duracion_horas'] = $durH;
                    $partida['encuentros'][$i]['intencion'] = 'autonomo';
                    $r['encuentro'] = $partida['encuentros'][$i];
                }
            }
        }
        MotorVidaDiaria::marcarActividadPublico($partida, [$quien]);
        if (!isset($partida['residentes'][$quien]['runtime']) || !is_array($partida['residentes'][$quien]['runtime'])) {
            $partida['residentes'][$quien]['runtime'] = [];
        }
        $partida['residentes'][$quien]['runtime']['ultima_salida_autonoma_dia'] = $dia;
        if (!isset($partida['npc_autonomo']) || !is_array($partida['npc_autonomo'])) {
            $partida['npc_autonomo'] = [];
        }
        if (!isset($partida['npc_autonomo']['historial_eventos']) || !is_array($partida['npc_autonomo']['historial_eventos'])) {
            $partida['npc_autonomo']['historial_eventos'] = [];
        }
        $partida['npc_autonomo']['historial_eventos'][] = [
            'dia' => $dia,
            'hora' => $hora,
            'accion' => 'visitar_lugar',
            'residente_id' => $quien,
            'lugar' => $lugar,
        ];
        return ['quien' => $quien, 'lugar' => $lugar, 'encuentro' => $r['encuentro'] ?? null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function salidasDelDia(array $partida, int $dia): array
    {
        $out = [];
        foreach ($partida['npc_autonomo']['historial_eventos'] ?? [] as $ev) {
            if ((int) ($ev['dia'] ?? 0) === $dia && ($ev['accion'] ?? '') === 'visitar_lugar') {
                $out[] = $ev;
            }
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $hechasDia
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    private static function pesosCandidatos(
        array $partida,
        array $cal,
        int $dia,
        int $hora,
        array $hechasDia,
        int $maxPers
    ): array {
        $porPersona = [];
        foreach ($hechasDia as $ev) {
            $rid = (string) ($ev['residente_id'] ?? '');
            if ($rid !== '') {
                $porPersona[$rid] = (int) ($porPersona[$rid] ?? 0) + 1;
            }
        }
        $aislamientoUmbral = (int) CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_umbral_dias', 0);
        $aislamientoBonusSal = (float) CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_bonus_salida', 0.0);
        $pesos = [];
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            $id = (string) $id;
            if ((int) ($porPersona[$id] ?? 0) >= $maxPers) {
                continue;
            }
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
            if ($aislamientoUmbral > 0 && $aislamientoBonusSal > 0.0) {
                $ultSal = (int) ($partida['residentes'][$id]['runtime']['ultima_salida_autonoma_dia'] ?? 0);
                $diasSin = ($ultSal === 0) ? $dia : max(0, $dia - $ultSal);
                if ($diasSin >= $aislamientoUmbral) {
                    $factor = $diasSin >= ($aislamientoUmbral * 2) ? $aislamientoBonusSal * 1.5 : $aislamientoBonusSal;
                    $w += $factor;
                }
            }
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        return $pesos;
    }
}
