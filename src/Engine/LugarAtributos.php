<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Aforo de juego y duración base de actividades. */
final class LugarAtributos
{
    /** @var array<string, array{aforo:int,duracion_minutos:int}> */
    private const FALLBACK = [
        'lug_cafeteria' => ['aforo' => 8, 'duracion_minutos' => 90],
        'lug_parque' => ['aforo' => 40, 'duracion_minutos' => 60],
        'lug_biblioteca' => ['aforo' => 12, 'duracion_minutos' => 120],
        'lug_cine' => ['aforo' => 16, 'duracion_minutos' => 150],
        'lug_plaza' => ['aforo' => 30, 'duracion_minutos' => 60],
        'lug_restaurante' => ['aforo' => 16, 'duracion_minutos' => 120],
        'lug_bar' => ['aforo' => 14, 'duracion_minutos' => 120],
        'lug_discoteca' => ['aforo' => 24, 'duracion_minutos' => 180],
        'lug_bingo' => ['aforo' => 20, 'duracion_minutos' => 150],
        'lug_arcade' => ['aforo' => 10, 'duracion_minutos' => 90],
        'lug_tienda_ropa' => ['aforo' => 8, 'duracion_minutos' => 60],
        'lug_gimnasio' => ['aforo' => 12, 'duracion_minutos' => 90],
        'lug_mirador' => ['aforo' => 8, 'duracion_minutos' => 60],
        'lug_casa' => ['aforo' => 4, 'duracion_minutos' => 120],
    ];

    /**
     * @return array{aforo:int,duracion_minutos:int,horas:int}
     */
    public static function de(?string $lugarId, ?array $item = null): array
    {
        $id = (string) $lugarId;
        $canon = ComplejoCatalog::destino($id);
        $fb = self::FALLBACK[$id] ?? ['aforo' => 12, 'duracion_minutos' => 60];
        $aforo = $canon !== null ? (int) $canon['aforo'] : $fb['aforo'];
        $dur = $canon !== null ? (int) $canon['duracion_minutos'] : $fb['duracion_minutos'];
        if (is_array($item)) {
            if (isset($item['aforo']) && is_numeric($item['aforo'])) {
                $aforo = (int) $item['aforo'];
            } elseif (isset($item['capacidad']) && is_numeric($item['capacidad'])) {
                $aforo = (int) $item['capacidad'];
            }
            if (isset($item['duracion_minutos']) && is_numeric($item['duracion_minutos'])) {
                $dur = (int) $item['duracion_minutos'];
            }
        }
        $horas = (int) max(1, (int) ceil($dur / 60));
        return ['aforo' => $aforo, 'duracion_minutos' => $dur, 'horas' => $horas];
    }

    public static function horasDeEncuentro(array $encuentro): int
    {
        if (isset($encuentro['duracion_horas']) && is_numeric($encuentro['duracion_horas'])) {
            return max(1, (int) $encuentro['duracion_horas']);
        }
        if (isset($encuentro['duracion_minutos']) && is_numeric($encuentro['duracion_minutos'])) {
            return max(1, (int) ceil(((int) $encuentro['duracion_minutos']) / 60));
        }
        return 1;
    }

    public static function ocupaHora(array $item, int $dia, int $hora): bool
    {
        if ((int) ($item['dia'] ?? -1) !== $dia) {
            return false;
        }
        $estado = (string) ($item['estado'] ?? 'programado');
        if (!in_array($estado, ['programado', 'en_curso'], true)) {
            return false;
        }
        $ini = (int) ($item['hora'] ?? -1);
        $fin = $ini + self::horasDeEncuentro($item);
        return $hora >= $ini && $hora < $fin;
    }
}
