<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Coincidencia ≠ interacción. Máximo una casual significativa por residente/salida.
 * Normalmente dos personas. Contacto leve.
 *
 * Tubo canónico: resolverGrupo() → probabilidadPar() → ejecutarPar().
 */
final class InteraccionCasual
{
  private const PROB_TOPE = 0.85;

    /**
     * @param list<string> $residentes
     * @param array<string, mixed> $cal
     * @param array{bonus_patron?: bool, solo_desconocidos?: bool} $opciones
     * @return list<array<string, mixed>>
     */
    public static function resolverGrupo(
        array &$partida,
        array $residentes,
        string $lugarId,
        int $dia,
        int $hora,
        RngService $rng,
        array $cal,
        ?Catalog $catalog = null,
        array $opciones = []
    ): array {
        $residentes = array_values(array_unique($residentes));
        if (count($residentes) < 2) {
            return [];
        }
        $bonusPatron = (bool) ($opciones['bonus_patron'] ?? false);
        $soloDesconocidos = (bool) ($opciones['solo_desconocidos'] ?? false);
        SimFunnelProbe::on($partida, 'casual', ['ev' => 'grupo_copresencia', '_k' => 'grupo_copresencia', 'n' => count($residentes), 'lugar' => $lugarId, '_solo_conteo' => true]);
        $maxPor = (int) CalibracionConfig::get($cal, 'coincidencias.max_significativas_por_salida', 1);
        $ya = [];
        $hechos = [];
        $pares = self::paresPonderados($partida, $residentes, $rng, $cal);
        foreach ($pares as $par) {
            $a = $par[0];
            $b = $par[1];
            if ($soloDesconocidos && RelacionEngine::seConocen($partida, $a, $b)) {
                continue;
            }
            if ((int) ($ya[$a] ?? 0) >= $maxPor || (int) ($ya[$b] ?? 0) >= $maxPor) {
                continue;
            }
            $clave = self::claveIntento($a, $b, $dia, $hora, $lugarId);
            if (self::yaIntentado($partida, $clave)) {
                continue;
            }
            self::marcarIntentado($partida, $clave);

            $extra = $bonusPatron ? self::bonusPatronPar($partida, [$a, $b], $lugarId, $dia, $cal) : 0.0;
            $p = self::probabilidadPar($partida, $a, $b, $cal, $extra);
            $rollCasual = $rng->nextFloat();
            $evCas = $rollCasual <= $p ? 'intento_ok' : 'intento_fallido';
            SimFunnelProbe::on($partida, 'casual', ['ev' => $evCas, '_k' => $evCas, 'a' => $a, 'b' => $b, 'p' => round($p, 3), 'se_conocen' => RelacionEngine::seConocen($partida, $a, $b)]);
            if ($rollCasual > $p) {
                continue;
            }
            $r = self::ejecutarPar($partida, $a, $b, $lugarId, $cal, $rng, $catalog);
            $ya[$a] = (int) ($ya[$a] ?? 0) + 1;
            $ya[$b] = (int) ($ya[$b] ?? 0) + 1;
            $hechos[] = $r;
        }
        return $hechos;
    }

    /**
     * Probabilidad canónica de intento casual A↔B (sin garantizar contacto).
     *
     * @param array<string, mixed> $cal
     */
    public static function probabilidadPar(
        array $partida,
        string $a,
        string $b,
        array $cal,
        float $bonusExtra = 0.0
    ): float {
        $p = (float) CalibracionConfig::get($cal, 'coincidencias.prob_interactuar_base', 0.22) + $bonusExtra;
        if (RelacionEngine::seConocen($partida, $a, $b)) {
            $p += 0.12;
        }
        $q = QuimicaEngine::valorHacia($partida, $a, $b);
        if ($q !== null) {
            $p += $q / 400.0;
        }
        $emoA = EstadoEmocional::modificadores(
            (string) ($partida['residentes'][$a]['runtime']['estado_emocional']['id'] ?? 'neutro'),
            $cal
        );
        $p += ((int) ($emoA['iniciativa_social'] ?? 0)) / 200.0;

        return min(self::PROB_TOPE, $p);
    }

    /**
     * Bonus por patrón repetido par+lugar (coincidencias históricas).
     *
     * @param list<string> $ids
     */
    public static function bonusPatronPar(array $partida, array $ids, string $lugarId, int $dia, array $cal): float
    {
        $minDias = (int) CalibracionConfig::get($cal, 'coincidencias.interactuar_min_dias_patron', 2);
        $porDia = (float) CalibracionConfig::get($cal, 'coincidencias.interactuar_bonus_por_dia_patron', 0.06);
        $maxBonus = (float) CalibracionConfig::get($cal, 'coincidencias.interactuar_bonus_max', 0.28);

        $dias = CotilleoNarrativo::diasPatronParLugar($partida, $ids, $lugarId, $dia, $cal);
        if ($dias < $minDias) {
            return 0.0;
        }

        return min($maxBonus, ($dias - $minDias + 1) * $porDia);
    }

    /**
     * @param list<string> $ids
     * @return list<array{0:string,1:string}>
     */
    private static function paresPonderados(array $partida, array $ids, RngService $rng, array $cal): array
    {
        $pares = [];
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $w = 1.0;
                $a = (string) $ids[$i];
                $b = (string) $ids[$j];
                if (RelacionEngine::seConocen($partida, $a, $b)) {
                    $w += 2.2;
                }
                $soc = abs(RelacionEngine::valorSocialHacia($partida, $a, $b));
                $w += $soc / 30.0;
                $pares[] = ['a' => $a, 'b' => $b, 'w' => $w];
            }
        }
        usort($pares, static function ($x, $y) use ($rng) {
            if ($x['w'] === $y['w']) {
                return $rng->nextInt(0, 1) === 0 ? -1 : 1;
            }
            return $y['w'] <=> $x['w'];
        });
        $out = [];
        foreach ($pares as $p) {
            $out[] = [$p['a'], $p['b']];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function ejecutarPar(
        array &$partida,
        string $a,
        string $b,
        string $lugarId,
        array $cal,
        RngService $rng,
        ?Catalog $catalog = null
    ): array {
        $calidad = (string) CalibracionConfig::get($cal, 'coincidencias.calidad_casual', ContactoCalidad::LEVE);
        RelacionEngine::registrarContacto($partida, $a, $b, $calidad, $cal, 1);
        RelacionEngine::registrarContacto($partida, $b, $a, $calidad, $cal, 1);
        $socAb = RelacionEngine::valorSocialHacia($partida, $a, $b);
        $socBa = RelacionEngine::valorSocialHacia($partida, $b, $a);
        if ($socAb < 12 || $socBa < 12) {
            SimFunnelProbe::on($partida, 'casual', ['ev' => 'quedada_soc_insuficiente', '_k' => 'quedada_soc_insuficiente', 'a' => $a, 'b' => $b, 'sa' => $socAb, 'sb' => $socBa]);
        } elseif ($rng->nextFloat() < 0.18) {
            SimFunnelProbe::on($partida, 'casual', ['ev' => 'quedada_roll_ok', '_k' => 'quedada_roll_ok', 'a' => $a, 'b' => $b]);
            self::intentarQuedadaCasual($partida, $a, $b, $cal, $rng);
        } else {
            SimFunnelProbe::on($partida, 'casual', ['ev' => 'quedada_roll_fail', '_k' => 'quedada_roll_fail', 'a' => $a, 'b' => $b]);
        }
        $disc = self::descubrimientoCasual($partida, $a, $b, $lugarId, $cal, $catalog);
        $flechazo = null;
        $prob = (float) CalibracionConfig::get($cal, 'flechazo.probabilidad', 0.006);
        $prob *= (float) CalibracionConfig::get($cal, 'flechazo.casual_mult', 0.35);
        $prob *= TerceroRomantico::multiplicador($partida, $a, $b, $cal);
        $rollFl = $rng->nextFloat();
        $evFl = $rollFl < $prob ? 'flechazo_casual_ok' : 'flechazo_casual_no';
        SimFunnelProbe::on($partida, 'flechazo', ['ev' => $evFl, '_k' => $evFl, 'p' => round($prob, 5)]);
        if ($rollFl < $prob) {
            $store = $catalog !== null ? $catalog->store() : null;
            if ($store !== null) {
                $flechazo = AccionRomantica::ejecutar($partida, 'flechazo', $a, $b, $store, $cal, true);
            }
        }
        MemoriaEventos::registrar($partida, 'casual', [$a, $b], 1, 'interaccion_casual', 'leve');
        return [
            'a' => $a,
            'b' => $b,
            'lugar' => $lugarId,
            'calidad' => $calidad,
            'descubrimientos' => $disc,
            'flechazo' => $flechazo,
        ];
    }

    private static function claveIntento(string $a, string $b, int $dia, int $hora, string $lugarId): string
    {
        $lo = $a < $b ? $a : $b;
        $hi = $a < $b ? $b : $a;

        return $dia . ':' . $hora . ':' . $lugarId . ':' . $lo . '|' . $hi;
    }

    private static function yaIntentado(array $partida, string $clave): bool
    {
        return isset($partida['_casual_intentos'][$clave]);
    }

    private static function marcarIntentado(array &$partida, string $clave): void
    {
        $partida['_casual_intentos'] ??= [];
        $partida['_casual_intentos'][$clave] = true;
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function descubrimientoCasual(
        array &$partida,
        string $a,
        string $b,
        string $lugarId,
        array $cal,
        ?Catalog $catalog
    ): array {
        $cands = [];
        $perfilB = PerfilPartida::de($partida, $b);
        $hobbies = is_array($perfilB['hobbies'] ?? null) ? $perfilB['hobbies'] : [];
        $rel = $catalog !== null ? LugarAutonomo::hobbiesDeLugar($catalog, $lugarId) : [];
        foreach ($hobbies as $h) {
            if (is_string($h) && $h !== '' && in_array($h, $rel, true)) {
                $cands[] = [
                    'residente_id' => $b,
                    'campo' => ConocimientoNpc::campoHobby($h),
                    'valor' => $h,
                    'observadores' => ['jugador', $a],
                ];
            }
        }
        return DiscoveryReveal::aplicarEvento($partida, $cands, $cal, 'casual');
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function intentarQuedadaCasual(
        array &$partida,
        string $a,
        string $b,
        array $cal,
        RngService $rng
    ): void {
        $ops = $partida['celeste']['lugares_desbloqueados'] ?? ['lug_cafeteria', 'lug_parque'];
        $lugar = (string) $ops[$rng->nextInt(0, max(0, count($ops) - 1))];
        $attr = LugarAtributos::de($lugar);
        $franja = AgendaConjunta::primeraFranja(
            $partida,
            [$a, $b],
            max(1, (int) ($attr['horas'] ?? 1)),
            9,
            22,
            (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            2,
            $lugar
        );
        if (!($franja['ok'] ?? false)) {
            SimFunnelProbe::on($partida, 'quedada_autonoma', ['ev' => 'sin_hueco_agenda', '_k' => 'sin_hueco_agenda', 'via' => 'casual']);
            return;
        }
        $tipo = 'conocerse';
        $rom = max(
            (int) (RelacionEngine::romanceHacia($partida, $a, $b) ?? 0),
            (int) (RelacionEngine::romanceHacia($partida, $b, $a) ?? 0)
        );
        if ($rom >= 22) {
            $tipo = 'romantico';
        }
        $r = EncuentroEngine::programar(
            $partida,
            [$a, $b],
            (int) $franja['dia'],
            (int) $franja['hora'],
            $tipo,
            $lugar,
            null,
            null
        );
        SimFunnelProbe::on($partida, 'quedada_autonoma', ['ev' => (($r['ok'] ?? false) ? 'programada' : 'error_programar'), '_k' => ((($r['ok'] ?? false) ? 'qc_programada_' : 'qc_error_programar') . $tipo), 'via' => 'casual', 'tipo' => $tipo, 'lugar' => $lugar]);
        if (($r['ok'] ?? false) && isset($r['encuentro']['id'])) {
            foreach ($partida['encuentros'] as $i => $enc) {
                if (($enc['id'] ?? '') === $r['encuentro']['id']) {
                    $partida['encuentros'][$i]['duracion_minutos'] = $attr['duracion_minutos'];
                    $partida['encuentros'][$i]['duracion_horas'] = $attr['horas'];
                    $partida['encuentros'][$i]['intencion'] = 'casual_quedada';
                }
            }
        }
    }
}
