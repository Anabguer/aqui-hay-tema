<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Reveal inicial al incorporar + descubrimiento contextual.
 * El jugador no ve la ficha interna completa. No revela por paso del tiempo.
 *
 * Throttling:
 *   - Revelación inicial controlada por calibracion (hobbies_iniciales, rasgos_iniciales)
 *   - Cap global diario: discovery.max_por_dia
 *   - Probabilidad por encuentro: discovery.prob_por_encuentro
 *   - Cooldown por residente: discovery.cooldown_dias_por_residente
 */
final class DiscoveryReveal
{
    public const HOBBY = 'hobby';
    public const RASGO = 'rasgo';

    /**
     * Revelación inicial al incorporar residente.
     * Controlado por calibracion: discovery.hobbies_iniciales y discovery.rasgos_iniciales.
     *
     * @param array<string, mixed> $cal
     */
    public static function alIncorporar(array &$partida, string $residenteId, array $cal = []): array
    {
        $perfil = PerfilPartida::de($partida, $residenteId);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? array_values($perfil['hobbies']) : [];
        $rasgos = is_array($perfil['rasgos'] ?? null) ? array_values($perfil['rasgos']) : [];
        $nHob = (int) CalibracionConfig::get($cal, 'discovery.hobbies_iniciales', 0);
        $nRas = (int) CalibracionConfig::get($cal, 'discovery.rasgos_iniciales', 0);
        $revelados = [];
        for ($i = 0; $i < $nHob && $i < count($hobbies); $i++) {
            $hid = (string) $hobbies[$i];
            $revelados[] = self::registrarJugador($partida, $residenteId, ConocimientoNpc::campoHobby($hid), $hid, 'reveal_inicial');
        }
        for ($i = 0; $i < $nRas && $i < count($rasgos); $i++) {
            $rid = (string) $rasgos[$i];
            $revelados[] = self::registrarJugador($partida, $residenteId, ConocimientoNpc::campoRasgo($rid), $rid, 'reveal_inicial');
        }
        return ['ok' => true, 'revelados' => array_values(array_filter($revelados))];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function registrarJugador(
        array &$partida,
        string $residenteId,
        string $campo,
        $valor,
        string $origen,
        ?string $correlacionId = null
    ): ?array {
        if (DiscoveryEngine::estado($partida, $residenteId, $campo) === DiscoveryEngine::DESCUBIERTO) {
            return null;
        }
        return DiscoveryEngine::registrar($partida, $residenteId, $campo, $valor, $origen, $correlacionId);
    }

    /**
     * Un evento declara qué puede descubrirse y quién lo descubre.
     * Throttled: max_por_dia global, prob_por_encuentro, cooldown por residente.
     *
     * @param list<array{campo:string,valor?:mixed,observadores?:list<string>}> $candidatos
     * @param array<string, mixed> $cal
     */
    public static function aplicarEvento(
        array &$partida,
        array $candidatos,
        array $cal,
        string $origen = 'evento',
        ?string $correlacionId = null,
        ?RngService $rng = null
    ): array {
        $max = (int) CalibracionConfig::get($cal, 'discovery.max_por_experiencia', 2);
        $maxDia = (int) CalibracionConfig::get($cal, 'discovery.max_por_dia', 3);
        $probEncuentro = (float) CalibracionConfig::get($cal, 'discovery.prob_por_encuentro', 0.4);
        $cooldownDias = (int) CalibracionConfig::get($cal, 'discovery.cooldown_dias_por_residente', 2);

        // Initialize daily counter
        if (!isset($partida['discovery_dia'])) {
            $partida['discovery_dia'] = ['dia' => 0, 'count' => 0, 'por_residente' => []];
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ((int) $partida['discovery_dia']['dia'] !== $dia) {
            $partida['discovery_dia'] = ['dia' => $dia, 'count' => 0, 'por_residente' => []];
        }

        $hechos = [];
        foreach ($candidatos as $c) {
            if (count($hechos) >= $max) {
                break;
            }
            // Daily cap
            if ($partida['discovery_dia']['count'] >= $maxDia) {
                break;
            }
            if (!is_array($c)) {
                continue;
            }
            $campo = (string) ($c['campo'] ?? '');
            if ($campo === '') {
                continue;
            }
            $valor = $c['valor'] ?? true;
            $obs = is_array($c['observadores'] ?? null) ? $c['observadores'] : ['jugador'];
            $residente = (string) ($c['residente_id'] ?? '');

            // Probability gate: encuentro and casual both go through throttle
            if (in_array($origen, ['encuentro', 'casual'], true) && $probEncuentro < 1.0) {
                if ($rng === null) {
                    $rng = RngService::fromPartida($partida);
                }
                if ($rng->nextFloat() >= $probEncuentro) {
                    // No discovery — do NOT increment daily counter (only real discoveries count)
                    continue;
                }
            }

            // Per-resident cooldown
            if ($residente !== '' && $cooldownDias > 0) {
                $ultDesc = (int) ($partida['discovery_dia']['por_residente'][$residente]['ultimo_dia'] ?? 0);
                if ($ultDesc > 0 && ($dia - $ultDesc) < $cooldownDias) {
                    // Cooldown active — do NOT increment daily counter
                    continue;
                }
            }

            foreach ($obs as $quien) {
                $quien = (string) $quien;
                if ($quien === 'jugador' || $quien === 'celestine') {
                    if ($residente !== '') {
                        $row = self::registrarJugador($partida, $residente, $campo, $valor, $origen, $correlacionId);
                        if ($row !== null) {
                            $hechos[] = ['quien' => 'jugador', 'de' => $residente, 'campo' => $campo];
                            $partida['discovery_dia']['count']++;
                            if ($residente !== '') {
                                $partida['discovery_dia']['por_residente'][$residente]['ultimo_dia'] = $dia;
                            }
                        }
                    }
                    continue;
                }
                if ($residente !== '' && $quien !== '') {
                    $n = ConocimientoNpc::revelar($partida, $quien, $residente, [$campo], $origen);
                    if ($n > 0) {
                        $hechos[] = ['quien' => $quien, 'de' => $residente, 'campo' => $campo];
                        $partida['discovery_dia']['count']++;
                        $partida['discovery_dia']['por_residente'][$residente]['ultimo_dia'] = $dia;
                    }
                }
            }
        }
        return ['ok' => true, 'descubiertos' => $hechos];
    }

    /**
     * Preferencias descubribles. Contextual si el lugar encaja con un hobby preferido/rechazado.
     *
     * @return list<array<string, mixed>>
     */
    public static function candidatosPreferencias(
        array $partida,
        string $residenteId,
        ?string $lugarId = null,
        ?Catalog $catalog = null
    ): array {
        $perfil = PerfilPartida::de($partida, $residenteId);
        if (!is_array($perfil)) {
            return [];
        }
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        $lugarH = [];
        if (is_string($lugarId) && $lugarId !== '' && $catalog !== null) {
            $lugarH = LugarAutonomo::hobbiesDeLugar($catalog, $lugarId);
        }
        $grupos = [
            ['hobbies_pos', 'hobby', true],
            ['hobbies_neg', 'hobby', false],
            ['personalidad_pos', 'personalidad', true],
            ['personalidad_neg', 'personalidad', false],
            ['visual_pos', 'visual', true],
            ['visual_neg', 'visual', false],
        ];
        $out = [];
        foreach ($grupos as $g) {
            $key = $g[0];
            $tipo = $g[1];
            $pos = $g[2];
            $ids = is_array($prefs[$key] ?? null) ? $prefs[$key] : [];
            foreach ($ids as $id) {
                if (!is_string($id) || $id === '') {
                    continue;
                }
                $campo = $pos
                    ? ConocimientoNpc::campoGusto($tipo, $id)
                    : ConocimientoNpc::campoRechazo($tipo, $id);
                if (DiscoveryEngine::estado($partida, $residenteId, $campo) === DiscoveryEngine::DESCUBIERTO) {
                    continue;
                }
                $contextual = $tipo === 'hobby' && $lugarH !== [] && in_array($id, $lugarH, true);
                $row = [
                    'residente_id' => $residenteId,
                    'campo' => $campo,
                    'valor' => $id,
                    'observadores' => ['jugador'],
                    'contextual' => $contextual,
                ];
                if ($contextual) {
                    array_unshift($out, $row);
                } else {
                    $out[] = $row;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Candidatos de discovery en encuentro: máx. 1 hobby o rasgo por residente, luego preferencias.
     * Permite que gusto/rechazo compita desde citas tempranas (cupo sigue en aplicarEvento).
     *
     * @return list<array<string, mixed>>
     */
    public static function candidatosEncuentro(
        array $partida,
        string $a,
        string $b,
        array $encuentro = [],
        ?Catalog $catalog = null
    ): array {
        $out = [];
        $lugar = isset($encuentro['lugar']) ? (string) $encuentro['lugar'] : null;
        foreach ([$a, $b] as $rid) {
            $perfil = PerfilPartida::deOLegacy($partida, $rid, $catalog);
            $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
            $rasgos = is_array($perfil['rasgos'] ?? null) ? $perfil['rasgos'] : [];
            $uno = self::candidatoHobbyORasgo($partida, $rid, $hobbies, $rasgos);
            if ($uno !== null) {
                $out[] = $uno;
            }
            foreach (self::candidatosPreferencias($partida, $rid, $lugar, $catalog) as $pref) {
                $out[] = $pref;
            }
        }
        return $out;
    }

    /**
     * @param list<mixed> $hobbies
     * @param list<mixed> $rasgos
     * @return array<string, mixed>|null
     */
    private static function candidatoHobbyORasgo(array $partida, string $rid, array $hobbies, array $rasgos): ?array
    {
        foreach ($hobbies as $h) {
            if (!is_string($h) || $h === '') {
                continue;
            }
            if (DiscoveryEngine::estado($partida, $rid, ConocimientoNpc::campoHobby($h)) === DiscoveryEngine::DESCUBIERTO) {
                continue;
            }
            return [
                'residente_id' => $rid,
                'campo' => ConocimientoNpc::campoHobby($h),
                'valor' => $h,
                'observadores' => ['jugador'],
            ];
        }
        foreach ($rasgos as $r) {
            if (!is_string($r) || $r === '') {
                continue;
            }
            if (DiscoveryEngine::estado($partida, $rid, ConocimientoNpc::campoRasgo($r)) === DiscoveryEngine::DESCUBIERTO) {
                continue;
            }
            return [
                'residente_id' => $rid,
                'campo' => ConocimientoNpc::campoRasgo($r),
                'valor' => $r,
                'observadores' => ['jugador'],
            ];
        }
        return null;
    }

    /**
     * Filtra hobbies/rasgos/gustos no descubiertos por el jugador.
     *
     * @param array<string, mixed> $campos
     * @return array<string, mixed>
     */
    public static function ocultarNoDescubierto(array $partida, string $residenteId, array $campos): array
    {
        $perfil = PerfilPartida::de($partida, $residenteId);
        if (!is_array($perfil)) {
            return $campos;
        }
        $hobbies = is_array($perfil['hobbies'] ?? null) ? array_values($perfil['hobbies']) : [];
        $rasgos = is_array($perfil['rasgos'] ?? null) ? array_values($perfil['rasgos']) : [];
        $visH = [];
        foreach ($hobbies as $h) {
            if (is_string($h) && $h !== '' && DiscoveryEngine::estado($partida, $residenteId, ConocimientoNpc::campoHobby($h)) === DiscoveryEngine::DESCUBIERTO) {
                $visH[] = $h;
            }
        }
        $visR = [];
        foreach ($rasgos as $r) {
            if (is_string($r) && $r !== '' && DiscoveryEngine::estado($partida, $residenteId, ConocimientoNpc::campoRasgo($r)) === DiscoveryEngine::DESCUBIERTO) {
                $visR[] = $r;
            }
        }
        $campos['vida.hobby_principal'] = $visH[0] ?? null;
        $campos['vida.hobbies_secundarios'] = array_slice($visH, 1);
        $campos['vida.rasgos_publicos'] = $visR;
        $campos['vida.rasgos_ocultos'] = [];
        return $campos;
    }

    public static function jugadorSabeHobby(array $partida, string $residenteId, string $hobbyId): bool
    {
        return DiscoveryEngine::estado($partida, $residenteId, ConocimientoNpc::campoHobby($hobbyId)) === DiscoveryEngine::DESCUBIERTO;
    }
}
