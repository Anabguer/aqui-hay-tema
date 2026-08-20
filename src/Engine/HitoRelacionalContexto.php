<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Factores de probabilidad de hitos. Valores internos; no se muestran al jugador.
 */
final class HitoRelacionalContexto
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function snapshotPar(array $partida, string $a, string $b, array $cal): array
    {
        $romAb = RelacionEngine::romanceHacia($partida, $a, $b);
        $romBa = RelacionEngine::romanceHacia($partida, $b, $a);
        $socAb = RelacionEngine::valorSocialHacia($partida, $a, $b);
        $socBa = RelacionEngine::valorSocialHacia($partida, $b, $a);
        $est = ParejaEngine::estado($partida, $a, $b);
        $relRom = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'] ?? null;
        $estab = null;
        if (is_array($relRom) && isset($relRom['estabilidad_pareja']['valor']) && is_numeric($relRom['estabilidad_pareja']['valor'])) {
            $estab = (int) $relRom['estabilidad_pareja']['valor'];
        }
        $diasContacto = self::diasDesdeContacto($partida, $a, $b);
        $oportunidadDias = (int) CalibracionConfig::get($cal, 'hitos_relacionales.oportunidad_dias_contacto', 5);
        return [
            'a' => $a,
            'b' => $b,
            'romance_ab' => $romAb === null ? 0 : (int) $romAb,
            'romance_ba' => $romBa === null ? 0 : (int) $romBa,
            'social_ab' => $socAb,
            'social_ba' => $socBa,
            'social_media' => (int) round(($socAb + $socBa) / 2),
            'estado_pareja' => $est,
            'estabilidad' => $estab,
            'dias_sin_contacto' => $diasContacto,
            'oportunidad' => $diasContacto !== null && $diasContacto <= $oportunidadDias,
            'conocidos' => RelacionEngine::seConocen($partida, $a, $b),
            'pareja_de_a' => TerceroRomantico::parejaDe($partida, $a),
            'pareja_de_b' => TerceroRomantico::parejaDe($partida, $b),
        ];
    }

    public static function diasDesdeContacto(array $partida, string $a, string $b): ?int
    {
        $soc = RelacionEngine::obtenerEntre($partida, $a, $b)['social'] ?? null;
        if (!is_array($soc)) {
            return null;
        }
        $uc = $soc['ultimo_contacto'] ?? $soc['ultimo_contacto_significativo'] ?? null;
        if (!is_array($uc) || !isset($uc['dia'])) {
            return null;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        return max(0, $dia - (int) $uc['dia']);
    }

    /**
     * @param array<string, mixed> $tabla
     */
    public static function multTabla(array $tabla, int $valor): float
    {
        if ($tabla === []) {
            return 1.0;
        }
        ksort($tabla, SORT_NUMERIC);
        $m = 1.0;
        foreach ($tabla as $umbral => $mult) {
            if ($valor >= (int) $umbral) {
                $m = (float) $mult;
            }
        }
        return $m;
    }

    /**
     * @return list<string>
     */
    public static function rasgosDe(array $partida, string $id): array
    {
        $r = $partida['residentes'][$id] ?? null;
        if (!is_array($r)) {
            return [];
        }
        $perfil = $r['runtime']['perfil_partida'] ?? [];
        $ras = $perfil['rasgos'] ?? $r['personalidad']['rasgos'] ?? $r['rasgos'] ?? [];
        if (!is_array($ras)) {
            return [];
        }
        $out = [];
        foreach ($ras as $x) {
            if (is_string($x) && $x !== '') {
                $out[] = $x;
            } elseif (is_array($x) && isset($x['id'])) {
                $out[] = (string) $x['id'];
            }
        }
        return $out;
    }

    /**
     * @param array<string, float|int> $mapa
     */
    public static function multRasgos(array $partida, string $id, array $mapa): float
    {
        if ($mapa === []) {
            return 1.0;
        }
        $m = 1.0;
        foreach (self::rasgosDe($partida, $id) as $rasgo) {
            if (isset($mapa[$rasgo])) {
                $m *= (float) $mapa[$rasgo];
            }
        }
        return max(0.05, min(3.0, $m));
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function emocionModIniciativa(array $partida, string $id, array $cal): float
    {
        $r = $partida['residentes'][$id] ?? [];
        $emoId = 'neutro';
        if (is_array($r)) {
            $ee = $r['runtime']['estado_emocional'] ?? $r['runtime']['animo'] ?? null;
            if (is_array($ee)) {
                $emoId = (string) ($ee['id'] ?? 'neutro');
            } elseif (is_string($ee)) {
                $emoId = $ee;
            }
        }
        $mods = EstadoEmocional::modificadores($emoId, $cal);
        $peso = (float) CalibracionConfig::get($cal, 'hitos_relacionales.factores.emocion_iniciativa_romantica_peso', 0.08);
        return 1.0 + ((float) ($mods['iniciativa_romantica'] ?? 0)) * $peso;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function enCooldown(array $partida, string $a, string $b, string $tipo, array $cal): bool
    {
        $cd = (int) CalibracionConfig::get($cal, 'hitos_relacionales.cooldowns_dias.' . $tipo, 0);
        if ($cd <= 0) {
            return false;
        }
        $hits = RelacionBitacora::entre($partida, $a, $b, $tipo);
        if ($hits === []) {
            return false;
        }
        $last = $hits[count($hits) - 1];
        $diaH = (int) (($last['fecha']['dia'] ?? 0));
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        return ($dia - $diaH) < $cd;
    }

    /**
     * @param list<string> $tipos
     */
    public static function cuentaHitos(array $partida, string $a, string $b, array $tipos): int
    {
        $n = 0;
        foreach ($tipos as $t) {
            $n += count(RelacionBitacora::entre($partida, $a, $b, $t));
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function clampP(float $p, array $cal, ?float $capOverride = null): float
    {
        $cap = $capOverride ?? (float) CalibracionConfig::get($cal, 'hitos_relacionales.p_cap', 0.85);
        if ($p < 0.0) {
            return 0.0;
        }
        if ($p > $cap) {
            return $cap;
        }
        return $p;
    }

    /**
     * @param array{0:int,1:int}|list<int> $rango
     */
    public static function randRango(RngService $rng, array $rango): int
    {
        $lo = (int) ($rango[0] ?? 0);
        $hi = (int) ($rango[1] ?? $lo);
        if ($hi < $lo) {
            $t = $lo;
            $lo = $hi;
            $hi = $t;
        }
        return $rng->nextInt($lo, $hi);
    }

    public static function bumpRomance(array &$partida, string $desde, string $hacia, int $delta): void
    {
        $cur = RelacionEngine::romanceHacia($partida, $desde, $hacia);
        $base = $cur === null ? 0 : (int) $cur;
        RelacionEngine::setRomanceHacia($partida, $desde, $hacia, RelacionBandas::clampRomance($base + $delta));
    }
}
