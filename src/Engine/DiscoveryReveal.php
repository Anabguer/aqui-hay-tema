<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Reveal inicial al incorporar + descubrimiento contextual.
 * El jugador no ve la ficha interna completa. No revela por paso del tiempo.
 */
final class DiscoveryReveal
{
    public const HOBBY = 'hobby';
    public const RASGO = 'rasgo';

    /**
     * 1 hobby + 1 rasgo para Celestine. El resto queda ???.
     *
     * @param array<string, mixed> $cal
     */
    public static function alIncorporar(array &$partida, string $residenteId, array $cal = []): array
    {
        $perfil = PerfilPartida::de($partida, $residenteId);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? array_values($perfil['hobbies']) : [];
        $rasgos = is_array($perfil['rasgos'] ?? null) ? array_values($perfil['rasgos']) : [];
        $nHob = (int) CalibracionConfig::get($cal, 'discovery.hobbies_iniciales', 1);
        $nRas = (int) CalibracionConfig::get($cal, 'discovery.rasgos_iniciales', 1);
        $revelados = [];
        for ($i = 0; $i < $nHob && $i < count($hobbies); $i++) {
            $hid = (string) $hobbies[$i];
            $revelados[] = self::registrarJugador($partida, $residenteId, ConocimientoNpc::campoHobby($hid), $hid, 'reveal_inicial');
            if ($i === 0) {
                // vida.hobby_principal se deja para descubrimiento contextual/tests;
                // el reveal inicial usa hobby:{id}.
            }
        }
        for ($i = 0; $i < $nRas && $i < count($rasgos); $i++) {
            $rid = (string) $rasgos[$i];
            $revelados[] = self::registrarJugador($partida, $residenteId, ConocimientoNpc::campoRasgo($rid), $rid, 'reveal_inicial');
            if ($i === 0) {
                // rasgo inicial vía rasgo:{id}
            }
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
     * Máximo muy pocos por experiencia.
     *
     * @param list<array{campo:string,valor?:mixed,observadores?:list<string>}> $candidatos
     * @param array<string, mixed> $cal
     */
    public static function aplicarEvento(
        array &$partida,
        array $candidatos,
        array $cal,
        string $origen = 'evento',
        ?string $correlacionId = null
    ): array {
        $max = (int) CalibracionConfig::get($cal, 'discovery.max_por_experiencia', 2);
        $hechos = [];
        foreach ($candidatos as $c) {
            if (count($hechos) >= $max) {
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
            foreach ($obs as $quien) {
                $quien = (string) $quien;
                if ($quien === 'jugador' || $quien === 'celestine') {
                    if ($residente !== '') {
                        $row = self::registrarJugador($partida, $residente, $campo, $valor, $origen, $correlacionId);
                        if ($row !== null) {
                            $hechos[] = ['quien' => 'jugador', 'de' => $residente, 'campo' => $campo];
                        }
                    }
                    continue;
                }
                if ($residente !== '' && $quien !== '') {
                    $n = ConocimientoNpc::revelar($partida, $quien, $residente, [$campo], $origen);
                    if ($n > 0) {
                        $hechos[] = ['quien' => $quien, 'de' => $residente, 'campo' => $campo];
                    }
                }
            }
        }
        return ['ok' => true, 'descubiertos' => $hechos];
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
