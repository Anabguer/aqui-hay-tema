<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Elige lugar sin usar secretos no descubiertos.
 * Con poco conocimiento puede meter la pata (elige lo que le gusta a él).
 */
final class LugarAutonomo
{
    /**
     * @param array<string, mixed> $cal
     * @param list<string> $operativos
     */
    public static function elegir(
        array $partida,
        string $quien,
        ?string $otro,
        array $operativos,
        RngService $rng,
        ?Catalog $catalog = null,
        array $cal = []
    ): ?string {
        $atraccionBonus = (float) CalibracionConfig::get($cal, 'autonomia.atraccion_ocupacion_bonus', 0);
        $atraccionCap   = (int)   CalibracionConfig::get($cal, 'autonomia.atraccion_ocupacion_cap', 3);
        $dia  = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);

        $cands = [];
        foreach ($operativos as $lug) {
            $lug = (string) $lug;
            if ($lug === '' || $lug === 'lug_casa') {
                continue;
            }
            $w = 1.0;
            if (!ComplejoCatalog::estaAbierto($lug, $hora)) {
                continue;
            }
            $afinYo = PlanAfinidad::paraParticipante($partida, $quien, $lug, $catalog);
            if (!empty($afinYo['relacionado'])) {
                $w += 3.0;
            }
            if ((int) ($afinYo['penalizacion'] ?? 0) > 0) {
                $w *= 0.35;
            }
            $ocupActual = AforoEngine::ocupacion($partida, $lug, $dia, $hora);
            $emo = (string) ($partida['residentes'][$quien]['runtime']['estado_emocional']['id'] ?? 'neutro');
            if ($emo === EstadoEmocional::TRISTE) {
                $w += $ocupActual >= 3 ? -0.4 : 0.35;
            } elseif ($emo === EstadoEmocional::ENFADADO) {
                $w += $ocupActual >= 2 ? -0.6 : 0.4;
            } elseif ($emo === EstadoEmocional::ALEGRE) {
                $w += min(2, $ocupActual) * 0.35;
            }
            if ($atraccionBonus > 0.0 && $emo !== EstadoEmocional::ENFADADO) {
                $ocupEfectiva = min($ocupActual, $atraccionCap);
                $w += $atraccionBonus * $ocupEfectiva;
            }
            $w *= self::factorAntiRepeticion($partida, $quien, $lug, $dia);
            if ($otro !== null && $otro !== '') {
                $hobbiesSabidos = ConocimientoNpc::hobbiesConocidos($partida, $quien, $otro);
                $rechazosSabidos = ConocimientoNpc::hobbiesRechazadosConocidos($partida, $quien, $otro);
                $relLug = [];
                if ($catalog !== null) {
                    $item = $catalog->store()->item('lugares', $lug);
                    // lugares catalog is loadLugares, not store item. Use hobbies_lugares.
                    $relLug = self::hobbiesDeLugar($catalog, $lug);
                }
                foreach ($hobbiesSabidos as $h) {
                    if (in_array($h, $relLug, true)) {
                        $w += 4.0;
                    }
                }
                foreach ($rechazosSabidos as $h) {
                    if (in_array($h, $relLug, true)) {
                        $w *= 0.25;
                    }
                }
                if ($hobbiesSabidos === []) {
                    $w += $rng->nextFloat() * 0.8;
                }
            }
            $cands[] = ['lugar' => $lug, 'w' => max(0.05, $w)];
        }
        if ($cands === []) {
            return null;
        }
        $sum = 0.0;
        foreach ($cands as $c) {
            $sum += $c['w'];
        }
        $pick = $rng->nextFloat() * $sum;
        $acc = 0.0;
        foreach ($cands as $c) {
            $acc += $c['w'];
            if ($pick <= $acc) {
                return $c['lugar'];
            }
        }
        return $cands[count($cands) - 1]['lugar'];
    }

    /**
     * Penaliza repetir el mismo destino el mismo día o el anterior. No es un veto.
     */
    public static function factorAntiRepeticion(array $partida, string $quien, string $lugarId, int $dia): float
    {
        $hoy = 0;
        $ayer = 0;
        foreach ($partida['npc_autonomo']['historial_eventos'] ?? [] as $ev) {
            if (($ev['accion'] ?? '') !== 'visitar_lugar') {
                continue;
            }
            if ((string) ($ev['residente_id'] ?? '') !== $quien) {
                continue;
            }
            if ((string) ($ev['lugar'] ?? '') !== $lugarId) {
                continue;
            }
            $d = (int) ($ev['dia'] ?? 0);
            if ($d === $dia) {
                $hoy++;
            } elseif ($d === $dia - 1) {
                $ayer++;
            }
        }
        if ($hoy > 0) {
            return 0.08;
        }
        if ($ayer > 0) {
            return 0.28;
        }
        return 1.0;
    }

    /**
     * @return list<string>
     */
    public static function hobbiesDeLugar(Catalog $catalog, string $lugarId): array
    {
        $out = [];
        foreach ($catalog->store()->items('hobbies') as $item) {
            $lugs = is_array($item['lugar_ids'] ?? null) ? $item['lugar_ids'] : [];
            if (in_array($lugarId, $lugs, true) && isset($item['id'])) {
                $out[] = (string) $item['id'];
            }
        }
        return $out;
    }
}
