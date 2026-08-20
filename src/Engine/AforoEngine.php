<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Compromisos confirmados reservan plaza. Autonomía usa el resto. */
final class AforoEngine
{
    public static function ocupacion(array $partida, string $lugarId, int $dia, int $hora): int
    {
        $n = 0;
        foreach (EncuentroEngine::list($partida) as $enc) {
            if ((string) ($enc['lugar'] ?? '') !== $lugarId) {
                continue;
            }
            if (!LugarAtributos::ocupaHora($enc, $dia, $hora)) {
                continue;
            }
            $n += count($enc['participantes'] ?? []);
        }
        foreach ($partida['npc_autonomo']['planes_pendientes'] ?? [] as $plan) {
            if ((string) ($plan['lugar'] ?? '') !== $lugarId) {
                continue;
            }
            if (!LugarAtributos::ocupaHora($plan, $dia, $hora)) {
                continue;
            }
            $n += count($plan['participantes'] ?? []);
        }
        return $n;
    }

    public static function ocupacionComplejo(array $partida, string $complejoId, int $dia, int $hora): int
    {
        $n = 0;
        foreach (ComplejoCatalog::destinosDeComplejo($complejoId) as $lug) {
            $n += self::ocupacion($partida, $lug, $dia, $hora);
        }
        return $n;
    }

    public static function cabe(array $partida, string $lugarId, int $dia, int $hora, int $quien, ?array $lugarItem = null): bool
    {
        $attr = LugarAtributos::de($lugarId, $lugarItem);
        if ((self::ocupacion($partida, $lugarId, $dia, $hora) + $quien) > $attr['aforo']) {
            return false;
        }
        $cid = ComplejoCatalog::complejoId($lugarId);
        if ($cid === null) {
            return true;
        }
        $cap = ComplejoCatalog::aforoComplejo($cid);
        if ($cap <= 0) {
            return true;
        }
        return (self::ocupacionComplejo($partida, $cid, $dia, $hora) + $quien) <= $cap;
    }

    public static function cabeIntervalo(
        array $partida,
        string $lugarId,
        int $dia,
        int $hora,
        int $horas,
        int $quien,
        ?array $lugarItem = null
    ): bool {
        $horas = max(1, $horas);
        $d = $dia;
        $h = $hora;
        for ($i = 0; $i < $horas; $i++) {
            if (!self::cabe($partida, $lugarId, $d, $h, $quien, $lugarItem)) {
                return false;
            }
            $h++;
            if ($h >= 24) {
                $h = 0;
                $d++;
            }
        }
        return true;
    }
}
