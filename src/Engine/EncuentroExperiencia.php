<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Experiencia de encuentro por participante. Circunstancias primero; azar después.
 * Pesos de circunstancias null = carga 0 (azar uniforme provisional).
 */
final class EncuentroExperiencia
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function resolver(
        array $partida,
        array $encuentro,
        Catalog $catalog,
        RngService $rng,
        array $cal
    ): array {
        $snap = EncuentroPonderacion::snapshot($partida, $encuentro, $catalog);
        $ids = array_values($encuentro['participantes'] ?? []);
        $resultados = CalibracionConfig::get($cal, 'resolucion_encuentro.resultados', ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien']);
        if (!is_array($resultados) || $resultados === []) {
            $resultados = ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'];
        }
        $por = [];
        $rachaUmbral = CalibracionConfig::get($cal, 'azar_ponderado.racha_penaliza_tras', null);
        $rachaN = is_numeric($rachaUmbral) ? (int) $rachaUmbral : null;
        $interv = is_array($encuentro['intervencion_celeste'] ?? null) ? $encuentro['intervencion_celeste'] : null;
        $temaCargas = is_array($interv['tema_cargas'] ?? null) ? $interv['tema_cargas'] : [];
        $cargaAccion = 0.0;
        if ($interv !== null && isset($interv['carga']) && is_numeric($interv['carga'])) {
            if (($interv['accion'] ?? '') === EncuentroIntervencion::HOBBY) {
                $cargaAccion = (float) CalibracionConfig::get($cal, 'mentes.carga_accion_hobby', 0.04);
            } else {
                $cargaAccion = (float) $interv['carga'];
            }
        }
        foreach ($ids as $pid) {
            $pid = (string) $pid;
            $carga = self::cargaDe($snap, $pid, $cal);
            $carga += $cargaAccion;
            $carga += (float) ($temaCargas[$pid] ?? 0.0);
            if ($carga < -1.0) {
                $carga = -1.0;
            }
            if ($carga > 1.0) {
                $carga = 1.0;
            }
            $recientes = [];
            foreach (MemoriaEventos::recientes($partida, [$pid], 5) as $ev) {
                if (isset($ev['resultado_experiencia'])) {
                    $recientes[] = (string) $ev['resultado_experiencia'];
                }
            }
            $avisoRacha = AzarPonderado::rachaArtificial($recientes, 'excelente', $rachaN)
                || AzarPonderado::rachaArtificial($recientes, 'malo', $rachaN);
            $tirada = AzarPonderado::tirar($rng, $resultados, $carga, $cal);
            $resultadoExp = (string) ($tirada['resultado'] ?? 'normal');
            $textoMentes = null;
            if ($interv !== null && $catalog !== null) {
                $textoMentes = MentesTemas::copyExperienciaParticipante(
                    $partida,
                    $encuentro,
                    $pid,
                    $resultadoExp,
                    $interv,
                    $catalog
                );
            }
            $por[$pid] = [
                'satisfaccion' => null,
                'texto' => $textoMentes,
                'resultado' => $resultadoExp,
                'carga' => $carga,
                'carga_tema' => (float) ($temaCargas[$pid] ?? 0.0),
                'carga_accion' => $cargaAccion,
                'aviso_racha' => $avisoRacha,
                'compatibilidad_hacia_otro' => $snap['por_participante'][$pid]['compatibilidad_hacia_otro'] ?? null,
                '_bloqueado_decision' => ['satisfaccion_numerica', 'copy'],
            ];
        }
        $snap['por_participante'] = $por;
        $snap['participantes'] = $ids;
        $snap['azar_ponderado'] = true;
        return $snap;
    }

    /**
     * @param array<string, mixed> $snap
     * @param array<string, mixed> $cal
     */
    public static function cargaDe(array $snap, string $pid, array $cal): float
    {
        $pesos = CalibracionConfig::get($cal, 'resolucion_encuentro.pesos', []);
        if (!is_array($pesos)) {
            return 0.0;
        }
        $row = $snap['por_participante'][$pid] ?? [];
        $fact = $snap['factores'] ?? [];
        $acc = 0.0;
        $wsum = 0.0;
        foreach ($pesos as $k => $w) {
            if (!is_numeric($w)) {
                continue;
            }
            $w = (float) $w;
            $wsum += abs($w);
            $v = 0.0;
            if ($k === 'compat_ab' || $k === 'compat_ba') {
                $dir = $k === 'compat_ab' ? ($fact['compat_ab'] ?? null) : ($fact['compat_ba'] ?? null);
                $tot = is_array($dir) ? ($dir['total'] ?? null) : null;
                $v = is_numeric($tot) ? ((float) $tot) / 100.0 : 0.0;
            } elseif ($k === 'quimica') {
                $q = $fact['quimica'] ?? null;
                $qv = is_array($q) ? ($q['a_hacia_b'] ?? $q['valor'] ?? null) : null;
                $v = is_numeric($qv) ? ((float) $qv) / 100.0 : 0.0;
            } elseif ($k === 'vinculo_social') {
                $soc = $fact['social_ab']['valor'] ?? 0;
                $v = ((float) $soc) / 100.0;
            } elseif ($k === 'vinculo_romance') {
                $v = ((float) ($fact['romance_ab'] ?? 0)) / 100.0;
            } elseif ($k === 'conflicto') {
                $c = $fact['conflicto'] ?? 0;
                $v = is_numeric($c) ? -((float) $c) / 20.0 : 0.0;
            } elseif ($k === 'emocional') {
                $emo = (string) ($fact['emocional_a'] ?? 'neutro');
                $v = ((float) EstadoEmocional::modificadores($emo, $cal)['experiencia_encuentro']) / 20.0;
            } elseif ($k === 'lugar' || $k === 'hobbies_plan') {
                $plan = $pid === ($snap['participantes'][0] ?? '') ? ($fact['plan_a'] ?? null) : ($fact['plan_b'] ?? null);
                $ap = is_array($plan) ? (int) ($plan['aporte'] ?? 0) : 0;
                $pe = is_array($plan) ? (int) ($plan['penalizacion'] ?? 0) : 0;
                $v = ($ap - $pe) / 20.0;
            } elseif ($k === 'azar') {
                $v = 0.0;
            }
            $acc += $w * $v;
        }
        if ($wsum <= 0) {
            return 0.0;
        }
        $carga = $acc / $wsum;
        if ($carga < -1) {
            return -1.0;
        }
        if ($carga > 1) {
            return 1.0;
        }
        return $carga;
    }

    /**
     * Contribución por factor a la carga (auditoría y narrativa). Misma lógica que cargaDe.
     *
     * @param array<string, mixed> $snap
     * @param array<string, mixed> $cal
     * @return array{carga: float, contribuciones: array<string, float>}
     */
    public static function desgloseCarga(array $snap, string $pid, array $cal): array
    {
        $pesos = CalibracionConfig::get($cal, 'resolucion_encuentro.pesos', []);
        if (!is_array($pesos)) {
            return ['carga' => 0.0, 'contribuciones' => []];
        }
        $fact = $snap['factores'] ?? [];
        $contrib = [];
        $acc = 0.0;
        $wsum = 0.0;
        foreach ($pesos as $k => $w) {
            if (!is_numeric($w)) {
                continue;
            }
            $w = (float) $w;
            $wsum += abs($w);
            $v = 0.0;
            if ($k === 'compat_ab' || $k === 'compat_ba') {
                $dir = $k === 'compat_ab' ? ($fact['compat_ab'] ?? null) : ($fact['compat_ba'] ?? null);
                $tot = is_array($dir) ? ($dir['total'] ?? null) : null;
                $v = is_numeric($tot) ? ((float) $tot) / 100.0 : 0.0;
            } elseif ($k === 'quimica') {
                $q = $fact['quimica'] ?? null;
                $qv = is_array($q) ? ($q['a_hacia_b'] ?? $q['valor'] ?? null) : null;
                $v = is_numeric($qv) ? ((float) $qv) / 100.0 : 0.0;
            } elseif ($k === 'vinculo_social') {
                $soc = $fact['social_ab']['valor'] ?? 0;
                $v = ((float) $soc) / 100.0;
            } elseif ($k === 'vinculo_romance') {
                $v = ((float) ($fact['romance_ab'] ?? 0)) / 100.0;
            } elseif ($k === 'conflicto') {
                $c = $fact['conflicto'] ?? 0;
                $v = is_numeric($c) ? -((float) $c) / 20.0 : 0.0;
            } elseif ($k === 'emocional') {
                $emo = (string) ($fact['emocional_a'] ?? 'neutro');
                if ($pid !== ($snap['participantes'][0] ?? '')) {
                    $emo = (string) ($fact['emocional_b'] ?? 'neutro');
                }
                $v = ((float) EstadoEmocional::modificadores($emo, $cal)['experiencia_encuentro']) / 20.0;
            } elseif ($k === 'lugar' || $k === 'hobbies_plan') {
                $plan = $pid === ($snap['participantes'][0] ?? '') ? ($fact['plan_a'] ?? null) : ($fact['plan_b'] ?? null);
                $ap = is_array($plan) ? (int) ($plan['aporte'] ?? 0) : 0;
                $pe = is_array($plan) ? (int) ($plan['penalizacion'] ?? 0) : 0;
                $v = ($ap - $pe) / 20.0;
            }
            $contrib[(string) $k] = $w * $v;
            $acc += $contrib[(string) $k];
        }
        $carga = $wsum <= 0 ? 0.0 : $acc / $wsum;
        if ($carga < -1) {
            $carga = -1.0;
        }
        if ($carga > 1) {
            $carga = 1.0;
        }
        return ['carga' => $carga, 'contribuciones' => $contrib];
    }
}
