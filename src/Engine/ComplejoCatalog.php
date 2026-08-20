<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * 6 complejos, horarios y aforos cerrados (Maestro 20/08 + Producto 20/08).
 * Cine 16:00–00:00 aforo 8; Arcade 12:00–00:00 aforo 8; Cine Game techo 12.
 */
final class ComplejoCatalog
{

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function complejos(): array
    {
        return [
            'cafe_libros' => [
                'nombre' => 'Café & Libros',
                'aforo' => 10,
                'destinos' => ['lug_cafeteria', 'lug_biblioteca', 'lug_tienda_ropa'],
            ],
            'rincon_lola' => [
                'nombre' => 'El Rincón de Lola',
                'aforo' => 10,
                'destinos' => ['lug_restaurante', 'lug_bingo'],
            ],
            'cine_game' => [
                'nombre' => 'Cine Game',
                'aforo' => 12,
                'destinos' => ['lug_cine', 'lug_arcade'],
            ],
            'mala_idea' => [
                'nombre' => 'La Mala Idea',
                'aforo' => 12,
                'destinos' => ['lug_bar', 'lug_discoteca', 'lug_karaoke'],
            ],
            'parque' => [
                'nombre' => 'Parque',
                'aforo' => 16,
                'destinos' => ['lug_parque', 'lug_picnic', 'lug_mirador'],
            ],
            'gimnasio_spa' => [
                'nombre' => 'Gimnasio & Spa',
                'aforo' => 10,
                'destinos' => ['lug_gimnasio', 'lug_spa'],
            ],
        ];
    }

    /**
     * ini inclusive, fin exclusive. fin=0 = medianoche.
     * Si ini > fin (o fin=0 con ini>0) la franja cruza medianoche.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function destinos(): array
    {
        return [
            'lug_cafeteria' => ['complejo' => 'cafe_libros', 'aforo' => 8, 'ini' => 8, 'fin' => 20, 'duracion_minutos' => 90],
            'lug_biblioteca' => ['complejo' => 'cafe_libros', 'aforo' => 6, 'ini' => 10, 'fin' => 20, 'duracion_minutos' => 120],
            'lug_tienda_ropa' => ['complejo' => 'cafe_libros', 'aforo' => 4, 'ini' => 10, 'fin' => 20, 'duracion_minutos' => 60],
            'lug_restaurante' => ['complejo' => 'rincon_lola', 'aforo' => 8, 'ini' => 13, 'fin' => 16, 'ini2' => 20, 'fin2' => 0, 'duracion_minutos' => 90],
            'lug_bingo' => ['complejo' => 'rincon_lola', 'aforo' => 8, 'ini' => 17, 'fin' => 23, 'duracion_minutos' => 120],
            'lug_cine' => ['complejo' => 'cine_game', 'aforo' => 8, 'ini' => 16, 'fin' => 0, 'duracion_minutos' => 150],
            'lug_arcade' => ['complejo' => 'cine_game', 'aforo' => 8, 'ini' => 12, 'fin' => 0, 'duracion_minutos' => 90],
            'lug_bar' => ['complejo' => 'mala_idea', 'aforo' => 8, 'ini' => 17, 'fin' => 0, 'duracion_minutos' => 120],
            'lug_discoteca' => ['complejo' => 'mala_idea', 'aforo' => 8, 'ini' => 22, 'fin' => 4, 'duracion_minutos' => 120],
            'lug_karaoke' => ['complejo' => 'mala_idea', 'aforo' => 4, 'ini' => 20, 'fin' => 3, 'duracion_minutos' => 90],
            'lug_parque' => ['complejo' => 'parque', 'aforo' => 12, 'ini' => 7, 'fin' => 22, 'duracion_minutos' => 60],
            'lug_picnic' => ['complejo' => 'parque', 'aforo' => 8, 'ini' => 10, 'fin' => 20, 'duracion_minutos' => 90],
            'lug_mirador' => ['complejo' => 'parque', 'aforo' => 4, 'ini' => 8, 'fin' => 0, 'duracion_minutos' => 60],
            'lug_gimnasio' => ['complejo' => 'gimnasio_spa', 'aforo' => 8, 'ini' => 7, 'fin' => 22, 'duracion_minutos' => 90],
            'lug_spa' => ['complejo' => 'gimnasio_spa', 'aforo' => 4, 'ini' => 10, 'fin' => 22, 'duracion_minutos' => 90],
        ];
    }

    public static function complejoId(string $lugarId): ?string
    {
        $d = self::destinos()[$lugarId] ?? null;
        return is_array($d) ? (string) $d['complejo'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function destino(string $lugarId): ?array
    {
        $d = self::destinos()[$lugarId] ?? null;
        return is_array($d) ? $d : null;
    }

    public static function estaAbierto(string $lugarId, int $hora): bool
    {
        $d = self::destino($lugarId);
        if ($d === null) {
            return true;
        }
        if (self::horaEnFranja($hora, (int) $d['ini'], (int) $d['fin'])) {
            return true;
        }
        if (isset($d['ini2'], $d['fin2']) && self::horaEnFranja($hora, (int) $d['ini2'], (int) $d['fin2'])) {
            return true;
        }
        return false;
    }

    public static function horaEnFranja(int $hora, int $ini, int $finExcl): bool
    {
        $hora = (($hora % 24) + 24) % 24;
        $ini = (($ini % 24) + 24) % 24;
        $finExcl = (($finExcl % 24) + 24) % 24;
        if ($ini === $finExcl) {
            return true;
        }
        if ($ini < $finExcl) {
            return $hora >= $ini && $hora < $finExcl;
        }
        return $hora >= $ini || $hora < $finExcl;
    }

    public static function horasRestantesAbiertas(string $lugarId, int $hora): int
    {
        if (!self::estaAbierto($lugarId, $hora)) {
            return 0;
        }
        $n = 0;
        $h = $hora;
        for ($i = 0; $i < 24; $i++) {
            if (!self::estaAbierto($lugarId, $h)) {
                break;
            }
            $n++;
            $h = ($h + 1) % 24;
        }
        return $n;
    }

    /**
     * @return list<string>
     */
    public static function destinosDeComplejo(string $complejoId): array
    {
        $c = self::complejos()[$complejoId] ?? null;
        if (!is_array($c) || !is_array($c['destinos'] ?? null)) {
            return [];
        }
        $out = [];
        foreach ($c['destinos'] as $id) {
            $out[] = (string) $id;
        }
        return $out;
    }

    public static function aforoComplejo(string $complejoId): int
    {
        $c = self::complejos()[$complejoId] ?? null;
        return is_array($c) ? (int) ($c['aforo'] ?? 0) : 0;
    }
}
