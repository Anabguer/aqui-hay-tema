<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Voluntad;

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;

/**
 * A3 — Evaluador barato de voluntad para salidas individuales autónomas (H3).
 *
 * score = base + aceptar_planes(emoción) + social medio del pueblo + hobby match.
 * Fallo = no sale ese tick (sin consumir cupo diario).
 */
final class VoluntadSalidaIndividual
{
    /**
     * @param list<string> $lugaresOps
     * @param array<string, mixed> $cal
     * @return array{acepta:bool,score:int,p:float,tirada:float,desglose:array<string,mixed>}
     */
    public static function evaluar(
        array $partida,
        string $residenteId,
        array $lugaresOps,
        Catalog $catalog,
        array $cal,
        RngService $rng
    ): array {
        $desglose = self::desglose($partida, $residenteId, $lugaresOps, $catalog, $cal);
        $score = (int) ($desglose['score'] ?? 0);
        $p = self::scoreAP($score, $cal);
        $tirada = $rng->nextFloat();
        return [
            'acepta' => $tirada < $p,
            'score' => $score,
            'p' => $p,
            'tirada' => $tirada,
            'desglose' => $desglose,
        ];
    }

    /**
     * @param list<string> $lugaresOps
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function desglose(
        array $partida,
        string $residenteId,
        array $lugaresOps,
        Catalog $catalog,
        array $cal
    ): array {
        $base = (int) CalibracionConfig::get(
            $cal,
            'autonomia.salida_individual_voluntad.base',
            CalibracionConfig::get($cal, 'voluntad.base', 48)
        );
        $emo = (string) ($partida['residentes'][$residenteId]['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
        $modEmo = (int) (EstadoEmocional::modificadores($emo, $cal)['aceptar_planes'] ?? 0);
        $socialMedio = self::socialMedioPueblo($partida, $residenteId);
        $factorSoc = (float) CalibracionConfig::get($cal, 'autonomia.salida_individual_voluntad.mod_social_medio_factor', 0.32);
        $modSoc = (int) round($socialMedio * $factorSoc);
        $hobbyMatch = self::tieneHobbyMatch($partida, $residenteId, $lugaresOps, $catalog);
        $bonusHobby = (int) CalibracionConfig::get($cal, 'autonomia.salida_individual_voluntad.bonus_hobby_match', 10);
        $modHobby = $hobbyMatch ? $bonusHobby : 0;
        $score = max(0, min(100, $base + $modEmo + $modSoc + $modHobby));
        return [
            'score' => $score,
            'base' => $base,
            'estado_emocional' => $emo,
            'mod_aceptar_planes' => $modEmo,
            'social_medio_pueblo' => $socialMedio,
            'mod_social_medio' => $modSoc,
            'hobby_match' => $hobbyMatch,
            'mod_hobby_match' => $modHobby,
        ];
    }

    public static function socialMedioPueblo(array $partida, string $quien): float
    {
        $ids = array_keys($partida['residentes'] ?? []);
        $sum = 0.0;
        $n = 0;
        foreach ($ids as $otro) {
            $otro = (string) $otro;
            if ($otro === $quien) {
                continue;
            }
            $sum += RelacionEngine::valorSocialHacia($partida, $quien, $otro);
            $n++;
        }

        return $n > 0 ? $sum / $n : 0.0;
    }

    /**
     * @param list<string> $lugaresOps
     */
    public static function tieneHobbyMatch(array $partida, string $quien, array $lugaresOps, Catalog $catalog): bool
    {
        if ($lugaresOps === []) {
            return false;
        }
        $perfil = PerfilPartida::deOLegacy($partida, $quien, $catalog);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        $store = $catalog->store();
        foreach ($hobbies as $h) {
            $item = $store->hobby((string) $h);
            if (!is_array($item)) {
                continue;
            }
            foreach ($item['lugar_ids'] ?? [] as $lug) {
                if (in_array((string) $lug, $lugaresOps, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function scoreAP(int $score, array $cal): float
    {
        $pMin = (float) CalibracionConfig::get($cal, 'voluntad.p_min', 0.08);
        $pMax = (float) CalibracionConfig::get($cal, 'voluntad.p_max', 0.94);
        $excelente = (int) CalibracionConfig::get($cal, 'voluntad.score_excelente', 88);
        $pExc = (float) CalibracionConfig::get($cal, 'voluntad.p_excelente', 0.92);
        $p = $pMin + (max(0, min(100, $score)) / 100.0) * ($pMax - $pMin);
        if ($score >= $excelente) {
            $p = $pExc;
        }

        return min($pMax, max($pMin, $p));
    }
}
