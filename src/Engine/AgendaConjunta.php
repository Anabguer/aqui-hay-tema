<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Primera franja razonable en que todos están libres. No pisa agenda confirmada. */
final class AgendaConjunta
{
    /**
     * @param list<string> $ids
     * @return array{ok:bool,dia?:int,hora?:int,error?:string}
     */
    public static function primeraFranja(
        array $partida,
        array $ids,
        int $duracionHoras = 1,
        int $horaMin = 9,
        int $horaMax = 22,
        int $diaDesde = 0,
        int $diasBuscar = 7,
        ?string $lugarId = null
    ): array {
        $duracionHoras = max(1, $duracionHoras);
        $dia0 = $diaDesde > 0 ? $diaDesde : (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $horaAhora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        for ($d = 0; $d < $diasBuscar; $d++) {
            $dia = $dia0 + $d;
            $h0 = $horaMin;
            if ($d === 0 && $dia === (int) ($partida['reloj']['dia_pueblo'] ?? 1)) {
                $h0 = max($horaMin, $horaAhora + 1);
            }
            for ($h = $h0; $h <= $horaMax - $duracionHoras; $h++) {
                if (self::libres($partida, $ids, $dia, $h, $duracionHoras) === false) {
                    continue;
                }
                if ($lugarId !== null && $lugarId !== '') {
                    $okAforo = true;
                    for ($k = 0; $k < $duracionHoras; $k++) {
                        if (!AforoEngine::cabe($partida, $lugarId, $dia, $h + $k, count($ids))) {
                            $okAforo = false;
                            break;
                        }
                    }
                    if (!$okAforo) {
                        continue;
                    }
                }
                return ['ok' => true, 'dia' => $dia, 'hora' => $h];
            }
        }
        return ['ok' => false, 'error' => 'sin_hueco'];
    }

    /**
     * @param list<string> $ids
     */
    public static function libres(array $partida, array $ids, int $dia, int $horaIni, int $duracionHoras): bool
    {
        foreach ($ids as $id) {
            for ($k = 0; $k < $duracionHoras; $k++) {
                $h = $horaIni + $k;
                $disp = AgendaEngine::estaDisponible($partida, (string) $id, $dia, $h);
                if (!($disp['disponible'] ?? false)) {
                    return false;
                }
            }
        }
        return true;
    }
}
